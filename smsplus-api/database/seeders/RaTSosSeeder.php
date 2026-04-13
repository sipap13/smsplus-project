<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RaTSosSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [];

        // Create ~9 months of synthetic SOS activity
        $total = 9000;
        for ($i = 0; $i < $total; $i++) {
            $type = random_int(0, 1) ? 'SOLDE' : 'DATA';
            $daysAgo = random_int(0, 270);
            $grantedAt = now()->subDays($daysAgo)->setTime(random_int(0, 23), random_int(0, 59), random_int(0, 59));

            $credit = $type === 'DATA' ? random_int(50, 400) / 10 : random_int(10, 200) / 10; // 1.0..20.0 or 5.0..40.0
            $fee = round($credit * (random_int(5, 15) / 100), 3); // 5%..15%

            // status distribution
            $r = random_int(1, 100);
            if ($r <= 65) {
                $status = 'REMBOURSE';
                $repaid = $credit;
                $repaidAt = (clone $grantedAt)->addDays(random_int(0, 25));
            } elseif ($r <= 85) {
                $status = 'PARTIEL';
                $repaid = round($credit * (random_int(10, 90) / 100), 3);
                $repaidAt = (clone $grantedAt)->addDays(random_int(10, 60));
            } else {
                $status = 'IMPAYE';
                $repaid = 0;
                $repaidAt = null;
            }

            $rows[] = [
                'msisdn' => '2169' . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
                'sos_type' => $type,
                'granted_at' => $grantedAt,
                'credit_amount' => number_format($credit, 3, '.', ''),
                'fee_amount' => number_format($fee, 3, '.', ''),
                'repaid_amount' => number_format($repaid, 3, '.', ''),
                'repaid_at' => $repaidAt,
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= 800) {
                DB::table('ra_t_sos_transactions')->insert($rows);
                $rows = [];
            }
        }

        if (! empty($rows)) {
            DB::table('ra_t_sos_transactions')->insert($rows);
        }
    }
}

