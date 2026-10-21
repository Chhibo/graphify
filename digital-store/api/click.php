<?php
// Records which offer a visitor clicked (sent by the locker with sendBeacon).
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';

$token = (string)($_POST['token'] ?? '');
if (!is_installed() || !preg_match('/^[a-f0-9]{32}$/', $token)) {
    json_out(['ok' => false], 400);
}
// Only tokens that belong to this visitor's own lockers count.
if (!in_array($token, $_SESSION['unlocks'] ?? [], true)) {
    json_out(['ok' => false], 403);
}
$q = db()->prepare('SELECT network, country FROM unlocks WHERE token = ?');
$q->execute([$token]);
$u = $q->fetch();
if (!$u) {
    json_out(['ok' => false], 404);
}
$offerId = mb_substr((string)($_POST['offer_id'] ?? ''), 0, 64);
// One click per offer per locker.
$dup = db()->prepare('SELECT 1 FROM offer_clicks WHERE token = ? AND offer_id = ?');
$dup->execute([$token, $offerId]);
if (!$dup->fetchColumn()) {
    db()->prepare('INSERT INTO offer_clicks (token, network, offer_id, offer_name, country, created_at) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$token, $u['network'], $offerId, mb_substr(trim((string)($_POST['offer_name'] ?? '')), 0, 120), $u['country'], time()]);
}
json_out(['ok' => true]);
