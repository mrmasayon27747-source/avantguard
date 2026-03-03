<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/storage.php';
require_once __DIR__ . '/../inc/layout.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/backup.php';
require_once __DIR__ . '/../inc/audit_log.php';

require_role('admin');

$notice = null;
$error = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $result = backup_create();
        if ($result['success']) {
            $notice = $result['message'] . ' Files: ' . implode(', ', $result['files_backed_up']);
            audit_log('backup_create', ['filename' => $result['filename']]);
        } else {
            $error = $result['message'];
        }
    }
    
    if ($action === 'delete') {
        $filename = $_POST['filename'] ?? '';
        if (backup_delete($filename)) {
            $notice = "Backup deleted.";
            audit_log('backup_delete', ['filename' => $filename]);
        } else {
            $error = "Failed to delete backup.";
        }
    }
    
    if ($action === 'restore') {
        $filename = $_POST['filename'] ?? '';
        $result = backup_restore($filename);
        if ($result['success']) {
            $notice = $result['message'] . ' A pre-restore backup was created: ' . ($result['pre_restore_backup'] ?? 'N/A');
            audit_log('backup_restore', ['filename' => $filename, 'files' => $result['files_restored']]);
        } else {
            $error = $result['message'];
        }
    }
}

// Handle download
if (isset($_GET['download'])) {
    $filename = $_GET['download'];
    backup_download($filename);
    exit;
}

$backups = backup_list();

dashboard_start('Backups');
?>

<style>
.backup-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.backup-table {
  font-size: 0.85rem;
}
.backup-table td, .backup-table th {
  padding: 10px 12px;
}
.btn-restore {
  background: rgba(255, 159, 10, 0.15);
  color: #ff9f0a;
  border: 1px solid rgba(255, 159, 10, 0.3);
}
.btn-restore:hover {
  background: rgba(255, 159, 10, 0.25);
}
.info-box {
  background: rgba(10, 132, 255, 0.1);
  border: 1px solid rgba(10, 132, 255, 0.3);
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 0.85rem;
  color: var(--text-muted);
  margin-bottom: 16px;
}
</style>

<div class="card">
  <h3 style="margin-bottom: 12px;">Database Backups</h3>
  
  <div class="info-box">
    <strong>Backup Information:</strong> Backups include all data files (users, employees, attendance, payslips, deductions, tasks, reports). 
    Maximum <?= MAX_BACKUPS ?> backups are retained. Older backups are automatically deleted.
  </div>
  
  <?php if ($notice): ?>
    <div class="notice"><?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="notice" style="border-color:rgba(255,45,85,.35); background:rgba(255,45,85,.10);">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>
  
  <form method="post" style="margin-bottom: 20px;">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <input type="hidden" name="action" value="create">
    <button type="submit" class="btn">Create Backup Now</button>
  </form>
  
  <h4 style="margin-bottom: 12px;">Existing Backups (<?= count($backups) ?>)</h4>
  
  <?php if (empty($backups)): ?>
    <p style="color: var(--text-muted);">No backups yet. Create your first backup above.</p>
  <?php else: ?>
    <table class="backup-table">
      <thead>
        <tr>
          <th>Filename</th>
          <th>Size</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($backups as $backup): ?>
          <tr>
            <td><?= htmlspecialchars($backup['filename']) ?></td>
            <td><?= htmlspecialchars($backup['size_formatted']) ?></td>
            <td><?= htmlspecialchars($backup['created_formatted']) ?></td>
            <td>
              <div class="backup-actions">
                <a href="?download=<?= urlencode($backup['filename']) ?>" class="btn btn-sm">Download</a>
                <form method="post" style="display:inline;" onsubmit="return confirm('Restore this backup? Current data will be backed up first.');">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="action" value="restore">
                  <input type="hidden" name="filename" value="<?= htmlspecialchars($backup['filename']) ?>">
                  <button type="submit" class="btn btn-sm btn-restore">Restore</button>
                </form>
                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this backup?');">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="filename" value="<?= htmlspecialchars($backup['filename']) ?>">
                  <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php dashboard_end(); ?>
