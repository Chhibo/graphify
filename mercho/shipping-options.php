<?php
/** Checkout: delivery options + totals for the address being typed (called when country/city change). */
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/json');

set_ship_to((string) ($_GET['country'] ?? ''), (string) ($_GET['city'] ?? ''));
if (isset($_GET['method'])) {
    $_SESSION['shipping_method'] = (int) $_GET['method'];
}
$items = cart_items();
$totals = cart_totals($items);
ob_start();
include __DIR__ . '/includes/shipping-list.php';
echo json_encode([
    'html' => ob_get_clean(),
    'show' => $totals['shipping_methods'] || $totals['no_delivery'],
    'no_delivery' => $totals['no_delivery'],
    'shipping' => $totals['shipping'] > 0 ? money($totals['shipping']) : 'Free',
    'total' => money($totals['total']),
]);
