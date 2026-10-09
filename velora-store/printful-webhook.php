<?php
/**
 * Printful calls this URL when an order changes (shipped, failed, cancelled...).
 * Registered from Admin > Printful > "Turn on automatic shipping updates".
 * The secret key in the URL makes sure only Printful (who knows the URL) can call it.
 */
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json');
$key = (string) ($_GET['key'] ?? '');
if (!is_post() || setting('printful_webhook_key') === '' || !hash_equals(setting('printful_webhook_key'), $key)) {
    http_response_code(403);
    exit('{"ok":false}');
}

$event = json_decode((string) file_get_contents('php://input'), true);
$pfOrder = $event['data']['order'] ?? null;
if (!is_array($pfOrder)) {
    exit('{"ok":true}');
}
$order = q_one('SELECT * FROM orders WHERE printful_order_id = ? AND printful_order_id <> ?', [(string) ($pfOrder['id'] ?? ''), ''])
    ?? q_one('SELECT * FROM orders WHERE order_number = ?', [(string) ($pfOrder['external_id'] ?? '')]);

if ($order) {
    $shipments = (array) ($pfOrder['shipments'] ?? []);
    if (!empty($event['data']['shipment'])) {
        $shipments[] = $event['data']['shipment'];
    }
    $status = (string) ($pfOrder['status'] ?? '');
    if (($event['type'] ?? '') === 'order_canceled') {
        $status = 'canceled';
    }
    printful_apply_status($order, $status, $shipments);
}
echo '{"ok":true}';
