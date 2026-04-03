<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ImportExcelData extends Command
{
    protected $signature = 'import:excel {type : occ|mmg} {path : Chemin fichier CSV/XLSX}';

    protected $description = 'Importer un fichier OCC/MMG (CSV ou XLSX)';

    /** SQLite limite ~999 variables par requête : batch = floor(900 / nb_colonnes). */
    private function maxBatchSize(string $type): int
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return 2500;
        }

        $cols = $type === 'occ' ? 14 : 12;

        return max(1, (int) floor(900 / $cols));
    }

    public function handle()
    {
        $type = strtolower((string) $this->argument('type'));
        $path = (string) $this->argument('path');

        if (!file_exists($path)) {
            $this->error("Fichier introuvable: {$path}");
            return self::FAILURE;
        }

        if (!in_array($type, ['occ', 'mmg'], true)) {
            $this->error("Type invalide: {$type}. Utilise occ ou mmg.");
            return self::FAILURE;
        }

        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $this->line("Import {$type}: {$path}");

        $count = match ($ext) {
            'csv' => $this->importCsvFile($type, $path),
            'xlsx', 'xls' => $this->importExcelFile($type, $path),
            default => throw new \RuntimeException("Extension non supportee: .{$ext}"),
        };

        $this->info("OK {$count} lignes importees.");
        return self::SUCCESS;
    }

    private function importCsvFile(string $type, string $path): int
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Impossible d'ouvrir: {$path}");
        }

        $header = fgetcsv($handle);
        if (!is_array($header)) {
            fclose($handle);

            return 0;
        }

        $chunk = $this->maxBatchSize($type);
        $total = 0;

        try {
            DB::transaction(function () use ($handle, $header, $type, $chunk, &$total) {
                $batch = [];
                while (($line = fgetcsv($handle)) !== false) {
                    $mapped = $this->mapRow($header, $line);
                    $prepared = $type === 'occ' ? $this->prepareOcc($mapped) : $this->prepareMmg($mapped);
                    if ($prepared === null) {
                        continue;
                    }
                    $batch[] = $prepared;
                    if (count($batch) >= $chunk) {
                        $this->insertBatch($type, $batch);
                        $total += count($batch);
                        $batch = [];
                    }
                }
                if ($batch !== []) {
                    $this->insertBatch($type, $batch);
                    $total += count($batch);
                }
            });
        } finally {
            fclose($handle);
        }

        return $total;
    }

    private function importExcelFile(string $type, string $path): int
    {
        $sheets = Excel::toArray([], $path);
        if (empty($sheets) || empty($sheets[0]) || !is_array($sheets[0][0] ?? null)) {
            return 0;
        }

        $rows = $sheets[0];
        $header = $rows[0];
        $chunk = $this->maxBatchSize($type);
        $total = 0;

        DB::transaction(function () use ($rows, $header, $type, $chunk, &$total) {
            $batch = [];
            for ($i = 1; $i < count($rows); $i++) {
                $line = $rows[$i];
                if (!is_array($line)) {
                    continue;
                }
                $mapped = $this->mapRow($header, $line);
                $prepared = $type === 'occ' ? $this->prepareOcc($mapped) : $this->prepareMmg($mapped);
                if ($prepared === null) {
                    continue;
                }
                $batch[] = $prepared;
                if (count($batch) >= $chunk) {
                    $this->insertBatch($type, $batch);
                    $total += count($batch);
                    $batch = [];
                }
            }
            if ($batch !== []) {
                $this->insertBatch($type, $batch);
                $total += count($batch);
            }
        });

        return $total;
    }

    private function mapRow(array $header, array $line): array
    {
        $row = [];
        foreach ($header as $idx => $column) {
            $key = strtoupper(trim((string) $column));
            if ($key === '') {
                continue;
            }
            $row[$key] = $line[$idx] ?? null;
        }
        return $row;
    }

    private function prepareOcc(array $row): ?array
    {
        $aMsisdn = $this->clean($row['A_MSISDN'] ?? null);
        $origStart = $this->clean($row['ORIG_START_TIME'] ?? null);
        $procDate = $this->clean($row['PROC_DATE'] ?? null);
        $startDate = $this->extractDate($origStart) ?? $this->extractDate($procDate);
        $startHour = $this->toIntOrNull($row['PROC_HOUR'] ?? null) ?? $this->extractHour($origStart);

        if ($aMsisdn === null || $startDate === null) {
            return null;
        }

        return [
            'datasource' => 'OCC',
            'a_msisdn' => $aMsisdn,
            'b_msisdn' => $this->clean($row['B_MSISDN'] ?? null),
            'start_date' => $startDate,
            'start_hour' => $startHour,
            'call_type' => $this->clean($row['CALL_TYPE'] ?? null),
            'event_type' => $this->clean($row['EVENT_TYPE'] ?? null),
            'subscriber_type' => $this->clean($row['SUBSCRIBER_TYPE'] ?? null),
            'roaming_type' => $this->clean($row['ROAMING_TYPE'] ?? null),
            'partner' => $this->clean($row['PARTNER'] ?? null),
            'charge_amount' => $this->toDecimal($row['CHARGE_AMOUNT_ORIG'] ?? null, $row['TON_C'] ?? null),
            'keyword' => $this->clean($row['SERVICE_TYPE'] ?? null),
            'orig_start_time' => $origStart,
            'created_at'      => now(),
            'updated_at'      => now(),
        ];
    }

    private function prepareMmg(array $row): ?array
    {
        $aMsisdn = $this->clean($row['A_MSISDN'] ?? null);
        $origStart = $this->clean($row['ORIG_START_TIME'] ?? null);
        $procDate = $this->clean($row['PROC_DATE'] ?? null);
        $startDate = $this->extractDate($origStart) ?? $this->extractDate($procDate);
        $startHour = $this->toIntOrNull($row['PROC_HOUR'] ?? null) ?? $this->extractHour($origStart);

        if ($aMsisdn === null || $startDate === null) {
            return null;
        }

        return [
            'ne' => $this->clean($row['NE'] ?? null),
            'a_msisdn' => $aMsisdn,
            'b_msisdn' => $this->clean($row['B_MSISDN'] ?? null),
            'start_date' => $startDate,
            'start_hour' => $startHour,
            'event_type' => $this->clean($row['EVENT_TYPE'] ?? null),
            'event_type_orig' => $this->clean($row['EVENT_TYPE_ORIG'] ?? null),
            'call_type' => $this->clean($row['CALL_TYPE'] ?? null),
            'event_status' => $this->clean($row['EVENT_STATUS'] ?? null),
            'subscriber_type' => $this->clean($row['SUBSCRIBER_TYPE'] ?? null),
            'service_type' => $this->clean($row['SERVICE_TYPE'] ?? null),
            'orig_start_time' => $origStart,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function insertBatch(string $type, array $batch): void
    {
        $table = $type === 'occ' ? 'ra_t_occ_cdr_detail' : 'ra_t_mmg_cdr_det';
        DB::table($table)->insert($batch);
    }

    private function clean(mixed $value): ?string
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

    private function extractDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $value);
        if ($digits === null || strlen($digits) < 8) {
            return null;
        }
        $ymd = substr($digits, 0, 8);
        return substr($ymd, 0, 4) . '-' . substr($ymd, 4, 2) . '-' . substr($ymd, 6, 2);
    }

    private function extractHour(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $value);
        if ($digits === null || strlen($digits) < 10) {
            return null;
        }
        return (int) substr($digits, 8, 2);
    }

    private function toIntOrNull(mixed $value): ?int
    {
        $clean = $this->clean($value);
        if ($clean === null || !is_numeric($clean)) {
            return null;
        }
        return (int) $clean;
    }

    private function toDecimal(mixed ...$values): float
    {
        foreach ($values as $value) {
            $clean = $this->clean($value);
            if ($clean !== null && is_numeric(str_replace(',', '.', $clean))) {
                return (float) str_replace(',', '.', $clean);
            }
        }
        return 0.0;
    }
}