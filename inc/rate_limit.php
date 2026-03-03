<?php
declare(strict_types=1);
/**
 * Rate Limiting System
 * Prevents brute-force login attacks by limiting attempts per IP/username
 */

define('RATE_LIMIT_FILE', DATA_DIR . '/rate_limits.json');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes in seconds

/**
 * Get rate limit data
 */
function rate_limit_get_data(): array {
    if (!file_exists(RATE_LIMIT_FILE)) {
        return [];
    }
    $content = file_get_contents(RATE_LIMIT_FILE);
    return $content ? json_decode($content, true) ?? [] : [];
}

/**
 * Save rate limit data
 */
function rate_limit_save_data(array $data): void {
    file_put_contents(RATE_LIMIT_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * Clean up expired entries
 */
function rate_limit_cleanup(array $data): array {
    $now = time();
    foreach ($data as $key => $entry) {
        if (isset($entry['locked_until']) && $entry['locked_until'] < $now) {
            // Lockout expired, reset attempts
            unset($data[$key]);
        } elseif (isset($entry['first_attempt']) && ($now - $entry['first_attempt']) > LOCKOUT_DURATION) {
            // Old entries without lockout, clean up
            unset($data[$key]);
        }
    }
    return $data;
}

/**
 * Get a unique key for rate limiting (combines IP and username)
 */
function rate_limit_key(string $username): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return md5($ip . ':' . strtolower($username));
}

/**
 * Check if login is allowed (not rate limited)
 * Returns: ['allowed' => bool, 'remaining_attempts' => int, 'locked_until' => int|null, 'message' => string]
 */
function rate_limit_check(string $username): array {
    $data = rate_limit_get_data();
    $data = rate_limit_cleanup($data);
    $key = rate_limit_key($username);
    $now = time();
    
    if (!isset($data[$key])) {
        return [
            'allowed' => true,
            'remaining_attempts' => MAX_LOGIN_ATTEMPTS,
            'locked_until' => null,
            'message' => ''
        ];
    }
    
    $entry = $data[$key];
    
    // Check if currently locked out
    if (isset($entry['locked_until']) && $entry['locked_until'] > $now) {
        $remaining_seconds = $entry['locked_until'] - $now;
        $remaining_minutes = ceil($remaining_seconds / 60);
        return [
            'allowed' => false,
            'remaining_attempts' => 0,
            'locked_until' => $entry['locked_until'],
            'message' => "Too many failed attempts. Try again in {$remaining_minutes} minute(s)."
        ];
    }
    
    $attempts = (int)($entry['attempts'] ?? 0);
    $remaining = max(0, MAX_LOGIN_ATTEMPTS - $attempts);
    
    return [
        'allowed' => true,
        'remaining_attempts' => $remaining,
        'locked_until' => null,
        'message' => $remaining <= 2 ? "Warning: {$remaining} attempt(s) remaining before lockout." : ''
    ];
}

/**
 * Record a failed login attempt
 * Returns: ['locked' => bool, 'message' => string]
 */
function rate_limit_record_failure(string $username): array {
    $data = rate_limit_get_data();
    $data = rate_limit_cleanup($data);
    $key = rate_limit_key($username);
    $now = time();
    
    if (!isset($data[$key])) {
        $data[$key] = [
            'attempts' => 1,
            'first_attempt' => $now,
            'last_attempt' => $now
        ];
    } else {
        $data[$key]['attempts'] = ($data[$key]['attempts'] ?? 0) + 1;
        $data[$key]['last_attempt'] = $now;
    }
    
    $attempts = $data[$key]['attempts'];
    
    // Check if should lock out
    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        $data[$key]['locked_until'] = $now + LOCKOUT_DURATION;
        rate_limit_save_data($data);
        
        $minutes = LOCKOUT_DURATION / 60;
        return [
            'locked' => true,
            'message' => "Account temporarily locked due to too many failed attempts. Try again in {$minutes} minutes."
        ];
    }
    
    rate_limit_save_data($data);
    
    $remaining = MAX_LOGIN_ATTEMPTS - $attempts;
    return [
        'locked' => false,
        'message' => $remaining <= 2 ? "Warning: {$remaining} attempt(s) remaining before lockout." : ''
    ];
}

/**
 * Clear rate limit on successful login
 */
function rate_limit_clear(string $username): void {
    $data = rate_limit_get_data();
    $key = rate_limit_key($username);
    
    if (isset($data[$key])) {
        unset($data[$key]);
        rate_limit_save_data($data);
    }
}

/**
 * Get rate limit status for admin display
 */
function rate_limit_get_status(): array {
    $data = rate_limit_get_data();
    $data = rate_limit_cleanup($data);
    rate_limit_save_data($data);
    
    $now = time();
    $active_lockouts = 0;
    
    foreach ($data as $entry) {
        if (isset($entry['locked_until']) && $entry['locked_until'] > $now) {
            $active_lockouts++;
        }
    }
    
    return [
        'total_tracked' => count($data),
        'active_lockouts' => $active_lockouts
    ];
}
