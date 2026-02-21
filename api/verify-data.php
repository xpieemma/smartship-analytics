<?php
// api/verify-data.php - Check what we have in the database

require_once '../src/bootstrap.php';

header('Content-Type: application/json');

try {
    $pdo = getDatabaseConnection();
    
    // Get comprehensive stats
    $stats = [
        'summary' => [
            'shipping_lanes' => (int) $pdo->query("SELECT COUNT(*) FROM shipping_lanes")->fetchColumn(),
            'shipments' => (int) $pdo->query("SELECT COUNT(*) FROM shipments")->fetchColumn(),
            'invoices' => (int) $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn(),
            'audit_exceptions' => (int) $pdo->query("SELECT COUNT(*) FROM audit_exceptions")->fetchColumn(),
        ],
        'breakdown' => [
            'exceptions_by_type' => $pdo->query("
                SELECT 
                    exception_type,
                    COUNT(*) as count,
                    SUM(potential_savings) as total_savings,
                    AVG(severity_score) as avg_severity
                FROM audit_exceptions 
                GROUP BY exception_type
            ")->fetchAll(),
            
            'shipments_by_status' => $pdo->query("
                SELECT status, COUNT(*) as count 
                FROM shipments 
                GROUP BY status
            ")->fetchAll(),
            
            'invoices_by_status' => $pdo->query("
                SELECT payment_status, COUNT(*) as count,
                SUM(billed_amount) as total
                FROM invoices 
                GROUP BY payment_status
            ")->fetchAll(),
        ],
        'financial' => [
            'total_billed' => (float) $pdo->query("SELECT SUM(billed_amount) FROM invoices")->fetchColumn(),
            'total_potential_savings' => (float) $pdo->query("SELECT SUM(potential_savings) FROM audit_exceptions")->fetchColumn(),
            'savings_percentage' => 0,
            'top_exceptions' => $pdo->query("
                SELECT 
                    ae.*,
                    s.tracking_number,
                    l.origin,
                    l.destination
                FROM audit_exceptions ae
                JOIN shipments s ON ae.shipment_id = s.id
                JOIN shipping_lanes l ON s.lane_id = l.id
                ORDER BY ae.potential_savings DESC
                LIMIT 5
            ")->fetchAll()
        ]
    ];
    
    // Calculate savings percentage
    if ($stats['financial']['total_billed'] > 0) {
        $stats['financial']['savings_percentage'] = round(
            ($stats['financial']['total_potential_savings'] / $stats['financial']['total_billed']) * 100, 
            2
        );
    }
    
    // Get sample exception with JSON details parsed
    $sample = $pdo->query("
        SELECT 
            ae.*,
            s.tracking_number,
            l.origin,
            l.destination
        FROM audit_exceptions ae
        JOIN shipments s ON ae.shipment_id = s.id
        JOIN shipping_lanes l ON s.lane_id = l.id
        LIMIT 1
    ")->fetch();
    
    if ($sample) {
        $sample['details'] = json_decode($sample['details'], true);
        $stats['sample_exception'] = $sample;
    }
    
    // Pretty print the JSON
    echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}