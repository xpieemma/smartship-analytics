

<?php
// tests/test_api.php - API endpoint tests
require_once 'includes/header.php';
echo "<!DOCTYPE html>
<html>
<head>
    <title>API Tests</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .test-pass { background: #d4edda; color: #155724; padding: 10px; margin: 5px 0; border-radius: 4px; }
        .test-fail { background: #f8d7da; color: #721c24; padding: 10px; margin: 5px 0; border-radius: 4px; }
        .test-info { background: #d1ecf1; color: #0c5460; padding: 10px; margin: 5px 0; border-radius: 4px; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow: auto; }
        h1, h2 { color: #333; }
    </style>
</head>
<body>
    <h1>🌐 SmartShip API Tests</h1>";

function testEndpoint($url, $description) {
    echo "<h2>🔍 Testing: $description</h2>";
    echo "<div>URL: $url</div>";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "<div class='test-fail'>❌ Connection Error: $error</div>";
        return false;
    }
    
    if ($httpCode != 200) {
        echo "<div class='test-fail'>❌ HTTP $httpCode - Not OK</div>";
        return false;
    }
    
    echo "<div class='test-pass'>✅ HTTP 200 OK</div>";
    
    // Try to parse JSON
    $data = json_decode($response, true);
    if ($data === null) {
        echo "<div class='test-fail'>❌ Invalid JSON response</div>";
        echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
        return false;
    }
    
    echo "<div class='test-pass'>✅ Valid JSON response</div>";
    
    // Check for success flag if present
    if (isset($data['success'])) {
        if ($data['success']) {
            echo "<div class='test-pass'>✅ API returned success=true</div>";
        } else {
            echo "<div class='test-fail'>❌ API returned success=false</div>";
            if (isset($data['error'])) {
                echo "<div class='test-fail'>Error: " . htmlspecialchars($data['error']) . "</div>";
            }
        }
    }
    
    echo "<h4>Response Preview:</h4>";
    echo "<pre>" . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "</pre>";
    
    return $data;
}

// Base URL - adjust if your project is in a different location
$baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/smartship/api';

$endpoints = [
    "$baseUrl/test.php" => "Test endpoint",
    "$baseUrl/dashboard-data.php" => "Dashboard data",
    "$baseUrl/audit.php?action=dashboard" => "Audit dashboard",
    "$baseUrl/audit.php?action=audit_batch" => "Audit batch"
];

$results = [];

foreach ($endpoints as $url => $description) {
    $data = testEndpoint($url, $description);
    $results[$url] = $data;
    
    // Specific validation for dashboard-data.php
    if (strpos($url, 'dashboard-data.php') !== false && $data && isset($data['success']) && $data['success']) {
        echo "<h3>📊 Dashboard Data Validation:</h3>";
        
        $validations = [
            'metrics' => isset($data['metrics']),
            'lanes' => isset($data['lanes']),
            'charts' => isset($data['charts']),
            'exceptions' => isset($data['exceptions']),
            'charts.trend' => isset($data['charts']['trend']),
            'charts.types' => isset($data['charts']['types'])
        ];
        
        foreach ($validations as $key => $valid) {
            if ($valid) {
                echo "<div class='test-pass'>✅ $key exists</div>";
            } else {
                echo "<div class='test-fail'>❌ $key missing</div>";
            }
        }
        
        // Check data types
        if (isset($data['metrics'])) {
            echo "<h4>Metric Values:</h4>";
            echo "<ul>";
            foreach ($data['metrics'] as $key => $value) {
                echo "<li><strong>$key:</strong> $value</li>";
            }
            echo "</ul>";
        }
        
        if (isset($data['exceptions']) && is_array($data['exceptions'])) {
            echo "<div class='test-pass'>✅ Found " . count($data['exceptions']) . " exceptions</div>";
        }
    }
    
    echo "<hr>";
}

echo "<h2>📋 Summary</h2>";
echo "<table border='1' cellpadding='8' style='border-collapse: collapse; background: white;'>";
echo "<tr><th>Endpoint</th><th>Status</th><th>Response</th></tr>";
foreach ($results as $url => $data) {
    $status = $data ? '✅ OK' : '❌ Failed';
    $responseType = $data ? 'Valid JSON' : 'Invalid';
    echo "<tr>";
    echo "<td>" . htmlspecialchars($url) . "</td>";
    echo "<td>$status</td>";
    echo "<td>$responseType</td>";
    echo "</tr>";
}
echo "</table>";

echo "</body></html>";