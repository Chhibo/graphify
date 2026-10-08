<?php
// Polled by the locker to learn when the postback has arrived.
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';

$token = (string)($_GET['token'] ?? '');
if (!is_installed() || !preg_match('/^[a-f0-9]{32}$/', $token)) {
    json_out(['completed' => false], 400);
}
$q = db()->prepare('SELECT completed_at FROM unlocks WHERE token = ?');
$q->execute([$token]);
$row = $q->fetch();
if ($row && $row['completed_at'] && $row['completed_at'] + download_ttl() >= time()) {
    json_out(['completed' => true, 'download' => base_url('download.php?token=' . $token)]);
}
json_out(['completed' => false]);
