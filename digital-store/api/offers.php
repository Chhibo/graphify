<?php
// Returns offers for the visitor and the unlock token tied to this product or locked link.
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/networks.php';

if (!is_installed()) {
    json_out(['error' => 'Store is not installed.'], 503);
}

$productId = (int)($_GET['product'] ?? 0);
$linkId = (int)($_GET['link'] ?? 0);
if ($linkId) {
    $stmt = db()->prepare('SELECT id FROM links WHERE id = ? AND active = 1');
    $stmt->execute([$linkId]);
    $key = 'l' . $linkId;
    $col = 'link_id';
    $itemId = $linkId;
} else {
    $stmt = db()->prepare('SELECT id FROM products WHERE id = ? AND active = 1');
    $stmt->execute([$productId]);
    $key = 'p' . $productId;
    $col = 'product_id';
    $itemId = $productId;
}
if (!$stmt->fetch()) {
    json_out(['error' => 'Not found.'], 404);
}

$ip = client_ip();
$country = visitor_country();

// Countries the owner chose not to serve.
if ($country !== '' && in_array($country, country_list(setting('blocked_countries')), true)) {
    json_out(['completed' => false, 'offers' => [], 'error' => setting_or('blocked_message', 'Sorry, downloads are not available in your country.')]);
}

// Reuse this visitor's unlock so a refresh keeps their progress.
$token = $_SESSION['unlocks'][$key] ?? null;
$unlock = null;
if ($token) {
    $q = db()->prepare("SELECT * FROM unlocks WHERE token = ? AND $col = ?");
    $q->execute([$token, $itemId]);
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
    // Abuse protection: limit how many new lockers one IP can open per hour.
    $limit = (int)setting('ip_hourly_limit', '30');
    if ($limit > 0 && !is_admin()) {
        $q = db()->prepare('SELECT COUNT(*) FROM unlocks WHERE ip = ? AND created_at > ?');
        $q->execute([$ip, time() - 3600]);
        if ((int)$q->fetchColumn() >= $limit) {
            json_out(['completed' => false, 'offers' => [], 'error' => 'Too many requests from your connection. Please try again in an hour.']);
        }
    }
    $token = random_token();
    db()->prepare("INSERT INTO unlocks (token, $col, ip, country, created_at) VALUES (?, ?, ?, ?, ?)")
        ->execute([$token, $itemId, $ip, $country, time()]);
    $_SESSION['unlocks'][$key] = $token;
}

$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
$max = max(1, min(10, (int)setting('offers_count', '4')));
$errors = [];

foreach (networks_to_try($country) as $net) {
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
    $msg .= ' [Admin details: ' . ($errors ? implode(' | ', $errors) : 'no CPA network is switched on for this country') . ']';
}
json_out(['token' => $token, 'completed' => false, 'offers' => [], 'error' => $msg]);
