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

$tasks = repo_tasks();
$notice = null;
$error = null;

// Handle task completion toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  
  $action = $_POST['action'] ?? '';
  $task_id = (int)($_POST['task_id'] ?? 0);
  
  if ($action === 'complete' && $task_id) {
    // Verify task belongs to this employee
    $task = repo_find_task_by_id($task_id);
    if ($task && (int)($task['employee_id'] ?? 0) === $employee_id) {
      repo_update_task($task_id, [
        'status' => 'completed',
        'completed_at' => date('Y-m-d H:i:s')
      ]);
      $tasks = repo_tasks();
      $notice = "Task marked as completed!";
    }
  }
}

// Get my tasks
$my_tasks = array_filter($tasks, fn($t) => (int)($t['employee_id'] ?? 0) === $employee_id);

// Separate by status
$pending_tasks = array_filter($my_tasks, fn($t) => ($t['status'] ?? '') !== 'completed');
$completed_tasks = array_filter($my_tasks, fn($t) => ($t['status'] ?? '') === 'completed');

// Sort by due date
usort($pending_tasks, fn($a, $b) => strcmp($a['due_date'] ?? '9999-12-31', $b['due_date'] ?? '9999-12-31'));
usort($completed_tasks, fn($a, $b) => strcmp($b['completed_at'] ?? '', $a['completed_at'] ?? ''));

dashboard_start('My Tasks');
?>

<div class="card">
  <h3>My Tasks</h3>
  <p>Tasks assigned to you by your administrator. Mark them complete when finished.</p>
  
  <?php if ($notice): ?>
    <div class="notice"><?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>
</div>

<!-- Pending Tasks -->
<div class="card task-panel">
  <div class="task-panel-header">
    <h3>Pending Tasks</h3>
    <span class="task-count"><?= count($pending_tasks) ?></span>
  </div>
  
  <?php if (empty($pending_tasks)): ?>
    <p style="color: var(--text-muted);">No pending tasks. Great job!</p>
  <?php else: ?>
    <div class="task-list">
      <?php foreach ($pending_tasks as $t): ?>
        <?php
          $priority = $t['priority'] ?? 'medium';
          $is_overdue = !empty($t['due_date']) && $t['due_date'] < date('Y-m-d');
        ?>
        <div class="task-item <?= $is_overdue ? 'overdue' : '' ?>">
          <form method="post" style="display: contents;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="complete">
            <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
            <button type="submit" class="task-checkbox" title="Mark as complete">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
            </button>
          </form>
          <div class="task-content">
            <div class="task-title"><?= htmlspecialchars($t['title'] ?? '') ?></div>
            <?php if (!empty($t['description'])): ?>
              <p style="color: var(--text-muted); font-size: var(--text-sm); margin: var(--space-1) 0;"><?= htmlspecialchars($t['description']) ?></p>
            <?php endif; ?>
            <div class="task-meta">
              <span class="task-tag <?= htmlspecialchars($priority) ?>"><?= strtoupper($priority) ?></span>
              <?php if (!empty($t['due_date'])): ?>
                <span class="task-due <?= $is_overdue ? 'overdue' : '' ?>">
                  Due: <?= htmlspecialchars($t['due_date']) ?>
                  <?= $is_overdue ? '(OVERDUE)' : '' ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Completed Tasks -->
<div class="card task-panel">
  <div class="task-panel-header">
    <h3>Completed Tasks</h3>
    <span class="task-count"><?= count($completed_tasks) ?></span>
  </div>
  
  <?php if (empty($completed_tasks)): ?>
    <p style="color: var(--text-muted);">No completed tasks yet.</p>
  <?php else: ?>
    <div class="task-list">
      <?php foreach (array_slice($completed_tasks, 0, 10) as $t): ?>
        <div class="task-item completed">
          <div class="task-checkbox checked">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
          </div>
          <div class="task-content">
            <div class="task-title"><?= htmlspecialchars($t['title'] ?? '') ?></div>
            <div class="task-meta">
              <span class="task-due">Completed: <?= htmlspecialchars($t['completed_at'] ?? '') ?></span>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<style>
.task-due.overdue {
  color: var(--error);
  font-weight: 500;
}
.task-item.overdue {
  border-left: 3px solid var(--error);
}
</style>

<?php dashboard_end(); ?>
