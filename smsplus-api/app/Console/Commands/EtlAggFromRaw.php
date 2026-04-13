<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EtlAggFromRaw extends Command
{
    protected $signature = 'etl:agg-from-raw
        {source=all : occ|mmg|all}
        {--truncate : Truncate normalized agg tables before insert}
        {--dry-run : Do not insert, only count candidates}';

    protected $description = 'ETL ra_t_occ_agg_raw / ra_t_mmg_agg_raw -> normalized agg tables (date + numeric conversion)';

    public function handle(): int
    {
        $source = strtolower((string) $this->argument('source'));
        if (! in_array($source, ['occ', 'mmg', 'all'], true)) {
            $this->error('source invalide. Valeurs: occ|mmg|all');
            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        if ($source === 'occ' || $source === 'all') {
            $this->etlOcc($dry);
        }
        if ($source === 'mmg' || $source === 'all') {
            $this->etlMmg($dry);
        }

        return self::SUCCESS;
    }

    private function etlOcc(bool $dry): void
    {
        if (! Schema::hasTable('ra_t_occ_agg_raw') || ! Schema::hasTable('ra_t_occ_agg')) {
            $this->warn('Tables OCC AGG manquantes. Lance les migrations.');
            return;
        }

        if ($this->option('truncate') && ! $dry) {
            DB::table('ra_t_occ_agg')->truncate();
        }

        $count = (int) DB::table('ra_t_occ_agg_raw')->count();
        $this->info("OCC RAW rows: {$count}");
        if ($dry) {
            return;
        }

        // Convert START_DATE (DD/MM/YY) and decimals with comma.
        DB::statement("
INSERT INTO ra_t_occ_agg (b_msisdn, start_date, start_hour, call_type, event_type, subscriber_type, keyword, cdr_count, charge_amount, created_at, updated_at)
SELECT
  NULLIF(TRIM(b_msisdn), '') AS b_msisdn,
  to_date(NULLIF(TRIM(start_date_raw), ''), 'DD/MM/YY') AS start_date,
  start_hour,
  NULLIF(TRIM(call_type), '') AS call_type,
  NULLIF(TRIM(event_type), '') AS event_type,
  NULLIF(TRIM(subscriber_type), '') AS subscriber_type,
  NULLIF(TRIM(keyword), '') AS keyword,
  COALESCE(NULLIF(TRIM(cdr_count_raw), ''), '0')::bigint AS cdr_count,
  COALESCE(NULLIF(REPLACE(TRIM(charge_amount_raw), ',', '.'), ''), '0')::numeric(14,3) AS charge_amount,
  NOW() AS created_at,
  NOW() AS updated_at
FROM ra_t_occ_agg_raw
WHERE NULLIF(TRIM(start_date_raw), '') IS NOT NULL
");

        $this->info('OCC AGG normalized: OK');
    }

    private function etlMmg(bool $dry): void
    {
        if (! Schema::hasTable('ra_t_mmg_agg_raw') || ! Schema::hasTable('ra_t_mmg_agg')) {
            $this->warn('Tables MMG AGG manquantes. Lance les migrations.');
            return;
        }

        if ($this->option('truncate') && ! $dry) {
            DB::table('ra_t_mmg_agg')->truncate();
        }

        $count = (int) DB::table('ra_t_mmg_agg_raw')->count();
        $this->info("MMG RAW rows: {$count}");
        if ($dry) {
            return;
        }

        DB::statement("
INSERT INTO ra_t_mmg_agg (b_msisdn, start_date, start_hour, event_type, call_type, event_status, subscriber_type, service_type, cdr_count, created_at, updated_at)
SELECT
  NULLIF(TRIM(b_msisdn), '') AS b_msisdn,
  to_date(NULLIF(TRIM(start_date_raw), ''), 'DD/MM/YY') AS start_date,
  start_hour,
  NULLIF(TRIM(event_type), '') AS event_type,
  NULLIF(TRIM(call_type), '') AS call_type,
  NULLIF(TRIM(event_status), '') AS event_status,
  NULLIF(TRIM(subscriber_type), '') AS subscriber_type,
  NULLIF(TRIM(service_type), '') AS service_type,
  COALESCE(NULLIF(TRIM(cdr_count_raw), ''), '0')::bigint AS cdr_count,
  NOW() AS created_at,
  NOW() AS updated_at
FROM ra_t_mmg_agg_raw
WHERE NULLIF(TRIM(start_date_raw), '') IS NOT NULL
");

        $this->info('MMG AGG normalized: OK');
    }
}

