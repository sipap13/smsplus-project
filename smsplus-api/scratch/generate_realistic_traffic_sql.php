<?php
// Generates SQL INSERT statements to add realistic traffic for low-traffic services
// Usage: php generate_realistic_traffic_sql.php [days]
$days = (int)($argv[1] ?? 30);
$minEstablished = 200; // threshold to consider established
$seed = 20260528; mt_srand($seed);

// locate project DB container access
$end = trim(shell_exec("docker compose exec -T db psql -U postgres -d smsplus -t -c \"SELECT COALESCE(MAX(start_date::date)::text, current_date::text) FROM ra_t_occ_cdr_detail;\""));
if ($end === '') $end = date('Y-m-d');
$start = date('Y-m-d', strtotime($end . " -" . ($days-1) . " days"));

// fetch service counts CSV
$sql = "SELECT s.id::text || ',' || coalesce(s.keyword,'') || ',' || coalesce(s.numero_court,'') || ',' || coalesce(s.nom_fournisseur,'') || ',' ||
 (SELECT count(*) FROM ra_t_occ_cdr_detail o WHERE o.keyword = s.keyword AND o.start_date BETWEEN '{$start}' AND '{$end}') || ',' ||
 (SELECT count(*) FROM ra_t_mmg_cdr_det m WHERE m.service_type = s.keyword AND m.start_date BETWEEN '{$start}' AND '{$end}')
 FROM ra_t_services s WHERE s.numero_court IS NOT NULL AND trim(s.numero_court) <> '' ORDER BY s.id;";

// write SQL to temp file and pipe into psql to avoid shell quoting problems
$tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gen_real_sql.sql';
file_put_contents($tmpFile, $sql);
$cmd = "docker compose exec -T db psql -U postgres -d smsplus -At -F ',' -f - < {$tmpFile}";
$out = shell_exec($cmd);
@unlink($tmpFile);
if ($out === null) { fwrite(STDERR, "Failed to run psql\n"); exit(1); }
$lines = array_filter(array_map('trim', explode("\n", trim($out))));

$services = [];
foreach ($lines as $ln) {
    // id,keyword,numero,provider,occ,mmg
    $parts = str_getcsv($ln, ',');
    if (count($parts) < 6) continue;
    [$id,$keyword,$numero,$provider,$occ,$mmg] = $parts;
    $services[] = [
        'id' => (int)$id,
        'keyword' => $keyword,
        'numero' => $numero,
        'provider' => $provider,
        'occ' => (int)$occ,
        'mmg' => (int)$mmg,
        'total' => (int)$occ + (int)$mmg,
    ];
}

// build established rates
$establishedRates = [];
foreach ($services as $s) {
    if ($s['total'] >= $minEstablished) {
        $establishedRates[] = max(1, round($s['total'] / $days));
    }
}
if (empty($establishedRates)) { fwrite(STDERR, "No established services found; abort\n"); exit(1); }

function sampleRate($rates){
    $sum = array_sum($rates);
    $r = mt_rand() / mt_getrandmax() * $sum;
    $acc = 0; foreach ($rates as $rate){ $acc += $rate; if ($r <= $acc) return $rate; }
    return $rates[array_rand($rates)];
}

$insertOcc = [];
$insertMmg = [];
$batchSize = 500;
$insertSqls = [];

