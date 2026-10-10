<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

function current_admin(): ?array
{
    static $admin = false;
    if ($admin === false) {
        $admin = !empty($_SESSION['admin_id']) ? q_one('SELECT id, name, email, is_owner, permissions FROM admins WHERE id = ?', [(int) $_SESSION['admin_id']]) : null;
    }
    return $admin;
}

/** Sections a staff member can be given access to (the owner can do everything). */
function admin_sections(): array
{
    return [
        'orders' => 'Orders, invoices, block list & abandoned carts',
        'customers' => 'Customers, messages & subscribers',
        'products' => 'Products, categories, delivery options, reviews, import & Printful',
        'marketing' => 'Coupons',
        'content' => 'Pages, blog, menus & testimonials',
        'reports' => 'Sales reports',
        'settings' => 'Store settings (payments, email, design...)',
    ];
}

/** Can the logged-in admin open this section? '' = any admin, 'owner' = store owner only. */
function admin_can(string $perm): bool
{
    $a = current_admin();
    if (!$a) {
        return false;
    }
    if ($perm === '' || (int) $a['is_owner'] === 1) {
        return true;
    }
    return $perm !== 'owner' && in_array($perm, explode(',', (string) $a['permissions']), true);
}

function require_admin(string $perm = ''): array
{
    $admin = current_admin();
    if (!$admin) {
        redirect('admin/index.php');
    }
    if (!admin_can($perm)) {
        http_response_code(403);
        $adminTitle = 'No access';
        include __DIR__ . '/header.php';
        echo '<div class="card"><h2>No access</h2><p class="muted">Your staff account does not have access to this page. Ask the store owner to give you access.</p><a class="btn btn-primary" href="dashboard.php">Back to dashboard</a></div>';
        include __DIR__ . '/footer.php';
        exit;
    }
    return $admin;
}

/** Read a checkbox value as '1' / '0'. */
function post_flag(string $key): int
{
    return !empty($_POST[$key]) ? 1 : 0;
}

function admin_status_badge(string $status): string
{
    $labels = order_status_labels();
    return '<span class="status st-' . e($status) . '">' . e($labels[$status] ?? ucfirst($status)) . '</span>';
}

function payment_badge(string $status): string
{
    $labels = ['paid' => 'Paid', 'unpaid' => 'Awaiting payment', 'cod' => 'Cash on delivery', 'failed' => 'Payment failed'];
    return '<span class="status pay-' . e($status) . '">' . e($labels[$status] ?? ucfirst($status)) . '</span>';
}
