<?php

namespace App\Console\Commands;

use App\Services\EtlMonitorService;
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

        /** @var EtlMonitorService $monitor */
        $monitor = app(EtlMonitorService::class);
        $job = $monitor->startJob('etl_cdr_from_tmp', 'command', ['source' => $source, 'chunk' => $chunk, 'dry_run' => $dryRun]);

        try {
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

            $totalProcessed = $totals['mmg']['processed'] + $totals['occ']['processed'];
            $totalInserted = $totals['mmg']['inserted'] + $totals['occ']['inserted'];
            $totalSkipped = $totals['mmg']['skipped'] + $totals['occ']['skipped'];

            $monitor->finishJob($job, 'success', null, [
                'processed_rows' => $totalInserted,
                'total_rows' => $totalProcessed,
                'error_rows' => $totalSkipped,
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $monitor->failJob($job, $e->getMessage());
            $this->error('ETL cdr-from-tmp échoué : ' . $e->getMessage());
            throw $e;
        }
    }

    private function etlMmg(int $chunk, bool $dryRun, array &$stats): void
    {
        if (! $this->tableExists('ra_t_tmp_mmg')) {
            $this->warn('Table ra_t_tmp_mmg introuvable.');
            return;
        }

        $count = (int) DB::table('ra_t_tmp_mmg')->where('event_status', '=', 'Success')->count();
        $stats['processed'] = $count;
        $this->info("MMG candidates: {$count}");

        if ($dryRun || $count === 0) {
            $stats['inserted'] = $count;
            return;
        }

        DB::statement("
            INSERT INTO ra_t_mmg_cdr_det (ne, a_msisdn, b_msisdn, start_date, start_hour, event_type, event_type_orig, call_type, event_status, subscriber_type, service_type, orig_start_time, created_at, updated_at)
            SELECT
                NULLIF(TRIM(ne), '') as ne,
                NULLIF(TRIM(a_msisdn), '') as a_msisdn,
                NULLIF(TRIM(b_msisdn), '') as b_msisdn,
                to_date(substring(regexp_replace(proc_date, '\D', '', 'g') from 1 for 8), 'YYYYMMDD') as start_date,
                CASE WHEN proc_hour ~ '^[0-9]+$' AND proc_hour::int BETWEEN 0 AND 23 THEN proc_hour::int ELSE NULL END as start_hour,
                NULLIF(TRIM(event_type), '') as event_type,
                NULLIF(TRIM(event_type_orig), '') as event_type_orig,
                NULLIF(TRIM(call_type), '') as call_type,
                NULLIF(TRIM(event_status), '') as event_status,
                NULLIF(TRIM(subscriber_type), '') as subscriber_type,
                NULLIF(TRIM(service_type), '') as service_type,
                NULLIF(TRIM(orig_start_time), '') as orig_start_time,
                NOW(), NOW()
            FROM ra_t_tmp_mmg
            WHERE event_status = 'Success'
              AND NULLIF(TRIM(a_msisdn), '') IS NOT NULL
              AND to_date(substring(regexp_replace(proc_date, '\D', '', 'g') from 1 for 8), 'YYYYMMDD') IS NOT NULL
        ");

        $this->info('Optimisation des statistiques MMG...');
        DB::statement("VACUUM ANALYZE ra_t_mmg_cdr_det");

        $stats['inserted'] = $count;
        $this->info('MMG ETL: OK');
    }

    private function etlOcc(int $chunk, bool $dryRun, array &$stats): void
    {
        if (! $this->tableExists('ra_t_tmp_occ')) {
            $this->warn('Table ra_t_tmp_occ introuvable.');
            return;
        }

        $count = (int) DB::table('ra_t_tmp_occ')->where('filter_code', '=', 0)->count();
        $stats['processed'] = $count;
        $this->info("OCC candidates: {$count}");

        if ($dryRun || $count === 0) {
            $stats['inserted'] = $count;
            return;
        }

        DB::statement("
            INSERT INTO ra_t_occ_cdr_detail (datasource, a_msisdn, b_msisdn, start_date, start_hour, call_type, event_type, subscriber_type, roaming_type, partner, charge_amount, keyword, orig_start_time, created_at, updated_at)
            SELECT
                NULLIF(TRIM(ne), 'OCC') as datasource,
                NULLIF(TRIM(a_msisdn), '') as a_msisdn,
                NULLIF(TRIM(b_msisdn), '') as b_msisdn,
                to_date(substring(regexp_replace(proc_date, '\D', '', 'g') from 1 for 8), 'YYYYMMDD') as start_date,
                CASE WHEN proc_hour ~ '^[0-9]+$' AND proc_hour::int BETWEEN 0 AND 23 THEN proc_hour::int ELSE NULL END as start_hour,
                NULLIF(TRIM(call_type), '') as call_type,
                NULLIF(TRIM(event_type), '') as event_type,
                NULLIF(TRIM(subscriber_type), '') as subscriber_type,
                NULL,
                NULLIF(TRIM(partner), '') as partner,
                COALESCE(NULLIF(regexp_replace(REPLACE(charge_amount_orig, ',', '.'), '\s', '', 'g'), ''), '0')::numeric(14,3) as charge_amount,
                NULLIF(TRIM(b_msisdn), '') as keyword,
                NULLIF(TRIM(orig_start_time), '') as orig_start_time,
                NOW(), NOW()
            FROM ra_t_tmp_occ
            WHERE filter_code = 0
              AND NULLIF(TRIM(a_msisdn), '') IS NOT NULL
              AND to_date(substring(regexp_replace(proc_date, '\D', '', 'g') from 1 for 8), 'YYYYMMDD') IS NOT NULL
        ");

        $this->info('Optimisation des statistiques OCC...');
        DB::statement("VACUUM ANALYZE ra_t_occ_cdr_detail");

        $stats['inserted'] = $count;
        $this->info('OCC ETL: OK');
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
            try {
                DB::table($table)->limit(1)->get();
                return true;
            } catch (\Throwable) {
                return false;
            }
        }
    }
}
