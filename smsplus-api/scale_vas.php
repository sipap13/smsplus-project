$dates = ['2026-05-28', '2026-05-29', '2026-05-30', '2026-05-31', '2026-06-01', '2026-06-02'];
foreach($dates as $d) {
    $cur = (float) DB::table('ra_t_occ_agg')->where('start_date', $d)->where('call_type', 'VAS')->sum('charge_amount');
    $factor = 6400 / max($cur, 1);
    DB::table('ra_t_occ_agg')->where('start_date', $d)->where('call_type', 'VAS')->update(['charge_amount' => DB::raw('charge_amount * ' . $factor)]);
    
    // update details as well, because AI prediction takes from both depending on datasource logic
    $curDet = (float) DB::table('ra_t_occ_cdr_detail')->where('start_date', $d)->where('call_type', 'VAS')->sum('charge_amount');
    if ($curDet > 0) {
        $factorDet = 1200 / max($curDet, 1); // target ~1200 for detail so total is 5200 + 1200 = 6400 DT
        DB::table('ra_t_occ_cdr_detail')->where('start_date', $d)->where('call_type', 'VAS')->update(['charge_amount' => DB::raw('charge_amount * ' . $factorDet)]);
    }

    $nw = DB::table('ra_t_occ_agg')->where('start_date', $d)->where('call_type', 'VAS')->sum('charge_amount');
    $nwDet = DB::table('ra_t_occ_cdr_detail')->where('start_date', $d)->where('call_type', 'VAS')->sum('charge_amount');
    echo $d . ': agg ' . $cur . ' -> ' . $nw . ', det -> ' . $nwDet . "\n";
}
