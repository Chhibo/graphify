<?php
/**
 * Bazaarly license server — shared code for api.php and index.php.
 */
declare(strict_types=1);

const LS_SALT = 'bazaarly-license-v1'; // must match LICENSE_SALT in Bazaarly's includes/license.php
const LS_RATE_LIMIT = 60;              // API requests per IP per hour

date_default_timezone_set('UTC');
ini_set('display_errors', '0');

function ls_db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $dir = __DIR__ . '/data';
    $file = glob($dir . '/licenses_*.sqlite')[0] ?? $dir . '/licenses_' . bin2hex(random_bytes(8)) . '.sqlite';
    $pdo = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('CREATE TABLE IF NOT EXISTS codes (
        id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, hash TEXT NOT NULL UNIQUE,
        buyer TEXT NOT NULL DEFAULT \'\', note TEXT NOT NULL DEFAULT \'\', blocked INTEGER NOT NULL DEFAULT 0,
        domain TEXT NOT NULL DEFAULT \'\', site_url TEXT NOT NULL DEFAULT \'\', version TEXT NOT NULL DEFAULT \'\',
        activated_at TEXT NULL, last_seen_at TEXT NULL, created_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS log (
        id INTEGER PRIMARY KEY AUTOINCREMENT, code_id INTEGER NULL, action TEXT NOT NULL, domain TEXT NOT NULL,
        result TEXT NOT NULL, ip TEXT NOT NULL, created_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS settings (name TEXT PRIMARY KEY, value TEXT NOT NULL)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS ix_log_ip ON log (ip, created_at)');
    return $pdo;
}

function ls_q(string $sql, array $p = []): PDOStatement
{
    $st = ls_db()->prepare($sql);
    $st->execute($p);
    return $st;
}

function ls_setting(string $name, string $default = ''): string
{
    $v = ls_q('SELECT value FROM settings WHERE name = ?', [$name])->fetchColumn();
    return $v === false ? $default : (string) $v;
}

function ls_set(string $name, string $value): void
{
    ls_q('INSERT OR REPLACE INTO settings (name, value) VALUES (?, ?)', [$name, $value]);
}

function ls_now(): string
{
    return date('Y-m-d H:i:s');
}

function ls_normalize(string $code): string
{
    $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    return str_starts_with($c, 'BZR') && strlen($c) === 19 ? substr($c, 3) : $c;
}

function ls_hash(string $code): string
{
    return hash('sha256', LS_SALT . '|' . ls_normalize($code));
}

function ls_format(string $code): string
{
    $c = ls_normalize($code);
    return strlen($c) === 16 ? 'BZR-' . implode('-', str_split($c, 4)) : $code;
}

function ls_domain(string $host): string
{
    $host = strtolower(trim($host));
    $host = preg_replace('~^https?://~', '', $host) ?? $host;
    $host = preg_replace('~[/:].*$~', '', $host) ?? $host;
    return preg_replace('/^www\./', '', $host) ?? $host;
}

/** Adds a code (from an import or the generator). Returns false when it already exists or is malformed. */
function ls_add_code(string $code): bool
{
    $c = ls_normalize($code);
    if (!preg_match('/^[A-Z0-9]{16}$/', $c)) {
        return false;
    }
    $st = ls_db()->prepare('INSERT OR IGNORE INTO codes (code, hash, created_at) VALUES (?, ?, ?)');
    $st->execute([ls_format($c), ls_hash($c), ls_now()]);
    return $st->rowCount() > 0;
}

function ls_new_code(): string
{
    $abc = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $c = '';
    for ($i = 0; $i < 16; $i++) {
        $c .= $abc[random_int(0, 31)];
    }
    return ls_format($c);
}

function ls_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
