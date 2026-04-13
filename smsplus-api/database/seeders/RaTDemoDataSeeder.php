<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RaTDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $serviceCatalog = [
            ['nom_fournisseur' => 'TOPNET', 'nom_service' => 'SHOFHA', 'numero_court' => '2168000', 'keyword' => 'mb1', 'type_service' => 'Service', 'prix' => 0.500, 'actif' => true],
            ['nom_fournisseur' => 'TOPNET', 'nom_service' => 'SHOFHA SE', 'numero_court' => '2168000', 'keyword' => 'se1', 'type_service' => 'Service', 'prix' => 0.500, 'actif' => true],
            ['nom_fournisseur' => 'TOPNET', 'nom_service' => 'PLAY WIN', 'numero_court' => '2168000', 'keyword' => 'plw1', 'type_service' => 'jeu', 'prix' => 0.500, 'actif' => true],
            ['nom_fournisseur' => 'TOPNET', 'nom_service' => 'MELODY', 'numero_court' => '2168000', 'keyword' => 'mel1', 'type_service' => 'Service', 'prix' => 0.500, 'actif' => true],
            ['nom_fournisseur' => 'TOPNET', 'nom_service' => 'OUK', 'numero_court' => '2168000', 'keyword' => 'ouk1', 'type_service' => 'Service', 'prix' => 0.500, 'actif' => true],
            ['nom_fournisseur' => 'TOPNET', 'nom_service' => 'BUSINESS', 'numero_court' => '2168000', 'keyword' => 'bus1', 'type_service' => 'Service', 'prix' => 0.500, 'actif' => true],
            ['nom_fournisseur' => 'ORANGE', 'nom_service' => 'SPORT LIVE', 'numero_court' => '85810', 'keyword' => 'spr1', 'type_service' => 'Service', 'prix' => 0.700, 'actif' => true],
            ['nom_fournisseur' => 'OOREDOO', 'nom_service' => 'FUN QUIZ', 'numero_court' => '85810', 'keyword' => 'fun1', 'type_service' => 'jeu', 'prix' => 0.400, 'actif' => true],
        ];

        foreach ($serviceCatalog as $service) {
            DB::table('ra_t_services')->updateOrInsert(
                ['keyword' => $service['keyword']],
                array_merge($service, ['updated_at' => $now, 'created_at' => $now])
            );
        }

        $keywords = array_column($serviceCatalog, 'keyword');
        $callTypes = ['VAS', 'SMS', 'VOICE', 'DATA'];
        $partners = ['TUNTT', 'TOPNET', 'ORANGE', 'OOREDOO'];

        $rows = [];
        $totalRows = 3500;
        for ($i = 1; $i <= $totalRows; $i++) {
            $dayOffset = random_int(0, 59);
            $hour = random_int(0, 23);
            $date = now()->subDays($dayOffset)->toDateString();
            $keyword = $keywords[array_rand($keywords)];
            $callType = $callTypes[array_rand($callTypes)];
            $baseAmount = $callType === 'DATA' ? random_int(5, 60) / 10 : random_int(2, 15) / 10;

            $rows[] = [
                'datasource' => $callType === 'DATA' ? 'OCC_DATA' : 'OCC_VAS',
                'a_msisdn' => '2169' . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
                'b_msisdn' => '2168' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT),
                'start_date' => $date,
                'start_hour' => $hour,
                'call_type' => $callType,
                'event_type' => (string) random_int(70, 79),
                'subscriber_type' => random_int(0, 1) ? 'PREPAID' : 'HYB',
                'roaming_type' => 'HOME',
                'partner' => $partners[array_rand($partners)],
                'charge_amount' => number_format($baseAmount, 3, '.', ''),
                'keyword' => $keyword,
                'orig_start_time' => str_replace('-', '', $date) . str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . str_pad((string) random_int(0, 59), 2, '0', STR_PAD_LEFT) . str_pad((string) random_int(0, 59), 2, '0', STR_PAD_LEFT),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= 500) {
                DB::table('ra_t_occ_cdr_detail')->insert($rows);
                $rows = [];
            }
        }
        if (! empty($rows)) {
            DB::table('ra_t_occ_cdr_detail')->insert($rows);
        }

        $alerts = [];
        for ($i = 1; $i <= 18; $i++) {
            $kw = $keywords[array_rand($keywords)];
            $svc = collect($serviceCatalog)->firstWhere('keyword', $kw);

            $alerts[] = [
                'start_date' => now()->subDays(random_int(0, 20))->toDateString(),
                'nom_service' => $svc['nom_service'] ?? strtoupper($kw),
                'numero_court' => $svc['numero_court'] ?? '85810',
                'keyword' => $kw,
                'nom_fournisseur' => $svc['nom_fournisseur'] ?? 'TOPNET',
                'seuil_pct' => random_int(15, 45),
                'count_nb_sms' => random_int(15000, 180000),
                'motif' => random_int(0, 1) ? 'Pic de trafic anormal' : 'Variation forte vs moyenne 7 jours',
                'status' => (bool) random_int(0, 1),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('ra_t_alerts')->insert($alerts);

        Cache::flush();
    }
}

