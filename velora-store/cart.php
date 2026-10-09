<?php
require __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $p = find_product((int) ($_POST['product_id'] ?? 0));
        if (!$p) {
            flash('error', 'This product is no longer available.');
            redirect('shop.php');
        }
        $sizes = str_list($p['sizes']);
        $colors = str_list($p['colors']);
        $size = (string) ($_POST['size'] ?? ($sizes[0] ?? ''));
        $color = (string) ($_POST['color'] ?? ($colors[0] ?? ''));
        if ($sizes && !in_array($size, $sizes, true)) {
            $size = $sizes[0];
        }
        if ($colors && !in_array($color, $colors, true)) {
            $color = $colors[0];
        }
        if (!$sizes) {
            $size = '';
        }
        if (!$colors) {
            $color = '';
        }
        $qty = max(1, (int) ($_POST['qty'] ?? 1));
        if ((int) $p['stock'] === 0) {
            flash('error', 'Sorry, this product is sold out.');
            redirect(product_url($p));
        }
        if ((int) $p['stock'] > 0) {
            $qty = min($qty, (int) $p['stock']);
        }
        cart_add((int) $p['id'], $qty, $size, $color);

        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'count' => cart_count()]);
            exit;
        }
        if (!empty($_POST['buy_now'])) {
            redirect('checkout.php');
        }
        flash('success', '“' . $p['name'] . '” was added to your cart.');
        redirect('cart.php');
    }

    if ($action === 'update') {
        foreach ((array) ($_POST['qty'] ?? []) as $key => $qty) {
            cart_set_qty((string) $key, (int) $qty);
        }
        flash('success', 'Cart updated.');
    }

    if ($action === 'remove') {
        cart_set_qty((string) ($_POST['key'] ?? ''), 0);
    }
    redirect('cart.php');
}

$items = cart_items();
$totals = cart_totals($items);
$pageTitle = 'Your Cart';
include __DIR__ . '/includes/header.php';
?>
<div class="container">
  <nav class="breadcrumb"><a href="<?= url() ?>">Home</a> <span>›</span> <span>Cart</span></nav>
  <h1 class="page-title">Your Cart</h1>

  <?php if (!$items): ?>
    <div class="empty">
      <?= icon('cart', 44) ?>
      <h3>Your cart is empty</h3>
      <p>Looks like you haven't added anything yet.</p>
      <a class="btn btn-primary" href="<?= url('shop.php') ?>">Start Shopping</a>
    </div>
  <?php else: ?>
    <div class="cart-layout">
      <form method="post" class="cart-items" id="cart-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <?php foreach ($items as $it): $p = $it['product']; ?>
          <div class="cart-item">
            <a href="<?= e(product_url($p)) ?>" class="ci-img"><img src="<?= e(img_url($p['image'])) ?>" alt=""></a>
            <div class="ci-info">
              <div class="ci-top">
                <a href="<?= e(product_url($p)) ?>"><strong><?= e($p['name']) ?></strong></a>
                <button class="icon-btn danger" type="submit" form="rm-<?= e($it['key']) ?>" aria-label="Remove"><?= icon('trash', 18) ?></button>
              </div>
              <?php if ($it['size'] !== ''): ?><small>Size: <b><?= e($it['size']) ?></b></small><?php endif; ?>
              <?php if ($it['color'] !== ''): ?><small>Color: <b><?= e($it['color']) ?></b></small><?php endif; ?>
              <div class="ci-bottom">
                <strong class="ci-price"><?= money($it['line_total']) ?></strong>
                <div class="qty small">
                  <button type="button" data-qty="-1" aria-label="Decrease"><?= icon('minus', 14) ?></button>
                  <input type="number" name="qty[<?= e($it['key']) ?>]" value="<?= (int) $it['qty'] ?>" min="0" max="99" data-autosubmit-delay>
                  <button type="button" data-qty="1" aria-label="Increase"><?= icon('plus', 14) ?></button>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
        <noscript><button class="btn btn-outline" type="submit">Update cart</button></noscript>
      </form>
      <?php foreach ($items as $it): ?>
        <form method="post" id="rm-<?= e($it['key']) ?>" hidden><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="key" value="<?= e($it['key']) ?>"></form>
      <?php endforeach; ?>

      <aside class="summary">
        <h3>Order Summary</h3>
        <div class="sum-row"><span>Subtotal</span><b><?= money($totals['subtotal']) ?></b></div>
        <div class="sum-row"><span>Delivery Fee</span><b><?= $totals['shipping'] > 0 ? money($totals['shipping']) : 'Free' ?></b></div>
        <?php if ((float) setting('free_shipping_over') > 0 && $totals['shipping'] > 0): ?>
          <p class="muted small-text">Add <?= money((float) setting('free_shipping_over') - $totals['subtotal']) ?> more for free delivery.</p>
        <?php endif; ?>
        <div class="sum-row total"><span>Total</span><b><?= money($totals['total']) ?></b></div>
        <a class="btn btn-primary btn-block" href="<?= url('checkout.php') ?>">Go to Checkout <?= icon('arrow-right', 16) ?></a>
        <a class="btn btn-ghost btn-block" href="<?= url('shop.php') ?>">Continue shopping</a>
      </aside>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
