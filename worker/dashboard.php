<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/storage.php';
require_once __DIR__ . '/../inc/layout.php';
require_once __DIR__ . '/../inc/repository.php';
require_once __DIR__ . '/../inc/helpers.php';

require_role('worker');

// Get current user info from session
$currentUser = current_user();

// Get employee name for greeting
$employees = repo_employees();
$empName = 'Worker';
if ($currentUser) {
    $employee_id = (int)($currentUser['employee_id'] ?? 0);
    if ($employee_id) {
        $emp = find_by_id($employees, $employee_id);
        if ($emp && !empty($emp['name'])) {
            $empName = $emp['name'];
        } elseif (!empty($currentUser['username'])) {
            $empName = $currentUser['username'];
        }
    } elseif (!empty($currentUser['username'])) {
        $empName = $currentUser['username'];
    }
}

// Get worker's attendance data
$attendance = repo_attendance();
$userId = $currentUser['id'] ?? 0;
$myAttendance = array_filter($attendance, fn($a) => (int)($a['employee_id'] ?? 0) === (int)($currentUser['employee_id'] ?? 0));
$todayAttendance = null;
$today = date('Y-m-d');
foreach ($myAttendance as $a) {
    if (($a['date'] ?? '') === $today) {
        $todayAttendance = $a;
        break;
    }
}

// Calculate stats
$totalDaysWorked = count($myAttendance);
$pendingPayslips = 0;
$payslips = repo_payslips();
foreach ($payslips as $p) {
    if ((int)($p['employee_id'] ?? 0) === (int)($currentUser['employee_id'] ?? 0) && ($p['status'] ?? '') === 'pending') {
        $pendingPayslips++;
    }
}

dashboard_start('Worker Dashboard');
?>

<!-- Welcome Section -->
<div class="welcome-section">
  <div class="welcome-content">
    <span class="welcome-greeting">Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>,</span>
    <h1 class="welcome-title"><?= htmlspecialchars($empName) ?></h1>
    <p class="welcome-subtitle">Check your attendance and payslips. Have a great day!</p>
  </div>
  <div class="welcome-illustration">
    <svg viewBox="0 0 200 150" fill="none">
      <ellipse cx="100" cy="140" rx="80" ry="10" fill="currentColor" opacity="0.1"/>
      <rect x="60" y="30" width="80" height="100" rx="8" fill="currentColor" opacity="0.2"/>
      <rect x="70" y="45" width="60" height="8" rx="2" fill="currentColor" opacity="0.4"/>
      <rect x="70" y="60" width="40" height="6" rx="2" fill="currentColor" opacity="0.3"/>
      <rect x="70" y="75" width="50" height="6" rx="2" fill="currentColor" opacity="0.3"/>
      <circle cx="100" cy="105" r="15" fill="currentColor" opacity="0.3"/>
      <path d="M95 105 L100 110 L108 100" stroke="currentColor" stroke-width="2" fill="none"/>
    </svg>
  </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon attendance">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
        <line x1="16" y1="2" x2="16" y2="6"/>
        <line x1="8" y1="2" x2="8" y2="6"/>
        <line x1="3" y1="10" x2="21" y2="10"/>
        <path d="M9 16l2 2 4-4"/>
      </svg>
    </div>
    <div class="stat-info">
      <span class="stat-value" data-count="<?= $totalDaysWorked ?>"><?= $totalDaysWorked ?></span>
      <span class="stat-label">Days Worked</span>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon employees">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"/>
        <polyline points="12,6 12,12 16,14"/>
      </svg>
    </div>
    <div class="stat-info">
      <span class="stat-value"><?= $todayAttendance ? ($todayAttendance['time_out'] ? 'Complete' : 'In') : 'Not Yet' ?></span>
      <span class="stat-label">Today's Status</span>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon payroll">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
        <line x1="6" y1="11" x2="6" y2="11"/>
        <line x1="18" y1="11" x2="18" y2="11"/>
      </svg>
    </div>
    <div class="stat-info">
      <span class="stat-value" data-count="<?= $pendingPayslips ?>"><?= $pendingPayslips ?></span>
      <span class="stat-label">Pending Payslips</span>
    </div>
  </div>
</div>

<!-- Dashboard Grid -->
<div class="dashboard-grid">
  
  <!-- Quick Actions -->
  <div class="dash-card">
    <div class="card-header">
      <h3>Quick Actions</h3>
    </div>
    <div class="action-grid">
      <a href="attendance.php" class="action-item">
        <div class="action-icon mint">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 11l3 3L22 4"/>
            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
          </svg>
        </div>
        <span>Submit Attendance</span>
      </a>
      <a href="payslips.php" class="action-item">
        <div class="action-icon purple">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14,2 14,8 20,8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
          </svg>
        </div>
        <span>View Payslips</span>
      </a>
      <a href="/avantguard/change_password.php" class="action-item">
        <div class="action-icon blue">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            <circle cx="12" cy="16" r="1"/>
          </svg>
        </div>
        <span>Change Password</span>
      </a>
      <a href="/avantguard/logout.php" class="action-item">
        <div class="action-icon pink">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16,17 21,12 16,7"/>
            <line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
        </div>
        <span>Sign Out</span>
      </a>
    </div>
  </div>

  <!-- Recent Attendance -->
  <div class="dash-card">
    <div class="card-header">
      <h3>Recent Attendance</h3>
      <a href="attendance.php" class="view-all">View All</a>
    </div>
    <?php 
    $recentAttendance = array_slice(array_reverse(array_values($myAttendance)), 0, 4);
    if (empty($recentAttendance)): 
    ?>
      <div class="empty-state">
        <p>No attendance records yet.</p>
        <a href="attendance.php" class="btn">Submit Attendance</a>
      </div>
    <?php else: ?>
      <div class="activity-list">
        <?php foreach ($recentAttendance as $record): ?>
          <div class="activity-item">
            <div class="activity-avatar" style="background: linear-gradient(135deg, var(--mint-300), var(--mint-500));">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
              </svg>
            </div>
            <div class="activity-info">
              <span class="activity-name"><?= htmlspecialchars($record['date'] ?? 'N/A') ?></span>
              <span class="activity-detail">
                In: <?= htmlspecialchars($record['time_in'] ?? 'N/A') ?> 
                <?= isset($record['time_out']) ? '| Out: ' . htmlspecialchars($record['time_out']) : '| Still working' ?>
              </span>
            </div>
            <span class="activity-time"><?= isset($record['time_out']) ? '✓' : '•' ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php dashboard_end(); ?>
