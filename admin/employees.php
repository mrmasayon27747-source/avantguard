<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/storage.php';
require_once __DIR__ . '/../inc/repository.php';
require_once __DIR__ . '/../inc/layout.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/helpers.php';

require_role('admin');

$msg = null;
$err = null;

$employees = repo_employees();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  // ADD EMPLOYEE
  if ($action === 'add') {
    $code = trim($_POST['employee_code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $pos  = trim($_POST['position'] ?? '');
    $pay_type = $_POST['pay_type'] ?? 'hourly';

    $hourly_rate = (float)($_POST['hourly_rate'] ?? 0);
    $fixed_default_hours = (int)($_POST['fixed_default_hours'] ?? 4);
    $fixed_daily_rate = (float)($_POST['fixed_daily_rate'] ?? 0);

    // Parse multi-schedule blocks
    $rawSchedules = $_POST['schedules'] ?? [];
    $schedules = [];
    foreach ($rawSchedules as $block) {
      $days = $block['days'] ?? [];
      $start = $block['start'] ?? '';
      $end = $block['end'] ?? '';
      if (!empty($days) && $start && $end) {
        $schedules[] = [
          'days' => $days,
          'start' => $start,
          'end' => $end
        ];
      }
    }
    // For backward compatibility, extract first schedule for legacy fields
    $schedule_start = $schedules[0]['start'] ?? '07:00';
    $schedule_end = $schedules[0]['end'] ?? '20:00';
    $schedule_days = $schedules[0]['days'] ?? ['Mon','Tue','Wed','Thu','Fri'];

    if (!$code || !$name) {
      $err = "Employee code and name are required.";
    } elseif (find_employee_by_code($employees, $code)) {
      $err = "Employee code already exists.";
    } elseif (!in_array($pay_type, ['hourly','fixed'], true)) {
      $err = "Invalid pay type.";
    } elseif ($pay_type === 'hourly' && $hourly_rate <= 0) {
      $err = "Please enter a valid hourly rate.";
    } elseif ($pay_type === 'fixed' && $fixed_default_hours <= 0) {
      $err = "Please enter valid fixed hours.";
    } elseif ($pay_type === 'fixed' && $fixed_daily_rate <= 0) {
      $err = "Please enter a valid daily rate.";
    } else {
      // Clean fields by pay type
      if ($pay_type === 'fixed') {
        $hourly_rate = 0.0;
      } else {
        $fixed_default_hours = 0;
        $fixed_daily_rate = 0.0;
      }

      repo_create_employee([
        'employee_code' => $code,
        'name' => $name,
        'position' => $pos,
        'pay_type' => $pay_type,
        'hourly_rate' => $hourly_rate,
        'fixed_default_hours' => $fixed_default_hours,
        'fixed_daily_rate' => $fixed_daily_rate,
        'schedule_start' => $schedule_start,
        'schedule_end' => $schedule_end,
        'schedule_days' => implode(',', $schedule_days),
        'schedules' => json_encode($schedules),
        'active' => 1
      ]);

      $msg = "Employee added.";
    }
  }

  // UPDATE EMPLOYEE
  if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);

    $pay_type = $_POST['pay_type'] ?? 'hourly';
    $hourly_rate = (float)($_POST['hourly_rate'] ?? 0);
    $fixed_default_hours = (int)($_POST['fixed_default_hours'] ?? 4);
    $fixed_daily_rate = (float)($_POST['fixed_daily_rate'] ?? 0);

    // Parse multi-schedule blocks for update
    $rawSchedules = $_POST['schedules'] ?? [];
    $schedules = [];
    foreach ($rawSchedules as $block) {
      $days = $block['days'] ?? [];
      $start = $block['start'] ?? '';
      $end = $block['end'] ?? '';
      if (!empty($days) && $start && $end) {
        $schedules[] = [
          'days' => $days,
          'start' => $start,
          'end' => $end
        ];
      }
    }
    // For backward compatibility
    $schedule_start = $schedules[0]['start'] ?? '07:00';
    $schedule_end = $schedules[0]['end'] ?? '20:00';
    $schedule_days = $schedules[0]['days'] ?? [];

    if (!in_array($pay_type, ['hourly','fixed'], true)) {
      $err = "Invalid pay type.";
    } elseif ($pay_type === 'hourly' && $hourly_rate <= 0) {
      $err = "Please enter a valid hourly rate.";
    } elseif ($pay_type === 'fixed' && $fixed_default_hours <= 0) {
      $err = "Please enter valid fixed hours.";
    } elseif ($pay_type === 'fixed' && $fixed_daily_rate <= 0) {
      $err = "Please enter a valid daily rate.";
    } else {
      $updateData = [
        'pay_type' => $pay_type,
        'schedule_start' => $schedule_start,
        'schedule_end' => $schedule_end,
        'schedule_days' => implode(',', $schedule_days),
        'schedules' => json_encode($schedules)
      ];

      if ($pay_type === 'fixed') {
        $updateData['hourly_rate'] = 0.0;
        $updateData['fixed_default_hours'] = $fixed_default_hours;
        $updateData['fixed_daily_rate'] = $fixed_daily_rate;
      } else {
        $updateData['fixed_default_hours'] = 0;
        $updateData['fixed_daily_rate'] = 0.0;
        $updateData['hourly_rate'] = $hourly_rate;
      }

      repo_update_employee($id, $updateData);
      $msg = "Employee updated.";
    }
  }

  // DELETE EMPLOYEE
  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    repo_delete_employee($id);
    $msg = "Employee removed.";
  }

  // reload after writes
  $employees = repo_employees();
}

