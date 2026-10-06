<?php
/**
 * Loaded by every admin page: boots the app and enforces login.
 */
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require __DIR__ . '/layout.php';

function current_admin(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = !empty($_SESSION['admin_id'])
            ? db_one('SELECT id, name, email, role, last_login FROM ' . tbl('users') . ' WHERE id = ?', [(int) $_SESSION['admin_id']])
            : null;
    }
    return $user;
}

function require_login(): array
{
    $user = current_admin();
    if (!$user) {
        $_SESSION['admin_return'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('admin/login.php');
    }
    // Expire idle sessions after 2 hours.
    if (!empty($_SESSION['admin_seen']) && time() - $_SESSION['admin_seen'] > 7200) {
        unset($_SESSION['admin_id']);
        flash('error', 'Your session expired. Please log in again.');
        redirect('admin/login.php');
    }
    $_SESSION['admin_seen'] = time();
    return $user;
}

/** Abort POST requests that fail CSRF validation. */
function require_csrf(string $back): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
        flash('error', 'Security token expired. Please try again.');
        redirect($back);
    }
}

if (!defined('ADMIN_PUBLIC_PAGE')) {
    $admin = require_login();
}
