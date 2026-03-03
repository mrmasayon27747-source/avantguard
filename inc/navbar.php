<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

$u = current_user();
if (!$u) return;
?>
<div class="navbar">
  <div class="nav-left">
    <!-- Brand display only (NOT a link) -->
    <span class="nav-brand">AVANT-GUARD VIRTUAL ASSISTANCE SERVICES</span>

    <?php if (($u['role'] ?? '') === 'admin'): ?>
      <a href="/avantguard/admin/dashboard.php">Dashboard</a>
      <a href="/avantguard/admin/reports.php">Reports</a>
      <a href="/avantguard/admin/employees.php">Employees</a>
      <a href="/avantguard/admin/attendance.php">Attendance</a>
      <a href="/avantguard/admin/payroll.php">Payroll</a>
    <?php else: ?>
      <a href="/avantguard/worker/dashboard.php">Dashboard</a>
      <a href="/avantguard/worker/attendance.php">Attendance</a>
      <a href="/avantguard/worker/payslips.php">Payslips</a>
    <?php endif; ?>
  </div>

  <div class="nav-right">
    <button class="btn secondary nav-icon" type="button" data-theme-toggle title="Toggle theme">☾</button>

    <div class="logout-wrap">
      <button class="btn secondary" id="logoutBtn" type="button">Logout</button>
      <div class="dropdown-menu" id="logoutMenu" hidden>
        <a href="/avantguard/logout.php">Confirm Logout</a>
      </div>
    </div>
  </div>
</div>
