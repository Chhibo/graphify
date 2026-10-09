<?php
/**
 * Core helpers: database, settings, auth, security, formatting, uploads.
 */
declare(strict_types=1);

/* ------------------------------------------------------------------ paths */

function detect_base_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $name = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $root = str_replace('\\', '/', (string) realpath(APP_ROOT));
    $dir = str_replace('\\', '/', dirname((string) (realpath($script) ?: $script)));
    $rel = str_starts_with($dir, $root) ? substr($dir, strlen($root)) : '';
    $urlDir = str_replace('\\', '/', dirname($name));
    if ($rel !== '' && str_ends_with($urlDir, $rel)) {
        $urlDir = substr($urlDir, 0, -strlen($rel));
    }
    $urlDir = rtrim($urlDir, '/.');
    return $urlDir;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function url(string $path = ''): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

function full_url(string $path = ''): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return (is_https() ? 'https://' : 'http://') . $host . url($path);
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/')) . '?v=' . APP_VERSION;
}

function upload_url(?string $path): string
{
    return $path ? url($path) : '';
}

function redirect(string $path): never
{
    $target = preg_match('~^https?://~', $path) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

function back(string $fallback = 'index.php'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($ref !== '' && parse_url($ref, PHP_URL_HOST) === $host) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

/* --------------------------------------------------------------- database */

function db_connect(array $c): PDO
{
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    if (($c['driver'] ?? 'sqlite') === 'sqlite') {
        $path = $c['path'];
        if (!preg_match('~^(/|[A-Za-z]:)~', $path)) {
            $path = APP_ROOT . '/' . $path;
        }
        $pdo = new PDO('sqlite:' . $path, null, null, $opts);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        return $pdo;
    }
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $c['host'] ?? 'localhost',
        (int) ($c['port'] ?? 3306),
        $c['name'] ?? ''
    );
    $pdo = new PDO($dsn, $c['user'] ?? '', $c['pass'] ?? '', $opts);
    $pdo->exec("SET NAMES utf8mb4");
    return $pdo;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = db_connect($GLOBALS['config']['db']);
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function q_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function q_val(string $sql, array $params = []): mixed
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function db_insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES ('
        . implode(', ', array_fill(0, count($cols), '?')) . ')';
    q($sql, array_values($data));
    return (int) db()->lastInsertId();
}

function db_update(string $table, array $data, string $where, array $params = []): int
{
    $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($data)));
    return q("UPDATE $table SET $set WHERE $where", array_merge(array_values($data), $params))->rowCount();
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/* --------------------------------------------------------------- settings */

function default_settings(): array
{
    return [
        'site_name' => 'Bazaarly',
        'site_tagline' => 'Buy & sell anything near you',
        'site_description' => 'Free local classifieds. Buy and sell cars, phones, furniture, property and more.',
        'logo' => '',
        'favicon' => '',
        'primary_color' => '#0d9488',
        'accent_color' => '#f97316',
        'default_theme' => 'auto',
        'hero_title' => 'Buy & sell anything, right around the corner',
        'hero_subtitle' => 'Thousands of local deals on cars, phones, furniture, property and more. Posting an ad takes less than two minutes.',
        'currency_symbol' => '$',
        'currency_position' => 'before',
        'currency_decimals' => '2',
        'timezone' => 'UTC',
        'date_format' => 'M j, Y',
        'allow_registration' => '1',
        'require_approval' => '0',
        'max_images' => '8',
        'max_image_mb' => '5',
        'listing_expiry_days' => '60',
        'listings_per_page' => '12',
        'show_phone_to_guests' => '0',
        'enable_map' => '1',
        'enable_reviews' => '1',
        'safety_tips' => "Meet in a safe, public place\nNever pay or send money in advance\nInspect the item before you buy\nBeware of deals that seem too good to be true",
        'footer_about' => 'The friendly marketplace for your neighbourhood. Post free ads and connect with local buyers and sellers in minutes.',
        'copyright_text' => '© {year} {site}. All rights reserved.',
        'contact_email' => '',
        'contact_phone' => '',
        'contact_address' => '',
        'mail_from' => '',
        'notify_new_message' => '1',
        'social_facebook' => '',
        'social_twitter' => '',
        'social_instagram' => '',
        'social_youtube' => '',
        'social_linkedin' => '',
        'ad_header' => '',
        'ad_sidebar' => '',
        'ad_listing' => '',
        'ad_footer' => '',
        'custom_head_code' => '',
        'cookie_notice' => '1',
        'cookie_text' => 'We use cookies to keep you signed in and to improve your experience.',
        'maintenance_mode' => '0',
        'maintenance_message' => 'We are doing some quick maintenance. Please check back soon.',
    ];
}

function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = default_settings();
        try {
            foreach (q_all('SELECT name, value FROM settings') as $row) {
                $cache[$row['name']] = (string) $row['value'];
            }
        } catch (Throwable) {
            // table may not exist yet during install
        }
    }
    return $cache;
}

