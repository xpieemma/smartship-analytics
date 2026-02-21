<?php
// database/seed.php - Run this to populate your database

require_once __DIR__ . '/../src/bootstrap.php';

class DatabaseSeeder {
    private $pdo;
    private $carriers = ['FEDEX', 'UPS', 'DHL', 'USPS'];
    private $cities = [
        'New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix',
        'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'San Jose',
        'Austin', 'Jacksonville', 'Fort Worth', 'Columbus', 'Charlotte'
    ];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function run() {
        try {
            echo "🌱 Starting database seeding...\n\n";
            
            // Clear existing data (optional - be careful!)
            $this->cleanDatabase();
            
            $this->seedShippingLanes();
            $this->seedShipments();
            $this->seedInvoices();
            $this->seedAuditExceptions();
            $this->updateAuditSummary();
            
            echo "\n✅ Database seeding completed successfully!\n";
            
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
            echo "   File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
        }
    }
    
    private function cleanDatabase() {
        echo "Cleaning existing data...\n";
        
        // Disable foreign key checks temporarily
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        // Truncate tables in correct order
        $this->pdo->exec("TRUNCATE TABLE audit_exceptions");
        $this->pdo->exec("TRUNCATE TABLE invoices");
        $this->pdo->exec("TRUNCATE TABLE shipments");
        $this->pdo->exec("TRUNCATE TABLE shipping_lanes");
        $this->pdo->exec("TRUNCATE TABLE audit_summary_daily");
        
        // Re-enable foreign key checks
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        echo "   Database cleaned\n";
    }
    
    private function seedShippingLanes() {
        echo "Creating shipping lanes...\n";
        
        $sql = "INSERT INTO shipping_lanes (origin, destination, base_rate, fuel_surcharge_percent, transit_days, carrier_code) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        
        $lanes = [];
        $combinations = []; // Track used combinations
        
        for ($i = 0; $i < 20; $i++) {
            do {
                $origin = $this->cities[array_rand($this->cities)];
                $destination = $this->cities[array_rand($this->cities)];
                $carrier = $this->carriers[array_rand($this->carriers)];
                $key = $origin . '|' . $destination . '|' . $carrier;
            } while ($destination === $origin || isset($combinations[$key]));
            
            $combinations[$key] = true;
            
            $baseRate = rand(150, 800) + (rand(0, 99) / 100);
            $fuelSurcharge = rand(10, 25);
            $transitDays = rand(2, 7);
            
            $stmt->execute([$origin, $destination, $baseRate, $fuelSurcharge, $transitDays, $carrier]);
            $lanes[] = $this->pdo->lastInsertId();
        }
        
        echo "   Created " . count($lanes) . " shipping lanes\n";
    }
    
    // private function seedShipments() {
    //     echo "Creating shipments with realistic patterns...\n";
        
    //     // Get all lane IDs
    //     $lanes = $this->pdo->query("SELECT id FROM shipping_lanes")->fetchAll(PDO::FETCH_COLUMN);
        
    //     if (empty($lanes)) {
    //         throw new Exception("No shipping lanes found. Run seedShippingLanes first.");
    //     }
        
    //     $sql = "INSERT INTO shipments (lane_id, tracking_number, weight, volume, declared_value, expected_delivery, actual_delivery, status) 
    //             VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    //     $stmt = $this->pdo->prepare($sql);
        
    //     $shipments = [];
    //     $usedTrackings = [];
    //     $statuses = ['delivered', 'delivered', 'delivered', 'delayed', 'exception']; // Weighted toward delivered
        
    //     for ($i = 1; $i <= 50; $i++) {
    //         $laneId = $lanes[array_rand($lanes)];
            
    //         // Generate unique tracking number
    //         do {
    //             $tracking = strtoupper(substr(md5(uniqid() . $i . rand()), 0, 12));
    //         } while (in_array($tracking, $usedTrackings));
            
    //         $usedTrackings[] = $tracking;
            
