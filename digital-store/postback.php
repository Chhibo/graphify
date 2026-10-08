<?php
// The CPA network calls this URL when a visitor completes an offer.
// Example: postback.php?network=ogads&secret=XXXX&token={aff_sub4}&payout={payout}&offer={offer_id}
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/networks.php';

header('Content-Type: text/plain; charset=utf-8');
if (!is_installed()) {
    http_response_code(503);
    exit('not installed');
}

$network = (string)($_REQUEST['network'] ?? '');
$secret = (string)($_REQUEST['secret'] ?? '');
$token = (string)($_REQUEST['token'] ?? '');
$payout = (float)($_REQUEST['payout'] ?? 0);
$offer = substr((string)($_REQUEST['offer'] ?? ''), 0, 64);
$ip = client_ip();

$log = function (string $result) use ($network, $ip): void {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $query = preg_replace('/(^|&)secret=[^&]*/', '$1secret=***', $query);
    db()->prepare('INSERT INTO postback_log (network, ip, query, result, created_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([substr($network, 0, 32), $ip, substr((string)$query, 0, 1000), $result, time()]);
};

if (setting('postback_secret') === '' || !hash_equals(setting('postback_secret'), $secret)) {
    $log('rejected: wrong secret');
    http_response_code(403);
    exit('bad secret');
}

$allowed = array_filter(array_map('trim', explode(',', setting('postback_ips'))));
if ($allowed && !in_array($ip, $allowed, true)) {
    $log('rejected: IP not in allow list');
    http_response_code(403);
    exit('ip not allowed');
}

if (!isset(network_definitions()[$network])) {
    $log('rejected: unknown network');
    http_response_code(400);
    exit('unknown network');
}

$q = db()->prepare('SELECT id, completed_at FROM unlocks WHERE token = ?');
$q->execute([$token]);
$unlock = $q->fetch();
if (!$unlock) {
    $log('ignored: unknown token');
    exit('0');
}
if ($unlock['completed_at']) {
    $log('duplicate: already unlocked');
    exit('1');
}

db()->prepare('UPDATE unlocks SET completed_at = ?, network = ?, payout = ?, offer_id = ? WHERE id = ?')
    ->execute([time(), $network, $payout, $offer, $unlock['id']]);
$log('unlocked');
exit('1');
