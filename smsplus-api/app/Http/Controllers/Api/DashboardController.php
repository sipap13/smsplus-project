<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $includeData = in_array(strtolower((string) $request->query('include_data', '0')), ['1', 'true', 'yes'], true);

        $cacheKey = $includeData ? 'dashboard_stats_all_latest_window' : 'dashboard_stats_smsplus_latest_window';

        $bypassCache = in_array(strtolower((string) $request->query('nocache', '0')), ['1', 'true', 'yes'], true);
        if ($bypassCache) {
            Cache::forget($cacheKey);
        }

        $stats = Cache::remember($cacheKey, 300, function () use ($includeData) {
            $allowedCallTypes = ['VAS', 'SMS', 'VOICE'];

            $qMax = DB::table('ra_t_occ_cdr_detail');
            if (! $includeData) {
                $qMax->whereIn('call_type', $allowedCallTypes);
            }

            $maxDate = $qMax->max('start_date');
            $anchorDate = $maxDate ?: now()->toDateString();

            $base = DB::table('ra_t_occ_cdr_detail');
            if (! $includeData) {
                $base->whereIn('call_type', $allowedCallTypes);
            }

            $base->whereDate('start_date', '>=', date('Y-m-d', strtotime($anchorDate . ' -30 days')))
                ->whereDate('start_date', '<=', $anchorDate);

            return [
                'total_revenus'   => (float) (clone $base)->sum('charge_amount'),
                'abonnes_actifs'  => (clone $base)->distinct('a_msisdn')->count('a_msisdn'),
                'services_actifs' => DB::table('ra_t_services')->where('actif', true)->count(),
                'cdr_du_jour'     => (function () use ($anchorDate, $includeData, $allowedCallTypes) {
                    $q = DB::table('ra_t_occ_cdr_detail');
                    if (! $includeData) {
                        $q->whereIn('call_type', $allowedCallTypes);
                    }

                    return $q->whereDate('start_date', $anchorDate)->count();
                })(),
            ];
        });

        return response()->json($stats);
    }

    public function revenus(Request $request)
    {
        $allowedCallTypes = ['VAS', 'SMS', 'VOICE'];

        $includeData = in_array(strtolower((string) $request->query('include_data', '0')), ['1', 'true', 'yes'], true);

        $granularity = strtolower((string) $request->query('granularity', 'day'));
        $days = max(1, min((int) $request->query('days', 30), 365));
        // Plusieurs lignes par jour (keyword / catégorie) : une limite trop basse tronque l’historique et casse les graphiques.
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

        $cacheKey = "dashboard_revenus_smsplus_" . ($includeData ? 'with_data' : 'no_data') . "_{$granularity}_{$effectiveDate}_{$days}_{$limit}";

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

            $fromDate = date('Y-m-d', strtotime($effectiveDate . " -{$days} days"));

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

        return response()->json($data);
    }

    public function revenusMonthly(Request $request)
    {
        $allowedCallTypes = ['VAS', 'SMS', 'VOICE'];
        $includeData = in_array(strtolower((string) $request->query('include_data', '0')), ['1', 'true', 'yes'], true);
        $months = max(1, min((int) $request->query('months', 12), 36));

        $cacheKey = "dashboard_revenus_monthly_" . ($includeData ? 'with_data' : 'no_data') . "_{$months}";
        $bypassCache = in_array(strtolower((string) $request->query('nocache', '0')), ['1', 'true', 'yes'], true);
        if ($bypassCache) Cache::forget($cacheKey);

        $data = Cache::remember($cacheKey, 600, function () use ($months, $includeData, $allowedCallTypes) {
            $q = DB::table('ra_t_occ_cdr_detail');
            if (! $includeData) {
                $q->whereIn('call_type', $allowedCallTypes);
            }

            // SQLite strftime; works on pg too with to_char if adapted later.
            return $q
                ->selectRaw("strftime('%Y-%m', start_date) as month, SUM(charge_amount) as total, COUNT(*) as nb_cdr")
                ->where('start_date', '>=', now()->subMonths($months)->startOfMonth()->toDateString())
                ->groupBy('month')
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

        $cacheKey = "dashboard_revenus_fournisseur_" . ($includeData ? 'with_data' : 'no_data') . "_{$days}_{$topN}";
        $bypassCache = in_array(strtolower((string) $request->query('nocache', '0')), ['1', 'true', 'yes'], true);
        if ($bypassCache) Cache::forget($cacheKey);

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

        $cacheKey = "dashboard_top_services_" . ($includeData ? 'with_data' : 'no_data') . "_{$days}_{$topN}";
        $bypassCache = in_array(strtolower((string) $request->query('nocache', '0')), ['1', 'true', 'yes'], true);
        if ($bypassCache) Cache::forget($cacheKey);

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

        $cacheKey = 'dashboard_mmg_vs_occ_' . ($includeData ? 'with_data' : 'no_data') . "_{$days}";
        $bypassCache = in_array(strtolower((string) $request->query('nocache', '0')), ['1', 'true', 'yes'], true);
        if ($bypassCache) {
            Cache::forget($cacheKey);
        }

        $series = Cache::remember($cacheKey, 600, function () use ($includeData, $allowedCallTypes, $days) {
            $qMax = DB::table('ra_t_occ_cdr_detail');
            if (! $includeData) {
                $qMax->whereIn('call_type', $allowedCallTypes);
            }
            $anchorDate = $qMax->max('start_date');
            if (! $anchorDate) {
                return [];
            }
            $anchorDate = (string) $anchorDate;
            $fromDate = date('Y-m-d', strtotime($anchorDate . " -{$days} days"));

            $occQ = DB::table('ra_t_occ_cdr_detail')
                ->where('start_date', '>=', $fromDate)
                ->where('start_date', '<=', $anchorDate);
            if (! $includeData) {
                $occQ->whereIn('call_type', $allowedCallTypes);
            }
            $occMap = [];
            foreach ($occQ->selectRaw('start_date as d, COUNT(*) as nb')->groupBy('start_date')->get() as $row) {
                $d = (string) $row->d;
                $occMap[$d] = (int) $row->nb;
            }

            $mmgMap = [];
            foreach (DB::table('ra_t_mmg_cdr_det')
                ->where('start_date', '>=', $fromDate)
                ->where('start_date', '<=', $anchorDate)
                ->selectRaw('start_date as d, COUNT(*) as nb')
                ->groupBy('start_date')
                ->get() as $row) {
                $d = (string) $row->d;
                $mmgMap[$d] = (int) $row->nb;
            }

            $out = [];
            $cursor = $fromDate;
            while ($cursor <= $anchorDate) {
                $out[] = [
                    'date' => $cursor,
                    'label' => date('d/m/Y', strtotime($cursor)),
                    'occ' => $occMap[$cursor] ?? 0,
                    'mmg' => $mmgMap[$cursor] ?? 0,
                ];
                $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
            }

            return $out;
        });

        return response()->json($series);
    }
}