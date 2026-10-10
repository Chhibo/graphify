<?php
require __DIR__ . '/includes/bootstrap.php';

$back = '';
if (is_post()) {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Please enter a valid email address.');
    } else {
        if ((int) q_val('SELECT COUNT(*) FROM subscribers WHERE email = ?', [$email]) === 0) {
            db_insert('subscribers', ['email' => $email, 'created_at' => now()]);
        }
        $coupon = !empty($_POST['from_popup']) && (int) setting('popup_coupon_id') > 0
            ? q_one('SELECT * FROM coupons WHERE id = ?', [(int) setting('popup_coupon_id')]) : null;
        if ($coupon && coupon_error($coupon, PHP_INT_MAX) === '') {
            // Send the popup coupon by email (needs a server that can send email) and apply it to the cart now.
            $sent = send_template($email, 'Your ' . setting('store_name') . ' coupon: ' . $coupon['code'], 'Thank you for subscribing!',
                '<p>Here is your coupon code (' . e(coupon_label($coupon)) . '):</p><p style="font-size:24px;font-weight:bold;letter-spacing:2px">' . e($coupon['code']) . '</p><p>Enter it in your cart or at checkout.</p>',
                'Shop now', full_url('shop.php'));
            $_SESSION['coupon'] = $coupon['code'];
            flash('success', 'Thank you for subscribing! Your code ' . $coupon['code'] . ' is applied to your cart' . ($sent ? ' and was sent to your email.' : '.'));
        } else {
            flash('success', 'Thank you for subscribing! You will be the first to hear about our offers.');
        }
    }
    // Go back to the page the visitor was on (only pages of this store).
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref !== '' && parse_url($ref, PHP_URL_HOST) === parse_url(full_url(), PHP_URL_HOST)) {
        $back = preg_replace('/([?&])popup=1(&|$)/', '$1', $ref);
    }
}
redirect($back !== '' ? $back : '');
