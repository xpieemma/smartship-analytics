<?php
// tests/includes/header.php
?>
<style>
    .test-nav {
        background: white;
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }
    .test-nav a {
        padding: 8px 16px;
        background: #f0f0f0;
        color: #333;
        text-decoration: none;
        border-radius: 4px;
        transition: all 0.2s;
    }
    .test-nav a:hover {
        background: #2563eb;
        color: white;
    }
    .test-nav a.active {
        background: #2563eb;
        color: white;
    }
</style>
<div class="test-nav">
    <a href="index.php">🏠 Home</a>
    <a href="test_database.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'test_database.php' ? 'active' : ''; ?>">📊 Database</a>
    <a href="test_api.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'test_api.php' ? 'active' : ''; ?>">🌐 API</a>
    <a href="test_audit_engine.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'test_audit_engine.php' ? 'active' : ''; ?>">⚙️ Audit Engine</a>
    <a href="test_frontend.html">🎨 Frontend</a>
    <a href="../index.html">📊 Main Dashboard</a>
</div>