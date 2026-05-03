<?php

namespace App\Jobs;

use App\Models\Import;
use App\Services\EtlMonitorService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 3600;

    public function __construct(private readonly Import $import) {}

    public function handle(): void
    {
        $type = $this->import->type;
        $path = storage_path("imports/temp/{$this->import->id}_{$this->import->filename}");

        if (! file_exists($path)) {
            $this->failImport("Fichier temporaire introuvable : {$path}");

            return;
        }

        $this->import->update(['status' => 'processing', 'started_at' => now()]);

        /** @var EtlMonitorService $monitor */
        $monitor = app(EtlMonitorService::class);
        $jobName = "import_{$type}_" . strtolower(pathinfo($this->import->filename, PATHINFO_EXTENSION));
        $etlJob = $monitor->startJob($jobName, 'import', [
            'filename' => $this->import->filename,
            'import_id' => $this->import->id,
            'type' => $type,
        ]);

        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        try {
            $count = match ($ext) {
                'csv' => $this->importCsvFile($type, $path, $monitor, $etlJob),
                'xlsx', 'xls' => $this->importExcelFile($type, $path, $monitor, $etlJob),
                default => throw new \RuntimeException("Extension non supportée : .{$ext}"),
            };

            $this->import->update([
                'status' => 'done',
                'imported_rows' => $count,
                'finished_at' => now(),
            ]);

            $monitor->finishJob($etlJob, ['rows_inserted' => $count]);
            $this->notifyImportResult($count, false);
        } catch (\Throwable $e) {
            $this->failImport($e->getMessage());
            $monitor->failJob($etlJob, $e->getMessage());
            $this->notifyImportResult(0, true);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    private function importCsvFile(string $type, string $path, EtlMonitorService $monitor, \App\Models\EtlJob $etlJob): int
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Impossible d'ouvrir : {$path}");
        }

        $header = fgetcsv($handle);
        if (! is_array($header)) {
            fclose($handle);

            return 0;
        }

        $chunk = $this->maxBatchSize($type);
        $total = 0;
        $errors = 0;
        $fileTotal = $this->countCsvLines($path) - 1;
        $this->import->update(['total_rows' => max(0, $fileTotal)]);

        try {
            $batch = [];
            while (($line = fgetcsv($handle)) !== false) {
                $prepared = $this->prepareLine($type, $header, $line);
                if ($prepared === null) {
                    $errors++;

                    continue;
                }
                $batch[] = $prepared;
                if (count($batch) >= $chunk) {
                    $this->insertBatch($type, $batch);
                    $total += count($batch);
                    $batch = [];
                    $this->updateProgress($total, $errors);
                    $pct = $fileTotal > 0 ? (int) round(($total / $fileTotal) * 100) : 0;
                    $monitor->updateProgress($etlJob, $pct, ['rows_inserted' => $total, 'rows_processed' => $total + $errors]);
                }
            }
            if ($batch !== []) {
                $this->insertBatch($type, $batch);
                $total += count($batch);
                $this->updateProgress($total, $errors);
                $pct = $fileTotal > 0 ? (int) round(($total / $fileTotal) * 100) : 100;
                $monitor->updateProgress($etlJob, $pct, ['rows_inserted' => $total, 'rows_processed' => $total + $errors]);
            }
        } finally {
            fclose($handle);
        }

        return $total;
    }

    private function importExcelFile(string $type, string $path, EtlMonitorService $monitor, \App\Models\EtlJob $etlJob): int
    {
        $sheets = Excel::toArray([], $path);
        if (empty($sheets) || empty($sheets[0]) || ! is_array($sheets[0][0] ?? null)) {
            return 0;
        }

        $rows = $sheets[0];
        $header = $rows[0];
        $chunk = $this->maxBatchSize($type);
        $total = 0;
        $errors = 0;
        $fileTotal = count($rows) - 1;
        $this->import->update(['total_rows' => max(0, $fileTotal)]);

        $batch = [];
        for ($i = 1; $i < count($rows); $i++) {
            $line = $rows[$i];
            if (! is_array($line)) {
                continue;
            }
            $prepared = $this->prepareLine($type, $header, $line);
            if ($prepared === null) {
                $errors++;

                continue;
            }
            $batch[] = $prepared;
            if (count($batch) >= $chunk) {
                $this->insertBatch($type, $batch);
                $total += count($batch);
                $batch = [];
                $this->updateProgress($total, $errors);
                $pct = $fileTotal > 0 ? (int) round(($total / $fileTotal) * 100) : 0;
                $monitor->updateProgress($etlJob, $pct, ['rows_inserted' => $total, 'rows_processed' => $total + $errors]);
            }
        }
        if ($batch !== []) {
            $this->insertBatch($type, $batch);
            $total += count($batch);
            $this->updateProgress($total, $errors);
            $pct = $fileTotal > 0 ? (int) round(($total / $fileTotal) * 100) : 100;
            $monitor->updateProgress($etlJob, $pct, ['rows_inserted' => $total, 'rows_processed' => $total + $errors]);
        }

        return $total;
    }

    private function prepareLine(string $type, array $header, array $line): ?array
    {
        $mapped = $this->mapRow($header, $line);

        return $type === 'occ' ? $this->prepareOcc($mapped) : $this->prepareMmg($mapped);
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
            'created_at' => now(),
            'updated_at' => now(),
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

    private function updateProgress(int $imported, int $errors): void
    {
        $this->import->update([
            'imported_rows' => $imported,
            'error_rows' => $errors,
        ]);
    }

    private function failImport(string $message): void
    {
        $this->import->update([
            'status' => 'error',
            'error_message' => $message,
            'finished_at' => now(),
        ]);
    }

    private function notifyImportResult(int $count, bool $isError): void
    {
        $importedBy = (string) ($this->import->imported_by ?? '');
        if ($importedBy === '') {
            return;
        }

        $user = DB::table('ra_t_users')->where('email', $importedBy)->first();
        if (! $user?->id) {
            return;
        }

        /** @var NotificationService $notificationService */
        $notificationService = app(NotificationService::class);
        if ($isError) {
            $notificationService->notifyUser((int) $user->id, [
                'type' => 'import',
                'titre' => '✗ Import en echec',
                'message' => "Le fichier {$this->import->filename} contient des erreurs",
                'priorite' => 'haute',
                'icon' => 'import',
                'action_url' => '/imports',
                'data' => ['import_id' => $this->import->id],
            ]);

            return;
        }

        $notificationService->notifyImportDone((int) $user->id, $count);
    }

    private function maxBatchSize(string $type): int
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return 2500;
        }
        $cols = $type === 'occ' ? 14 : 12;

        return max(1, (int) floor(900 / $cols));
    }

    private function countCsvLines(string $path): int
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return 0;
        }
        $lines = 0;
        while (! feof($handle)) {
            $lines += substr_count(fread($handle, 8192), "\n");
        }
        fclose($handle);

        return $lines;
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

        return substr($ymd, 0, 4).'-'.substr($ymd, 4, 2).'-'.substr($ymd, 6, 2);
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
        if ($clean === null || ! is_numeric($clean)) {
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
