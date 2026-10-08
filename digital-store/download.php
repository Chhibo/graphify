<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require_installed();

$token = (string)($_GET['token'] ?? '');
$q = db()->prepare('SELECT u.completed_at, p.* FROM unlocks u JOIN products p ON p.id = u.product_id WHERE u.token = ?');
$q->execute([$token]);
$row = $q->fetch();

if (!$row || !$row['completed_at'] || $row['completed_at'] + download_ttl() < time()) {
    http_response_code(403);
    exit('This download link is not valid or has expired. Go back to the product page to unlock it again.');
}

db()->prepare('UPDATE products SET downloads = downloads + 1 WHERE id = ?')->execute([$row['id']]);

if ($row['file_url'] !== '') {
    header('Location: ' . $row['file_url']);
    exit;
}

$path = FILES_DIR . '/' . basename($row['file_name']);
if ($row['file_name'] === '' || !is_file($path)) {
    http_response_code(404);
    exit('File missing. Please contact the site owner.');
}

$label = $row['file_label'] !== '' ? $row['file_label'] : basename($path);
$label = str_replace(['"', "\r", "\n"], '', $label);
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $label . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
