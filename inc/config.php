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