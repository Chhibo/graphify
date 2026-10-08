<?php
// Admin-only: mark an unlock as completed to test the flow without a real offer.
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';

if (!is_installed() || !is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['error' => 'Not allowed.'], 403);
}
check_csrf();
$token = (string)($_POST['token'] ?? '');
db()->prepare("UPDATE unlocks SET completed_at = ?, network = 'test', offer_id = 'admin-test'
    WHERE token = ? AND completed_at IS NULL")->execute([time(), $token]);
json_out(['ok' => true]);
