<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Test the predictions endpoint directly
$controller = new \App\Http\Controllers\Api\PredictionController(
    app('App\Services\ChatbotService'),
    app('App\Services\AiProviderService'),
    app('App\Services\StatisticalPredictor')
);

$request = new \Illuminate\Http\Request(['horizon' => 7]);

echo "Testing predictions endpoint...\n";
try {
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
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
