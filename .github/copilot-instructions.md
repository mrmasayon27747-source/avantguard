# Copilot / AI Agent Instructions — Vanguard Priority1

Purpose: Quickly orient an AI code assistant to be productive in this small PHP JSON-backed payroll app.

Key points
- **Architecture:** PHP server-rendered app. Data is JSON files under `data/` (see [data/users.json](data/users.json)). The app centralizes storage access in `inc/storage.php` and a thin repository layer in [inc/repository.php](inc/repository.php).
- **Entry points & routing:** Pages are plain PHP files at repo root and under `admin/` and `worker/`. `index.php` redirects users by role to `admin/dashboard.php` or `worker/dashboard.php`.
- **Auth & sessions:** Session-based auth lives in [inc/auth.php](inc/auth.php). Use `require_login()` and `require_role('admin')` to enforce access. `login.php` and `signup.php` show common usage patterns.
- **Bootstrap / defaults:** `inc/bootstrap.php` seeds an `admin` user. Useful for local dev reset.

Storage & migration guidance
- All persistent data is JSON. Use repo helpers in [inc/repository.php](inc/repository.php) (e.g. `repo_users()`, `repo_save_users()`, `repo_attendance()`). To migrate to SQL, replace implementations in `inc/storage.php` and keep repository signatures.
- Data file paths are defined in [inc/storage.php](inc/storage.php). The `DATA_DIR` can be overridden via `AVANTGUARD_DATA_DIR` env var (see [inc/config.php](inc/config.php)).

Conventions & patterns to follow
- Use repository functions rather than reading JSON files directly. Example: attendance flow uses `repo_attendance()` → writes to `data/attendance.json`.
- Use helper functions in [inc/helpers.php](inc/helpers.php) for ID generation (`next_id()`), lookups (`find_by_id()`), and normalization (`normalize_pay_type()`).
- Page scaffolding: call `head_html()` / `foot_html()` from [inc/layout.php](inc/layout.php) for consistent markup.
- CSRF: Forms include `csrf_token()` and call `csrf_check()` (see [inc/csrf.php](inc/csrf.php)). Preserve this pattern when adding forms.
- Passwords: Stored as `password_hash()` values; verify with `password_verify()` (see `login.php`).

Developer workflows
- No build system. Run locally with XAMPP/Apache + PHP. Place project in web root (existing base path references assume `/vanguard_priority1`).
- To reset seeded data: remove files in `data/` and load `index.php` or call `inc/bootstrap.php` (it will recreate default admin user).
- Ensure `data/` is writable by PHP; `inc/storage.php` attempts to `mkdir()` if missing.

Files to inspect for common editing tasks
- Auth & session: [inc/auth.php](inc/auth.php)
- Storage & repo: [inc/storage.php](inc/storage.php), [inc/repository.php](inc/repository.php)
- Helpers/utilities: [inc/helpers.php](inc/helpers.php)
- CSRF: [inc/csrf.php](inc/csrf.php)
- Page layout: [inc/layout.php](inc/layout.php), [inc/navbar.php](inc/navbar.php)
- Entry pages: [login.php](login.php), [index.php](index.php), [signup.php](signup.php)

Safety and constraints
- Avoid introducing database assumptions; preserve JSON storage compatibility unless specifically converting storage layer only.
- Preserve existing auth and CSRF patterns. Do not bypass `require_role()` or `csrf_check()` for convenience.

Examples (copyable)
- Read users and find by username:
```php
$users = repo_users();
foreach ($users as $u) {
  if (strcasecmp($u['username'], $username) === 0) { /* ... */ }
}
```
- Save attendance:
```php
$attendance = repo_attendance();
$attendance[] = $newRow;
repo_save_attendance($attendance);
```

If something important is missing or access to runtime (XAMPP) commands would help, ask the repo owner for preferred dev instructions or the local URL to validate behavior.

---
Please review these notes and tell me if you want more examples or stricter rules (tests, lint, or migration steps).
