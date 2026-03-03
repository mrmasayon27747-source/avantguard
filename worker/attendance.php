<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/storage.php';
require_once __DIR__ . '/../inc/repository.php';
require_once __DIR__ . '/../inc/layout.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/csrf.php';

require_role('worker');

$u = current_user();
$employee_id = (int)($u['employee_id'] ?? 0);

$employees = repo_employees();
$attendance = repo_attendance();

$emp = find_by_id($employees, $employee_id);

$notice = null;
$error = null;

function valid_date(string $d): bool {
  $dt = DateTime::createFromFormat('Y-m-d', $d);
  return $dt && $dt->format('Y-m-d') === $d;
}

// Parse time in various formats (12-hour with AM/PM or 24-hour)
function parse_time(string $t): ?string {
  $t = trim($t);
  // Try 12-hour format with AM/PM (e.g., "8:00 AM", "8:00AM", "8:00 am")
  $formats = ['g:i A', 'g:iA', 'g:i a', 'g:ia', 'h:i A', 'h:iA', 'h:i a', 'h:ia', 'H:i'];
  foreach ($formats as $fmt) {
    $dt = DateTime::createFromFormat($fmt, $t);
    if ($dt !== false) {
      return $dt->format('H:i'); // Return in 24-hour for storage
    }
  }
  return null;
}

function valid_time(string $t): bool {
  return parse_time($t) !== null;
}

// Format time for display (12-hour with AM/PM)
function format_time_display(string $t): string {
  // Only truly empty values should show as not set
  // Note: '00:00' is midnight (12:00 AM) and IS a valid time
  if ($t === '' || $t === null) return '-';
  $dt = DateTime::createFromFormat('H:i', $t);
  if ($dt === false) {
    $dt = DateTime::createFromFormat('H:i:s', $t);
  }
  return $dt ? $dt->format('g:i A') : '-';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  if ($action === 'time_in') {
    $date = trim($_POST['date'] ?? '');
    $time_in_raw = trim($_POST['time_in'] ?? '');
    $time_in = parse_time($time_in_raw);

    if (!$emp) {
      $error = "Employee profile not found.";
    } elseif (!valid_date($date)) {
      $error = "Please choose a valid date.";
    } elseif (!$time_in) {
      $error = "Please choose a valid time-in.";
    } else {
      // ✅ One record per user per date
      foreach ($attendance as $r) {
        if ((int)($r['employee_id'] ?? 0) === $employee_id && (string)($r['date'] ?? '') === $date) {
          $error = "You already submitted attendance for this date.";
          break;
        }
      }

      if (!$error) {
        $mode = (string)($emp['pay_type'] ?? 'hourly'); // hourly | fixed
        $fixed_hours = (float)($emp['fixed_hours'] ?? 0);

        // For fixed: hours are fixed immediately, no time-out edit needed
        $hours = 0.0;
        if ($mode === 'fixed') {
          $hours = $fixed_hours > 0 ? $fixed_hours : 0.0;
        }

        repo_create_attendance([
          'employee_id' => $employee_id,
          'mode' => $mode,
          'date' => $date,
          'time_in' => $time_in,
          'time_out' => '',
          'fixed_hours' => $fixed_hours,
          'hours' => $hours,
          'created_by' => $u['username'] ?? 'worker'
        ]);

        $notice = "Time-in saved.";
      }
    }
  }

  if ($action === 'time_out') {
    $id = (int)($_POST['id'] ?? 0);
    $time_out_raw = trim($_POST['time_out'] ?? '');
    $time_out = parse_time($time_out_raw);

    if (!$id) {
      $error = "Invalid attendance record.";
    } elseif (!$time_out) {
      $error = "Please enter a valid time (e.g., 5:00 PM).";
    } else {
      $updated = false;

      foreach ($attendance as $r) {
        if ((int)($r['id'] ?? 0) !== $id) continue;
        if ((int)($r['employee_id'] ?? 0) !== $employee_id) continue;

        // ✅ only allow if currently empty (edit ONCE)
        if (!empty($r['time_out'])) {
          $error = "Time-out already submitted. No further edits allowed.";
          break;
        }

        $date = (string)($r['date'] ?? '');
        $time_in_val = (string)($r['time_in'] ?? '');

        // Calculate duration (supports overnight shift)
        $in_ts = strtotime($date . ' ' . $time_in_val);
        $out_ts = strtotime($date . ' ' . $time_out);
        if ($out_ts !== false && $in_ts !== false && $out_ts < $in_ts) {
          $out_ts += 86400; // past midnight
        }

        if ($in_ts === false || $out_ts === false) {
          $error = "Invalid time calculation.";
          break;
        }

        $hrs = max(0, ($out_ts - $in_ts) / 3600);

        repo_update_attendance($id, [
          'time_out' => $time_out,
          'hours' => round($hrs, 2),
          'updated_by' => $u['username'] ?? 'worker'
        ]);
        $updated = true;
        break;
      }

      if (!$error && $updated) {
        $notice = "Time-out saved.";
      } elseif (!$error && !$updated) {
        $error = "Record not found.";
      }
    }
  }
  // Reload attendance after any successful POST to show updated records
  $attendance = repo_attendance();
}

