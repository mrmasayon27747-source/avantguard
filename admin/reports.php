<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/storage.php';
require_once __DIR__ . '/../inc/repository.php';
require_once __DIR__ . '/../inc/layout.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/csrf.php';

require_role('admin');

$payslips = repo_payslips();
$employees = repo_employees();

$notice = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $action = $_POST['action'] ?? '';
  $id = (int)($_POST['id'] ?? 0);

  if ($action === 'release' && $id) {
    repo_update_payslip($id, [
      'status' => 'released',
      'released_at' => date('Y-m-d H:i:s')
    ]);
    $notice = "Payslip released. Worker can now see it.";
    $payslips = repo_payslips();
  }

  if ($action === 'delete' && $id) {
    repo_delete_payslip($id);
    $payslips = repo_payslips();
    $notice = "Payslip deleted.";
  }
}

usort($payslips, fn($a,$b) => (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0));

dashboard_start('Reports');
?>

  <div class="card">
    <h3>Reports / Payslips</h3>
    <p>Draft payslips are hidden from workers until released. Delete a payslip if you need to recreate it with different dates.</p>

    <?php if ($notice): ?>
      <div class="notice"><?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="notice" style="border-color:rgba(255,45,85,.35); background:rgba(255,45,85,.10);">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>Payslip List</h3>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Employee</th>
          <th>Period</th>
          <th>Gross</th>
          <th>Status</th>
          <th>Released At</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payslips as $p): ?>
          <?php
            $status = (string)($p['status'] ?? 'released'); // backward compatible
          ?>
          <tr>
            <td><?= (int)($p['id'] ?? 0) ?></td>
            <td><?= htmlspecialchars((string)($p['employee_name'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string)($p['period_start'] ?? '') . ' → ' . (string)($p['period_end'] ?? '')) ?></td>
            <td><?= number_format((float)($p['gross_pay'] ?? 0), 2) ?></td>
            <td><b><?= htmlspecialchars(strtoupper($status)) ?></b></td>
            <td><?= htmlspecialchars((string)($p['released_at'] ?? '-')) ?></td>
            <td>
              <div class="emp-btns">
              <?php if ($status !== 'released'): ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="action" value="release">
                  <input type="hidden" name="id" value="<?= (int)($p['id'] ?? 0) ?>">
                  <button class="btn xs" type="submit">Release</button>
                </form>
              <?php endif; ?>
                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this payslip? This cannot be undone.')">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)($p['id'] ?? 0) ?>">
                  <button class="btn danger xs" type="submit">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (count($payslips) === 0): ?>
          <tr><td colspan="7">No payslips yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php dashboard_end(); ?>
