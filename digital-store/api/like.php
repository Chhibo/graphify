<?php
// Like / unlike a product. One like per visitor (session + IP).
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';

if (!is_installed() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false], 400);
}
if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
    json_out(['ok' => false], 403);
}
$id = (int)($_POST['id'] ?? 0);
$voter = hash('sha256', client_ip() . '|' . setting('postback_secret'));
$pdo = db();
$exists = $pdo->prepare('SELECT 1 FROM likes WHERE product_id = ? AND voter = ?');
$exists->execute([$id, $voter]);
$pdo->beginTransaction();
if ($exists->fetchColumn()) {
    $pdo->prepare('DELETE FROM likes WHERE product_id = ? AND voter = ?')->execute([$id, $voter]);
    $liked = false;
} else {
    $ok = $pdo->prepare('INSERT OR IGNORE INTO likes (product_id, voter, created_at) SELECT id, ?, ? FROM products WHERE id = ? AND active = 1');
    $ok->execute([$voter, time(), $id]);
    $liked = $ok->rowCount() > 0;
}
$pdo->prepare('UPDATE products SET likes = (SELECT COUNT(*) FROM likes WHERE product_id = ?) WHERE id = ?')->execute([$id, $id]);
$pdo->commit();
$_SESSION['liked'][$id] = $liked;
$q = $pdo->prepare('SELECT likes FROM products WHERE id = ?');
$q->execute([$id]);
json_out(['ok' => true, 'liked' => $liked, 'likes' => (int)$q->fetchColumn()]);
