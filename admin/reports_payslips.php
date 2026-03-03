<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/storage.php';
require_once __DIR__ . '/../inc/layout.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/repository.php';
require_once __DIR__ . '/../inc/pagination.php';

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
      'status' => 'released'
    ]);
    $payslips = repo_payslips();
    $notice = "Payslip released. Worker can now see it.";
  }
  
  if ($action === 'delete' && $id) {
    repo_delete_payslip($id);
    $payslips = repo_payslips();
    $notice = "Payslip deleted.";
  }
}

// Filter
$filter_emp = (int)($_GET['employee_id'] ?? 0);
$filter_status = $_GET['status'] ?? '';
$filter_date_from = trim($_GET['date_from'] ?? '');
$filter_date_to   = trim($_GET['date_to'] ?? '');

$filtered = $payslips;
if ($filter_emp) {
  $filtered = array_filter($filtered, fn($p) => (int)($p['employee_id'] ?? 0) === $filter_emp);
}
if ($filter_status) {
  $filtered = array_filter($filtered, fn($p) => ($p['status'] ?? 'released') === $filter_status);
}
if ($filter_date_from) {
  $filtered = array_filter($filtered, fn($p) => ($p['period_start'] ?? '') >= $filter_date_from);
}
if ($filter_date_to) {
  $filtered = array_filter($filtered, fn($p) => ($p['period_end'] ?? '') <= $filter_date_to);
}

usort($filtered, fn($a,$b) => (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0));

// Calculate summary totals (from all filtered, before pagination)
$summary_total_gross = 0;
$summary_total_deductions = 0;
$summary_total_net = 0;
$summary_count = count($filtered);
foreach ($filtered as $p) {
  $summary_total_gross += (float)($p['gross_pay'] ?? 0);
  $summary_total_deductions += (float)($p['total_deductions'] ?? 0);
  $summary_total_net += (float)($p['net_pay'] ?? 0);
}

// Apply pagination after summary calculation
$pg_params = get_pagination_params();
$pg = paginate_array($filtered, $pg_params['page'], $pg_params['per_page']);
$filtered = $pg['items'];

