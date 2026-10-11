<?php
/**
 * Core helper functions shared by the public site, the admin panel and the installer.
 */

if (!defined('APP_ROOT')) {
    exit('No direct access');
}

/* -------------------------------------------------------------------------
 * Database
 * ---------------------------------------------------------------------- */

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = $GLOBALS['config'];
        $dsn = 'mysql:host=' . $c['db_host'] . ';port=' . ($c['db_port'] ?? 3306) . ';dbname=' . $c['db_name'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $c['db_user'], $c['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

/** Prefixed table name. */
function tbl(string $name): string
{
    return '`' . ($GLOBALS['config']['db_prefix'] ?? '') . $name . '`';
}

function db_all(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function db_one(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function db_value(string $sql, array $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

function db_exec(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

/** Insert an associative array into a table and return the new id. */
function db_insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = 'INSERT INTO ' . tbl($table) . ' (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    db_exec($sql, array_values($data));
    return (int) db()->lastInsertId();
}

function db_update(string $table, array $data, int $id): void
{
    $sets = [];
    foreach (array_keys($data) as $col) {
        $sets[] = '`' . $col . '` = ?';
    }
    $params = array_values($data);
    $params[] = $id;
    db_exec('UPDATE ' . tbl($table) . ' SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/* -------------------------------------------------------------------------
 * Settings
 * ---------------------------------------------------------------------- */

function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        foreach (db_all('SELECT name, value FROM ' . tbl('settings')) as $row) {
            $cache[$row['name']] = $row['value'];
        }
    }
    return $cache;
}

function setting(string $key, $default = '')
{
    $all = settings_all();
    return (isset($all[$key]) && $all[$key] !== '') ? $all[$key] : $default;
}

function save_setting(string $key, $value): void
{
    $exists = db_value('SELECT COUNT(*) FROM ' . tbl('settings') . ' WHERE name = ?', [$key]);
    if ($exists) {
        db_exec('UPDATE ' . tbl('settings') . ' SET value = ? WHERE name = ?', [(string) $value, $key]);
    } else {
        db_exec('INSERT INTO ' . tbl('settings') . ' (name, value) VALUES (?, ?)', [$key, (string) $value]);
    }
}

/* -------------------------------------------------------------------------
 * Output / URLs
 * ---------------------------------------------------------------------- */

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(): string
{
    return rtrim($GLOBALS['config']['base_url'] ?? '', '/');
}

function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

/** Images can be stored as full URLs (http...) or as file names inside /uploads. */
function img_url(?string $value, string $fallback = ''): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return $fallback !== '' ? $fallback : url('assets/img/placeholder.svg');
    }
    if (preg_match('#^(https?:)?//#i', $value)) {
        return $value;
    }
    return url('uploads/' . rawurlencode(basename($value)));
}

function redirect(string $to): void
{
    if (!preg_match('#^https?://#i', $to) && strpos($to, '/') !== 0) {
        $to = url($to);
    }
    header('Location: ' . $to);
    exit;
}

function money($amount): string
{
    $symbol = setting('currency_symbol', '$');
    $amount = (float) $amount;
    $decimals = (floor($amount) == $amount) ? 0 : 2;
    $formatted = number_format($amount, $decimals);
    return setting('currency_position', 'before') === 'after' ? $formatted . ' ' . $symbol : $symbol . $formatted;
}

function format_date(?string $date, string $format = ''): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date($format ?: setting('date_format', 'M j, Y'), $ts) : '';
}

function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) {
        return 'just now';
    }
    $units = [31536000 => 'year', 2592000 => 'month', 604800 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'minute'];
    foreach ($units as $secs => $label) {
        if ($diff >= $secs) {
            $n = (int) floor($diff / $secs);
            return $n . ' ' . $label . ($n > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

function excerpt(?string $text, int $length = 160): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $length)) . '…';
}

/** Split newline separated text into a clean list (used for inclusions / itinerary). */
function lines(?string $text): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $text)), 'strlen'));
}

/**
 * Render admin-authored rich text. Plain text gets paragraphs, HTML is kept but
 * stripped of scripts, event handlers and javascript: URLs.
 */
