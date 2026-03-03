<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/database.php';

$db = get_db();
try {
    $db->exec('ALTER TABLE eod_reports ADD COLUMN pending_concerns TEXT NULL AFTER tasks_completed');
    echo "Column 'pending_concerns' added successfully.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Column 'pending_concerns' already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
