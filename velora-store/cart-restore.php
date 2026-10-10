<?php
/** Link from the abandoned cart email: puts the items back in the cart (and applies the reminder coupon). */
require __DIR__ . '/includes/bootstrap.php';

$cart = q_one('SELECT * FROM carts WHERE token = ? AND recovered = 0', [(string) ($_GET['t'] ?? '')]);
if (!$cart) {
    flash('error', 'This cart link has expired. Your items may have been ordered already.');
    redirect('shop.php');
}
cart_clear();
cart_load_items((string) $cart['items']);
$_SESSION['cart_token'] = $cart['token'];
$_SESSION['cart_contact'] = ['email' => $cart['email'], 'name' => $cart['name'], 'phone' => $cart['phone']];
$coupon = (int) setting('abandoned_coupon_id') > 0 ? q_one('SELECT * FROM coupons WHERE id = ?', [(int) setting('abandoned_coupon_id')]) : null;
if ($coupon && coupon_error($coupon, PHP_INT_MAX) === '') {
    $_SESSION['coupon'] = $coupon['code'];
}
flash('success', 'Welcome back! Your cart is ready.');
redirect('cart.php');