dashboard_start('Reports - Payslips');
?>

  <!-- Print Header (only visible when printing) -->
  <div class="print-header">
    <h2>PAYROLL SUMMARY REPORT</h2>
    <?php if ($filter_emp): ?>
      <?php $emp_filter_name = ''; foreach($employees as $e) { if ((int)$e['id'] === $filter_emp) { $emp_filter_name = $e['name'] ?? ''; break; }} ?>
      <p><strong>Employee:</strong> <?= htmlspecialchars($emp_filter_name) ?></p>
    <?php endif; ?>
    <?php if ($filter_status): ?>
      <p><strong>Status:</strong> <?= htmlspecialchars(ucfirst($filter_status)) ?></p>
    <?php endif; ?>
  </div>

  <div class="card compact-card">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 8px;">
      <div>
        <h3 style="margin-bottom: 4px;">Payslips Report</h3>
        <p style="margin: 0;">Draft payslips are hidden from workers until released.</p>
      </div>
    </div>

    <?php if ($notice): ?>
      <div class="notice"><?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="notice" style="border-color:rgba(255,45,85,.35); background:rgba(255,45,85,.10);">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>
    
    <form method="get" class="row" style="align-items:end; gap:8px; margin-bottom:12px;">
      <div style="flex:1 1 0; min-width:0;">
        <label>Employee</label>
        <select name="employee_id" style="height:34px; width:100%; box-sizing:border-box;">
          <option value="0">All Employees</option>
          <?php foreach ($employees as $e): ?>
            <option value="<?= (int)$e['id'] ?>" <?= $filter_emp === (int)$e['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars(employee_name($e)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="flex:0 0 100px;">
        <label>Status</label>
        <select name="status" style="height:34px; width:100%; box-sizing:border-box;">
          <option value="">All</option>
          <option value="draft" <?= $filter_status === 'draft' ? 'selected' : '' ?>>Draft</option>
          <option value="released" <?= $filter_status === 'released' ? 'selected' : '' ?>>Released</option>
        </select>
      </div>
      <div style="flex:0 0 auto;">
        <label>From</label>
        <input type="date" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>" style="height:34px; box-sizing:border-box;">
      </div>
      <div style="flex:0 0 auto;">
        <label>To</label>
        <input type="date" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>" style="height:34px; box-sizing:border-box;">
      </div>
      <div>
        <button class="btn" type="submit" style="height:34px; box-sizing:border-box;">Filter</button>
      </div>
    </form>
  </div>

  <div class="card compact-card compact-card" style="overflow:hidden; max-width:100%;">
    <h3 style="margin-bottom: 8px;">Payslip List</h3>
    <?php render_pagination_styles(); ?>
    <div class="table-responsive" style="max-width:100%;">
      <table>
        <thead>
          <tr>
            <th>Employee</th>
            <th>Period</th>
            <th>Work Pay</th>
            <th>OT Bonus</th>
            <th>Deductions</th>
            <th>Final Pay</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($filtered as $p): ?>
            <?php
              $status = (string)($p['status'] ?? 'released');
              $pay_type = (string)($p['pay_type'] ?? 'hourly');
              $total_hours = (float)($p['total_hours'] ?? 0);
              $rate = (float)($p['rate'] ?? 0);
              $gross_pay = (float)($p['gross_pay'] ?? 0);
              $ot_bonus = (float)($p['manual_overtime_bonus'] ?? 0);
              $total_deductions = (float)($p['total_deductions'] ?? 0);
              $net_pay = (float)($p['net_pay'] ?? $gross_pay);
              $base_pay = $gross_pay - $ot_bonus;
              $periodStart = date('m/d', strtotime($p['period_start'] ?? ''));
              $periodEnd = date('m/d', strtotime($p['period_end'] ?? ''));
              $periodFull = date('M. j, Y', strtotime($p['period_start'] ?? '')) . ' - ' . date('M. j, Y', strtotime($p['period_end'] ?? ''));
            ?>
            <?php
              // Get just employee name (without code)
              $emp_display_name = '';
              $emp_id = (int)($p['employee_id'] ?? 0);
              foreach ($employees as $e) {
                if ((int)($e['id'] ?? 0) === $emp_id) {
                  $emp_display_name = $e['name'] ?? '';
                  break;
                }
              }
              if (!$emp_display_name) {
                // Fallback: strip code from stored name
                $emp_display_name = preg_replace('/\s*\([^)]*\)\s*$/', '', (string)($p['employee_name'] ?? ''));
              }
            ?>
            <tr data-hours="<?= number_format($total_hours, 2) ?>" data-rate="₱<?= number_format($rate, 2) ?>" data-period="<?= htmlspecialchars($periodFull) ?>" data-calculated-by="<?= htmlspecialchars((string)($p['calculated_by'] ?? '')) ?>">
              <td><?= htmlspecialchars($emp_display_name) ?></td>
              <td><?= $periodStart ?> - <?= $periodEnd ?></td></td>
              <td>₱<?= number_format($base_pay, 2) ?></td>
              <td style="color: <?= $ot_bonus > 0 ? 'var(--mint-400)' : 'inherit' ?>;">
                <?= $ot_bonus > 0 ? '+₱' . number_format($ot_bonus, 2) : '-' ?>
              </td>
              <td style="color: <?= $total_deductions > 0 ? '#ff3b30' : 'inherit' ?>;">
                <?= $total_deductions > 0 ? '-₱' . number_format($total_deductions, 2) : '-' ?>
              </td>
              <td style="font-weight:600; color:var(--mint-400);">₱<?= number_format($net_pay, 2) ?></td>
              <td>
                <span class="status-badge <?= $status === 'released' ? 'released' : 'draft' ?>"><?= $status === 'released' ? 'REL' : 'DFT' ?></span>
              </td>
              <td>
                <div class="action-btns" style="white-space:nowrap;">
                  <button type="button" class="btn btn-sm" onclick="viewPayslip(this)" title="View Breakdown">📋</button>
                  <?php if ($status !== 'released'): ?>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                      <input type="hidden" name="action" value="release">
                      <input type="hidden" name="id" value="<?= (int)($p['id'] ?? 0) ?>">
                      <button class="btn btn-sm" type="submit" title="Release">✓</button>
                    </form>
                  <?php endif; ?>
                  <form method="post" style="display:inline;" onsubmit="return confirm('Delete this payslip?');">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)($p['id'] ?? 0) ?>">
                    <button class="btn btn-sm btn-danger" type="submit" title="Delete">✕</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (count($filtered) === 0): ?>
            <tr><td colspan="8" class="text-center">No payslips found.</td></tr>
          <?php endif; ?>
        </tbody>
        <tfoot style="font-weight: bold; background: var(--dash-surface-hover);">
          <tr>
            <td colspan="2">TOTAL (<?= $summary_count ?> payslips)</td>
            <td></td>
            <td></td>
            <td style="color:#ff3b30;">
              <?= $summary_total_deductions > 0 ? '-₱' . number_format($summary_total_deductions, 2) : '-' ?>
            </td>
            <td style="font-weight:600; color:var(--mint-400);">₱<?= number_format($summary_total_net, 2) ?></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php render_pagination($pg); ?>
  </div>

