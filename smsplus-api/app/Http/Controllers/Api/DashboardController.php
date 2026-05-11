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
            
            $qMax = DB::table('ra_t_occ_cdr_detail');
            if (!$includeData) $qMax->whereIn('call_type', $allowedCallTypes);
            $maxDate = $qMax->max('start_date');
            $anchorDate = $maxDate ?: now()->toDateString();
            $prevDate = date('Y-m-d', strtotime($anchorDate . ' -1 day'));

            // Basic KPIs for today
            $todayQuery = DB::table('ra_t_occ_cdr_detail')->whereDate('start_date', $anchorDate);
            if (!$includeData) $todayQuery->whereIn('call_type', $allowedCallTypes);
            
            $todayRevenus = (float) (clone $todayQuery)->sum('charge_amount');
            $todayCdr = (clone $todayQuery)->count();
            $todayAbonnes = (clone $todayQuery)->distinct('a_msisdn')->count('a_msisdn');

            // Variations vs yesterday
            $prevQuery = DB::table('ra_t_occ_cdr_detail')->whereDate('start_date', $prevDate);
            if (!$includeData) $prevQuery->whereIn('call_type', $allowedCallTypes);
            
            $prevRevenus = (float) (clone $prevQuery)->sum('charge_amount');
            $prevCdr = (clone $prevQuery)->count();
            $prevAbonnes = (clone $prevQuery)->distinct('a_msisdn')->count('a_msisdn');

            $calcVar = fn($curr, $prev) => $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : 0;

            // Top service today
            $topService = DB::table('ra_t_occ_cdr_detail as o')
                ->leftJoin('ra_t_services as s', 's.keyword', '=', 'o.keyword')
                ->whereDate('o.start_date', $anchorDate)
                ->selectRaw("o.keyword, COALESCE(s.nom_service, o.keyword) as nom, SUM(o.charge_amount) as revenus, COUNT(*) as nb_cdr")
                ->groupBy('o.keyword', 'nom')
                ->orderByDesc('revenus')
                ->first();

            // MMG vs OCC Ecart for anchor date
            $occCount = $todayCdr;
            $mmgCount = DB::table('ra_t_mmg_cdr_det')->whereDate('start_date', $anchorDate)->count();
            $ecartMmgOcc = $occCount > 0 ? round(abs($mmgCount - $occCount) / $occCount * 100, 2) : 0;

            // Last Import
            $lastImport = DB::table('ra_t_etl_jobs')
                ->where('job_name', 'LIKE', '%import%')
                ->where('status', 'success')
                ->orderByDesc('finished_at')
                ->value('finished_at');

            $totalCdrBase = DB::table('ra_t_occ_cdr_detail')->count();
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
        $maxDate = DB::table('ra_t_mmg_cdr_det')->max('start_date') ?: now()->toDateString();
        $startDate = $request->query('start_date', date('Y-m-d', strtotime($maxDate . ' -7 days')));
        $endDate = $request->query('end_date', $maxDate);

        return DB::table('ra_t_mmg_cdr_det')
            ->whereBetween('start_date', [$startDate, $endDate])
            ->selectRaw("event_status, COUNT(*) as nb, COUNT(DISTINCT a_msisdn) as abonnes")
            ->groupBy('event_status')
            ->get();
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
                    ->groupBy('hour')
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

        $cacheKey = 'dashboard_revenus_monthly_'.($includeData ? 'with_data' : 'no_data')."_{$months}";
        $bypassCache = in_array(strtolower((string) $request->query('nocache', '0')), ['1', 'true', 'yes'], true);
        if ($bypassCache) {
            Cache::forget($cacheKey);
        }

        $data = Cache::remember($cacheKey, 600, function () use ($months, $includeData, $allowedCallTypes) {
            $q = DB::table('ra_t_occ_cdr_detail');
            if (! $includeData) {
                $q->whereIn('call_type', $allowedCallTypes);
            }

            return $q
                ->selectRaw("TO_CHAR(start_date, 'YYYY-MM') as month, SUM(charge_amount) as total, COUNT(*) as nb_cdr")
                ->where('start_date', '>=', now()->subMonths($months)->startOfMonth()->toDateString())
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

        $cacheKey = 'dashboard_revenus_fournisseur_'.($includeData ? 'with_data' : 'no_data')."_{$days}_{$topN}";
        $bypassCache = in_array(strtolower((string) $request->query('nocache', '0')), ['1', 'true', 'yes'], true);
        if ($bypassCache) {
            Cache::forget($cacheKey);
        }

        $data = Cache::remember($cacheKey, 600, function () use ($includeData, $allowedCallTypes, $days, $topN) {
            $q = DB::table('ra_t_occ_cdr_detail as o')
                ->leftJoin('ra_t_services as s', 's.keyword', '=', 'o.keyword')
                ->where('o.start_date', '>=', now()->subDays($days)->toDateString());

            if (! $includeData) {
                $q->whereIn('o.call_type', $allowedCallTypes);
            }

            return $q
                ->selectRaw("COALESCE(NULLIF(s.nom_fournisseur,''), 'Inconnu') as fournisseur, SUM(o.charge_amount) as total, COUNT(*) as nb_cdr")
                ->groupBy('fournisseur')
                ->orderByDesc('total')
                ->limit($topN)
                ->get();
        });

        return response()->json($data);
    }

    public function topServices(Request $request)
    {
        $allowedCallTypes = ['VAS', 'SMS', 'VOICE'];
        $includeData = in_array(strtolower((string) $request->query('include_data', '0')), ['1', 'true', 'yes'], true);
        $days = max(1, min((int) $request->query('days', 30), 365));
        $topN = max(1, min((int) $request->query('topN', 20), 100));

        $cacheKey = 'dashboard_top_services_'.($includeData ? 'with_data' : 'no_data')."_{$days}_{$topN}";
        $bypassCache = in_array(strtolower((string) $request->query('nocache', '0')), ['1', 'true', 'yes'], true);
        if ($bypassCache) {
            Cache::forget($cacheKey);
        }

        $data = Cache::remember($cacheKey, 600, function () use ($includeData, $allowedCallTypes, $days, $topN) {
            $q = DB::table('ra_t_occ_cdr_detail as o')
                ->leftJoin('ra_t_services as s', 's.keyword', '=', 'o.keyword')
                ->where('o.start_date', '>=', now()->subDays($days)->toDateString());

            if (! $includeData) {
                $q->whereIn('o.call_type', $allowedCallTypes);
            }

            return $q
                ->selectRaw("COALESCE(NULLIF(s.nom_service,''), COALESCE(NULLIF(o.keyword,''), 'Autre')) as service, COALESCE(NULLIF(s.nom_fournisseur,''), 'Inconnu') as fournisseur, SUM(o.charge_amount) as total, COUNT(*) as nb_cdr")
                ->groupBy('service')
                ->groupBy('fournisseur')
                ->orderByDesc('total')
                ->limit($topN)
                ->get();
        });

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

            $mmgMap = DB::table('ra_t_mmg_cdr_det')
                ->where('start_date', '>=', $fromDate)
                ->where('start_date', '<=', $anchorDate)
                ->selectRaw('start_date as d, COUNT(*) as nb')
                ->groupBy('start_date')
                ->get()
                ->pluck('nb', 'd');

            $out = [];
            $cursor = $fromDate;
            while ($cursor <= $anchorDate) {
                $out[] = [
                    'date' => $cursor,
                    'label' => date('d/m', strtotime($cursor)),
                    'occ' => $occMap->get($cursor, 0),
                    'mmg' => $mmgMap->get($cursor, 0),
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
        $days = max(1, min((int) $request->query('days', 14), 60));
        $cacheKey = "dashboard_revenus_enrichi_v2_{$days}";
        
        $result = Cache::remember($cacheKey, 600, function() use ($days) {
            $maxDate = DB::table('ra_t_occ_cdr_detail')->max('start_date');
            $anchorDate = $maxDate ?: now()->toDateString();
            $fromDate = date('Y-m-d', strtotime($anchorDate . " -{$days} days"));

            // 1. Par jour (OCC vs MMG)
            $occData = DB::table('ra_t_occ_cdr_detail')
                ->where('start_date', '>=', $fromDate)
                ->selectRaw("start_date as date, SUM(charge_amount) as occ_revenus, COUNT(*) as cdr_occ")
                ->groupBy('start_date')
                ->get()
                ->keyBy('date');

            $mmgData = DB::table('ra_t_mmg_cdr_det')
                ->where('start_date', '>=', $fromDate)
                ->selectRaw("start_date as date, COUNT(*) as cdr_mmg")
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
            $parService = DB::table('ra_t_occ_cdr_detail as o')
                ->leftJoin('ra_t_services as s', 's.keyword', '=', 'o.keyword')
                ->where('o.start_date', '>=', $fromDate)
                ->selectRaw("o.keyword, COALESCE(s.nom_service, o.keyword) as nom, SUM(o.charge_amount) as revenus, COUNT(*) as nb_cdr")
                ->groupBy('o.keyword', 'nom')
                ->orderByDesc('revenus')
                ->limit(10)
                ->get();
            
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
                ->groupBy('type')
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
        $maxDate = DB::table('ra_t_occ_cdr_detail')->max('start_date') ?: now()->toDateString();
        $startDate = $request->query('start_date', date('Y-m-d', strtotime($maxDate . ' -7 days')));
        $endDate = $request->query('end_date', $maxDate);
        $granularite = $request->query('granularite', 'day');

        $cacheKey = "db_trafic_mmg_occ_v3_{$startDate}_{$endDate}_{$granularite}";
        
        $data = Cache::remember($cacheKey, 600, function() use ($startDate, $endDate, $granularite) {
            $queryOcc = DB::table('ra_t_occ_cdr_detail')
                ->where('start_date', '>=', $startDate)
                ->where('start_date', '<=', $endDate);

            $queryMmg = DB::table('ra_t_mmg_cdr_det')
                ->where('start_date', '>=', $startDate)
                ->where('start_date', '<=', $endDate);

            if ($granularite === 'hour') {
                $expr = "start_date::text || ' ' || LPAD(start_hour::text, 2, '0') || ':00'";
                $occData = $queryOcc->selectRaw("$expr as bucket, COUNT(*) as nb")
                    ->groupBy(DB::raw($expr))->pluck('nb', 'bucket');
                $mmgData = $queryMmg->selectRaw("$expr as bucket, COUNT(*) as nb")
                    ->groupBy(DB::raw($expr))->pluck('nb', 'bucket');
                
                $buckets = $occData->keys()->merge($mmgData->keys())->unique()->sort();
                $results = [];
                foreach ($buckets as $b) {
                    $o = $occData->get($b, 0);
                    $m = $mmgData->get($b, 0);
                    $results[] = [
                        'date' => $b,
                        'label' => date('d/m H:i', strtotime($b)),
                        'occ' => $o,
                        'mmg' => $m,
                        'ecart_pct' => $o > 0 ? round(abs($m - $o) / $o * 100, 1) : 0,
                    ];
                }
                return $results;
            }

            if ($granularite === 'week') {
                $expr = "to_char(start_date, 'IYYY-IW')";
                $occData = $queryOcc->selectRaw("$expr as bucket, COUNT(*) as nb")
                    ->groupBy(DB::raw($expr))->pluck('nb', 'bucket');
                $mmgData = $queryMmg->selectRaw("$expr as bucket, COUNT(*) as nb")
                    ->groupBy(DB::raw($expr))->pluck('nb', 'bucket');
                
                $buckets = $occData->keys()->merge($mmgData->keys())->unique()->sort();
                $results = [];
                foreach ($buckets as $b) {
                    $o = $occData->get($b, 0);
                    $m = $mmgData->get($b, 0);
                    $results[] = [
                        'date' => $b,
                        'label' => "Sem " . explode('-', $b)[1],
                        'occ' => $o,
                        'mmg' => $m,
                        'ecart_pct' => $o > 0 ? round(abs($m - $o) / $o * 100, 1) : 0,
                    ];
                }
                return $results;
            }

            // Default: Day
            $occData = $queryOcc->selectRaw("start_date as date, COUNT(*) as nb")
                ->groupBy('start_date')->pluck('nb', 'date');
            
            $mmgData = $queryMmg->selectRaw("start_date as date, COUNT(*) as nb")
                ->groupBy('start_date')->pluck('nb', 'date');

            $results = [];
            $cursor = $startDate;
            while ($cursor <= $endDate) {
                $o = $occData->get($cursor, 0);
                $m = $mmgData->get($cursor, 0);
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

        // Always apply outlier detection to ensure valeur_capped exists for the frontend chart
        $data = $this->applyOutlierDetection($data, 'occ');

        return response()->json($data);
    }


    public function revenusParService(Request $request)
    {
        $maxDate = DB::table('ra_t_occ_cdr_detail')->max('start_date') ?: now()->toDateString();
        $endDate = $request->query('end_date', $maxDate);
        $startDate = $request->query('start_date', date('Y-m-d', strtotime($endDate . ' -7 days')));
        $keyword = $request->query('keyword');
        $granularite = $request->query('granularite', 'day');

        // Safety check: if startDate is beyond endDate, reset
        if ($startDate > $endDate) {
            $startDate = date('Y-m-d', strtotime($endDate . ' -7 days'));
        }

        $cacheKey = "db_revenus_svc_v3_{$startDate}_{$endDate}_{$keyword}_{$granularite}";

        $data = Cache::remember($cacheKey, 300, function() use ($startDate, $endDate, $keyword, $granularite) {
            $query = DB::table('ra_t_occ_cdr_detail as o')
                ->leftJoin('ra_t_services as s', 's.keyword', '=', 'o.keyword')
                ->where('o.start_date', '>=', $startDate)
                ->where('o.start_date', '<=', $endDate);

            if ($keyword) {
                $query->where('o.keyword', $keyword);
            }

            if ($granularite === 'hour') {
                $expr = "o.start_date::text || ' ' || LPAD(o.start_hour::text, 2, '0') || ':00'";
                $query->selectRaw("$expr as time_bucket, COALESCE(NULLIF(o.keyword, ''), 'Autre/DATA') as svc_key, COALESCE(s.nom_service, NULLIF(o.keyword, ''), 'Autre/DATA') as nom, SUM(o.charge_amount) as revenus")
                      ->groupBy(DB::raw($expr), 'svc_key', 'nom')
                      ->orderBy(DB::raw($expr));
            } elseif ($granularite === 'week') {
                $expr = "to_char(o.start_date, 'IYYY-IW')";
                $query->selectRaw("$expr as time_bucket, COALESCE(NULLIF(o.keyword, ''), 'Autre/DATA') as svc_key, COALESCE(s.nom_service, NULLIF(o.keyword, ''), 'Autre/DATA') as nom, SUM(o.charge_amount) as revenus")
                      ->groupBy(DB::raw($expr), 'svc_key', 'nom')
                      ->orderBy(DB::raw($expr));
            } else {
                $expr = "o.start_date::text";
                $query->selectRaw("$expr as time_bucket, COALESCE(NULLIF(o.keyword, ''), 'Autre/DATA') as svc_key, COALESCE(s.nom_service, NULLIF(o.keyword, ''), 'Autre/DATA') as nom, SUM(o.charge_amount) as revenus")
                      ->groupBy(DB::raw($expr), 'svc_key', 'nom')
                      ->orderBy(DB::raw($expr));
            }

            $data = $query->get();

            $formatLabel = function($bucket) use ($granularite) {
                if ($granularite === 'hour') return date('d/m H:i', strtotime($bucket));
                if ($granularite === 'week') {
                    // bucket is YYYY-WW, e.g. 2026-18
                    $parts = explode('-', $bucket);
                    return "Sem " . ($parts[1] ?? $bucket);
                }
                return date('d/m', strtotime($bucket));
            };

            if ($keyword) {
                return $data->map(fn($item) => [
                    'date' => $item->time_bucket,
                    'label' => $formatLabel($item->time_bucket),
                    'revenus' => round($item->revenus, 2)
                ]);
            }

                $grouped = [];
                foreach ($data as $row) {
                    $bucket = $row->time_bucket;
                    if (!isset($grouped[$bucket])) {
                        $grouped[$bucket] = (object)[
                            'date' => $bucket, 
                            'label' => $formatLabel($bucket), 
                            'total' => 0
                        ];
                    }
                    $nom = $row->nom;
                    // FIX: Sum revenues instead of overwriting
                    $grouped[$bucket]->$nom = ($grouped[$bucket]->$nom ?? 0) + round($row->revenus, 2);
                    $grouped[$bucket]->total += round($row->revenus, 2);
                }
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
        $maxDate = DB::table('ra_t_occ_cdr_detail')->max('start_date') ?: now()->toDateString();
        $startDate = $request->query('start_date', date('Y-m-d', strtotime($maxDate . ' -7 days')));
        $endDate = $request->query('end_date', $maxDate);
        $limit = (int) $request->query('limit', 5);
        $orderBy = $request->query('order_by', 'revenus'); // revenus|nb_cdr|nb_abonnes

        $cacheKey = "db_top_svc_enrichi_{$startDate}_{$endDate}_{$limit}_{$orderBy}";

        return Cache::remember($cacheKey, 600, function() use ($startDate, $endDate, $limit, $orderBy) {
            // Calculate previous period
            $diff = (strtotime($endDate) - strtotime($startDate)) / 86400 + 1;
            $prevEnd = date('Y-m-d', strtotime($startDate . ' -1 day'));
            $prevStart = date('Y-m-d', strtotime($prevEnd . " -".($diff-1)." days"));

            $fetchTop = function($s, $e) use ($limit, $orderBy) {
                $q = DB::table('ra_t_occ_cdr_detail as o')
                    ->leftJoin('ra_t_services as s', 's.keyword', '=', 'o.keyword')
                    ->where('o.start_date', '>=', $s)
                    ->where('o.start_date', '<=', $e)
                    ->selectRaw("COALESCE(NULLIF(o.keyword, ''), 'Autre/DATA') as svc_key, COALESCE(s.nom_service, NULLIF(o.keyword, ''), 'Autre/DATA') as nom, SUM(o.charge_amount) as revenus, COUNT(*) as nb_cdr, COUNT(DISTINCT o.a_msisdn) as nb_abonnes")
                    ->groupBy('svc_key', 'nom');
                
                if ($orderBy === 'nb_cdr') $q->orderByDesc('nb_cdr');
                elseif ($orderBy === 'nb_abonnes') $q->orderByDesc('nb_abonnes');
                else $q->orderByDesc('revenus');

                return $q->limit($limit)->get()->keyBy('svc_key');
            };

            $current = $fetchTop($startDate, $endDate);
            $previous = $fetchTop($prevStart, $prevEnd);

            $results = [];
            $rank = 1;
            foreach ($current as $kw => $item) {
                $prevItem = $previous->get($kw);
                $variation = 0;
                if ($prevItem) {
                    $old = (float) $prevItem->$orderBy;
                    $new = (float) $item->$orderBy;
                    $variation = $old > 0 ? round((($new - $old) / $old) * 100, 1) : 0;
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
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $stats = DB::table('ra_t_occ_cdr_detail as o')
            ->join('ra_t_services as s', 's.keyword', '=', 'o.keyword')
            ->where('o.call_type', 'VAS')
            ->whereBetween('o.start_date', [$startDate, $endDate])
            ->select([
                'o.keyword',
                's.nom_service',
                's.prix as prix_theorique',
                DB::raw('COUNT(*) as nb_cdr'),
                DB::raw('SUM(o.charge_amount) as total_reel'),
                DB::raw('SUM(s.prix) as total_theorique'),
                DB::raw('SUM(o.charge_amount) - SUM(s.prix) as ecart_total'),
                DB::raw('CASE WHEN SUM(s.prix) > 0 THEN ((SUM(o.charge_amount) - SUM(s.prix)) / SUM(s.prix) * 100) ELSE 0 END as ecart_pct')
            ])
            ->groupBy('o.keyword', 's.nom_service', 's.prix')
            ->orderByRaw('ABS(SUM(o.charge_amount) - SUM(s.prix)) DESC')
            ->limit(10)
            ->get();

        return response()->json($stats);
    }

    public function repartitionRoaming(Request $request)
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $data = DB::table('ra_t_occ_cdr_detail')
            ->whereBetween('start_date', [$startDate, $endDate])
            ->selectRaw("COALESCE(roaming_type, 'LOCAL') as type, COUNT(*) as nb, SUM(charge_amount) as revenus")
            ->groupBy('type')
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
            
            $item->is_outlier = $zScore > 3.0; // Robust Z-score threshold 3.0
            $item->z_score = round($zScore, 2);
            
            // Capping at 4x Median for visual clarity if it's an extreme outlier
            $capped = $item->is_outlier ? ($median + 3.0 * $stdDev) : $val;
            if ($item->is_outlier && $capped > ($median * 4)) {
                $capped = $median * 4;
            }
            $item->valeur_capped = round($capped, 3);
            
            return $item;
        });
    }
}
