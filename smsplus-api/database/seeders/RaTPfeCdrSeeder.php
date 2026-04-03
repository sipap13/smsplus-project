<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RaTPfeCdrSeeder extends Seeder
{
    public function run(): void
    {
        $basePath = env('PFE_DATA_PATH', base_path('..\\pfe'));
        $mmgPath = $basePath . DIRECTORY_SEPARATOR . 'CDR MMG';
        $occPath = $basePath . DIRECTORY_SEPARATOR . 'CDR OCC';

        DB::table('ra_t_mmg_cdr_det')->truncate();
        DB::table('ra_t_occ_cdr_detail')->truncate();
        DB::table('ra_t_cdr_agg')->truncate();

        $this->importMmg($mmgPath);
        $this->importOcc($occPath);
        $this->buildMmgAggregation();
    }

    private function importMmg(string $directory): void
    {
        $rows = [];

        foreach ($this->csvFiles($directory) as $file) {
            $handle = fopen($file, 'r');
            if ($handle === false) {
                continue;
            }

            $header = fgetcsv($handle);
            if (!is_array($header)) {
                fclose($handle);
                continue;
            }

            while (($line = fgetcsv($handle)) !== false) {
                $row = $this->mapRow($header, $line);
                $startDate = $this->extractDate($row['ORIG_START_TIME'] ?? null);
                if (!$startDate || empty($row['A_MSISDN'])) {
                    continue;
                }

                $rows[] = [
                    'ne' => $this->nullable($row['NE'] ?? null),
                    'a_msisdn' => (string) ($row['A_MSISDN'] ?? ''),
                    'b_msisdn' => $this->nullable($row['B_MSISDN'] ?? null),
                    'start_date' => $startDate,
                    'start_hour' => $this->toIntOrNull($row['PROC_HOUR'] ?? null),
                    'event_type' => $this->nullable($row['EVENT_TYPE'] ?? null),
                    'event_type_orig' => $this->nullable($row['EVENT_TYPE_ORIG'] ?? null),
                    'call_type' => $this->nullable($row['CALL_TYPE'] ?? null),
                    'event_status' => $this->nullable($row['EVENT_STATUS'] ?? null),
                    'subscriber_type' => $this->nullable($row['SUBSCRIBER_TYPE'] ?? null),
                    'service_type' => $this->nullable($row['SERVICE_TYPE'] ?? null),
                    'orig_start_time' => $this->nullable($row['ORIG_START_TIME'] ?? null),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($rows) >= 1000) {
                    DB::table('ra_t_mmg_cdr_det')->insert($rows);
                    $rows = [];
                }
            }

            fclose($handle);
        }

        if (!empty($rows)) {
            DB::table('ra_t_mmg_cdr_det')->insert($rows);
        }
    }

    private function importOcc(string $directory): void
    {
        $rows = [];

        foreach ($this->csvFiles($directory) as $file) {
            $handle = fopen($file, 'r');
            if ($handle === false) {
                continue;
            }

            $header = fgetcsv($handle);
            if (!is_array($header)) {
                fclose($handle);
                continue;
            }

            while (($line = fgetcsv($handle)) !== false) {
                $row = $this->mapRow($header, $line);
                $startDate = $this->extractDate($row['ORIG_START_TIME'] ?? null);
                if (!$startDate || empty($row['A_MSISDN'])) {
                    continue;
                }

                $rows[] = [
                    'datasource' => 'OCC',
                    'a_msisdn' => (string) ($row['A_MSISDN'] ?? ''),
                    'b_msisdn' => $this->nullable($row['B_MSISDN'] ?? null),
                    'start_date' => $startDate,
                    'start_hour' => $this->toIntOrNull($row['PROC_HOUR'] ?? null),
                    'call_type' => $this->nullable($row['CALL_TYPE'] ?? null),
                    'event_type' => $this->nullable($row['EVENT_TYPE'] ?? null),
                    'subscriber_type' => $this->nullable($row['SUBSCRIBER_TYPE'] ?? null),
                    'roaming_type' => $this->nullable($row['ROAMING_TYPE'] ?? null),
                    'partner' => $this->nullable($row['PARTNER'] ?? null),
                    'charge_amount' => $this->toDecimal($row['CHARGE_AMOUNT_ORIG'] ?? null, $row['TON_C'] ?? null),
                    'keyword' => $this->nullable($row['SERVICE_TYPE'] ?? null),
                    'orig_start_time' => $this->nullable($row['ORIG_START_TIME'] ?? null),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($rows) >= 1000) {
                    DB::table('ra_t_occ_cdr_detail')->insert($rows);
                    $rows = [];
                }
            }

            fclose($handle);
        }

        if (!empty($rows)) {
            DB::table('ra_t_occ_cdr_detail')->insert($rows);
        }
    }

    private function buildMmgAggregation(): void
    {
        $grouped = DB::table('ra_t_mmg_cdr_det')
            ->select(
                'b_msisdn',
                'start_date',
                'start_hour',
                'event_type',
                'call_type',
                'event_status',
                'subscriber_type',
                'service_type',
                DB::raw('COUNT(*) as cdr_count')
            )
            ->groupBy(
                'b_msisdn',
                'start_date',
                'start_hour',
                'event_type',
                'call_type',
                'event_status',
                'subscriber_type',
                'service_type'
            )
            ->get();

        $toInsert = [];
        foreach ($grouped as $row) {
            $toInsert[] = [
                'b_msisdn' => $row->b_msisdn,
                'start_date' => $row->start_date,
                'start_hour' => $row->start_hour,
                'event_type' => $row->event_type,
                'call_type' => $row->call_type,
                'event_status' => $row->event_status,
                'subscriber_type' => $row->subscriber_type,
                'service_type' => $row->service_type,
                'cdr_count' => $row->cdr_count,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($toInsert) >= 1000) {
                DB::table('ra_t_cdr_agg')->insert($toInsert);
                $toInsert = [];
            }
        }

        if (!empty($toInsert)) {
            DB::table('ra_t_cdr_agg')->insert($toInsert);
        }
    }

    private function csvFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . '*.csv');
        return is_array($files) ? $files : [];
    }

    private function mapRow(array $header, array $line): array
    {
        $row = [];
        foreach ($header as $index => $column) {
            $row[$column] = $line[$index] ?? null;
        }

        return $row;
    }

    private function extractDate(?string $value): ?string
    {
        if (!$value || strlen($value) < 8) {
            return null;
        }

        $digits = substr($value, 0, 8);
        if (!ctype_digit($digits)) {
            return null;
        }

        return substr($digits, 0, 4) . '-' . substr($digits, 4, 2) . '-' . substr($digits, 6, 2);
    }

    private function toIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === '_N' || $value === '_UN') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function toDecimal(mixed $first, mixed $fallback): float
    {
        if (is_numeric($first)) {
            return (float) $first;
        }

        if (is_numeric($fallback)) {
            return (float) $fallback;
        }

        return 0.0;
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '' || $text === '_N' || $text === '_UN') {
            return null;
        }

        return $text;
    }
}
