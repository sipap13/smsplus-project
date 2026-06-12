<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Seeder de simulation d'importation de données pour alimenter :
 *   - Data Lineage  (GET /etl/lineage-stats, /etl/stats)
 *   - ETL Performance (GET /etl/performance, /etl/jobs)
 *
 * Génère ~200 jobs ETL réalistes couvrant les 30 derniers jours.
 */
class RaTEtlJobsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚀 Seeding ETL jobs for Data Lineage & Performance...');

        $now = Carbon::now();
        $jobs = [];

        // ─── 1) IMPORT JOBS (source CSV / XLSX) ──────────────────────────
        // Simulate daily OCC and MMG imports over the last 30 days
        for ($day = 0; $day < 30; $day++) {
            $baseDate = $now->copy()->subDays($day);

            // ── Import OCC CSV (daily, ~08:00)
            $started = $baseDate->copy()->setTime(8, random_int(0, 15), random_int(0, 59));
            $rowsProcessed = random_int(800, 5000);
            $rowsSkipped = random_int(0, (int)($rowsProcessed * 0.03));
            $durationMs = random_int(2000, 12000);
            $jobs[] = $this->makeJob('import_occ_csv', 'import', 'import', $started, $durationMs, $rowsProcessed, $rowsSkipped, [
                'file_name' => 'occ_cdr_' . $baseDate->format('Ymd') . '.csv',
                'file_size_kb' => random_int(200, 2500),
                'total_rows' => $rowsProcessed + $rowsSkipped,
                'processed_rows' => $rowsProcessed,
                'duplicates_found' => $rowsSkipped,
                'source_table' => 'ra_t_tmp_occ',
                'encoding' => 'UTF-8',
            ], 'Importation CDR', 'system_cron');

            // ── Import MMG CSV (daily, ~08:30)
            $started = $baseDate->copy()->setTime(8, random_int(25, 45), random_int(0, 59));
            $rowsProcessed = random_int(500, 3000);
            $rowsSkipped = random_int(0, (int)($rowsProcessed * 0.02));
            $durationMs = random_int(1500, 8000);
            $jobs[] = $this->makeJob('import_mmg_csv', 'import', 'import', $started, $durationMs, $rowsProcessed, $rowsSkipped, [
                'file_name' => 'mmg_cdr_' . $baseDate->format('Ymd') . '.csv',
                'file_size_kb' => random_int(150, 1800),
                'total_rows' => $rowsProcessed + $rowsSkipped,
                'processed_rows' => $rowsProcessed,
                'duplicates_found' => $rowsSkipped,
                'source_table' => 'ra_t_tmp_mmg',
                'encoding' => 'UTF-8',
            ], 'Importation CDR', 'system_cron');

            // ── Import OCC XLSX (2-3 times per week)
            if ($day % 3 === 0) {
                $started = $baseDate->copy()->setTime(9, random_int(0, 30), random_int(0, 59));
                $rowsProcessed = random_int(1200, 8000);
                $rowsSkipped = random_int(0, (int)($rowsProcessed * 0.05));
                $durationMs = random_int(5000, 25000);
                $jobs[] = $this->makeJob('import_occ_xlsx', 'import', 'import', $started, $durationMs, $rowsProcessed, $rowsSkipped, [
                    'file_name' => 'OCC_Detail_Report_' . $baseDate->format('Ymd') . '.xlsx',
                    'file_size_kb' => random_int(500, 4000),
                    'total_rows' => $rowsProcessed + $rowsSkipped,
                    'processed_rows' => $rowsProcessed,
                    'sheets_processed' => random_int(1, 3),
                    'source_table' => 'ra_t_tmp_occ',
                ], 'Importation CDR', 'admin@tt.tn');
            }

            // ── Import MMG XLSX (weekly)
            if ($day % 7 === 0) {
                $started = $baseDate->copy()->setTime(9, random_int(30, 59), random_int(0, 59));
                $rowsProcessed = random_int(2000, 12000);
                $rowsSkipped = random_int(5, (int)($rowsProcessed * 0.04));
                $durationMs = random_int(8000, 40000);
                $jobs[] = $this->makeJob('import_mmg_xlsx', 'import', 'import', $started, $durationMs, $rowsProcessed, $rowsSkipped, [
                    'file_name' => 'MMG_Weekly_Report_S' . (int)($day / 7 + 1) . '.xlsx',
                    'file_size_kb' => random_int(800, 6000),
                    'total_rows' => $rowsProcessed + $rowsSkipped,
                    'processed_rows' => $rowsProcessed,
                    'sheets_processed' => random_int(1, 4),
                    'source_table' => 'ra_t_tmp_mmg',
                ], 'Importation CDR', 'admin@tt.tn');
            }
        }

        // ─── 2) CDR PROCESSING (tmp → detail) ────────────────────────────
        for ($day = 0; $day < 30; $day++) {
            $baseDate = $now->copy()->subDays($day);

            $started = $baseDate->copy()->setTime(10, random_int(0, 20), random_int(0, 59));
            $rowsProcessed = random_int(1000, 6000);
            $durationMs = random_int(3000, 18000);
            $jobs[] = $this->makeJob('etl_cdr_from_tmp', 'transform', 'command', $started, $durationMs, $rowsProcessed, 0, [
                'total_rows' => $rowsProcessed,
                'processed_rows' => $rowsProcessed,
                'source_tables' => ['ra_t_tmp_occ', 'ra_t_tmp_mmg'],
                'target_tables' => ['ra_t_occ_cdr_detail', 'ra_t_mmg_cdr_det'],
                'services_enriched' => random_int(6, 12),
            ], 'Traitement CDR', 'system_cron');
        }

        // ─── 3) AGGREGATION JOBS ──────────────────────────────────────────
        for ($day = 0; $day < 30; $day++) {
            $baseDate = $now->copy()->subDays($day);

            $started = $baseDate->copy()->setTime(11, random_int(0, 30), random_int(0, 59));
            $rowsProcessed = random_int(50, 400);
            $durationMs = random_int(1000, 8000);
            $jobs[] = $this->makeJob('etl_agg_from_raw', 'aggregate', 'command', $started, $durationMs, $rowsProcessed, 0, [
                'total_rows' => $rowsProcessed,
                'processed_rows' => $rowsProcessed,
                'source_tables' => ['ra_t_occ_cdr_detail', 'ra_t_mmg_cdr_det'],
                'target_tables' => ['ra_t_occ_agg', 'ra_t_mmg_agg'],
                'aggregation_level' => 'hourly',
            ], 'Agrégation', 'system_cron');
        }

        // ─── 4) DASHBOARD / LOAD JOBS ─────────────────────────────────────
        for ($day = 0; $day < 15; $day++) {
            $baseDate = $now->copy()->subDays($day);

            // Multiple dashboard loads per day (simulates user visits)
            $nbLoads = random_int(3, 8);
            for ($load = 0; $load < $nbLoads; $load++) {
                $started = $baseDate->copy()->setTime(random_int(8, 22), random_int(0, 59), random_int(0, 59));
                $durationMs = random_int(150, 2500);
                $jobs[] = $this->makeJob('dashboard_stats_load', 'query', 'command', $started, $durationMs, random_int(5, 20), 0, [
                    'processed_rows' => random_int(5, 20),
                    'cache_hit' => (bool)random_int(0, 1),
                ], 'Dashboard', 'user');
            }

            // Revenue chart loads
            $nbRevLoads = random_int(2, 5);
            for ($load = 0; $load < $nbRevLoads; $load++) {
                $started = $baseDate->copy()->setTime(random_int(9, 20), random_int(0, 59), random_int(0, 59));
                $durationMs = random_int(200, 3000);
                $jobs[] = $this->makeJob('dashboard_revenus_chart', 'query', 'command', $started, $durationMs, random_int(30, 180), 0, [
                    'processed_rows' => random_int(30, 180),
                    'date_range_days' => random_int(7, 30),
                ], 'Dashboard', 'user');
            }
        }

        // ─── 5) EXPORT JOBS ───────────────────────────────────────────────
        for ($day = 0; $day < 30; $day += random_int(2, 5)) {
            $baseDate = $now->copy()->subDays($day);

            // OCC Excel export
            $started = $baseDate->copy()->setTime(random_int(10, 17), random_int(0, 59), random_int(0, 59));
            $rowsProcessed = random_int(500, 15000);
            $durationMs = random_int(2000, 20000);
            $jobs[] = $this->makeJob('export_occ_excel', 'export', 'export', $started, $durationMs, $rowsProcessed, 0, [
                'processed_rows' => $rowsProcessed,
                'file_name' => 'export_occ_' . $baseDate->format('Ymd') . '.xlsx',
                'file_size_kb' => (int)($rowsProcessed * 0.3),
            ], 'Export', 'admin@tt.tn');

            // MMG Excel export (less frequent)
            if ($day % 4 === 0) {
                $started = $baseDate->copy()->setTime(random_int(14, 18), random_int(0, 59), random_int(0, 59));
                $rowsProcessed = random_int(300, 8000);
                $durationMs = random_int(1500, 15000);
                $jobs[] = $this->makeJob('export_mmg_excel', 'export', 'export', $started, $durationMs, $rowsProcessed, 0, [
                    'processed_rows' => $rowsProcessed,
                    'file_name' => 'export_mmg_' . $baseDate->format('Ymd') . '.xlsx',
                    'file_size_kb' => (int)($rowsProcessed * 0.25),
                ], 'Export', 'admin@tt.tn');
            }

            // Revenue CSV export
            if ($day % 3 === 0) {
                $started = $baseDate->copy()->setTime(random_int(15, 19), random_int(0, 59), random_int(0, 59));
                $rowsProcessed = random_int(1000, 20000);
                $durationMs = random_int(1000, 12000);
                $jobs[] = $this->makeJob('export_revenus_csv', 'export', 'export', $started, $durationMs, $rowsProcessed, 0, [
                    'processed_rows' => $rowsProcessed,
                    'file_name' => 'revenus_export_' . $baseDate->format('Ymd') . '.csv',
                ], 'Export', 'analyste@tt.tn');
            }
        }

        // ─── 6) NOTIFICATION POLLING ──────────────────────────────────────
        for ($day = 0; $day < 10; $day++) {
            $baseDate = $now->copy()->subDays($day);
            $nbPolls = random_int(5, 15);
            for ($poll = 0; $poll < $nbPolls; $poll++) {
                $started = $baseDate->copy()->setTime(random_int(8, 22), random_int(0, 59), random_int(0, 59));
                $durationMs = random_int(30, 400);
                $jobs[] = $this->makeJob('notifications_load', 'query', 'command', $started, $durationMs, random_int(0, 25), 0, [
                    'processed_rows' => random_int(0, 25),
                ], 'Notifications', 'user');
            }
        }

        // ─── 7) PREDICTION / AI JOBS ──────────────────────────────────────
        for ($day = 0; $day < 20; $day += random_int(1, 3)) {
            $baseDate = $now->copy()->subDays($day);

            // Data collection for prediction
            $started = $baseDate->copy()->setTime(random_int(10, 16), random_int(0, 59), random_int(0, 59));
            $durationMs = random_int(500, 3000);
            $jobs[] = $this->makeJob('prediction_data_collect', 'query', 'command', $started, $durationMs, random_int(30, 200), 0, [
                'processed_rows' => random_int(30, 200),
                'date_range_days' => 90,
            ], 'Prédictions IA', 'user');

            // Metrics calculation
            $started2 = $started->copy()->addSeconds(random_int(3, 10));
            $durationMs2 = random_int(200, 1500);
            $jobs[] = $this->makeJob('prediction_metrics_calc', 'transform', 'command', $started2, $durationMs2, random_int(5, 30), 0, [
                'processed_rows' => random_int(5, 30),
                'metrics_computed' => ['trend', 'seasonality', 'volatility'],
            ], 'Prédictions IA', 'user');

            // LLM call
            $started3 = $started2->copy()->addSeconds(random_int(2, 5));
            $durationMs3 = random_int(2000, 12000);
            $jobs[] = $this->makeJob('prediction_groq_call', 'query', 'command', $started3, $durationMs3, 1, 0, [
                'processed_rows' => 1,
                'model' => random_int(0, 1) ? 'llama-3.1-8b-instant' : 'mistral-small-latest',
                'tokens_used' => random_int(800, 4000),
                'response_time_ms' => $durationMs3,
            ], 'Prédictions IA', 'user');
        }

        // ─── 8) MSISDN SEARCH JOBS ───────────────────────────────────────
        for ($day = 0; $day < 15; $day++) {
            $baseDate = $now->copy()->subDays($day);
            $nbSearches = random_int(2, 6);
            for ($s = 0; $s < $nbSearches; $s++) {
                $started = $baseDate->copy()->setTime(random_int(9, 18), random_int(0, 59), random_int(0, 59));
                $durationMs = random_int(200, 4000);
                $occTotal = random_int(10, 500);
                $mmgTotal = random_int(5, 300);
                $jobs[] = $this->makeJob('msisdn_search_all', 'query', 'command', $started, $durationMs, $occTotal + $mmgTotal, 0, [
                    'occ_total' => $occTotal,
                    'mmg_total' => $mmgTotal,
                    'msisdn' => '2169' . str_pad((string)random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
                ], 'Recherche MSISDN', 'user');
            }
        }

        // ─── 9) CDR PAGINATION JOBS ───────────────────────────────────────
        for ($day = 0; $day < 15; $day++) {
            $baseDate = $now->copy()->subDays($day);
            $nbQueries = random_int(2, 8);
            for ($q = 0; $q < $nbQueries; $q++) {
                $started = $baseDate->copy()->setTime(random_int(8, 20), random_int(0, 59), random_int(0, 59));
                $durationMs = random_int(100, 3000);
                $rowsProcessed = random_int(20, 100);
                $type = random_int(0, 1) ? 'cdr_occ_paginate' : 'cdr_mmg_paginate';
                $jobs[] = $this->makeJob($type, 'query', 'command', $started, $durationMs, $rowsProcessed, 0, [
                    'processed_rows' => $rowsProcessed,
                    'page' => random_int(1, 20),
                    'per_page' => 20,
                ], 'Consultation CDR', 'user');
            }
        }

        // ─── 10) DEDUPLICATION JOBS ───────────────────────────────────────
        for ($day = 0; $day < 30; $day += random_int(3, 7)) {
            $baseDate = $now->copy()->subDays($day);
            $started = $baseDate->copy()->setTime(random_int(12, 15), random_int(0, 59), random_int(0, 59));
            $duplicatesFound = random_int(10, 300);
            $durationMs = random_int(1000, 10000);
            $jobs[] = $this->makeJob('etl_deduplicate', 'transform', 'command', $started, $durationMs, $duplicatesFound, 0, [
                'processed_rows' => $duplicatesFound,
                'duplicates_removed' => $duplicatesFound,
                'source' => random_int(0, 1) ? 'OCC' : 'MMG',
            ], 'Dédoublonnage', 'admin@tt.tn');
        }

        // ─── 11) SERVICES LIST LOAD ───────────────────────────────────────
        for ($day = 0; $day < 10; $day++) {
            $baseDate = $now->copy()->subDays($day);
            $nbLoads = random_int(1, 4);
            for ($l = 0; $l < $nbLoads; $l++) {
                $started = $baseDate->copy()->setTime(random_int(9, 19), random_int(0, 59), random_int(0, 59));
                $durationMs = random_int(50, 500);
                $jobs[] = $this->makeJob('services_list_load', 'query', 'command', $started, $durationMs, random_int(8, 25), 0, [
                    'processed_rows' => random_int(8, 25),
                ], 'Services', 'user');
            }
        }

        // ─── 12) INJECT SOME FAILURES ─────────────────────────────────────
        $failureScenarios = [
            ['import_occ_csv', 'Fichier CSV corrompu : encoding non reconnu (ISO-8859-15 inattendu)', 3],
            ['import_mmg_xlsx', 'PhpSpreadsheet: Unable to open file — format Excel 97 non supporté', 7],
            ['etl_agg_from_raw', 'SQLSTATE[23505]: unique_violation — clé dupliquée dans ra_t_occ_agg', 5],
            ['export_occ_excel', 'Memory limit exceeded (256MB) lors de la génération du fichier Excel', 12],
            ['prediction_groq_call', 'Groq API timeout: no response after 30s', 2],
            ['import_occ_csv', 'Le fichier CSV est vide (0 lignes après header)', 15],
            ['etl_cdr_from_tmp', 'Foreign key violation: keyword "xyz99" not found in ra_t_services', 8],
        ];

        foreach ($failureScenarios as [$jobName, $errorMsg, $daysAgo]) {
            $started = $now->copy()->subDays($daysAgo)->setTime(random_int(8, 18), random_int(0, 59), random_int(0, 59));
            $durationMs = random_int(500, 5000);
            $job = $this->makeJob($jobName, 'import', 'command', $started, $durationMs, 0, 0, [
                'error_detail' => $errorMsg,
            ], 'Importation CDR', 'system_cron');
            $job['status'] = 'failed';
            $job['error_message'] = $errorMsg;
            $jobs[] = $job;
        }

        // ─── 13) ADD A CURRENTLY RUNNING JOB ─────────────────────────────
        $jobs[] = [
            'job_name' => 'import_occ_csv',
            'job_type' => 'import',
            'status' => 'running',
            'started_at' => $now->copy()->subMinutes(2),
            'finished_at' => null,
            'duration_ms' => null,
            'rows_processed' => 1247,
            'rows_inserted' => 1247,
            'rows_skipped' => 3,
            'pourcentage' => 68,
            'error_message' => null,
            'metadata' => json_encode([
                'file_name' => 'occ_cdr_' . $now->format('Ymd') . '.csv',
                'total_rows' => 1834,
                'processed_rows' => 1247,
                'nb_lignes' => 1834,
            ]),
            'page' => 'Importation CDR',
            'created_at' => $now->copy()->subMinutes(2),
            'updated_at' => $now,
        ];

        // ─── INSERT ALL ───────────────────────────────────────────────────
        // Shuffle to avoid monotonic ordering
        shuffle($jobs);

        // Insert in batches of 50
        $chunks = array_chunk($jobs, 50);
        foreach ($chunks as $chunk) {
            DB::table('ra_t_etl_jobs')->insert($chunk);
        }

        $total = count($jobs);
        $successes = count(array_filter($jobs, fn($j) => $j['status'] === 'success'));
        $failures = count(array_filter($jobs, fn($j) => $j['status'] === 'failed'));
        $running = count(array_filter($jobs, fn($j) => $j['status'] === 'running'));

        $this->command->info("✅ {$total} ETL jobs created ({$successes} success, {$failures} failed, {$running} running)");
        $this->command->info('📊 Data Lineage & ETL Performance pages are now populated!');
    }

    /**
     * Build a single ETL job record with realistic data.
     */
    private function makeJob(
        string $jobName,
        string $category,
        string $jobType,
        Carbon $started,
        int $durationMs,
        int $rowsProcessed,
        int $rowsSkipped,
        array $metadata,
        string $page,
        string $triggeredBy,
    ): array {
        $finished = $started->copy()->addMilliseconds($durationMs);

        return [
            'job_name' => $jobName,
            'job_type' => $jobType,
            'status' => 'success',
            'started_at' => $started,
            'finished_at' => $finished,
            'duration_ms' => $durationMs,
            'rows_processed' => $rowsProcessed,
            'rows_inserted' => $rowsProcessed,
            'rows_skipped' => $rowsSkipped,
            'pourcentage' => 100,
            'error_message' => null,
            'metadata' => json_encode($metadata),
            'page' => $page,
            'created_at' => $started,
            'updated_at' => $finished,
        ];
    }
}
