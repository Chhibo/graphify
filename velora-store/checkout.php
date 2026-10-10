<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/payments.php';
require __DIR__ . '/includes/whatsapp.php';

$items = cart_items();
$totals = cart_totals($items);
$methods = enabled_payment_methods();
// Printful needs a complete postal address (ZIP code) to ship.
$hasPrintful = (bool) array_filter($items, fn($it) => !empty($it['variant']['printful_variant_id']));
$errors = [];
$form = [
    'customer_name' => '', 'phone' => '', 'email' => '', 'address' => '', 'city' => '', 'state' => '', 'zip' => '',
    'country' => country_code(setting('country_default')), 'notes' => '', 'payment_method' => array_key_first($methods) ?? '',
];

if (is_post() && $items) {
    verify_csrf();
    // Recalculate with the delivery option the customer picked.
    if (isset($_POST['shipping_method'])) {
        $_SESSION['shipping_method'] = (int) $_POST['shipping_method'];
        $totals = cart_totals($items);
    }
    if ($totals['coupon_error'] !== '' && !empty($_SESSION['coupon'])) {
        $errors[] = 'Coupon: ' . $totals['coupon_error'];
        unset($_SESSION['coupon']);
    }
    foreach ($form as $k => $v) {
        $form[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    if (mb_strlen($form['customer_name']) < 2) {
        $errors[] = 'Please enter your full name.';
    }
    if (!preg_match('/^\+?[0-9 ()\-]{6,20}$/', $form['phone'])) {
        $errors[] = 'Please enter a valid phone number.';
    }
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($form['address'] === '' || $form['city'] === '') {
        $errors[] = 'Please enter your delivery address and city.';
    }
    $form['country'] = country_code($form['country']);
    if ($form['country'] === '') {
        $errors[] = 'Please choose your country.';
    }
    if ($hasPrintful && $form['zip'] === '' && !in_array($form['country'], ['AE', 'HK', 'QA', 'IE'], true)) {
        $errors[] = 'Please enter your postal / ZIP code.';
    }
    if (!isset($methods[$form['payment_method']])) {
        $errors[] = 'Please choose a payment method.';
    }
    foreach ($items as $it) {
        $stock = (int) $it['product']['stock'];
        if ($stock >= 0 && $it['qty'] > $stock) {
            $errors[] = '“' . $it['product']['name'] . '” has only ' . $stock . ' left in stock.';
        }
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $orderNumber = generate_order_number();
            $orderId = db_insert('orders', [
                'order_number' => $orderNumber,
                'customer_name' => mb_substr($form['customer_name'], 0, 150),
                'email' => mb_substr($form['email'], 0, 190),
                'phone' => mb_substr($form['phone'], 0, 40),
                'address' => mb_substr($form['address'], 0, 255),
                'city' => mb_substr($form['city'], 0, 120),
                'state' => mb_substr($form['state'], 0, 120),
                'zip' => mb_substr($form['zip'], 0, 30),
                'country' => countries()[$form['country']],
                'country_code' => $form['country'],
                'notes' => mb_substr($form['notes'], 0, 2000),
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'coupon_code' => $totals['coupon']['code'] ?? '',
                'shipping' => $totals['shipping'],
                'shipping_method' => mb_substr((string) ($totals['shipping_method']['name'] ?? ''), 0, 120),
                'total' => $totals['total'],
                'payment_method' => $form['payment_method'],
                'payment_status' => $form['payment_method'] === 'cod' ? 'cod' : 'unpaid',
                'payment_ref' => '',
                'status' => 'pending',
                'whatsapp_sent' => 0,
                'stock_reduced' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($items as $it) {
                db_insert('order_items', [
                    'order_id' => $orderId,
                    'product_id' => (int) $it['product']['id'],
                    'name' => $it['product']['name'],
                    'image' => ($it['variant']['image'] ?? '') !== '' ? $it['variant']['image'] : $it['product']['image'],
                    'size' => $it['size'],
                    'color' => $it['color'],
                    'price' => $it['price'],
                    'qty' => $it['qty'],
                    'variant_id' => $it['variant'] ? (int) $it['variant']['id'] : null,
                    'printful_variant_id' => (string) ($it['variant']['printful_variant_id'] ?? ''),
                    'options' => mb_substr(options_text($it['options']), 0, 500),
                ]);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }

        $order = q_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
        $_SESSION['my_orders'][] = $orderNumber;

        if ($order['payment_method'] === 'cod') {
            order_reduce_stock($orderId);
            printful_auto_send($orderId);
            $_SESSION['wa_link'][$orderNumber] = whatsapp_notify_order($order);
            cart_clear();
            redirect('order-success.php?order=' . rawurlencode($orderNumber));
        }

        try {
            $payUrl = $order['payment_method'] === 'paypal' ? paypal_create($order) : stripe_create($order);
            header('Location: ' . $payUrl);
            exit;
        } catch (Throwable $ex) {
            q("UPDATE orders SET payment_status = 'failed', status = 'cancelled', updated_at = ? WHERE id = ?", [now(), $orderId]);
            $errors[] = $ex->getMessage();
        }
    }
}

$pageTitle = 'Checkout';
include __DIR__ . '/includes/header.php';
?>
<div class="container">
  <nav class="breadcrumb"><a href="<?= url() ?>">Home</a> <span>›</span> <a href="<?= url('cart.php') ?>">Cart</a> <span>›</span> <span>Checkout</span></nav>
  <h1 class="page-title">Checkout</h1>

  <?php if (!$items): ?>
    <div class="empty"><?= icon('cart', 44) ?><h3>Your cart is empty</h3><a class="btn btn-primary" href="<?= url('shop.php') ?>">Start Shopping</a></div>
  <?php elseif (!$methods): ?>
    <div class="alert alert-error">Checkout is not available right now: no payment method is enabled. (Store owner: enable one in Admin → Settings → Payments.)</div>
  <?php else: ?>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <?php $couponFormPrinted = true; $couponBack = 'checkout'; ?>
    <form method="post" action="<?= url('cart.php') ?>" id="coupon-form"><?= csrf_field() ?><input type="hidden" name="back" value="checkout"></form>
    <form method="post" class="checkout-layout" id="checkout-form">
      <?= csrf_field() ?>
      <div class="checkout-main">
        <div class="box">
          <h3>Delivery details</h3>
          <div class="grid-2">
            <label>Full name *<input name="customer_name" value="<?= e($form['customer_name']) ?>" required autocomplete="name"></label>
            <label>Phone (WhatsApp) *<input name="phone" type="tel" value="<?= e($form['phone']) ?>" required autocomplete="tel"></label>
          </div>
          <label>Email <small class="muted">(optional)</small><input name="email" type="email" value="<?= e($form['email']) ?>" autocomplete="email"></label>
          <label>Address *<input name="address" value="<?= e($form['address']) ?>" required autocomplete="street-address" placeholder="Street, building, apartment"></label>
          <div class="grid-2">
            <label>City *<input name="city" value="<?= e($form['city']) ?>" required autocomplete="address-level2"></label>
            <label>State / Region<input name="state" value="<?= e($form['state']) ?>" autocomplete="address-level1" placeholder="e.g. CA, NY"></label>
          </div>
          <div class="grid-2">
            <label>Postal / ZIP code<?= $hasPrintful ? ' *' : '' ?><input name="zip" value="<?= e($form['zip']) ?>" autocomplete="postal-code"<?= $hasPrintful ? ' required' : '' ?>></label>
            <label>Country *
              <select name="country" required autocomplete="country">
                <option value="">Choose your country</option>
                <?php foreach (countries() as $code => $name): ?><option value="<?= e($code) ?>" <?= $form['country'] === $code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
              </select>
            </label>
          </div>
          <label>Order notes <small class="muted">(optional)</small><textarea name="notes" rows="3" placeholder="Anything we should know about your delivery?"><?= e($form['notes']) ?></textarea></label>
        </div>

        <?php if ($totals['shipping_methods']): ?>
        <div class="box">
          <h3>Delivery method</h3>
          <div class="pay-methods">
            <?php foreach ($totals['shipping_methods'] as $m): ?>
              <label class="pay-method">
                <input type="radio" name="shipping_method" value="<?= (int) $m['id'] ?>" data-cost="<?= e((string) (float) $m['cost']) ?>" <?= (int) $m['id'] === (int) $totals['shipping_method']['id'] ? 'checked' : '' ?>>
                <span class="pm-body">
                  <span class="pm-title"><?= e($m['name']) ?></span>
                  <?php if ($m['description'] !== ''): ?><span class="pm-text"><?= e($m['description']) ?></span><?php endif; ?>
                </span>
                <span class="pm-cost"><?= (float) $m['cost'] > 0 ? money($m['cost']) : 'Free' ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <div class="box">
          <h3>Payment method</h3>
          <div class="pay-methods">
            <?php foreach ($methods as $key => $title): ?>
              <label class="pay-method">
                <input type="radio" name="payment_method" value="<?= e($key) ?>" <?= $form['payment_method'] === $key ? 'checked' : '' ?>>
                <span class="pm-body">
                  <span class="pm-title"><?= e($title) ?></span>
                  <span class="pm-text">
                    <?php if ($key === 'cod'): ?><?= e(setting('pay_cod_text', 'Pay with cash when your order is delivered.')) ?>
                    <?php elseif ($key === 'paypal'): ?>You will be redirected to PayPal to complete your payment securely.
                    <?php else: ?>Pay securely with Visa, Mastercard, Apple Pay or Google Pay (powered by Stripe).<?php endif; ?>
                  </span>
                </span>
                <span class="pm-logo pm-<?= e($key) ?>"><?= $key === 'cod' ? icon('package', 22) : ($key === 'paypal' ? '<b>Pay</b><i>Pal</i>' : '<b>VISA</b>') ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <aside class="summary">
        <h3>Order Summary</h3>
        <?php foreach ($items as $it): ?>
          <div class="sum-item">
            <span class="si-img"><img src="<?= e(img_url($it['product']['image'])) ?>" alt=""><b><?= (int) $it['qty'] ?></b></span>
            <span class="si-name"><?= e($it['product']['name']) ?><small><?= e(implode(' / ', array_filter(array_merge([$it['size'], $it['color']], array_values($it['options']))))) ?></small></span>
            <b><?= money($it['line_total']) ?></b>
          </div>
        <?php endforeach; ?>
        <div class="sum-row"><span>Subtotal</span><b><?= money($totals['subtotal']) ?></b></div>
        <?php if ($totals['discount'] > 0): ?><div class="sum-row discount"><span>Discount (<?= e($totals['coupon']['code']) ?>)</span><b>-<?= money($totals['discount']) ?></b></div><?php endif; ?>
        <?php
          // Delivery is free when the order reaches the free delivery amount or a free-shipping coupon is used.
          $shipFree = ((float) setting('free_shipping_over') > 0 && $totals['subtotal'] >= (float) setting('free_shipping_over'))
              || ($totals['coupon']['type'] ?? '') === 'free_shipping';
        ?>
        <div class="sum-row" data-summary data-base="<?= e((string) round($totals['subtotal'] - $totals['discount'], 2)) ?>" data-free="<?= $shipFree ? 1 : 0 ?>">
          <span>Delivery</span><b data-ship><?= $totals['shipping'] > 0 ? money($totals['shipping']) : 'Free' ?></b></div>
        <?php include __DIR__ . '/includes/coupon-box.php'; ?>
        <div class="sum-row total"><span>Total</span><b data-total><?= money($totals['total']) ?></b></div>
        <button class="btn btn-primary btn-block" type="submit" data-loading-text="Please wait...">Place Order</button>
        <p class="muted small-text center"><?= icon('shield', 14) ?> Your details are safe and only used for your order.</p>
      </aside>
    </form>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
