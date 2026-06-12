
$req = Illuminate\Http\Request::create('/api/export/mmg', 'GET');
$req->attributes->set('auth_user', (object)['id' => 1]);
$resp = app(App\Http\Controllers\Api\ExportController::class)->exportMmg($req);
ob_start();
$resp->sendContent();
$content = ob_get_clean();
echo "Exported length: " . strlen($content) . "\n";
