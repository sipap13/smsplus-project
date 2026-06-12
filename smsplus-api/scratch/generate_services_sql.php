<?php
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$xlsxPath = __DIR__ . '/../storage/imports/List VAS 1.xlsx';
if (!file_exists($xlsxPath)) {
    fwrite(STDERR, "ERROR: file not found: {$xlsxPath}\n");
    exit(1);
}
try {
    $reader = IOFactory::createReaderForFile($xlsxPath);
    $spreadsheet = $reader->load($xlsxPath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);
    if (empty($rows)) {
        fwrite(STDERR, "ERROR: no rows found in Excel\n");
        exit(1);
    }
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

        $esc = fn($v) => str_replace("'", "''", trim((string)$v));
        $nom_fournisseur = $esc($rowAssoc['nom_fournisseur'] ?? $rowAssoc['fournisseur'] ?? '');
        $nom_service = $esc($rowAssoc['nom_service'] ?? $rowAssoc['service'] ?? '');
        $numero_court = $esc($rowAssoc['numero_court'] ?? $rowAssoc['numero'] ?? $rowAssoc['short'] ?? '');
        $keyword = $esc($rowAssoc['keyword'] ?? $rowAssoc['mot_cle'] ?? $rowAssoc['mot cle'] ?? '');
        $type_service = $esc($rowAssoc['type'] ?? $rowAssoc['type_service'] ?? 'Service');
        $prixRaw = $rowAssoc['prix'] ?? $rowAssoc['prix ttc'] ?? $rowAssoc['price'] ?? '0';
        $prix = (float) str_replace(',', '.', preg_replace('/[^0-9,\.]/', '', $prixRaw));

        if ($keyword === '' && $numero_court === '') continue;

        // Use UPDATE then INSERT-if-not-exists pattern
        $where = $keyword !== '' ? "keyword = '{$keyword}'" : "(keyword = '' AND numero_court = '{$numero_court}')";

        $update = "UPDATE ra_t_services SET nom_fournisseur = '{$nom_fournisseur}', nom_service = '{$nom_service}', numero_court = '{$numero_court}', keyword = '{$keyword}', type_service = '{$type_service}', prix = {$prix}, actif = true, updated_at = now() WHERE {$where};";

        $insert = "INSERT INTO ra_t_services (nom_fournisseur, nom_service, numero_court, keyword, type_service, prix, actif, created_at, updated_at) SELECT '{$nom_fournisseur}', '{$nom_service}', '{$numero_court}', '{$keyword}', '{$type_service}', {$prix}, true, now(), now() WHERE NOT EXISTS (SELECT 1 FROM ra_t_services WHERE {$where});";

        echo $update . "\n" . $insert . "\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
