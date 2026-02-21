<?php
// src/Audit/AuditEngine.php

namespace SmartShip\Audit;

use PDO;
use RuntimeException;

class AuditEngine {
    private PDO $db;
    private array $config;
    
    // Audit rule thresholds
    private const WEIGHT_TOLERANCE = 0.10; // 10%
    private const LATE_DELIVERY_THRESHOLD = 1; // 1 day
    private const RATE_ABUSE_THRESHOLD = 0.20; // 20% over expected
    
    public function __construct(PDO $db, array $config = []) {
        $this->db = $db;
        $this->config = array_merge([
            'weight_tolerance' => self::WEIGHT_TOLERANCE,
            'late_threshold' => self::LATE_DELIVERY_THRESHOLD,
            'rate_threshold' => self::RATE_ABUSE_THRESHOLD,
            'auto_dispute' => false
        ], $config);
    }
    
    /**
     * Run full audit on a single shipment
     */
    public function auditShipment(int $shipmentId): array {
        try {
            // Get shipment with all related data
            $shipment = $this->getShipmentData($shipmentId);
            if (!$shipment) {
                throw new RuntimeException("Shipment #$shipmentId not found");
            }
            
            $exceptions = [];
            
            // Run all audit rules
            $exceptions = array_merge(
                $exceptions,
                $this->checkWeightDiscrepancy($shipment),
                $this->checkLateDelivery($shipment),
                $this->checkRateAbuse($shipment),
                $this->checkDuplicateInvoice($shipment),
                $this->checkFuelSurcharge($shipment)
            );
            
            // Save exceptions to database
            foreach ($exceptions as $exception) {
                $this->saveException($exception);
            }
            
            // Update shipment status if exceptions found
            if (!empty($exceptions)) {
                $this->updateShipmentStatus($shipmentId, 'exception');
            }
            
            return [
                'shipment_id' => $shipmentId,
                'tracking' => $shipment['tracking_number'],
                'exceptions_found' => count($exceptions),
                'exceptions' => $exceptions,
                'total_potential_savings' => array_sum(array_column($exceptions, 'potential_savings')),
                'audit_timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            error_log("Audit failed for shipment $shipmentId: " . $e->getMessage());
            throw new RuntimeException("Audit failed: " . $e->getMessage());
        }
    }
    
    /**
     * Run audit on multiple shipments
     */
    public function auditBatch(array $shipmentIds): array {
        $results = [];
        foreach ($shipmentIds as $id) {
            try {
                $results[] = $this->auditShipment((int)$id);
            } catch (\Exception $e) {
                $results[] = [
                    'shipment_id' => $id,
                    'error' => $e->getMessage()
                ];
            }
        }
        return $results;
    }
    
    /**
     * Get dashboard metrics
     */
    public function getDashboardMetrics(): array {
        // Get real-time metrics
        $sql = "
            SELECT 
                COUNT(DISTINCT s.id) as total_shipments,
                COALESCE(SUM(i.billed_amount), 0) as total_spend,
                COUNT(DISTINCT ae.id) as total_exceptions,
                COALESCE(SUM(ae.potential_savings), 0) as potential_savings,
                COUNT(DISTINCT CASE WHEN s.status = 'delayed' THEN s.id END) as delayed_shipments,
                COUNT(DISTINCT CASE WHEN s.status = 'exception' THEN s.id END) as exception_shipments,
                (
                    SELECT JSON_OBJECTAGG(exception_type, cnt)
                    FROM (
                        SELECT exception_type, COUNT(*) as cnt
                        FROM audit_exceptions
                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        GROUP BY exception_type
                    ) type_counts
                ) as exceptions_by_type
            FROM shipments s
            LEFT JOIN invoices i ON s.id = i.shipment_id
            LEFT JOIN audit_exceptions ae ON s.id = ae.shipment_id
            WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ";
        
        $metrics = $this->db->query($sql)->fetch();
        
        // Calculate percentages
        $metrics['savings_percentage'] = $metrics['total_spend'] > 0 
            ? round(($metrics['potential_savings'] / $metrics['total_spend']) * 100, 2)
            : 0;
            
        $metrics['exception_rate'] = $metrics['total_shipments'] > 0
            ? round(($metrics['total_exceptions'] / $metrics['total_shipments']) * 100, 2)
            : 0;
            
        return $metrics;
    }
    
    /**
     * Get shipment data with invoice
     */
    private function getShipmentData(int $shipmentId): ?array {
        $sql = "
            SELECT 
                s.*,
                i.id as invoice_id,
                i.billed_weight,
                i.billed_amount,
                i.fuel_surcharge,
                i.additional_charges,
                l.base_rate,
                l.origin,
                l.destination,
                l.carrier_code
            FROM shipments s
            JOIN shipping_lanes l ON s.lane_id = l.id
            LEFT JOIN invoices i ON s.id = i.shipment_id
            WHERE s.id = :id
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $shipmentId]);
        
