<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/inc/storage.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/audit_log.php';

// Log before destroying session
if (isset($_SESSION['user'])) {
    audit_log(AUDIT_LOGOUT, ['username' => $_SESSION['user']['username'] ?? 'unknown']);
}

logout_user();
header('Location: /avantguard/login.php');
exit;
