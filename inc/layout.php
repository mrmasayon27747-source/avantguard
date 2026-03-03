<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function head_html(string $title): void { ?>
<!doctype html>
<html data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($title) ?> - <?= htmlspecialchars(APP_NAME) ?></title>
  <link rel="stylesheet" href="/avantguard/inc/styles.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
  <script defer src="/avantguard/inc/app.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script>
    // Apply saved theme immediately to prevent flash
    (function() {
      const saved = localStorage.getItem('theme') || 'dark';
      document.documentElement.setAttribute('data-theme', saved);
    })();
  </script>
</head>
<body>
<?php }

function foot_html(): void { ?>
<script src="/avantguard/inc/input_validation.js"></script>
</body></html>
<?php }

// Dashboard layout wrapper
function dashboard_start(string $title): void {
  head_html($title);
  ?>
  <div class="dashboard-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="dashboard-main">
      <header class="dashboard-header">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="12" x2="21" y2="12"/>
            <line x1="3" y1="6" x2="21" y2="6"/>
            <line x1="3" y1="18" x2="21" y2="18"/>
          </svg>
        </button>
        <div class="header-actions">
          <div class="header-brand">
            <span class="header-brand-text">WageWise</span>
            <img src="/avantguard/assets/logo.png" alt="WageWise" class="header-brand-logo">
          </div>
          <button class="theme-toggle" id="themeToggle" data-theme-toggle title="Toggle theme">
            <span class="theme-icon">☽</span>
          </button>
        </div>
      </header>
      <div class="dashboard-content">
<?php }

function dashboard_end(): void { ?>
      </div>
    </main>
  </div>
<?php foot_html(); }