<style>
.compact-card {
  padding: 12px 16px;
  max-width: 100%;
  overflow: hidden;
}
.compact-card h3 {
  margin-bottom: 8px;
}
.status-badge {
  display: inline-block;
  padding: 2px 6px;
  border-radius: 8px;
  font-size: 0.65rem;
  font-weight: 600;
  text-transform: uppercase;
}
.status-badge.released {
  background: rgba(52, 199, 89, 0.15);
  color: #34c759;
}
.status-badge.draft {
  background: rgba(255, 159, 10, 0.15);
  color: #ff9f0a;
}
.action-btns {
  display: flex;
  gap: 4px;
  flex-wrap: nowrap;
}
.btn-sm {
  padding: 4px 8px;
  font-size: 0.75rem;
  white-space: nowrap;
  min-width: unset;
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
  overflow-y: visible;
  width: 100%;
  max-width: 100%;
  -webkit-overflow-scrolling: touch;
  margin: 0;
  padding: 0;
}
.table-responsive::-webkit-scrollbar {
  height: 8px;
}
.table-responsive::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.05);
  border-radius: 4px;
}
.table-responsive::-webkit-scrollbar-thumb {
  background: rgba(139, 92, 246, 0.3);
  border-radius: 4px;
}
.table-responsive::-webkit-scrollbar-thumb:hover {
  background: rgba(139, 92, 246, 0.5);
}
.table-responsive table {
  font-size: 0.75rem;
  min-width: 50rem;
  width: 100%;
  table-layout: auto;
}
.table-responsive th,
.table-responsive td {
  padding: 6px 8px;
  white-space: nowrap;
}
.table-responsive th:first-child,
.table-responsive td:first-child {
  width: 30px;
}
.text-center {
  text-align: center;
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
let currentPayslipData = null;

function viewPayslip(btn) {
  const row = btn.closest('tr');
  // Get hours, rate, and full period from data attributes
  const hours = row.dataset.hours || '-';
  const rate = row.dataset.rate || '-';
  const period = row.dataset.period || row.cells[1].textContent;
  currentPayslipData = {
    empName: row.cells[0].textContent,
    period: period,
    hours: hours,
    rate: rate,
    gross: row.cells[2].textContent,
    otBonus: row.cells[3].textContent,
    deductions: row.cells[4].textContent,
    netPay: row.cells[5].textContent,
    calculatedBy: row.dataset.calculatedBy || ''
  };
  
  document.getElementById('modal-empName').textContent = currentPayslipData.empName;
  document.getElementById('modal-period').textContent = currentPayslipData.period;
  document.getElementById('modal-hours').textContent = currentPayslipData.hours;
  document.getElementById('modal-rate').textContent = currentPayslipData.rate;
  document.getElementById('modal-gross').textContent = currentPayslipData.gross;
  document.getElementById('modal-otBonus').textContent = currentPayslipData.otBonus;
  document.getElementById('modal-deductions').textContent = currentPayslipData.deductions;
  document.getElementById('modal-netPay').textContent = currentPayslipData.netPay;
  document.getElementById('modal-calculatedBy').textContent = currentPayslipData.calculatedBy || 'N/A';
  
  document.getElementById('payslipModal').style.display = 'flex';
}

function closeModal() {
  document.getElementById('payslipModal').style.display = 'none';
}

function getPayslipHTML() {
  const d = currentPayslipData;
  return `
    <html>
    <head>
      <title>Payslip - ${d.empName}</title>
      <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        h2 { text-align: center; margin-bottom: 5px; }
        .subtitle { text-align: center; color: #666; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #333; padding: 10px; text-align: left; }
        th { background: #f0f0f0; width: 40%; }
        .net-pay { font-weight: bold; font-size: 1.2em; color: #2e7d32; }
        .bonus { color: #2e7d32; }
        .deduction { color: #c62828; }
        .footer { margin-top: 30px; text-align: center; font-size: 0.9em; color: #666; }
      </style>
    </head>
    <body>
      <h2>PAYSLIP</h2>
      <p class="subtitle">Avant-Guard Virtual Assistance Services</p>
      <table>
        <tr><th>Employee</th><td>${d.empName}</td></tr>
        <tr><th>Period</th><td>${d.period}</td></tr>
        <tr><th>Hours Worked</th><td>${d.hours}</td></tr>
        <tr><th>Salary</th><td>${d.rate}</td></tr>
        <tr><th>Work Pay</th><td>${d.gross}</td></tr>
        <tr><th>OT Bonus</th><td class="bonus">${d.otBonus}</td></tr>
        <tr><th>Deductions</th><td class="deduction">${d.deductions}</td></tr>
        <tr><th>Final Pay</th><td class="net-pay">${d.netPay}</td></tr>
        ${d.calculatedBy ? `<tr><th>Calculated By</th><td>${d.calculatedBy}</td></tr>` : ''}
      </table>
      <p class="footer">Generated: ${new Date().toLocaleDateString()}</p>
    </body>
    </html>
  `;
}

function printPayslip() {
  const printWindow = window.open('', '_blank', 'width=650,height=550');
  printWindow.document.write(getPayslipHTML());
  printWindow.document.close();
  printWindow.print();
}

function downloadPDF() {
  const d = currentPayslipData;
  const content = document.createElement('div');
  content.innerHTML = `
    <div style="font-family: Arial, sans-serif; padding: 20px; background: white; color: #000;">
      <div style="text-align: center; margin-bottom: 15px;">
        <img src="/avantguard/assets/avantguard.png" alt="Avant-Guard" style="max-height: 60px; margin-bottom: 10px;">
      </div>
      <h2 style="text-align: center; margin-bottom: 5px; color: #000;">PAYSLIP</h2>
      <p style="text-align: center; color: #000; margin-bottom: 20px;">Avant-Guard Virtual Assistance Services</p>
      <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
        <tr><th style="border: 1px solid #333; padding: 10px; text-align: left; background: #f0f0f0; width: 40%; color: #000;">Employee</th><td style="border: 1px solid #333; padding: 10px; color: #000;">${d.empName}</td></tr>
        <tr><th style="border: 1px solid #333; padding: 10px; text-align: left; background: #f0f0f0; width: 40%; color: #000;">Period</th><td style="border: 1px solid #333; padding: 10px; color: #000;">${d.period}</td></tr>
        <tr><th style="border: 1px solid #333; padding: 10px; text-align: left; background: #f0f0f0; width: 40%; color: #000;">Hours Worked</th><td style="border: 1px solid #333; padding: 10px; color: #000;">${d.hours}</td></tr>
        <tr><th style="border: 1px solid #333; padding: 10px; text-align: left; background: #f0f0f0; width: 40%; color: #000;">Salary</th><td style="border: 1px solid #333; padding: 10px; color: #000;">${d.rate}</td></tr>
        <tr><th style="border: 1px solid #333; padding: 10px; text-align: left; background: #f0f0f0; width: 40%; color: #000;">Work Pay</th><td style="border: 1px solid #333; padding: 10px; color: #000;">${d.gross}</td></tr>
        <tr><th style="border: 1px solid #333; padding: 10px; text-align: left; background: #f0f0f0; width: 40%; color: #000;">OT Bonus</th><td style="border: 1px solid #333; padding: 10px; color: #000;">${d.otBonus}</td></tr>
        <tr><th style="border: 1px solid #333; padding: 10px; text-align: left; background: #f0f0f0; width: 40%; color: #000;">Deductions</th><td style="border: 1px solid #333; padding: 10px; color: #000;">${d.deductions}</td></tr>
        <tr><th style="border: 1px solid #333; padding: 10px; text-align: left; background: #f0f0f0; width: 40%; color: #000;">Final Pay</th><td style="border: 1px solid #333; padding: 10px; font-weight: bold; font-size: 1.2em; color: #000;">${d.netPay}</td></tr>
        ${d.calculatedBy ? `<tr><th style="border: 1px solid #333; padding: 10px; text-align: left; background: #f0f0f0; width: 40%; color: #000;">Calculated By</th><td style="border: 1px solid #333; padding: 10px; color: #000;">${d.calculatedBy}</td></tr>` : ''}
      </table>
      <p style="margin-top: 30px; text-align: center; font-size: 0.9em; color: #888;">Generated: ${new Date().toLocaleDateString()}</p>
    </div>
  `;
  
  const opt = {
    margin: 10,
    filename: `payslip_${d.empName.replace(/\s+/g, '_')}_${d.period.replace(/[^a-zA-Z0-9]/g, '_')}.pdf`,
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 2 },
    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
  };
  
  html2pdf().set(opt).from(content).save();
}

