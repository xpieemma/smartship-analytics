<?php
// tests/index.php - Main test dashboard

echo "<!DOCTYPE html>
<html>
<head>
    <title>SmartShip Test Suite</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .test-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .test-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-decoration: none; color: inherit; display: block; transition: transform 0.2s; }
        .test-card:hover { transform: translateY(-4px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        .test-card h2 { margin-top: 0; color: #2563eb; }
        .test-card p { color: #666; }
        .status { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; margin-top: 10px; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        h1 { margin: 0; color: #1e293b; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>🧪 SmartShip Test Suite</h1>
        <p>Comprehensive tests for all components</p>
    </div>
    
    <div class='test-grid'>
        <a href='test_database.php' class='test-card'>
            <h2>📊 Database Tests</h2>
            <p>Test database connection, table structure, data integrity, and relationships</p>
            <span class='status' style='background: #2563eb; color: white;'>Run Tests →</span>
        </a>
        
        <a href='test_api.php' class='test-card'>
            <h2>🌐 API Tests</h2>
            <p>Test all API endpoints, response formats, and data validation</p>
            <span class='status' style='background: #2563eb; color: white;'>Run Tests →</span>
        </a>
        
        <a href='test_audit_engine.php' class='test-card'>
            <h2>⚙️ Audit Engine Tests</h2>
            <p>Test audit logic, exception detection, severity scoring, and batch processing</p>
            <span class='status' style='background: #2563eb; color: white;'>Run Tests →</span>
        </a>
        
        <a href='test_frontend.html' class='test-card'>
            <h2>🎨 Frontend Tests</h2>
            <p>Test JavaScript components, Chart.js, API integration, and helper functions</p>
            <span class='status' style='background: #2563eb; color: white;'>Run Tests →</span>
        </a>
    </div>
    
    <div style='margin-top: 30px; background: white; padding: 20px; border-radius: 8px;'>
        <h3>📋 Quick System Check</h3>
        <ul>
            <li>PHP Version: " . phpversion() . "</li>
            <li>Server: " . $_SERVER['SERVER_SOFTWARE'] . "</li>
            <li>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</li>
            <li>Project Path: " . __DIR__ . "</li>
        </ul>
    </div>
</body>
</html>";