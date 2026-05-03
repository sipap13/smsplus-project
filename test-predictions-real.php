<?php

require __DIR__.'/smsplus-api/vendor/autoload.php';

$app = require_once __DIR__.'/smsplus-api/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing predictions endpoint with real data...\n";

try {
    $controller = app('App\Http\Controllers\Api\PredictionController');
    $request = new \Illuminate\Http\Request(['horizon' => 7]);
    
    $response = $controller->revenus($request);
    $data = $response->getData(true);
    
    echo "Response status: " . $response->getStatusCode() . "\n";
    echo "AI Provider: " . ($data['ai_provider'] ?? 'none') . "\n";
    echo "Source: " . ($data['source'] ?? 'none') . "\n";
    echo "Fallback: " . ($data['ai_fallback'] ? 'true' : 'false') . "\n";
    echo "Score fiabilité: " . ($data['score_fiabilite'] ?? 0) . "\n";
    echo "Nombre de prédictions: " . count($data['predictions'] ?? []) . "\n";
    
    if (isset($data['insuffisant'])) {
        echo "ERREUR: Données insuffisantes - " . $data['message'] . "\n";
    } else {
        echo "SUCCESS: Prédictions générées avec succès\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
