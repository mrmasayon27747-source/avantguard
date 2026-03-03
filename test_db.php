<?php
echo "<h2>Database Connection Test</h2>";

// Test 1: Basic PHP
echo "<p>1. PHP is working</p>";

// Test 2: MySQL on port 3307
echo "<p>2. Testing MySQL on port 3307... ";
$start = microtime(true);
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3307', 'root', '', [
        PDO::ATTR_TIMEOUT => 5
    ]);
    $elapsed = round((microtime(true) - $start) * 1000);
    echo "<strong style='color:green'>Connected!</strong> ({$elapsed}ms)</p>";
    
    // Show databases
    $dbs = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Databases: " . implode(', ', $dbs) . "</p>";
} catch (Exception $e) {
    echo "<strong style='color:red'>Error:</strong> " . $e->getMessage() . "</p>";
}

// Test 3: Check phpMyAdmin config
echo "<h3>phpMyAdmin Config Check</h3>";
$configFile = 'C:/xampp/phpMyAdmin/config.inc.php';
if (file_exists($configFile)) {
    $config = file_get_contents($configFile);
    
    // Check for port setting
    if (preg_match("/\['port'\]\s*=\s*'?(\d+)'?/", $config, $m)) {
        echo "<p>Port configured: " . $m[1] . "</p>";
    } else {
        echo "<p style='color:red'>Port NOT configured in phpMyAdmin!</p>";
    }
    
    // Check host
    if (preg_match("/\['host'\]\s*=\s*'([^']+)'/", $config, $m)) {
        echo "<p>Host configured: " . $m[1] . "</p>";
    }
}

echo "<hr><p><a href='database/setup_sqlite.php'>Setup SQLite instead</a></p>";
