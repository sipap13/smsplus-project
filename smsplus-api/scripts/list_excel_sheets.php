<?php

require __DIR__ . '/../vendor/autoload.php';

$path = __DIR__ . '/../storage/imports/sms plus alerts + users.xlsx';
if (! is_file($path)) {
    fwrite(STDERR, "File not found: {$path}\n");
    exit(1);
}

$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
$names = $spreadsheet->getSheetNames();

echo 'sheets=' . count($names) . PHP_EOL;
foreach ($names as $i => $name) {
    echo $i . ':' . $name . PHP_EOL;
}

$alertSheet = $spreadsheet->getSheetByName('alert');
if ($alertSheet) {
    $highestRow = $alertSheet->getHighestRow();
    $highestCol = $alertSheet->getHighestColumn();
    echo PHP_EOL . "alert_sheet_highest_row={$highestRow}, highest_col={$highestCol}" . PHP_EOL;
    $data = $alertSheet->rangeToArray("A1:{$highestCol}" . min($highestRow, 20), null, true, true, false);
    foreach ($data as $idx => $row) {
        echo ($idx + 1) . ': ' . json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}

