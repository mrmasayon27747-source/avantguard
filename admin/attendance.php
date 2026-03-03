<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/storage.php';
require_once __DIR__ . '/../inc/repository.php';
require_once __DIR__ . '/../inc/layout.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/pagination.php';

require_role('admin');

$employees = repo_employees();
$attendance = repo_attendance();

$notice = null;
$error = null;

// Helper function to parse time
function parse_time_admin(string $t): ?string {
  $t = trim($t);
  $formats = ['g:i A', 'g:iA', 'g:i a', 'g:ia', 'h:i A', 'h:iA', 'h:i a', 'h:ia', 'H:i'];
  foreach ($formats as $fmt) {
    $dt = DateTime::createFromFormat($fmt, $t);
    if ($dt !== false) return $dt->format('H:i');
  }
  return null;
}

// Helper function to calculate hours (supports overnight)
function calc_hours(string $date, string $time_in, string $time_out): float {
  if (empty($time_in) || empty($time_out)) return 0.0;
  $in_ts = strtotime($date . ' ' . $time_in);
  $out_ts = strtotime($date . ' ' . $time_out);
  if ($out_ts !== false && $in_ts !== false && $out_ts < $in_ts) {
    $out_ts += 86400; // past midnight
  }
  if ($in_ts === false || $out_ts === false) return 0.0;
  return max(0, ($out_ts - $in_ts) / 3600);
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  // UPDATE attendance record
  if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $date = trim($_POST['date'] ?? '');
    $time_in_raw = trim($_POST['time_in'] ?? '');
    $time_out_raw = trim($_POST['time_out'] ?? '');

    $time_in = parse_time_admin($time_in_raw);
    $time_out = !empty($time_out_raw) ? parse_time_admin($time_out_raw) : '';

    if (!$id) {
      $error = "Invalid record.";
    } elseif (empty($date)) {
      $error = "Date is required.";
    } elseif (!$time_in) {
      $error = "Valid time in is required.";
    } else {
      $updated = false;
      foreach ($attendance as $r) {
        if ((int)($r['id'] ?? 0) === $id) {
          $hours = round(calc_hours($date, $time_in, $time_out ?: ''), 2);
          repo_update_attendance($id, [
            'date' => $date,
            'time_in' => $time_in,
            'time_out' => $time_out ?: '',
            'hours' => $hours,
            'updated_by' => 'admin'
          ]);
          $updated = true;
          break;
        }
      }

      if ($updated) {
        $notice = "Attendance record updated.";
      } else {
        $error = "Record not found.";
      }
    }
  }

  // DELETE attendance record
  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $found = false;
    foreach ($attendance as $r) {
      if ((int)($r['id'] ?? 0) === $id) {
        $found = true;
        break;
      }
    }
    if ($found) {
      repo_delete_attendance($id);
      $notice = "Attendance record deleted.";
    } else {
      $error = "Record not found.";
    }
  }

  // ADD attendance record manually
  if ($action === 'add') {
    $employee_id = (int)($_POST['employee_id'] ?? 0);
    $date = trim($_POST['date'] ?? '');
    $time_in_raw = trim($_POST['time_in'] ?? '');
    $time_out_raw = trim($_POST['time_out'] ?? '');

    $time_in = parse_time_admin($time_in_raw);
    $time_out = !empty($time_out_raw) ? parse_time_admin($time_out_raw) : '';

    $emp = find_by_id($employees, $employee_id);

    if (!$emp) {
      $error = "Please select a valid employee.";
    } elseif (empty($date)) {
      $error = "Date is required.";
    } elseif (!$time_in) {
      $error = "Valid time in is required.";
    } else {
      // Check for duplicate date for this employee
      $duplicate = false;
      foreach ($attendance as $r) {
        if ((int)($r['employee_id'] ?? 0) === $employee_id && ($r['date'] ?? '') === $date) {
          $duplicate = true;
          break;
        }
      }

      if ($duplicate) {
        $error = "Attendance already exists for this employee on this date.";
      } else {
        $mode = (string)($emp['pay_type'] ?? 'hourly');
        $hours = round(calc_hours($date, $time_in, $time_out ?: ''), 2);

        try {
          repo_create_attendance([
            'employee_id' => $employee_id,
            'mode' => $mode,
            'date' => $date,
            'time_in' => $time_in,
            'time_out' => $time_out ?: '',
            'fixed_hours' => ($mode === 'fixed') ? (int)($emp['fixed_default_hours'] ?? 0) : 0,
            'hours' => $hours,
            'created_by' => 'admin'
          ]);
          $notice = "Attendance record added.";
        } catch (PDOException $e) {
          $error = "Database error: " . $e->getMessage();
        }
      }
    }
  }

  // Reload after writes
  $attendance = repo_attendance();
}

// Filter and display
$att = $attendance;
$filter_emp = (int)($_GET['employee_id'] ?? 0);
$filter_date_from = trim($_GET['date_from'] ?? '');
$filter_date_to   = trim($_GET['date_to'] ?? '');

if ($filter_emp) {
  $att = array_values(array_filter($att, fn($r) => (int)$r['employee_id'] === $filter_emp));
}
if ($filter_date_from) {
  $att = array_values(array_filter($att, fn($r) => ($r['date'] ?? '') >= $filter_date_from));
}
if ($filter_date_to) {
  $att = array_values(array_filter($att, fn($r) => ($r['date'] ?? '') <= $filter_date_to));
}

usort($att, fn($a,$b) => strcmp(($b['date'] ?? '').(string)($b['id'] ?? 0), ($a['date'] ?? '').(string)($a['id'] ?? 0)));

