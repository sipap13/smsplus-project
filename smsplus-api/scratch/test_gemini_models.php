<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$apiKey = env('GEMINI_API_KEY');
$url = "https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}";

$response = Http::get($url);

if ($response->successful()) {
    $models = $response->json()['models'];
    foreach ($models as $model) {
        if (str_contains($model['name'], 'gemini')) {
            echo $model['name'] . " - " . $model['supportedGenerationMethods'][0] . "\n";
        }
    }
} else {
    echo "Error: " . $response->status() . "\n";
    echo $response->body() . "\n";
}