// Show only this worker’s records
$mine = array_values(array_filter($attendance, fn($r) => (int)($r['employee_id'] ?? 0) === $employee_id));
usort($mine, fn($a,$b) => strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? '')));

dashboard_start('Attendance');
?>

  <div class="card">
    <h3>Attendance</h3>
    <p>Pick a date, enter your time-in, then submit. If hourly, you can submit time-out once.</p>

    <?php if ($notice): ?>
      <div class="notice"><?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="notice" style="border-color:rgba(255,45,85,.35); background:rgba(255,45,85,.10);">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="post" class="form-grid" id="timeInForm" onsubmit="return showPrayingConfirm(event)">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="time_in">

      <div>
        <label>Date</label>
        <input type="date" name="date" required class="date-input">
      </div>

      <div>
        <label>Time In</label>
        <input type="text" name="time_in" required placeholder="Select time" class="time-picker-input">
      </div>

      <div class="form-actions">
        <button class="btn" type="submit">Submit Time In</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h3>Your Records</h3>
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Mode</th>
          <th>Time In</th>
          <th>Time Out</th>
          <th>Hours</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($mine as $r): ?>
          <?php
            $mode = (string)($r['mode'] ?? '');
            $raw_time_out = $r['time_out'] ?? null;
            // Check if time_out is truly set - NULL or empty string means not set
            // Note: '00:00' is midnight and IS a valid time-out
            $has_time_out = ($raw_time_out !== null && $raw_time_out !== '');
          ?>
          <tr>
            <td><?= htmlspecialchars((string)($r['date'] ?? '')) ?></td>
            <td><?= htmlspecialchars($mode) ?></td>
            <td><?= format_time_display((string)($r['time_in'] ?? '')) ?></td>
            <td><?= format_time_display((string)($r['time_out'] ?? '')) ?></td>
            <td><?= number_format((float)($r['hours'] ?? 0), 2) ?></td>
            <td>
              <?php
                $is_editing = isset($_GET['edit']) && (string)$_GET['edit'] === (string)($r['id'] ?? '');
              ?>

              <?php if (!$has_time_out && !$is_editing): ?>
                <a class="btn sm" href="attendance.php?edit=<?= (int)($r['id'] ?? 0) ?>">Edit Time Out</a>

              <?php elseif (!$has_time_out && $is_editing): ?>
                <form method="post" class="inline-form">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="action" value="time_out">
                  <input type="hidden" name="id" value="<?= (int)($r['id'] ?? 0) ?>">
                  <input type="text" name="time_out" required placeholder="Select time" class="time-picker-input">
                  <button class="btn sm" type="submit">Save</button>
                  <a class="btn secondary sm" href="attendance.php">Cancel</a>
                </form>

              <?php else: ?>
                <span class="muted">✓ Done</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (count($mine) === 0): ?>
          <tr><td colspan="6">No attendance records yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

<!-- Done Praying Modal -->
<div id="prayingModal" class="praying-modal" style="display:none;">
  <div class="praying-modal-content">
    <h3>Done praying?</h3>
    <p>Please confirm before submitting your time in.</p>
    <div class="praying-modal-buttons">
      <button type="button" class="btn" onclick="confirmPraying()">Yes</button>
      <button type="button" class="btn secondary" onclick="closePrayingModal()">No</button>
    </div>
  </div>
</div>

<style>
.praying-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.95);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}
.praying-modal-content {
  background: #1e1033;
  border-radius: 12px;
  padding: 24px 32px;
  text-align: center;
  max-width: 320px;
}
.praying-modal-content h3 {
  margin: 0 0 12px 0;
  font-size: 1.3rem;
}
.praying-modal-content p {
  margin: 0 0 20px 0;
  color: var(--text-muted);
}
.praying-modal-buttons {
  display: flex;
  gap: 12px;
  justify-content: center;
}
</style>

<script>
let pendingSubmit = false;

function showPrayingConfirm(event) {
  event.preventDefault();
  document.getElementById('prayingModal').style.display = 'flex';
  return false;
}

function confirmPraying() {
  closePrayingModal();
  document.getElementById('timeInForm').removeAttribute('onsubmit');
  document.getElementById('timeInForm').submit();
}

function closePrayingModal() {
  document.getElementById('prayingModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('prayingModal')?.addEventListener('click', function(e) {
  if (e.target === this) closePrayingModal();
});
</script>

<?php dashboard_end(); ?>
