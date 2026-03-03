<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/storage.php';
require_once __DIR__ . '/../inc/layout.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/repository.php';

require_role('worker');

$u = current_user();
$employee_id = (int)($u['employee_id'] ?? 0);
$today = date('Y-m-d');

$eod_reports = repo_eod();
$notice = null;
$error = null;

// Check if already submitted today
$submitted_today = false;
foreach ($eod_reports as $r) {
  if ((int)($r['employee_id'] ?? 0) === $employee_id && ($r['date'] ?? '') === $today) {
    $submitted_today = true;
    break;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  
  $selected_date = trim($_POST['date'] ?? $today);
  
  // Check if already submitted for selected date
  $already_submitted = false;
  foreach ($eod_reports as $r) {
    if ((int)($r['employee_id'] ?? 0) === $employee_id && ($r['date'] ?? '') === $selected_date) {
      $already_submitted = true;
      break;
    }
  }
  
  if ($already_submitted) {
    $error = "You have already submitted an EOD report for this date.";
  } else {
    $tasks_completed = trim($_POST['tasks_completed'] ?? '');
    $pending_concerns = trim($_POST['pending_concerns'] ?? '');
    
    // Determine notes_status automatically
    $has_finished = !empty($tasks_completed);
    $has_pending = !empty($pending_concerns);
    if ($has_finished && $has_pending) {
      $notes_status = 'finished'; // primary status
    } elseif ($has_finished) {
      $notes_status = 'finished';
    } elseif ($has_pending) {
      $notes_status = 'pending';
    } else {
      $notes_status = 'finished';
    }
    
    if (empty($tasks_completed) && empty($pending_concerns)) {
      $error = "Please fill in at least one of the panels.";
    } else {
      $photo_path = null;
      
      // Handle optional photo upload
      if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, $allowed)) {
          $error = "Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.";
        } elseif ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
          $error = "File too large. Maximum size is 5MB.";
        } else {
          $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
          $filename = 'eod_' . $employee_id . '_' . $selected_date . '_' . time() . '.' . $ext;
          $upload_path = EOD_UPLOADS_DIR . DIRECTORY_SEPARATOR . $filename;
          
          if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
            $photo_path = 'uploads/eod/' . $filename;
          } else {
            $error = "Failed to upload photo. Please try again.";
          }
        }
      }
      
      if (!$error) {
        if ($employee_id <= 0) {
          $error = "Your account is not linked to an employee profile. Please contact admin.";
        } else {
          try {
            repo_create_eod([
              'employee_id' => $employee_id,
              'date' => $selected_date,
              'tasks_completed' => $tasks_completed,
              'pending_concerns' => $pending_concerns,
              'notes_status' => $notes_status,
              'photo' => $photo_path
            ]);
            
            $eod_reports = repo_eod();
            $notice = "EOD report submitted successfully!";
            if ($selected_date === $today) {
              $submitted_today = true;
            }
          } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
          }
        }
      }
    }
  }
}

// Get my EOD reports
$my_reports = array_filter($eod_reports, fn($r) => (int)($r['employee_id'] ?? 0) === $employee_id);
usort($my_reports, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
$my_reports = array_slice($my_reports, 0, 30);

dashboard_start('EOD Report');
?>

<div class="card">
  <h3>End of Day Report</h3>
  <p>Submit your daily report before logging off. You can only submit once per day.</p>
  
  <?php if ($notice): ?>
    <div class="notice"><?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="notice" style="border-color:rgba(255,45,85,.35); background:rgba(255,45,85,.10);">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>
  
  <?php if ($submitted_today): ?>
    <div class="notice" style="border-color:rgba(76,175,80,.35); background:rgba(76,175,80,.10);">
      ✓ You have already submitted your EOD report for today (<?= htmlspecialchars($today) ?>).
    </div>
  <?php else: ?>
    <form method="post" enctype="multipart/form-data" class="profile-form" style="max-width: 700px;">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      
      <div class="form-group full-width">
        <label>Date *</label>
        <input type="date" name="date" value="<?= htmlspecialchars($today) ?>" max="<?= htmlspecialchars($today) ?>" required>
      </div>
      
      <div class="form-group full-width">
        <div class="eod-panels">
          <div class="eod-panel eod-panel-finished">
            <div class="eod-panel-header finished">
              <span class="eod-panel-icon">✓</span>
              <span class="eod-panel-title">Finished</span>
            </div>
            <textarea name="tasks_completed" rows="6" placeholder="List your completed tasks for today..."></textarea>
          </div>
          <div class="eod-panel eod-panel-pending">
            <div class="eod-panel-header pending">
              <span class="eod-panel-icon">⏳</span>
              <span class="eod-panel-title">Pending / Concern</span>
            </div>
            <textarea name="pending_concerns" rows="6" placeholder="List any pending tasks or concerns..."></textarea>
          </div>
        </div>
        <p class="eod-panel-hint">Fill in at least one panel. Both are optional individually.</p>
      </div>
      
      <div class="form-group full-width">
        <label>Attach Photo (Optional)</label>
        <div class="upload-area" onclick="document.getElementById('photo-input').click();">
          <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="17 8 12 3 7 8"/>
            <line x1="12" y1="3" x2="12" y2="15"/>
          </svg>
          <p class="upload-text">Click to upload or drag and drop</p>
          <p class="upload-hint">JPEG, PNG, GIF, WebP (max 5MB)</p>
          <div class="upload-preview" id="photo-preview"></div>
        </div>
        <input type="file" name="photo" id="photo-input" accept="image/*" style="display: none;" onchange="previewImage(this)">
      </div>
      
      <div class="form-group full-width">
        <button type="submit" class="btn">Submit EOD Report</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <h3>Your Recent EOD Reports</h3>
  <table>
    <thead>
      <tr>
        <th>Date</th>
        <th>Finished Tasks</th>
        <th>Pending / Concern</th>
        <th>Photo</th>
        <th>Submitted</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($my_reports as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['date'] ?? '') ?></td>
          <td style="max-width: 300px; white-space: pre-wrap;"><?php $tc = $r['tasks_completed'] ?? ''; echo $tc ? htmlspecialchars($tc) : '<span style="color:var(--text-muted);">-</span>'; ?></td>
          <td style="max-width: 300px; white-space: pre-wrap;"><?php $pc = $r['pending_concerns'] ?? ''; echo $pc ? htmlspecialchars($pc) : '<span style="color:var(--text-muted);">-</span>'; ?></td>
          <td>
            <?php if (!empty($r['photo'])): ?>
              <a href="/avantguard/<?= htmlspecialchars($r['photo']) ?>" target="_blank">View</a>
            <?php else: ?>
              -
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($r['created_at'] ?? ''))) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($my_reports)): ?>
        <tr><td colspan="5">No EOD reports yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<style>
