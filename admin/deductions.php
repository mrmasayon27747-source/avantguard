<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/storage.php';
require_once __DIR__ . '/../inc/layout.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/repository.php';

require_role('admin');

$deductions = repo_deductions();
$employees = repo_employees();
$attendance = repo_attendance();

$notice = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  
  $action = $_POST['action'] ?? '';
  
  // Add new deduction
  if ($action === 'add') {
    $employee_id = (int)($_POST['employee_id'] ?? 0);
    $date = trim($_POST['date'] ?? '');
    $reason = $_POST['reason'] ?? '';
    $custom_reason = trim($_POST['custom_reason'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    
    if (!$employee_id) {
      $error = "Please select an employee.";
    } elseif (empty($date)) {
      $error = "Please select a date.";
    } elseif (empty($reason)) {
      $error = "Please select a reason.";
    } elseif ($reason === 'others' && empty($custom_reason)) {
      $error = "Please provide a custom reason.";
    } elseif ($amount <= 0) {
      $error = "Deduction amount must be greater than 0.";
    } else {
      $final_reason = ($reason === 'others') ? $custom_reason : $reason;
      
      repo_create_deduction([
        'employee_id' => $employee_id,
        'date' => $date,
        'reason' => $final_reason,
        'reason_type' => $reason,
        'amount' => $amount,
        'notes' => $notes,
        'created_by' => current_user()['id'] ?? 0
      ]);
      
      $deductions = repo_deductions();
      $notice = "Deduction added successfully.";
    }
  }
  
  // Delete deduction
  if ($action === 'delete') {
    $deduction_id = (int)($_POST['deduction_id'] ?? 0);
    repo_delete_deduction($deduction_id);
    $deductions = repo_deductions();
    $notice = "Deduction deleted.";
  }
}

// Filter
$filter_emp = (int)($_GET['employee_id'] ?? 0);
$filter_date = trim($_GET['date'] ?? '');

$filtered = $deductions;
if ($filter_emp) {
  $filtered = array_filter($filtered, fn($d) => (int)($d['employee_id'] ?? 0) === $filter_emp);
}
if ($filter_date) {
  $filtered = array_filter($filtered, fn($d) => ($d['date'] ?? '') === $filter_date);
}

usort($filtered, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));

