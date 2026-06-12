



$now = now();
$serviceCatalog = DB::table('ra_t_services')->get()->toArray();
$keywords = array_column($serviceCatalog, 'keyword');
$callTypes = ['VAS', 'SMS', 'VOICE', 'DATA'];
$partners = ['TUNTT', 'TOPNET', 'ORANGE', 'OOREDOO'];

$startDate = \Carbon\Carbon::create(2026, 5, 28);
$endDate = \Carbon\Carbon::create(2026, 6, 2);
$daysDiff = $startDate->diffInDays($endDate);

$occRows = [];
$mmgRows = [];

for ($day = 0; $day <= $daysDiff; $day++) {
    $currentDate = $startDate->copy()->addDays($day)->toDateString();
    
    // Generate ~3500 OCC rows per day
    for ($i = 0; $i < 3500; $i++) {
        $hour = random_int(0, 23);
        $keyword = $keywords[array_rand($keywords)];
        $callType = $callTypes[array_rand($callTypes)];
        $baseAmount = $callType === 'DATA' ? random_int(5, 60) / 10 : random_int(2, 15) / 10;
        
        $occRows[] = [
            'datasource' => $callType === 'DATA' ? 'OCC_DATA' : 'OCC_VAS',
            'a_msisdn' => '2169' . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
            'b_msisdn' => '2168' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT),
            'start_date' => $currentDate,
            'start_hour' => $hour,
            'call_type' => $callType,
            'event_type' => (string) random_int(70, 79),
            'subscriber_type' => random_int(0, 1) ? 'PREPAID' : 'HYB',
            'roaming_type' => 'HOME',
            'partner' => $partners[array_rand($partners)],
            'charge_amount' => number_format($baseAmount, 3, '.', ''),
            'keyword' => $keyword,
            'orig_start_time' => str_replace('-', '', $currentDate) . str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . str_pad((string) random_int(0, 59), 2, '0', STR_PAD_LEFT) . str_pad((string) random_int(0, 59), 2, '0', STR_PAD_LEFT),
            'created_at' => $now,
            'updated_at' => $now,
        ];
        
        if (count($occRows) >= 500) {
            DB::table('ra_t_occ_cdr_detail')->insert($occRows);
            $occRows = [];
        }
    }
    
    // Generate ~3500 MMG rows per day
    for ($i = 0; $i < 3500; $i++) {
        $hour = random_int(0, 23);
        $mmgRows[] = [
            'ne' => 'SMSGW' . random_int(1, 4),
            'a_msisdn' => '2169' . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
            'b_msisdn' => '2168' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT),
            'start_date' => $currentDate,
            'start_hour' => $hour,
            'event_type' => (string) random_int(70, 79),
            'event_type_orig' => 'MO_SMS',
            'call_type' => 'SMS',
            'event_status' => random_int(1, 100) > 85 ? 'FAILED' : 'SUCCESS',
            'subscriber_type' => random_int(0, 1) ? 'PREPAID' : 'HYB',
            'service_type' => $keywords[array_rand($keywords)],
            'orig_start_time' => str_replace('-', '', $currentDate) . str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . str_pad((string) random_int(0, 59), 2, '0', STR_PAD_LEFT) . str_pad((string) random_int(0, 59), 2, '0', STR_PAD_LEFT),
            'created_at' => $now,
            'updated_at' => $now,
        ];
        
        if (count($mmgRows) >= 500) {
            DB::table('ra_t_mmg_cdr_det')->insert($mmgRows);
            $mmgRows = [];
        }
    }
}

if (!empty($occRows)) DB::table('ra_t_occ_cdr_detail')->insert($occRows);
if (!empty($mmgRows)) DB::table('ra_t_mmg_cdr_det')->insert($mmgRows);

echo "Recent data filled successfully.\n";
