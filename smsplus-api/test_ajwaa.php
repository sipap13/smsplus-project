<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Illuminate\Support\Facades\DB;

$ajwaa = DB::table('ra_t_services')->where('nom_service', 'AJWAA')->get();
echo "AJWAA in DB:\n";
print_r($ajwaa->toArray());

$startDate = '2025-01-01';
$endDate = '2026-12-31';

$detail = DB::table('ra_t_occ_cdr_detail as o')
    ->join('ra_t_services as s', 's.keyword', '=', 'o.keyword')
    ->where('s.nom_service', 'AJWAA')
    ->whereBetween('o.start_date', [$startDate, $endDate])
    ->selectRaw('o.keyword, s.nom_service, s.prix as prix_theorique, COUNT(*) as nb_cdr, SUM(o.charge_amount) as total_reel, SUM(s.prix) as total_theorique')
    ->groupBy('o.keyword', 's.nom_service', 's.prix')
    ->get()
    ->keyBy('keyword');

$agg = DB::table('ra_t_occ_agg as oa')
    ->join('ra_t_services as s', 's.keyword', '=', 'oa.keyword')
    ->where('s.nom_service', 'AJWAA')
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
echo "\nDashboard logic result for AJWAA:\n";
print_r($merged->toArray());
