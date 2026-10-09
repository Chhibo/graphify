<?php
/**
 * Return / cancel endpoint for PayPal and Stripe.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/payments.php';
require __DIR__ . '/includes/whatsapp.php';

$gateway = $_GET['gateway'] ?? '';
$action = $_GET['action'] ?? '';
$order = q_one('SELECT * FROM orders WHERE order_number = ?', [(string) ($_GET['order'] ?? '')]);

if (!$order || $order['payment_method'] !== $gateway) {
    flash('error', 'Order not found.');
    redirect('cart.php');
}
$number = $order['order_number'];

if ($order['payment_status'] === 'paid') {
    redirect('order-success.php?order=' . rawurlencode($number));
}

if ($action === 'cancel') {
    q("UPDATE orders SET status = 'cancelled', updated_at = ? WHERE id = ? AND payment_status <> 'paid'", [now(), $order['id']]);
    flash('error', 'Payment was cancelled. Your cart is still saved - you can try again or choose another payment method.');
    redirect('checkout.php');
}

$paid = false;
try {
    if ($gateway === 'paypal') {
        $paid = paypal_capture($order, (string) ($_GET['token'] ?? ''));
    } elseif ($gateway === 'stripe') {
        $paid = stripe_verify($order, (string) ($_GET['session_id'] ?? ''));
    }
} catch (Throwable $ex) {
    $paid = false;
}

if (!$paid) {
    q("UPDATE orders SET payment_status = 'failed', updated_at = ? WHERE id = ? AND payment_status <> 'paid'", [now(), $order['id']]);
    flash('error', 'We could not confirm your payment. If money was taken from your account, please contact us with order number ' . $number . '.');
    redirect('checkout.php');
}

q("UPDATE orders SET payment_status = 'paid', status = 'processing', updated_at = ? WHERE id = ?", [now(), $order['id']]);
order_reduce_stock((int) $order['id']);
printful_auto_send((int) $order['id']);
cart_clear();
$_SESSION['my_orders'][] = $number;

if (setting_on('whatsapp_notify_online')) {
    $order = q_one('SELECT * FROM orders WHERE id = ?', [$order['id']]);
    $_SESSION['wa_link'][$number] = whatsapp_notify_order($order);
}
redirect('order-success.php?order=' . rawurlencode($number));
