<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/layout.php';
require_once __DIR__ . '/../inc/repository.php';

require_role('admin');

// Get data for dashboard
$employees = repo_employees();
$attendance = repo_attendance();
$payslips = repo_payslips();

$totalEmployees = count($employees);
$todayAttendance = count(array_filter($attendance, fn($a) => ($a['date'] ?? '') === date('Y-m-d')));
$totalPayslips = count($payslips);

// Get current user
$user = current_user();
$greeting = 'Good ' . (date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening'));

dashboard_start('Admin Dashboard');
?>

<!-- Welcome Section -->
<div class="welcome-section">
  <div class="welcome-content">
    <span class="welcome-greeting"><?= $greeting ?>, <?= htmlspecialchars($user['username'] ?? 'Admin') ?></span>
    <h1 class="welcome-title">Manage your payroll & employees</h1>
    <p class="welcome-subtitle">Have a productive day!</p>
  </div>
  <div class="welcome-illustration">
    <svg viewBox="0 0 200 160" fill="none" xmlns="http://www.w3.org/2000/svg">
      <ellipse cx="100" cy="145" rx="60" ry="10" fill="currentColor" opacity="0.1"/>
      <rect x="60" y="50" width="80" height="60" rx="8" fill="var(--purple-400)" opacity="0.2"/>
      <rect x="70" y="40" width="60" height="70" rx="6" fill="var(--purple-500)"/>
      <rect x="78" y="52" width="44" height="4" rx="2" fill="white" opacity="0.6"/>
      <rect x="78" y="62" width="30" height="4" rx="2" fill="white" opacity="0.4"/>
      <rect x="78" y="72" width="38" height="4" rx="2" fill="white" opacity="0.4"/>
      <rect x="78" y="82" width="25" height="4" rx="2" fill="white" opacity="0.4"/>
      <circle cx="145" cy="75" r="25" fill="var(--mint-300)"/>
      <path d="M135 75 L143 83 L158 68" stroke="white" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  </div>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon employees">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
      </svg>
    </div>
    <div class="stat-info">
      <span class="stat-value" data-count="<?= $totalEmployees ?>"><?= $totalEmployees ?></span>
      <span class="stat-label">Total Employees</span>
    </div>
    <div class="stat-trend up">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
        <polyline points="17 6 23 6 23 12"/>
      </svg>
      <span>Active</span>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon attendance">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
        <line x1="16" y1="2" x2="16" y2="6"/>
        <line x1="8" y1="2" x2="8" y2="6"/>
        <line x1="3" y1="10" x2="21" y2="10"/>
      </svg>
    </div>
    <div class="stat-info">
      <span class="stat-value" data-count="<?= $todayAttendance ?>"><?= $todayAttendance ?></span>
      <span class="stat-label">Today's Attendance</span>
    </div>
    <div class="stat-trend">
      <span><?= date('M d') ?></span>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon payroll">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <text x="12" y="17" text-anchor="middle" font-size="16" font-weight="bold" fill="currentColor" stroke="none">₱</text>
      </svg>
    </div>
    <div class="stat-info">
      <span class="stat-value" data-count="<?= $totalPayslips ?>"><?= $totalPayslips ?></span>
      <span class="stat-label">Payslips Generated</span>
    </div>
    <div class="stat-trend up">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
        <polyline points="17 6 23 6 23 12"/>
      </svg>
      <span>+12%</span>
    </div>
  </div>
</div>

<!-- Main Content Grid -->
<div class="dashboard-grid">
  <!-- Quick Actions -->
  <div class="dash-card quick-actions">
    <div class="card-header">
      <h3>Quick Actions</h3>
    </div>
    <div class="action-grid">
      <a href="/avantguard/admin/employees.php" class="action-item">
        <div class="action-icon purple">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="8.5" cy="7" r="4"/>
            <line x1="20" y1="8" x2="20" y2="14"/>
            <line x1="23" y1="11" x2="17" y2="11"/>
          </svg>
        </div>
        <span>Add Employee</span>
      </a>
      <a href="/avantguard/admin/attendance.php" class="action-item">
        <div class="action-icon mint">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 11l3 3L22 4"/>
            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
          </svg>
        </div>
        <span>Log Attendance</span>
      </a>
      <a href="/avantguard/admin/payroll.php" class="action-item">
        <div class="action-icon pink">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
            <line x1="1" y1="10" x2="23" y2="10"/>
          </svg>
        </div>
        <span>Run Payroll</span>
      </a>
      <a href="/avantguard/admin/reports.php" class="action-item">
        <div class="action-icon blue">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="20" x2="18" y2="10"/>
            <line x1="12" y1="20" x2="12" y2="4"/>
            <line x1="6" y1="20" x2="6" y2="14"/>
          </svg>
        </div>
        <span>View Reports</span>
      </a>
    </div>
  </div>

  <!-- Recent Activity -->
  <div class="dash-card activity-card">
    <div class="card-header">
      <h3>Recent Activity</h3>
      <a href="/avantguard/admin/attendance.php" class="view-all">View all</a>
    </div>
    <div class="activity-list">
      <?php 
      $recentAttendance = array_slice(array_reverse($attendance), 0, 5);
      if (empty($recentAttendance)): ?>
        <div class="empty-state">
          <p>No recent attendance records</p>
        </div>
      <?php else: 
        foreach ($recentAttendance as $record): 
          $emp = null;
          foreach ($employees as $e) {
            if (($e['id'] ?? 0) == ($record['employee_id'] ?? 0)) {
              $emp = $e;
              break;
            }
          }
      ?>
        <div class="activity-item">
          <div class="activity-avatar">
            <?= strtoupper(substr($emp['name'] ?? 'U', 0, 1)) ?>
          </div>
          <div class="activity-info">
            <span class="activity-name"><?= htmlspecialchars($emp['name'] ?? 'Unknown') ?></span>
            <?php 
              $hrs = (float)($record['hours'] ?? 0);
              $timeOut = (string)($record['time_out'] ?? '');
              $hasTimeOut = !empty($timeOut) && $timeOut !== '00:00:00' && $timeOut !== '00:00';
            ?>
            <span class="activity-detail">
              <?php if ($hasTimeOut): ?>
                Logged <?= number_format($hrs, 2) ?> hours
              <?php else: ?>
                Clocked in (pending time-out)
              <?php endif; ?>
            </span>
          </div>
          <span class="activity-time"><?= $record['date'] ?? '' ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

</div>

<?php dashboard_end(); ?>
