<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class GenerateSyntheticCdrData extends Command
{
    protected $signature = 'app:generate-synthetic-cdr
        {--from=2025-06-06 : Start date for generated data (Y-m-d)}
        {--to=2026-05-05 : End date for generated data (Y-m-d)}
        {--occ-detail-per-day=900 : Average OCC detailed CDR rows per day}
        {--mmg-detail-per-day=1000 : Average MMG detailed CDR rows per day}
        {--seed=20260528 : Random seed for reproducibility}
        {--chunk=1000 : Insert batch size}
        {--with-agg : Also generate aggregate tables from the same service catalog}
        {--no-truncate : Keep existing data and append instead of replacing}';

    protected $description = 'Generate realistic synthetic OCC/MMG datasets (detail + aggregate) with the same structure as production tables';

    // Services are loaded from storage/imports/List VAS 1.xlsx. Do not embed default services.

    private array $hourWeights = [
        0 => 0.5, 1 => 0.4, 2 => 0.3, 3 => 0.2, 4 => 0.2, 5 => 0.3,
        6 => 0.6, 7 => 1.1, 8 => 1.7, 9 => 2.0, 10 => 2.3, 11 => 2.6,
        12 => 2.8, 13 => 2.5, 14 => 2.2, 15 => 2.0, 16 => 1.9, 17 => 2.1,
        18 => 2.5, 19 => 2.7, 20 => 2.4, 21 => 1.9, 22 => 1.3, 23 => 0.8,
    ];

    public function handle(): int
    {
        $startDate = $this->normalizeDateOption($this->option('from'));
        $endDate = $this->normalizeDateOption($this->option('to'));
        $occDetailPerDay = max(100, (int) $this->option('occ-detail-per-day'));
        $mmgDetailPerDay = max(100, (int) $this->option('mmg-detail-per-day'));
        $chunk = max(200, (int) $this->option('chunk'));
        $seed = (int) $this->option('seed');
        $truncate = ! (bool) $this->option('no-truncate');
        $withAgg = (bool) $this->option('with-agg');

        mt_srand($seed);
        DB::connection()->disableQueryLog();

        $this->info('Synthetic OCC/MMG generation started...');
        $this->line("Seed: {$seed}");
        $this->line("Range: {$startDate} -> {$endDate}");

        $services = $this->syncServiceCatalog();

        DB::beginTransaction();
        try {
            if ($truncate) {
                DB::table('ra_t_occ_cdr_detail')->truncate();
                DB::table('ra_t_mmg_cdr_det')->truncate();
                DB::table('ra_t_occ_agg')->truncate();
                DB::table('ra_t_mmg_agg')->truncate();

                if (DB::getSchemaBuilder()->hasTable('ra_t_cdr_agg')) {
                    DB::table('ra_t_cdr_agg')->truncate();
                }
            }

            $summary = [
                'occ_detail_rows' => $this->generateOccDetail($startDate, $endDate, $occDetailPerDay, $services, $chunk),
                'mmg_detail_rows' => $this->generateMmgDetail($startDate, $endDate, $mmgDetailPerDay, $services, $chunk),
            ];

            if ($withAgg) {
                $summary['occ_agg_rows'] = $this->generateOccAgg($startDate, $endDate, $services, $chunk);
                $summary['mmg_agg_rows'] = $this->generateMmgAgg($startDate, $endDate, $services, $chunk);

                if (DB::getSchemaBuilder()->hasTable('ra_t_cdr_agg')) {
                    $summary['cdr_agg_rows'] = $this->syncLegacyCdrAggFromMmgAgg($chunk);
                }
            }

            DB::commit();

            $this->newLine();
            $this->info('Synthetic dataset generated successfully.');
            $this->table(['Table', 'Rows'], [
                ['ra_t_occ_cdr_detail', (string) $summary['occ_detail_rows']],
                ['ra_t_mmg_cdr_det', (string) $summary['mmg_detail_rows']],
                ['ra_t_occ_agg', (string) ($summary['occ_agg_rows'] ?? 0)],
                ['ra_t_mmg_agg', (string) ($summary['mmg_agg_rows'] ?? 0)],
                ['ra_t_cdr_agg', (string) ($summary['cdr_agg_rows'] ?? 0)],
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Generation failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function syncServiceCatalog(): array
    {
        $nowTs = now()->toDateTimeString();

        $xlsxPath = storage_path('imports/List VAS 1.xlsx');
        $toSync = [];

        if (file_exists($xlsxPath)) {
            try {
                $reader = IOFactory::createReaderForFile($xlsxPath);
                $spreadsheet = $reader->load($xlsxPath);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, true, true, true);

                if (!empty($rows)) {
                    $first = array_shift($rows);
                    $headers = [];
                    foreach ($first as $col => $val) {
                        $headers[$col] = strtolower(trim((string) $val));
                    }

                    foreach ($rows as $r) {
                        $rowAssoc = [];
                        foreach ($headers as $col => $h) {
                            $rowAssoc[$h] = trim((string) ($r[$col] ?? ''));
                        }

                        $nom_fournisseur = $rowAssoc['fournisseur'] ?? $rowAssoc['nom_fournisseur'] ?? $rowAssoc['provider'] ?? '';
                        $nom_service = $rowAssoc['service'] ?? $rowAssoc['nom_service'] ?? $rowAssoc['nom'] ?? '';
                        $numero_court = $rowAssoc['numero'] ?? $rowAssoc['numero_court'] ?? $rowAssoc['numero court'] ?? $rowAssoc['short'] ?? '';
                        $keyword = $rowAssoc['keyword'] ?? $rowAssoc['mot_cle'] ?? $rowAssoc['mot cle'] ?? $rowAssoc['keyword'] ?? '';
                        $type_service = $rowAssoc['type'] ?? $rowAssoc['type_service'] ?? 'Service';
                        $prixRaw = $rowAssoc['prix'] ?? $rowAssoc['price'] ?? '0';
                        $prix = (float) str_replace(',', '.', preg_replace('/[^0-9,\.]/', '', $prixRaw));

                        if ($numero_court === '' && $keyword === '') {
                            continue;
                        }

                        $toSync[] = [
                            'nom_fournisseur' => $nom_fournisseur,
                            'nom_service' => $nom_service,
                            'numero_court' => $numero_court,
                            'keyword' => $keyword,
                            'type_service' => $type_service,
                            'prix' => $prix,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                throw new \RuntimeException("Unable to parse services Excel file at {$xlsxPath}: " . $e->getMessage());
            }
        } else {
            throw new \RuntimeException("Services Excel file not found at {$xlsxPath}. Please put List VAS 1.xlsx in storage/imports.");
        }

        // Keep existing services for selected providers and merge with Excel rows
        $keepProviders = ['Topnet', 'Orange Tunisie', 'Tunisie Telecom', 'Tunisie Telecome', 'Ooredoo'];
        $existing = DB::table('ra_t_services')
            ->whereIn('nom_fournisseur', $keepProviders)
            ->select('nom_fournisseur', 'nom_service', 'numero_court', 'keyword', 'type_service', 'prix')
            ->get()
            ->map(function ($s) {
                return [
                    'nom_fournisseur' => $s->nom_fournisseur,
                    'nom_service' => $s->nom_service,
                    'numero_court' => $s->numero_court,
                    'keyword' => $s->keyword,
                    'type_service' => $s->type_service,
                    'prix' => $s->prix,
                ];
            })
            ->all();

        // Merge existing services with Excel-derived ones, avoid duplicates by keyword or numero_court
        $merged = [];
        $seenKeys = [];

        foreach (array_merge($existing, $toSync) as $svc) {
            $key = trim((string) ($svc['keyword'] ?? '')) ?: null;
            $num = trim((string) ($svc['numero_court'] ?? '')) ?: null;
            $id = $key !== null && $key !== '' ? 'k:' . strtoupper($key) : ('n:' . $num);
            if ($id === 'n:' || $id === 'k:') {
                continue;
            }
            if (isset($seenKeys[$id])) {
                continue;
            }
            $seenKeys[$id] = true;
            $merged[] = $svc;
        }

        foreach ($merged as $service) {
            $match = [];
            if (!empty($service['keyword'])) {
                $match = ['keyword' => $service['keyword']];
            } else {
                $match = ['numero_court' => $service['numero_court']];
            }

            DB::table('ra_t_services')->updateOrInsert(
                $match,
                [
                    'nom_fournisseur' => $service['nom_fournisseur'] ?? '',
                    'nom_service' => $service['nom_service'] ?? '',
                    'numero_court' => $service['numero_court'] ?? '',
                    'keyword' => $service['keyword'] ?? '',
                    'type_service' => $service['type_service'] ?? 'Service',
                    'prix' => $service['prix'] ?? 0.0,
                    'actif' => true,
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ]
            );
        }

        // Return ALL active services so the generator creates traffic for all providers, including legacy ones
        return DB::table('ra_t_services')
            ->where('actif', true)
            ->orderBy('id')
            ->get()
            ->all();
    }

    private function normalizeDateOption($value): string
    {
        $date = date_create((string) $value);
        if (! $date) {
            throw new \InvalidArgumentException("Invalid date: {$value}");
        }

        return $date->format('Y-m-d');
    }

    private function generateOccDetail(string $startDate, string $endDate, int $avgPerDay, array $services, int $chunk): int
    {
        $inserted = 0;
        $batch = [];
        $cursor = $startDate;
        $nowTs = now()->toDateTimeString();

        while ($cursor <= $endDate) {
            $dailyTarget = (int) round($avgPerDay * $this->dayFactor($cursor) * $this->randFloat(0.92, 1.08));
            for ($i = 0; $i < $dailyTarget; $i++) {
                $service = $this->pickService($services, ['VAS' => 0.78, 'SMS' => 0.14, 'VOICE' => 0.08]);
                $callType = $this->weightedPick(['VAS' => 0.98, 'SMS' => 0.015, 'VOICE' => 0.005]);
                $keyword = $service->keyword;
                $basePrice = max(0.05, (float) ($service->prix ?? 0.5));
                $hour = $this->pickHour();
                $minute = mt_rand(0, 59);
                $second = mt_rand(0, 59);
                $amount = $this->occChargeAmount($callType, $basePrice);

                $batch[] = [
                    'datasource' => $this->weightedPick(['OCC' => 0.84, 'OCC_VAS' => 0.13, 'OCC_PSM' => 0.03]),
                    'a_msisdn' => $this->makeMsisdn(),
                    'b_msisdn' => $this->formatServiceBMsisdn((string) $service->numero_court, 'occ', $callType),
                    'start_date' => $cursor,
                    'start_hour' => $hour,
                    'call_type' => $callType,
                    'event_type' => $callType === 'VAS' ? '74' : ($callType === 'SMS' ? '51' : '1'),
                    'subscriber_type' => $this->weightedPick(['PREPAID' => 0.71, 'POSTPAID' => 0.19, 'HYB' => 0.10]),
                    'roaming_type' => $this->weightedPick(['HOME' => 0.965, 'ROAM' => 0.035]),
                    'partner' => $service->nom_fournisseur,
                    'charge_amount' => round($amount, 3),
                    'keyword' => $keyword,
                    'orig_start_time' => $this->origStartTime($cursor, $hour, $minute, $second),
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ];

                if (count($batch) >= $chunk) {
                    DB::table('ra_t_occ_cdr_detail')->insert($batch);
                    $inserted += count($batch);
                    $batch = [];
                }
            }

            $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
        }

        if (!empty($batch)) {
            DB::table('ra_t_occ_cdr_detail')->insert($batch);
            $inserted += count($batch);
        }

        return $inserted;
    }

    private function generateMmgDetail(string $startDate, string $endDate, int $avgPerDay, array $services, int $chunk): int
    {
        $inserted = 0;
        $batch = [];
        $cursor = $startDate;
        $nowTs = now()->toDateTimeString();

        while ($cursor <= $endDate) {
            $dailyTarget = (int) round($avgPerDay * $this->dayFactor($cursor) * $this->randFloat(0.92, 1.08));
            for ($i = 0; $i < $dailyTarget; $i++) {
                $callType = $this->weightedPick([
                    'VAS' => 0.80,
                    'SMS' => 0.14,
                    'DATA' => 0.06,
                ]);

                $service = $this->pickService($services, ['VAS' => 0.74, 'SMS' => 0.18, 'DATA' => 0.08]);
                $hour = $this->pickHour();
                $minute = mt_rand(0, 59);
                $second = mt_rand(0, 59);

                $batch[] = [
                    'ne' => $this->weightedPick(['MMG01' => 0.50, 'MMG02' => 0.30, 'MMG03' => 0.20]),
                    'a_msisdn' => $this->makeMsisdn(),
                    'b_msisdn' => $this->formatServiceBMsisdn((string) $service->numero_court, 'mmg'),
                    'start_date' => $cursor,
                    'start_hour' => $hour,
                    'event_type' => $callType === 'VAS' ? 'MT' : ($callType === 'SMS' ? 'SMS' : 'DATA'),
                    'event_type_orig' => $callType === 'VAS' ? 'CONTENT_DELIVERY' : ($callType === 'SMS' ? 'SHORT_MESSAGE' : 'DATA_EVENT'),
                    'call_type' => $callType,
                    'event_status' => $this->weightedPick(['SUCCESS' => 0.95, 'FAILED' => 0.03, 'PENDING' => 0.02]),
                    'subscriber_type' => $this->weightedPick(['PREPAID' => 0.69, 'POSTPAID' => 0.20, 'HYB' => 0.11]),
                    'service_type' => $service->keyword,
                    'orig_start_time' => $this->origStartTime($cursor, $hour, $minute, $second),
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ];

                if (count($batch) >= $chunk) {
                    DB::table('ra_t_mmg_cdr_det')->insert($batch);
                    $inserted += count($batch);
                    $batch = [];
                }
            }

            $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
        }

        if (!empty($batch)) {
            DB::table('ra_t_mmg_cdr_det')->insert($batch);
            $inserted += count($batch);
        }

        return $inserted;
    }

    private function generateOccAgg(string $startDate, string $endDate, array $services, int $chunk): int
    {
        $inserted = 0;
        $batch = [];
        $cursor = $startDate;
        $nowTs = now()->toDateTimeString();

        while ($cursor <= $endDate) {
            $weekdayFactor = $this->dayFactor($cursor);
            $dailyTotal = (int) round(12000 * $weekdayFactor * $this->randFloat(0.88, 1.10));
            $loads = $this->buildDailyServiceLoads($dailyTotal, $services);

            foreach ($loads as $entry) {
                $portion = (int) $entry['count'];
                $service = $entry['service'];
                $hour = $this->pickHour();
                $charge = round($portion * max(0.05, (float) ($service->prix ?? 0.5)) * $this->randFloat(0.93, 1.08), 3);

                $batch[] = [
                    'b_msisdn' => $this->formatServiceBMsisdn((string) $service->numero_court, 'occ'),
                    'start_date' => $cursor,
                    'start_hour' => $hour,
                    'call_type' => 'VAS',
                    'event_type' => '74',
                    'subscriber_type' => $this->weightedPick(['PREPAID' => 0.72, 'POSTPAID' => 0.18, 'HYB' => 0.10]),
                    'keyword' => $service->keyword,
                    'cdr_count' => $portion,
                    'charge_amount' => $charge,
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ];

                if (count($batch) >= $chunk) {
                    DB::table('ra_t_occ_agg')->insert($batch);
                    $inserted += count($batch);
                    $batch = [];
                }
            }

            $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
        }

        if (!empty($batch)) {
            DB::table('ra_t_occ_agg')->insert($batch);
            $inserted += count($batch);
        }

        return $inserted;
    }

    private function generateMmgAgg(string $startDate, string $endDate, array $services, int $chunk): int
    {
        $inserted = 0;
        $batch = [];
        $cursor = $startDate;
        $nowTs = now()->toDateTimeString();

        while ($cursor <= $endDate) {
            $weekdayFactor = $this->dayFactor($cursor);
            $dailyTotal = (int) round(13000 * $weekdayFactor * $this->randFloat(0.88, 1.10));
            $loads = $this->buildDailyServiceLoads($dailyTotal, $services);

            foreach ($loads as $entry) {
                $portion = (int) $entry['count'];
                $service = $entry['service'];

                $batch[] = [
                    'b_msisdn' => $this->formatServiceBMsisdn((string) $service->numero_court, 'mmg'),
                    'start_date' => $cursor,
                    'start_hour' => $this->pickHour(),
                    'event_type' => 'MT',
                    'call_type' => 'VAS',
                    'event_status' => $this->weightedPick(['SUCCESS' => 0.96, 'FAILED' => 0.03, 'PENDING' => 0.01]),
                    'subscriber_type' => $this->weightedPick(['PREPAID' => 0.70, 'POSTPAID' => 0.20, 'HYB' => 0.10]),
                    'service_type' => $service->keyword,
                    'cdr_count' => $portion,
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ];

                if (count($batch) >= $chunk) {
                    DB::table('ra_t_mmg_agg')->insert($batch);
                    $inserted += count($batch);
                    $batch = [];
                }
            }

            $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
        }

        if (!empty($batch)) {
            DB::table('ra_t_mmg_agg')->insert($batch);
            $inserted += count($batch);
        }

        return $inserted;
    }

    private function syncLegacyCdrAggFromMmgAgg(int $chunk): int
    {
        $inserted = 0;
        $batch = [];
        $nowTs = now()->toDateTimeString();

        DB::table('ra_t_mmg_agg')
            ->orderBy('id')
            ->chunk(5000, function ($rows) use (&$batch, &$inserted, $chunk, $nowTs) {
                foreach ($rows as $row) {
                    $batch[] = [
                        'b_msisdn' => $row->b_msisdn,
                        'start_date' => $row->start_date,
                        'start_hour' => $row->start_hour,
                        'event_type' => $row->event_type,
                        'call_type' => $row->call_type,
                        'event_status' => $row->event_status,
                        'subscriber_type' => $row->subscriber_type,
                        'service_type' => $row->service_type,
                        'cdr_count' => $row->cdr_count,
                        'created_at' => $nowTs,
                        'updated_at' => $nowTs,
                    ];

                    if (count($batch) >= $chunk) {
                        DB::table('ra_t_cdr_agg')->insert($batch);
                        $inserted += count($batch);
                        $batch = [];
                    }
                }
            });

        if (!empty($batch)) {
            DB::table('ra_t_cdr_agg')->insert($batch);
            $inserted += count($batch);
        }

        return $inserted;
    }

    private function dayFactor(string $date): float
    {
        $dow = (int) date('N', strtotime($date));
        return match ($dow) {
            6 => 0.90, // Saturday
            7 => 0.84, // Sunday
            1 => 1.04, // Monday recovery
            default => 1.00,
        };
    }

    private function buildDailyServiceLoads(int $dailyTotal, array $services): array
    {
        if (empty($services)) {
            return [];
        }

        $weights = [];
        $weightSum = 0.0;

        foreach (array_values($services) as $idx => $service) {
            $rankWeight = max(0.4, 2.3 - ($idx * 0.11));
            $priceWeight = max(0.6, 0.9 + ((float) ($service->prix ?? 0.5) * 0.8));
            $weight = $rankWeight * $priceWeight * $this->randFloat(0.9, 1.1);
            $weights[] = ['service' => $service, 'weight' => $weight];
            $weightSum += $weight;
        }

        $loads = [];
        $allocated = 0;
        $count = count($weights);
        $i = 0;

        foreach ($weights as $entry) {
            $service = $entry['service'];
            $weight = $entry['weight'];
            $i++;
            if ($i === $count) {
                $portion = max(300, $dailyTotal - $allocated);
            } else {
                $portion = (int) floor(($dailyTotal * $weight) / max(0.0001, $weightSum));
                $portion = max(300, $portion);
            }

            $loads[] = ['service' => $service, 'count' => $portion];
            $allocated += $portion;
        }

        if ($allocated > $dailyTotal) {
            $overflow = $allocated - $dailyTotal;
            $lastIndex = array_key_last($loads);
            if ($lastIndex !== null) {
                $loads[$lastIndex]['count'] = max(300, $loads[$lastIndex]['count'] - $overflow);
            }
        }

        return $loads;
    }

    private function pickService(array $services, array $callTypeWeights)
    {
        $filtered = [];
        foreach ($services as $service) {
            $serviceType = strtolower((string) ($service->type_service ?? 'service'));
            $weight = $callTypeWeights['VAS'] ?? 1.0;

            if ($serviceType === 'jeu') {
                $weight *= 1.15;
            }

            $filtered[] = ['service' => $service, 'weight' => $weight * $this->randFloat(0.9, 1.1)];
        }

        $sum = array_sum(array_column($filtered, 'weight'));
        $r = $this->randFloat(0.0, max(0.0001, (float) $sum));
        $acc = 0.0;

        foreach ($filtered as $entry) {
            $acc += (float) $entry['weight'];
            if ($r <= $acc) {
                return $entry['service'];
            }
        }

        return $filtered[array_key_last($filtered)]['service'];
    }

    private function pickHour(): int
    {
        return (int) $this->weightedPick($this->hourWeights);
    }

    private function occChargeAmount(string $callType, float $basePrice): float
    {
        if ($callType === 'VAS') {
            // Real data shows an average VAS price around 0.33 DT to 0.40 DT
            // We ignore the $basePrice since the DB 'prix' column seems to be incorrect/too low (avg 0.025)
            // Or we use a realistic multiplier
            return $this->randFloat(0.15, 0.85); // average ~0.50 DT
        }

        if ($callType === 'SMS') {
            return $this->randFloat(0.03, 0.12);
        }

        return $this->randFloat(0.08, 0.40);
    }

    private function makeMsisdn(): string
    {
        $prefix = $this->weightedPick([
            '2169' => 0.58,
            '2162' => 0.22,
            '2165' => 0.12,
            '2164' => 0.08,
        ]);

        return $prefix . str_pad((string) mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT);
    }

    private function origStartTime(string $date, int $hour, int $minute, int $second): string
    {
        return str_replace('-', '', $date)
            . str_pad((string) $hour, 2, '0', STR_PAD_LEFT)
            . str_pad((string) $minute, 2, '0', STR_PAD_LEFT)
            . str_pad((string) $second, 2, '0', STR_PAD_LEFT);
    }

    private function randFloat(float $min, float $max): float
    {
        $rand = mt_rand() / mt_getrandmax();
        return $min + ($max - $min) * $rand;
    }

    private function weightedPick(array $weights)
    {
        $sum = array_sum($weights);
        if ($sum <= 0) {
            $keys = array_keys($weights);
            return $keys[0] ?? null;
        }

        $r = $this->randFloat(0.0, (float) $sum);
        $acc = 0.0;
        foreach ($weights as $value => $weight) {
            $acc += (float) $weight;
            if ($r <= $acc) {
                return $value;
            }
        }

        $keys = array_keys($weights);
        return end($keys);
    }

    private function formatServiceBMsisdn(string $numeroCourt, string $context, ?string $callType = null): string
    {
        $raw = trim((string) $numeroCourt);
        $raw = preg_replace('/\s+|\+/', '', $raw);

        // Preserve explicit internet marker
        if ($raw === '' || strcasecmp($raw, 'internet.tn') === 0) {
            return 'internet.tn';
        }

        // If contains letters, return as-is
        if (preg_match('/[A-Za-z]/', $raw)) {
            return $raw;
        }

        // Numeric handling: prefer 216-prefixed forms
        $isNumeric = ctype_digit($raw);

        if (! $isNumeric) {
            return $raw;
        }

        // For OCC, allow some DATA rows to be 'internet.tn'
        if ($context === 'occ' && $callType === 'DATA') {
            if ($this->randFloat(0.0, 1.0) < 0.40) {
                return 'internet.tn';
            }
        }

        // If already prefixed with 216, keep (or trim leading zeros)
        if (str_starts_with($raw, '216')) {
            return $raw;
        }

        // Short codes or local numbers: sometimes return full MSISDN, sometimes 216+short
        $r = $this->randFloat(0.0, 1.0);
        if ($context === 'mmg') {
            // MMG: prefer full MSISDN but allow 216+short
            if ($r < 0.6) {
                return '216' . str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
            }
            return '216' . ltrim($raw, '0');
        }

        // OCC: allow short-codes (216+short) or full MSISDN
        if ($r < 0.5) {
            return '216' . ltrim($raw, '0');
        }
        return '216' . str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
    }
}
