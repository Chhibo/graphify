<?php
/**
 * Velora Store - bootstrap. Every public page and admin page includes this file first.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.0.0');

if (!is_file(APP_ROOT . '/config.php')) {
    // Not installed yet: send the visitor to the installer.
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $base = preg_replace('#/(admin/)?[^/]*$#', '', $script);
    header('Location: ' . $base . '/install/');
    exit;
}

require APP_ROOT . '/config.php';

error_reporting(E_ALL);
ini_set('display_errors', defined('APP_DEBUG') && APP_DEBUG ? '1' : '0');
date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'UTC');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('velora_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => (BASE_PATH === '' ? '/' : BASE_PATH . '/'),
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require APP_ROOT . '/includes/db.php';
require APP_ROOT . '/includes/functions.php';
require APP_ROOT . '/includes/cart.php';
require APP_ROOT . '/includes/icons.php';
