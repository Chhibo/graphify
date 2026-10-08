<?php
// Shared setup: database, sessions, settings and small helpers.
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('DATA_DIR', APP_ROOT . '/data');
define('FILES_DIR', DATA_DIR . '/files');
define('UPLOADS_DIR', APP_ROOT . '/uploads');
// Written by install.php. Holds the database's random filename, so the database
// can't be downloaded by guessing its name on hosts that ignore .htaccess.
define('CONFIG_FILE', DATA_DIR . '/config.php');
$config = is_file(CONFIG_FILE) ? (require CONFIG_FILE) : [];
define('DB_FILE', DATA_DIR . '/' . basename((string)($config['db_file'] ?? 'store.sqlite')));
unset($config);

if (session_status() === PHP_SESSION_NONE) {
    session_name('dstore');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        // install.php opens the new database itself before config.php exists.
        $pdo = $GLOBALS['installDb'] ?? new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        migrate($pdo);
    }
    return $pdo;
}

function is_installed(): bool
{
    return is_file(CONFIG_FILE) && is_file(DB_FILE);
}

function require_installed(): void
{
    if (!is_installed()) {
        header('Location: ' . base_url('install.php'));
        exit;
    }
}

/**
 * Bring the database up to date. Each step runs once, tracked by SQLite's
 * user_version, so stores installed with an older version upgrade on the
 * first page load after the new files are uploaded.
 */
function migrate(PDO $pdo): void
{
    $version = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    $steps = [
        1 => "
            CREATE TABLE IF NOT EXISTS settings (
                key   TEXT PRIMARY KEY,
                value TEXT NOT NULL DEFAULT ''
            );
            CREATE TABLE IF NOT EXISTS products (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                title       TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT '',
                image       TEXT NOT NULL DEFAULT '',
                file_name   TEXT NOT NULL DEFAULT '',
                file_label  TEXT NOT NULL DEFAULT '',
                file_url    TEXT NOT NULL DEFAULT '',
                active      INTEGER NOT NULL DEFAULT 1,
                downloads   INTEGER NOT NULL DEFAULT 0,
                created_at  INTEGER NOT NULL
            );
            CREATE TABLE IF NOT EXISTS unlocks (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                token        TEXT NOT NULL UNIQUE,
                product_id   INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
                network      TEXT NOT NULL DEFAULT '',
                ip           TEXT NOT NULL DEFAULT '',
                created_at   INTEGER NOT NULL,
                completed_at INTEGER,
                offer_id     TEXT NOT NULL DEFAULT '',
                payout       REAL NOT NULL DEFAULT 0
            );
            CREATE INDEX IF NOT EXISTS idx_unlocks_product ON unlocks(product_id);
            CREATE TABLE IF NOT EXISTS postback_log (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                network    TEXT NOT NULL DEFAULT '',
                ip         TEXT NOT NULL DEFAULT '',
                query      TEXT NOT NULL DEFAULT '',
                result     TEXT NOT NULL DEFAULT '',
                created_at INTEGER NOT NULL
            );
        ",
        2 => "
            CREATE TABLE categories (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                name           TEXT NOT NULL,
                slug           TEXT NOT NULL UNIQUE,
                sort_order     INTEGER NOT NULL DEFAULT 0,
                show_on_home   INTEGER NOT NULL DEFAULT 1,
                show_in_header INTEGER NOT NULL DEFAULT 1
            );
            ALTER TABLE products ADD COLUMN category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL;
            ALTER TABLE products ADD COLUMN featured INTEGER NOT NULL DEFAULT 0;
            CREATE TABLE pages (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                title          TEXT NOT NULL,
                slug           TEXT NOT NULL UNIQUE,
                content        TEXT NOT NULL DEFAULT '',
                sort_order     INTEGER NOT NULL DEFAULT 0,
                show_in_header INTEGER NOT NULL DEFAULT 0,
                show_in_footer INTEGER NOT NULL DEFAULT 1,
                updated_at     INTEGER NOT NULL
            );
            CREATE TABLE messages (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT NOT NULL,
                email      TEXT NOT NULL,
                subject    TEXT NOT NULL DEFAULT '',
                message    TEXT NOT NULL,
                ip         TEXT NOT NULL DEFAULT '',
                is_read    INTEGER NOT NULL DEFAULT 0,
                created_at INTEGER NOT NULL
            );
        ",
    ];
    foreach ($steps as $to => $sql) {
        if ($version >= $to) {
            continue;
        }
        $pdo->beginTransaction();
        $pdo->exec($sql);
        if ($to === 2) {
            seed_default_pages($pdo);
        }
        $pdo->exec('PRAGMA user_version = ' . $to);
        $pdo->commit();
    }
}

