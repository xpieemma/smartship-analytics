<?php
// api/test.php - Simple test endpoint

header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'API is working',
    'time' => date('Y-m-d H:i:s'),
    'php_version' => phpversion()
]);