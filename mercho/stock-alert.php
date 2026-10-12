<?php
/** Product page: "Notify me when it is back in stock". */
require __DIR__ . '/includes/bootstrap.php';

if (!is_post() || !stock_alerts_on()) {
    redirect('shop.php');
}
verify_csrf();
$p = find_product((int) ($_POST['product_id'] ?? 0));
if (!$p) {
    redirect('shop.php');
}
if ((time() - (int) ($_SESSION['last_stock_alert'] ?? 0)) < 10) {
    redirect(product_url($p));
}
$error = stock_alert_add($p, trim((string) ($_POST['email'] ?? '')), (string) ($_POST['size'] ?? ''), (string) ($_POST['color'] ?? ''));
$_SESSION['last_stock_alert'] = time();
flash($error === '' ? 'success' : 'error', $error === '' ? 'Done! We will email you as soon as it is back in stock.' : $error);
redirect(product_url($p));
