<?php
// api/audit.php - Fixed version with proper JSON output

require_once '../src/bootstrap.php';
require_once '../src/Audit/AuditEngine.php';

use SmartShip\Audit\AuditEngine;

// Set JSON header first
header('Content-Type: application/json');

// Error handling to catch any PHP notices/warnings
try {
    // Check if database connection works
    if (!function_exists('getDatabaseConnection')) {
        throw new Exception('Database connection function not found');
    }
    
    $pdo = getDatabaseConnection();
    $auditEngine = new AuditEngine($pdo);
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'dashboard':
            $metrics = $auditEngine->getDashboardMetrics();
            echo json_encode([
                'success' => true,
                'data' => $metrics
            ]);
            break;
            
        case 'audit_single':
            $shipmentId = isset($_GET['shipment_id']) ? (int)$_GET['shipment_id'] : 0;
            if (!$shipmentId) {
                throw new Exception('Shipment ID required');
            }
            $result = $auditEngine->auditShipment($shipmentId);
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        case 'audit_batch':
            // Get 5 random shipments
            $shipments = $pdo->query("SELECT id FROM shipments ORDER BY RAND() LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
            $results = $auditEngine->auditBatch($shipments);
            echo json_encode([
                'success' => true,
                'data' => $results
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action. Use: dashboard, audit_single, or audit_batch'
            ]);
    }
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log('Audit API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Return clean JSON error
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}