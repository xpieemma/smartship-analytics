<?php
// tests/test_audit_engine.php - Audit engine tests
require_once 'includes/header.php';
require_once '../src/bootstrap.php';
require_once '../src/Audit/AuditEngine.php';

use SmartShip\Audit\AuditEngine;

echo "<!DOCTYPE html>
<html>
<head>
    <title>Audit Engine Tests</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .test-pass { background: #d4edda; color: #155724; padding: 10px; margin: 5px 0; border-radius: 4px; }
        .test-fail { background: #f8d7da; color: #721c24; padding: 10px; margin: 5px 0; border-radius: 4px; }
        .test-info { background: #d1ecf1; color: #0c5460; padding: 10px; margin: 5px 0; border-radius: 4px; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>⚙️ SmartShip Audit Engine Tests</h1>";

$tests_run = 0;
$tests_passed = 0;

function assertTest($condition, $message, $details = '') {
    global $tests_run, $tests_passed;
    $tests_run++;
    if ($condition) {
        $tests_passed++;
        echo "<div class='test-pass'>✅ $message</div>";
    } else {
        echo "<div class='test-fail'>❌ $message - $details</div>";
    }
}

try {
    $pdo = getDatabaseConnection();
    $auditEngine = new AuditEngine($pdo);
    
    // Test 1: Engine instantiation
    echo "<h2>🔧 Engine Initialization</h2>";
    assertTest($auditEngine instanceof AuditEngine, "AuditEngine instantiated");
    
    // Test 2: Get dashboard metrics
    echo "<h2>📊 Dashboard Metrics</h2>";
    $metrics = $auditEngine->getDashboardMetrics();
    
    assertTest(is_array($metrics), "Metrics returned as array");
    assertTest(isset($metrics['total_shipments']), "Metrics include total_shipments");
    assertTest(isset($metrics['total_spend']), "Metrics include total_spend");
    assertTest(isset($metrics['total_exceptions']), "Metrics include total_exceptions");
    assertTest(isset($metrics['potential_savings']), "Metrics include potential_savings");
    
    echo "<div class='test-info'>";
    echo "<strong>Current Metrics:</strong><br>";
    foreach ($metrics as $key => $value) {
        echo "$key: " . (is_numeric($value) ? number_format($value, 2) : $value) . "<br>";
    }
    echo "</div>";
    
    // Test 3: Get a shipment to audit
    echo "<h2>📦 Shipment Audit Tests</h2>";
    
    $shipmentId = $pdo->query("SELECT id FROM shipments LIMIT 1")->fetchColumn();
    
    if ($shipmentId) {
        assertTest($shipmentId > 0, "Found shipment to test", "ID: $shipmentId");
        
        // Test 4: Audit single shipment
        $result = $auditEngine->auditShipment($shipmentId);
        
        assertTest(is_array($result), "Audit returned array");
        assertTest(isset($result['shipment_id']), "Result includes shipment_id");
        assertTest(isset($result['exceptions_found']), "Result includes exceptions_found");
        assertTest(isset($result['total_potential_savings']), "Result includes potential savings");
        
        echo "<div class='test-info'>";
        echo "<strong>Audit Result for Shipment $shipmentId:</strong><br>";
        echo "Exceptions found: {$result['exceptions_found']}<br>";
        echo "Potential savings: $" . number_format($result['total_potential_savings'], 2) . "<br>";
        
        if (!empty($result['exceptions'])) {
            echo "<strong>Exceptions:</strong><br>";
            foreach ($result['exceptions'] as $ex) {
                echo "- {$ex['type']}: $" . number_format($ex['potential_savings'], 2) . "<br>";
            }
        }
        echo "</div>";
        
    } else {
        echo "<div class='test-fail'>❌ No shipments found to test</div>";
    }
    
    // Test 5: Batch audit
    echo "<h2>📦📦 Batch Audit Test</h2>";
    
    $shipmentIds = $pdo->query("SELECT id FROM shipments ORDER BY RAND() LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($shipmentIds) > 0) {
        $batchResults = $auditEngine->auditBatch($shipmentIds);
        
        assertTest(is_array($batchResults), "Batch audit returned array");
        assertTest(count($batchResults) == count($shipmentIds), "Processed all shipments");
        
        echo "<div class='test-info'>";
        echo "<strong>Batch processed " . count($batchResults) . " shipments</strong><br>";
        
        $totalSavings = 0;
        foreach ($batchResults as $result) {
            if (isset($result['total_potential_savings'])) {
                $totalSavings += $result['total_potential_savings'];
            }
        }
        echo "Total potential savings: $" . number_format($totalSavings, 2) . "<br>";
        echo "</div>";
    }
    
    // Test 6: Exception type distribution
    echo "<h2>📈 Exception Analysis</h2>";
    
    $typeStats = $pdo->query("
        SELECT exception_type, COUNT(*) as count, AVG(severity_score) as avg_severity
        FROM audit_exceptions
        GROUP BY exception_type
        ORDER BY count DESC
    ")->fetchAll();
    
    if (count($typeStats) > 0) {
        echo "<div class='test-info'>";
        echo "<strong>Exception Distribution:</strong><br>";
        foreach ($typeStats as $stat) {
            echo "- {$stat['exception_type']}: {$stat['count']} (avg severity: " . round($stat['avg_severity'], 1) . ")<br>";
        }
        echo "</div>";
    }
    
    // Test 7: Severity scoring
    echo "<h2>🎯 Severity Scoring Test</h2>";
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($auditEngine);
    $method = $reflection->getMethod('calculateSeverity');
    $method->setAccessible(true);
    
    $testAmounts = [10, 50, 100, 250, 500, 1000];
    foreach ($testAmounts as $amount) {
        $severity = $method->invoke($auditEngine, $amount);
        assertTest($severity >= 1 && $severity <= 10, "Amount $$amount gives severity $severity");
    }
    
    // Test 8: Service credit calculation
    echo "<h2>💰 Service Credit Test</h2>";
    
    $reflection = new ReflectionClass($auditEngine);
    $method = $reflection->getMethod('calculateServiceCredit');
    $method->setAccessible(true);
    
    $testCases = [
        ['days' => 0.5, 'amount' => 100, 'expected' => 0],
        ['days' => 1, 'amount' => 100, 'expected' => 25],
        ['days' => 2, 'amount' => 100, 'expected' => 50],
        ['days' => 3, 'amount' => 100, 'expected' => 100]
    ];
    
    foreach ($testCases as $case) {
        $credit = $method->invoke($auditEngine, $case['days'], $case['amount']);
        $expected = $case['expected'];
        assertTest(abs($credit - $expected) < 0.01, 
            "{$case['days']} days late => $$credit credit",
            "Expected $$expected"
        );
    }
    
    // Summary
    echo "<div style='background: white; padding: 15px; border-radius: 8px; margin-top: 20px;'>";
    echo "<h2>📋 Test Summary</h2>";
    echo "<p>Tests Run: <strong>$tests_run</strong></p>";
    echo "<p>Tests Passed: <strong>$tests_passed</strong></p>";
    echo "<p>Tests Failed: <strong>" . ($tests_run - $tests_passed) . "</strong></p>";
    echo "<p>Success Rate: <strong>" . round(($tests_passed / $tests_run) * 100, 2) . "%</strong></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='test-fail'>❌ Critical Error: " . $e->getMessage() . "</div>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</body></html>";