    //         $weight = rand(10, 500) + (rand(0, 99) / 100);
    //         $volume = $weight * (rand(80, 120) / 100);
    //         $value = $weight * rand(5, 20);
            
    //         // Create dates within last 30 days
    //         $createdDays = rand(1, 30);
    //         $expectedDate = date('Y-m-d', strtotime("-$createdDays days +" . rand(2, 5) . " days"));
    //         $createdDate = date('Y-m-d H:i:s', strtotime("-$createdDays days " . rand(0, 23) . " hours"));
    //         $sql = "INSERT INTO shipments (lane_id, tracking_number, weight, volume, declared_value, expected_delivery, actual_delivery, status, created_at) 
    //     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    //     $stmt->execute([$laneId, $tracking, $weight, $volume, $value, $expectedDate, $actualDate, $status, $createdDate]);
    //         $status = $statuses[array_rand($statuses)];
    //         $actualDate = null;
            
    //         if ($status === 'delivered') {
    //             $actualDate = date('Y-m-d', strtotime($expectedDate . ' -' . rand(0, 2) . ' days'));
    //         } elseif ($status === 'delayed') {
    //             $actualDate = date('Y-m-d', strtotime($expectedDate . ' +' . rand(1, 5) . ' days'));
    //         }
            
    //         $stmt->execute([$laneId, $tracking, $weight, $volume, $value, $expectedDate, $actualDate, $status]);
    //         $shipments[] = $this->pdo->lastInsertId();
    //     }
        
    //     echo "   Created " . count($shipments) . " shipments\n";
    // }
    private function seedShipments() {
    echo "Creating shipments with realistic patterns...\n";
    
    // Get all lane IDs
    $lanes = $this->pdo->query("SELECT id FROM shipping_lanes")->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($lanes)) {
        throw new Exception("No shipping lanes found. Run seedShippingLanes first.");
    }
    
    // Updated SQL to include created_at
    $sql = "INSERT INTO shipments (lane_id, tracking_number, weight, volume, declared_value, expected_delivery, actual_delivery, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $this->pdo->prepare($sql);
    
    $shipments = [];
    $usedTrackings = [];
    $statuses = ['delivered', 'delivered', 'delivered', 'delayed', 'exception']; // Weighted toward delivered
    
    for ($i = 1; $i <= 50; $i++) {
        $laneId = $lanes[array_rand($lanes)];
        
        // Generate unique tracking number
        do {
            $tracking = strtoupper(substr(md5(uniqid() . $i . rand()), 0, 12));
        } while (in_array($tracking, $usedTrackings));
        
        $usedTrackings[] = $tracking;
        
        $weight = rand(10, 500) + (rand(0, 99) / 100);
        $volume = $weight * (rand(80, 120) / 100);
        $value = $weight * rand(5, 20);
        
        // Create dates within last 30 days
        $createdDays = rand(1, 30);
        $expectedDate = date('Y-m-d', strtotime("-$createdDays days +" . rand(2, 5) . " days"));
        $createdDate = date('Y-m-d H:i:s', strtotime("-$createdDays days " . rand(0, 23) . " hours"));
        
        $status = $statuses[array_rand($statuses)];
        $actualDate = null;
        
        if ($status === 'delivered') {
            $actualDate = date('Y-m-d', strtotime($expectedDate . ' -' . rand(0, 2) . ' days'));
        } elseif ($status === 'delayed') {
            $actualDate = date('Y-m-d', strtotime($expectedDate . ' +' . rand(1, 5) . ' days'));
        }
        
        // Execute with all 9 parameters
        $stmt->execute([
            $laneId, 
            $tracking, 
            $weight, 
            $volume, 
            $value, 
            $expectedDate, 
            $actualDate, 
            $status,
            $createdDate  // Added created_at
        ]);
        
        $shipments[] = $this->pdo->lastInsertId();
    }
    
