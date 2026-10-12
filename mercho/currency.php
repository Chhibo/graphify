<?php
/** The visitor picks a display currency (header switcher). Prices, orders and payments stay in the main currency. */
require __DIR__ . '/includes/bootstrap.php';

$code = strtoupper((string) ($_GET['c'] ?? ''));
if (isset(store_currencies()[$code])) {
    $_SESSION['currency'] = $code;
}
// Go back to the page the visitor was on (only addresses of this store).
$back = (string) ($_GET['back'] ?? '');
if ($back === '' || !preg_match('#^/[^/\\\\]#', $back)) {
    $back = url();
}
header('Location: ' . $back);
exit;
