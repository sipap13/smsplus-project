<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ImportExcelData extends Command
{
    protected $signature = 'import:excel {type : occ|mmg} {path : Chemin fichier CSV/XLSX}';

    /**
     * Normalise le keyword technique pour matcher ra_t_services.keyword.
     * Objectif: enlever espaces, uniformiser la casse.
     */
    private function normalizeKeyword(string $keyword): string
    {
        return strtoupper(trim($keyword));
    }

    protected $description = 'Importer un fichier OCC/MMG (CSV ou XLSX)';

    /** SQLite limite ~999 variables par requête : batch = floor(900 / nb_colonnes). */
    private function maxBatchSize(string $type): int
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return 500; // Reduced from 2500 to avoid Postgres 65535 parameter limit
        }

        $cols = $type === 'occ' ? 14 : 12;

        return max(1, (int) floor(900 / $cols));
    }

    public function handle()
    {
        ini_set('memory_limit', '2048M');
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

        if (is_dir($path)) {
            $this->info("Importation du dossier : $path");
            $files = glob(rtrim($path, '/\\') . '/*.{csv,xlsx,xls}', GLOB_BRACE);
            $totalFiles = count($files);
            $this->info("Trouvé $totalFiles fichier(s) à importer.");

            $successCount = 0;
            foreach ($files as $idx => $file) {
                $this->info("Fichier [" . ($idx + 1) . "/$totalFiles] : " . basename($file));
                $res = $this->processSingleFile($type, $file);
                if ($res === self::SUCCESS) {
                    $successCount++;
                }
            }
            
            $this->info("Terminé. $successCount/$totalFiles fichiers importés avec succès.");
            return self::SUCCESS;
        }

        return $this->processSingleFile($type, $path);
    }

    private function processSingleFile(string $type, string $path): int
    {
        $importId = DB::table('ra_t_imports')->insertGetId([
            'filename' => basename($path),
            'type' => $type,
            'status' => 'processing',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $count = 0;
        $status = 'done';
        $errorMessage = null;

        try {
            $count = match ($ext) {
                'csv' => $this->importCsvFile($type, $path),
                'xlsx', 'xls' => $this->importExcelFile($type, $path),
                default => throw new \RuntimeException("Extension non supportee: .{$ext}"),
            };
            $this->info("OK {$count} lignes importees.");
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $status = 'error';
            $this->error("Erreur: " . $errorMessage);
        }

        DB::table('ra_t_imports')->where('id', $importId)->update([
            'status' => $status,
            'imported_rows' => $count,
            'error_message' => $errorMessage !== null ? substr($errorMessage, 0, 250) : null,
            'finished_at' => now(),
            'updated_at' => now(),
        ]);

        if ($status === 'done') {
            if ($type === 'occ') {
                \Illuminate\Support\Facades\Cache::forget('occ_total_count_v2');
                \Illuminate\Support\Facades\Cache::forget('occ_total_charge_v2');
            } elseif ($type === 'mmg') {
                \Illuminate\Support\Facades\Cache::forget('mmg_total_count');
                \Illuminate\Support\Facades\Cache::forget('mmg_total_charge');
            }
        }

        return $status === 'done' ? self::SUCCESS : self::FAILURE;
    }

    private function importCsvFile(string $type, string $path): int
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Impossible d'ouvrir: {$path}");
        }

        // Auto-detect delimiter
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            return 0;
        }
        rewind($handle);

        $delimiter = ',';
        if (strpos($firstLine, '|') !== false) {
            $delimiter = '|';
        } elseif (strpos($firstLine, ';') !== false) {
            $delimiter = ';';
        }

        $header = fgetcsv($handle, 0, $delimiter);
        if (!is_array($header)) {
            fclose($handle);
            return 0;
        }

        $chunk = $this->maxBatchSize($type);
        $total = 0;

        try {
            DB::transaction(function () use ($handle, $header, $type, $chunk, &$total, $delimiter) {
                $batch = [];
                while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
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

    private ?array $occColumnsList = null;
    private ?array $mmgColumnsList = null;

    private function prepareOcc(array $row): ?array
    {
        $filterCode = trim($row['FILTER_CODE'] ?? '');
        if ($filterCode !== '' && $filterCode !== '0') {
            return null; // Ignore les données rejetées ou inutiles si le code est présent
        }

        $aMsisdn = $this->clean($row['A_MSISDN'] ?? null);
        $origStart = $this->clean($row['ORIG_START_TIME'] ?? null);
        $procDate = $this->clean($row['PROC_DATE'] ?? null);
        $startDate = $this->extractDate($origStart) ?? $this->extractDate($procDate);
        $startHour = $this->toIntOrNull($row['PROC_HOUR'] ?? null) ?? $this->extractHour($origStart);

        if ($aMsisdn === null || $startDate === null) {
            return null;
        }

        // Service mapping OCC: normaliser keyword (trim + casse) pour matcher ra_t_services.keyword.
        // Note: si SERVICE_TYPE est vide/_N/_UN, keyword devient null.
        $keyword = $this->clean($row['SERVICE_TYPE'] ?? $row['KEYWORD'] ?? null);
        if ($keyword !== null) {
            $keyword = $this->normalizeKeyword($keyword);
        }

        $data = [
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
            'charge_amount' => $this->toDecimal($row['CHARGE_AMOUNT_ORIG'] ?? $row['CHARGE_AMOUNT'] ?? null, $row['TON_C'] ?? null),
            'keyword' => $keyword,
            'orig_start_time' => $origStart,
            'created_at'      => now(),
            'updated_at'      => now(),
        ];

        if ($this->occColumnsList === null) {
            $this->occColumnsList = \Illuminate\Support\Facades\Schema::getColumnListing('ra_t_occ_cdr_detail');
        }

        $validCols = array_flip($this->occColumnsList);
        foreach ($row as $key => $val) {
            $colName = strtolower($key);
            if (!array_key_exists($colName, $data) && array_key_exists($colName, $validCols)) {
                $data[$colName] = $this->clean($val);
            }
        }

        return $data;
    }

    /**
     * Statistiques post-import pour OCC: keyword NULL/empty.
     */
    private function logKeywordHealth(string $type, int $importedRows): void
    {
        if ($type !== 'occ') {
            return;
        }

        $nullEmptyCount = DB::table('ra_t_occ_cdr_detail')
            ->where(function ($q) {
                $q->whereNull('keyword')->orWhere('keyword', '=', '');
            })
            ->count();

        $total = DB::table('ra_t_occ_cdr_detail')->count();
        $pct = $total > 0 ? round(($nullEmptyCount / $total) * 100, 4) : 0;

        $this->info("[OCC keyword] null/empty: {$nullEmptyCount}/{$total} ({$pct}%). importedRows={$importedRows}");

        // Top 10 orig_start_time/call_type combos les plus touchés (helps diagnose source issues).
        $top = DB::table('ra_t_occ_cdr_detail')
            ->selectRaw('call_type, event_type, COUNT(*) as nb')
            ->where(function ($q) {
                $q->whereNull('keyword')->orWhere('keyword', '=', '');
            })
            ->groupBy('call_type', 'event_type')
            ->orderByDesc('nb')
            ->limit(10)
            ->get();

        foreach ($top as $row) {
            $this->info("[OCC keyword] offender call_type={$row->call_type} event_type={$row->event_type} nb={$row->nb}");
        }
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

        $data = [
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

        if ($this->mmgColumnsList === null) {
            $this->mmgColumnsList = \Illuminate\Support\Facades\Schema::getColumnListing('ra_t_mmg_cdr_det');
        }

        $validCols = array_flip($this->mmgColumnsList);
        foreach ($row as $key => $val) {
            $colName = strtolower($key);
            if (!array_key_exists($colName, $data) && array_key_exists($colName, $validCols)) {
                $data[$colName] = $this->clean($val);
            }
        }

        return $data;
    }

    private function insertBatch(string $type, array $batch): void
    {
        if ($batch === []) {
            return;
        }

        if ($type === 'occ') {
            // OCC idempotence: déduplication par clé métier (approx)
            // - évite les explosions de volume lors de reimports/replays
            // - clé choisie pour être stable pour une majorité de datasets OCC
            $table = 'ra_t_occ_cdr_detail';

            // Normalisation: keyword déjà normalisé à l’étape prepareOcc.
            // On déduplique en base via upsert (si supporté) sinon insert + ignore.
            // Laravel upsert est supporté sur MySQL/Postgres/SQLServer; sur SQLite/MySQL récents OK.
            // IMPORTANT: upsert nécessite une clé unique au niveau DB.
            // Si la contrainte n’existe pas, ça échouera : on retombe alors sur insert direct.
            $businessKey = ['a_msisdn', 'b_msisdn', 'start_date', 'start_hour', 'call_type', 'event_type', 'orig_start_time'];

            // Déduplication du batch en mémoire pour éviter SQLSTATE[21000] Cardinality violation
            // car PostgreSQL ne supporte pas d'upserter deux fois la même ligne dans la même requête.
            $uniqueBatch = [];
            foreach ($batch as $row) {
                $hash = implode('|', [
                    $row['a_msisdn'],
                    $row['b_msisdn'],
                    $row['start_date'],
                    $row['start_hour'],
                    $row['call_type'],
                    $row['event_type'],
                    $row['orig_start_time']
                ]);
                $uniqueBatch[$hash] = $row;
            }
            $batch = array_values($uniqueBatch);

            // Upsert privilégié (nécessite une contrainte UNIQUE au niveau DB).
            try {
                if ($this->occColumnsList === null) {
                    $this->occColumnsList = \Illuminate\Support\Facades\Schema::getColumnListing($table);
                }
                $updateCols = array_values(array_diff($this->occColumnsList, $businessKey, ['id', 'created_at']));
                
                DB::table($table)->upsert(
                    $batch,
                    $businessKey,
                    $updateCols
                );
                return;
            } catch (\Throwable $e) {
                // If we're inside a transaction, Postgres aborts the transaction on the first error.
                // We cannot fallback to insert/insertOrIgnore on this connection without resetting the transaction.
                // Instead, we throw the original error to be caught by the outer block and logged properly.
                throw $e;
            }
        }

        // MMG: insertion simple (on peut aussi upserter si besoin)
        $table = 'ra_t_mmg_cdr_det';
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