    echo "   Created " . count($shipments) . " shipments with spread dates\n";
}
    
    private function seedInvoices() {
        echo "Creating carrier invoices...\n";
        
        // Get all shipments
        $shipments = $this->pdo->query("SELECT s.*, l.base_rate FROM shipments s JOIN shipping_lanes l ON s.lane_id = l.id")->fetchAll();
        
        if (empty($shipments)) {
            throw new Exception("No shipments found. Run seedShipments first.");
        }
        
        $sql = "INSERT INTO invoices (shipment_id, invoice_number, billed_weight, billed_amount, fuel_surcharge, additional_charges, invoice_date, due_date, payment_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        
        $usedInvoiceNumbers = [];
        $invoiceCount = 0;
        
        foreach ($shipments as $shipment) {
            // Generate unique invoice number
            do {
                $year = date('Y');
                $randomNum = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $invoiceNumber = 'INV-' . $year . '-' . $randomNum;
            } while (in_array($invoiceNumber, $usedInvoiceNumbers));
            
            $usedInvoiceNumbers[] = $invoiceNumber;
            
            // Sometimes carriers overbill (80% accurate, 20% errors)
            $accuracy = rand(1, 100);
            
            if ($accuracy <= 80) {
                // Correct billing
                $billedWeight = $shipment['weight'];
                $billedAmount = $shipment['base_rate'] + ($shipment['base_rate'] * 0.15); // Base + 15% fuel
            } else {
                // Errors! Overbilling by 10-30%
                $billedWeight = $shipment['weight'] * (1 + (rand(10, 30) / 100));
                $billedAmount = ($shipment['base_rate'] * 1.5) + rand(25, 100);
            }
            
            $fuelSurcharge = $billedAmount * 0.15;
            $invoiceDate = date('Y-m-d', strtotime($shipment['created_at'] . ' +' . rand(1, 3) . ' days'));
            $dueDate = date('Y-m-d', strtotime($invoiceDate . ' +30 days'));
            
            // Additional charges (sometimes)
            $additional = null;
            if (rand(1, 100) <= 30) {
                $additional = json_encode([
                    'residential_fee' => rand(0, 1) ? 15.50 : 0,
                    'weekend_delivery' => rand(0, 1) ? 25.00 : 0,
                    'signature_required' => rand(0, 1) ? 8.75 : 0
                ]);
            }
            
            try {
                $stmt->execute([
                    $shipment['id'],
                    $invoiceNumber,
                    $billedWeight,
                    $billedAmount,
                    $fuelSurcharge,
                    $additional,
                    $invoiceDate,
                    $dueDate,
                    rand(1, 100) <= 90 ? 'pending' : 'paid'
                ]);
                $invoiceCount++;
                
            } catch (PDOException $e) {
                echo "   Warning: Could not create invoice for shipment {$shipment['id']}: " . $e->getMessage() . "\n";
            }
        }
        
        echo "   Created $invoiceCount unique invoices\n";
    }
    
    // private function seedAuditExceptions() {
    //     echo "Generating audit exceptions...\n";
        
    //     // Get shipments with their invoices
    //     $sql = "SELECT s.*, i.billed_weight, i.billed_amount, i.fuel_surcharge, l.base_rate 
    //             FROM shipments s 
    //             JOIN invoices i ON s.id = i.shipment_id 
    //             JOIN shipping_lanes l ON s.lane_id = l.id
    //             WHERE s.status IN ('delayed', 'exception') OR RAND() < 0.3"; // 30% random exceptions
        
    //     $shipments = $this->pdo->query($sql)->fetchAll();
        
    //     if (empty($shipments)) {
    //         echo "   No shipments found for exceptions\n";
    //         return;
    //     }
        
    //     $sql = "INSERT INTO audit_exceptions (shipment_id, exception_type, severity_score, details, potential_savings, status) 
    //             VALUES (?, ?, ?, ?, ?, ?)";
    //     $stmt = $this->pdo->prepare($sql);
        
    //     $exceptionCount = 0;
        
    //     foreach ($shipments as $shipment) {
    //         $exceptions = [];
            
    //         // Check for weight discrepancy
    //         if (isset($shipment['billed_weight']) && isset($shipment['weight']) && $shipment['weight'] > 0) {
    //             $weightDiff = abs($shipment['billed_weight'] - $shipment['weight']) / $shipment['weight'];
    //             if ($weightDiff > 0.1) {
    //                 $overcharge = ($shipment['billed_weight'] - $shipment['weight']) * ($shipment['base_rate'] / 100);
    //                 if ($overcharge > 0) {
    //                     $exceptions[] = [
    //                         'type' => 'weight_discrepancy',
    //                         'severity' => min(10, ceil($overcharge / 50)),
    //                         'details' => json_encode([
    //                             'actual_weight' => $shipment['weight'],
    //                             'billed_weight' => $shipment['billed_weight'],
    //                             'difference' => round($shipment['billed_weight'] - $shipment['weight'], 2),
    //                             'rate_applied' => $shipment['base_rate'],
    //                             'audit_notes' => 'Billed weight exceeds actual by ' . round(($shipment['billed_weight'] / $shipment['weight'] - 1) * 100, 1) . '%'
    //                         ]),
    //                         'savings' => max(0, $overcharge)
    //                     ];
    //                 }
    //             }
    //         }
            
    //         // Check for late delivery
    //         if (!empty($shipment['actual_delivery']) && !empty($shipment['expected_delivery']) && 
    //             $shipment['actual_delivery'] > $shipment['expected_delivery']) {
                
    //             $daysLate = (strtotime($shipment['actual_delivery']) - strtotime($shipment['expected_delivery'])) / 86400;
    //             if ($daysLate > 0) {
    //                 $exceptions[] = [
    //                     'type' => 'late_delivery',
    //                     'severity' => min(10, ceil($daysLate * 2)),
    //                     'details' => json_encode([
    //                         'expected' => $shipment['expected_delivery'],
    //                         'actual' => $shipment['actual_delivery'],
    //                         'days_late' => round($daysLate, 1),
    //                         'service_level' => 'Standard',
    //                         'impact' => $daysLate > 3 ? 'Critical - Customer impact' : 'Minor delay'
    //                     ]),
    //                     'savings' => $daysLate > 3 ? 75.00 : 25.00
    //                 ];
    //             }
    //         }
            
    //         // Check for rate abuse (randomly)
    //         if (rand(1, 100) <= 15 && isset($shipment['billed_amount']) && isset($shipment['base_rate'])) {
    //             if ($shipment['billed_amount'] > $shipment['base_rate'] * 1.5) {
    //                 $exceptions[] = [
    //                     'type' => 'rate_abuse',
    //                     'severity' => 8,
    //                     'details' => json_encode([
    //                         'expected_rate' => $shipment['base_rate'],
    //                         'billed_rate' => round($shipment['billed_amount'], 2),
    //                         'difference' => round($shipment['billed_amount'] - $shipment['base_rate'], 2),
    //                         'contract_section' => 'Section 4.2 - Fuel surcharge cap',
    //                         'notes' => 'Applied incorrect class rating'
    //                     ]),
    //                     'savings' => max(0, $shipment['billed_amount'] - $shipment['base_rate'])
    //                 ];
    //             }
    //         }
            
    //         // Save exceptions
    //         foreach ($exceptions as $ex) {
    //             try {
    //                 $stmt->execute([
    //                     $shipment['id'],
    //                     $ex['type'],
    //                     $ex['severity'],
    //                     $ex['details'],
    //                     $ex['savings'],
    //                     rand(1, 100) <= 70 ? 'new' : 'reviewed'
    //                 ]);
    //                 $exceptionCount++;
    //             } catch (PDOException $e) {
    //                 // Skip if exception already exists
    //             }
    //         }
    //     }
        
    //     echo "   Created $exceptionCount audit exceptions\n";
    // }
    private function seedAuditExceptions() {
    echo "Generating audit exceptions with spread dates...\n";
    
    // Get shipments with their invoices
    $sql = "SELECT s.*, i.billed_weight, i.billed_amount, i.fuel_surcharge, l.base_rate 
            FROM shipments s 
            JOIN invoices i ON s.id = i.shipment_id 
            JOIN shipping_lanes l ON s.lane_id = l.id
            WHERE s.status IN ('delayed', 'exception') OR RAND() < 0.3"; // 30% random exceptions
    
    $shipments = $this->pdo->query($sql)->fetchAll();
    
    if (empty($shipments)) {
        echo "   No shipments found for exceptions\n";
        return;
    }
    
    $sql = "INSERT INTO audit_exceptions (shipment_id, exception_type, severity_score, details, potential_savings, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $this->pdo->prepare($sql);
    
    $exceptionCount = 0;
    
    foreach ($shipments as $shipment) {
        $exceptions = [];
        
        // Check for weight discrepancy
        if (isset($shipment['billed_weight']) && isset($shipment['weight']) && $shipment['weight'] > 0) {
            $weightDiff = abs($shipment['billed_weight'] - $shipment['weight']) / $shipment['weight'];
            if ($weightDiff > 0.1) {
                $overcharge = ($shipment['billed_weight'] - $shipment['weight']) * ($shipment['base_rate'] / 100);
                if ($overcharge > 0) {
                    $exceptions[] = [
                        'type' => 'weight_discrepancy',
                        'severity' => min(10, ceil($overcharge / 50)),
                        'details' => json_encode([
                            'actual_weight' => $shipment['weight'],
                            'billed_weight' => $shipment['billed_weight'],
                            'difference' => round($shipment['billed_weight'] - $shipment['weight'], 2),
                            'rate_applied' => $shipment['base_rate'],
                            'audit_notes' => 'Billed weight exceeds actual by ' . round(($shipment['billed_weight'] / $shipment['weight'] - 1) * 100, 1) . '%'
                        ]),
                        'savings' => max(0, $overcharge)
                    ];
                }
            }
        }
        
        // Check for late delivery
        if (!empty($shipment['actual_delivery']) && !empty($shipment['expected_delivery']) && 
            $shipment['actual_delivery'] > $shipment['expected_delivery']) {
            
            $daysLate = (strtotime($shipment['actual_delivery']) - strtotime($shipment['expected_delivery'])) / 86400;
            if ($daysLate > 0) {
                $exceptions[] = [
                    'type' => 'late_delivery',
                    'severity' => min(10, ceil($daysLate * 2)),
                    'details' => json_encode([
                        'expected' => $shipment['expected_delivery'],
                        'actual' => $shipment['actual_delivery'],
                        'days_late' => round($daysLate, 1),
                        'service_level' => 'Standard',
                        'impact' => $daysLate > 3 ? 'Critical - Customer impact' : 'Minor delay'
                    ]),
                    'savings' => $daysLate > 3 ? 75.00 : 25.00
                ];
            }
        }
        
        // Check for rate abuse (randomly)
        if (rand(1, 100) <= 15 && isset($shipment['billed_amount']) && isset($shipment['base_rate'])) {
            if ($shipment['billed_amount'] > $shipment['base_rate'] * 1.5) {
                $exceptions[] = [
                    'type' => 'rate_abuse',
                    'severity' => 8,
                    'details' => json_encode([
                        'expected_rate' => $shipment['base_rate'],
                        'billed_rate' => round($shipment['billed_amount'], 2),
                        'difference' => round($shipment['billed_amount'] - $shipment['base_rate'], 2),
                        'contract_section' => 'Section 4.2 - Fuel surcharge cap',
                        'notes' => 'Applied incorrect class rating'
                    ]),
                    'savings' => max(0, $shipment['billed_amount'] - $shipment['base_rate'])
                ];
            }
        }
        
        // Save exceptions with spread dates
        foreach ($exceptions as $ex) {
            try {
                // Generate a random date within the last 30 days
                $daysAgo = rand(1, 30);
                $randomDate = date('Y-m-d H:i:s', strtotime("-$daysAgo days " . rand(0, 23) . " hours " . rand(0, 59) . " minutes"));
                
                $stmt->execute([
                    $shipment['id'],
                    $ex['type'],
                    $ex['severity'],
                    $ex['details'],
                    $ex['savings'],
                    rand(1, 100) <= 70 ? 'new' : 'reviewed',
                    $randomDate  // Use random date instead of current timestamp
                ]);
                $exceptionCount++;
            } catch (PDOException $e) {
                // Skip if exception already exists
            }
        }
    }
    
    echo "   Created $exceptionCount audit exceptions with dates spread across last 30 days\n";
}
    
    private function updateAuditSummary() {
        echo "Updating dashboard summary...\n";
        
        // Clear existing summary
        $this->pdo->exec("DELETE FROM audit_summary_daily");
        
        // Calculate daily summary for the last 30 days
        $summaryCount = 0;
        
        for ($i = 0; $i < 30; $i++) {
            $date = date('Y-m-d', strtotime("-$i days"));
            
            // Get summary data
            $sql = "SELECT 
                    COUNT(DISTINCT s.id) as shipments,
                    COUNT(DISTINCT ae.id) as exceptions,
                    COALESCE(SUM(ae.potential_savings), 0) as savings,
                    COALESCE(SUM(i.billed_amount), 0) as spend
                    FROM shipments s
                    LEFT JOIN invoices i ON s.id = i.shipment_id
                    LEFT JOIN audit_exceptions ae ON s.id = ae.shipment_id
                    WHERE DATE(s.created_at) = :date";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['date' => $date]);
            $data = $stmt->fetch();
            
            if ($data['shipments'] > 0) {
                // Insert summary
                $insertSql = "INSERT INTO audit_summary_daily 
                            (summary_date, total_shipments, total_exceptions, total_potential_savings, total_spend)
                            VALUES (:date, :shipments, :exceptions, :savings, :spend)";
                
                $insertStmt = $this->pdo->prepare($insertSql);
                $insertStmt->execute([
                    'date' => $date,
                    'shipments' => $data['shipments'],
                    'exceptions' => $data['exceptions'],
                    'savings' => $data['savings'],
                    'spend' => $data['spend']
                ]);
                
                $summaryCount++;
            }
        }
        
        echo "   Updated $summaryCount daily summaries\n";
    }
}

