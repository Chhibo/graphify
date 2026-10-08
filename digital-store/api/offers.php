<?php
// Returns offers for the visitor and the unlock token tied to this product.
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/networks.php';

if (!is_installed()) {
    json_out(['error' => 'Store is not installed.'], 503);
}

$productId = (int)($_GET['product'] ?? 0);
$stmt = db()->prepare('SELECT id FROM products WHERE id = ? AND active = 1');
$stmt->execute([$productId]);
if (!$stmt->fetch()) {
    json_out(['error' => 'Product not found.'], 404);
}

// Reuse this visitor's unlock for the product so a refresh keeps their progress.
$token = $_SESSION['unlocks'][$productId] ?? null;
$unlock = null;
if ($token) {
    $q = db()->prepare('SELECT * FROM unlocks WHERE token = ? AND product_id = ?');
    $q->execute([$token, $productId]);
    $unlock = $q->fetch() ?: null;
    if ($unlock && $unlock['completed_at'] && $unlock['completed_at'] + download_ttl() < time()) {
        $unlock = null; // expired, start a fresh one
    }
}
if ($unlock && $unlock['completed_at']) {
    json_out(['token' => $unlock['token'], 'completed' => true,
        'download' => base_url('download.php?token=' . $unlock['token'])]);
}
if (!$unlock) {
    $token = random_token();
    db()->prepare('INSERT INTO unlocks (token, product_id, ip, created_at) VALUES (?, ?, ?, ?)')
        ->execute([$token, $productId, client_ip(), time()]);
    $_SESSION['unlocks'][$productId] = $token;
}

$ip = client_ip();
$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
$max = max(1, min(10, (int)setting('offers_count', '4')));
$errors = [];

foreach (networks_to_try() as $net) {
    try {
        $offers = fetch_offers($net, $token, $ip, $ua, $max);
    } catch (Throwable $ex) {
        $errors[] = $ex->getMessage();
        continue;
    }
    if ($offers) {
        db()->prepare('UPDATE unlocks SET network = ? WHERE token = ?')->execute([$net, $token]);
        json_out(['token' => $token, 'completed' => false, 'network' => $net, 'offers' => $offers]);
    }
    $errors[] = network_definitions()[$net]['label'] . ': no offers for this visitor.';
}

if ($errors) {
    error_log('[digital-store] offers failed: ' . implode(' | ', $errors));
}
$msg = 'No offers are available for your country or device right now. Please try again later.';
if (is_admin()) {
    $msg .= ' [Admin details: ' . ($errors ? implode(' | ', $errors) : 'no CPA network is switched on') . ']';
}
json_out(['token' => $token, 'completed' => false, 'offers' => [], 'error' => $msg]);
