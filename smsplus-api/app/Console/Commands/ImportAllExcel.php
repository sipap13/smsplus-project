<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportAllExcel extends Command
{
    protected $signature = 'import:all {--truncate : Vider tables OCC/MMG avant import}';
    protected $description = 'Importer tous les fichiers OCC/MMG depuis storage/imports (CSV+XLSX)';

    public function handle()
    {
        $this->optimizeSqliteForBulkImport();

        $occPath = storage_path('imports/occ');
        $mmgPath = storage_path('imports/mmg');

        if ($this->option('truncate')) {
            $this->warn('Truncate tables OCC/MMG...');
            DB::table('ra_t_occ_cdr_detail')->truncate();
            DB::table('ra_t_mmg_cdr_det')->truncate();
            DB::table('ra_t_cdr_agg')->truncate();
        }

        $occFiles = array_merge(
            glob($occPath . DIRECTORY_SEPARATOR . '*.csv') ?: [],
            glob($occPath . DIRECTORY_SEPARATOR . '*.xlsx') ?: [],
            glob($occPath . DIRECTORY_SEPARATOR . '*.xls') ?: []
        );

        $mmgFiles = array_merge(
            glob($mmgPath . DIRECTORY_SEPARATOR . '*.csv') ?: [],
            glob($mmgPath . DIRECTORY_SEPARATOR . '*.xlsx') ?: [],
            glob($mmgPath . DIRECTORY_SEPARATOR . '*.xls') ?: []
        );

        $this->info('OCC files: ' . count($occFiles));
        foreach ($occFiles as $file) {
            $this->call('import:excel', ['type' => 'occ', 'path' => $file]);
        }

        $this->info('MMG files: ' . count($mmgFiles));
        foreach ($mmgFiles as $file) {
            $this->call('import:excel', ['type' => 'mmg', 'path' => $file]);
        }

        // Rebuild lightweight MMG aggregation for dashboard usage.
        DB::table('ra_t_cdr_agg')->insertUsing(
            ['b_msisdn', 'start_date', 'start_hour', 'event_type', 'call_type', 'event_status', 'subscriber_type', 'service_type', 'cdr_count', 'created_at', 'updated_at'],
            DB::table('ra_t_mmg_cdr_det')
                ->selectRaw('b_msisdn, start_date, start_hour, event_type, call_type, event_status, subscriber_type, service_type, COUNT(*) as cdr_count, CURRENT_TIMESTAMP as created_at, CURRENT_TIMESTAMP as updated_at')
                ->groupBy('b_msisdn', 'start_date', 'start_hour', 'event_type', 'call_type', 'event_status', 'subscriber_type', 'service_type')
        );

        $this->info('Import complet termine.');
        return self::SUCCESS;
    }

    /**
     * Accélère les gros imports SQLite (WAL, cache, moins de fsync par défaut).
     */
    private function optimizeSqliteForBulkImport(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        try {
            DB::statement('PRAGMA journal_mode = WAL');
            DB::statement('PRAGMA synchronous = NORMAL');
            DB::statement('PRAGMA cache_size = -200000');
            DB::statement('PRAGMA temp_store = MEMORY');
            DB::statement('PRAGMA mmap_size = 268435456');
        } catch (\Throwable) {
            // ignore
        }
    }
}