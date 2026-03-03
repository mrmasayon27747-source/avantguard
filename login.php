<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/session.php';

require_once __DIR__ . '/inc/bootstrap.php';
bootstrap_defaults();

require_once __DIR__ . '/inc/storage.php';
require_once __DIR__ . '/inc/repository.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/csrf.php';
require_once __DIR__ . '/inc/rate_limit.php';
require_once __DIR__ . '/inc/audit_log.php';


$error = null;
$warning = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Check rate limit before attempting login
    $rate_check = rate_limit_check($username);
    if (!$rate_check['allowed']) {
        $error = $rate_check['message'];
        audit_log(AUDIT_LOGIN_LOCKOUT, ['username' => $username, 'reason' => 'rate_limited'], null, $username);
    } else {
        $user = repo_find_user_by_username($username);
        if ($user) {
            if (!isset($user['password_hash'])) {
                $error = "Account misconfigured. Contact admin.";
                rate_limit_record_failure($username);
                audit_log(AUDIT_LOGIN_FAILURE, ['username' => $username, 'reason' => 'no_password_hash'], null, $username);
            } elseif (password_verify($password, $user['password_hash'])) {
                // Successful login - clear rate limit
                rate_limit_clear($username);
                audit_log(AUDIT_LOGIN_SUCCESS, ['username' => $username], (int)$user['id'], $username);
                
                $_SESSION['user'] = $user;

                // FORCE password change if required
                if (!empty($user['must_change_password'])) {
                    header('Location: change_password.php');
                    exit;
                }

                header('Location: index.php');
                exit;
            } else {
                $result = rate_limit_record_failure($username);
                $error = "Invalid username or password.";
                if ($result['message']) {
                    $warning = $result['message'];
                }
                audit_log(AUDIT_LOGIN_FAILURE, ['username' => $username, 'reason' => 'wrong_password'], null, $username);
            }
        } else {
            $result = rate_limit_record_failure($username);
            $error = "Invalid username or password.";
            if ($result['message']) {
                $warning = $result['message'];
            }
            audit_log(AUDIT_LOGIN_FAILURE, ['username' => $username, 'reason' => 'user_not_found'], null, $username);
        }
    }
}

head_html('Login');
?>
<div class="login-page">
  <!-- Background effects -->
  <div class="bg-effects">
    <div class="grid-overlay"></div>
    <div class="glow-orb orb-1"></div>
    <div class="glow-orb orb-2"></div>
    <div class="glow-orb orb-3"></div>
    <div class="particles">
      <span></span><span></span><span></span><span></span><span></span>
      <span></span><span></span><span></span><span></span><span></span>
      <span></span><span></span><span></span><span></span><span></span>
      <span></span><span></span><span></span><span></span><span></span>
    </div>
  </div>

  <!-- Left side: branding -->
  <div class="login-left">
    <div class="login-branding">
      <div class="brand-logo">
        <img src="/avantguard/assets/logo.png" alt="WageWise" class="brand-icon">
        <span class="brand-text">WageWise</span>
      </div>
      <p class="brand-tagline">Intelligent Payroll Management<br>and Task Monitoring System</p>
    </div>
  </div>

  <!-- Right side: login form -->
  <div class="login-right">
    <div class="login-form-card">
      <h1 class="login-heading">Log In to WageWise</h1>

      <?php if ($error): ?>
        <div class="login-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($warning): ?>
        <div class="login-warning" style="background: rgba(255, 159, 10, 0.15); border: 1px solid rgba(255, 159, 10, 0.3); color: #ff9f0a; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 0.9rem;">
          <?= htmlspecialchars($warning) ?>
        </div>
      <?php endif; ?>

      <form method="post" class="login-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

        <div class="form-field">
          <label class="field-label">Your Username</label>
          <div class="field-input-wrap">
            <input type="text" name="username" required placeholder="Enter username" class="field-input" data-input="alphanumeric">
            <span class="field-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
              </svg>
            </span>
          </div>
        </div>

        <div class="form-field">
          <label class="field-label">Your Password</label>
          <div class="field-input-wrap">
            <input type="password" name="password" required placeholder="••••••••••••" class="field-input">
            <span class="field-icon toggle-password">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </span>
          </div>
        </div>

        <div class="form-options">
          <label class="remember-me">
            <input type="checkbox" name="remember">
            <span>Remember</span>
          </label>
          <a href="#" class="forgot-link">Forgotten?</a>
        </div>

        <button type="submit" class="login-submit">Log In</button>

        <div class="signup-prompt">
          <span>Don't have an account?</span>
          <a href="/avantguard/signup.php" class="signup-link">Sign Up</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php foot_html(); ?>