function setting(string $key, mixed $default = ''): string
{
    $all = settings_all();
    return array_key_exists($key, $all) ? (string) $all[$key] : (string) $default;
}

function save_settings(array $values): void
{
    $pdo = db();
    $pdo->beginTransaction();
    foreach ($values as $k => $v) {
        q('DELETE FROM settings WHERE name = ?', [$k]);
        q('INSERT INTO settings (name, value) VALUES (?, ?)', [$k, (string) $v]);
    }
    $pdo->commit();
    settings_all(true);
}

/* ---------------------------------------------------------------- session */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $dir = APP_ROOT . '/data/sessions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        session_save_path($dir);
        ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 30));
    }
    session_name('bzr_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => (BASE_PATH === '' ? '/' : BASE_PATH . '/'),
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => is_https(),
    ]);
    session_start();
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

function render_flashes(): string
{
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $icon = $f['type'] === 'success' ? 'check-circle' : ($f['type'] === 'error' ? 'alert' : 'info');
        $out .= '<div class="alert alert-' . e($f['type']) . '" role="status">' . icon($icon) . '<span>' . e($f['msg']) . '</span>'
            . '<button type="button" class="alert-close" aria-label="Dismiss">' . icon('x') . '</button></div>';
    }
    unset($_SESSION['flash']);
    return $out === '' ? '' : '<div class="flash-stack">' . $out . '</div>';
}

/* --------------------------------------------------------------- security */

function e(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = (string) ($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return $sent !== '' && hash_equals(csrf_token(), $sent);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function too_many_attempts(): bool
{
    $since = date('Y-m-d H:i:s', time() - 900);
    return (int) q_val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > ?', [client_ip(), $since]) >= 10;
}

function record_attempt(): void
{
    db_insert('login_attempts', ['ip' => client_ip(), 'created_at' => now()]);
    if (mt_rand(1, 50) === 1) {
        q('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }
}

/* ------------------------------------------------------------------- auth */

function current_user(bool $refresh = false): ?array
{
    static $user = false;
    if ($user === false || $refresh) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $user = q_one('SELECT * FROM users WHERE id = ?', [(int) $_SESSION['uid']]);
            if (!$user || $user['status'] === 'banned') {
                unset($_SESSION['uid']);
                $user = null;
            }
        }
    }
    return $user;
}

function user_id(): int
{
    return (int) (current_user()['id'] ?? 0);
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function login_user(array $user, bool $remember = false): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
    q('UPDATE users SET last_login = ? WHERE id = ?', [now(), $user['id']]);
    if ($remember) {
        $p = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires' => time() + 60 * 60 * 24 * 30,
            'path' => $p['path'],
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => is_https(),
        ]);
    }
}

function logout_user(): void
{
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path']]);
    session_destroy();
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        flash('info', 'Please sign in to continue.');
        $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login.php');
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        flash('error', 'You do not have access to the admin panel.');
        redirect('dashboard/');
    }
    return $u;
}

function intended_url(string $fallback): string
{
    $target = $_SESSION['intended'] ?? '';
    unset($_SESSION['intended']);
    if ($target !== '' && str_starts_with($target, '/') && !str_starts_with($target, '//')) {
        return $target;
    }
    return url($fallback);
}

/* ------------------------------------------------------------- formatting */

function money(float|string|null $amount): string
{
    $n = number_format((float) $amount, (int) setting('currency_decimals', '2'));
    $sym = setting('currency_symbol', '$');
    return setting('currency_position') === 'after' ? $n . ' ' . $sym : $sym . $n;
}

