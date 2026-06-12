<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportMmgAggData extends Command
{
    protected $signature = 'import:mmg-agg {path?}';
    protected $description = 'Importer le fichier MMG AGG CSV dans ra_t_mmg_agg_raw (séparateur |, enclos ")';

    public function handle(): int
    {
        ini_set('memory_limit', '2048M');

        $defaultPath = storage_path('imports/data mmg agg.csv');
        $path = $this->argument('path') ?: $defaultPath;

        if (!file_exists($path)) {
            $this->error("Fichier introuvable : {$path}");
            return self::FAILURE;
        }

        if (!Schema::hasTable('ra_t_mmg_agg_raw')) {
            $this->error('Table ra_t_mmg_agg_raw introuvable. Lance les migrations.');
            return self::FAILURE;
        }

        $this->info("Importation MMG AGG : {$path}");

        $handle = fopen($path, 'r');
        if (!$handle) {
            $this->error("Impossible d'ouvrir le fichier.");
            return self::FAILURE;
        }

        // Header-based mapping (col names appear like "B_MSISDN" etc.)
        $header = fgetcsv($handle, 0, '|', '"');
        if (!is_array($header) || count($header) < 2) {
            fclose($handle);
            $this->error('Header CSV invalide ou absent.');
            return self::FAILURE;
        }

        $headerMap = [];
        foreach ($header as $idx => $col) {
            $key = strtoupper(trim((string) $col, " \t\r\n\""));
            if ($key !== '') {
                $headerMap[$key] = $idx;
            }
        }

        $required = [
            'B_MSISDN',
            'START_DATE',
            'START_HOUR',
            'EVENT_TYPE',
            'CALL_TYPE',
            'EVENT_STATUS',
            'SUBSCRIBER_TYPE',
            'SERVICE_TYPE',
            'CDR_COUNT',
        ];

        $missing = array_values(array_diff($required, array_keys($headerMap)));
        if (!empty($missing)) {
            fclose($handle);
            $this->error('Colonnes manquantes dans le header: ' . implode(', ', $missing));
            return self::FAILURE;
        }

        $batchSize = 1000;
        $batch = [];
        $totalInserted = 0;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle, 0, '|', '"')) !== false) {
                if (!is_array($row) || count($row) === 1 && trim((string) ($row[0] ?? '')) === '') {
                    continue;
                }

                $b_msisdn = $this->cleanField($row[$headerMap['B_MSISDN']] ?? null);
                $start_date_raw = $this->cleanField($row[$headerMap['START_DATE']] ?? null);
                $start_hour = $this->cleanField($row[$headerMap['START_HOUR']] ?? null);
                $event_type = $this->cleanField($row[$headerMap['EVENT_TYPE']] ?? null);
                $call_type = $this->cleanField($row[$headerMap['CALL_TYPE']] ?? null);
                $event_status = $this->cleanField($row[$headerMap['EVENT_STATUS']] ?? null);
                $subscriber_type = $this->cleanField($row[$headerMap['SUBSCRIBER_TYPE']] ?? null);
                $service_type = $this->cleanField($row[$headerMap['SERVICE_TYPE']] ?? null);
                $cdr_count_raw = $this->cleanField($row[$headerMap['CDR_COUNT']] ?? null);

                if ($b_msisdn === null || $start_date_raw === null) {
                    continue;
                }

                // START_DATE format: DD/MM/YY -> keep as-is into start_date_raw
                // (ra_t_mmg_agg ETL expects DD/MM/YY in raw table)


                $batch[] = [
                    'b_msisdn' => $b_msisdn,
                    'start_date_raw' => $start_date_raw,
                    'start_hour' => is_numeric($start_hour) ? (int) $start_hour : null,
                    'event_type' => $event_type,
                    'call_type' => $call_type,
                    'event_status' => $event_status,
                    'subscriber_type' => $subscriber_type,
                    'service_type' => $service_type,
                    'cdr_count_raw' => $cdr_count_raw,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($batch) >= $batchSize) {
                    DB::table('ra_t_mmg_agg_raw')->insert($batch);
                    $totalInserted += count($batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                DB::table('ra_t_mmg_agg_raw')->insert($batch);
                $totalInserted += count($batch);
            }

            DB::commit();
            $this->info("OK. {$totalInserted} lignes insérées dans ra_t_mmg_agg_raw.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Erreur import mmg-agg: ' . $e->getMessage());
            return self::FAILURE;
        } finally {
            fclose($handle);
        }
    }

    private function cleanField(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }

        $s = trim((string) $v);
        // Remove possible outer quotes just in case
        $s = trim($s, '"');

        if ($s === '' || $s === '_N' || $s === '_UN') {
            return null;
        }

        return $s;
    }
}