usort($employees, fn($a,$b) => strcmp(($a['employee_code'] ?? ''), ($b['employee_code'] ?? '')));

dashboard_start('Employees');
?>

  <div class="card">
    <h3>Add Employee</h3>
    <p>Workers can sign up only if their employee code exists.</p>

    <?php if ($err): ?>
      <div class="notice" style="border-color:rgba(255,45,85,.35); background:rgba(255,45,85,.10);">
        <?= htmlspecialchars($err) ?>
      </div>
    <?php endif; ?>
    <?php if ($msg): ?><div class="notice"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <form method="post" id="empForm">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="add">

      <div class="row">
        <div style="flex:1">
          <label>Employee Code</label>
          <input name="employee_code" placeholder="EMP001" required data-input="alphanumeric">
        </div>
        <div style="flex:2">
          <label>Name</label>
          <input name="name" required data-input="letters-only">
        </div>
      </div>

      <div class="row">
        <div style="flex:1">
          <label>Position</label>
          <input name="position" data-input="letters-only">
        </div>

        <div style="flex:1">
          <label>Pay Type</label>
          <select name="pay_type" id="pay_type">
            <option value="hourly">Hourly</option>
            <option value="fixed">Fixed</option>
          </select>
        </div>

        <div style="flex:1" id="hourlyBox">
          <label>Hourly Rate (₱)</label>
          <input type="number" step="0.01" min="0" name="hourly_rate" id="hourly_rate_input" value="0" placeholder="e.g. 100" data-input="decimal-only">
        </div>

        <div style="flex:1; display:none;" id="fixedHoursBox">
          <label>Fixed Hours/Day</label>
          <input type="number" step="1" min="1" max="24" name="fixed_default_hours" id="fixed_hours_input" value="4" placeholder="e.g. 4 or 8" data-input="numbers-only">
        </div>

        <div style="flex:1; display:none;" id="fixedRateBox">
          <label>Daily Rate (₱)</label>
          <input type="number" step="0.01" min="0" name="fixed_daily_rate" id="fixed_rate_input" value="0" placeholder="e.g. 100" data-input="decimal-only">
        </div>
      </div>

      <!-- Multi-Schedule Blocks -->
      <div class="schedule-section">
        <label>Work Schedules</label>
        <p class="hint">Add one or more schedule blocks (e.g., Fri-Sat 5:30am-9:30am, Tue-Wed 2:00pm-6:00pm)</p>
        
        <div id="scheduleBlocks">
          <div class="schedule-block" data-index="0">
            <div class="schedule-block-header">
              <span>Schedule Block 1</span>
              <button type="button" class="btn xs danger remove-schedule-btn" onclick="removeScheduleBlock(this)" style="display:none;">Remove</button>
            </div>
            <div class="schedule-block-content">
              <div class="schedule-time-row">
                <div>
                  <label>Start Time</label>
                  <input type="time" name="schedules[0][start]" value="07:00" required>
                </div>
                <div>
                  <label>End Time</label>
                  <input type="time" name="schedules[0][end]" value="16:00" required>
                </div>
              </div>
              <div class="schedule-days-row">
                <label>Days</label>
                <div class="day-checkboxes-inline">
                  <label class="day-checkbox-sm"><input type="checkbox" name="schedules[0][days][]" value="Mon" checked> M</label>
                  <label class="day-checkbox-sm"><input type="checkbox" name="schedules[0][days][]" value="Tue" checked> T</label>
                  <label class="day-checkbox-sm"><input type="checkbox" name="schedules[0][days][]" value="Wed" checked> W</label>
                  <label class="day-checkbox-sm"><input type="checkbox" name="schedules[0][days][]" value="Thu" checked> Th</label>
                  <label class="day-checkbox-sm"><input type="checkbox" name="schedules[0][days][]" value="Fri" checked> F</label>
                  <label class="day-checkbox-sm"><input type="checkbox" name="schedules[0][days][]" value="Sat"> Sa</label>
                  <label class="day-checkbox-sm"><input type="checkbox" name="schedules[0][days][]" value="Sun"> Su</label>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <button type="button" class="btn secondary sm" onclick="addScheduleBlock()" style="margin-top:10px;">
          + Add Another Schedule Block
        </button>
      </div>

      <div style="margin-top:14px;">
        <button class="btn" type="submit">Add Employee</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="employees-header">
      <h3>Employees</h3>
      <div class="view-toggle">
        <button class="view-btn active" data-view="grid" title="Grid View">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
            <rect x="3" y="3" width="7" height="7" rx="1"/>
            <rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="3" y="14" width="7" height="7" rx="1"/>
            <rect x="14" y="14" width="7" height="7" rx="1"/>
          </svg>
        </button>
        <button class="view-btn" data-view="table" title="Table View">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
            <line x1="3" y1="6" x2="21" y2="6"/>
            <line x1="3" y1="12" x2="21" y2="12"/>
            <line x1="3" y1="18" x2="21" y2="18"/>
          </svg>
        </button>
      </div>
    </div>
    
    <!-- Grid View -->
    <div class="employee-grid" id="gridView">
      <?php foreach ($employees as $e): ?>
        <?php
          $pt = normalize_pay_type((string)($e['pay_type'] ?? 'hourly'));
          $schedStart = $e['schedule_start'] ?? ($e['allowed_start'] ?? '07:00');
          $schedEnd   = $e['schedule_end']   ?? ($e['allowed_end'] ?? '20:00');
          $schedDays  = $e['schedule_days'] ?? 'Mon,Tue,Wed,Thu,Fri';
          $schedDaysArr = is_array($schedDays) ? $schedDays : explode(',', $schedDays);
          
          // Parse multi-schedules JSON
          $schedulesJson = $e['schedules'] ?? '';
          $multiSchedules = [];
          if (!empty($schedulesJson)) {
            $parsed = json_decode($schedulesJson, true);
            if (is_array($parsed)) {
              $multiSchedules = $parsed;
            }
          }
          // Fallback to legacy single schedule if no multi-schedules
          if (empty($multiSchedules)) {
            $multiSchedules = [['days' => $schedDaysArr, 'start' => $schedStart, 'end' => $schedEnd]];
          }
          
          $initial = strtoupper(substr($e['name'] ?? 'U', 0, 1));
          $isEditing = isset($_GET['edit']) && (int)$_GET['edit'] === (int)$e['id'];
        ?>
        <div class="employee-card <?= $isEditing ? 'editing' : '' ?>">
          <div class="emp-card-header">
            <div class="emp-avatar">
              <?= $initial ?>
            </div>
            <div class="emp-main-info">
              <h4 class="emp-name"><?= htmlspecialchars($e['name'] ?? '') ?></h4>
              <p class="emp-position"><?= htmlspecialchars($e['position'] ?? 'No position') ?></p>
            </div>
            <span class="emp-status active">ACTIVE</span>
          </div>
          
          <div class="emp-card-body">
            <div class="emp-detail">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                <rect x="2" y="4" width="20" height="16" rx="2"/>
                <path d="M7 15h0M12 15h0M17 15h0M7 11h0M12 11h0M17 11h0"/>
              </svg>
              <span><?= htmlspecialchars($e['employee_code'] ?? '') ?></span>
            </div>
            <div class="emp-detail">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                <text x="12" y="17" text-anchor="middle" font-size="16" font-weight="bold" fill="currentColor" stroke="none">₱</text>
              </svg>
              <span>
                <?php if ($pt === 'hourly'): ?>
                  ₱<?= number_format((float)($e['hourly_rate'] ?? 0), 2) ?>/hr
                <?php else: ?>
                  ₱<?= number_format((float)($e['fixed_daily_rate'] ?? 0), 2) ?>/day
                <?php endif; ?>
              </span>
            </div>
          </div>
          
          <div class="emp-card-footer">
            <div class="emp-schedule">
              <div class="schedule-label">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                  <circle cx="12" cy="12" r="10"/>
                  <polyline points="12 6 12 12 16 14"/>
                </svg>
                Schedule<?= count($multiSchedules) > 1 ? 's' : '' ?>
              </div>
              <?php foreach ($multiSchedules as $idx => $sched): 
                $sDays = $sched['days'] ?? [];
                $sStart = $sched['start'] ?? '07:00';
                $sEnd = $sched['end'] ?? '16:00';
              ?>
                <div class="schedule-block-display">
                  <div class="schedule-time"><?= htmlspecialchars(date('g:i A', strtotime($sStart))) ?> - <?= htmlspecialchars(date('g:i A', strtotime($sEnd))) ?></div>
                  <div class="schedule-days-display">
                    <?php 
                      $allDays = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
                      foreach ($allDays as $day): 
                        $active = in_array($day, $sDays);
                    ?>
                      <span class="day-badge <?= $active ? 'active' : '' ?>"><?= $day[0] ?></span>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="emp-meta">
              <span class="emp-pay-type <?= $pt ?>"><?= ucfirst($pt) ?></span>
              <?php if ($pt === 'fixed'): ?>
                <span class="emp-hours"><?= (int)($e['fixed_default_hours'] ?? 0) ?>h/day</span>
              <?php endif; ?>
            </div>
          </div>
          
          <?php if (!$isEditing): ?>
            <div class="emp-card-actions">
              <a href="?edit=<?= (int)$e['id'] ?>" class="btn xs">Edit</a>
              <form method="post" style="display:inline;" onsubmit="return confirm('Remove this employee?')">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                <button class="btn danger xs" type="submit">Delete</button>
              </form>
            </div>
          <?php else: ?>
            <div class="emp-card-edit">
              <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                <div class="edit-row">
                  <label>Pay Type</label>
                  <select name="pay_type" class="edit-pay-type">
                    <option value="hourly" <?= $pt === 'hourly' ? 'selected' : '' ?>>Hourly</option>
                    <option value="fixed" <?= $pt === 'fixed' ? 'selected' : '' ?>>Fixed</option>
                  </select>
                </div>
                <div class="edit-row edit-hourly" style="<?= $pt === 'fixed' ? 'display:none;' : '' ?>">
                  <label>Hourly Rate (₱)</label>
                  <input type="number" step="0.01" min="0" name="hourly_rate" value="<?= (float)($e['hourly_rate'] ?? 0) ?>" data-input="decimal-only">
                </div>
                <div class="edit-row edit-fixed-hours" style="<?= $pt === 'hourly' ? 'display:none;' : '' ?>">
                  <label>Fixed Hours/Day</label>
                  <input type="number" step="1" min="1" max="24" name="fixed_default_hours" value="<?= (int)($e['fixed_default_hours'] ?? 4) ?>" data-input="numbers-only">
                </div>
                <div class="edit-row edit-fixed-rate" style="<?= $pt === 'hourly' ? 'display:none;' : '' ?>">
                  <label>Daily Rate (₱)</label>
                  <input type="number" step="0.01" min="0" name="fixed_daily_rate" value="<?= (float)($e['fixed_daily_rate'] ?? 0) ?>" data-input="decimal-only">
                </div>
                
                <!-- Multi-Schedule Edit -->
                <div class="edit-row">
                  <label>Work Schedules</label>
                  <div class="edit-schedules-container" data-emp-id="<?= (int)$e['id'] ?>">
                    <?php foreach ($multiSchedules as $idx => $sched): 
                      $sDays = $sched['days'] ?? [];
                      $sStart = $sched['start'] ?? '07:00';
                      $sEnd = $sched['end'] ?? '16:00';
                    ?>
                      <div class="edit-schedule-block" data-index="<?= $idx ?>">
                        <div class="edit-schedule-header">
                          <span>Block <?= $idx + 1 ?></span>
                          <?php if (count($multiSchedules) > 1): ?>
                            <button type="button" class="btn xs danger" onclick="this.closest('.edit-schedule-block').remove()">×</button>
                          <?php endif; ?>
                        </div>
                        <div class="edit-schedule-times">
                          <input type="time" name="schedules[<?= $idx ?>][start]" value="<?= htmlspecialchars($sStart) ?>">
                          <span>to</span>
                          <input type="time" name="schedules[<?= $idx ?>][end]" value="<?= htmlspecialchars($sEnd) ?>">
                        </div>
                        <div class="day-checkboxes-inline">
                          <?php $allDays = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']; ?>
                          <?php foreach ($allDays as $day): ?>
                            <label class="day-checkbox-sm">
                              <input type="checkbox" name="schedules[<?= $idx ?>][days][]" value="<?= $day ?>" <?= in_array($day, $sDays) ? 'checked' : '' ?>>
                              <?= $day[0] ?>
                            </label>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <button type="button" class="btn xs secondary" onclick="addEditScheduleBlock(this)" style="margin-top:6px;">+ Add Block</button>
                </div>
                
                <div class="edit-actions">
                  <button class="btn xs" type="submit">Save</button>
                  <a href="employees.php" class="btn secondary xs">Cancel</a>
                </div>
              </form>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (count($employees) === 0): ?>
        <div class="empty-state">
          <p>No employees yet. Add one above!</p>
        </div>
      <?php endif; ?>
    </div>
    
    <!-- Table View (hidden by default) -->
    <div class="emp-table-wrap" id="tableView" style="display:none;">
    <table class="compact">
      <thead>
        <tr>
          <th>Code</th>
          <th>Name</th>
          <th>Position</th>
          <th>Pay</th>
          <th>Rate</th>
          <th>Schedule</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($employees as $e): ?>
        <?php
          $pt = normalize_pay_type((string)($e['pay_type'] ?? 'hourly'));
          $schedStart = $e['schedule_start'] ?? ($e['allowed_start'] ?? '07:00');
          $schedEnd   = $e['schedule_end']   ?? ($e['allowed_end'] ?? '20:00');
        ?>
        <tr>
          <td><?= htmlspecialchars($e['employee_code'] ?? '') ?></td>
          <td><?= htmlspecialchars($e['name'] ?? '') ?></td>
          <td><?= htmlspecialchars($e['position'] ?? '') ?></td>
          <td><?= htmlspecialchars($pt) ?></td>
          <td>
            <?php if ($pt === 'hourly'): ?>
              ₱<?= number_format((float)($e['hourly_rate'] ?? 0), 2) ?>/hr
            <?php else: ?>
              ₱<?= number_format((float)($e['fixed_daily_rate'] ?? 0), 2) ?>/day (<?= (int)($e['fixed_default_hours'] ?? 0) ?>h)
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($schedStart . '-' . $schedEnd) ?></td>
          <td>
            <div class="emp-btns">
              <a href="?edit=<?= (int)$e['id'] ?>" class="btn xs">Edit</a>
              <form method="post" class="emp-remove-form" onsubmit="return confirm('Remove this employee?')">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                <button class="btn danger xs" type="submit">Del</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (count($employees) === 0): ?>
        <tr><td colspan="7">No employees yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  // Add form toggle
  const pay = document.getElementById("pay_type");
  const hourlyBox = document.getElementById("hourlyBox");
  const fixedHoursBox = document.getElementById("fixedHoursBox");
  const fixedRateBox = document.getElementById("fixedRateBox");

  function toggle(){
    const isFixed = pay.value === "fixed";
    hourlyBox.style.display = isFixed ? "none" : "block";
    fixedHoursBox.style.display = isFixed ? "block" : "none";
    fixedRateBox.style.display = isFixed ? "block" : "none";
  }
  pay.addEventListener("change", toggle);
  toggle();

  // Inline edit form toggles (for card view)
  document.querySelectorAll('.emp-card-edit .edit-pay-type').forEach(select => {
    select.addEventListener('change', function() {
      const form = this.closest('form');
      const isFixed = this.value === 'fixed';
      form.querySelector('.edit-hourly').style.display = isFixed ? 'none' : 'block';
      form.querySelector('.edit-fixed-hours').style.display = isFixed ? 'block' : 'none';
      form.querySelector('.edit-fixed-rate').style.display = isFixed ? 'block' : 'none';
    });
  });
  
  // View toggle (grid/table)
  const viewBtns = document.querySelectorAll('.view-btn');
  const gridView = document.getElementById('gridView');
  const tableView = document.getElementById('tableView');
  
  viewBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      const view = this.dataset.view;
      viewBtns.forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      
      if (view === 'grid') {
        gridView.style.display = 'grid';
        tableView.style.display = 'none';
      } else {
        gridView.style.display = 'none';
        tableView.style.display = 'block';
      }
      
      localStorage.setItem('employeeView', view);
    });
  });
  
  // Restore saved view preference
  const savedView = localStorage.getItem('employeeView') || 'grid';
  const savedBtn = document.querySelector(`.view-btn[data-view="${savedView}"]`);
  if (savedBtn) savedBtn.click();
});