function listing_price(array $l): string
{
    return match ($l['price_type'] ?? 'fixed') {
        'free' => 'Free',
        'contact' => 'Ask for price',
        default => money($l['price']),
    };
}

function format_date(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    return date(setting('date_format', 'M j, Y') ?: 'M j, Y', strtotime($dt));
}

function time_ago(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $diff = time() - strtotime($dt);
    if ($diff < 60) {
        return 'just now';
    }
    $units = [31536000 => 'year', 2592000 => 'month', 604800 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'minute'];
    foreach ($units as $secs => $name) {
        if ($diff >= $secs) {
            $n = intdiv($diff, $secs);
            return $n . ' ' . $name . ($n > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('~[^\pL\pN]+~u', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'item';
}

function excerpt(string $text, int $len = 140): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');
    return mb_strlen($text) > $len ? mb_substr($text, 0, $len - 1) . '…' : $text;
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out !== '' ? $out : '?';
}

function avatar_html(?array $user, string $size = ''): string
{
    $cls = 'avatar' . ($size ? ' avatar-' . $size : '');
    if (!$user) {
        return '<span class="' . $cls . '">?</span>';
    }
    if (!empty($user['avatar'])) {
        return '<img class="' . $cls . '" src="' . e(upload_url($user['avatar'])) . '" alt="' . e($user['name']) . '" loading="lazy">';
    }
    $hue = ((int) ($user['id'] ?? 0) * 47) % 360;
    return '<span class="' . $cls . '" style="--h:' . $hue . '">' . e(initials((string) $user['name'])) . '</span>';
}

function status_badge(string $status): string
{
    $map = [
        'active' => 'success', 'pending' => 'warning', 'rejected' => 'danger', 'sold' => 'info',
        'expired' => 'muted', 'banned' => 'danger', 'open' => 'warning', 'resolved' => 'success',
    ];
    return '<span class="badge badge-' . ($map[$status] ?? 'muted') . '">' . e(ucfirst($status)) . '</span>';
}

function stars(float $rating, string $cls = ''): string
{
    $out = '<span class="stars ' . e($cls) . '" title="' . number_format($rating, 1) . ' / 5">';
    for ($i = 1; $i <= 5; $i++) {
        $out .= icon('star', $rating >= $i - 0.25 ? 'on' : ($rating >= $i - 0.75 ? 'half' : ''));
    }
    return $out . '</span>';
}

function condition_label(string $c): string
{
    return ['new' => 'New', 'like_new' => 'Like new', 'used' => 'Used', 'refurbished' => 'Refurbished'][$c] ?? '';
}

function ad_slot(string $name): string
{
    $html = setting('ad_' . $name);
    return trim($html) === '' ? '' : '<div class="ad-slot ad-' . e($name) . '">' . $html . '</div>';
}

function copyright_text(): string
{
    return strtr(setting('copyright_text'), ['{year}' => date('Y'), '{site}' => setting('site_name')]);
}

/* ------------------------------------------------------------- pagination */

function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page = min(max(1, $page), $pages);
    return [$page, $pages, ($page - 1) * $perPage];
}

function pagination_links(int $page, int $pages): string
{
    if ($pages <= 1) {
        return '';
    }
    $link = function (int $p) {
        $qs = $_GET;
        $qs['page'] = $p;
        return '?' . http_build_query($qs);
    };
    $out = '<nav class="pagination" aria-label="Pagination">';
    if ($page > 1) {
        $out .= '<a href="' . e($link($page - 1)) . '" aria-label="Previous">' . icon('chevron-left') . '</a>';
    }
    $window = [];
    for ($i = 1; $i <= $pages; $i++) {
        if ($i === 1 || $i === $pages || abs($i - $page) <= 1) {
            $window[] = $i;
        }
    }
    $prev = 0;
    foreach ($window as $i) {
        if ($i - $prev > 1) {
            $out .= '<span class="gap">…</span>';
        }
        $out .= $i === $page
            ? '<span class="current" aria-current="page">' . $i . '</span>'
            : '<a href="' . e($link($i)) . '">' . $i . '</a>';
        $prev = $i;
    }
    if ($page < $pages) {
        $out .= '<a href="' . e($link($page + 1)) . '" aria-label="Next">' . icon('chevron-right') . '</a>';
    }
    return $out . '</nav>';
}

/* -------------------------------------------------------------- categories */

function categories_all(): array
{
    static $cats = null;
    if ($cats === null) {
        $cats = [];
        foreach (q_all('SELECT * FROM categories ORDER BY sort_order, name') as $c) {
            $cats[(int) $c['id']] = $c;
        }
    }
    return $cats;
}

function category_tree(): array
{
    $tree = [];
    $all = categories_all();
    foreach ($all as $id => $c) {
        if ((int) $c['parent_id'] === 0 || !isset($all[(int) $c['parent_id']])) {
            $tree[$id] = $c + ['children' => []];
        }
    }
    foreach ($all as $id => $c) {
        $pid = (int) $c['parent_id'];
        if ($pid && isset($tree[$pid])) {
            $tree[$pid]['children'][$id] = $c;
        }
    }
    return $tree;
}

function category_ids_with_children(int $id): array
{
    $ids = [$id];
    foreach (categories_all() as $cid => $c) {
        if ((int) $c['parent_id'] === $id) {
            $ids[] = $cid;
        }
    }
    return $ids;
}

function category_options(int $selected = 0, bool $parentsSelectable = true): string
{
    $out = '';
    foreach (category_tree() as $id => $c) {
        if (empty($c['children'])) {
            $out .= '<option value="' . $id . '"' . ($id === $selected ? ' selected' : '') . '>' . e($c['name']) . '</option>';
            continue;
        }
        if ($parentsSelectable) {
            $out .= '<option value="' . $id . '"' . ($id === $selected ? ' selected' : '') . '>' . e($c['name']) . '</option>';
            foreach ($c['children'] as $cid => $ch) {
                $out .= '<option value="' . $cid . '"' . ($cid === $selected ? ' selected' : '') . '>&nbsp;&nbsp;&nbsp;' . e($ch['name']) . '</option>';
            }
        } else {
            $out .= '<optgroup label="' . e($c['name']) . '">';
            $out .= '<option value="' . $id . '"' . ($id === $selected ? ' selected' : '') . '>' . e($c['name']) . ' (general)</option>';
            foreach ($c['children'] as $cid => $ch) {
                $out .= '<option value="' . $cid . '"' . ($cid === $selected ? ' selected' : '') . '>' . e($ch['name']) . '</option>';
            }
            $out .= '</optgroup>';
        }
    }
    return $out;
}

function category_counts(): array
{
    $counts = [];
    [$where, $params] = visible_listing_where();
    foreach (q_all("SELECT l.category_id, COUNT(*) AS n FROM listings l JOIN users u ON u.id = l.user_id WHERE $where GROUP BY l.category_id", $params) as $r) {
        $counts[(int) $r['category_id']] = (int) $r['n'];
    }
    $all = categories_all();
    $totals = $counts;
    foreach ($counts as $cid => $n) {
        $pid = (int) ($all[$cid]['parent_id'] ?? 0);
        if ($pid) {
            $totals[$pid] = ($totals[$pid] ?? 0) + $n;
        }
    }
    return $totals;
}

/* ---------------------------------------------------------------- listings */

function visible_listing_where(string $alias = 'l'): array
{
    return [
        "$alias.status = 'active' AND ($alias.expires_at IS NULL OR $alias.expires_at > ?) AND u.status = 'active'",
        [now()],
    ];
}

function listing_select(): string
{
    return 'SELECT l.*, c.name AS category_name, c.icon AS category_icon, u.name AS seller_name, u.verified AS seller_verified,
        (SELECT thumb FROM listing_images i WHERE i.listing_id = l.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover
        FROM listings l
        JOIN users u ON u.id = l.user_id
        LEFT JOIN categories c ON c.id = l.category_id';
}

function listing_url(array $l): string
{
    return url('listing.php?id=' . (int) $l['id']);
}

function favorite_ids(): array
{
    static $ids = null;
    if ($ids === null) {
        $ids = [];
        if (is_logged_in()) {
            foreach (q_all('SELECT listing_id FROM favorites WHERE user_id = ?', [user_id()]) as $r) {
                $ids[(int) $r['listing_id']] = true;
            }
        }
    }
    return $ids;
}

function delete_listing(int $id): void
{
    foreach (q_all('SELECT path, thumb FROM listing_images WHERE listing_id = ?', [$id]) as $img) {
        delete_upload($img['path']);
        delete_upload($img['thumb']);
    }
    q('DELETE FROM listing_images WHERE listing_id = ?', [$id]);
    q('DELETE FROM favorites WHERE listing_id = ?', [$id]);
    q('DELETE FROM reports WHERE listing_id = ?', [$id]);
    foreach (q_all('SELECT id FROM conversations WHERE listing_id = ?', [$id]) as $c) {
        q('DELETE FROM messages WHERE conversation_id = ?', [$c['id']]);
    }
    q('DELETE FROM conversations WHERE listing_id = ?', [$id]);
    q('DELETE FROM listings WHERE id = ?', [$id]);
}

function delete_user_account(int $id): void
{
    foreach (q_all('SELECT id FROM listings WHERE user_id = ?', [$id]) as $l) {
        delete_listing((int) $l['id']);
    }
    $u = q_one('SELECT avatar FROM users WHERE id = ?', [$id]);
    if ($u) {
        delete_upload($u['avatar']);
    }
    foreach (q_all('SELECT id FROM conversations WHERE buyer_id = ? OR seller_id = ?', [$id, $id]) as $c) {
        q('DELETE FROM messages WHERE conversation_id = ?', [$c['id']]);
    }
    q('DELETE FROM conversations WHERE buyer_id = ? OR seller_id = ?', [$id, $id]);
    q('DELETE FROM favorites WHERE user_id = ?', [$id]);
    q('DELETE FROM reviews WHERE seller_id = ? OR reviewer_id = ?', [$id, $id]);
    q('UPDATE reports SET user_id = 0 WHERE user_id = ?', [$id]);
    q('DELETE FROM password_resets WHERE user_id = ?', [$id]);
    q('DELETE FROM users WHERE id = ?', [$id]);
}

function expire_listings(): void
{
    q("UPDATE listings SET status = 'expired' WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at <= ?", [now()]);
}

function new_expiry(): ?string
{
    $days = (int) setting('listing_expiry_days', '60');
    return $days > 0 ? date('Y-m-d H:i:s', time() + $days * 86400) : null;
}

function unread_count(): int
{
    if (!is_logged_in()) {
        return 0;
    }
    $uid = user_id();
    return (int) q_val(
        'SELECT COALESCE(SUM(CASE WHEN buyer_id = ? THEN buyer_unread ELSE seller_unread END), 0)
         FROM conversations WHERE buyer_id = ? OR seller_id = ?',
        [$uid, $uid, $uid]
    );
}

function seller_rating(int $sellerId): array
{
    $r = q_one('SELECT AVG(rating) AS avg, COUNT(*) AS n FROM reviews WHERE seller_id = ?', [$sellerId]);
    return [round((float) ($r['avg'] ?? 0), 1), (int) ($r['n'] ?? 0)];
}

/* ----------------------------------------------------------------- uploads */

function normalize_files(?array $files): array
{
    if (!$files || !isset($files['name'])) {
        return [];
    }
    if (!is_array($files['name'])) {
        return $files['error'] === UPLOAD_ERR_NO_FILE ? [] : [$files];
    }
    $out = [];
    foreach ($files['name'] as $i => $name) {
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = [
            'name' => $name,
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i],
        ];
    }
    return $out;
}

/**
 * Validate, resize and store an uploaded image.
 * Returns ['path' => ..., 'thumb' => ...] on success or an error string.
 */
function store_image(array $file, string $folder, int $maxW = 1600, int $thumbW = 520): array|string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return match ($file['error'] ?? 0) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The image "' . ($file['name'] ?? '') . '" is too large.',
            default => 'Upload failed for "' . ($file['name'] ?? '') . '".',
        };
    }
    $maxBytes = max(1, (int) setting('max_image_mb', '5')) * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        return 'The image "' . $file['name'] . '" is larger than ' . setting('max_image_mb', '5') . ' MB.';
    }
    if (!is_uploaded_file($file['tmp_name']) && !defined('BZR_TESTING')) {
        return 'Invalid upload.';
    }
    $info = @getimagesize($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!$info || !isset($allowed[$info['mime']])) {
        return '"' . $file['name'] . '" is not a supported image (JPG, PNG, WEBP or GIF).';
    }
    $sub = 'uploads/' . trim($folder, '/') . '/' . date('Y/m');
    $dir = APP_ROOT . '/' . $sub;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return 'The uploads folder is not writable.';
    }
    $base = bin2hex(random_bytes(10));
    $mime = $info['mime'];

    $canGd = function_exists('imagecreatetruecolor') && match ($mime) {
        'image/jpeg' => function_exists('imagecreatefromjpeg'),
        'image/png' => function_exists('imagecreatefrompng'),
        'image/webp' => function_exists('imagecreatefromwebp'),
        'image/gif' => function_exists('imagecreatefromgif'),
        default => false,
    };

    if (!$canGd || $mime === 'image/gif') {
        $ext = $allowed[$mime];
        $rel = "$sub/$base.$ext";
        if (!move_or_copy($file['tmp_name'], APP_ROOT . '/' . $rel)) {
            return 'Could not save the uploaded image.';
        }
        return ['path' => $rel, 'thumb' => $rel];
    }

    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
        'image/png' => @imagecreatefrompng($file['tmp_name']),
        'image/webp' => @imagecreatefromwebp($file['tmp_name']),
    };
    if (!$src) {
        return 'Could not read "' . $file['name'] . '".';
    }
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp_name']);
        $rot = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($rot) {
            $rotated = imagerotate($src, $rot, 0);
            if ($rotated) {
                imagedestroy($src);
                $src = $rotated;
            }
        }
    }
    $useWebp = function_exists('imagewebp');
    $ext = $useWebp ? 'webp' : 'jpg';
    $rel = "$sub/$base.$ext";
    $relThumb = "$sub/{$base}_t.$ext";
    $ok = save_resized($src, APP_ROOT . '/' . $rel, $maxW, $useWebp)
        && save_resized($src, APP_ROOT . '/' . $relThumb, $thumbW, $useWebp);
    imagedestroy($src);
    return $ok ? ['path' => $rel, 'thumb' => $relThumb] : 'Could not save the uploaded image.';
}

