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

$tasks = repo_tasks();
$employees = repo_employees();

$notice = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  
  $action = $_POST['action'] ?? '';
  
  // Add new task
  if ($action === 'add') {
    $employee_id = (int)($_POST['employee_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $due_date = trim($_POST['due_date'] ?? '');
    
    if (!$employee_id) {
      $error = "Please select an employee.";
    } elseif (empty($title)) {
      $error = "Task title is required.";
    } elseif (!in_array($priority, ['low', 'medium', 'high'])) {
      $error = "Invalid priority level.";
    } else {
      repo_create_task([
        'employee_id' => $employee_id,
        'title' => $title,
        'description' => $description,
        'priority' => $priority,
        'due_date' => $due_date,
        'status' => 'pending',
        'created_by' => current_user()['id'] ?? 0
      ]);
      
      $tasks = repo_tasks();
      $notice = "Task assigned successfully!";
    }
  }
  
  // Delete task
  if ($action === 'delete') {
    $task_id = (int)($_POST['task_id'] ?? 0);
    repo_delete_task($task_id);
    $tasks = repo_tasks();
    $notice = "Task deleted.";
  }
}

// Filter tasks
$filter_emp = (int)($_GET['employee_id'] ?? 0);
$filter_status = $_GET['status'] ?? '';

$filtered = $tasks;
if ($filter_emp) {
  $filtered = array_filter($filtered, fn($t) => (int)($t['employee_id'] ?? 0) === $filter_emp);
}
if ($filter_status) {
  $filtered = array_filter($filtered, fn($t) => ($t['status'] ?? '') === $filter_status);
}

usort($filtered, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

dashboard_start('Task Management');
?>

<div class="card">
  <h3>Task Management</h3>
  <p>Assign and manage tasks for employees. Tasks integrate with worker dashboards.</p>
  
  <?php if ($notice): ?>
    <div class="notice"><?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="notice" style="border-color:rgba(255,45,85,.35); background:rgba(255,45,85,.10);">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>
</div>

<!-- Add Task Form -->
<div class="card">
  <h3>Assign New Task</h3>
  <form method="post" class="form-grid">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <input type="hidden" name="action" value="add">
    
    <div>
      <label>Employee *</label>
      <select name="employee_id" required>
        <option value="">Select employee</option>
        <?php foreach ($employees as $e): ?>
          <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars(employee_name($e)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    
    <div>
      <label>Priority *</label>
      <select name="priority" required>
        <option value="low">Low</option>
        <option value="medium" selected>Medium</option>
        <option value="high">High</option>
      </select>
    </div>
    
    <div>
      <label>Due Date</label>
      <input type="date" name="due_date">
    </div>
    
    <div style="grid-column: 1 / -1;">
      <label>Task Title *</label>
      <input type="text" name="title" required placeholder="Enter task title">
    </div>
    
    <div style="grid-column: 1 / -1;">
      <label>Description</label>
      <textarea name="description" rows="3" placeholder="Optional task description"></textarea>
    </div>
    
    <div>
      <button type="submit" class="btn">Assign Task</button>
    </div>
  </form>
</div>

<!-- Filter -->
<div class="card">
  <form method="get" class="row" style="align-items:end; gap: var(--space-4);">
    <div style="flex:2">
      <label>Filter by Employee</label>
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
      <label>Status</label>
      <select name="status">
        <option value="">All</option>
        <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
      </select>
    </div>
    <div>
      <button type="submit" class="btn secondary">Filter</button>
    </div>
  </form>
</div>

<!-- Task List -->
<div class="card">
  <h3>All Tasks (<?= count($filtered) ?>)</h3>
  <table>
    <thead>
      <tr>
        <th>Employee</th>
        <th>Task</th>
        <th>Priority</th>
        <th>Due Date</th>
        <th>Status</th>
        <th>Created</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($filtered as $t): ?>
        <?php 
          $emp = find_by_id($employees, (int)($t['employee_id'] ?? 0));
          $priority = $t['priority'] ?? 'medium';
          $status = $t['status'] ?? 'pending';
          $is_overdue = $status !== 'completed' && !empty($t['due_date']) && $t['due_date'] < date('Y-m-d');
        ?>
        <tr>
          <td><?= htmlspecialchars($emp ? employee_name($emp) : 'Unknown') ?></td>
          <td>
            <strong><?= htmlspecialchars($t['title'] ?? '') ?></strong>
            <?php if (!empty($t['description'])): ?>
              <br><small style="color: var(--text-muted);"><?= htmlspecialchars(substr($t['description'], 0, 50)) ?><?= strlen($t['description']) > 50 ? '...' : '' ?></small>
            <?php endif; ?>
          </td>
          <td>
            <span class="task-tag <?= $priority ?>"><?= strtoupper($priority) ?></span>
          </td>
          <td>
            <?= htmlspecialchars($t['due_date'] ?? '-') ?>
            <?php if ($is_overdue): ?>
              <span style="color: var(--error); font-weight: 500;"> (OVERDUE)</span>
            <?php endif; ?>
          </td>
          <td>
            <span style="color: <?= $status === 'completed' ? 'var(--success)' : 'var(--warning)' ?>; font-weight: 500;">
              <?= strtoupper($status) ?>
            </span>
          </td>
          <td><?= htmlspecialchars($t['created_at'] ?? '') ?></td>
          <td>
            <form method="post" style="display: inline;" onsubmit="return confirm('Delete this task?');">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
              <button type="submit" class="btn secondary" style="padding: 4px 8px; font-size: 12px;">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($filtered)): ?>
        <tr><td colspan="7">No tasks found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php dashboard_end(); ?>
