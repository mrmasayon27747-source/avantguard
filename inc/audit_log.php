<?php
declare(strict_types=1);
/**
 * Audit Logging System
 * Tracks important system events for security and compliance
 */

define('AUDIT_LOG_FILE', DATA_DIR . '/audit_log.json');
define('AUDIT_LOG_MAX_ENTRIES', 1000); // Keep last 1000 entries

/**
 * Log event types
 */
const AUDIT_LOGIN_SUCCESS = 'login_success';
const AUDIT_LOGIN_FAILURE = 'login_failure';
const AUDIT_LOGIN_LOCKOUT = 'login_lockout';
const AUDIT_LOGOUT = 'logout';
const AUDIT_PASSWORD_CHANGE = 'password_change';
const AUDIT_USER_CREATE = 'user_create';
const AUDIT_USER_UPDATE = 'user_update';
const AUDIT_USER_DELETE = 'user_delete';
const AUDIT_EMPLOYEE_CREATE = 'employee_create';
const AUDIT_EMPLOYEE_UPDATE = 'employee_update';
const AUDIT_EMPLOYEE_DELETE = 'employee_delete';
const AUDIT_PAYSLIP_CREATE = 'payslip_create';
const AUDIT_PAYSLIP_RELEASE = 'payslip_release';
const AUDIT_PAYSLIP_DELETE = 'payslip_delete';
const AUDIT_ATTENDANCE_CREATE = 'attendance_create';
const AUDIT_ATTENDANCE_UPDATE = 'attendance_update';
const AUDIT_ATTENDANCE_DELETE = 'attendance_delete';
const AUDIT_DEDUCTION_CREATE = 'deduction_create';
const AUDIT_DEDUCTION_UPDATE = 'deduction_update';
const AUDIT_DEDUCTION_DELETE = 'deduction_delete';

/**
 * Get audit log data
 */
function audit_log_get(): array {
    if (!file_exists(AUDIT_LOG_FILE)) {
        return [];
    }
    $content = file_get_contents(AUDIT_LOG_FILE);
    return $content ? json_decode($content, true) ?? [] : [];
}

/**
 * Save audit log data
 */
function audit_log_save(array $logs): void {
    // Keep only last N entries
    if (count($logs) > AUDIT_LOG_MAX_ENTRIES) {
        $logs = array_slice($logs, -AUDIT_LOG_MAX_ENTRIES);
    }
    file_put_contents(AUDIT_LOG_FILE, json_encode($logs, JSON_PRETTY_PRINT));
}

/**
 * Record an audit event
 */
function audit_log(string $event_type, array $details = [], ?int $user_id = null, ?string $username = null): void {
    $logs = audit_log_get();
    
    // Get current user if not specified
    if ($user_id === null && isset($_SESSION['user'])) {
        $user_id = (int)($_SESSION['user']['id'] ?? 0);
        $username = $username ?? ($_SESSION['user']['username'] ?? 'unknown');
    }
    
    $entry = [
        'id' => count($logs) + 1,
        'timestamp' => date('Y-m-d H:i:s'),
        'event_type' => $event_type,
        'user_id' => $user_id,
        'username' => $username ?? 'system',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 200),
        'details' => $details
    ];
    
    $logs[] = $entry;
    audit_log_save($logs);
}

/**
 * Get recent audit logs with optional filtering
 */
function audit_log_query(array $filters = [], int $limit = 100): array {
    $logs = audit_log_get();
    
    // Filter by event type
    if (!empty($filters['event_type'])) {
        $logs = array_filter($logs, fn($log) => $log['event_type'] === $filters['event_type']);
    }
    
    // Filter by user
    if (!empty($filters['user_id'])) {
        $logs = array_filter($logs, fn($log) => ($log['user_id'] ?? 0) === (int)$filters['user_id']);
    }
    
    // Filter by username
    if (!empty($filters['username'])) {
        $logs = array_filter($logs, fn($log) => stripos($log['username'] ?? '', $filters['username']) !== false);
    }
    
    // Filter by date range
    if (!empty($filters['date_from'])) {
        $logs = array_filter($logs, fn($log) => ($log['timestamp'] ?? '') >= $filters['date_from']);
    }
    if (!empty($filters['date_to'])) {
        $logs = array_filter($logs, fn($log) => ($log['timestamp'] ?? '') <= $filters['date_to'] . ' 23:59:59');
    }
    
    // Sort by timestamp descending (most recent first)
    usort($logs, fn($a, $b) => strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? ''));
    
    // Apply limit
    return array_slice($logs, 0, $limit);
}

/**
 * Get summary stats for audit logs
 */
function audit_log_stats(): array {
    $logs = audit_log_get();
    $today = date('Y-m-d');
    
    $stats = [
        'total_events' => count($logs),
        'today_events' => 0,
        'login_failures_today' => 0,
        'lockouts_today' => 0,
        'by_type' => []
    ];
    
    foreach ($logs as $log) {
        $type = $log['event_type'] ?? 'unknown';
        $date = substr($log['timestamp'] ?? '', 0, 10);
        
        // Count by type
        if (!isset($stats['by_type'][$type])) {
            $stats['by_type'][$type] = 0;
        }
        $stats['by_type'][$type]++;
        
        // Today's stats
        if ($date === $today) {
            $stats['today_events']++;
            
            if ($type === AUDIT_LOGIN_FAILURE) {
                $stats['login_failures_today']++;
            }
            if ($type === AUDIT_LOGIN_LOCKOUT) {
                $stats['lockouts_today']++;
            }
        }
    }
    
    return $stats;
}

/**
 * Get human-readable event type label
 */
function audit_event_label(string $type): string {
    $labels = [
        AUDIT_LOGIN_SUCCESS => 'Login Success',
        AUDIT_LOGIN_FAILURE => 'Login Failed',
        AUDIT_LOGIN_LOCKOUT => 'Account Lockout',
        AUDIT_LOGOUT => 'Logout',
        AUDIT_PASSWORD_CHANGE => 'Password Changed',
        AUDIT_USER_CREATE => 'User Created',
        AUDIT_USER_UPDATE => 'User Updated',
        AUDIT_USER_DELETE => 'User Deleted',
        AUDIT_EMPLOYEE_CREATE => 'Employee Created',
        AUDIT_EMPLOYEE_UPDATE => 'Employee Updated',
        AUDIT_EMPLOYEE_DELETE => 'Employee Deleted',
        AUDIT_PAYSLIP_CREATE => 'Payslip Created',
        AUDIT_PAYSLIP_RELEASE => 'Payslip Released',
        AUDIT_PAYSLIP_DELETE => 'Payslip Deleted',
        AUDIT_ATTENDANCE_CREATE => 'Attendance Created',
        AUDIT_ATTENDANCE_UPDATE => 'Attendance Updated',
        AUDIT_ATTENDANCE_DELETE => 'Attendance Deleted',
        AUDIT_DEDUCTION_CREATE => 'Deduction Created',
        AUDIT_DEDUCTION_UPDATE => 'Deduction Updated',
        AUDIT_DEDUCTION_DELETE => 'Deduction Deleted',
    ];
    
    return $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
}
