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

$eod_reports = repo_eod();
$employees = repo_employees();

// Filter
$filter_emp = (int)($_GET['employee_id'] ?? 0);
$filter_date_from = trim($_GET['date_from'] ?? '');
$filter_date_to   = trim($_GET['date_to'] ?? '');

$filtered = $eod_reports;
if ($filter_emp) {
  $filtered = array_filter($filtered, fn($r) => (int)($r['employee_id'] ?? 0) === $filter_emp);
}
if ($filter_date_from) {
  $filtered = array_filter($filtered, fn($r) => ($r['date'] ?? '') >= $filter_date_from);
}
if ($filter_date_to) {
  $filtered = array_filter($filtered, fn($r) => ($r['date'] ?? '') <= $filter_date_to);
}

// Sort by date descending
usort($filtered, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));

dashboard_start('Reports - EOD');
?>

  <div class="card">
    <h3>End of Day Reports</h3>
    <p>View all worker EOD submissions.</p>
    
    <form method="get" class="row" style="align-items:end; gap:12px; margin-bottom:16px;">
      <div style="flex:2">
        <label>Employee</label>
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
        <label>From</label>
        <input type="date" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>">
      </div>
      <div style="flex:1">
        <label>To</label>
        <input type="date" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>">
      </div>
      <div>
        <button class="btn" type="submit">Filter</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h3>EOD Submissions</h3>
    <div class="eod-grid">
      <?php if (count($filtered) === 0): ?>
        <div class="empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
          </svg>
          <p>No EOD reports found.</p>
        </div>
      <?php else: ?>
        <?php foreach ($filtered as $r): ?>
          <?php 
            $emp = find_by_id($employees, (int)($r['employee_id'] ?? 0));
            $emp_name = $emp ? employee_name($emp) : 'Unknown';
          ?>
          <div class="eod-card">
            <div class="eod-header">
              <div class="eod-employee">
                <div class="avatar-sm">
                  <?= strtoupper(substr($emp['name'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="eod-meta">
                  <span class="emp-name"><?= htmlspecialchars($emp_name) ?></span>
                  <span class="eod-date"><?= htmlspecialchars($r['date'] ?? '') ?></span>
                </div>
              </div>
              <span class="eod-time">Submitted: <?= htmlspecialchars(date('M j, Y g:i A', strtotime($r['created_at'] ?? $r['submitted_at'] ?? ''))) ?></span>
            </div>
            
            <div class="eod-content">
              <?php
                $ns = $r['notes_status'] ?? 'finished';
                $tasks_completed = $r['tasks_completed'] ?? '';
                $pending_concerns = $r['pending_concerns'] ?? '';
              ?>
              <?php if (!empty($tasks_completed)): ?>
              <div class="eod-section eod-section-finished">
                <h4><span class="eod-section-icon">✓</span> Finished</h4>
                <p><?= nl2br(htmlspecialchars($tasks_completed)) ?></p>
              </div>
              <?php endif; ?>
              <?php if (!empty($pending_concerns)): ?>
              <div class="eod-section eod-section-pending">
                <h4><span class="eod-section-icon">⏳</span> Pending / Concern</h4>
                <p><?= nl2br(htmlspecialchars($pending_concerns)) ?></p>
              </div>
              <?php endif; ?>
              <?php if (empty($tasks_completed) && empty($pending_concerns)): ?>
              <div style="padding: 8px 0; color: var(--dash-text-muted);">No content submitted.</div>
              <?php endif; ?>
            </div>
            
            <?php if (!empty($r['photo_path'])): ?>
              <div class="eod-photo">
                <img src="/avantguard/uploads/eod/<?= htmlspecialchars(basename($r['photo_path'])) ?>" alt="EOD Photo" onclick="openLightbox(this.src)">
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

<!-- Lightbox for image preview -->
<div id="lightbox" class="lightbox" onclick="closeLightbox()">
  <span class="lightbox-close">&times;</span>
  <img id="lightbox-img" src="" alt="Preview">
</div>

<style>
.eod-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
  gap: 16px;
}
.eod-card {
  background: var(--dash-surface);
  border: 1px solid var(--dash-border);
  border-radius: 12px;
  overflow: hidden;
}
.eod-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: var(--dash-surface-hover);
  border-bottom: 1px solid var(--dash-border);
}
.eod-employee {
  display: flex;
  align-items: center;
  gap: 10px;
}
.avatar-sm {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--mint-400), var(--mint-600));
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 600;
  font-size: 0.9rem;
}
.eod-meta {
  display: flex;
  flex-direction: column;
}
.emp-name {
  font-weight: 600;
  color: var(--dash-text);
}
.eod-date {
  font-size: 0.8rem;
  color: var(--dash-text-secondary);
}
.eod-time {
  font-size: 0.75rem;
  color: var(--dash-text-secondary);
}
.eod-content {
  padding: 16px;
}
.eod-section {
  border-radius: 8px;
  padding: 10px 12px;
  margin-bottom: 10px;
}
.eod-section:last-child {
  margin-bottom: 0;
}
.eod-section-finished {
  background: rgba(52, 199, 89, 0.08);
  border-left: 3px solid #34c759;
}
.eod-section-pending {
  background: rgba(255, 159, 10, 0.08);
  border-left: 3px solid #ff9f0a;
}
.eod-section h4 {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8rem;
  font-weight: 600;
  margin-bottom: 6px;
}
.eod-section-finished h4 {
  color: #34c759;
}
.eod-section-pending h4 {
  color: #ff9f0a;
}
.eod-section-icon {
  font-size: 0.9rem;
}
.eod-section p {
  color: var(--dash-text);
  line-height: 1.5;
  font-size: 0.85rem;
  margin: 0;
}
.eod-photo {
  padding: 0 16px 16px;
}
.eod-photo img {
  width: 100%;
  max-height: 200px;
  object-fit: cover;
  border-radius: 8px;
  cursor: pointer;
  transition: transform 0.2s;
}
.eod-photo img:hover {
  transform: scale(1.02);
}
.empty-state {
  grid-column: 1 / -1;
  text-align: center;
  padding: 48px;
  color: var(--dash-text-secondary);
}
.empty-state svg {
  opacity: 0.5;
  margin-bottom: 12px;
}

