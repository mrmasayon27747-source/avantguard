<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Storage Configuration (MySQL Only)
 * 
 * This file handles upload directory setup.
 * All data storage is handled via MySQL database (see repository.php).
 */

// Upload directories for file uploads (EOD reports, devotionals)
define('UPLOADS_DIR', realpath(__DIR__ . '/../uploads') ?: __DIR__ . '/../uploads');
define('EOD_UPLOADS_DIR', UPLOADS_DIR . DIRECTORY_SEPARATOR . 'eod');
define('DEVOTIONAL_UPLOADS_DIR', UPLOADS_DIR . DIRECTORY_SEPARATOR . 'devotional');

// Ensure upload directories exist
if (!is_dir(UPLOADS_DIR)) {
    mkdir(UPLOADS_DIR, 0750, true);
}
if (!is_dir(EOD_UPLOADS_DIR)) {
    mkdir(EOD_UPLOADS_DIR, 0750, true);
}
if (!is_dir(DEVOTIONAL_UPLOADS_DIR)) {
    mkdir(DEVOTIONAL_UPLOADS_DIR, 0750, true);
}
