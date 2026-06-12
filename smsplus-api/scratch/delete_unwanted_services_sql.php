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

    $keywords = [];
    $numeros = [];
    foreach ($rows as $r) {
        $rowAssoc = [];
        foreach ($headers as $col => $h) {
            $rowAssoc[$h] = trim((string) ($r[$col] ?? ''));
        }
        $k = $rowAssoc['keyword'] ?? $rowAssoc['mot_cle'] ?? $rowAssoc['mot cle'] ?? '';
        $n = $rowAssoc['numero_court'] ?? $rowAssoc['numero'] ?? $rowAssoc['short'] ?? '';
        if ($k !== '') $keywords[] = $k;
        if ($n !== '') $numeros[] = $n;
    }

    $keywords = array_values(array_unique(array_map('trim', $keywords)));
    $numeros = array_values(array_unique(array_map('trim', $numeros)));

    $escape = fn($s) => str_replace("'", "''", $s);
    $providers = ['Topnet','Orange Tunisie','Tunisie Telecom','Ooredoo'];

    $prov_list = array_map(fn($p) => "'" . $escape($p) . "'", $providers);
    $kw_list = array_map(fn($k) => "'" . $escape($k) . "'", $keywords);
    $num_list = array_map(fn($n) => "'" . $escape($n) . "'", $numeros);

    $sql = "BEGIN;\n";
    $where_keep = [];
    $where_keep[] = "nom_fournisseur IN (" . implode(',', $prov_list) . ")";
    if (!empty($kw_list)) {
        $where_keep[] = "(keyword IS NOT NULL AND keyword <> '' AND keyword IN (" . implode(',', $kw_list) . "))";
    }
    if (!empty($num_list)) {
        $where_keep[] = "(numero_court IS NOT NULL AND numero_court <> '' AND numero_court IN (" . implode(',', $num_list) . "))";
    }

    $keep_clause = implode(' OR ', $where_keep);
    $delete_where = "NOT (" . $keep_clause . ")";

    $sql .= "-- count to delete\n";
    $sql .= "SELECT COUNT(*) AS to_delete FROM ra_t_services WHERE {$delete_where};\n";
    $sql .= "-- perform delete\n";
    $sql .= "DELETE FROM ra_t_services WHERE {$delete_where};\n";
    $sql .= "-- count remaining\n";
    $sql .= "SELECT COUNT(*) AS remaining FROM ra_t_services;\n";
    $sql .= "COMMIT;\n";

    echo $sql;

} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
