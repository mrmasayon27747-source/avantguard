<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/storage.php';
require_once __DIR__ . '/inc/repository.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/csrf.php';

require_login();
$u = current_user();

$error = null;
$ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $current = $_POST['current_password'] ?? '';
  $new = $_POST['new_password'] ?? '';
  $confirm = $_POST['confirm_password'] ?? '';

  if (strlen($new) < 8) {
    $error = "New password must be at least 8 characters.";
  } elseif ($new !== $confirm) {
    $error = "Passwords do not match.";
  } else {
    $users = repo_users();
    $found = false;

    foreach ($users as $user) {
      if ((int)$user['id'] === (int)$u['id']) {
        if (!password_verify($current, (string)($user['password_hash'] ?? ''))) {
          $error = "Current password is incorrect.";
          break;
        }

        repo_update_user((int)$u['id'], [
          'password_hash' => password_hash($new, PASSWORD_DEFAULT),
          'must_change_password' => 0
        ]);

        // Update session immediately
        $_SESSION['user']['must_change_password'] = false;

        $ok = "Password updated successfully.";
        $found = true;
        break;
      }
    }

    if (!$found && !$ok && !$error) {
      $error = "User not found.";
    }
  }
}

dashboard_start("Change Password");
?>
<div class="card">
  <h3>Change Password</h3>
  <p>For security reasons you must update your password before continuing.</p>

  <?php if ($error): ?>
    <div class="notice" style="border-color:rgba(255,45,85,.35); background:rgba(255,45,85,.10);"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($ok): ?>
    <div class="notice"><?= htmlspecialchars($ok) ?></div>
    <p style="margin-top:10px">
      <a class="btn" href="/avantguard/index.php">Continue</a>
    </p>
  <?php else: ?>
    <form method="post" class="form-grid" style="max-width: 400px;">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

      <div>
        <label>Current password</label>
        <input type="password" name="current_password" required>
      </div>

      <div>
        <label>New password</label>
        <input type="password" name="new_password" required>
      </div>

      <div>
        <label>Confirm new password</label>
        <input type="password" name="confirm_password" required>
      </div>

      <div class="form-actions" style="display: flex; gap: 10px;">
        <button class="btn" type="submit">Update password</button>
        <a class="btn secondary" href="/avantguard/logout.php">Logout</a>
      </div>
    </form>
  <?php endif; ?>
</div>
<?php dashboard_end(); ?>
