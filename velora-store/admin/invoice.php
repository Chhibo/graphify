<?php
require __DIR__ . '/includes/auth.php';
require_admin('orders');
$order = q_one('SELECT * FROM orders WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
if (!$order) {
    exit('Order not found');
}
$docType = ($_GET['type'] ?? '') === 'slip' ? 'slip' : 'invoice';
$otherDocUrl = 'invoice.php?id=' . (int) $order['id'] . '&type=' . ($docType === 'slip' ? 'invoice' : 'slip');
include APP_ROOT . '/includes/invoice-template.php';
