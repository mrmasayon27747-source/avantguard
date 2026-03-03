<?php
declare(strict_types=1);

/**
 * Helper Functions
 * General utility functions used across the application.
 */

function now_iso(): string {
  return date('Y-m-d H:i:s');
}

function find_by_id(array $items, int $id): ?array {
  foreach ($items as $it) {
    if ((int)($it['id'] ?? 0) === $id) return $it;
  }
  return null;
}

function employee_name(array $emp): string {
  return trim(($emp['name'] ?? '') . ' (' . ($emp['employee_code'] ?? '') . ')');
}


/**
 * Normalizes legacy pay type values to prevent payroll bugs.
 * Supported: hourly, fixed
 * Legacy compatibility: fixed_daily -> fixed
 */
function normalize_pay_type(string $payType): string {
  $pt = strtolower(trim($payType));
  if ($pt === 'fixed_daily') return 'fixed';
  if ($pt === 'fixed') return 'fixed';
  return 'hourly';
}

// ───────────────────────────────────────────────────────────────
// Input Sanitization Functions (Server-side Validation)
// ───────────────────────────────────────────────────────────────

/**
 * Sanitize integer input (whole numbers only)
 */
function sanitize_integer(string $val): string {
  return preg_replace('/[^0-9]/', '', $val);
}

/**
 * Sanitize decimal input (numbers with optional decimal point)
 */
function sanitize_decimal(string $val): string {
  $cleaned = preg_replace('/[^0-9.]/', '', $val);
  // Keep only first decimal point
  $parts = explode('.', $cleaned);
  if (count($parts) > 2) {
    $cleaned = $parts[0] . '.' . implode('', array_slice($parts, 1));
  }
  return $cleaned;
}

/**
 * Sanitize name fields (letters, spaces, hyphens, apostrophes)
 */
function sanitize_name(string $val): string {
  return preg_replace('/[^a-zA-Z\s\-\']/', '', trim($val));
}

/**
 * Sanitize alphanumeric fields (letters, numbers, underscore)
 */
function sanitize_alphanumeric(string $val): string {
  return preg_replace('/[^a-zA-Z0-9_]/', '', $val);
}

/**
 * Sanitize phone number (digits only, max 11)
 */
function sanitize_phone(string $val): string {
  $cleaned = preg_replace('/[^0-9]/', '', $val);
  return substr($cleaned, 0, 11);
}

/**
 * Sanitize alphanumeric with spaces
 */
function sanitize_alphanumeric_space(string $val): string {
  return preg_replace('/[^a-zA-Z0-9\s]/', '', trim($val));
}