// Function to set schedule days checkboxes (used by preset buttons)
function setScheduleDays(days) {
  const checkboxes = document.querySelectorAll('#empForm .day-checkboxes input[type="checkbox"]');
  checkboxes.forEach(cb => {
    cb.checked = days.includes(cb.value);
  });
}

// Schedule block counter for add form
let scheduleBlockCount = 1;

// Add new schedule block (Add Employee form)
function addScheduleBlock() {
  const container = document.getElementById('scheduleBlocks');
  const index = scheduleBlockCount++;
  
  const block = document.createElement('div');
  block.className = 'schedule-block';
  block.dataset.index = index;
  block.innerHTML = `
    <div class="schedule-block-header">
      <span>Schedule Block ${index + 1}</span>
      <button type="button" class="btn xs danger remove-schedule-btn" onclick="removeScheduleBlock(this)">Remove</button>
    </div>
    <div class="schedule-block-content">
      <div class="schedule-time-row">
        <div>
          <label>Start Time</label>
          <input type="time" name="schedules[${index}][start]" value="07:00" required>
        </div>
        <div>
          <label>End Time</label>
          <input type="time" name="schedules[${index}][end]" value="16:00" required>
        </div>
      </div>
      <div class="schedule-days-row">
        <label>Days</label>
        <div class="day-checkboxes-inline">
          <label class="day-checkbox-sm"><input type="checkbox" name="schedules[${index}][days][]" value="Mon"> M</label>
          <label class="day-checkbox-sm"><input type="checkbox" name="schedules[${index}][days][]" value="Tue"> T</label>
          <label class="day-checkbox-sm"><input type="checkbox" name="schedules[${index}][days][]" value="Wed"> W</label>
          <label class="day-checkbox-sm"><input type="checkbox" name="schedules[${index}][days][]" value="Thu"> Th</label>
          <label class="day-checkbox-sm"><input type="checkbox" name="schedules[${index}][days][]" value="Fri"> F</label>
          <label class="day-checkbox-sm"><input type="checkbox" name="schedules[${index}][days][]" value="Sat"> Sa</label>
          <label class="day-checkbox-sm"><input type="checkbox" name="schedules[${index}][days][]" value="Sun"> Su</label>
        </div>
      </div>
    </div>
  `;
  container.appendChild(block);
  
  // Show remove button on first block if we have more than one
  updateRemoveButtons();
}

