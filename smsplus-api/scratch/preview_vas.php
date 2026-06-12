<?php
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$path = __DIR__ . '/../storage/imports/List VAS 1.xlsx';
if (!file_exists($path)) {
    echo json_encode(['error' => 'File not found', 'path' => $path], JSON_PRETTY_PRINT);
    exit(1);
}
try {
    $reader = IOFactory::createReaderForFile($path);
    $spreadsheet = $reader->load($path);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);
    if (empty($rows)) {
        echo json_encode(['error' => 'No rows found'], JSON_PRETTY_PRINT);
        exit(0);
    }
    $first = array_shift($rows);
    $headers = [];
    foreach ($first as $col => $val) {
        $headers[$col] = strtolower(trim((string) $val));
    }
    $preview = [];
    $count = 0;
    foreach ($rows as $r) {
        $rowAssoc = [];
        foreach ($headers as $col => $h) {
            $rowAssoc[$h] = trim((string) ($r[$col] ?? ''));
        }
        $preview[] = $rowAssoc;
        $count++;
        if ($count >= 10) break;
    }
    echo json_encode(['path' => $path, 'preview_count' => count($preview), 'preview' => $preview], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['error' => 'Exception', 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
    exit(1);
}
