<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/inc/auth.php';

require_login();
$u = current_user();

if ($u['role'] === 'admin') {
  header('Location: /avantguard/admin/dashboard.php');
} else {
  header('Location: /avantguard/worker/dashboard.php');
}
exit;
