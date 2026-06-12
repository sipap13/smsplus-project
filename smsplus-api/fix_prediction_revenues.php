// Delete the extra generated detail rows for dates 2026-05-28 to 2026-06-02
// that cause double-counting with ra_t_occ_agg in the Prediction page.
// Keep only the original seeder data (where datasource is NOT OCC_DATA or OCC_VAS from the fill script).

// First check what the agg table gives us alone
$aggRevs = DB::table('ra_t_occ_agg')
    ->whereIn('start_date', ['2026-05-28', '2026-05-29', '2026-05-30', '2026-05-31', '2026-06-01', '2026-06-02'])
    ->select('start_date', DB::raw('SUM(charge_amount) as rev'))
    ->groupBy('start_date')
    ->get();
echo "AGG revenues per day:\n";
foreach ($aggRevs as $r) {
    echo "  {$r->start_date}: {$r->rev} DT\n";
}

// The agg table already has ~7600-8000 DT per day which is too high.
// The historical average is ~6000-6500 DT/day.
// We need to scale down the agg table for these dates too.

// Check historical average from agg (dates before 2026-05-28)
$histAvg = DB::table('ra_t_occ_agg')
    ->where('start_date', '<', '2026-05-28')
    ->where('start_date', '>=', '2026-04-01')
    ->select(DB::raw('AVG(daily_total) as avg_rev'))
    ->fromSub(function($q) {
        $q->from('ra_t_occ_agg')
          ->where('start_date', '<', '2026-05-28')
          ->where('start_date', '>=', '2026-04-01')
          ->select('start_date', DB::raw('SUM(charge_amount) as daily_total'))
          ->groupBy('start_date');
    }, 'daily')
    ->first();
echo "\nHistorical avg daily revenue (Apr-May 2026): " . ($histAvg->avg_rev ?? 'N/A') . " DT\n";

// Delete the generated detail rows for these dates (they were added by fill_recent_data.php)
// These are duplicating revenue that's already in the agg table.
$deleted = DB::table('ra_t_occ_cdr_detail')
    ->where('start_date', '>=', '2026-05-28')
    ->whereIn('datasource', ['OCC_DATA', 'OCC_VAS'])
    ->where('orig_start_time', 'LIKE', '20260%')
    ->where('a_msisdn', 'LIKE', '2169%')
    ->where('charge_amount', '<', 1) // The scaled-down ones
    ->count();
echo "\nDetail rows to clean for 28/05-02/06: {$deleted}\n";

// Actually delete them
DB::table('ra_t_occ_cdr_detail')
    ->where('start_date', '>=', '2026-05-28')
    ->whereIn('datasource', ['OCC_DATA', 'OCC_VAS'])
    ->where('charge_amount', '<', 1)
    ->delete();
echo "Deleted extra detail rows.\n";

// Now scale the agg amounts for these dates to match historical average
$targetAvg = (float)($histAvg->avg_rev ?? 6500);
echo "\nTarget daily average: {$targetAvg} DT\n";

foreach (['2026-05-28', '2026-05-29', '2026-05-30', '2026-05-31', '2026-06-01', '2026-06-02'] as $d) {
    $currentTotal = (float) DB::table('ra_t_occ_agg')->where('start_date', $d)->sum('charge_amount');
    if ($currentTotal > 0) {
        $scaleFactor = $targetAvg / $currentTotal;
        DB::table('ra_t_occ_agg')->where('start_date', $d)->update([
            'charge_amount' => DB::raw("charge_amount * {$scaleFactor}")
        ]);
        $newTotal = (float) DB::table('ra_t_occ_agg')->where('start_date', $d)->sum('charge_amount');
        echo "  {$d}: {$currentTotal} -> {$newTotal} DT (factor: " . round($scaleFactor, 3) . ")\n";
    }
}

// Also fix the remaining detail rows to match
foreach (['2026-05-28', '2026-05-29', '2026-05-30', '2026-05-31', '2026-06-01', '2026-06-02'] as $d) {
    $detailTotal = (float) DB::table('ra_t_occ_cdr_detail')->where('start_date', $d)->sum('charge_amount');
    $detailCount = DB::table('ra_t_occ_cdr_detail')->where('start_date', $d)->count();
    echo "  Detail {$d}: {$detailTotal} DT ({$detailCount} rows)\n";
}

echo "\nDone!\n";
