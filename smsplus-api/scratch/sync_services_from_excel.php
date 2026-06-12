<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

$xlsxPath = __DIR__ . '/../storage/imports/List VAS 1.xlsx';
if (!file_exists($xlsxPath)) {
    echo "ERROR: file not found: {$xlsxPath}\n";
    exit(1);
}

try {
    $reader = IOFactory::createReaderForFile($xlsxPath);
    $spreadsheet = $reader->load($xlsxPath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);
    if (empty($rows)) {
        echo "ERROR: no rows found in Excel\n";
        exit(1);
    }

    $first = array_shift($rows);
    $headers = [];
    foreach ($first as $col => $val) {
        $headers[$col] = strtolower(trim((string) $val));
    }

    $toSync = [];
    foreach ($rows as $r) {
        $rowAssoc = [];
        foreach ($headers as $col => $h) {
            $rowAssoc[$h] = trim((string) ($r[$col] ?? ''));
        }

        $nom_fournisseur = $rowAssoc['nom_fournisseur'] ?? $rowAssoc['fournisseur'] ?? '';
        $nom_service = $rowAssoc['nom_service'] ?? $rowAssoc['service'] ?? '';
        $numero_court = $rowAssoc['numero_court'] ?? $rowAssoc['numero'] ?? $rowAssoc['short'] ?? '';
        $keyword = $rowAssoc['keyword'] ?? $rowAssoc['mot_cle'] ?? $rowAssoc['mot cle'] ?? '';
        $type_service = $rowAssoc['type'] ?? $rowAssoc['type_service'] ?? 'Service';
        $prixRaw = $rowAssoc['prix'] ?? $rowAssoc['prix ttc'] ?? $rowAssoc['price'] ?? '0';
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

    // Keep providers list
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

    // Merge unique by keyword or numero_court
    $merged = [];
    $seen = [];
    foreach (array_merge($existing, $toSync) as $svc) {
        $key = trim((string) ($svc['keyword'] ?? '')) ?: null;
        $num = trim((string) ($svc['numero_court'] ?? '')) ?: null;
        $id = $key ? 'k:' . strtoupper($key) : ('n:' . $num);
        if ($id === 'n:' || $id === 'k:') continue;
        if (isset($seen[$id])) continue;
        $seen[$id] = true;
        $merged[] = $svc;
    }

    $inserted = 0; $updated = 0; $processed = 0;
    foreach ($merged as $service) {
        $processed++;
        $match = [];
        if (!empty($service['keyword'])) {
            $match = ['keyword' => $service['keyword']];
        } else {
            $match = ['numero_court' => $service['numero_court']];
        }
        $exists = DB::table('ra_t_services')->where($match)->exists();
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
                'updated_at' => now()->toDateTimeString(),
            ]
        );
        if ($exists) $updated++; else $inserted++;
    }

    echo "SYNC COMPLETE: processed={$processed}, inserted={$inserted}, updated={$updated}\n";
    exit(0);

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
