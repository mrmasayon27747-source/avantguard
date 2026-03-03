<?php
declare(strict_types=1);
/**
 * Database Backup System
 * Creates and manages backups of JSON data files
 */

define('BACKUP_DIR', DATA_DIR . '/backups');
define('MAX_BACKUPS', 10); // Keep last 10 backups

/**
 * Ensure backup directory exists
 */
function backup_ensure_dir(): void {
    if (!is_dir(BACKUP_DIR)) {
        mkdir(BACKUP_DIR, 0755, true);
    }
}

/**
 * Get list of existing backups
 */
function backup_list(): array {
    backup_ensure_dir();
    
    $backups = [];
    $files = glob(BACKUP_DIR . '/backup_*.zip');
    
    foreach ($files as $file) {
        $filename = basename($file);
        $backups[] = [
            'filename' => $filename,
            'path' => $file,
            'size' => filesize($file),
            'size_formatted' => format_bytes(filesize($file)),
            'created' => filemtime($file),
            'created_formatted' => date('M j, Y g:i A', filemtime($file))
        ];
    }
    
    // Sort by date descending (newest first)
    usort($backups, fn($a, $b) => $b['created'] <=> $a['created']);
    
    return $backups;
}

/**
 * Format bytes to human readable
 */
function format_bytes(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 2) . ' KB';
    return round($bytes / 1048576, 2) . ' MB';
}

/**
 * Create a backup
 */
function backup_create(): array {
    backup_ensure_dir();
    
    $timestamp = date('Y-m-d_H-i-s');
    $backup_name = "backup_{$timestamp}.zip";
    $backup_path = BACKUP_DIR . '/' . $backup_name;
    
    // Files to backup
    $data_files = [
        'users.json',
        'employees.json',
        'attendance.json',
        'payslips.json',
        'deductions.json',
        'tasks.json',
        'devotionals.json',
        'eod_reports.json',
        'audit_log.json',
        'rate_limits.json'
    ];
    
    $zip = new ZipArchive();
    $result = $zip->open($backup_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    
    if ($result !== true) {
        return [
            'success' => false,
            'message' => 'Failed to create backup archive.'
        ];
    }
    
    $backed_up = [];
    foreach ($data_files as $file) {
        $full_path = DATA_DIR . '/' . $file;
        if (file_exists($full_path)) {
            $zip->addFile($full_path, $file);
            $backed_up[] = $file;
        }
    }
    
    $zip->close();
    
    // Clean up old backups
    backup_cleanup();
    
    return [
        'success' => true,
        'filename' => $backup_name,
        'path' => $backup_path,
        'files_backed_up' => $backed_up,
        'message' => 'Backup created successfully.'
    ];
}

/**
 * Clean up old backups (keep only MAX_BACKUPS)
 */
function backup_cleanup(): void {
    $backups = backup_list();
    
    if (count($backups) > MAX_BACKUPS) {
        $to_delete = array_slice($backups, MAX_BACKUPS);
        foreach ($to_delete as $backup) {
            if (file_exists($backup['path'])) {
                unlink($backup['path']);
            }
        }
    }
}

/**
 * Delete a specific backup
 */
function backup_delete(string $filename): bool {
    $path = BACKUP_DIR . '/' . basename($filename);
    
    if (file_exists($path) && strpos($path, BACKUP_DIR) === 0) {
        return unlink($path);
    }
    
    return false;
}

/**
 * Restore from backup
 */
function backup_restore(string $filename): array {
    $path = BACKUP_DIR . '/' . basename($filename);
    
    if (!file_exists($path)) {
        return [
            'success' => false,
            'message' => 'Backup file not found.'
        ];
    }
    
    $zip = new ZipArchive();
    $result = $zip->open($path);
    
    if ($result !== true) {
        return [
            'success' => false,
            'message' => 'Failed to open backup archive.'
        ];
    }
    
    // Create a pre-restore backup first
    $pre_restore = backup_create();
    
    // Extract files
    $restored = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $file = $zip->getNameIndex($i);
        $zip->extractTo(DATA_DIR, $file);
        $restored[] = $file;
    }
    
    $zip->close();
    
    return [
        'success' => true,
        'files_restored' => $restored,
        'pre_restore_backup' => $pre_restore['filename'] ?? null,
        'message' => 'Backup restored successfully.'
    ];
}

/**
 * Download backup file
 */
function backup_download(string $filename): void {
    $path = BACKUP_DIR . '/' . basename($filename);
    
    if (!file_exists($path) || strpos($path, BACKUP_DIR) !== 0) {
        http_response_code(404);
        echo 'Backup not found.';
        return;
    }
    
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}
