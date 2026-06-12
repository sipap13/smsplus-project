<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Configuration
$occPerService = (int) ($argv[1] ?? 200); // number of OCC detail rows per service
$mmgPerService = (int) ($argv[2] ?? 200); // number of MMG detail rows per service
$chunk = 1000;

echo "Ensure services have numero_court and generate traffic: OCC={$occPerService}, MMG={$mmgPerService}\n";

// Load services
$services = DB::table('ra_t_services')->orderBy('id')->get();
if ($services->isEmpty()) {
    echo "No services found in ra_t_services. Aborting.\n";
    exit(1);
}

// gather existing numeros
$existingNumeros = DB::table('ra_t_services')->whereNotNull('numero_court')->where('numero_court','<>','')->pluck('numero_court')->map(fn($v)=>trim((string)$v))->unique()->values()->all();

$nextShort = 9000; // fallback starting short code
while (in_array((string)$nextShort, $existingNumeros)) $nextShort++;

$assigned = 0;
DB::beginTransaction();
try {
    foreach ($services as $s) {
        $num = trim((string)($s->numero_court ?? ''));
        if ($num === '') {
            // assign next available short code
            while (in_array((string)$nextShort, $existingNumeros)) $nextShort++;
            $num = (string)$nextShort;
            DB::table('ra_t_services')->where('id', $s->id)->update(['numero_court' => $num, 'updated_at' => now()->toDateTimeString()]);
            $existingNumeros[] = $num;
            $assigned++;
            $nextShort++;
            echo "Assigned numero_court {$num} to service id={$s->id} keyword={$s->keyword}\n";
        }
    }
    DB::commit();
} catch (Throwable $e) {
    DB::rollBack();
    echo "Error assigning numeros: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Assigned {$assigned} new numero_court values.\n";

// Determine date range for inserted rows: use latest existing date if present
$minDate = DB::table('ra_t_occ_cdr_detail')->selectRaw("MIN(start_date::date)::text as md")->value('md');
$maxDate = DB::table('ra_t_occ_cdr_detail')->selectRaw("MAX(start_date::date)::text as md")->value('md');
if (!$minDate || !$maxDate) {
    $maxDate = date('Y-m-d');
    $minDate = date('Y-m-d', strtotime($maxDate . ' -30 days'));
}

echo "Using date range {$minDate} -> {$maxDate} for new rows.\n";

// Helpers
$randFloat = fn($min,$max) => $min + ($max-$min) * (mt_rand()/mt_getrandmax());
$makeMsisdn = function() use ($randFloat) {
    $prefix = ['2169'=>0.58,'2162'=>0.22,'2165'=>0.12,'2164'=>0.08];
    $r = $randFloat(0, array_sum($prefix));
    $acc=0; foreach($prefix as $k=>$w){$acc+=$w; if($r<=$acc){$p=$k;break;}} if(!isset($p)) $p='2169';
    return $p . str_pad((string) mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT);
};
$pickHour = function(){ return mt_rand(0,23); };
$origStartTime = function($date,$hour){ return str_replace('-','',$date) . str_pad((string)$hour,2,'0',STR_PAD_LEFT) . str_pad((string)mt_rand(0,59),2,'0',STR_PAD_LEFT) . str_pad((string)mt_rand(0,59),2,'0',STR_PAD_LEFT); };
$occCharge = function(){ return round(max(0.05, $randFloat(0.05,0.6)),3); };

// Insert traffic
$occInserted=0; $mmgInserted=0;
$occBatch=[]; $mmgBatch=[];
foreach ($services as $s) {
    $b_msisdn = trim((string)($s->numero_court ?? ''));
    if ($b_msisdn === '') continue;

    // ensure formatted with 216 prefix for storage (consistent)
    if (ctype_digit($b_msisdn) && !str_starts_with($b_msisdn,'216')) {
        $b_store = '216' . ltrim($b_msisdn,'0');
    } else {
        $b_store = $b_msisdn;
    }

    // OCC rows
    for ($i=0;$i<$occPerService;$i++){
        $date = date('Y-m-d', mt_rand(strtotime($minDate), strtotime($maxDate)));
        $hour = $pickHour();
        $occBatch[] = [
            'datasource' => 'OCC',
            'a_msisdn' => $makeMsisdn(),
            'b_msisdn' => $b_store,
            'start_date' => $date,
            'start_hour' => $hour,
            'call_type' => 'VAS',
            'event_type' => '74',
            'subscriber_type' => 'PREPAID',
            'roaming_type' => 'HOME',
            'partner' => $s->nom_fournisseur,
            'charge_amount' => $occCharge(),
            'keyword' => $s->keyword ?? '',
            'orig_start_time' => $origStartTime($date,$hour),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];
        if (count($occBatch) >= $chunk) {
            DB::table('ra_t_occ_cdr_detail')->insert($occBatch);
            $occInserted += count($occBatch);
            $occBatch = [];
        }
    }

    // MMG rows
    for ($i=0;$i<$mmgPerService;$i++){
        $date = date('Y-m-d', mt_rand(strtotime($minDate), strtotime($maxDate)));
        $hour = $pickHour();
        $mmgBatch[] = [
            'ne' => 'MMG_AUTO',
            'a_msisdn' => $makeMsisdn(),
            'b_msisdn' => $b_store,
            'start_date' => $date,
            'start_hour' => $hour,
            'event_type' => 'MT',
            'event_type_orig' => 'CONTENT_DELIVERY',
            'call_type' => 'VAS',
            'event_status' => 'SUCCESS',
            'subscriber_type' => 'PREPAID',
            'service_type' => $s->keyword ?? '',
            'orig_start_time' => $origStartTime($date,$hour),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];
        if (count($mmgBatch) >= $chunk) {
            DB::table('ra_t_mmg_cdr_det')->insert($mmgBatch);
            $mmgInserted += count($mmgBatch);
            $mmgBatch = [];
        }
    }
}

if (!empty($occBatch)) { DB::table('ra_t_occ_cdr_detail')->insert($occBatch); $occInserted += count($occBatch); }
if (!empty($mmgBatch)) { DB::table('ra_t_mmg_cdr_det')->insert($mmgBatch); $mmgInserted += count($mmgBatch); }

echo "Inserted OCC rows: {$occInserted}\nInserted MMG rows: {$mmgInserted}\n";

// Update todo
