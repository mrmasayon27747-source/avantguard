<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

$u = current_user();
if (!$u) return;

$isAdmin = ($u['role'] ?? '') === 'admin';
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

// Check if we're in a reports sub-page
$reportsPages = ['reports', 'reports_payslips', 'reports_eod', 'reports_devotional'];
$isReportsActive = in_array($currentPage, $reportsPages);
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">
      <img src="/avantguard/assets/avantguard.png" alt="Avant-Guard" class="logo-icon">
      <span class="logo-text">AVANT-GUARD<br><small>Virtual Assistance Services</small></span>
    </div>
  </div>

  <nav class="sidebar-nav">
    <?php if ($isAdmin): ?>
      <a href="/avantguard/admin/dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="7" height="7" rx="1"/>
          <rect x="14" y="3" width="7" height="7" rx="1"/>
          <rect x="3" y="14" width="7" height="7" rx="1"/>
          <rect x="14" y="14" width="7" height="7" rx="1"/>
        </svg>
        <span>Dashboard</span>
      </a>
      <a href="/avantguard/admin/employees.php" class="nav-item <?= $currentPage === 'employees' ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        <span>Employees</span>
      </a>
      <a href="/avantguard/admin/attendance.php" class="nav-item <?= $currentPage === 'attendance' ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
          <line x1="16" y1="2" x2="16" y2="6"/>
          <line x1="8" y1="2" x2="8" y2="6"/>
          <line x1="3" y1="10" x2="21" y2="10"/>
          <path d="M9 16l2 2 4-4"/>
        </svg>
        <span>Attendance</span>
      </a>
      <a href="/avantguard/admin/payroll.php" class="nav-item <?= $currentPage === 'payroll' ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <text x="12" y="17" text-anchor="middle" font-size="16" font-weight="bold" fill="currentColor" stroke="none">₱</text>
        </svg>
        <span>Payroll</span>
      </a>
      <a href="/avantguard/admin/tasks.php" class="nav-item <?= $currentPage === 'tasks' ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 11l3 3L22 4"/>
          <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
        </svg>
        <span>Tasks</span>
      </a>
      <a href="/avantguard/admin/deductions.php" class="nav-item <?= $currentPage === 'deductions' ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <line x1="8" y1="12" x2="16" y2="12"/>
        </svg>
        <span>Deductions</span>
      </a>
      
      <!-- Reports Dropdown -->
      <div class="nav-dropdown <?= $isReportsActive ? 'open' : '' ?>">
        <button class="nav-item nav-dropdown-toggle <?= $isReportsActive ? 'active' : '' ?>" type="button">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
          </svg>
          <span>Reports</span>
          <svg class="dropdown-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 12 15 18 9"/>
          </svg>
        </button>
        <div class="nav-dropdown-menu">
          <a href="/avantguard/admin/reports_payslips.php" class="nav-subitem <?= $currentPage === 'reports_payslips' ? 'active' : '' ?>">
            Payslips
          </a>
          <a href="/avantguard/admin/reports_eod.php" class="nav-subitem <?= $currentPage === 'reports_eod' ? 'active' : '' ?>">
            EOD Reports
          </a>
          <a href="/avantguard/admin/reports_devotional.php" class="nav-subitem <?= $currentPage === 'reports_devotional' ? 'active' : '' ?>">
            Devotionals
          </a>
        </div>
      </div>
      
    <?php else: ?>
      <a href="/avantguard/worker/dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="7" height="7" rx="1"/>
          <rect x="14" y="3" width="7" height="7" rx="1"/>
          <rect x="3" y="14" width="7" height="7" rx="1"/>
          <rect x="14" y="14" width="7" height="7" rx="1"/>
        </svg>
        <span>Dashboard</span>
      </a>
      <a href="/avantguard/worker/attendance.php" class="nav-item <?= $currentPage === 'attendance' ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
          <line x1="16" y1="2" x2="16" y2="6"/>
          <line x1="8" y1="2" x2="8" y2="6"/>
          <line x1="3" y1="10" x2="21" y2="10"/>
          <path d="M9 16l2 2 4-4"/>
        </svg>
        <span>Attendance</span>
      </a>
      <a href="/avantguard/worker/tasks.php" class="nav-item <?= $currentPage === 'tasks' ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 11l3 3L22 4"/>
          <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
        </svg>
        <span>Tasks</span>
      </a>
      <a href="/avantguard/worker/eod.php" class="nav-item <?= $currentPage === 'eod' ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <path d="M12 6v6l4 2"/>
        </svg>
        <span>EOD Report</span>
      </a>
      <a href="/avantguard/worker/devotional.php" class="nav-item <?= $currentPage === 'devotional' ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
        </svg>
        <span>Devotional</span>
      </a>
      <a href="/avantguard/worker/payslips.php" class="nav-item <?= $currentPage === 'payslips' ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
          <line x1="16" y1="13" x2="8" y2="13"/>
          <line x1="16" y1="17" x2="8" y2="17"/>
        </svg>
        <span>Payslips</span>
      </a>
      <a href="/avantguard/worker/profile.php" class="nav-item <?= $currentPage === 'profile' ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
          <circle cx="12" cy="7" r="4"/>
        </svg>
        <span>Profile</span>
      </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar">
        <?= strtoupper(substr($u['username'] ?? 'U', 0, 1)) ?>
      </div>
      <div class="user-info">
        <span class="user-name"><?= htmlspecialchars($u['username'] ?? 'User') ?></span>
        <span class="user-role"><?= ucfirst($u['role'] ?? 'user') ?></span>
      </div>
      <a href="/avantguard/logout.php" class="logout-btn" title="Logout">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
      </a>
    </div>
  </div>
</aside>
