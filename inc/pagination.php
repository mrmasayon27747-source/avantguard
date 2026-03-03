<?php
declare(strict_types=1);

/**
 * Pagination Helper Functions
 * Provides simple pagination logic for list views
 */

define('DEFAULT_PAGE_SIZE', 25);
define('AVAILABLE_PAGE_SIZES', [10, 25, 50, 100]);

/**
 * Get pagination parameters from request
 */
function get_pagination_params(int $default_size = DEFAULT_PAGE_SIZE): array {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per_page = (int)($_GET['per_page'] ?? $default_size);
    
    // Validate per_page against allowed sizes
    if (!in_array($per_page, AVAILABLE_PAGE_SIZES, true)) {
        $per_page = $default_size;
    }
    
    return [
        'page' => $page,
        'per_page' => $per_page
    ];
}

/**
 * Paginate an array of items
 * Returns: ['items' => [...], 'total' => int, 'page' => int, 'per_page' => int, 'total_pages' => int]
 */
function paginate_array(array $items, int $page = 1, int $per_page = DEFAULT_PAGE_SIZE): array {
    $total = count($items);
    $total_pages = max(1, (int)ceil($total / $per_page));
    
    // Ensure page is within bounds
    $page = max(1, min($page, $total_pages));
    
    $offset = ($page - 1) * $per_page;
    $paged_items = array_slice($items, $offset, $per_page);
    
    return [
        'items' => $paged_items,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => $total_pages,
        'start_item' => $total > 0 ? $offset + 1 : 0,
        'end_item' => min($offset + $per_page, $total)
    ];
}

/**
 * Build pagination URL preserving existing query parameters
 */
function pagination_url(int $page, ?int $per_page = null): string {
    $params = $_GET;
    $params['page'] = $page;
    if ($per_page !== null) {
        $params['per_page'] = $per_page;
    }
    return '?' . http_build_query($params);
}

/**
 * Render pagination controls
 */
function render_pagination(array $pagination, bool $show_page_size = true): void {
    $page = $pagination['page'];
    $total_pages = $pagination['total_pages'];
    $per_page = $pagination['per_page'];
    $total = $pagination['total'];
    $start = $pagination['start_item'];
    $end = $pagination['end_item'];
    
    if ($total <= 0) {
        return;
    }
    ?>
    <div class="pagination-container">
        <div class="pagination-info">
            Showing <?= $start ?>–<?= $end ?> of <?= number_format($total) ?> records
        </div>
        
        <div class="pagination-controls">
            <?php if ($show_page_size): ?>
            <div class="page-size-selector">
                <label>Per page:</label>
                <select onchange="window.location.href = this.value">
                    <?php foreach (AVAILABLE_PAGE_SIZES as $size): ?>
                        <option value="<?= htmlspecialchars(pagination_url(1, $size)) ?>" 
                                <?= $size === $per_page ? 'selected' : '' ?>>
                            <?= $size ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination-nav">
                <?php if ($page > 1): ?>
                    <a href="<?= htmlspecialchars(pagination_url(1)) ?>" class="pagination-btn" title="First page">&laquo;</a>
                    <a href="<?= htmlspecialchars(pagination_url($page - 1)) ?>" class="pagination-btn" title="Previous page">&lsaquo;</a>
                <?php else: ?>
                    <span class="pagination-btn disabled">&laquo;</span>
                    <span class="pagination-btn disabled">&lsaquo;</span>
                <?php endif; ?>
                
                <span class="pagination-current">Page <?= $page ?> of <?= $total_pages ?></span>
                
                <?php if ($page < $total_pages): ?>
                    <a href="<?= htmlspecialchars(pagination_url($page + 1)) ?>" class="pagination-btn" title="Next page">&rsaquo;</a>
                    <a href="<?= htmlspecialchars(pagination_url($total_pages)) ?>" class="pagination-btn" title="Last page">&raquo;</a>
                <?php else: ?>
                    <span class="pagination-btn disabled">&rsaquo;</span>
                    <span class="pagination-btn disabled">&raquo;</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * CSS styles for pagination (include once per page)
 */
function render_pagination_styles(): void {
    ?>
    <style>
    .pagination-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid rgba(255,255,255,0.1);
    }
    .pagination-info {
        font-size: 0.85rem;
        color: var(--text-muted);
    }
    .pagination-controls {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .page-size-selector {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.85rem;
    }
    .page-size-selector label {
        color: var(--text-muted);
    }
    .page-size-selector select {
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.15);
        border-radius: 4px;
        padding: 4px 8px;
        color: var(--text-color);
        font-size: 0.85rem;
    }
    .pagination-nav {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .pagination-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.15);
        border-radius: 4px;
        color: var(--text-color);
        text-decoration: none;
        font-size: 0.9rem;
        transition: all 0.2s;
    }
    .pagination-btn:hover:not(.disabled) {
        background: rgba(10, 132, 255, 0.2);
        border-color: rgba(10, 132, 255, 0.5);
        color: #0a84ff;
    }
    .pagination-btn.disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
    .pagination-current {
        padding: 0 12px;
        font-size: 0.85rem;
        color: var(--text-muted);
    }
    </style>
    <?php
}