function rich_text(?string $html): string
{
    $html = (string) $html;
    if ($html === strip_tags($html)) {
        $paras = preg_split('/(\r\n|\r|\n){2,}/', trim($html));
        return implode("\n", array_map(fn ($p) => '<p>' . nl2br(e(trim($p))) . '</p>', array_filter($paras, 'strlen')));
    }
    $allowed = '<p><br><b><strong><i><em><u><ul><ol><li><a><h2><h3><h4><blockquote><img><hr><span><div><table><thead><tbody><tr><th><td>';
    $html = preg_replace('#<(script|style|iframe|object|embed)[^>]*>.*?</\1>#is', '', $html);
    $html = strip_tags($html, $allowed);
    $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/(href|src)\s*=\s*(["\']?)\s*(javascript|data|vbscript):[^"\'>\s]*\2/i', '$1="#"', $html);
    // Inline-only HTML (e.g. a <b> or <a> inside plain text): keep the paragraph breaks.
    if (!preg_match('#<(p|div|h[2-4]|ul|ol|blockquote|table)\b#i', $html)) {
        $paras = preg_split('/(\r\n|\r|\n){2,}/', trim($html));
        $html = implode("\n", array_map(fn ($p) => '<p>' . nl2br(trim($p)) . '</p>', array_filter($paras, 'strlen')));
    }
    return $html;
}

function slugify(string $text): string
{
    $text = mb_strtolower(trim($text));
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'item-' . substr(bin2hex(random_bytes(3)), 0, 6);
}

function unique_slug(string $table, string $source, int $ignoreId = 0): string
{
    $base = slugify($source);
    $slug = $base;
    $i = 2;
    while (db_value('SELECT COUNT(*) FROM ' . tbl($table) . ' WHERE slug = ? AND id <> ?', [$slug, $ignoreId])) {
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

/* -------------------------------------------------------------------------
 * Sessions, CSRF & flash messages
 * ---------------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    $token = $_POST['_token'] ?? '';
    return is_string($token) && hash_equals(csrf_token(), $token);
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function old(string $key, $default = '')
{
    return $_SESSION['_old'][$key] ?? $default;
}

function remember_input(array $data): void
{
    $_SESSION['_old'] = $data;
}

function clear_input(): void
{
    unset($_SESSION['_old']);
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/**
 * Basic spam protection for public forms: honeypot field + per-session throttle.
 * Returns an error message, or null when the submission may proceed.
 */
function spam_check(string $form, int $seconds = 15): ?string
{
    if (!empty($_POST['website'])) {
        return 'Your submission could not be processed.';
    }
    $key = '_throttle_' . $form;
    if (!empty($_SESSION[$key]) && (time() - $_SESSION[$key]) < $seconds) {
        return 'Please wait a few seconds before submitting again.';
    }
    $_SESSION[$key] = time();
    return null;
}

function post(string $key, $default = '')
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

/* -------------------------------------------------------------------------
 * Uploads
 * ---------------------------------------------------------------------- */

/**
 * Handle an image upload. Returns the stored file name, null when no file was sent.
 * Throws RuntimeException on invalid files.
 */
function upload_image(string $field): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    return store_uploaded_image($_FILES[$field]);
}

/** Handle a multiple file input (name="field[]"). Returns the stored file names. */
function upload_images(string $field): array
{
    $names = [];
    $files = $_FILES[$field] ?? null;
    if (!$files || !is_array($files['name'])) {
        return $names;
    }
    foreach (array_keys($files['name']) as $i) {
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $names[] = store_uploaded_image([
            'name' => $files['name'][$i], 'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i], 'size' => $files['size'][$i],
        ]);
    }
    return $names;
}

/** Validate one uploaded image (real image, max 5 MB) and store it under a random name. */
function store_uploaded_image(array $file): string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (error code ' . (int) $file['error'] . ').');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Image is larger than 5 MB.');
    }
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) {
        throw new RuntimeException('Only JPG, PNG, GIF and WEBP images are allowed.');
    }
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], APP_ROOT . '/uploads/' . $name)) {
        throw new RuntimeException('Could not save the uploaded file. Check that /uploads is writable.');
    }
    return $name;
}

/** Copy an uploaded file so a duplicate owns its own copy. URLs are returned unchanged. */
function copy_upload(string $value): string
{
    if ($value === '' || preg_match('#^(https?:)?//#i', $value)) {
        return $value;
    }
    $src = APP_ROOT . '/uploads/' . basename($value);
    if (!is_file($src)) {
        return '';
    }
    $copy = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . pathinfo($src, PATHINFO_EXTENSION);
    return @copy($src, APP_ROOT . '/uploads/' . $copy) ? $copy : '';
}

