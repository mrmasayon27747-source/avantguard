<?php
declare(strict_types=1);

/**
 * Payroll Anomaly Detection
 * Warns admins about unusual pay amounts based on historical patterns
 */

/**
 * Detect anomalies in payroll data before/after payslip creation
 * 
 * @param int $employee_id Employee ID
 * @param float $gross_pay Current gross pay
 * @param float $net_pay Current net pay
 * @param float $overtime_bonus Overtime bonus amount
 * @param float $deductions Total deductions
 * @param float $total_hours Hours worked
 * @param int $days_present Days present (for fixed rate)
 * @param array $historical_payslips Previous payslips for this employee
 * @return array Array of warning messages (empty if no anomalies)
 */
function detect_payroll_anomalies(
    int $employee_id,
    float $gross_pay,
    float $net_pay,
    float $overtime_bonus,
    float $deductions,
    float $total_hours,
    int $days_present,
    array $historical_payslips
): array {
    $warnings = [];
    
    // Filter historical payslips for this employee
    $emp_history = array_filter($historical_payslips, fn($p) => 
        (int)($p['employee_id'] ?? 0) === $employee_id && 
        (string)($p['status'] ?? '') === 'released'
    );
    
    // Need at least 3 historical payslips for meaningful comparison
    if (count($emp_history) < 3) {
        return $warnings; // Not enough data for anomaly detection
    }
    
    // Calculate historical averages
    $hist_gross = array_map(fn($p) => (float)($p['gross_pay'] ?? 0), $emp_history);
    $hist_net = array_map(fn($p) => (float)($p['net_pay'] ?? 0), $emp_history);
    $hist_hours = array_map(fn($p) => (float)($p['total_hours'] ?? 0), $emp_history);
    $hist_days = array_map(fn($p) => (int)($p['days_present'] ?? 0), $emp_history);
    
    $avg_gross = array_sum($hist_gross) / count($hist_gross);
    $avg_net = array_sum($hist_net) / count($hist_net);
    $avg_hours = count($hist_hours) > 0 ? array_sum($hist_hours) / count($hist_hours) : 0;
    $avg_days = count($hist_days) > 0 ? array_sum($hist_days) / count($hist_days) : 0;
    
    // Check for significant deviations (>50% from average)
    $deviation_threshold = 0.5; // 50%
    
    // Gross pay anomaly
    if ($avg_gross > 0) {
        $gross_deviation = abs($gross_pay - $avg_gross) / $avg_gross;
        if ($gross_deviation > $deviation_threshold) {
            $direction = $gross_pay > $avg_gross ? 'higher' : 'lower';
            $pct = round($gross_deviation * 100);
            $warnings[] = [
                'type' => 'gross_pay',
                'severity' => $gross_deviation > 1 ? 'high' : 'medium',
                'message' => "Gross pay (₱" . number_format($gross_pay, 2) . ") is {$pct}% {$direction} than average (₱" . number_format($avg_gross, 2) . ")"
            ];
        }
    }
    
    // Net pay anomaly
    if ($avg_net > 0) {
        $net_deviation = abs($net_pay - $avg_net) / $avg_net;
        if ($net_deviation > $deviation_threshold) {
            $direction = $net_pay > $avg_net ? 'higher' : 'lower';
            $pct = round($net_deviation * 100);
            $warnings[] = [
                'type' => 'net_pay',
                'severity' => $net_deviation > 1 ? 'high' : 'medium',
                'message' => "Net pay (₱" . number_format($net_pay, 2) . ") is {$pct}% {$direction} than average (₱" . number_format($avg_net, 2) . ")"
            ];
        }
    }
    
    // Overtime bonus check (warning if > 30% of base pay)
    $base_pay = $gross_pay - $overtime_bonus;
    if ($base_pay > 0 && $overtime_bonus > 0) {
        $ot_ratio = $overtime_bonus / $base_pay;
        if ($ot_ratio > 0.3) {
            $pct = round($ot_ratio * 100);
            $warnings[] = [
                'type' => 'overtime',
                'severity' => $ot_ratio > 0.5 ? 'high' : 'medium',
                'message' => "Overtime bonus (₱" . number_format($overtime_bonus, 2) . ") is {$pct}% of base pay"
            ];
        }
    }
    
    // Deduction check (warning if > 30% of gross pay)
    if ($gross_pay > 0 && $deductions > 0) {
        $ded_ratio = $deductions / $gross_pay;
        if ($ded_ratio > 0.3) {
            $pct = round($ded_ratio * 100);
            $warnings[] = [
                'type' => 'deductions',
                'severity' => $ded_ratio > 0.5 ? 'high' : 'medium',
                'message' => "Deductions (₱" . number_format($deductions, 2) . ") are {$pct}% of gross pay"
            ];
        }
    }
    
    // Hours anomaly (for hourly workers)
    if ($avg_hours > 0 && $total_hours > 0) {
        $hours_deviation = abs($total_hours - $avg_hours) / $avg_hours;
        if ($hours_deviation > $deviation_threshold) {
            $direction = $total_hours > $avg_hours ? 'more' : 'fewer';
            $pct = round($hours_deviation * 100);
            $warnings[] = [
                'type' => 'hours',
                'severity' => $hours_deviation > 1 ? 'high' : 'medium',
                'message' => "Hours worked (" . number_format($total_hours, 1) . "h) is {$pct}% {$direction} than average (" . number_format($avg_hours, 1) . "h)"
            ];
        }
    }
    
    // Days present anomaly (for fixed rate workers)
    if ($avg_days > 0 && $days_present > 0) {
        $days_deviation = abs($days_present - $avg_days) / $avg_days;
        if ($days_deviation > $deviation_threshold) {
            $direction = $days_present > $avg_days ? 'more' : 'fewer';
            $pct = round($days_deviation * 100);
            $warnings[] = [
                'type' => 'days',
                'severity' => $days_deviation > 1 ? 'high' : 'medium',
                'message' => "Days present ({$days_present}) is {$pct}% {$direction} than average (" . round($avg_days, 1) . ")"
            ];
        }
    }
    
    return $warnings;
}

/**
 * Render anomaly warnings as HTML
 */
function render_anomaly_warnings(array $warnings): void {
    if (empty($warnings)) return;
    ?>
    <div class="anomaly-warnings">
        <div class="anomaly-header">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <strong>Anomaly Warnings</strong>
        </div>
        <ul class="anomaly-list">
            <?php foreach ($warnings as $w): ?>
                <li class="anomaly-item <?= htmlspecialchars($w['severity'] ?? 'medium') ?>">
                    <?= htmlspecialchars($w['message']) ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="anomaly-note">Review these items before releasing the payslip.</p>
    </div>
    <?php
}

/**
 * CSS for anomaly warnings (include in page)
 */
function render_anomaly_styles(): void {
    ?>
    <style>
    .anomaly-warnings {
        background: rgba(255, 159, 10, 0.08);
        border: 1px solid rgba(255, 159, 10, 0.3);
        border-radius: 8px;
        padding: 12px 16px;
        margin: 12px 0;
    }
    .anomaly-header {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #ff9f0a;
        margin-bottom: 8px;
    }
    .anomaly-list {
        margin: 0;
        padding-left: 20px;
    }
    .anomaly-item {
        font-size: 0.85rem;
        margin: 4px 0;
        color: var(--text-color);
    }
    .anomaly-item.high {
        color: #ff3b30;
        font-weight: 500;
    }
    .anomaly-item.medium {
        color: #ff9f0a;
    }
    .anomaly-note {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin: 8px 0 0 0;
    }
    </style>
    <?php
}
