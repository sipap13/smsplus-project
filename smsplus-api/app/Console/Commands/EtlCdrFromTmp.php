<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EtlCdrFromTmp extends Command
{
    protected $signature = 'etl:cdr-from-tmp
        {source=all : mmg|occ|all}
        {--chunk=2000 : Chunk size}
        {--truncate : Truncate target tables before insert}
        {--dry-run : Do not insert, only count}';

    protected $description = 'ETL RA_T_TMP_MMG/RA_T_TMP_OCC -> ra_t_mmg_cdr_det / ra_t_occ_cdr_detail (mapping + filtres)';

    public function handle(): int
    {
        $source = strtolower((string) $this->argument('source'));
        if (! in_array($source, ['mmg', 'occ', 'all'], true)) {
            $this->error('source invalide. Valeurs: mmg|occ|all');
            return self::FAILURE;
        }

        $chunk = max(100, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        if ($this->option('truncate') && ! $dryRun) {
            if ($source === 'mmg' || $source === 'all') {
                DB::table('ra_t_mmg_cdr_det')->truncate();
            }
            if ($source === 'occ' || $source === 'all') {
                DB::table('ra_t_occ_cdr_detail')->truncate();
            }
        }

        $totals = [
            'mmg' => ['processed' => 0, 'inserted' => 0, 'skipped' => 0],
            'occ' => ['processed' => 0, 'inserted' => 0, 'skipped' => 0],
        ];

        if ($source === 'mmg' || $source === 'all') {
            $this->info('ETL MMG...');
            $this->etlMmg($chunk, $dryRun, $totals['mmg']);
        }

        if ($source === 'occ' || $source === 'all') {
            $this->info('ETL OCC...');
            $this->etlOcc($chunk, $dryRun, $totals['occ']);
        }

        $this->newLine();
        $this->line('Résumé:');
        foreach (['mmg', 'occ'] as $k) {
            if ($source !== 'all' && $source !== $k) {
                continue;
            }
            $this->line(strtoupper($k) . " processed={$totals[$k]['processed']} inserted={$totals[$k]['inserted']} skipped={$totals[$k]['skipped']}");
        }

        return self::SUCCESS;
    }

    private function etlMmg(int $chunk, bool $dryRun, array &$stats): void
    {
        if (! $this->tableExists('ra_t_tmp_mmg')) {
            $this->warn('Table ra_t_tmp_mmg introuvable. Lance les migrations puis charge les données brutes.');
            return;
        }

        $q = DB::table('ra_t_tmp_mmg')
            ->where('event_status', '=', 'Success');

        $q->orderBy('id')->chunkById($chunk, function ($rows) use ($dryRun, &$stats) {
            $stats['processed'] += count($rows);
            $batch = [];
            foreach ($rows as $r) {
                $mapped = $this->mapMmgRow($r);
                if ($mapped === null) {
                    $stats['skipped']++;
                    continue;
                }
                $batch[] = $mapped;
            }

            if ($batch === []) {
                return;
            }

            if ($dryRun) {
                $stats['inserted'] += count($batch);
                return;
            }

            DB::table('ra_t_mmg_cdr_det')->insert($batch);
            $stats['inserted'] += count($batch);
        });
    }

    private function etlOcc(int $chunk, bool $dryRun, array &$stats): void
    {
        if (! $this->tableExists('ra_t_tmp_occ')) {
            $this->warn('Table ra_t_tmp_occ introuvable. Lance les migrations puis charge les données brutes.');
            return;
        }

        $q = DB::table('ra_t_tmp_occ')
            ->where('filter_code', '=', 0);

        $q->orderBy('id')->chunkById($chunk, function ($rows) use ($dryRun, &$stats) {
            $stats['processed'] += count($rows);
            $batch = [];
            foreach ($rows as $r) {
                $mapped = $this->mapOccRow($r);
                if ($mapped === null) {
                    $stats['skipped']++;
                    continue;
                }
                $batch[] = $mapped;
            }

            if ($batch === []) {
                return;
            }

            if ($dryRun) {
                $stats['inserted'] += count($batch);
                return;
            }

            DB::table('ra_t_occ_cdr_detail')->insert($batch);
            $stats['inserted'] += count($batch);
        });
    }

    private function mapMmgRow(object $r): ?array
    {
        $a = $this->nullify($r->a_msisdn ?? null);
        $procDate = $this->nullify($r->proc_date ?? null);
        $procHour = $this->toIntOrNull($r->proc_hour ?? null);
        $startDate = $this->procDateToDate($procDate);

        if ($a === null || $startDate === null) {
            return null;
        }

        return [
            'ne' => $this->nullify($r->ne ?? null),
            'a_msisdn' => $a,
            'b_msisdn' => $this->nullify($r->b_msisdn ?? null),
            'start_date' => $startDate,
            'start_hour' => $procHour,
            'event_type' => $this->nullify($r->event_type ?? null),
            'event_type_orig' => $this->nullify($r->event_type_orig ?? null),
            'call_type' => $this->nullify($r->call_type ?? null),
            'event_status' => $this->nullify($r->event_status ?? null),
            'subscriber_type' => $this->nullify($r->subscriber_type ?? null),
            'service_type' => $this->nullify($r->service_type ?? null),
            'orig_start_time' => $this->nullify($r->orig_start_time ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function mapOccRow(object $r): ?array
    {
        $a = $this->nullify($r->a_msisdn ?? null);
        $procDate = $this->nullify($r->proc_date ?? null);
        $procHour = $this->toIntOrNull($r->proc_hour ?? null);
        $startDate = $this->procDateToDate($procDate);

        if ($a === null || $startDate === null) {
            return null;
        }

        return [
            'datasource' => $this->nullify($r->ne ?? null),
            'a_msisdn' => $a,
            'b_msisdn' => $this->nullify($r->b_msisdn ?? null),
            'start_date' => $startDate,
            'start_hour' => $procHour,
            'call_type' => $this->nullify($r->call_type ?? null),
            'event_type' => $this->nullify($r->event_type ?? null),
            'subscriber_type' => $this->nullify($r->subscriber_type ?? null),
            'roaming_type' => null,
            'partner' => $this->nullify($r->partner ?? null),
            'charge_amount' => $this->toDecimal($r->charge_amount_orig ?? null),
            'keyword' => null,
            'orig_start_time' => $this->nullify($r->orig_start_time ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function procDateToDate(?string $procDate): ?string
    {
        if ($procDate === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $procDate);
        if (! is_string($digits) || strlen($digits) < 8) {
            return null;
        }

        $ymd = substr($digits, 0, 8);
        try {
            return Carbon::createFromFormat('Ymd', $ymd)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function nullify(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);
        if ($s === '' || $s === '_N' || $s === '_UN') {
            return null;
        }
        return $s;
    }

    private function toIntOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $i = (int) $v;
        return ($i >= 0 && $i <= 23) ? $i : null;
    }

    private function toDecimal(mixed $v): float
    {
        if ($v === null) {
            return 0.0;
        }
        $s = trim((string) $v);
        if ($s === '' || $s === '_N' || $s === '_UN') {
            return 0.0;
        }
        $s = str_replace([' ', ','], ['', '.'], $s);
        return is_numeric($s) ? (float) $s : 0.0;
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            // fallback without Schema (if not imported)
            try {
                DB::table($table)->limit(1)->get();
                return true;
            } catch (\Throwable) {
                return false;
            }
        }
    }
}

