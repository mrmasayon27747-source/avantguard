<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/database.php';

$db = get_db();

// Add notes_status to eod_reports
try {
    $db->exec('ALTER TABLE eod_reports ADD COLUMN notes_status VARCHAR(50) NULL DEFAULT NULL AFTER tasks_completed');
    echo "eod_reports: notes_status column added\n";
} catch (Exception $e) {
    echo "eod_reports: " . $e->getMessage() . "\n";
}

// Add calculated_by to payslips
try {
    $db->exec('ALTER TABLE payslips ADD COLUMN calculated_by VARCHAR(200) NULL DEFAULT NULL AFTER status');
    echo "payslips: calculated_by column added\n";
} catch (Exception $e) {
    echo "payslips: " . $e->getMessage() . "\n";
}

echo "Migration complete.\n";
