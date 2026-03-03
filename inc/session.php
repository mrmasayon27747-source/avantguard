<?php
declare(strict_types=1);

// Secure session configuration
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $secure ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');

session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/avantguard',
  'domain' => '',
  'secure' => $secure,
  'httponly' => true,
  'samesite' => 'Lax'
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Session timeout (1 hour inactivity)
$timeout = 3600;
$now = time();

if (!empty($_SESSION['last_activity']) && ($now - (int)$_SESSION['last_activity']) > $timeout) {
  $_SESSION = [];
  session_destroy();
  session_start();
  $_SESSION['session_expired'] = true;
}

$_SESSION['last_activity'] = $now;
