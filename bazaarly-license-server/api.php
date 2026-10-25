<?php
/**
 * Called by Bazaarly's installer and scheduled jobs.
 * POST action=activate|check, code, domain, site_url, version  →  JSON {status, message}
 * status: active | used | blocked | invalid | error
 */
require __DIR__ . '/inc.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function reply(string $status, string $message = '', int $http = 200): never
{
    http_response_code($http);
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    reply('error', 'Use POST.', 405);
}
$ip = ls_ip();
$recent = (int) ls_q('SELECT COUNT(*) FROM log WHERE ip = ? AND created_at > ?', [$ip, date('Y-m-d H:i:s', time() - 3600)])->fetchColumn();
if ($recent >= LS_RATE_LIMIT) {
    reply('error', 'Too many requests. Please try again later.', 429);
}

$action = ($_POST['action'] ?? '') === 'check' ? 'check' : 'activate';
$domain = ls_domain((string) ($_POST['domain'] ?? ''));
$code = (string) ($_POST['code'] ?? '');
$log = function (?int $codeId, string $result) use ($action, $domain, $ip): void {
    ls_q('INSERT INTO log (code_id, action, domain, result, ip, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$codeId, $action, substr($domain, 0, 190), $result, $ip, ls_now()]);
};

if ($domain === '' || !preg_match('/^[a-z0-9.-]{1,190}$/', $domain)) {
    $log(null, 'bad domain');
    reply('error', 'Missing or invalid domain.', 400);
}
$row = ls_q('SELECT * FROM codes WHERE hash = ?', [ls_hash($code)])->fetch();
if (!$row) {
    $log(null, 'invalid');
    reply('invalid', 'This purchase code is not valid.');
}
$id = (int) $row['id'];
if ($row['blocked']) {
    $log($id, 'blocked');
    reply('blocked', 'This purchase code has been disabled. Please contact the seller.');
}
if ($row['domain'] !== '' && $row['domain'] !== $domain) {
    $log($id, 'used');
    reply('used', 'This purchase code is already used on another website. Each code works on one website — contact the seller if you moved your site.');
}

$siteUrl = substr((string) ($_POST['site_url'] ?? ''), 0, 255);
$version = substr((string) ($_POST['version'] ?? ''), 0, 20);
if ($row['domain'] === '') {
    ls_q('UPDATE codes SET domain = ?, site_url = ?, version = ?, activated_at = ?, last_seen_at = ? WHERE id = ?', [$domain, $siteUrl, $version, ls_now(), ls_now(), $id]);
    $log($id, 'activated');
} else {
    ls_q('UPDATE codes SET site_url = ?, version = ?, last_seen_at = ? WHERE id = ?', [$siteUrl ?: $row['site_url'], $version ?: $row['version'], ls_now(), $id]);
    $log($id, 'ok');
}
reply('active');
