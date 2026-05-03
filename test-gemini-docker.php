<?php

require __DIR__.'/smsplus-api/vendor/autoload.php';

$app = require_once __DIR__.'/smsplus-api/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing Gemini in Docker environment...\n";

try {
    $result = app('App\Services\AiProviderService')->callGemini('Test', 'Hello', 50);
    echo "Gemini works! Result: " . $result . "\n";
} catch (Exception $e) {
    echo "Gemini error: " . $e->getMessage() . "\n";
}
