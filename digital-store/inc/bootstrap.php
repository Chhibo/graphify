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

function create_schema(): void
{
    db()->exec("
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
    ");
}

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT key, value FROM settings') as $row) {
            $cache[$row['key']] = $row['value'];
        }
    }
    return $cache[$key] ?? $default;
}

function save_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings (key, value) VALUES (?, ?)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $stmt->execute([$key, $value]);
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
