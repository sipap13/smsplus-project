<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SosController extends Controller
{
    public function kpis(Request $request)
    {
        $granularity = strtolower((string) $request->query('granularity', 'day'));
        $granularity = in_array($granularity, ['day', 'month'], true) ? $granularity : 'day';

        $days = max(1, min((int) $request->query('days', 30), 365));
        $type = strtoupper((string) $request->query('type', 'ALL'));
        $type = in_array($type, ['ALL', 'SOLDE', 'DATA'], true) ? $type : 'ALL';

        $cacheKey = "sos_kpis_{$granularity}_{$days}_{$type}";
        $bypassCache = in_array(strtolower((string) $request->query('nocache', '0')), ['1', 'true', 'yes'], true);
        if ($bypassCache) Cache::forget($cacheKey);

        $data = Cache::remember($cacheKey, 300, function () use ($granularity, $days, $type) {
            $qBase = DB::table('ra_t_sos_transactions')
                ->where('granted_at', '>=', now()->subDays($days)->startOfDay());

            if ($type !== 'ALL') {
                $qBase->where('sos_type', $type);
            }

            $total = (clone $qBase)->count();
            $uniqueMsisdn = (clone $qBase)->distinct('msisdn')->count('msisdn');

            $byStatus = (clone $qBase)
                ->selectRaw("status, COUNT(*) as nb, SUM(credit_amount) as credit, SUM(repaid_amount) as repaid, SUM(fee_amount) as fees")
                ->groupBy('status')
                ->get()
                ->keyBy('status');

            $seriesGroupExpr = $granularity === 'month'
                ? "strftime('%Y-%m', granted_at)"
                : "date(granted_at)";

            $series = (clone $qBase)
                ->selectRaw("{$seriesGroupExpr} as period, SUM(credit_amount) as credit, SUM(repaid_amount) as repaid, SUM(fee_amount) as fees, COUNT(*) as nb")
                ->groupBy('period')
                ->orderBy('period')
                ->get();

            // bad debts: granted in a month, still not fully repaid after X months
            $badDebt3 = $this->badDebtForMonths(3, $type);
            $badDebt6 = $this->badDebtForMonths(6, $type);

            return [
                'meta' => [
                    'granularity' => $granularity,
                    'days' => $days,
                    'type' => $type,
                ],
                'summary' => [
                    'total_sos' => $total,
                    'parc_msisdn' => $uniqueMsisdn,
                    'credit_total' => (float) (clone $qBase)->sum('credit_amount'),
                    'repaid_total' => (float) (clone $qBase)->sum('repaid_amount'),
                    'fees_total' => (float) (clone $qBase)->sum('fee_amount'),
                    'revenus_total' => (float) ((clone $qBase)->sum('fee_amount')), // revenue sharing placeholder = fees
                    'rembourses' => (int) ($byStatus['REMBOURSE']->nb ?? 0),
                    'partiels' => (int) ($byStatus['PARTIEL']->nb ?? 0),
                    'impayes' => (int) ($byStatus['IMPAYE']->nb ?? 0),
                ],
                'bad_debts' => [
                    'after_3_months' => $badDebt3,
                    'after_6_months' => $badDebt6,
                ],
                'series' => $series,
            ];
        });

        return response()->json($data);
    }

    public function badDebts(Request $request)
    {
        $months = (int) $request->query('months', 3);
        $months = in_array($months, [3, 6], true) ? $months : 3;

        $type = strtoupper((string) $request->query('type', 'ALL'));
        $type = in_array($type, ['ALL', 'SOLDE', 'DATA'], true) ? $type : 'ALL';

        return response()->json($this->badDebtForMonths($months, $type));
    }

    private function badDebtForMonths(int $months, string $type): array
    {
        $start = now()->subMonths($months)->startOfMonth();
        $end = now()->subMonths($months)->endOfMonth();

        $q = DB::table('ra_t_sos_transactions')
            ->whereBetween('granted_at', [$start, $end])
            ->whereIn('status', ['PARTIEL', 'IMPAYE']);

        if ($type !== 'ALL') {
            $q->where('sos_type', $type);
        }

        return [
            'reference_month' => $start->format('Y-m'),
            'months' => $months,
            'count' => (int) (clone $q)->count(),
            'credit_total' => (float) (clone $q)->sum('credit_amount'),
            'repaid_total' => (float) (clone $q)->sum('repaid_amount'),
            'unpaid_total' => (float) ((clone $q)->sum('credit_amount') - (clone $q)->sum('repaid_amount')),
        ];
    }
}

