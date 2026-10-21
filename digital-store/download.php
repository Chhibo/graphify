<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require_installed();

$token = (string)($_GET['token'] ?? '');
$q = db()->prepare('SELECT completed_at, product_id, link_id FROM unlocks WHERE token = ?');
$q->execute([$token]);
$unlock = $q->fetch();

if (!$unlock || !$unlock['completed_at'] || $unlock['completed_at'] + download_ttl() < time()) {
    http_response_code(403);
    exit('This download link is not valid or has expired. Go back to the product page to unlock it again.');
}

// Locked link: send the visitor to the hidden address.
if ($unlock['link_id']) {
    $l = db()->prepare('SELECT target_url FROM links WHERE id = ?');
    $l->execute([$unlock['link_id']]);
    $target = (string)$l->fetchColumn();
    if (!preg_match('#^https?://#i', $target)) {
        http_response_code(404);
        exit('This link no longer exists.');
    }
    header('Location: ' . $target);
    exit;
}

$q = db()->prepare('SELECT * FROM products WHERE id = ?');
$q->execute([$unlock['product_id']]);
$row = $q->fetch();
if (!$row) {
    http_response_code(404);
    exit('This product no longer exists.');
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
