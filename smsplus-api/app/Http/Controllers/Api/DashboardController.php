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
        $limit = max(1, min((int) $request->query('limit', 500), 5000));

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

        $data = Cache::remember($cacheKey, 3600, function () use ($days, $limit, $granularity, $effectiveDate, $includeData, $allowedCallTypes) {
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
}