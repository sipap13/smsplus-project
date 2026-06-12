
$export = new App\Exports\CdrMmgExport();
$file = storage_path('app/test_mmg.xlsx');
Maatwebsite\Excel\Facades\Excel::store($export, 'test_mmg.xlsx');
echo "Saved size: " . filesize($file) . "\n";