// Close modal when clicking outside
document.getElementById('payslipModal')?.addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
</script>

<!-- Payslip Modal -->
<div id="payslipModal" class="payslip-modal" style="display:none;">
  <div class="payslip-modal-content">
    <div class="payslip-modal-header">
      <h3>Payslip Details</h3>
      <button onclick="closeModal()" class="modal-close">✕</button>
    </div>
    <div class="payslip-modal-body">
      <table class="payslip-detail-table">
        <tr><th>Employee</th><td id="modal-empName"></td></tr>
        <tr><th>Period</th><td id="modal-period"></td></tr>
        <tr><th>Hours Worked</th><td id="modal-hours"></td></tr>
        <tr><th>Salary</th><td id="modal-rate"></td></tr>
        <tr><th>Work Pay</th><td id="modal-gross"></td></tr>
        <tr><th>OT Bonus</th><td id="modal-otBonus" style="color:#34c759;"></td></tr>
        <tr><th>Deductions</th><td id="modal-deductions" style="color:#ff3b30;"></td></tr>
        <tr><th>Final Pay</th><td id="modal-netPay" style="font-weight:bold; font-size:1.1em; color:#34c759;"></td></tr>
        <tr><th>Calculated By</th><td id="modal-calculatedBy"></td></tr>
      </table>
    </div>
    <div class="payslip-modal-footer">
      <button onclick="printPayslip()" class="btn">🖨️ Print</button>
      <button onclick="downloadPDF()" class="btn btn-pdf">📄 Download PDF</button>
      <button onclick="closeModal()" class="btn secondary">Close</button>
    </div>
  </div>
