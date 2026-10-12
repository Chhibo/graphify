<?php
/** Customer invoice: only for the customer's own orders (logged in, or the order placed in this browser). */
require __DIR__ . '/includes/bootstrap.php';
money_base_mode(); // order amounts are shown in the store's main currency
$number = (string) ($_GET['order'] ?? '');
$order = q_one('SELECT * FROM orders WHERE order_number = ?', [$number]);
$me = current_customer();
$allowed = $order && (($me && (int) $order['customer_id'] === (int) $me['id']) || in_array($number, $_SESSION['my_orders'] ?? [], true));
if (!$allowed) {
    http_response_code(404);
    exit('Invoice not found. Please log in to your account.');
}
$docType = 'invoice';
include __DIR__ . '/includes/invoice-template.php';
