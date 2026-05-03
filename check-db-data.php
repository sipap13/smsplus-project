<?php

require __DIR__.'/smsplus-api/vendor/autoload.php';

$app = require_once __DIR__.'/smsplus-api/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking database data...\n";

try {
    $pdo = new PDO('pgsql:host=db;port=5432;dbname=smsplus', 'postgres', 'postgres');
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM ra_t_occ_cdr_detail WHERE call_type = 'VAS' AND start_date >= NOW() - INTERVAL '60 days'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "VAS records last 60 days: " . $result['count'] . "\n";
    
    if ($result['count'] < 7) {
        echo "ERROR: Insufficient data for predictions (need at least 7 days)\n";
        exit(1);
    }
    
    echo "SUCCESS: Sufficient data available for predictions\n";
    
} catch (Exception $e) {
    echo "DB ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
