<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/session.php';

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/storage.php';
require_once __DIR__ . '/../inc/layout.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/repository.php';
require_once __DIR__ . '/../inc/anomaly.php';

require_role('admin');

$employees = repo_employees();
$attendance = repo_attendance();
$payslips = repo_payslips();

$notice = null;
$error = null;
$anomaly_warnings = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $employee_id = (int)($_POST['employee_id'] ?? 0);
  $period_start = trim($_POST['period_start'] ?? '');
  $period_end = trim($_POST['period_end'] ?? '');
  $manual_overtime_bonus = (float)($_POST['manual_overtime_bonus'] ?? 0);
  $calculated_by = trim($_POST['calculated_by'] ?? '');

  if (!$employee_id || !$period_start || !$period_end) {
    $error = "Please select employee and payroll period.";
  } elseif ($period_start > $period_end) {
    $error = "Period start date cannot be after end date.";
  } else {
    $emp = find_by_id($employees, $employee_id);
    if (!$emp) {
      $error = "Employee not found.";
    } else {
        // ✅ Block if any existing payslip for this employee overlaps the requested period
        $overlapping = repo_payslip_overlaps($employee_id, $period_start, $period_end);
        if (!empty($overlapping)) {
          $c = $overlapping[0];
          $c_status = strtoupper((string)($c['status'] ?? 'draft'));
          $error = "Cannot generate payslip: the period {$period_start} → {$period_end} overlaps with an existing {$c_status} payslip ({$c['period_start']} → {$c['period_end']}). "
                 . "Delete or adjust the conflicting payslip first, or choose a non-overlapping date range.";
        }

        // Filter attendance: this employee, within the date range
        $recs = array_values(array_filter($attendance, function($r) use ($employee_id, $period_start, $period_end) {
          if ((int)($r['employee_id'] ?? 0) !== $employee_id) return false;
          $d = (string)($r['date'] ?? '');
          if ($d < $period_start || $d > $period_end) return false;
          return true;
        }));

      // Check if we found any unpaid attendance in this period
      if (empty($recs)) {
        $error = "No unpaid attendance records found for this employee in the selected period. All attendance may have already been included in other payslips.";
      }

    if (!$error) {

      $total_hours = 0.0;
$days_present = 0;

// hours sum (hourly)
foreach ($recs as $r) {
  $total_hours += (float)($r['hours'] ?? 0);
}

// days count (fixed/day)
foreach ($recs as $r) {
  if (!empty($r['time_in'])) $days_present++;
}

$pay_type = (string)($emp['pay_type'] ?? 'hourly');

// ✅ Hourly: rate per hour
$hourly_rate = (float)($emp['hourly_rate'] ?? 0);

// ✅ Fixed: admin-defined daily rate (stored in fixed_daily_rate field)
$fixed_daily_rate = (float)($emp['fixed_daily_rate'] ?? 0);
$fixed_default_hours = (int)($emp['fixed_default_hours'] ?? 0);

$base = 0.0;

if ($pay_type === 'fixed') {
  // ✅ Fixed = days present × daily rate (hours are for monitoring only)
  $base = $days_present * $fixed_daily_rate;
} else {
  // ✅ Hourly = actual hours worked × hourly rate
  $base = $total_hours * $hourly_rate;
}

      // ✅ Calculate deductions for this period
      $deductions = repo_deductions();
      $period_deductions = array_filter($deductions, function($d) use ($employee_id, $period_start, $period_end) {
        if ((int)($d['employee_id'] ?? 0) !== $employee_id) return false;
        $date = $d['date'] ?? '';
        return $date >= $period_start && $date <= $period_end;
      });
      
      $total_deductions = 0.0;
      foreach ($period_deductions as $d) {
        $total_deductions += (float)($d['amount'] ?? 0);
      }
      
      // ✅ Gross pay = base + overtime bonus
      $gross_pay = $base + $manual_overtime_bonus;
      
      // ✅ Net pay = gross - deductions
      $net_pay = max(0, $gross_pay - $total_deductions);

      // ✅ Anomaly detection - check for unusual amounts
      $anomaly_warnings = detect_payroll_anomalies(
          $employee_id,
          $gross_pay,
          $net_pay,
          $manual_overtime_bonus,
          $total_deductions,
          $total_hours,
          $days_present,
          $payslips
      );

      // ✅ Calculate actual date range from attendance records
      $actual_dates = array_map(fn($r) => (string)($r['date'] ?? ''), $recs);
      $actual_start = min($actual_dates);
      $actual_end = max($actual_dates);

      repo_create_payslip([
        'employee_id' => $employee_id,
        'employee_name' => employee_name($emp),
        'period_start' => $actual_start,
        'period_end' => $actual_end,
        'pay_type' => $pay_type,
        'rate' => ($pay_type === 'fixed') ? $fixed_daily_rate : $hourly_rate,
        'days_present' => $days_present,
        'total_hours' => round($total_hours, 2),
        'gross_pay' => round($gross_pay, 2),
        'manual_overtime_bonus' => round($manual_overtime_bonus, 2),
        'total_deductions' => round($total_deductions, 2),
        'net_pay' => round($net_pay, 2),
        'status' => 'draft',
        'calculated_by' => $calculated_by ?: null
      ]);
      
      $payslips = repo_payslips();

      $ot_msg = $manual_overtime_bonus > 0 ? " OT Bonus: ₱" . number_format($manual_overtime_bonus, 2) . "." : "";
      $deduction_msg = $total_deductions > 0 ? " Deductions: ₱" . number_format($total_deductions, 2) . "." : "";
      $notice = "Payslip saved as DRAFT for period {$actual_start} → {$actual_end} ({$days_present} days).{$ot_msg}{$deduction_msg} Worker will NOT see it until you release it in Reports.";
    } // end if !$error (overlap check)
    }
  }
}