// Pagination
$pg_params = get_pagination_params();
$pg = paginate_array($att, $pg_params['page'], $pg_params['per_page']);
$att = $pg['items'];

// Helper to format time for display
function format_time_display_admin(string $t): string {
  if (empty($t)) return '';
  $dt = DateTime::createFromFormat('H:i', $t);
  if ($dt === false) $dt = DateTime::createFromFormat('H:i:s', $t);
  return $dt ? $dt->format('g:i A') : $t;
}

dashboard_start('Attendance');
?>

  <div class="card">
    <h3>Attendance Management</h3>
    <p>View, edit, delete, or manually add attendance records.</p>

    <?php if ($notice): ?>
      <div class="notice"><?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="notice" style="border-color:rgba(255,45,85,.35); background:rgba(255,45,85,.10);">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="get" class="row" style="align-items:end; flex-wrap:wrap; gap:10px;">
      <div style="flex:2; min-width:150px;">
        <label>Employee</label>
        <select name="employee_id">
          <option value="0">All</option>
          <?php foreach ($employees as $e): ?>
            <option value="<?= (int)$e['id'] ?>" <?= $filter_emp === (int)$e['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars(employee_name($e)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="flex:1; min-width:130px;">
        <label>From</label>
        <input type="date" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>">
      </div>
      <div style="flex:1; min-width:130px;">
        <label>To</label>
        <input type="date" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>">
      </div>
      <div style="flex:0 0 auto; align-self:end;">
        <button class="btn" type="submit">Filter</button>
      </div>
    </form>
  </div>

  <!-- Add Attendance Manually -->
  <div class="card">
    <h3>Add Attendance Manually</h3>
    <form method="post" class="row" style="align-items:end; flex-wrap:wrap; gap:12px;">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="add">
      <div style="flex:2; min-width:150px;">
        <label>Employee</label>
        <select name="employee_id" required>
          <option value="">Select</option>
          <?php foreach ($employees as $e): ?>
            <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars(employee_name($e)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="flex:1; min-width:120px;">
        <label>Date</label>
        <input type="date" name="date" required>
      </div>
      <div style="flex:1; min-width:140px;">
        <label>Time In</label>
        <input type="text" name="time_in" placeholder="Select time" required class="time-picker-input">
      </div>
      <div style="flex:1; min-width:140px;">
        <label>Time Out</label>
        <input type="text" name="time_out" placeholder="Select time" class="time-picker-input">
      </div>
      <div style="flex:0 0 auto; align-self:end;">
        <button class="btn" type="submit">Add</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h3>Records</h3>
    <?php render_pagination_styles(); ?>
    <div class="emp-table-wrap">
    <table class="compact">
      <thead>
        <tr>
          <th>Date</th>
          <th>Employee</th>
          <th>Mode</th>
          <th>Time In</th>
          <th>Time Out</th>
          <th>Hours</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($att as $r): ?>
          <?php 
            $e = find_by_id($employees, (int)$r['employee_id']);
            $isEditing = isset($_GET['edit']) && (int)$_GET['edit'] === (int)$r['id'];
          ?>
          <tr>
            <?php if (!$isEditing): ?>
              <td><?= htmlspecialchars($r['date'] ?? '') ?></td>
              <td><?= htmlspecialchars($e ? ($e['name'] ?? 'Unknown') : 'Unknown') ?></td>
              <td><?= htmlspecialchars($r['mode'] ?? '') ?></td>
              <td><?= htmlspecialchars(format_time_display_admin($r['time_in'] ?? '')) ?></td>
              <td><?= htmlspecialchars(format_time_display_admin($r['time_out'] ?? '')) ?></td>
              <td><?= number_format((float)($r['hours'] ?? 0), 2) ?></td>
              <td>
                <div class="emp-btns">
                  <a href="?edit=<?= (int)$r['id'] ?>&employee_id=<?= $filter_emp ?>&date_from=<?= urlencode($filter_date_from) ?>&date_to=<?= urlencode($filter_date_to) ?>" class="btn xs">Edit</a>
                  <form method="post" style="display:inline;" onsubmit="return confirm('Delete this attendance record?')">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn danger xs" type="submit">Del</button>
                  </form>
                </div>
              </td>
            <?php else: ?>
              <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <td><input type="date" name="date" value="<?= htmlspecialchars($r['date'] ?? '') ?>" required style="width:130px;"></td>
                <td><?= htmlspecialchars($e ? ($e['name'] ?? 'Unknown') : 'Unknown') ?></td>
                <td><?= htmlspecialchars($r['mode'] ?? '') ?></td>
                <td><input type="text" name="time_in" value="<?= htmlspecialchars(format_time_display_admin($r['time_in'] ?? '')) ?>" required style="width:130px;" placeholder="Select time" class="time-picker-input"></td>
                <td><input type="text" name="time_out" value="<?= htmlspecialchars(format_time_display_admin($r['time_out'] ?? '')) ?>" style="width:130px;" placeholder="Select time" class="time-picker-input"></td>
                <td><?= number_format((float)($r['hours'] ?? 0), 2) ?></td>
                <td>
                  <button class="btn xs" type="submit">Save</button>
                  <a href="attendance.php?employee_id=<?= $filter_emp ?>&date_from=<?= urlencode($filter_date_from) ?>&date_to=<?= urlencode($filter_date_to) ?>" class="btn secondary xs">X</a>
                </td>
              </form>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        <?php if (count($att) === 0): ?>
          <tr><td colspan="7">No attendance records.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
    <?php render_pagination($pg); ?>
  </div>
<?php dashboard_end(); ?>
