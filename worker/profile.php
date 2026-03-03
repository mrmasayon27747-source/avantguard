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

$employees = repo_employees();

// Get current profile
$profile = repo_find_profile_by_employee($employee_id);

// Get employee data
$employee = find_by_id($employees, $employee_id);

$notice = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  
  $name = trim($_POST['name'] ?? '');
  $contact_number = trim($_POST['contact_number'] ?? '');
  $home_address = trim($_POST['home_address'] ?? '');
  $email_address = trim($_POST['email_address'] ?? '');
  $birthdate = trim($_POST['birthdate'] ?? '');
  
  if (empty($name)) {
    $error = "Name is required.";
  } elseif (!empty($email_address) && !filter_var($email_address, FILTER_VALIDATE_EMAIL)) {
    $error = "Invalid email address format.";
  } elseif (!empty($birthdate) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
    $error = "Invalid birthdate format.";
  } else {
    repo_upsert_profile($employee_id, [
      'name' => $name,
      'contact_number' => $contact_number,
      'home_address' => $home_address,
      'email_address' => $email_address,
      'birthdate' => $birthdate
    ]);
    
    $profile = repo_find_profile_by_employee($employee_id);
    $notice = "Profile updated successfully!";
  }
}

dashboard_start('My Profile');
?>

<div class="card profile-card">
  <div class="profile-header">
    <div class="profile-avatar-large">
      <?= strtoupper(substr($profile['name'] ?? $u['username'] ?? 'U', 0, 1)) ?>
    </div>
    <div class="profile-info">
      <h2><?= htmlspecialchars($profile['name'] ?? $employee['name'] ?? $u['username'] ?? 'Worker') ?></h2>
      <p><?= htmlspecialchars($employee['position'] ?? 'Employee') ?> • <?= htmlspecialchars($employee['employee_code'] ?? '') ?></p>
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
  
  <form method="post" class="profile-form">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    
    <div class="form-group full-width">
      <label>Full Name *</label>
      <input type="text" name="name" value="<?= htmlspecialchars($profile['name'] ?? $employee['name'] ?? '') ?>" required data-input="letters-only">
    </div>
    
    <div class="form-group">
      <label>Contact Number</label>
      <input type="tel" name="contact_number" value="<?= htmlspecialchars($profile['contact_number'] ?? '') ?>" placeholder="e.g. 09171234567" data-input="phone">
    </div>
    
    <div class="form-group">
      <label>Email Address</label>
      <input type="email" name="email_address" value="<?= htmlspecialchars($profile['email_address'] ?? '') ?>" placeholder="e.g. email@example.com">
    </div>
    
    <div class="form-group">
      <label>Birthdate</label>
      <input type="date" name="birthdate" id="birthdate-input" value="<?= htmlspecialchars($profile['birthdate'] ?? '') ?>">
    </div>
    
    <div class="form-group full-width">
      <label>Home Address</label>
      <textarea name="home_address" rows="3" placeholder="Enter your complete home address"><?= htmlspecialchars($profile['home_address'] ?? '') ?></textarea>
    </div>
    
    <div class="form-group full-width">
      <button type="submit" class="btn">Save Profile</button>
    </div>
  </form>
</div>

<div class="card">
  <h3>Employment Information</h3>
  <p style="color: var(--text-muted); margin-bottom: var(--space-4);">This information is managed by your administrator.</p>
  
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4);">
    <div>
      <p style="color: var(--text-muted); font-size: var(--text-sm); margin-bottom: var(--space-1);">Employee Code</p>
      <p style="font-weight: 500;"><?= htmlspecialchars($employee['employee_code'] ?? 'N/A') ?></p>
    </div>
    <div>
      <p style="color: var(--text-muted); font-size: var(--text-sm); margin-bottom: var(--space-1);">Position</p>
      <p style="font-weight: 500;"><?= htmlspecialchars($employee['position'] ?? 'N/A') ?></p>
    </div>
    <div>
      <p style="color: var(--text-muted); font-size: var(--text-sm); margin-bottom: var(--space-1);">Pay Type</p>
      <p style="font-weight: 500;"><?= ucfirst(htmlspecialchars($employee['pay_type'] ?? 'N/A')) ?></p>
    </div>
    <div>
      <p style="color: var(--text-muted); font-size: var(--text-sm); margin-bottom: var(--space-1);">Schedule</p>
      <p style="font-weight: 500;"><?= htmlspecialchars(($employee['schedule_start'] ?? '') . ' - ' . ($employee['schedule_end'] ?? '')) ?></p>
    </div>
  </div>
</div>