        $data = $stmt->fetch();
        if ($data && $data['additional_charges']) {
            $data['additional_charges'] = json_decode($data['additional_charges'], true);
        }
        
        return $data ?: null;
    }
    
    /**
     * Check for weight discrepancies
     */
    private function checkWeightDiscrepancy(array $shipment): array {
        $exceptions = [];
        
        if (!isset($shipment['billed_weight']) || !isset($shipment['weight'])) {
            return $exceptions;
        }
        
        $billedWeight = (float)$shipment['billed_weight'];
        $actualWeight = (float)$shipment['weight'];
        
        if ($billedWeight > $actualWeight * (1 + $this->config['weight_tolerance'])) {
            $overcharge = ($billedWeight - $actualWeight) * ($shipment['base_rate'] / 100);
            
            $exceptions[] = [
                'shipment_id' => $shipment['id'],
                'type' => 'weight_discrepancy',
                'severity' => $this->calculateSeverity($overcharge),
                'details' => [
                    'actual_weight' => $actualWeight,
                    'billed_weight' => $billedWeight,
                    'difference' => round($billedWeight - $actualWeight, 2),
                    'tolerance_applied' => $this->config['weight_tolerance'],
                    'rate_per_lb' => $shipment['base_rate'] / 100,
                    'audit_rule' => 'weight_check_v1',
                    'confidence' => 'high'
                ],
                'potential_savings' => round($overcharge, 2)
            ];
        }
        
        return $exceptions;
    }
    
    /**
     * Check for late deliveries
     */
    private function checkLateDelivery(array $shipment): array {
        $exceptions = [];
        
        if (empty($shipment['actual_delivery']) || empty($shipment['expected_delivery'])) {
            return $exceptions;
        }
        
        $actual = strtotime($shipment['actual_delivery']);
        $expected = strtotime($shipment['expected_delivery']);
        $daysLate = ($actual - $expected) / 86400;
        
        if ($daysLate >= $this->config['late_threshold']) {
            // Calculate service credit based on carrier contracts
            $creditAmount = $this->calculateServiceCredit($daysLate, $shipment['billed_amount'] ?? 0);
            
            $exceptions[] = [
                'shipment_id' => $shipment['id'],
                'type' => 'late_delivery',
                'severity' => min(10, ceil($daysLate * 1.5)),
                'details' => [
                    'expected' => $shipment['expected_delivery'],
                    'actual' => $shipment['actual_delivery'],
                    'days_late' => round($daysLate, 1),
                    'service_level' => 'Standard Ground',
                    'credit_eligible' => $creditAmount > 0,
                    'carrier' => $shipment['carrier_code'] ?? 'Unknown'
                ],
                'potential_savings' => $creditAmount
            ];
        }
        
        return $exceptions;
    }
    
    /**
     * Check for rate abuse
     */
    private function checkRateAbuse(array $shipment): array {
        $exceptions = [];
        
        if (!isset($shipment['billed_amount']) || !isset($shipment['base_rate'])) {
            return $exceptions;
        }
        
        $expectedMax = $shipment['base_rate'] * (1 + $this->config['rate_threshold']);
        
        if ($shipment['billed_amount'] > $expectedMax) {
            $overcharge = $shipment['billed_amount'] - $expectedMax;
            
            $exceptions[] = [
                'shipment_id' => $shipment['id'],
                'type' => 'rate_abuse',
                'severity' => 8,
                'details' => [
                    'expected_rate' => $shipment['base_rate'],
                    'billed_amount' => $shipment['billed_amount'],
                    'threshold_applied' => $this->config['rate_threshold'],
                    'overcharge' => round($overcharge, 2),
                    'contract_section' => 'Pricing Agreement Section 3.2',
                    'carrier' => $shipment['carrier_code'] ?? 'Unknown'
                ],
                'potential_savings' => round($overcharge, 2)
            ];
        }
        
        return $exceptions;
    }
    
    /**
     * Check for duplicate invoices
     */
    private function checkDuplicateInvoice(array $shipment): array {
        $exceptions = [];
        
        if (!isset($shipment['invoice_id'])) {
            return $exceptions;
        }
        
        // Check for other invoices with same tracking number
        $sql = "
            SELECT COUNT(*) as duplicate_count
            FROM invoices i
            JOIN shipments s ON i.shipment_id = s.id
            WHERE s.tracking_number = :tracking
            AND i.id != :invoice_id
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tracking' => $shipment['tracking_number'],
            'invoice_id' => $shipment['invoice_id']
        ]);
        
        $result = $stmt->fetch();
        
        if ($result['duplicate_count'] > 0) {
            $exceptions[] = [
                'shipment_id' => $shipment['id'],
                'type' => 'duplicate_invoice',
                'severity' => 10,
                'details' => [
                    'tracking' => $shipment['tracking_number'],
                    'duplicate_count' => $result['duplicate_count'],
                    'current_invoice' => $shipment['invoice_id'],
                    'action_required' => 'Review for duplicate billing'
                ],
                'potential_savings' => $shipment['billed_amount'] ?? 0
            ];
        }
        
        return $exceptions;
    }
    
    /**
     * Check fuel surcharge calculations
     */
    private function checkFuelSurcharge(array $shipment): array {
        $exceptions = [];
        
        if (!isset($shipment['fuel_surcharge']) || !isset($shipment['billed_amount'])) {
            return $exceptions;
        }
        
        $expectedFuelMax = $shipment['billed_amount'] * 0.25; // Max 25% fuel surcharge
        
        if ($shipment['fuel_surcharge'] > $expectedFuelMax) {
            $exceptions[] = [
                'shipment_id' => $shipment['id'],
                'type' => 'fuel_surcharge_error',
                'severity' => 7,
                'details' => [
                    'charged' => $shipment['fuel_surcharge'],
                    'expected_max' => round($expectedFuelMax, 2),
                    'overcharge' => round($shipment['fuel_surcharge'] - $expectedFuelMax, 2),
                    'contract_rate' => '15% standard',
                    'notes' => 'Fuel surcharge exceeds contractual maximum'
                ],
                'potential_savings' => round(max(0, $shipment['fuel_surcharge'] - $expectedFuelMax), 2)
            ];
        }
        
        return $exceptions;
    }
    
    /**
     * Calculate severity score (1-10)
     */
    private function calculateSeverity(float $amount): int {
        if ($amount >= 500) return 10;
        if ($amount >= 250) return 8;
        if ($amount >= 100) return 6;
        if ($amount >= 50) return 4;
        if ($amount >= 25) return 2;
        return 1;
    }
    
    /**
     * Calculate service credit for late delivery
     */
    private function calculateServiceCredit(float $daysLate, float $billedAmount): float {
        // Simplified carrier credit rules
        if ($daysLate >= 3) return $billedAmount; // Full refund
        if ($daysLate >= 2) return $billedAmount * 0.5; // 50% refund
        if ($daysLate >= 1) return $billedAmount * 0.25; // 25% refund
        return 0;
    }
    
    /**
     * Save exception to database
     */
    private function saveException(array $exception): void {
        $sql = "
            INSERT INTO audit_exceptions 
            (shipment_id, exception_type, severity_score, details, potential_savings, status)
            VALUES (:shipment_id, :type, :severity, :details, :savings, 'new')
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'shipment_id' => $exception['shipment_id'],
            'type' => $exception['type'],
            'severity' => $exception['severity'],
            'details' => json_encode($exception['details']),
            'savings' => $exception['potential_savings']
        ]);
    }
    
    /**
     * Update shipment status
     */
    private function updateShipmentStatus(int $shipmentId, string $status): void {
        $sql = "UPDATE shipments SET status = :status WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $shipmentId, 'status' => $status]);
    }
}