function delete_upload(?string $value): void
{
    $value = (string) $value;
    if ($value === '' || preg_match('#^(https?:)?//#i', $value)) {
        return;
    }
    $path = APP_ROOT . '/uploads/' . basename($value);
    if (is_file($path)) {
        @unlink($path);
    }
}

/* -------------------------------------------------------------------------
 * Domain helpers
 * ---------------------------------------------------------------------- */

function booking_statuses(): array
{
    return [
        'pending' => ['Pending', 'bg-amber-100 text-amber-700'],
        'confirmed' => ['Confirmed', 'bg-emerald-100 text-emerald-700'],
        'completed' => ['Completed', 'bg-sky-100 text-sky-700'],
        'cancelled' => ['Cancelled', 'bg-rose-100 text-rose-700'],
    ];
}

function status_badge(string $status): string
{
    $map = booking_statuses();
    [$label, $cls] = $map[$status] ?? [ucfirst($status), 'bg-slate-100 text-slate-700'];
    return '<span class="inline-block px-2.5 py-1 rounded-lg text-xs font-bold ' . $cls . '">' . e($label) . '</span>';
}

function generate_booking_reference(): string
{
    $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', setting('booking_prefix', 'WL'))) ?: 'BK';
    do {
        $ref = $prefix . '-' . random_int(100000, 999999);
    } while (db_value('SELECT COUNT(*) FROM ' . tbl('bookings') . ' WHERE reference = ?', [$ref]));
    return $ref;
}

function voucher_url(array $booking): string
{
    return url('voucher.php?ref=' . urlencode($booking['reference']) . '&t=' . urlencode($booking['access_token']));
}

function categories(): array
{
    static $cats = null;
    if ($cats === null) {
        $cats = db_all('SELECT * FROM ' . tbl('categories') . ' ORDER BY sort_order, name');
    }
    return $cats;
}

function bookable_tours(): array
{
    return db_all('SELECT id, type, title, price, child_price, infant_price, allow_children, allow_infants, hide_price, max_guests FROM ' . tbl('tours') . " WHERE status = 'active' ORDER BY type DESC, sort_order, title");
}

/* ---------------- Prices ---------------- */

/** True when a trip's price must not be shown (hidden on the trip, or all prices hidden in Settings). */
function prices_hidden(array $tour = []): bool
{
    return setting('hide_all_prices', '0') === '1' || !empty($tour['hide_price']);
}

/** Price per adult, child and infant. Empty child price = adult price, empty infant price = free. */
function tour_prices(array $tour): array
{
    $adult = (float) $tour['price'];
    return [
        'adult' => $adult,
        'child' => ($tour['child_price'] ?? null) === null ? $adult : (float) $tour['child_price'],
        'infant' => ($tour['infant_price'] ?? null) === null ? 0.0 : (float) $tour['infant_price'],
    ];
}

/** Price shown for a trip/activity: the adult price, or "Price on request" when hidden. */
function tour_price(array $tour): string
{
    return prices_hidden($tour) ? setting('price_hidden_text', 'Price on request') : money($tour['price']);
}

/** Price fields and total for a booking of $tour. */
function booking_quote(array $tour, int $adults, int $children, int $infants): array
{
    if (prices_hidden($tour)) {
        return ['unit_price' => 0, 'child_price' => 0, 'infant_price' => 0, 'total' => 0, 'price_on_request' => 1];
    }
    $p = tour_prices($tour);
    return [
        'unit_price' => $p['adult'],
        'child_price' => $p['child'],
        'infant_price' => $p['infant'],
        'total' => round($adults * $p['adult'] + $children * $p['child'] + $infants * $p['infant'], 2),
        'price_on_request' => 0,
    ];
}

/** Amount of a booking: the total, or "To be confirmed" when the price is on request. */
function booking_total(array $booking): string
{
    return !empty($booking['price_on_request']) ? 'To be confirmed' : money($booking['total']);
}

/** "2 adults, 1 child, 1 infant" */
function guests_summary(array $b): string
{
    $parts = [];
    foreach (['adults' => ['adult', 'adults'], 'children' => ['child', 'children'], 'infants' => ['infant', 'infants']] as $key => [$one, $many]) {
        $n = (int) ($b[$key] ?? 0);
        if ($n > 0) {
            $parts[] = $n . ' ' . ($n === 1 ? $one : $many);
        }
    }
    return $parts ? implode(', ', $parts) : (int) ($b['guests'] ?? 0) . ' guests';
}

