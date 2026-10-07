<?php
/**
 * Loaded at the top of every public and admin page.
 */

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.0.0');

$configFile = APP_ROOT . '/config.php';
if (!is_file($configFile)) {
    // Not installed yet: send the visitor to the web installer.
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $base = preg_replace('#/(admin/)?[^/]*$#', '', $script);
    header('Location: ' . $base . '/install/');
    exit;
}

$config = require $configFile;
$GLOBALS['config'] = $config;

if (!empty($config['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

date_default_timezone_set($config['timezone'] ?? 'UTC');

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('tcms_' . substr(md5($config['app_key'] ?? 'tcms'), 0, 8));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require APP_ROOT . '/includes/functions.php';

try {
    db();
    run_migrations();
    $tz = setting('timezone', '');
    if ($tz && in_array($tz, timezone_identifiers_list(), true)) {
        date_default_timezone_set($tz);
    }
} catch (PDOException $ex) {
    http_response_code(500);
    exit('<h1 style="font-family:sans-serif">Database connection failed</h1><p style="font-family:sans-serif">Please check the credentials in <code>config.php</code>.</p>');
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
