<?php

echo "Testing predictions in Docker environment...\n";

// Test database connection
try {
    $pdo = new PDO('pgsql:host=db;port=5432;dbname=smsplus', 'postgres', 'postgres');
    echo "DB connection: OK\n";
    
    // Check if we have data
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM ra_t_occ_cdr_detail WHERE call_type = 'VAS' AND start_date >= NOW() - INTERVAL '60 days'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "VAS records last 60 days: " . $result['count'] . "\n";
    
    if ($result['count'] < 7) {
        echo "ERROR: Insufficient data for predictions (need at least 7 days)\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "DB ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Test AI providers
echo "\nTesting AI providers...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Test Groq
curl_setopt($ch, CURLOPT_URL, 'http://api:8000/api/ai/health');
$response = curl_exec($ch);
echo "AI Health check: " . $response . "\n";

curl_close($ch);

echo "\nTest completed.\n";
