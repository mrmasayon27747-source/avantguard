<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/storage.php';
require_once __DIR__ . '/../inc/repository.php';
require_once __DIR__ . '/../inc/layout.php';

require_role('worker');

$u = current_user();
$payslips = repo_payslips();

$mine = array_values(array_filter($payslips, function($p) use ($u) {
  if ((int)($p['employee_id'] ?? 0) !== (int)($u['employee_id'] ?? 0)) return false;

  // ✅ New rule: only released are visible
  $status = (string)($p['status'] ?? 'released'); // old payslips default visible
  return $status === 'released';
}));

usort($mine, fn($a,$b) => (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0));

dashboard_start('Payslips');

// Get employee name for this worker
$emp_name = '';
$employees = repo_employees();
foreach ($employees as $e) {
  if ((int)($e['id'] ?? 0) === (int)($u['employee_id'] ?? 0)) {
    $emp_name = $e['name'] ?? '';
    break;
  }
}
?>

  <!-- Print Header (only visible when printing) -->
  <div class="print-header">
    <h2>PAYSLIP RECORD</h2>
    <p><strong>Employee:</strong> <?= htmlspecialchars($emp_name ?: ($u['username'] ?? 'N/A')) ?></p>
    <p><strong>Generated:</strong> <?= date('F j, Y') ?></p>
  </div>

  <div class="card">
    <h3>Your Payslips</h3>
    <p>You can only see payslips after the admin releases them.</p>
  </div>

  <div class="card">
    <h3>History</h3>
    <div class="table-responsive">
      <table class="payslip-table">
        <thead>
          <tr>
            <th>Period</th>
            <th>Hours</th>
            <th>Salary</th>
            <th>Work Pay</th>
            <th>OT Bonus</th>
            <th>Deductions</th>
            <th>Final Pay</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($mine as $p): ?>
            <?php
              $gross = (float)($p['gross_pay'] ?? 0);
              $ot_bonus = (float)($p['manual_overtime_bonus'] ?? 0);
              $deductions = (float)($p['total_deductions'] ?? 0);
              $net = (float)($p['net_pay'] ?? $gross);
              $periodStart = date('m/d', strtotime($p['period_start'] ?? ''));
              $periodEnd = date('m/d', strtotime($p['period_end'] ?? ''));
              $periodFull = date('M. j, Y', strtotime($p['period_start'] ?? '')) . ' - ' . date('M. j, Y', strtotime($p['period_end'] ?? ''));
            ?>
            <tr data-period="<?= htmlspecialchars($periodFull) ?>" data-calculated-by="<?= htmlspecialchars((string)($p['calculated_by'] ?? '')) ?>">
              <td><?= $periodStart ?> - <?= $periodEnd ?></td>
              <td><?= number_format((float)($p['total_hours'] ?? 0), 2) ?></td>
              <td>₱<?= number_format((float)($p['rate'] ?? 0), 2) ?></td>
              <td>₱<?= number_format($gross - $ot_bonus, 2) ?></td>
              <td style="color: <?= $ot_bonus > 0 ? 'var(--mint-400)' : 'inherit' ?>;">
                <?= $ot_bonus > 0 ? '+₱' . number_format($ot_bonus, 2) : '-' ?>
              </td>
              <td style="color: <?= $deductions > 0 ? '#ff3b30' : 'inherit' ?>;">
                <?= $deductions > 0 ? '-₱' . number_format($deductions, 2) : '-' ?>
              </td>
              <td style="font-weight: 600; color: var(--mint-400);">₱<?= number_format($net, 2) ?></td>
              <td>
                <button type="button" class="btn btn-sm btn-print" onclick="viewPayslip(this)" title="View Payslip">📋</button>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (count($mine) === 0): ?>
            <tr><td colspan="8">No released payslips yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
const empName = <?= json_encode($emp_name ?: ($u['username'] ?? 'Employee')) ?>;
let currentPayslipData = null;

function viewPayslip(btn) {
  const row = btn.closest('tr');
  const period = row.dataset.period || row.cells[0].textContent;
  currentPayslipData = {
    period: period,
    hours: row.cells[1].textContent,
    rate: row.cells[2].textContent,
    gross: row.cells[3].textContent,
    otBonus: row.cells[4].textContent,
    deductions: row.cells[5].textContent,
    netPay: row.cells[6].textContent,
    calculatedBy: row.dataset.calculatedBy || ''
  };
  
  document.getElementById('modal-empName').textContent = empName;
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
      <title>Payslip - ${empName}</title>
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
        <tr><th>Employee</th><td>${empName}</td></tr>
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
        <tr><th style="border: 1px solid #333; padding: 10px; text-align: left; background: #f0f0f0; width: 40%; color: #000;">Employee</th><td style="border: 1px solid #333; padding: 10px; color: #000;">${empName}</td></tr>
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
    filename: `payslip_${empName.replace(/\s+/g, '_')}_${d.period.replace(/[^a-zA-Z0-9]/g, '_')}.pdf`,
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