</div>

<style>
.payslip-modal {
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
.payslip-modal-content {
  background: var(--dash-surface, #1e1033);
  border-radius: 12px;
  width: 85%;
  max-width: 380px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.5);
}
.payslip-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--dash-border, rgba(255,255,255,0.1));
}
.payslip-modal-header h3 {
  margin: 0;
  font-size: 1.1rem;
}
.modal-close {
  background: none;
  border: none;
  font-size: 1.2rem;
  cursor: pointer;
  color: var(--text-muted);
  padding: 4px 8px;
}
.modal-close:hover {
  color: #ff3b30;
}
.payslip-modal-body {
  padding: 20px;
}
.payslip-detail-table {
  width: 100%;
  border-collapse: collapse;
}
.payslip-detail-table th,
.payslip-detail-table td {
  padding: 10px 12px;
  text-align: left;
  border-bottom: 1px solid var(--dash-border, rgba(255,255,255,0.08));
}
.payslip-detail-table th {
  width: 40%;
  color: var(--text-muted);
  font-weight: 500;
}
.payslip-modal-footer {
  display: flex;
  gap: 10px;
  padding: 16px 20px;
  border-top: 1px solid var(--dash-border, rgba(255,255,255,0.1));
  justify-content: flex-end;
}
.btn-pdf {
  background: rgba(255, 59, 48, 0.15);
  color: #ff6b6b;
  border: 1px solid rgba(255, 59, 48, 0.3);
}
.btn-pdf:hover {
  background: rgba(255, 59, 48, 0.25);
}
</style>

<?php dashboard_end(); ?>
