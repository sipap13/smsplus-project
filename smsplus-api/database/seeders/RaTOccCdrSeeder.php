<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RaTOccCdrSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('ra_t_occ_cdr_detail')->insert([
            'datasource'      => 'OCC_VAS',
            'a_msisdn'        => '21698542320',
            'b_msisdn'        => '2168000',
            'start_date'      => '2026-01-21',
            'start_hour'      => 0,
            'call_type'       => 'VAS',
            'event_type'      => '74',
            'subscriber_type' => 'PREPAID',
            'roaming_type'    => 'HOME',
            'partner'         => 'TUNTT',
            'charge_amount'   => 0.500,
            'keyword'         => 'mb1',
            'orig_start_time' => '20260121001145',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        DB::table('ra_t_occ_cdr_detail')->insert([
            'datasource'      => 'OCC_VAS',
            'a_msisdn'        => '21696776950',
            'b_msisdn'        => '2168000',
            'start_date'      => '2026-01-21',
            'start_hour'      => 0,
            'call_type'       => 'VAS',
            'event_type'      => '74',
            'subscriber_type' => 'PREPAID',
            'roaming_type'    => 'HOME',
            'partner'         => 'TUNTT',
            'charge_amount'   => 0.500,
            'keyword'         => 'mb1',
            'orig_start_time' => '20260121001145',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        DB::table('ra_t_occ_cdr_detail')->insert([
            'datasource'      => 'OCC_VAS',
            'a_msisdn'        => '21698123456',
            'b_msisdn'        => '2168000',
            'start_date'      => '2026-01-22',
            'start_hour'      => 1,
            'call_type'       => 'VAS',
            'event_type'      => '74',
            'subscriber_type' => 'HYB',
            'roaming_type'    => 'HOME',
            'partner'         => 'TUNTT',
            'charge_amount'   => 0.500,
            'keyword'         => 'se1',
            'orig_start_time' => '20260122011200',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        DB::table('ra_t_occ_cdr_detail')->insert([
            'datasource'      => 'OCC_VAS',
            'a_msisdn'        => '21691234567',
            'b_msisdn'        => '2168000',
            'start_date'      => '2026-01-22',
            'start_hour'      => 2,
            'call_type'       => 'VAS',
            'event_type'      => '74',
            'subscriber_type' => 'PREPAID',
            'roaming_type'    => 'HOME',
            'partner'         => 'TUNTT',
            'charge_amount'   => 0.500,
            'keyword'         => 'plw1',
            'orig_start_time' => '20260122021300',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        DB::table('ra_t_occ_cdr_detail')->insert([
            'datasource'      => 'OCC_PSM',
            'a_msisdn'        => '21693216549',
            'b_msisdn'        => '2168000',
            'start_date'      => '2026-01-24',
            'start_hour'      => 14,
            'call_type'       => 'VAS',
            'event_type'      => '74',
            'subscriber_type' => 'HYB',
            'roaming_type'    => 'HOME',
            'partner'         => 'TUNTT',
            'charge_amount'   => 0.350,
            'keyword'         => 'mb1',
            'orig_start_time' => '20260124141500',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }
}