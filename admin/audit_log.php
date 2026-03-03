<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/storage.php';
require_once __DIR__ . '/../inc/layout.php';
require_once __DIR__ . '/../inc/audit_log.php';
require_once __DIR__ . '/../inc/rate_limit.php';

require_role('admin');

// Get filters
$filter_type = $_GET['type'] ?? '';
$filter_date = $_GET['date'] ?? '';
$limit = (int)($_GET['limit'] ?? 100);

$filters = [];
if ($filter_type) $filters['event_type'] = $filter_type;
if ($filter_date) $filters['date_from'] = $filter_date;

$logs = audit_log_query($filters, $limit);
$stats = audit_log_stats();
$rate_status = rate_limit_get_status();

// Get unique event types for filter dropdown
$all_logs = audit_log_get();
$event_types = array_unique(array_column($all_logs, 'event_type'));
sort($event_types);

dashboard_start('Audit Log');
?>

<style>
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}
.stat-card {
  background: var(--dash-surface-hover);
  padding: 12px 16px;
  border-radius: 8px;
  text-align: center;
}
.stat-value {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--mint-400);
}
.stat-label {
  font-size: 0.75rem;
  color: var(--text-muted);
  margin-top: 4px;
}
.stat-card.warning .stat-value {
  color: #ff9f0a;
}
.stat-card.danger .stat-value {
  color: #ff3b30;
}
.log-table {
  font-size: 0.8rem;
}
.log-table td, .log-table th {
  padding: 8px 10px;
}
.event-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.7rem;
  font-weight: 500;
}
.event-badge.success { background: rgba(52, 199, 89, 0.15); color: #34c759; }
.event-badge.failure { background: rgba(255, 59, 48, 0.15); color: #ff3b30; }
.event-badge.warning { background: rgba(255, 159, 10, 0.15); color: #ff9f0a; }
.event-badge.info { background: rgba(10, 132, 255, 0.15); color: #0a84ff; }
.details-cell {
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.7rem;
  color: var(--text-muted);
}
</style>

<div class="card">
  <h3 style="margin-bottom: 12px;">Security Overview</h3>
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-value"><?= $stats['total_events'] ?></div>
      <div class="stat-label">Total Events</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= $stats['today_events'] ?></div>
      <div class="stat-label">Today's Events</div>
    </div>
    <div class="stat-card <?= $stats['login_failures_today'] > 5 ? 'warning' : '' ?>">
      <div class="stat-value"><?= $stats['login_failures_today'] ?></div>
      <div class="stat-label">Login Failures Today</div>
    </div>
    <div class="stat-card <?= $stats['lockouts_today'] > 0 ? 'danger' : '' ?>">
      <div class="stat-value"><?= $stats['lockouts_today'] ?></div>
      <div class="stat-label">Lockouts Today</div>
    </div>
    <div class="stat-card <?= $rate_status['active_lockouts'] > 0 ? 'danger' : '' ?>">
      <div class="stat-value"><?= $rate_status['active_lockouts'] ?></div>
      <div class="stat-label">Active Lockouts</div>
    </div>
  </div>
</div>

<div class="card">
  <h3 style="margin-bottom: 12px;">Audit Log</h3>
  
  <form method="get" class="row" style="gap: 12px; margin-bottom: 16px; align-items: end;">
    <div style="flex: 1;">
      <label style="font-size: 0.75rem;">Event Type</label>
      <select name="type" style="padding: 6px 8px; font-size: 0.85rem;">
        <option value="">All Events</option>
        <?php foreach ($event_types as $type): ?>
          <option value="<?= htmlspecialchars($type) ?>" <?= $filter_type === $type ? 'selected' : '' ?>>
            <?= htmlspecialchars(audit_event_label($type)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="flex: 1;">
      <label style="font-size: 0.75rem;">Date From</label>
      <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" style="padding: 6px 8px; font-size: 0.85rem;">
    </div>
    <div style="flex: 1;">
      <label style="font-size: 0.75rem;">Limit</label>
      <select name="limit" style="padding: 6px 8px; font-size: 0.85rem;">
        <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
        <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
        <option value="200" <?= $limit === 200 ? 'selected' : '' ?>>200</option>
        <option value="500" <?= $limit === 500 ? 'selected' : '' ?>>500</option>
      </select>
    </div>
    <div>
      <button class="btn" type="submit" style="padding: 6px 12px;">Filter</button>
    </div>
  </form>

  <div style="overflow-x: auto;">
    <table class="log-table">
      <thead>
        <tr>
          <th>Timestamp</th>
          <th>Event</th>
          <th>User</th>
          <th>IP Address</th>
          <th>Details</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $log): ?>
          <?php
            $type = $log['event_type'] ?? '';
            $badge_class = 'info';
            if (strpos($type, 'success') !== false || $type === AUDIT_LOGIN_SUCCESS) $badge_class = 'success';
            if (strpos($type, 'failure') !== false || $type === AUDIT_LOGIN_FAILURE) $badge_class = 'failure';
            if (strpos($type, 'lockout') !== false || $type === AUDIT_LOGIN_LOCKOUT) $badge_class = 'warning';
            if (strpos($type, 'delete') !== false) $badge_class = 'failure';
          ?>
          <tr>
            <td><?= htmlspecialchars($log['timestamp'] ?? '') ?></td>
            <td><span class="event-badge <?= $badge_class ?>"><?= htmlspecialchars(audit_event_label($type)) ?></span></td>
            <td><?= htmlspecialchars($log['username'] ?? '-') ?></td>
            <td><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
            <td class="details-cell" title="<?= htmlspecialchars(json_encode($log['details'] ?? [])) ?>">
              <?= htmlspecialchars(json_encode($log['details'] ?? [])) ?>
            </td>
          </tr>
        <?php endforeach; ?>
        
        <?php if (empty($logs)): ?>
          <tr><td colspan="5" style="text-align: center;">No audit logs found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php dashboard_end(); ?>
