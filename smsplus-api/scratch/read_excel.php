<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$filePath = __DIR__ . '/../storage/imports/List VAS 1.xlsx';

if (!file_exists($filePath)) {
    die("File not found: " . $filePath);
}

$spreadsheet = IOFactory::load($filePath);
$worksheet = $spreadsheet->getActiveSheet();

$data = $worksheet->toArray();

// Print first 10 rows
for ($i = 0; $i < min(10, count($data)); $i++) {
    echo json_encode($data[$i]) . "\n";
}