/* Light mode overrides for EOD sections */
[data-theme="light"] .eod-card {
  background: #ffffff;
  border-color: rgba(0, 0, 0, 0.08);
}
[data-theme="light"] .eod-header {
  background: #f8f7fc;
  border-bottom-color: rgba(0, 0, 0, 0.06);
}
[data-theme="light"] .emp-name {
  color: #1a0a2e;
}
[data-theme="light"] .eod-date,
[data-theme="light"] .eod-time {
  color: #6b7280;
}
[data-theme="light"] .eod-section p {
  color: #1a0a2e;
}
[data-theme="light"] .eod-section-finished h4 {
  color: #248a3d;
}
[data-theme="light"] .eod-section-pending h4 {
  color: #c77c02;
}
[data-theme="light"] .eod-section-finished {
  background: rgba(52, 199, 89, 0.06);
  border-left-color: #248a3d;
}
[data-theme="light"] .eod-section-pending {
  background: rgba(255, 159, 10, 0.06);
  border-left-color: #c77c02;
}

/* Lightbox */
.lightbox {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.9);
  z-index: 9999;
  cursor: pointer;
  align-items: center;
  justify-content: center;
}
.lightbox.active {
  display: flex;
}
.lightbox img {
  max-width: 90%;
  max-height: 90%;
  border-radius: 8px;
}
.lightbox-close {
  position: absolute;
  top: 20px;
  right: 30px;
  color: white;
  font-size: 40px;
  font-weight: bold;
}
</style>

<script>
function openLightbox(src) {
  document.getElementById('lightbox-img').src = src;
  document.getElementById('lightbox').classList.add('active');
}
function closeLightbox() {
  document.getElementById('lightbox').classList.remove('active');
}
</script>

<?php dashboard_end(); ?>
