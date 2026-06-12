<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Config
$days = (int) ($argv[1] ?? 30);
$minEstablished = (int) ($argv[2] ?? 200); // established service threshold over period
$chunk = (int) ($argv[3] ?? 2000);
$seed = (int) ($argv[4] ?? 20260528);
mt_srand($seed);

echo "Generating realistic traffic using last {$days} days, minEstablished={$minEstablished}\n";

$end = DB::table('ra_t_occ_cdr_detail')->selectRaw("MAX(start_date::date)::text as maxd")->value('maxd') ?? date('Y-m-d');
$start = date('Y-m-d', strtotime($end . " -" . ($days-1) . " days"));
$periodDays = (int) $days;

// Gather service list
$services = DB::table('ra_t_services')->whereNotNull('numero_court')->where('numero_court','<>','')->get();
if ($services->isEmpty()) { echo "No services found.\n"; exit(1); }

// Compute existing counts per service (mmg + occ) over period
$serviceCounts = [];
foreach ($services as $s) {
    $keyword = trim((string)($s->keyword ?? ''));
    $num = trim((string)($s->numero_court ?? ''));

    $occQ = DB::table('ra_t_occ_cdr_detail')
        ->whereBetween('start_date', [$start, $end])
        ->where(function($q) use ($keyword, $num) {
            if ($keyword !== '') $q->where('keyword', $keyword);
            if ($num !== '') $q->orWhere('b_msisdn', 'LIKE', "%{$num}%");
        });
    $mmgQ = DB::table('ra_t_mmg_cdr_det')
        ->whereBetween('start_date', [$start, $end])
        ->where(function($q) use ($keyword, $num) {
            if ($keyword !== '') $q->where('service_type', $keyword);
            if ($num !== '') $q->orWhere('b_msisdn', 'LIKE', "%{$num}%");
        });

    $occCount = (int) $occQ->count();
    $mmgCount = (int) $mmgQ->count();
    $serviceCounts[$s->id] = ['service' => $s, 'occ' => $occCount, 'mmg' => $mmgCount, 'total' => $occCount + $mmgCount];
}

// Build distribution from established services
$establishedRates = [];
foreach ($serviceCounts as $sc) {
    if ($sc['total'] >= $minEstablished) {
        $rate = max(1, round($sc['total'] / $periodDays));
        $establishedRates[] = $rate;
    }
}

if (empty($establishedRates)) {
    echo "No established services found with threshold {$minEstablished}. Reduce threshold.\n";
    exit(1);
}

// Helper: sample a rate from distribution weighted by rate
function sampleRate(array $rates) {
    // weights proportional to rate
    $sum = array_sum($rates);
    $r = mt_rand() / mt_getrandmax() * $sum;
    $acc = 0.0;
    foreach ($rates as $rate) {
        $acc += $rate;
        if ($r <= $acc) return $rate;
    }
    return $rates[array_rand($rates)];
}

$toInsertOcc = [];
$toInsertMmg = [];
$occInserted = 0; $mmgInserted = 0;

foreach ($serviceCounts as $sc) {
    if ($sc['total'] >= max(10, round(array_sum(array_column($serviceCounts,'total'))/max(1,count($serviceCounts))*0.05))) {
        // has some traffic, skip
        continue;
    }
    $s = $sc['service'];
    $keyword = trim((string)($s->keyword ?? ''));
    $num = trim((string)($s->numero_court ?? ''));

    // sample target daily rate
    $targetDaily = sampleRate($establishedRates);
    // small randomization
    $targetDaily = max(1, (int) round($targetDaily * (0.75 + mt_rand()/mt_getrandmax()*0.5)));

    echo "Service id={$s->id} keyword={$keyword} num={$num} -> targetDaily={$targetDaily}\n";

    for ($d = 0; $d < $periodDays; $d++) {
        $date = date('Y-m-d', strtotime($start . " +{$d} days"));
        $occCountForDay = (int) round($targetDaily * (0.4 + mt_rand()/mt_getrandmax()*0.6)); // split between occ/mmg
        $mmgCountForDay = max(1, (int) round($targetDaily - $occCountForDay));

        // OCC rows
        for ($i=0;$i<$occCountForDay;$i++) {
            $hour = mt_rand(0,23);
            $toInsertOcc[] = [
                'datasource' => 'OCC',
                'a_msisdn' => '216' . str_pad((string)mt_rand(0,9999999),7,'0',STR_PAD_LEFT),
                'b_msisdn' => (ctype_digit($num) && !str_starts_with($num,'216')) ? '216'.ltrim($num,'0') : $num,
                'start_date' => $date,
                'start_hour' => $hour,
                'call_type' => 'VAS',
                'event_type' => '74',
                'subscriber_type' => 'PREPAID',
                'roaming_type' => 'HOME',
                'partner' => $s->nom_fournisseur,
                'charge_amount' => round(0.05 + mt_rand()/mt_getrandmax()*0.6,3),
                'keyword' => $keyword,
                'orig_start_time' => date('Ymd') . str_pad((string)$hour,2,'0',STR_PAD_LEFT) . str_pad((string)mt_rand(0,59),2,'0',STR_PAD_LEFT) . str_pad((string)mt_rand(0,59),2,'0',STR_PAD_LEFT),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
            if (count($toInsertOcc) >= $chunk) { DB::table('ra_t_occ_cdr_detail')->insert($toInsertOcc); $occInserted += count($toInsertOcc); $toInsertOcc = []; }
        }

        // MMG rows
        for ($i=0;$i<$mmgCountForDay;$i++) {
            $hour = mt_rand(0,23);
            $toInsertMmg[] = [
                'ne' => 'MMG_AUTO',
                'a_msisdn' => '216' . str_pad((string)mt_rand(0,9999999),7,'0',STR_PAD_LEFT),
                'b_msisdn' => (ctype_digit($num) && !str_starts_with($num,'216')) ? '216'.ltrim($num,'0') : $num,
                'start_date' => $date,
                'start_hour' => $hour,
                'event_type' => 'MT',
                'event_type_orig' => 'CONTENT_DELIVERY',
                'call_type' => 'VAS',
                'event_status' => 'SUCCESS',
                'subscriber_type' => 'PREPAID',
                'service_type' => $keyword,
                'orig_start_time' => date('Ymd') . str_pad((string)$hour,2,'0',STR_PAD_LEFT) . str_pad((string)mt_rand(0,59),2,'0',STR_PAD_LEFT) . str_pad((string)mt_rand(0,59),2,'0',STR_PAD_LEFT),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
            if (count($toInsertMmg) >= $chunk) { DB::table('ra_t_mmg_cdr_det')->insert($toInsertMmg); $mmgInserted += count($toInsertMmg); $toInsertMmg = []; }
        }
    }
}

if (!empty($toInsertOcc)) { DB::table('ra_t_occ_cdr_detail')->insert($toInsertOcc); $occInserted += count($toInsertOcc); }
if (!empty($toInsertMmg)) { DB::table('ra_t_mmg_cdr_det')->insert($toInsertMmg); $mmgInserted += count($toInsertMmg); }

echo "Inserted OCC: {$occInserted}, MMG: {$mmgInserted}\n";
