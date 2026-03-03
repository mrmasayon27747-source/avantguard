<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/inc/storage.php';
require_once __DIR__ . '/inc/repository.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/csrf.php';

$error = null;
$ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  $code = trim($_POST['employee_code'] ?? '');

  if (strlen($username) < 3 || strlen($password) < 6) {
    $error = "Username must be 3+ chars and password 6+ chars.";
  } else {
    $existingUser = repo_find_user_by_username($username);
    if ($existingUser) {
      $error = "Username already exists.";
    }
    if (!$error) {
      $emp = repo_find_employee_by_code($code);
      if (!$emp) {
        $error = "Employee code not found. Ask admin to create your employee first.";
      } else {
        repo_create_user([
          'username' => $username,
          'password_hash' => password_hash($password, PASSWORD_DEFAULT),
          'role' => 'worker',
          'employee_id' => $emp['id']
        ]);
        $ok = "Account created. You can now log in.";
      }
    }
  }
}

head_html('Sign up');
?>
<div class="container">
  <div class="card" style="max-width:520px; margin:30px auto;">
    <h2 class="login-title">Create Worker Account</h2>
    <p>Use the employee code provided by the admin to link your account.</p>

    <?php if ($error): ?>
      <div class="notice" style="border-color:rgba(255,45,85,.35); background:rgba(255,45,85,.10);">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>
    <?php if ($ok): ?><div class="notice"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

      <label>Employee Code</label>
      <input name="employee_code" placeholder="e.g. EMP001" required>

      <label>Username</label>
      <input name="username" required data-input="alphanumeric">

      <label>Password</label>
      <input type="password" name="password" required>

      <div style="margin-top:14px; display:flex; gap:10px;">
        <button class="btn" type="submit">Create Account</button>
        <a class="btn secondary" href="/avantguard/login.php">Back to Login</a>
      </div>
    </form>
  </div>
</div>
<?php foot_html(); ?>