function seed_default_pages(PDO $pdo): void
{
    $old = $pdo->query("SELECT value FROM settings WHERE key = 'privacy_text'")->fetchColumn();
    $content = $old !== false && trim((string)$old) !== '' ? (string)$old : <<<'HTML'
<p>To unlock free downloads we show offers from advertising partners (CPA networks such as OGAds and AdBlueMedia).</p>
<p>When you open an offer list, your IP address and browser type are sent to the partner so it can show offers that work in your country and on your device. A random tracking code is attached to each offer so we can confirm completion and unlock your download. We do not ask for your name or email to download.</p>
<p>If you send us a message through the contact form, we keep your name, email and message only to reply to you.</p>
<p>Offers are run by third parties under their own terms and privacy policies. We use a session cookie only to remember your unlock progress.</p>
HTML;
    $pdo->prepare('INSERT INTO pages (title, slug, content, sort_order, show_in_footer, updated_at) VALUES (?, ?, ?, 1, 1, ?)')
        ->execute(['Privacy Policy', 'privacy-policy', $content, time()]);
    $pdo->prepare('INSERT INTO pages (title, slug, content, sort_order, show_in_footer, updated_at) VALUES (?, ?, ?, 2, 1, ?)')
        ->execute(['Terms of Use', 'terms', '<p>Products on this site are free for personal use. You unlock them by completing one sponsored offer. Do not resell or redistribute the files.</p>', time()]);
}

function &settings_cache(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT key, value FROM settings') as $row) {
            $cache[$row['key']] = $row['value'];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $cache = &settings_cache();
    return $cache[$key] ?? $default;
}

/** Like setting(), but an empty value also falls back to the default. */
function setting_or(string $key, string $default): string
{
    $v = setting($key);
    return trim($v) !== '' ? $v : $default;
}

function save_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings (key, value) VALUES (?, ?)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $stmt->execute([$key, $value]);
    $cache = &settings_cache();
    $cache[$key] = $value;
}

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function random_token(int $bytes = 16): string
{
    return bin2hex(random_bytes($bytes));
}

/** URL of the store root, e.g. https://example.com/store/ */
function base_url(string $path = ''): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $appRoot = realpath(APP_ROOT) ?: APP_ROOT;
    $dir = ($docRoot !== '' && strpos($appRoot, $docRoot) === 0)
        ? str_replace('\\', '/', substr($appRoot, strlen($docRoot)))
        : '';
    return ($https ? 'https' : 'http') . '://' . $host . rtrim($dir, '/') . '/' . ltrim($path, '/');
}

function client_ip(): string
{
    // Cloudflare sends the real visitor IP in this header.
    if (setting('trust_cloudflare') === '1' && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = random_token();
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void
{
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid form token. Go back, refresh the page and try again.');
    }
}

function is_admin(): bool
{
    return !empty($_SESSION['admin']);
}

function require_admin(): void
{
    require_installed();
    if (!is_admin()) {
        header('Location: ' . base_url('admin/login.php'));
        exit;
    }
}

function flash(?string $msg = null): ?string
{
    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

function redirect(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}

/** How long a finished unlock keeps its download link working. */
function download_ttl(): int
{
    return max(1, (int)setting('download_hours', '24')) * 3600;
}

function slugify(string $text): string
{
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $text), '-'));
    return $slug !== '' ? $slug : 'item-' . random_token(3);
}

/** A slug not used by any other row in $table. */
function unique_slug(string $table, string $slug, int $exceptId = 0): string
{
    $base = $slug;
    $q = db()->prepare("SELECT COUNT(*) FROM $table WHERE slug = ? AND id != ?");
    for ($i = 2; ; $i++) {
        $q->execute([$slug, $exceptId]);
        if (!(int)$q->fetchColumn()) {
            return $slug;
        }
        $slug = "$base-$i";
    }
}

function upload_error(array $f): ?string
{
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        return 'The file is bigger than your hosting allows (' . ini_get('upload_max_filesize')
            . '). Upload it to Google Drive/Dropbox/MediaFire and use the "File link" field instead.';
    }
    return $f['error'] === UPLOAD_ERR_OK ? null : 'Upload failed (error code ' . $f['error'] . ').';
}

/**
 * Save an uploaded JPG/PNG/GIF/WEBP into uploads/ under a random name.
 * Returns the new file name, '' when no file was sent, or null on error
 * (with the reason appended to $errors).
 */
function save_uploaded_image(?array $img, array &$errors): ?string
{
    if (!$img || $img['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($err = upload_error($img)) {
        $errors[] = $err;
        return null;
    }
    $info = @getimagesize($img['tmp_name']);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
    if (!$ext) {
        $errors[] = 'Images must be JPG, PNG, GIF or WEBP.';
        return null;
    }
    $name = random_token(8) . '.' . $ext;
    if (!move_uploaded_file($img['tmp_name'], UPLOADS_DIR . '/' . $name)) {
        $errors[] = 'Could not save the image. Check that uploads/ is writable.';
        return null;
    }
    return $name;
}

/**
 * Page content written in the admin panel. HTML is allowed (only the admin
 * can write it); plain text gets its line breaks kept.
 */
function render_content(string $content): string
{
    if (strip_tags($content) === $content) {
        $paras = preg_split('/\n\s*\n/', trim(str_replace("\r", '', $content)));
        return implode('', array_map(fn($p) => '<p>' . nl2br(e($p)) . '</p>', $paras));
    }
    return $content;
}

function valid_color(string $c): bool
{
    return (bool)preg_match('/^#[0-9a-f]{6}$/i', $c);
}