/** Price lines of a booking, e.g. [["2 adults × $100", "$200"], ...]. Empty when on request. */
function booking_lines(array $b): array
{
    if (!empty($b['price_on_request'])) {
        return [];
    }
    $lines = [];
    foreach (['adults' => ['unit_price', 'adult', 'adults'], 'children' => ['child_price', 'child', 'children'], 'infants' => ['infant_price', 'infant', 'infants']] as $key => [$priceKey, $one, $many]) {
        $n = (int) ($b[$key] ?? 0);
        if ($n > 0) {
            $price = (float) $b[$priceKey];
            $lines[] = [$n . ' ' . ($n === 1 ? $one : $many) . ' × ' . money($price), $price > 0 ? money($n * $price) : 'Free'];
        }
    }
    return $lines;
}

/* ---------------- Destinations ---------------- */

function destinations(bool $featuredOnly = false): array
{
    return db_all('SELECT d.*, (SELECT COUNT(*) FROM ' . tbl('tours') . " t WHERE t.destination_id = d.id AND t.status = 'active') AS tours_count FROM " . tbl('destinations') . " d WHERE d.status = 'active'" . ($featuredOnly ? ' AND d.is_featured = 1' : '') . ' ORDER BY d.sort_order, d.name');
}

/* ---------------- Wishlist (stored in a cookie, no account needed) ---------------- */

function wishlist_ids(): array
{
    $ids = array_filter(array_map('intval', explode(',', (string) ($_COOKIE['tcms_wishlist'] ?? ''))));
    return array_slice(array_values(array_unique($ids)), 0, 50);
}

function in_wishlist(int $id): bool
{
    return in_array($id, wishlist_ids(), true);
}

/* ---------------- Newsletter ---------------- */

function unsubscribe_url(string $email): string
{
    $token = substr(hash_hmac('sha256', strtolower($email), (string) ($GLOBALS['config']['app_key'] ?? '')), 0, 32);
    return url('unsubscribe.php?e=' . urlencode($email) . '&t=' . $token);
}

function valid_unsubscribe_token(string $email, string $token): bool
{
    parse_str((string) parse_url(unsubscribe_url($email), PHP_URL_QUERY), $q);
    return hash_equals($q['t'], $token);
}

/** Add a subscriber. Returns 'new', 'reactivated' or 'exists'. */
function subscribe_email(string $email, string $name = '', string $source = 'footer'): string
{
    $email = strtolower(trim($email));
    $row = db_one('SELECT id, status FROM ' . tbl('subscribers') . ' WHERE email = ?', [$email]);
    if ($row) {
        if ($row['status'] !== 'active') {
            db_update('subscribers', ['status' => 'active', 'unsubscribed_at' => null], (int) $row['id']);
            return 'reactivated';
        }
        return 'exists';
    }
    db_insert('subscribers', ['email' => $email, 'name' => mb_substr($name, 0, 150), 'status' => 'active', 'source' => $source, 'ip_address' => client_ip(), 'created_at' => now()]);
    return 'new';
}

/* ---------------- SEO ---------------- */

/** Print a JSON-LD structured data block (Google rich results). */
function json_ld(array $data): string
{
    $data = ['@context' => 'https://schema.org'] + $data;
    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';
}

/** Absolute URL of the current page without tracking parameters (canonical link). */
function current_url(array $keep = []): string
{
    $path = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: 'index.php');
    $query = array_intersect_key($_GET, array_flip($keep));
    $path = $path === 'index.php' ? '' : $path;
    return url($path . ($query ? '?' . http_build_query($query) : ''));
}

/* ---------------- Upgrades ---------------- */

/** Bring the database of an older installation up to date (runs once per version). */
function run_migrations(): void
{
    require_once APP_ROOT . '/includes/schema.php';
    $current = (int) setting('db_version', '1');
    if ($current >= SCHEMA_VERSION) {
        return;
    }
    schema_sync(db(), (string) ($GLOBALS['config']['db_prefix'] ?? ''));
    if ($current < 3) {
        // Bookings made before adult/child pricing: every guest was an adult.
        db_exec('UPDATE ' . tbl('bookings') . ' SET adults = guests WHERE children = 0 AND infants = 0');
    }
    save_setting('db_version', (string) SCHEMA_VERSION);
    settings_all(true);
}

function stars(int $rating): string
{
    $rating = max(0, min(5, $rating));
    return str_repeat('<i class="fa-solid fa-star"></i>', $rating) . str_repeat('<i class="fa-regular fa-star"></i>', 5 - $rating);
}

