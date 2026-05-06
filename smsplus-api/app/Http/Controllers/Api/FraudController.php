<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FraudController extends Controller
{
    public function usageHigh(Request $request)
    {
        $source = strtolower((string) $request->query('source', 'occ'));
        $metric = strtolower((string) $request->query('metric', 'traffic')); // traffic|revenue (occ_agg only)
        $threshold = (float) $request->query('threshold', 0.20); // 20%
        $minCount = (int) $request->query('min_count', 50);

        if (! in_array($source, ['occ', 'mmg', 'occ_agg', 'mmg_agg'], true)) {
            return response()->json([
                'message' => 'Paramètre source invalide. Valeurs: occ | mmg | occ_agg | mmg_agg',
            ], 422);
        }

        $isAgg = str_ends_with($source, '_agg');
        $isOcc = str_starts_with($source, 'occ');

        if ($metric !== 'traffic' && $metric !== 'revenue') {
            return response()->json([
                'message' => 'Paramètre metric invalide. Valeurs: traffic | revenue',
            ], 422);
        }

        if (! $isOcc && $metric === 'revenue') {
            return response()->json([
                'message' => 'metric=revenue est supporté uniquement pour OCC (occ_agg).',
            ], 422);
        }

        $table = match ($source) {
            'occ' => 'ra_t_occ_cdr_detail',
            'mmg' => 'ra_t_mmg_cdr_det',
            'occ_agg' => 'ra_t_occ_agg',
            'mmg_agg' => 'ra_t_mmg_agg',
            default => 'ra_t_occ_cdr_detail',
        };

        $serviceCol = $isOcc ? 'keyword' : 'service_type';
        $typeCol = 'call_type';
        $statusCol = $isOcc ? null : 'event_status';

        // Anchor date: max date for SMS+ traffic (VAS) with non-empty service key.
        $anchorQ = DB::table($table)
            ->where($typeCol, '=', 'VAS')
            ->whereRaw("COALESCE(NULLIF(TRIM($serviceCol), ''), '') <> ''");
        if (! $isOcc) {
            $anchorQ->where($statusCol, '=', 'Success');
        }

        $anchorDate = $anchorQ->max('start_date');
        if (! $anchorDate) {
            return response()->json([
                'meta' => [
                    'source' => $source,
                    'metric' => $metric,
                    'threshold' => $threshold,
                    'min_count' => $minCount,
                    'anchor_date' => null,
                ],
                'items' => [],
            ]);
        }

        // Rolling comparison: anchor day vs average of previous 7 days (excluding anchor day).
        $valueExpr = $isAgg
            ? ($isOcc && $metric === 'revenue' ? 'SUM(charge_amount)' : 'SUM(cdr_count)')
            : 'COUNT(*)';

        $extraWhere = '';
        if (! $isOcc) {
            $extraWhere = " AND $statusCol = 'Success' ";
        }

        $sql = "
WITH daily AS (
  SELECT
    start_date::date AS d,
    TRIM($serviceCol) AS svc,
    ($valueExpr)::double precision AS vol
  FROM $table
  WHERE $typeCol = 'VAS'
    AND COALESCE(NULLIF(TRIM($serviceCol), ''), '') <> ''
    $extraWhere
    AND start_date::date BETWEEN (:anchor::date - INTERVAL '7 days') AND :anchor::date
  GROUP BY 1, 2
),
curr AS (
  SELECT svc, vol AS vol_curr
  FROM daily
  WHERE d = :anchor::date
),
prev AS (
  SELECT svc, (SUM(vol)::double precision / 7.0) AS avg_prev_7d
  FROM daily
  WHERE d < :anchor::date
  GROUP BY 1
)
SELECT
  c.svc,
  s.nom_service,
  c.vol_curr,
  COALESCE(p.avg_prev_7d, 0) AS avg_prev_7d,
  CASE
    WHEN COALESCE(p.avg_prev_7d, 0) = 0 THEN NULL
    ELSE (c.vol_curr - p.avg_prev_7d) / p.avg_prev_7d
  END AS pct_increase
FROM curr c
LEFT JOIN prev p ON p.svc = c.svc
LEFT JOIN ra_t_services s ON s.keyword = c.svc
WHERE c.vol_curr >= :min_count
ORDER BY
  (CASE WHEN COALESCE(p.avg_prev_7d, 0) = 0 THEN 999999 ELSE (c.vol_curr - p.avg_prev_7d) / p.avg_prev_7d END) DESC,
  c.vol_curr DESC
LIMIT 200
";

        $rows = DB::select($sql, [
            'anchor' => $anchorDate,
            'min_count' => $minCount,
        ]);

        $items = collect($rows)->map(function ($r) use ($threshold) {
            $pct = $r->pct_increase;
            $pctFloat = $pct === null ? null : (float) $pct;
            return [
                'service_key' => $r->svc,
                'nom_service' => $r->nom_service,
                'vol_curr' => (int) $r->vol_curr,
                'avg_prev_7d' => (float) $r->avg_prev_7d,
                'pct_increase' => $pctFloat,
                'flag' => $pctFloat !== null ? ($pctFloat >= $threshold) : true,
            ];
        })->values();

        return response()->json([
            'meta' => [
                'source' => $source,
                'metric' => $metric,
                'threshold' => $threshold,
                'min_count' => $minCount,
                'anchor_date' => $anchorDate,
            ],
            'items' => $items,
        ]);
    }
}

