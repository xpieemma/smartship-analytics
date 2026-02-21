<?php
// tests/test_database.php - Comprehensive database tests
require_once 'includes/header.php';
require_once '../src/bootstrap.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Tests</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .test-pass { background: #d4edda; color: #155724; padding: 10px; margin: 5px 0; border-radius: 4px; }
        .test-fail { background: #f8d7da; color: #721c24; padding: 10px; margin: 5px 0; border-radius: 4px; }
        .test-info { background: #d1ecf1; color: #0c5460; padding: 10px; margin: 5px 0; border-radius: 4px; }
        h1, h2 { color: #333; }
        .summary { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>📊 SmartShip Database Tests</h1>";

$tests_run = 0;
$tests_passed = 0;

function assertTest($condition, $message, $success_message = '✓ Passed') {
    global $tests_run, $tests_passed;
    $tests_run++;
    if ($condition) {
        $tests_passed++;
        echo "<div class='test-pass'>✅ $message - $success_message</div>";
    } else {
        echo "<div class='test-fail'>❌ $message - FAILED</div>";
    }
}

try {
    // Test 1: Database connection
    echo "<h2>🔌 Database Connection Tests</h2>";
    
    $pdo = getDatabaseConnection();
    assertTest($pdo instanceof PDO, "Database connection", "Connected successfully");
    
    // Test 2: Database selection
    $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
    assertTest($dbName == 'smartship', "Using correct database", "Using 'smartship' database");
    
    // Test 3: Tables exist
    echo "<h2>📋 Table Structure Tests</h2>";
    
    $expectedTables = ['shipping_lanes', 'shipments', 'invoices', 'audit_exceptions', 'audit_summary_daily'];
    $existingTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($expectedTables as $table) {
        assertTest(in_array($table, $existingTables), "Table '$table' exists");
    }
    
    // Test 4: Table structures
    echo "<h2>🏗️ Table Schema Tests</h2>";
    
    // Check shipping_lanes structure
    $columns = $pdo->query("DESCRIBE shipping_lanes")->fetchAll(PDO::FETCH_COLUMN);
    $expectedLaneColumns = ['id', 'origin', 'destination', 'base_rate', 'fuel_surcharge_percent', 'transit_days', 'carrier_code', 'is_active', 'created_at', 'updated_at'];
    foreach ($expectedLaneColumns as $col) {
        assertTest(in_array($col, $columns), "shipping_lanes has column '$col'");
    }
    
    // Check audit_exceptions has JSON column
    $jsonCheck = $pdo->query("SHOW COLUMNS FROM audit_exceptions WHERE Field = 'details'")->fetch();
    assertTest($jsonCheck && strpos($jsonCheck['Type'], 'json') !== false, "audit_exceptions.details is JSON type");
    
    // Test 5: Data existence
    echo "<h2>📦 Data Tests</h2>";
    
    $counts = [
        'shipping_lanes' => $pdo->query("SELECT COUNT(*) FROM shipping_lanes")->fetchColumn(),
        'shipments' => $pdo->query("SELECT COUNT(*) FROM shipments")->fetchColumn(),
        'invoices' => $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn(),
        'audit_exceptions' => $pdo->query("SELECT COUNT(*) FROM audit_exceptions")->fetchColumn()
    ];
    
    foreach ($counts as $table => $count) {
        assertTest($count > 0, "Data in $table", "$count records found");
    }
    
    // Test 6: Relationship integrity
    echo "<h2>🔗 Relationship Tests</h2>";
    
    // Check foreign keys
    $orphanedInvoices = $pdo->query("
        SELECT COUNT(*) FROM invoices i 
        LEFT JOIN shipments s ON i.shipment_id = s.id 
        WHERE s.id IS NULL
    ")->fetchColumn();
    assertTest($orphanedInvoices == 0, "No orphaned invoices", "$orphanedInvoices orphaned records");
    
    // Check shipments have lanes
    $orphanedShipments = $pdo->query("
        SELECT COUNT(*) FROM shipments s 
        LEFT JOIN shipping_lanes l ON s.lane_id = l.id 
        WHERE l.id IS NULL
    ")->fetchColumn();
    assertTest($orphanedShipments == 0, "No orphaned shipments", "$orphanedShipments orphaned records");
    
    // Test 7: Sample data quality
    echo "<h2>🔍 Sample Data Tests</h2>";
    
    // Get a sample exception
    $sample = $pdo->query("
        SELECT ae.*, s.tracking_number, l.origin, l.destination 
        FROM audit_exceptions ae
        JOIN shipments s ON ae.shipment_id = s.id
        JOIN shipping_lanes l ON s.lane_id = l.id
        LIMIT 1
    ")->fetch();
    
    if ($sample) {
        assertTest(!empty($sample['tracking_number']), "Exception has tracking number");
        assertTest(!empty($sample['origin']) && !empty($sample['destination']), "Exception has lane info");
        
        // Test JSON details
        $details = json_decode($sample['details'], true);
        assertTest(is_array($details), "Exception details are valid JSON");
    }
    
    // Test 8: Audit summary
    echo "<h2>📈 Summary Table Tests</h2>";
    
    $summaryCount = $pdo->query("SELECT COUNT(*) FROM audit_summary_daily")->fetchColumn();
    assertTest($summaryCount > 0, "Audit summary has data", "$summaryCount daily summaries");
    
    // Summary
    echo "<div class='summary'>";
    echo "<h2>📋 Test Summary</h2>";
    echo "<p>Tests Run: <strong>$tests_run</strong></p>";
    echo "<p>Tests Passed: <strong>$tests_passed</strong></p>";
    echo "<p>Tests Failed: <strong>" . ($tests_run - $tests_passed) . "</strong></p>";
    echo "<p>Success Rate: <strong>" . round(($tests_passed / $tests_run) * 100, 2) . "%</strong></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='test-fail'>❌ Critical Error: " . $e->getMessage() . "</div>";
}

echo "</body></html>";