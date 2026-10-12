<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
money_base_mode(); // the admin always shows the store's main currency

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
    admin_log_request(admin_page_label());
    return $admin;
}

/** Name of the current admin page, used in the activity log. */
function admin_page_label(): string
{
    $page = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
    $labels = [
        'order' => 'Order', 'orders' => 'Orders', 'product-edit' => 'Product', 'products' => 'Products', 'categories' => 'Categories',
        'settings' => 'Settings', 'shipping' => 'Delivery options', 'coupons' => 'Coupons', 'customers' => 'Customers',
        'reviews' => 'Product reviews', 'questions' => 'Product questions', 'testimonials' => 'Testimonials', 'pages' => 'Pages & Blog',
        'page-edit' => 'Page', 'menus' => 'Menus', 'messages' => 'Messages', 'subscribers' => 'Subscribers', 'printful' => 'Printful',
        'staff' => 'Staff accounts', 'backup' => 'Backup', 'import' => 'Import', 'blocklist' => 'Block list', 'abandoned' => 'Abandoned carts',
        'stock' => 'Stock & alerts', 'update' => 'Updates', 'account' => 'My account', 'upload' => 'Image upload',
    ];
    return $labels[$page] ?? ucfirst(str_replace('-', ' ', $page));
}

/* ---------- Two-step login (Settings > Security) ---------- */

function admin_2fa_required(): bool
{
    return setting_on('admin_2fa') && !(defined('ADMIN_2FA_OFF') && ADMIN_2FA_OFF);
}

/** "Remember this device for 30 days": a signed cookie that stops working when the password changes. */
function admin_device_cookie_name(): string
{
    return 'mercho_dev';
}

function admin_device_sign(array $admin, int $expires): string
{
    return hash_hmac('sha256', $admin['id'] . '|' . $expires . '|' . $admin['password'], setting('device_secret'));
}

function admin_device_trusted(array $admin): bool
{
    $raw = (string) ($_COOKIE[admin_device_cookie_name()] ?? '');
    foreach (explode(',', $raw) as $token) {
        $parts = explode('.', $token);
        if (count($parts) === 3 && (int) $parts[0] === (int) $admin['id'] && (int) $parts[1] > time()
            && hash_equals(admin_device_sign($admin, (int) $parts[1]), $parts[2])) {
            return true;
        }
    }
    return false;
}

function admin_device_remember(array $admin): void
{
    $expires = time() + 30 * 86400;
    // Several admins can share one computer: keep the other admins' tokens.
    $tokens = array_filter(explode(',', (string) ($_COOKIE[admin_device_cookie_name()] ?? '')), fn($t) => $t !== '' && strpos($t, $admin['id'] . '.') !== 0);
    $tokens[] = $admin['id'] . '.' . $expires . '.' . admin_device_sign($admin, $expires);
    setcookie(admin_device_cookie_name(), implode(',', array_slice($tokens, -5)), [
        'expires' => $expires, 'path' => (BASE_PATH === '' ? '/' : BASE_PATH . '/'),
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), 'httponly' => true, 'samesite' => 'Lax',
    ]);
}

/** Email a new 6-digit login code. Returns true when the email was sent. */
function admin_send_login_code(array $admin): bool
{
    $code = (string) random_int(100000, 999999);
    $_SESSION['admin_2fa'] = ['id' => (int) $admin['id'], 'hash' => password_hash($code, PASSWORD_DEFAULT), 'expires' => time() + 600, 'tries' => 0, 'sent' => time()];
    return send_template($admin['email'], 'Your login code: ' . $code, 'Your admin login code',
        '<p>Use this code to finish signing in to the admin of ' . e(setting('store_name')) . ':</p>'
        . '<p style="font-size:30px;font-weight:bold;letter-spacing:6px;margin:16px 0">' . $code . '</p>'
        . '<p style="color:#888">It works for 10 minutes. If you did not try to sign in, change your password: someone knows it.</p>');
}

function admin_finish_login(array $admin): void
{
    session_regenerate_id(true);
    unset($_SESSION['admin_2fa']);
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['login_attempts'] = [];
    log_activity('Logged in', '', $admin);
    redirect('admin/dashboard.php');
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