// Run the seeder
try {
    // Check if file is being included or run directly
    if (php_sapi_name() === 'cli' || isset($argv)) {
        echo "SmartShip Database Seeder\n";
        echo "========================\n\n";
        
        // Try different database connection options
        $connections = [
            ['host' => 'localhost', 'user' => 'root', 'pass' => ''],  // XAMPP default
            ['host' => 'localhost', 'user' => 'root', 'pass' => 'root'], // MAMP default
            ['host' => '127.0.0.1', 'user' => 'root', 'pass' => '']  // Alternative
        ];
        
        $connected = false;
        $pdo = null;
        
        foreach ($connections as $creds) {
            try {
                $pdo = new PDO(
                    "mysql:host={$creds['host']};dbname=smartship;charset=utf8mb4",
                    $creds['user'],
                    $creds['pass'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
                $connected = true;
                echo "✓ Connected to MySQL using {$creds['user']}@{$creds['host']}\n";
                break;
            } catch (PDOException $e) {
                // Try next connection
            }
        }
        
        if (!$connected) {
            throw new Exception("Could not connect to database. Please check your MySQL credentials.");
        }
        
        $seeder = new DatabaseSeeder($pdo);
        $seeder->run();
    }
    
} catch (Exception $e) {
    die("\n❌ Error: " . $e->getMessage() . "\n");
}