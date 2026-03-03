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

$devotionals = repo_devotionals();
$notice = null;
$error = null;

// Check if already submitted today
$submitted_today = false;
foreach ($devotionals as $d) {
  if ((int)($d['employee_id'] ?? 0) === $employee_id && ($d['date'] ?? '') === $today) {
    $submitted_today = true;
    break;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  
  $selected_date = trim($_POST['date'] ?? $today);
  
  // Check if already submitted for selected date
  $already_submitted = false;
  foreach ($devotionals as $d) {
    if ((int)($d['employee_id'] ?? 0) === $employee_id && ($d['date'] ?? '') === $selected_date) {
      $already_submitted = true;
      break;
    }
  }
  
  if ($already_submitted) {
    $error = "You have already submitted a devotional for this date.";
  } elseif (empty($_FILES['photo']['name']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $error = "Please upload a photo.";
  } else {
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
      $filename = 'devotional_' . $employee_id . '_' . $selected_date . '_' . time() . '.' . $ext;
      $upload_path = DEVOTIONAL_UPLOADS_DIR . DIRECTORY_SEPARATOR . $filename;
      
      if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
        repo_create_devotional([
          'employee_id' => $employee_id,
          'date' => $selected_date,
          'photo' => 'uploads/devotional/' . $filename
        ]);
        
        $devotionals = repo_devotionals();
        $notice = "Devotional uploaded successfully!";
        if ($selected_date === $today) {
          $submitted_today = true;
        }
      } else {
        $error = "Failed to upload photo. Please try again.";
      }
    }
  }
}

// Get my devotionals
$my_devotionals = array_filter($devotionals, fn($d) => (int)($d['employee_id'] ?? 0) === $employee_id);
usort($my_devotionals, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
$my_devotionals = array_slice($my_devotionals, 0, 30);

dashboard_start('Devotional');
?>

<div class="card">
  <h3>Daily Devotional</h3>
  <p>Upload your spiritual reflection for today. You can only submit once per day.</p>
  
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
      ✓ You have already submitted your devotional for today (<?= htmlspecialchars($today) ?>).
    </div>
  <?php else: ?>
    <form method="post" enctype="multipart/form-data" style="max-width: 500px;">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      
      <div class="form-group" style="margin-bottom: var(--space-4);">
        <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Date *</label>
        <input type="date" name="date" value="<?= htmlspecialchars($today) ?>" max="<?= htmlspecialchars($today) ?>" required style="width: 100%; padding: var(--space-3); background: var(--surface-glass); border: 1px solid var(--border-default); border-radius: var(--radius-md); color: var(--text-primary);">
      </div>
      
      <div class="form-group" style="margin-bottom: var(--space-4);">
        <label style="display: block; margin-bottom: var(--space-2); font-weight: 500;">Upload Photo *</label>
        <div class="upload-area" onclick="document.getElementById('photo-input').click();">
          <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
          </svg>
          <p class="upload-text">Click to upload your devotional</p>
          <p class="upload-hint">JPEG, PNG, GIF, WebP (max 5MB)</p>
          <div class="upload-preview" id="photo-preview"></div>
        </div>
        <input type="file" name="photo" id="photo-input" accept="image/*" style="display: none;" onchange="previewImage(this)" required>
      </div>
      
      <button type="submit" class="btn">Upload Devotional</button>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <h3>Your Recent Devotionals</h3>
  <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: var(--space-4);">
    <?php foreach ($my_devotionals as $d): ?>
      <div style="background: var(--surface-glass); border-radius: var(--radius-md); overflow: hidden;">
        <a href="/avantguard/<?= htmlspecialchars($d['photo'] ?? '') ?>" target="_blank">
          <img src="/avantguard/<?= htmlspecialchars($d['photo'] ?? '') ?>" alt="Devotional" style="width: 100%; height: 150px; object-fit: cover;">
        </a>
        <div style="padding: var(--space-3);">
          <p style="margin: 0; font-size: var(--text-sm); color: var(--text-secondary);"><?= htmlspecialchars($d['date'] ?? '') ?></p>
          <p style="margin: 4px 0 0; font-size: var(--text-xs); color: var(--text-muted);">Uploaded: <?= htmlspecialchars(date('g:i A', strtotime($d['created_at'] ?? ''))) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (empty($my_devotionals)): ?>
      <p style="color: var(--text-muted);">No devotionals yet.</p>
    <?php endif; ?>
  </div>
</div>

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
