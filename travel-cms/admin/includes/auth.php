<?php
/**
 * Loaded by every admin page: boots the app and enforces login.
 */
define('ADMIN_AREA', true);
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

/**
 * Staff roles. Admins can do everything; managers run the content and bookings
 * but not settings or staff accounts; booking agents only handle bookings and messages.
 */
function admin_roles(): array
{
    return [
        'admin' => ['Administrator', 'Full access, including settings and staff accounts.', ['*']],
        'manager' => ['Manager', 'Bookings, trip requests, trips, destinations, FAQs, blog, pages, reviews, messages, subscribers and reports. No settings, languages, templates, backups or staff accounts.',
            ['dashboard', 'bookings', 'bookings_delete', 'content', 'reviews', 'messages', 'subscribers', 'reports']],
        'agent' => ['Booking Agent', 'Bookings, trip requests, calendar and contact messages only. Cannot delete bookings.',
            ['dashboard', 'bookings', 'messages']],
    ];
}

function role_label(string $role): string
{
    return admin_roles()[$role][0] ?? ucfirst($role);
}

/** Can the logged-in staff member use this part of the admin panel? */
function can(string $permission): bool
{
    $user = current_admin();
    if (!$user) {
        return false;
    }
    $perms = admin_roles()[$user['role']][2] ?? [];
    return in_array('*', $perms, true) || in_array($permission, $perms, true);
}

function require_permission(string $permission): void
{
    if (!can($permission)) {
        flash('error', 'Your account does not have access to that section.');
        redirect('admin/index.php');
    }
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