// Remove a schedule block
function removeScheduleBlock(btn) {
  btn.closest('.schedule-block').remove();
  updateRemoveButtons();
}

// Update visibility of remove buttons
function updateRemoveButtons() {
  const blocks = document.querySelectorAll('#scheduleBlocks .schedule-block');
  blocks.forEach((block, i) => {
    const removeBtn = block.querySelector('.remove-schedule-btn');
    if (removeBtn) {
      removeBtn.style.display = blocks.length > 1 ? 'inline-block' : 'none';
    }
  });
}

// Add schedule block for edit form
function addEditScheduleBlock(btn) {
  const container = btn.previousElementSibling;
  const blocks = container.querySelectorAll('.edit-schedule-block');
  const index = blocks.length;
  
  const block = document.createElement('div');
  block.className = 'edit-schedule-block';
  block.dataset.index = index;
  block.innerHTML = `
    <div class="edit-schedule-header">
      <span>Block ${index + 1}</span>
      <button type="button" class="btn xs danger" onclick="this.closest('.edit-schedule-block').remove()">×</button>
    </div>
    <div class="edit-schedule-times">
      <input type="time" name="schedules[${index}][start]" value="07:00">
      <span>to</span>
      <input type="time" name="schedules[${index}][end]" value="16:00">
    </div>
    <div class="day-checkboxes-inline">
      <label class="day-checkbox-sm"><input type="checkbox" name="schedules[${index}][days][]" value="Mon"> M</label>
      <label class="day-checkbox-sm"><input type="checkbox" name="schedules[${index}][days][]" value="Tue"> T</label>
      <label class="day-checkbox-sm"><input type="checkbox" name="schedules[${index}][days][]" value="Wed"> W</label>
      <label class="day-checkbox-sm"><input type="checkbox" name="schedules[${index}][days][]" value="Thu"> Th</label>
      <label class="day-checkbox-sm"><input type="checkbox" name="schedules[${index}][days][]" value="Fri"> F</label>
      <label class="day-checkbox-sm"><input type="checkbox" name="schedules[${index}][days][]" value="Sat"> Sa</label>
      <label class="day-checkbox-sm"><input type="checkbox" name="schedules[${index}][days][]" value="Sun"> Su</label>
    </div>
  `;
  container.appendChild(block);
}
</script>
<?php dashboard_end(); ?>