/* EOD Dual Panels */
.eod-panels {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
@media (max-width: 700px) {
  .eod-panels { grid-template-columns: 1fr; }
}
.eod-panel {
  border: 1px solid var(--border-default);
  border-radius: var(--radius-lg);
  overflow: hidden;
  background: var(--surface-glass);
  transition: border-color 0.2s ease;
}
.eod-panel:focus-within {
  border-color: var(--purple-400);
  box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.12);
}
.eod-panel-header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  font-weight: 600;
  font-size: 0.9rem;
}
.eod-panel-header.finished {
  background: rgba(52, 199, 89, 0.12);
  color: #34c759;
  border-bottom: 1px solid rgba(52, 199, 89, 0.2);
}
.eod-panel-header.pending {
  background: rgba(255, 159, 10, 0.12);
  color: #ff9f0a;
  border-bottom: 1px solid rgba(255, 159, 10, 0.2);
}
.eod-panel-icon {
  font-size: 1.1rem;
}
.eod-panel textarea {
  width: 100%;
  border: none;
  background: transparent;
  padding: 12px 14px;
  color: var(--text-primary);
  font-family: inherit;
  font-size: var(--text-sm);
  resize: vertical;
  min-height: 120px;
  outline: none;
}
.eod-panel textarea::placeholder {
  color: var(--text-muted);
}
.eod-panel-hint {
  margin-top: 8px;
  font-size: 0.8rem;
  color: var(--text-muted);
}

/* Light mode panel overrides */
[data-theme="light"] .eod-panel {
  background: #f8f7fc;
  border-color: rgba(0, 0, 0, 0.1);
}
[data-theme="light"] .eod-panel:focus-within {
  border-color: var(--purple-400);
}
[data-theme="light"] .eod-panel-header.finished {
  background: rgba(52, 199, 89, 0.08);
  color: #248a3d;
  border-bottom-color: rgba(52, 199, 89, 0.15);
}
[data-theme="light"] .eod-panel-header.pending {
  background: rgba(255, 159, 10, 0.08);
  color: #c77c02;
  border-bottom-color: rgba(255, 159, 10, 0.15);
}
[data-theme="light"] .eod-panel textarea {
  color: #1a0a2e;
}
[data-theme="light"] .eod-panel textarea::placeholder {
  color: #9ca3af;
}

/* Keep EOD status badge styles for history table */
.eod-status-badge {
  display: inline-block;
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 600;
  border: 2px solid transparent;
}
.eod-status-badge.finished {
  background: rgba(52, 199, 89, 0.15);
  color: #34c759;
}
.eod-status-badge.pending {
  background: rgba(255, 159, 10, 0.15);
  color: #ff9f0a;
}
[data-theme="light"] .eod-status-badge.finished { color: #248a3d; }
[data-theme="light"] .eod-status-badge.pending { color: #c77c02; }
</style>

<script>
function previewImage(input) {
  const preview = document.getElementById('photo-preview');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
    };
    reader.readAsDataURL(input.files[0]);
  }
}

// Drag and drop support
const uploadArea = document.querySelector('.upload-area');
if (uploadArea) {
  ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    uploadArea.addEventListener(eventName, (e) => {
      e.preventDefault();
      e.stopPropagation();
    });
  });
  
  ['dragenter', 'dragover'].forEach(eventName => {
    uploadArea.addEventListener(eventName, () => uploadArea.classList.add('dragover'));
  });
  
  ['dragleave', 'drop'].forEach(eventName => {
    uploadArea.addEventListener(eventName, () => uploadArea.classList.remove('dragover'));
  });
  
  uploadArea.addEventListener('drop', (e) => {
    const files = e.dataTransfer.files;
    if (files.length) {
      document.getElementById('photo-input').files = files;
      previewImage(document.getElementById('photo-input'));
    }
  });
}
</script>

<?php dashboard_end(); ?>
