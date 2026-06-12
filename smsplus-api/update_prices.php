<?php
// Script temporaire pour mettre à jour les prix des services à partir des revenus réels
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Calculer le prix unitaire moyen par keyword depuis les CDR réels
$results = DB::table('ra_t_occ_cdr_detail')
    ->where('call_type', 'VAS')
    ->selectRaw('keyword, SUM(charge_amount) as rev, COUNT(*) as nb')
    ->groupBy('keyword')
    ->havingRaw('COUNT(*) > 0')
    ->get();

$updated = 0;
foreach ($results as $r) {
    $prix = $r->nb > 0 ? round($r->rev / $r->nb, 3) : 0;
    if ($prix > 0) {
        $n = DB::table('ra_t_services')
            ->where('keyword', $r->keyword)
            ->where('prix', 0)
            ->update(['prix' => $prix]);
        if ($n > 0) {
            echo "  {$r->keyword}: {$prix} DT (mis à jour)\n";
            $updated += $n;
        }
    }
}

echo "\nTotal services mis à jour: {$updated}\n";

// Vérification
$still_zero = DB::table('ra_t_services')->where('prix', 0)->count();
$total = DB::table('ra_t_services')->count();
echo "Services avec prix > 0: " . ($total - $still_zero) . " / {$total}\n";