foreach ($services as $s) {
    if ($s['total'] >= $minEstablished) continue; // only generate for services below threshold
    $keyword = $s['keyword']; $num = $s['numero']; $prov = addslashes($s['provider']);
    $targetDaily = sampleRate($establishedRates);
    $targetDaily = max(1, (int)round($targetDaily * (0.75 + mt_rand()/mt_getrandmax()*0.5)));
    fwrite(STDERR, "Will generate for service id={$s['id']} keyword={$keyword} num={$num} targetDaily={$targetDaily}\n");
    for ($d=0;$d<$days;$d++){
        $date = date('Y-m-d', strtotime($start . " +{$d} days"));
        $occCount = (int) round($targetDaily * (0.4 + mt_rand()/mt_getrandmax()*0.6));
        $mmgCount = max(1, $targetDaily - $occCount);
        // create occ values
        for ($i=0;$i<$occCount;$i++){
            $a = '216'.str_pad((string)mt_rand(0,9999999),7,'0',STR_PAD_LEFT);
            $b = ctype_digit($num) && strpos($num,'216')!==0 ? '216'.ltrim($num,'0') : $num;
            $hour = mt_rand(0,23);
            $orig = date('Ymd').str_pad($hour,2,'0',STR_PAD_LEFT).str_pad(mt_rand(0,59),2,'0',STR_PAD_LEFT).str_pad(mt_rand(0,59),2,'0',STR_PAD_LEFT);
            $charge = number_format(0.05 + mt_rand()/mt_getrandmax()*0.6,3,'.','');
            $k = addslashes($keyword);
            $bEsc = addslashes($b);
            $provEsc = addslashes($prov);
            $insertOcc[] = "('OCC', '{$a}', '{$bEsc}', '{$date}', {$hour}, 'VAS', '74', 'PREPAID', 'HOME', '{$provEsc}', {$charge}, '{$k}', '{$orig}', now(), now())";
            if (count($insertOcc) >= $batchSize){
                $insertSqls[] = "INSERT INTO ra_t_occ_cdr_detail (datasource,a_msisdn,b_msisdn,start_date,start_hour,call_type,event_type,subscriber_type,roaming_type,partner,charge_amount,keyword,orig_start_time,created_at,updated_at) VALUES \n" . implode(",\n", $insertOcc) . ";";
                $insertOcc = [];
            }
        }
        for ($i=0;$i<$mmgCount;$i++){
            $a = '216'.str_pad((string)mt_rand(0,9999999),7,'0',STR_PAD_LEFT);
            $b = ctype_digit($num) && strpos($num,'216')!==0 ? '216'.ltrim($num,'0') : $num;
            $hour = mt_rand(0,23);
            $orig = date('Ymd').str_pad($hour,2,'0',STR_PAD_LEFT).str_pad(mt_rand(0,59),2,'0',STR_PAD_LEFT).str_pad(mt_rand(0,59),2,'0',STR_PAD_LEFT);
            $k = addslashes($keyword);
            $bEsc = addslashes($b);
            $insertMmg[] = "('MMG_AUTO', '{$a}', '{$bEsc}', '{$date}', {$hour}, 'MT', 'CONTENT_DELIVERY', 'VAS', 'SUCCESS', 'PREPAID', '{$k}', '{$orig}', now(), now())";
            if (count($insertMmg) >= $batchSize){
                $insertSqls[] = "INSERT INTO ra_t_mmg_cdr_det (ne,a_msisdn,b_msisdn,start_date,start_hour,event_type,event_type_orig,call_type,event_status,subscriber_type,service_type,orig_start_time,created_at,updated_at) VALUES \n" . implode(",\n", $insertMmg) . ";";
                $insertMmg = [];
            }
        }
    }
}
if (!empty($insertOcc)) $insertSqls[] = "INSERT INTO ra_t_occ_cdr_detail (datasource,a_msisdn,b_msisdn,start_date,start_hour,call_type,event_type,subscriber_type,roaming_type,partner,charge_amount,keyword,orig_start_time,created_at,updated_at) VALUES \n" . implode(",\n", $insertOcc) . ";";
if (!empty($insertMmg)) $insertSqls[] = "INSERT INTO ra_t_mmg_cdr_det (ne,a_msisdn,b_msisdn,start_date,start_hour,event_type,event_type_orig,call_type,event_status,subscriber_type,service_type,orig_start_time,created_at,updated_at) VALUES \n" . implode(",\n", $insertMmg) . ";";

// output SQL
foreach ($insertSqls as $s) echo $s . "\n";

echo "-- DONE\n";