function move_or_copy(string $from, string $to): bool
{
    return is_uploaded_file($from) ? move_uploaded_file($from, $to) : copy($from, $to);
}

function save_resized(GdImage $src, string $dest, int $maxW, bool $webp): bool
{
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1, $maxW / max(1, $w));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    if ($webp) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    } else {
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = $webp ? imagewebp($dst, $dest, 82) : imagejpeg($dst, $dest, 84);
    imagedestroy($dst);
    return (bool) $ok;
}

function delete_upload(?string $rel): void
{
    if (!$rel || !str_starts_with($rel, 'uploads/') || str_contains($rel, '..')) {
        return;
    }
    $path = APP_ROOT . '/' . $rel;
    if (is_file($path)) {
        @unlink($path);
    }
}

/* -------------------------------------------------------------------- mail */

function send_mail(string $to, string $subject, string $body): bool
{
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $from = setting('mail_from') ?: ('no-reply@' . $host);
    $name = str_replace(["\r", "\n", '"'], '', setting('site_name'));
    $headers = [
        'From: "' . $name . '" <' . $from . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];
    $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return @mail($to, $subject, $body, implode("\r\n", $headers));
}

/* ------------------------------------------------------------------- misc */

function render_maintenance(): never
{
    http_response_code(503);
    $name = e(setting('site_name'));
    $msg = nl2br(e(setting('maintenance_message')));
    $login = e(url('login.php'));
    echo "<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>$name – Maintenance</title>"
        . '<link rel="stylesheet" href="' . e(asset('css/app.css')) . '"></head><body class="center-page"><div class="card narrow center">'
        . "<h1>We'll be right back</h1><p class=\"muted\">$msg</p><p><a class=\"btn btn-ghost btn-sm\" href=\"$login\">Admin sign in</a></p></div></body></html>";
    exit;
}

function not_found(string $message = 'The page you are looking for could not be found.'): never
{
    http_response_code(404);
    $pageTitle = 'Not found';
    require APP_ROOT . '/includes/header.php';
    echo '<section class="container section"><div class="empty-state big">' . icon('search')
        . '<h1>Nothing here</h1><p>' . e($message) . '</p><a class="btn btn-primary" href="' . e(url('listings.php')) . '">Browse ads</a></div></section>';
    require APP_ROOT . '/includes/footer.php';
    exit;
}

function valid_color(string $c, string $fallback): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : $fallback;
}
