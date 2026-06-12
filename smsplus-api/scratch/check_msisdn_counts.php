<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$msisdn = '2168424169';

$occ = Illuminate\Support\Facades\DB::table('ra_t_occ_cdr_detail')
    ->whereRaw('(datasource IS NULL OR datasource <> ?) AND (a_msisdn = ? OR b_msisdn = ?)', ['OCC_AGG', $msisdn, $msisdn])
    ->count();

$mmg = Illuminate\Support\Facades\DB::table('ra_t_mmg_cdr_det')
    ->where(function ($query) use ($msisdn) {
        $query->where('a_msisdn', $msisdn)->orWhere('b_msisdn', $msisdn);
    })
    ->count();

echo "OCC={$occ}\n";
echo "MMG={$mmg}\n";