dashboard_start('Deductions');
?>

  <div class="card">
    <h3>Manage Deductions</h3>
    <p>Add deductions for late arrivals, absences, or other reasons. These will be applied during payroll calculation.</p>

    <?php if ($notice): ?>
      <div class="notice"><?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="notice" style="border-color:rgba(255,45,85,.35); background:rgba(255,45,85,.10);">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="post" class="deduction-form" id="deductionForm">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="add">
      
      <div class="form-row">
        <div class="form-group">
          <label>Employee *</label>
          <select name="employee_id" required>
            <option value="">Select employee</option>
            <?php foreach ($employees as $e): ?>
              <option value="<?= (int)$e['id'] ?>">
                <?= htmlspecialchars(employee_name($e)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="form-group">
          <label>Date *</label>
          <input type="date" name="date" required value="<?= date('Y-m-d') ?>">
        </div>
        
        <div class="form-group">
          <label>Reason *</label>
          <select name="reason" id="reasonSelect" required onchange="toggleCustomReason()">
            <option value="">Select reason</option>
            <option value="late">Late Arrival</option>
            <option value="absent">Absent</option>
            <option value="undertime">Undertime</option>
            <option value="others">Others (specify)</option>
          </select>
        </div>
        
        <div class="form-group" id="customReasonGroup" style="display:none;">
          <label>Specify Reason *</label>
          <input type="text" name="custom_reason" id="customReason" placeholder="Enter reason...">
        </div>
        
        <div class="form-group">
          <label>Amount (₱) *</label>
          <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00" data-input="decimal-only">
        </div>
      </div>
      
      <div class="form-group">
        <label>Notes (optional)</label>
        <textarea name="notes" rows="2" placeholder="Additional notes..."></textarea>
      </div>
      
      <button type="submit" class="btn">Add Deduction</button>
    </form>
  </div>

  <div class="card">
    <h3>Deduction Records</h3>
    
    <form method="get" class="row" style="align-items:end; gap:12px; margin-bottom:16px;">
      <div style="flex:2">
        <label>Employee</label>
        <select name="employee_id">
          <option value="0">All Employees</option>
          <?php foreach ($employees as $e): ?>
            <option value="<?= (int)$e['id'] ?>" <?= $filter_emp === (int)$e['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars(employee_name($e)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="flex:1">
        <label>Date</label>
        <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>">
      </div>
      <div>
        <button class="btn" type="submit">Filter</button>
      </div>
    </form>
    
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Employee</th>
            <th>Reason</th>
            <th>Amount</th>
            <th>Notes</th>
            <th>Created</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($filtered as $d): ?>
            <?php 
              $emp = find_by_id($employees, (int)($d['employee_id'] ?? 0));
              $emp_name = $emp ? employee_name($emp) : 'Unknown';
              $reason_type = $d['reason_type'] ?? $d['reason'] ?? '';
            ?>
            <tr>
              <td><?= htmlspecialchars($d['date'] ?? '') ?></td>
              <td><?= htmlspecialchars($emp_name) ?></td>
              <td>
                <span class="reason-badge <?= htmlspecialchars($reason_type) ?>">
                  <?= htmlspecialchars(ucfirst($d['reason'] ?? '')) ?>
                </span>
              </td>
              <td style="font-weight:600; color:#ff3b30;">-₱<?= number_format((float)($d['amount'] ?? 0), 2) ?></td>
              <td><?= htmlspecialchars($d['notes'] ?? '-') ?></td>
              <td><?= htmlspecialchars($d['created_at'] ?? '') ?></td>
              <td>
                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this deduction?');">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="deduction_id" value="<?= (int)($d['id'] ?? 0) ?>">
                  <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          
          <?php if (count($filtered) === 0): ?>
            <tr><td colspan="7" class="text-center">No deductions found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<style>
.deduction-form {
  margin-top: 16px;
}
.form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
  margin-bottom: 16px;
}
.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.form-group label {
  font-size: 0.85rem;
  color: var(--dash-text-secondary);
}
.form-group input,
.form-group select,
.form-group textarea {
  padding: 10px 12px;
  border: 1px solid var(--dash-border);
  border-radius: 8px;
  background: var(--dash-surface);
  color: var(--dash-text);
  font-size: 0.9rem;
}
.form-group textarea {
  resize: vertical;
}
.reason-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
}
.reason-badge.late {
  background: rgba(255, 159, 10, 0.15);
  color: #ff9f0a;
}
.reason-badge.absent {
  background: rgba(255, 59, 48, 0.15);
  color: #ff3b30;
}
.reason-badge.undertime {
  background: rgba(175, 82, 222, 0.15);
  color: #af52de;
}
.reason-badge.others {
  background: rgba(142, 142, 147, 0.15);
  color: #8e8e93;
}
.btn-sm {
  padding: 4px 10px;
  font-size: 0.8rem;
}
.btn-danger {
  background: rgba(255, 59, 48, 0.15);
  color: #ff3b30;
  border: 1px solid rgba(255, 59, 48, 0.3);
}
.btn-danger:hover {
  background: rgba(255, 59, 48, 0.25);
}
.table-responsive {
  overflow-x: auto;
}
.text-center {
  text-align: center;
}
</style>

<script>
function toggleCustomReason() {
  const select = document.getElementById('reasonSelect');
  const customGroup = document.getElementById('customReasonGroup');
  const customInput = document.getElementById('customReason');
  
  if (select.value === 'others') {
    customGroup.style.display = 'flex';
    customInput.required = true;
  } else {
    customGroup.style.display = 'none';
    customInput.required = false;
    customInput.value = '';
  }
}
</script>

<?php dashboard_end(); ?>
