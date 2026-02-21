<?php
// api/run-audit.php - Test the audit engine

require_once '../src/bootstrap.php';
require_once '../src/Audit/AuditEngine.php';

use SmartShip\Audit\AuditEngine;

header('Content-Type: application/json');

try {
    $pdo = getDatabaseConnection();
    $auditEngine = new AuditEngine($pdo);
    
    $action = $_GET['action'] ?? 'dashboard';
    
    switch ($action) {
        case 'dashboard':
            $metrics = $auditEngine->getDashboardMetrics();
            echo json_encode(['success' => true, 'data' => $metrics], JSON_PRETTY_PRINT);
            break;
            
        case 'audit_single':
            $shipmentId = $_GET['shipment_id'] ?? 0;
            if (!$shipmentId) {
                throw new Exception('Shipment ID required');
            }
            $result = $auditEngine->auditShipment((int)$shipmentId);
            echo json_encode(['success' => true, 'data' => $result], JSON_PRETTY_PRINT);
            break;
            
        case 'audit_batch':
            // Get 5 random shipments
            $shipments = $pdo->query("SELECT id FROM shipments ORDER BY RAND() LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
            $results = $auditEngine->auditBatch($shipments);
            echo json_encode(['success' => true, 'data' => $results], JSON_PRETTY_PRINT);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}