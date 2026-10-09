<?php
/**
 * Loaded at the top of every page.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.0.0');

require APP_ROOT . '/includes/functions.php';
require APP_ROOT . '/includes/icons.php';

$configFile = APP_ROOT . '/config.php';
$GLOBALS['config'] = is_file($configFile) ? require $configFile : null;

define('BASE_PATH', rtrim((string) ($GLOBALS['config']['base_path'] ?? detect_base_path()), '/'));

if (!$GLOBALS['config']) {
    header('Location: ' . BASE_PATH . '/install/');
    exit;
}

if (!empty($GLOBALS['config']['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

$tz = setting('timezone', 'UTC');
date_default_timezone_set(in_array($tz, timezone_identifiers_list(), true) ? $tz : 'UTC');

start_session();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Every POST request must carry a valid CSRF token.
if (is_post()) {
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('error', 'The upload was too large for the server. Try fewer or smaller images.');
        back();
    }
    if (!csrf_valid()) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            http_response_code(419);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Session expired. Please reload the page.']);
            exit;
        }
        flash('error', 'Your session expired. Please try again.');
        back();
    }
}

if (mt_rand(1, 25) === 1) {
    expire_listings();
}

if (setting('maintenance_mode') === '1' && !is_admin() && !defined('ALLOW_IN_MAINTENANCE')) {
    render_maintenance();
}
