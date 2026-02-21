<?php
// src/bootstrap.php - Central configuration

declare(strict_types=1);

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Timezone
date_default_timezone_set('America/New_York');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'smartship');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Autoloader (simple PSR-4 style)
spl_autoload_register(function ($class) {
    // Project-specific namespace prefix
    $prefix = 'SmartShip\\';
    $base_dir = __DIR__ . '/';
    
    // Check if class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Get relative class name
    $relative_class = substr($class, $len);
    
    // Replace namespace separators with directory separators
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // Load file if exists
    if (file_exists($file)) {
        require $file;
    }
});

// Database connection function
function getDatabaseConnection(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=%s",
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );
        
        try {
            $pdo = new PDO(
                $dsn,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_FOUND_ROWS => true
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new RuntimeException("Database connection failed. Check error logs.");
        }
    }
    
    return $pdo;
}

// Helper functions
function formatMoney(float $amount): string {
    return '$' . number_format($amount, 2);
}

function formatPercent(float $value): string {
    return number_format($value, 1) . '%';
}

function generateTrackingNumber(): string {
    return strtoupper(substr(md5(uniqid()), 0, 12));
}