<style>
.bdate-picker-wrap {
  position: relative;
  display: inline-block;
  width: 100%;
}
.bdate-display {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--dash-surface, #1e1535);
  border: 1px solid var(--dash-border, rgba(255,255,255,0.12));
  border-radius: var(--radius-md, 8px);
  padding: 10px 14px;
  color: var(--dash-text, #fff);
  font-size: var(--text-sm, 0.875rem);
  cursor: pointer;
  transition: all 0.15s ease;
}
[data-theme="light"] .bdate-display {
  background: #ffffff;
  border-color: rgba(0,0,0,0.12);
  color: #1a0a2e;
}
.bdate-display:hover { border-color: var(--purple-400); }
.bdate-dropdown {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  z-index: 999;
  background: var(--dash-surface, #1e1535);
  border: 1px solid var(--dash-border, rgba(255,255,255,0.12));
  border-radius: var(--radius-lg, 12px);
  box-shadow: 0 10px 40px rgba(0,0,0,0.4);
  padding: 16px;
  min-width: 280px;
  display: none;
}
[data-theme="light"] .bdate-dropdown {
  background: #ffffff;
  box-shadow: 0 10px 40px rgba(0,0,0,0.12);
}
.bdate-dropdown.open { display: block; }
.bdate-selectors {
  display: flex;
  gap: 10px;
  margin-bottom: 12px;
}
.bdate-selectors select {
  flex: 1;
  padding: 8px;
  border-radius: var(--radius-md, 8px);
  background: var(--dash-surface-hover, #2a1f45);
  border: 1px solid var(--dash-border, rgba(255,255,255,0.12));
  color: var(--dash-text, #fff);
  font-size: var(--text-sm, 0.875rem);
  font-weight: 600;
  cursor: pointer;
}
[data-theme="light"] .bdate-selectors select {
  background: #f3f2f7;
  color: #1a0a2e;
}
.bdate-days-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 2px;
}
.bdate-weekday {
  text-align: center;
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--dash-text-muted, rgba(255,255,255,0.55));
  padding: 4px;
}
.bdate-day {
  aspect-ratio: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  color: var(--dash-text, #fff);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s ease;
}
[data-theme="light"] .bdate-day { color: #1a0a2e; }
.bdate-day:hover:not(.empty) { background: var(--purple-500); color: #fff; }
.bdate-day.empty { cursor: default; }
.bdate-day.selected { background: var(--purple-500); color: #fff; font-weight: 600; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const hiddenInput = document.getElementById('birthdate-input');
  if (!hiddenInput) return;
  
  // Replace with custom picker
  const wrapper = document.createElement('div');
  wrapper.className = 'bdate-picker-wrap';
  
  const display = document.createElement('div');
  display.className = 'bdate-display';
  display.textContent = hiddenInput.value ? formatDate(hiddenInput.value) : 'Select birthdate...';
  
  const dropdown = document.createElement('div');
  dropdown.className = 'bdate-dropdown';
  
  // Selectors
  const selectors = document.createElement('div');
  selectors.className = 'bdate-selectors';
  
  const monthSel = document.createElement('select');
  const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  months.forEach((m, i) => {
    const opt = document.createElement('option');
    opt.value = i;
    opt.textContent = m;
    monthSel.appendChild(opt);
  });
  
  const yearSel = document.createElement('select');
  const currentYear = new Date().getFullYear();
  for (let y = currentYear; y >= 1940; y--) {
    const opt = document.createElement('option');
    opt.value = y;
    opt.textContent = y;
    yearSel.appendChild(opt);
  }
  
  selectors.appendChild(monthSel);
  selectors.appendChild(yearSel);
  dropdown.appendChild(selectors);
  
  // Days grid
  const daysContainer = document.createElement('div');
  dropdown.appendChild(daysContainer);
  
  wrapper.appendChild(display);
  wrapper.appendChild(dropdown);
  
  hiddenInput.style.display = 'none';
  hiddenInput.parentNode.insertBefore(wrapper, hiddenInput.nextSibling);
  
  // Initialize from value
  let selectedDate = hiddenInput.value ? new Date(hiddenInput.value + 'T00:00:00') : null;
  if (selectedDate && !isNaN(selectedDate)) {
    monthSel.value = selectedDate.getMonth();
    yearSel.value = selectedDate.getFullYear();
  } else {
    monthSel.value = 0;
    yearSel.value = 2000;
  }
  
  function formatDate(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    if (isNaN(d)) return dateStr;
    return months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
  }
  
  function renderDays() {
    const month = parseInt(monthSel.value);
    const year = parseInt(yearSel.value);
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    
    let html = '<div class="bdate-days-grid">';
    ['Su','Mo','Tu','We','Th','Fr','Sa'].forEach(d => {
      html += '<div class="bdate-weekday">' + d + '</div>';
    });
    for (let i = 0; i < firstDay; i++) html += '<div class="bdate-day empty"></div>';
    for (let d = 1; d <= daysInMonth; d++) {
      const isSelected = selectedDate && selectedDate.getDate() === d && selectedDate.getMonth() === month && selectedDate.getFullYear() === year;
      html += '<div class="bdate-day' + (isSelected ? ' selected' : '') + '" data-day="' + d + '">' + d + '</div>';
    }
    html += '</div>';
    daysContainer.innerHTML = html;
    
    daysContainer.querySelectorAll('.bdate-day:not(.empty)').forEach(el => {
      el.addEventListener('click', function() {
        const day = parseInt(this.dataset.day);
        selectedDate = new Date(year, month, day);
        const val = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
        hiddenInput.value = val;
        display.textContent = formatDate(val);
        dropdown.classList.remove('open');
        renderDays();
      });
    });
  }
  
  monthSel.addEventListener('change', renderDays);
  yearSel.addEventListener('change', renderDays);
  
  display.addEventListener('click', function(e) {
    e.stopPropagation();
    dropdown.classList.toggle('open');
    renderDays();
  });
  
  document.addEventListener('click', function(e) {
    if (!wrapper.contains(e.target)) dropdown.classList.remove('open');
  });
  
  renderDays();
});
</script>

<?php dashboard_end(); ?>
