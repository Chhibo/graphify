<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

function current_admin(): ?array
{
    static $admin = false;
    if ($admin === false) {
        $admin = !empty($_SESSION['admin_id']) ? q_one('SELECT id, name, email FROM admins WHERE id = ?', [(int) $_SESSION['admin_id']]) : null;
    }
    return $admin;
}

function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        redirect('admin/index.php');
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