dashboard_start('Payroll');

// Helper function to format time for display
function format_time_12h(string $t): string {
  if (empty($t)) return '-';
  $dt = DateTime::createFromFormat('H:i', $t);
  if ($dt === false) $dt = DateTime::createFromFormat('H:i:s', $t);
  return $dt ? $dt->format('g:i A') : $t;
}
?>

  <div class="card">
    <h3>Payroll</h3>
    <p>Create payslips as <b>Draft</b>. Go to <b>Reports</b> to release them.</p>

    <?php if ($notice): ?>
      <div class="notice"><?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="notice" style="border-color:rgba(255,45,85,.35); background:rgba(255,45,85,.10);">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>
    
    <?php if (!empty($anomaly_warnings)): ?>
      <?php render_anomaly_styles(); ?>
      <?php render_anomaly_warnings($anomaly_warnings); ?>
    <?php endif; ?>

    <form method="post" class="form-grid" id="payrollForm" style="grid-template-columns: repeat(3, 1fr);">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

      <div>
        <label>Employee</label>
        <select name="employee_id" id="employee_id" required>
          <option value="">Select employee</option>
          <?php foreach ($employees as $e): ?>
            <option value="<?= (int)$e['id'] ?>" 
                    data-pay-type="<?= htmlspecialchars($e['pay_type'] ?? 'hourly') ?>" 
                    data-hourly-rate="<?= (float)($e['hourly_rate'] ?? 0) ?>" 
                    data-fixed-hours="<?= (int)($e['fixed_default_hours'] ?? 0) ?>"
                    data-fixed-daily-rate="<?= (float)($e['fixed_daily_rate'] ?? 0) ?>">
              <?= htmlspecialchars(employee_name($e)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label>Period Start</label>
        <input type="date" name="period_start" id="period_start" required>
      </div>

      <div>
        <label>Period End</label>
        <input type="date" name="period_end" id="period_end" required>
      </div>

      <div>
        <label>OT Bonus (₱)</label>
        <input type="number" name="manual_overtime_bonus" id="manual_overtime_bonus" step="0.01" min="0" value="0" placeholder="0.00" data-input="decimal-only">
        <small style="color: var(--text-muted); font-size: 0.7rem;">Optional</small>
      </div>

      <div>
        <label>Calculated By</label>
        <input type="text" name="calculated_by" id="calculated_by" placeholder="Name of person calculating" data-input="letters-only">
        <small style="color: var(--text-muted); font-size: 0.7rem;">For accountability</small>
      </div>

      <div class="form-actions" style="grid-column: 2 / -1; display: flex; gap: 12px; align-items: flex-end;">
        <button type="button" class="btn secondary" id="previewBtn">Preview Attendance</button>
        <button class="btn" type="submit">Save Draft Payslip</button>
      </div>
    </form>
  </div>

  <!-- Attendance Preview Section -->
  <div class="card" id="previewSection" style="display: none;">
    <h3>Attendance Breakdown</h3>
    <p id="previewInfo"></p>
    
    <div class="emp-table-wrap">
      <table class="compact">
        <thead>
          <tr>
            <th>Date</th>
            <th>Mode</th>
            <th>Time In</th>
            <th>Time Out</th>
            <th>Hours</th>
          </tr>
        </thead>
        <tbody id="previewBody">
        </tbody>
        <tfoot>
          <tr style="font-weight: bold; background: var(--dash-surface-hover);">
            <td colspan="4">Total</td>
            <td id="totalHours">0.00</td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="payroll-summary" id="payrollSummary" style="margin-top: 16px; padding: 16px; background: var(--dash-surface-hover); border-radius: 8px;">
      <p><strong>Days Present:</strong> <span id="sumDays">0</span></p>
      <p><strong>Total Hours:</strong> <span id="sumHours">0.00</span></p>
      <p><strong>Pay Type:</strong> <span id="sumPayType">-</span></p>
      <p><strong>Salary:</strong> <span id="sumRate">-</span></p>
      <p style="font-size: 1.2em; margin-top: 8px;"><strong>Estimated Gross Pay:</strong> <span id="sumGross" style="color: var(--mint-400);">₱0.00</span></p>
    </div>
  </div>

<script>
// Attendance data from PHP
const attendanceData = <?= json_encode($attendance) ?>;
const employeesData = <?= json_encode($employees) ?>;

function formatTime12h(t) {
  if (!t) return '-';
  const parts = t.split(':');
  if (parts.length < 2) return t;
  let h = parseInt(parts[0], 10);
  const m = parts[1];
  const ampm = h >= 12 ? 'PM' : 'AM';
  h = h % 12 || 12;
  return h + ':' + m + ' ' + ampm;
}

document.getElementById('previewBtn').addEventListener('click', function() {
  const empId = parseInt(document.getElementById('employee_id').value, 10);
  const startDate = document.getElementById('period_start').value;
  const endDate = document.getElementById('period_end').value;
  
  if (!empId || !startDate || !endDate) {
    alert('Please select employee and date range first.');
    return;
  }
  
  // Find employee
  const emp = employeesData.find(e => parseInt(e.id, 10) === empId);
  if (!emp) {
    alert('Employee not found.');
    return;
  }
  
  // Filter attendance
  const records = attendanceData.filter(r => {
    if (parseInt(r.employee_id, 10) !== empId) return false;
    const d = r.date || '';
    return d >= startDate && d <= endDate;
  }).sort((a, b) => (a.date || '').localeCompare(b.date || ''));
  
  // Build table
  const tbody = document.getElementById('previewBody');
  tbody.innerHTML = '';
  
  let totalHours = 0;
  let daysPresent = 0;
  
  if (records.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5">No attendance records found for this period.</td></tr>';
  } else {
    records.forEach(r => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${r.date || '-'}</td>
        <td>${r.mode || '-'}</td>
        <td>${formatTime12h(r.time_in)}</td>
        <td>${formatTime12h(r.time_out)}</td>
        <td>${parseFloat(r.hours || 0).toFixed(2)}</td>
      `;
      tbody.appendChild(row);
      totalHours += parseFloat(r.hours || 0);
      if (r.time_in) daysPresent++;
    });
  }
  
  document.getElementById('totalHours').textContent = totalHours.toFixed(2);
  
  // Calculate pay - use admin-defined rates
  const payType = emp.pay_type || 'hourly';
  const hourlyRate = parseFloat(emp.hourly_rate || 0);
  const fixedHours = parseInt(emp.fixed_default_hours || 0, 10);
  const fixedDailyRate = parseFloat(emp.fixed_daily_rate || 0);
  
  let grossPay = 0;
  if (payType === 'fixed') {
    // Fixed: days present × admin-defined daily rate
    grossPay = daysPresent * fixedDailyRate;
  } else {
    // Hourly: actual hours × hourly rate
    grossPay = totalHours * hourlyRate;
  }
  
  document.getElementById('sumDays').textContent = daysPresent;
  document.getElementById('sumHours').textContent = totalHours.toFixed(2);
  document.getElementById('sumPayType').textContent = payType.charAt(0).toUpperCase() + payType.slice(1);
  document.getElementById('sumRate').textContent = payType === 'fixed' 
    ? '₱' + fixedDailyRate.toFixed(2) + '/day (' + fixedHours + 'h ref)'
    : '₱' + hourlyRate.toFixed(2) + '/hour';
  document.getElementById('sumGross').textContent = '₱' + grossPay.toFixed(2);
  
  document.getElementById('previewInfo').textContent = `Showing attendance for ${emp.name || emp.employee_code} from ${startDate} to ${endDate}`;
  document.getElementById('previewSection').style.display = 'block';
});
</script>
<?php dashboard_end(); ?>
