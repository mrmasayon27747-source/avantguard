<?php
declare(strict_types=1);
require_once __DIR__ . '/storage.php';

function current_user(): ?array {
  return $_SESSION['user'] ?? null;
}

function enforce_password_change(): void {
  $u = current_user();
  if (!$u) return;

  $must = (bool)($u['must_change_password'] ?? false);

  if ($must) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    // Allow the change password screen + logout
    if (strpos($uri, '/avantguard/change_password.php') === false && strpos($uri, '/vanguard_priority1/logout.php') === false) {
      header('Location: /avantguard/change_password.php');
      exit;
    }
  }
}

function require_login(): void
{
    if (empty($_SESSION['user'])) {
        header('Location: /avantguard/login.php');
        exit;
    }

    // Force password change everywhere
    if (!empty($_SESSION['user']['must_change_password'])
        && basename($_SERVER['PHP_SELF']) !== 'change_password.php'
    ) {
        header('Location: /avantguard/change_password.php');
        exit;
    }
}


function require_role(string $role): void {
  require_login();
  $u = current_user();
  if (($u['role'] ?? '') !== $role) {
    header('Location: /avantguard/index.php');
    exit;
  }
}

function login_user(array $user): void {
  session_regenerate_id(true);
  $_SESSION['user'] = [
    'id' => (int)$user['id'],
    'username' => $user['username'],
    'role' => $user['role'],
    'employee_id' => $user['employee_id'] ?? null,
    'must_change_password' => (bool)($user['must_change_password'] ?? false)
  ];
}

function logout_user(): void {
  $_SESSION = [];
  session_destroy();
}
