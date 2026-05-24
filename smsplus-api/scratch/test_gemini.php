<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$apiKey = env('GEMINI_API_KEY');
$models = [
    'v1/gemini-1.5-flash',
    'v1/gemini-1.5-flash-latest',
    'v1beta/gemini-1.5-flash',
];

foreach ($models as $m) {
    echo "Testing $m...\n";
    [$ver, $name] = explode('/', $m);
    $url = "https://generativelanguage.googleapis.com/$ver/models/$name:generateContent?key=$apiKey";
    
    $response = Http::post($url, [
        'contents' => [['parts' => [['text' => 'Hello']]]]
    ]);
    
    echo "Status: " . $response->status() . "\n";
    if ($response->failed()) {
        echo "Body: " . $response->body() . "\n";
    } else {
        echo "Success!\n";
    }
    echo "-------------------\n";
}
