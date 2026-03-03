<?php
declare(strict_types=1);

const APP_NAME = 'AVANT-GUARD VIRTUAL ASSISTANCE SERVICES';
const STORAGE_MODE = 'mysql';  // Using MySQL database

$__dataDir = getenv('AVANTGUARD_DATA_DIR');

if ($__dataDir && is_string($__dataDir)) {
    define('DATA_DIR', rtrim($__dataDir, DIRECTORY_SEPARATOR));
} else {
    define('DATA_DIR', realpath(__DIR__ . '/../data'));
}

if (!DATA_DIR) {
    die('Data directory not found.');
}

// Database Configuration (Railway + Local XAMPP)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'avantguard');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>