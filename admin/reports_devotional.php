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

$devotionals = repo_devotionals();
$employees = repo_employees();

// Filter
$filter_emp = (int)($_GET['employee_id'] ?? 0);
$filter_date_from = trim($_GET['date_from'] ?? '');
$filter_date_to   = trim($_GET['date_to'] ?? '');

$filtered = $devotionals;
if ($filter_emp) {
  $filtered = array_filter($filtered, fn($d) => (int)($d['employee_id'] ?? 0) === $filter_emp);
}
if ($filter_date_from) {
  $filtered = array_filter($filtered, fn($r) => ($r['date'] ?? '') >= $filter_date_from);
}
if ($filter_date_to) {
  $filtered = array_filter($filtered, fn($r) => ($r['date'] ?? '') <= $filter_date_to);
}

// Sort by date descending
usort($filtered, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));

dashboard_start('Reports - Devotionals');
?>

  <div class="card">
    <h3>Devotional Submissions</h3>
    <p>View all worker devotional photo uploads.</p>
    
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
    <h3>Devotional Gallery</h3>
    <div class="devotional-grid">
      <?php if (count($filtered) === 0): ?>
        <div class="empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
          </svg>
          <p>No devotional submissions found.</p>
        </div>
      <?php else: ?>
        <?php foreach ($filtered as $d): ?>
          <?php 
            $emp = find_by_id($employees, (int)($d['employee_id'] ?? 0));
            $emp_name = $emp ? ($emp['name'] ?? 'Unknown') : 'Unknown';
            $photo_url = $d['photo'] ?? $d['photo_path'] ?? '';
          ?>
          <div class="devotional-card" onclick="openLightbox('/avantguard/<?= htmlspecialchars($photo_url) ?>')">
            <div class="devotional-image">
              <img src="/avantguard/<?= htmlspecialchars($photo_url) ?>" alt="Devotional">
            </div>
            <div class="devotional-info">
              <div class="avatar-sm">
                <?= strtoupper(substr($emp_name, 0, 1)) ?>
              </div>
              <div class="devotional-meta">
                <span class="emp-name"><?= htmlspecialchars($emp_name) ?></span>
                <span class="devotional-date"><?= htmlspecialchars($d['date'] ?? '') ?> at <?= htmlspecialchars(date('g:i A', strtotime($d['created_at'] ?? ''))) ?></span>
              </div>
            </div>
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
.devotional-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 16px;
}
.devotional-card {
  background: var(--dash-surface);
  border: 1px solid var(--dash-border);
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
}
.devotional-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}
.devotional-image {
  aspect-ratio: 4/3;
  overflow: hidden;
}
.devotional-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.devotional-info {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px;
  background: var(--dash-surface-hover);
}
.avatar-sm {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--mint-400), var(--mint-600));
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 600;
  font-size: 0.8rem;
  flex-shrink: 0;
}
.devotional-meta {
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.emp-name {
  font-weight: 600;
  color: var(--dash-text);
  font-size: 0.9rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.devotional-date {
  font-size: 0.75rem;
  color: var(--dash-text-secondary);
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
  event.stopPropagation();
  document.getElementById('lightbox-img').src = src;
  document.getElementById('lightbox').classList.add('active');
}
function closeLightbox() {
  document.getElementById('lightbox').classList.remove('active');
}
</script>

<?php dashboard_end(); ?>
