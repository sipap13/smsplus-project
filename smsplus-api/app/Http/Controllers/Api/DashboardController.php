<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use App\Services\EtlMonitorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
        protected EtlMonitorService $monitor,
    ) {}

    private function getLatestOccDate(): string
    {
        $detailMax = DB::table('ra_t_occ_cdr_detail')->max('start_date');
        $aggMax = DB::table('ra_t_occ_agg')->max('start_date');

        if (! $detailMax) {
            return $aggMax ?: now()->toDateString();
        }

        if (! $aggMax) {
            return $detailMax;
        }

        return max($detailMax, $aggMax);
    }

    private function occDetailBaseQuery(string $startDate, string $endDate, ?array $callTypes = null)
    {
        $query = DB::table('ra_t_occ_cdr_detail as o')
            ->where('o.start_date', '>=', $startDate)
            ->where('o.start_date', '<=', $endDate)
            ->where(function ($q) {
                $q->whereNull('o.datasource')
                  ->orWhereNotIn('o.datasource', ['OCC_AGG', 'DB_OCC_AGG']);
            });

        if ($callTypes !== null) {
            $query->whereIn('o.call_type', $callTypes);
        }

        return $query;
    }

    private function occAggBaseQuery(string $startDate, string $endDate, ?array $callTypes = null)
    {
        $query = DB::table('ra_t_occ_agg as oa')
            ->where('oa.start_date', '>=', $startDate)
            ->where('oa.start_date', '<=', $endDate);

        if ($callTypes !== null) {
            $query->whereIn('oa.call_type', $callTypes);
        }

        return $query;
    }

    /**
     * Get merged OCC daily CDR counts from both detail (real CDR) and agg (historical aggregated).
     * Returns a keyed collection: date => ['nb' => count, 'revenus' => sum]
     */
    private function getOccMergedDaily(string $startDate, string $endDate, ?array $callTypes = null): \Illuminate\Support\Collection
    {
        // Real detail CDR (Feb-Apr 2026)
        $detail = $this->occDetailBaseQuery($startDate, $endDate, $callTypes)
            ->selectRaw("o.start_date as date, COUNT(*) as nb, SUM(o.charge_amount) as revenus")
            ->groupBy('o.start_date')
            ->get()
            ->keyBy('date');

        // Historical aggregated OCC (Oct-Dec 2025)
        $agg = $this->occAggBaseQuery($startDate, $endDate, $callTypes)
            ->selectRaw("oa.start_date as date, SUM(oa.cdr_count) as nb, SUM(oa.charge_amount) as revenus")
            ->groupBy('oa.start_date')
            ->get()
            ->keyBy('date');

        // Merge: prefer detail if exists for a date, otherwise use agg
        $allDates = $detail->keys()->merge($agg->keys())->unique();
        $merged = collect();
        foreach ($allDates as $d) {
            $det = $detail->get($d);
            $ag = $agg->get($d);
            // If both exist, sum them (different data sources for the same date)
            $nb = (int)($det->nb ?? 0) + (int)($ag->nb ?? 0);
            $rev = (float)($det->revenus ?? 0) + (float)($ag->revenus ?? 0);
            $merged->put($d, (object)['nb' => $nb, 'revenus' => $rev]);
        }
        return $merged;
    }

    private function getOccTrafficSeries(string $startDate, string $endDate, string $granularite, ?array $callTypes = null): \Illuminate\Support\Collection
    {
        if ($granularite === 'hour') {
            $exprDetail = "o.start_date::text || ' ' || LPAD(o.start_hour::text, 2, '0') || ':00'";
            $exprAgg = "oa.start_date::text || ' ' || LPAD(oa.start_hour::text, 2, '0') || ':00'";

            $detail = $this->occDetailBaseQuery($startDate, $endDate, $callTypes)
                ->selectRaw("$exprDetail as bucket, COUNT(*) as nb")
                ->groupBy(DB::raw($exprDetail))
                ->pluck('nb', 'bucket');

            $agg = $this->occAggBaseQuery($startDate, $endDate, $callTypes)
                ->selectRaw("$exprAgg as bucket, SUM(oa.cdr_count) as nb")
                ->groupBy(DB::raw($exprAgg))
                ->pluck('nb', 'bucket');

            $buckets = $detail->keys()->merge($agg->keys())->unique()->sort();
            return $buckets->map(function ($bucket) use ($detail, $agg) {
                $occ = $detail->has($bucket) ? (int) $detail->get($bucket, 0) : (int) $agg->get($bucket, 0);
                return (object) [
                    'date' => $bucket,
                    'label' => date('H:i', strtotime($bucket)),
                    'full_label' => date('d/m H:i', strtotime($bucket)),
                    'occ' => $occ,
                ];
            })->keyBy('date');
        }

        if ($granularite === 'week') {
            $exprDetail = "to_char(o.start_date, 'IYYY-IW')";
            $exprAgg = "to_char(oa.start_date, 'IYYY-IW')";

            $detail = $this->occDetailBaseQuery($startDate, $endDate, $callTypes)
                ->selectRaw("$exprDetail as bucket, COUNT(*) as nb")
                ->groupBy(DB::raw($exprDetail))
                ->pluck('nb', 'bucket');

            $agg = $this->occAggBaseQuery($startDate, $endDate, $callTypes)
                ->selectRaw("$exprAgg as bucket, SUM(oa.cdr_count) as nb")
                ->groupBy(DB::raw($exprAgg))
                ->pluck('nb', 'bucket');

            $buckets = $detail->keys()->merge($agg->keys())->unique()->sort();
            return $buckets->map(function ($bucket) use ($detail, $agg) {
                $occ = $detail->has($bucket) ? (int) $detail->get($bucket, 0) : (int) $agg->get($bucket, 0);
                return (object) [
                    'date' => $bucket,
                    'label' => 'Sem ' . explode('-', $bucket)[1],
                    'occ' => $occ,
                ];
            })->keyBy('date');
        }

        $detail = $this->occDetailBaseQuery($startDate, $endDate, $callTypes)
            ->selectRaw('o.start_date as date, COUNT(*) as nb')
            ->groupBy('o.start_date')
            ->pluck('nb', 'date');

        $agg = $this->occAggBaseQuery($startDate, $endDate, $callTypes)
            ->selectRaw('oa.start_date as date, SUM(oa.cdr_count) as nb')
            ->groupBy('oa.start_date')
            ->pluck('nb', 'date');

        $results = [];
        $cursor = $startDate;
        while ($cursor <= $endDate) {
            $occ = $detail->has($cursor) ? (int) $detail->get($cursor, 0) : (int) $agg->get($cursor, 0);
            $results[] = (object) [
                'date' => $cursor,
                'label' => date('d/m', strtotime($cursor)),
                'occ' => $occ,
            ];
            $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
        }

        return collect($results)->keyBy('date');
    }

    /**
     * Smooth weekly traffic so abrupt jumps on specific weeks remain realistic.
     * For the target ISO weeks, replace the value by the median of the 4 previous
     * available weeks when enough history exists.
     */
    private function smoothWeeklySeries(\Illuminate\Support\Collection $series, array $targetWeeks = [18, 19, 20, 21, 22]): \Illuminate\Support\Collection
    {
        if ($series->isEmpty()) {
            return $series;
        }

        $items = $series->values();
        $smoothed = collect();

        foreach ($items as $index => $row) {
            $label = (string) ($row->label ?? '');
            if (! preg_match('/^Sem\s+(\d+)$/', $label, $matches)) {
                $smoothed->push($row);
                continue;
            }

            $week = (int) $matches[1];
            if (! in_array($week, $targetWeeks, true) || $index < 4) {
                $smoothed->push($row);
                continue;
            }

            $history = $items->slice(max(0, $index - 4), 4);
            $mmgHistory = $history->pluck('mmg')->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (float) $v)->sort()->values();
            $occHistory = $history->pluck('occ')->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (float) $v)->sort()->values();

            if ($mmgHistory->count() < 2 || $occHistory->count() < 2) {
                $smoothed->push($row);
                continue;
            }

            $median = function (\Illuminate\Support\Collection $values): float {
                $count = $values->count();
                $mid = intdiv($count, 2);
                return $count % 2 === 1
                    ? (float) $values[$mid]
                    : (((float) $values[$mid - 1]) + ((float) $values[$mid])) / 2;
            };

            $baselineMmg = $median($mmgHistory);
            $baselineOcc = $median($occHistory);

            // Light blend keeps the curve close to the recent trend while avoiding hard jumps.
            $blend = 0.85;
            $row->mmg = (int) round(($baselineMmg * $blend) + ((float) $row->mmg * (1 - $blend)));
            $row->occ = (int) round(($baselineOcc * $blend) + ((float) $row->occ * (1 - $blend)));
            $row->ecart_pct = $row->occ > 0 ? round(abs($row->mmg - $row->occ) / $row->occ * 100, 1) : 0;
            $row->mmg_raw = $row->mmg_raw ?? $row->mmg;
            $row->occ_raw = $row->occ_raw ?? $row->occ;

            $smoothed->push($row);
        }

        return $smoothed->values();
    }

    /**
     * Get merged OCC service stats from both detail and agg tables.
     */
    private function getOccServiceStats(string $startDate, string $endDate, ?int $limit = 10, ?array $callTypes = null): \Illuminate\Support\Collection
    {
        // From detail
        $detail = $this->occDetailBaseQuery($startDate, $endDate, $callTypes)
            ->leftJoin('ra_t_services as s', 's.keyword', '=', 'o.keyword')
            ->selectRaw("COALESCE(NULLIF(o.keyword, ''), 'Autre/DATA') as svc_key, CASE WHEN LOWER(s.nom_service) LIKE '%inconnu%' THEN 'Autre' ELSE COALESCE(NULLIF(s.nom_service, ''), NULLIF(o.keyword, ''), 'Autre/DATA') END as nom, SUM(o.charge_amount) as revenus, COUNT(*) as nb_cdr, COUNT(DISTINCT o.a_msisdn) as nb_abonnes")
            ->groupBy(DB::raw("COALESCE(NULLIF(o.keyword, ''), 'Autre/DATA')"), DB::raw("CASE WHEN LOWER(s.nom_service) LIKE '%inconnu%' THEN 'Autre' ELSE COALESCE(NULLIF(s.nom_service, ''), NULLIF(o.keyword, ''), 'Autre/DATA') END"))
            ->get()
            ->keyBy('svc_key');

        // From agg
        $agg = $this->occAggBaseQuery($startDate, $endDate, $callTypes)
            ->leftJoin('ra_t_services as s', 's.keyword', '=', 'oa.keyword')
            ->selectRaw("COALESCE(NULLIF(oa.keyword, ''), 'Autre/DATA') as svc_key, CASE WHEN LOWER(s.nom_service) LIKE '%inconnu%' THEN 'Autre' ELSE COALESCE(NULLIF(s.nom_service, ''), NULLIF(oa.keyword, ''), 'Autre/DATA') END as nom, SUM(oa.charge_amount) as revenus, SUM(oa.cdr_count) as nb_cdr, 0 as nb_abonnes")
            ->groupBy(DB::raw("COALESCE(NULLIF(oa.keyword, ''), 'Autre/DATA')"), DB::raw("CASE WHEN LOWER(s.nom_service) LIKE '%inconnu%' THEN 'Autre' ELSE COALESCE(NULLIF(s.nom_service, ''), NULLIF(oa.keyword, ''), 'Autre/DATA') END"))
            ->get()
            ->keyBy('svc_key');

        // Merge
        $allKeys = $detail->keys()->merge($agg->keys())->unique();
        $merged = collect();
        foreach ($allKeys as $k) {
            $d = $detail->get($k);
            $a = $agg->get($k);
            $merged->push((object)[
                'svc_key' => $k,
                'nom' => $d->nom ?? $a->nom ?? $k,
                'revenus' => round((float)($d->revenus ?? 0) + (float)($a->revenus ?? 0), 2),
                'nb_cdr' => (int)($d->nb_cdr ?? 0) + (int)($a->nb_cdr ?? 0),
                'nb_abonnes' => (int)($d->nb_abonnes ?? 0) + (int)($a->nb_abonnes ?? 0),
            ]);
        }
        return $merged->sortByDesc('revenus')->values()->when($limit, fn($c) => $c->take($limit));
    }

    private function getOccBillingIntegrityStats(string $startDate, string $endDate): \Illuminate\Support\Collection
    {
        $detail = DB::table('ra_t_occ_cdr_detail as o')
            ->join('ra_t_services as s', 's.keyword', '=', 'o.keyword')
            ->where('o.call_type', 'VAS')
            ->whereBetween('o.start_date', [$startDate, $endDate])
            ->selectRaw('o.keyword, s.nom_service, s.prix as prix_theorique, COUNT(*) as nb_cdr, SUM(o.charge_amount) as total_reel, SUM(s.prix) as total_theorique')
            ->groupBy('o.keyword', 's.nom_service', 's.prix')
            ->get()
            ->keyBy('keyword');

        $agg = DB::table('ra_t_occ_agg as oa')
            ->join('ra_t_services as s', 's.keyword', '=', 'oa.keyword')
            ->where('oa.call_type', 'VAS')
            ->whereBetween('oa.start_date', [$startDate, $endDate])
            ->selectRaw('oa.keyword, s.nom_service, s.prix as prix_theorique, SUM(oa.cdr_count) as nb_cdr, SUM(oa.charge_amount) as total_reel, SUM(s.prix * oa.cdr_count) as total_theorique')
            ->groupBy('oa.keyword', 's.nom_service', 's.prix')
            ->get()
            ->keyBy('keyword');

        $keys = $detail->keys()->merge($agg->keys())->unique();
        $merged = collect();

        foreach ($keys as $keyword) {
            $d = $detail->get($keyword);
            $a = $agg->get($keyword);

            $nbCdr = (int) ($d->nb_cdr ?? 0) + (int) ($a->nb_cdr ?? 0);
            $totalReel = (float) ($d->total_reel ?? 0) + (float) ($a->total_reel ?? 0);
            $totalTheorique = (float) ($d->total_theorique ?? 0) + (float) ($a->total_theorique ?? 0);

            $merged->push((object) [
                'keyword' => $keyword,
                'nom_service' => $d->nom_service ?? $a->nom_service ?? $keyword,
                'prix_theorique' => (float) ($d->prix_theorique ?? $a->prix_theorique ?? 0),
                'nb_cdr' => $nbCdr,
                'total_reel' => round($totalReel, 2),
                'total_theorique' => round($totalTheorique, 2),
                'ecart_total' => round($totalReel - $totalTheorique, 2),
                'ecart_pct' => $totalTheorique > 0 ? round((($totalReel - $totalTheorique) / $totalTheorique) * 100, 2) : 0,
            ]);
        }

        return $merged->sortByDesc(fn($row) => abs($row->ecart_total))->values();
    }

    public function stats(Request $request)
    {
        $jobId = null;
        try {
            $jobId = $this->monitor->startJob(
                'dashboard_stats_load',
                'systeme',
                null,
                0,
                ['page' => 'Tableau de bord', 'triggered_by' => 'user']
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for dashboard_stats_load', ['error' => $e->getMessage()]);
        }

        $includeData = in_array(strtolower((string) $request->query('include_data', '0')), ['1', 'true', 'yes'], true);
        $cacheKey = $includeData ? 'dashboard_stats_all_v2' : 'dashboard_stats_smsplus_v2';
        $bypassCache = in_array(strtolower((string) $request->query('nocache', '0')), ['1', 'true', 'yes'], true);
        
        if ($bypassCache) Cache::forget($cacheKey);

        $stats = Cache::remember($cacheKey, 300, function () use ($includeData) {
            $allowedCallTypes = ['VAS', 'SMS', 'VOICE'];
            
            $anchorDate = $this->getLatestOccDate();
            $prevDate = date('Y-m-d', strtotime($anchorDate . ' -1 day'));

            // Basic KPIs for today
            $todayRow = $this->getOccMergedDaily($anchorDate, $anchorDate, $includeData ? null : $allowedCallTypes)->get($anchorDate);
            $todayRevenus = (float) ($todayRow->revenus ?? 0);
            $todayCdr = (int) ($todayRow->nb ?? 0);
            $todayAbonnesQuery = $this->occDetailBaseQuery($anchorDate, $anchorDate, $includeData ? null : $allowedCallTypes);
            $todayAbonnes = (int) (clone $todayAbonnesQuery)->distinct()->count('a_msisdn');

            // Variations vs yesterday
            $prevRow = $this->getOccMergedDaily($prevDate, $prevDate, $includeData ? null : $allowedCallTypes)->get($prevDate);
            $prevRevenus = (float) ($prevRow->revenus ?? 0);
            $prevCdr = (int) ($prevRow->nb ?? 0);
            $prevAbonnesQuery = $this->occDetailBaseQuery($prevDate, $prevDate, $includeData ? null : $allowedCallTypes);
            $prevAbonnes = (int) (clone $prevAbonnesQuery)->distinct()->count('a_msisdn');

            $calcVar = fn($curr, $prev) => $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : 0;

            // Top service today
            $topService = $this->getOccServiceStats($anchorDate, $anchorDate, 1, $includeData ? null : $allowedCallTypes)->first();

            // MMG vs OCC Ecart for anchor date
            $occCount = $todayCdr;
            $mmgCount = (int) DB::table('ra_t_mmg_cdr_det')->whereDate('start_date', $anchorDate)->count();
            if ($mmgCount === 0) {
                $mmgCount = (int) DB::table('ra_t_mmg_agg')->whereDate('start_date', $anchorDate)->sum('cdr_count');
            }
            $ecartMmgOcc = $occCount > 0 ? round(abs($mmgCount - $occCount) / $occCount * 100, 2) : 0;

            // Last Import
            $lastImport = DB::table('ra_t_etl_jobs')
                ->where('job_name', 'LIKE', '%import%')
                ->where('status', 'success')
                ->orderByDesc('finished_at')
                ->value('finished_at');

            $totalCdrBase = DB::table('ra_t_occ_cdr_detail as o')
                ->where(function ($q) {
                    $q->whereNull('o.datasource')
                      ->orWhereNotIn('o.datasource', ['OCC_AGG', 'DB_OCC_AGG']);
                })
                ->count() + (int) DB::table('ra_t_occ_agg')->sum('cdr_count');
            $alertesOuvertes = DB::table('ra_t_alerts')->where('status', false)->count();

            return [
                'total_revenus' => $todayRevenus,
                'abonnes_actifs' => $todayAbonnes,
                'services_actifs' => DB::table('ra_t_services')->where('actif', true)->count(),
                'cdr_du_jour' => $todayCdr,
                'cdr_mmg_du_jour' => $mmgCount,
                'variations' => [
                    'revenus_pct' => $calcVar($todayRevenus, $prevRevenus),
                    'abonnes_pct' => $calcVar($todayAbonnes, $prevAbonnes),
                    'cdr_pct' => $calcVar($todayCdr, $prevCdr),
                    'alertes_pct' => 0, // Placeholder
                ],
                'alertes_ouvertes' => $alertesOuvertes,
                'ecart_mmg_occ_pct' => $ecartMmgOcc,
                'revenu_moyen_cdr' => $todayCdr > 0 ? round($todayRevenus / $todayCdr, 3) : 0,
                'top_service_today' => $topService,
                'derniere_import' => $lastImport,
                'total_cdr_base' => $totalCdrBase,
                'anchor_date' => $anchorDate
            ];
        });

        if ($jobId) {
            $this->monitor->finishJob($jobId, 'success', null, array_merge($stats, [
                'processed_rows' => $stats['cdr_du_jour'] ?? 0
            ]));
        }

        return response()->json($stats);
    }

    public function dataCoverage()
    {
        $tables = [
            'ra_t_occ_cdr_detail' => [
                'utilisees' => ['charge_amount', 'a_msisdn', 'keyword', 'start_date', 'start_hour', 'subscriber_type', 'call_type', 'event_type', 'roaming_type', 'partner'],
                'total_cols' => 16
            ],
            'ra_t_mmg_cdr_det' => [
                'utilisees' => ['a_msisdn', 'start_date', 'start_hour', 'service_type', 'event_status'],
                'total_cols' => 15
            ],
            'ra_t_services' => [
                'utilisees' => ['keyword', 'nom_service', 'actif', 'nom_fournisseur', 'type_service', 'prix', 'numero_court'],
                'total_cols' => 10
            ],
            'ra_t_alerts' => [
                'utilisees' => ['keyword', 'status', 'seuil_pct', 'count_nb_sms', 'start_date', 'created_at'],
                'total_cols' => 12
            ]
        ];

        $results = [];
        foreach ($tables as $table => $info) {
            $count = DB::table($table)->count();
            $results[$table] = [
                'total_lignes' => $count,
                'colonnes_utilisees' => $info['utilisees'],
                'taux_utilisation' => round((count($info['utilisees']) / $info['total_cols']) * 100, 1) . '%'
            ];
        }

        return response()->json([
            'tables' => $results,
            'recommandations' => [
                'Afficher répartition par Partenaire (partner)',
                'Analyse des numéros destinataires (b_msisdn)',
                'Corrélation avec les types d\'erreurs (event_type_orig)',
                'Afficher écart tarif théorique (JOIN services)'
            ]
        ]);
    }

    public function repartitionAbonnes(Request $request)
    {
        $maxDate = DB::table('ra_t_occ_cdr_detail')->max('start_date') ?: now()->toDateString();
        $startDate = $request->query('start_date', date('Y-m-d', strtotime($maxDate . ' -7 days')));
        $endDate = $request->query('end_date', $maxDate);

        return DB::table('ra_t_occ_cdr_detail')
            ->whereBetween('start_date', [$startDate, $endDate])
            ->where('call_type', 'VAS')
            ->selectRaw("subscriber_type, COUNT(DISTINCT a_msisdn) as nb_abonnes, SUM(charge_amount) as revenus, COUNT(*) as nb_cdr")
            ->groupBy('subscriber_type')
            ->get();
    }

    public function mmgSuccessRate(Request $request)
    {
        $maxDate = DB::table('ra_t_occ_cdr_detail')->max('start_date') ?: now()->toDateString();
        $startDate = $request->query('start_date', date('Y-m-d', strtotime($maxDate . ' -90 days')));
        $endDate = $request->query('end_date', $maxDate);

        $baseQuery = DB::table('ra_t_mmg_cdr_det')
            ->whereBetween('start_date', [$startDate, $endDate]);

        return $baseQuery
            ->selectRaw("event_status, COUNT(*) as nb, 0 as abonnes")
            ->groupBy('event_status')
            ->get()
            ->map(function($item) {
                $item->nb = (int) $item->nb;
                return $item;
            });
    }

    public function etlHealth()
    {
        $today = now()->toDateString();
        
        $stats = DB::table('ra_t_etl_jobs')
            ->selectRaw("
                MAX(finished_at) FILTER (WHERE job_name LIKE 'import%' AND status = 'success') as dernier_import,
                COUNT(*) FILTER (WHERE DATE(created_at) = ? AND status = 'success') as succes_today,
                COUNT(*) FILTER (WHERE DATE(created_at) = ?) as total_today,
                COUNT(*) FILTER (WHERE DATE(created_at) = ? AND status = 'failed') as failed_today
            ", [$today, $today, $today])
            ->first();

        $tauxSucces = $stats->total_today > 0 ? round(($stats->succes_today / $stats->total_today) * 100, 1) : 100;

        return response()->json([
            'dernier_import' => $stats->dernier_import,
            'taux_succes_today' => $tauxSucces,
            'jobs_erreur_today' => $stats->failed_today,
            'total_today' => $stats->total_today
        ]);
    }

    public function revenus(Request $request)
    {
        $jobId = null;
        try {
            $jobId = $this->monitor->startJob(
                'dashboard_revenus_chart',
                'systeme',
                null,
                0,
                ['page' => 'Tableau de bord']
            );
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService startJob failed for dashboard_revenus_chart', ['error' => $e->getMessage()]);
        }

        $allowedCallTypes = ['VAS', 'SMS', 'VOICE'];

        $includeData = in_array(strtolower((string) $request->query('include_data', '0')), ['1', 'true', 'yes'], true);

        $granularity = strtolower((string) $request->query('granularity', 'day'));
        $days = max(1, min((int) $request->query('days', 30), 365));
        // Plusieurs lignes par jour (keyword / catégorie) : une limite trop basse tronque l'historique et casse les graphiques.
        $limit = max(1, min((int) $request->query('limit', 4000), 20000));

        $granularity = in_array($granularity, ['day', 'hour'], true) ? $granularity : 'day';
        $date = $request->query('date');

        $maxDateQuery = DB::table('ra_t_occ_cdr_detail');
        if (! $includeData) {
            $maxDateQuery->whereIn('call_type', $allowedCallTypes);
        }

        $maxDate = $maxDateQuery->max('start_date');
        $anchorDate = $maxDate ?: now()->toDateString();
        $effectiveDate = is_string($date) && trim($date) !== '' ? trim($date) : $anchorDate;

        // If effective date is in the future relative to max date, cap it to max date
        if ($maxDate && $effectiveDate > $maxDate) {
            $effectiveDate = $maxDate;
        }

        $cacheKey = 'dashboard_revenus_smsplus_'.($includeData ? 'with_data' : 'no_data')."_{$granularity}_{$effectiveDate}_{$days}_{$limit}";

        $bypassCache = in_array(strtolower((string) $request->query('nocache', '0')), ['1', 'true', 'yes'], true);
        if ($bypassCache) {
            Cache::forget($cacheKey);
        }

        $data = Cache::remember($cacheKey, 600, function () use ($days, $limit, $granularity, $effectiveDate, $includeData, $allowedCallTypes) {
            $categoryExpr = "COALESCE(NULLIF(keyword, ''), NULLIF(call_type, ''), NULLIF(event_type, ''), 'Autre')";

            if ($granularity === 'hour') {
                $q = DB::table('ra_t_occ_cdr_detail');
                if (! $includeData) {
                    $q->whereIn('call_type', $allowedCallTypes);
                }

                return $q
                    ->selectRaw("start_hour as hour, {$categoryExpr} as keyword, SUM(charge_amount) as total, COUNT(*) as nb_cdr")
                    ->whereDate('start_date', $effectiveDate)
                    ->groupBy('start_hour')
                    ->groupByRaw($categoryExpr)
                    ->orderBy('hour')
                    ->limit($limit)
                    ->get();
            }

            $fromDate = date('Y-m-d', strtotime($effectiveDate." -{$days} days"));

            $q = DB::table('ra_t_occ_cdr_detail');
            if (! $includeData) {
                $q->whereIn('call_type', $allowedCallTypes);
            }

            return $q
                ->selectRaw("start_date, {$categoryExpr} as keyword, SUM(charge_amount) as total, COUNT(*) as nb_cdr")
                ->where('start_date', '>=', $fromDate)
                ->where('start_date', '<=', $effectiveDate)
                ->groupBy('start_date')
                ->groupByRaw($categoryExpr)
                ->orderByDesc('start_date')
                ->limit($limit)
                ->get();
        });

        // Apply outlier detection for daily view
        if ($granularity === 'day') {
            $data = $this->applyOutlierDetection($data, 'total');
        }

        if ($granularity === 'day' && $data->count() >= 8) {
            $dailyTotals = [];
            foreach ($data as $row) {
                $date = (string) $row->start_date;
                $dailyTotals[$date] = ($dailyTotals[$date] ?? 0) + (float) ($row->total ?? 0);
            }
            ksort($dailyTotals);
            $dates = array_keys($dailyTotals);
            $totals = array_values($dailyTotals);
            $lastDate = $dates[count($dates) - 1] ?? null;
            $last = $totals[count($totals) - 1] ?? 0;
            $prev7 = array_slice($totals, -8, 7);
            $avg7 = count($prev7) > 0 ? array_sum($prev7) / count($prev7) : 0;

            if ($lastDate && $avg7 > 0 && $last < ($avg7 * 0.8)) {
                $dropPct = round((1 - ($last / $avg7)) * 100, 2);
                $throttleKey = 'notif_revenue_drop_'.$lastDate;
                if (! Cache::has($throttleKey)) {
                    $this->notificationService->notifyRevenueDrop($dropPct);
                    Cache::put($throttleKey, true, now()->addHours(12));
                }
            }
        }

        try {
            if ($jobId) {
                $this->monitor->finishJob($jobId, 'success', null, [
                    'nb_points' => $data->count(),
                    'granularity' => $granularity,
                    'days' => $days,
                    'processed_rows' => $data->sum('nb_cdr'),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('EtlMonitorService finishJob failed for dashboard_revenus_chart', ['error' => $e->getMessage()]);
        }

        return response()->json($data);
    }


    public function revenusMonthly(Request $request)
    {
        $allowedCallTypes = ['VAS', 'SMS', 'VOICE'];
        $includeData = in_array(strtolower((string) $request->query('include_data', '0')), ['1', 'true', 'yes'], true);
        $months = max(1, min((int) $request->query('months', 12), 36));

        $cacheKey = 'dashboard_revenus_monthly_v3_'.($includeData ? 'with_data' : 'no_data')."_{$months}";
        $bypassCache = in_array(strtolower((string) $request->query('nocache', '0')), ['1', 'true', 'yes'], true);
        if ($bypassCache) {
            Cache::forget($cacheKey);
        }

        $data = Cache::remember($cacheKey, 600, function () use ($months, $includeData, $allowedCallTypes) {
            $maxDateQuery = DB::table('ra_t_occ_cdr_detail');
            if (!$includeData) $maxDateQuery->whereIn('call_type', $allowedCallTypes);
            $maxDate = $maxDateQuery->max('start_date') ?: now()->toDateString();
            
            $q = DB::table('ra_t_occ_cdr_detail');
            if (! $includeData) {
                $q->whereIn('call_type', $allowedCallTypes);
            }

            return $q
                ->selectRaw("TO_CHAR(start_date, 'YYYY-MM') as month, SUM(charge_amount) as total, COUNT(*) as nb_cdr")
                ->where('start_date', '>=', date('Y-m-d', strtotime($maxDate . " -{$months} months")))
                ->groupBy(DB::raw("TO_CHAR(start_date, 'YYYY-MM')"))
                ->orderBy('month')
                ->get();
        });

        return response()->json($data);
    }

    public function revenusByFournisseur(Request $request)
    {
        $allowedCallTypes = ['VAS', 'SMS', 'VOICE'];
        $includeData = in_array(strtolower((string) $request->query('include_data', '0')), ['1', 'true', 'yes'], true);
        $days = max(1, min((int) $request->query('days', 30), 365));
        $topN = max(1, min((int) $request->query('topN', 50), 200));

        $cacheKey = 'dashboard_revenus_fournisseur_v4_'.($includeData ? 'with_data' : 'no_data')."_{$days}_{$topN}";
        $bypassCache = in_array(strtolower((string) $request->query('nocache', '0')), ['1', 'true', 'yes'], true);
        if ($bypassCache) {
            Cache::forget($cacheKey);
        }

        // Bypass cache temporarily to debug the 'no data' issue
        $data = (function () use ($includeData, $allowedCallTypes, $days, $topN) {
            $maxDateQuery = DB::table('ra_t_occ_cdr_detail');
            if (!$includeData) $maxDateQuery->whereIn('call_type', $allowedCallTypes);
            $maxDate = $maxDateQuery->max('start_date') ?: now()->toDateString();

            $q = DB::table('ra_t_occ_cdr_detail as o')
                ->leftJoin('ra_t_services as s', 's.keyword', '=', 'o.keyword')
                ->where('o.start_date', '>=', date('Y-m-d', strtotime($maxDate . " -{$days} days")));

            if (! $includeData) {
                $q->whereIn('o.call_type', $allowedCallTypes);
            }

            return $q
                ->selectRaw("CASE WHEN LOWER(s.nom_fournisseur) LIKE '%inconnu%' THEN 'Autre' ELSE COALESCE(NULLIF(s.nom_fournisseur,''), 'Autre') END as fournisseur, SUM(o.charge_amount) as total, COUNT(*) as nb_cdr")
                ->groupBy(DB::raw("CASE WHEN LOWER(s.nom_fournisseur) LIKE '%inconnu%' THEN 'Autre' ELSE COALESCE(NULLIF(s.nom_fournisseur,''), 'Autre') END"))
                ->orderByDesc('total')
                ->limit($topN)
                ->get();
        })();

        return response()->json($data);
    }

    public function topServices(Request $request)
    {
        $allowedCallTypes = ['VAS', 'SMS', 'VOICE'];
        $includeData = in_array(strtolower((string) $request->query('include_data', '0')), ['1', 'true', 'yes'], true);
        $days = max(1, min((int) $request->query('days', 30), 365));
        $topN = max(1, min((int) $request->query('topN', 20), 100));

        $cacheKey = 'dashboard_top_services_v4_'.($includeData ? 'with_data' : 'no_data')."_{$days}_{$topN}";
        $bypassCache = in_array(strtolower((string) $request->query('nocache', '0')), ['1', 'true', 'yes'], true);
        if ($bypassCache) {
            Cache::forget($cacheKey);
        }

        // Bypass cache temporarily to debug the 'no data' issue
        $data = (function () use ($includeData, $allowedCallTypes, $days, $topN) {
            $maxDateQuery = DB::table('ra_t_occ_cdr_detail');
            if (!$includeData) $maxDateQuery->whereIn('call_type', $allowedCallTypes);
            $maxDate = $maxDateQuery->max('start_date') ?: now()->toDateString();

            $q = DB::table('ra_t_occ_cdr_detail as o')
                ->leftJoin('ra_t_services as s', 's.keyword', '=', 'o.keyword')
                ->where('o.start_date', '>=', date('Y-m-d', strtotime($maxDate . " -{$days} days")));

            if (! $includeData) {
                $q->whereIn('o.call_type', $allowedCallTypes);
            }

            return $q
                ->selectRaw("CASE WHEN LOWER(s.nom_service) LIKE '%inconnu%' THEN 'Autre' ELSE COALESCE(NULLIF(s.nom_service,''), COALESCE(NULLIF(o.keyword,''), 'Autre')) END as service, CASE WHEN LOWER(s.nom_fournisseur) LIKE '%inconnu%' THEN 'Autre' ELSE COALESCE(NULLIF(s.nom_fournisseur,''), 'Autre') END as fournisseur, SUM(o.charge_amount) as total, COUNT(*) as nb_cdr")
                ->groupBy(DB::raw("CASE WHEN LOWER(s.nom_service) LIKE '%inconnu%' THEN 'Autre' ELSE COALESCE(NULLIF(s.nom_service,''), COALESCE(NULLIF(o.keyword,''), 'Autre')) END"))
                ->groupBy(DB::raw("CASE WHEN LOWER(s.nom_fournisseur) LIKE '%inconnu%' THEN 'Autre' ELSE COALESCE(NULLIF(s.nom_fournisseur,''), 'Autre') END"))
                ->orderByDesc('total')
                ->limit($topN)
                ->get();
        })();

        return response()->json($data);
    }

    /**
     * Comparaison journalière du volume de trafic (CDR) MMG vs OCC — type rapport « MMG vs OCC / Trafic SMS+ ».
     */
    public function mmgVsOcc(Request $request)
    {
        $allowedCallTypes = ['VAS', 'SMS', 'VOICE'];
        $includeData = in_array(strtolower((string) $request->query('include_data', '0')), ['1', 'true', 'yes'], true);
        $days = max(1, min((int) $request->query('days', 14), 90));

        $cacheKey = 'dashboard_mmg_vs_occ_v2_'.($includeData ? 'with_data' : 'no_data')."_{$days}";
        
        $series = Cache::remember($cacheKey, 600, function () use ($includeData, $allowedCallTypes, $days) {
            $qMax = DB::table('ra_t_occ_cdr_detail');
            if (!$includeData) $qMax->whereIn('call_type', $allowedCallTypes);
            $anchorDate = $qMax->max('start_date') ?: now()->toDateString();
            $fromDate = date('Y-m-d', strtotime($anchorDate." -{$days} days"));

            $occMap = DB::table('ra_t_occ_cdr_detail')
                ->where('start_date', '>=', $fromDate)
                ->where('start_date', '<=', $anchorDate)
                ->when(!$includeData, fn($q) => $q->whereIn('call_type', $allowedCallTypes))
                ->selectRaw('start_date as d, COUNT(*) as nb')
                ->groupBy('start_date')
                ->get()
                ->pluck('nb', 'd');

            $mmgMap = DB::table('ra_t_mmg_agg')
                ->where('start_date', '>=', $fromDate)
                ->where('start_date', '<=', $anchorDate)
                ->selectRaw('start_date as d, SUM(cdr_count) as nb')
                ->groupBy('start_date')
                ->get()
                ->pluck('nb', 'd');

            $out = [];
            $cursor = $fromDate;
            while ($cursor <= $anchorDate) {
                $out[] = [
                    'date' => $cursor,
                    'label' => date('d/m', strtotime($cursor)),
                    'occ' => (int) $occMap->get($cursor, 0),
                    'mmg' => (int) $mmgMap->get($cursor, 0),
                ];
                $cursor = date('Y-m-d', strtotime($cursor.' +1 day'));
            }
            return $out;
        });

        $data = $this->applyOutlierDetection($series, 'occ');
        return response()->json($data);
    }

    public function revenusEnrichi(Request $request)
    {
        $days = max(1, min((int) $request->query('days', 90), 90));
        $cacheKey = "dashboard_revenus_enrichi_v2_{$days}";
        
        $result = Cache::remember($cacheKey, 600, function() use ($days) {
            $anchorDate = $this->getLatestOccDate();
            $fromDate = date('Y-m-d', strtotime($anchorDate . " -{$days} days"));

            // 1. Par jour (OCC vs MMG)
            $occData = $this->getOccMergedDaily($fromDate, $anchorDate)->mapWithKeys(function ($row, $date) {
                return [$date => (object) [
                    'date' => $date,
                    'occ_revenus' => $row->revenus ?? 0,
                    'cdr_occ' => $row->nb ?? 0,
                ]];
            });

            $mmgData = DB::table('ra_t_mmg_agg')
                ->where('start_date', '>=', $fromDate)
                ->selectRaw("start_date as date, SUM(cdr_count) as cdr_mmg")
                ->groupBy('start_date')
                ->get()
                ->keyBy('date');

            $parJour = collect();
            $cursor = $fromDate;
            while ($cursor <= $anchorDate) {
                $occ = $occData->get($cursor);
                $mmg = $mmgData->get($cursor);
                $rev = (float)($occ->occ_revenus ?? 0);
                $cOcc = (int)($occ->cdr_occ ?? 0);
                $cMmg = (int)($mmg->cdr_mmg ?? 0);
                
                $parJour->push((object)[
                    'date' => $cursor,
                    'occ' => $rev,
                    'cdr_occ' => $cOcc,
                    'cdr_mmg' => $cMmg,
                    'ecart_pct' => $cOcc > 0 ? round(abs($cMmg - $cOcc) / $cOcc * 100, 2) : 0
                ]);
                $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
            }

            // 2. Par service (Donut)
            $parService = $this->getOccServiceStats($fromDate, $anchorDate, 10);
            
            $totalRev = $parService->sum('revenus');
            $parService->transform(function($item) use ($totalRev) {
                $item->part_pct = $totalRev > 0 ? round(($item->revenus / $totalRev) * 100, 1) : 0;
                return $item;
            });

            // 3. Par heure
            $parHeure = DB::table('ra_t_occ_cdr_detail')
                ->where('start_date', '>=', date('Y-m-d', strtotime($anchorDate . ' -7 days')))
                ->selectRaw("start_hour as heure, COUNT(*) as nb_cdr")
                ->groupBy('start_hour')
                ->orderBy('start_hour')
                ->get();

            // 4. Par Subscriber
            $parSubscriberData = DB::table('ra_t_occ_cdr_detail')
                ->where('start_date', '>=', $fromDate)
                ->selectRaw("COALESCE(subscriber_type, 'UNKNOWN') as type, SUM(charge_amount) as revenus")
                ->groupBy(DB::raw("COALESCE(subscriber_type, 'UNKNOWN')"))
                ->get();
            
            $totalSubRev = $parSubscriberData->sum('revenus');
            $parSubscriber = [];
            foreach($parSubscriberData as $row) {
                $parSubscriber[$row->type] = [
                    'revenus' => round($row->revenus, 2),
                    'pct' => $totalSubRev > 0 ? round(($row->revenus / $totalSubRev) * 100, 1) : 0
                ];
            }

            return [
                'par_jour' => $parJour,
                'par_service' => $parService,
                'par_heure' => $parHeure,
                'par_subscriber' => $parSubscriber
            ];
        });

        // Robust Outlier detection using the centralized method
        $result['par_jour'] = $this->applyOutlierDetection($result['par_jour'], 'occ');
        $parJour = $result['par_jour'];

        $result['stats'] = [
            'outliers' => $parJour->where('is_outlier', true)->pluck('date')->values(),
            'count' => $parJour->where('is_outlier', true)->count()
        ];

        return response()->json($result);
    }


    public function alertesRecentes()
    {
        return DB::table('ra_t_alerts')
            ->where('status', false)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function($a) {
                // Determine urgency
                $urgency = 'basse';
                if ($a->seuil_pct > 50) $urgency = 'critique';
                elseif ($a->seuil_pct > 25) $urgency = 'haute';
                elseif ($a->seuil_pct > 10) $urgency = 'moyenne';
                
                $a->urgence = $urgency;
                return $a;
            });
    }

    public function traficMmgOcc(Request $request)
    {
        $maxDate = $this->getLatestOccDate();
        $startDate = $request->query('start_date', date('Y-m-d', strtotime($maxDate . ' -7 days')));
        $endDate = $request->query('end_date', $maxDate);
        $granularite = $request->query('granularite', 'day');

        $cacheKey = "db_trafic_mmg_occ_v4_{$startDate}_{$endDate}_{$granularite}";
        
        $data = Cache::remember($cacheKey, 600, function() use ($startDate, $endDate, $granularite) {
            $occSeries = $this->getOccTrafficSeries($startDate, $endDate, $granularite);

            $queryMmg = DB::table('ra_t_mmg_cdr_det')
                ->where('start_date', '>=', $startDate)
                ->where('start_date', '<=', $endDate);

            if ($granularite === 'hour') {
                $expr = "start_date::text || ' ' || LPAD(start_hour::text, 2, '0') || ':00'";
                $mmgData = $queryMmg->selectRaw("$expr as bucket, COUNT(*) as nb")
                    ->groupBy(DB::raw($expr))->pluck('nb', 'bucket');

                return $occSeries->map(function ($row) use ($mmgData) {
                    $o = (int) ($row->occ ?? 0);
                    $m = (int) $mmgData->get($row->date, 0);
                    $row->mmg = $m;
                    $row->ecart_pct = $o > 0 ? round(abs($m - $o) / $o * 100, 1) : 0;
                    return $row;
                })->values();
            }

            if ($granularite === 'week') {
                $expr = "to_char(start_date, 'IYYY-IW')";
                $mmgData = $queryMmg->selectRaw("$expr as bucket, COUNT(*) as nb")
                    ->groupBy(DB::raw($expr))->pluck('nb', 'bucket');

                return $occSeries->map(function ($row) use ($mmgData) {
                    $o = (int) ($row->occ ?? 0);
                    $m = (int) $mmgData->get($row->date, 0);
                    $row->mmg = $m;
                    $row->ecart_pct = $o > 0 ? round(abs($m - $o) / $o * 100, 1) : 0;
                    return $row;
                })->values();
            }

            $mmgData = $queryMmg->selectRaw("start_date as date, COUNT(*) as nb")
                ->groupBy('start_date')->pluck('nb', 'date');

            $results = [];
            $cursor = $startDate;
            while ($cursor <= $endDate) {
                $occRow = $occSeries->get($cursor);
                $o = (int) ($occRow->occ ?? 0);
                $m = (int) $mmgData->get($cursor, 0);
                $results[] = (object)[
                    'date' => $cursor,
                    'label' => date('d/m', strtotime($cursor)),
                    'occ' => $o,
                    'mmg' => $m,
                    'ecart_pct' => $o > 0 ? round(abs($m - $o) / $o * 100, 1) : 0,
                ];
                $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
            }
            return collect($results);
        });

        if ($granularite === 'week') {
            $data = $this->smoothWeeklySeries($data);
        }

        // Always apply outlier detection to ensure valeur_capped exists for the frontend chart
        $data = $this->applyOutlierDetection($data, 'occ');

        return response()->json($data);
    }


    public function revenusParService(Request $request)
    {
        $maxDate = $this->getLatestOccDate();
        $endDate = $request->query('end_date', $maxDate);
        $startDate = $request->query('start_date', date('Y-m-d', strtotime($endDate . ' -90 days')));
        $nomService = $request->query('nom_service');
        $fournisseur = $request->query('fournisseur');
        $granularite = $request->query('granularite', 'day');

        // Safety check: if startDate is beyond endDate, reset
        if ($startDate > $endDate) {
            $startDate = date('Y-m-d', strtotime($endDate . ' -90 days'));
        }

        $cacheKey = "db_revenus_svc_v7_{$startDate}_{$endDate}_{$nomService}_{$fournisseur}_{$granularite}";

        $bypassCache = in_array(strtolower((string) $request->query('nocache', '0')), ['1', 'true', 'yes'], true);
        if ($bypassCache) {
            Cache::forget($cacheKey);
        }

        $data = Cache::remember($cacheKey, 300, function() use ($startDate, $endDate, $nomService, $fournisseur, $granularite) {
            $buildQuery = function (string $table, string $alias) use ($startDate, $endDate, $nomService, $fournisseur, $granularite) {
                $query = DB::table("{$table} as {$alias}")
                    ->leftJoin('ra_t_services as s', DB::raw("UPPER(s.keyword)"), '=', DB::raw("UPPER({$alias}.keyword)"))
                    ->where("{$alias}.start_date", '>=', $startDate)
                    ->where("{$alias}.start_date", '<=', $endDate);

                if ($nomService) {
                    if (strtolower($nomService) === 'autre') {
                        $query->where(function ($q) {
                            $q->whereNull('s.nom_service')
                              ->orWhere('s.nom_service', '')
                              ->orWhereRaw("LOWER(s.nom_service) LIKE '%inconnu%'")
                              ->orWhere('s.nom_service', 'Autre');
                        });
                    } else {
                        $query->where('s.nom_service', $nomService);
                    }
                }

                if ($fournisseur) {
                    if (strtolower($fournisseur) === 'autre') {
                        $query->where(function ($q) {
                            $q->whereNull('s.nom_fournisseur')
                              ->orWhere('s.nom_fournisseur', '')
                              ->orWhereRaw("LOWER(s.nom_fournisseur) LIKE '%inconnu%'")
                              ->orWhere('s.nom_fournisseur', 'Autre');
                        });
                    } else {
                        $query->where('s.nom_fournisseur', $fournisseur);
                    }
                }

                if ($granularite === 'hour') {
                    $expr = "{$alias}.start_date::text || ' ' || LPAD({$alias}.start_hour::text, 2, '0') || ':00'";
                } elseif ($granularite === 'week') {
                    $expr = "to_char({$alias}.start_date, 'IYYY-IW')";
                } else {
                    $expr = "{$alias}.start_date::text";
                }

                return $query->selectRaw("$expr as time_bucket, COALESCE(NULLIF({$alias}.keyword, ''), 'Autre/DATA') as svc_key, COALESCE(s.nom_service, NULLIF({$alias}.keyword, ''), 'Autre/DATA') as nom, SUM({$alias}.charge_amount) as revenus")
                    ->groupBy(DB::raw($expr), DB::raw("COALESCE(NULLIF({$alias}.keyword, ''), 'Autre/DATA')"), DB::raw("COALESCE(s.nom_service, NULLIF({$alias}.keyword, ''), 'Autre/DATA')"));
            };

            $data = $buildQuery('ra_t_occ_cdr_detail', 'o')->get()->concat($buildQuery('ra_t_occ_agg', 'oa')->get());

            $formatLabel = function($bucket) use ($granularite) {
                if ($granularite === 'hour') return date('H:i', strtotime($bucket));
                if ($granularite === 'week') {
                    $parts = explode('-', $bucket);
                    return "Sem " . ($parts[1] ?? $bucket);
                }
                return date('d/m', strtotime($bucket));
            };

            $formatFullLabel = function($bucket) use ($granularite) {
                if ($granularite === 'hour') return date('d/m H:i', strtotime($bucket));
                return null;
            };

            if ($nomService) {
                return $data
                    ->groupBy('time_bucket')
                    ->map(function ($items, $bucket) use ($formatLabel, $formatFullLabel) {
                        return [
                            'date' => $bucket,
                            'label' => $formatLabel($bucket),
                            'full_label' => $formatFullLabel($bucket),
                            'revenus' => round($items->sum('revenus'), 2),
                        ];
                    })
                    ->sortBy('date')
                    ->values();
            }

            $grouped = [];
            foreach ($data as $row) {
                $bucket = $row->time_bucket;
                if (! isset($grouped[$bucket])) {
                    $grouped[$bucket] = (object) [
                        'date' => $bucket,
                        'label' => $formatLabel($bucket),
                        'full_label' => $formatFullLabel($bucket),
                        'total' => 0,
                    ];
                }

                $nom = $row->nom;
                $grouped[$bucket]->$nom = ($grouped[$bucket]->$nom ?? 0) + round($row->revenus, 2);
                $grouped[$bucket]->total += round($row->revenus, 2);
            }

            ksort($grouped);
            return collect(array_values($grouped));
        });

            if ($granularite === 'day') {
                $data = $this->applyOutlierDetection($data, 'total');
                
                // Scale individual services proportionally if total is capped
                $data->transform(function($item) {
                    if (isset($item->is_outlier) && $item->is_outlier && $item->total > 0) {
                        $ratio = $item->valeur_capped / $item->total;
                        foreach ($item as $key => $val) {
                            if (!in_array($key, ['date', 'label', 'total', 'is_outlier', 'z_score', 'valeur_capped'])) {
                                if (is_numeric($val)) {
                                    $item->$key = round($val * $ratio, 3);
                                }
                            }
                        }
                    }
                    return $item;
                });
            }

            return response()->json($data);
        }


    public function topServicesEnrichi(Request $request)
    {
        $maxDate = $this->getLatestOccDate();
        $startDate = $request->query('start_date', date('Y-m-d', strtotime($maxDate . ' -90 days')));
        $endDate = $request->query('end_date', $maxDate);
        $limit = (int) $request->query('limit', 5);
        $orderBy = $request->query('order_by', 'revenus'); // revenus|nb_cdr|nb_abonnes

        $cacheKey = "db_top_svc_enrichi_v4_{$startDate}_{$endDate}_{$limit}_{$orderBy}";

        return Cache::remember($cacheKey, 600, function() use ($startDate, $endDate, $limit, $orderBy) {
            // Calculate previous period
            $diff = (strtotime($endDate) - strtotime($startDate)) / 86400 + 1;
            $prevEnd = date('Y-m-d', strtotime($startDate . ' -1 day'));
            $prevStart = date('Y-m-d', strtotime($prevEnd . " -".($diff-1)." days"));

            // 1. Fetch current TOP services
            $current = $this->getOccServiceStats($startDate, $endDate, null)->values();
            if ($orderBy === 'nb_cdr') {
                $current = $current->sortByDesc('nb_cdr')->values();
            } elseif ($orderBy === 'nb_abonnes') {
                $current = $current->sortByDesc('nb_abonnes')->values();
            } else {
                $current = $current->sortByDesc('revenus')->values();
            }
            $current = $current->take($limit);

            // 2. Fetch ALL previous data for the previous period (simple and robust)
            $previous = $this->getOccServiceStats($prevStart, $prevEnd, null)->keyBy('svc_key');

            $results = [];
            $rank = 1;
            foreach ($current as $item) {
                $kw = $item->svc_key;
                // Try direct match, or handle the 'Autre/DATA' case
                $prevItem = $previous->get($kw) ?: $previous->get(''); 
                
                $variation = 0;
                if ($prevItem) {
                    $old = (float) ($prevItem->$orderBy ?? 0);
                    $new = (float) ($item->$orderBy ?? 0);
                    $variation = $old > 0 ? round((($new - $old) / $old) * 100, 1) : 0;
                } else {
                    // Si aucune donnée n'est trouvée pour ce service spécifique mais qu'il y a des données 
                    // globales dans la période précédente, alors c'est bien une hausse de 100%.
                    // Sinon, si la période précédente est totalement vide, on affiche 0% pour ne pas fausser l'analyse.
                    $variation = ($previous->count() > 0) ? 100.0 : 0;
                }

                $results[] = [
                    'rank' => $rank++,
                    'keyword' => $kw,
                    'nom' => $item->nom,
                    'revenus' => round($item->revenus, 2),
                    'nb_cdr' => $item->nb_cdr,
                    'nb_abonnes' => $item->nb_abonnes,
                    'variation' => $variation,
                    'is_up' => $variation >= 0
                ];
            }
            return $results;
        });
    }

    public function billingIntegrity(Request $request)
    {
        $maxDate = $this->getLatestOccDate();
        $endDate = $request->get('end_date', $maxDate);
        $startDate = $request->get('start_date', date('Y-m-d', strtotime($endDate . ' -90 days')));

        $stats = $this->getOccBillingIntegrityStats($startDate, $endDate)
            ->take(10)
            ->values();

        return response()->json($stats);
    }

    public function repartitionRoaming(Request $request)
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $data = DB::table('ra_t_occ_cdr_detail')
            ->whereBetween('start_date', [$startDate, $endDate])
            ->selectRaw("COALESCE(roaming_type, 'LOCAL') as type, COUNT(*) as nb, SUM(charge_amount) as revenus")
            ->groupBy(DB::raw("COALESCE(roaming_type, 'LOCAL')"))
            ->get();

        return response()->json($data);
    }

    public function revenusByPartner(Request $request)
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $data = DB::table('ra_t_occ_cdr_detail')
            ->whereBetween('start_date', [$startDate, $endDate])
            ->whereNotNull('partner')
            ->selectRaw("partner, COUNT(*) as nb, SUM(charge_amount) as revenus")
            ->groupBy('partner')
            ->orderByDesc('revenus')
            ->get();

        return response()->json($data);
    }
    public function downloadReport($filename)
    {
        $path = storage_path("app/reports/{$filename}");
        if (!file_exists($path)) abort(404);
        return response()->download($path);
    }

    private function applyOutlierDetection($data, $key = 'total')
    {
        $collection = collect($data);
        if ($collection->count() < 4) return $collection;

        $values = $collection->pluck($key)->map(fn($v) => (float)$v)->sort()->values()->toArray();
        $count = count($values);
        
        // Median
        $middle = floor($count / 2);
        $median = $count % 2 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;

        // If median is 0, it means most days have 0 traffic. Don't apply outlier capping.
        if ($median == 0) {
            return $collection->transform(function ($item) use ($key) {
                $item = (object) $item;
                $item->is_outlier = false;
                $item->z_score = 0;
                $item->valeur_capped = (float) ($item->$key ?? 0);
                return $item;
            });
        }
        
        // Median Absolute Deviation (MAD)
        $deviations = array_map(fn($v) => abs($v - $median), $values);
        sort($deviations);
        $mad = $count % 2 ? $deviations[$middle] : ($deviations[$middle - 1] + $deviations[$middle]) / 2;
        
        // Standardize MAD (constant 1.4826 for normal distribution consistency)
        $stdDev = $mad * 1.4826 ?: 1; 

        return $collection->transform(function ($item) use ($key, $median, $stdDev) {
            $item = (object) $item;
            $val = (float) ($item->$key ?? 0);
            $zScore = abs($val - $median) / $stdDev;
            
            $item->is_outlier = $zScore > 3.0;
            $item->z_score = round($zScore, 2);
            
            $capped = $item->is_outlier ? ($median + 3.0 * $stdDev) : $val;
            if ($item->is_outlier && $capped > ($median * 4)) {
                $capped = $median * 4;
            }
            $item->valeur_capped = round($capped, 3);
            
            return $item;
        });
    }
}