/* -------------------------------------------------------------------------
 * Email
 * ---------------------------------------------------------------------- */

function send_mail(string $to, string $subject, string $bodyHtml): bool
{
    if (!setting('mail_enabled', '1') || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $fromEmail = setting('mail_from', setting('contact_email', 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')));
    $fromName = setting('site_name', 'Travel CMS');
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . mb_encode_mimeheader($fromName) . ' <' . $fromEmail . '>',
        'Reply-To: ' . setting('contact_email', $fromEmail),
    ];
    $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:auto;color:#1e293b">'
        . '<div style="background:#0d9488;color:#fff;padding:20px 24px;border-radius:12px 12px 0 0;font-size:20px;font-weight:bold">' . e($fromName) . '</div>'
        . '<div style="border:1px solid #e2e8f0;border-top:0;padding:24px;border-radius:0 0 12px 12px;font-size:14px;line-height:1.6">' . $bodyHtml . '</div></div>';
    if (setting('smtp_host')) {
        return smtp_send($to, $subject, $html, $fromEmail, $fromName);
    }
    return @mail($to, mb_encode_mimeheader($subject), $html, implode("\r\n", $headers));
}

/**
 * Minimal SMTP client (AUTH LOGIN, SSL or STARTTLS) so bookings can be emailed
 * on hosts where PHP mail() is disabled. Configure it in Settings > Email.
 */
function smtp_send(string $to, string $subject, string $html, string $fromEmail, string $fromName): bool
{
    $host = setting('smtp_host');
    $port = (int) setting('smtp_port', '587');
    $secure = setting('smtp_secure', 'tls');
    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 15);
    if (!$fp) {
        error_log('SMTP connect failed: ' . $errstr);
        return false;
    }
    stream_set_timeout($fp, 15);
    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = function (string $command, array $ok) use ($fp, $read): bool {
        fwrite($fp, $command . "\r\n");
        $reply = $read();
        if (!in_array((int) substr($reply, 0, 3), $ok, true)) {
            error_log('SMTP error after "' . explode(' ', $command)[0] . '": ' . trim($reply));
            return false;
        }
        return true;
    };
    $helo = 'EHLO ' . (parse_url(base_url(), PHP_URL_HOST) ?: 'localhost');
    $ok = (int) substr($read(), 0, 3) === 220 && $cmd($helo, [250]);
    if ($ok && $secure === 'tls') {
        $ok = $cmd('STARTTLS', [220])
            && stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)
            && $cmd($helo, [250]);
    }
    if ($ok && setting('smtp_user') !== '') {
        $ok = $cmd('AUTH LOGIN', [334]) && $cmd(base64_encode(setting('smtp_user')), [334]) && $cmd(base64_encode(setting('smtp_pass')), [235]);
    }
    if ($ok) {
        $message = implode("\r\n", [
            'Date: ' . date('r'),
            'From: ' . mb_encode_mimeheader($fromName) . ' <' . $fromEmail . '>',
            'To: <' . $to . '>',
            'Reply-To: ' . setting('contact_email', $fromEmail),
            'Subject: ' . mb_encode_mimeheader($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($html)),
        ]);
        $ok = $cmd('MAIL FROM:<' . $fromEmail . '>', [250])
            && $cmd('RCPT TO:<' . $to . '>', [250, 251])
            && $cmd('DATA', [354])
            && $cmd($message . "\r\n.", [250]);
    }
    @fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return $ok;
}

function booking_email_table(array $b): string
{
    $rows = [
        'Reference' => $b['reference'],
        'Experience' => $b['tour_title'],
        'Travel date' => format_date($b['travel_date']),
        'Guests' => guests_summary($b),
    ];
    foreach (booking_lines($b) as [$label, $amount]) {
        $rows[$label] = $amount;
    }
    $rows += [
        'Amount due on arrival' => booking_total($b),
        'Status' => ucfirst($b['status']),
    ];
    $html = '<table style="width:100%;border-collapse:collapse;margin:16px 0">';
    foreach ($rows as $k => $v) {
        $html .= '<tr><td style="padding:8px;border-bottom:1px solid #f1f5f9;color:#64748b">' . e($k) . '</td><td style="padding:8px;border-bottom:1px solid #f1f5f9;font-weight:bold">' . e($v) . '</td></tr>';
    }
    return $html . '</table>';
}
