<?php
require __DIR__ . '/includes/bootstrap.php';

$order = null;
$error = '';
if (is_post()) {
    verify_csrf();
    $number = strtoupper(trim((string) ($_POST['order_number'] ?? '')));
    $phoneDigits = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? ''));
    $row = q_one('SELECT * FROM orders WHERE order_number = ?', [$number]);
    // Compare the last 6 digits so "+212 6.." and "06.." both match.
    if ($row && strlen($phoneDigits) >= 6 && substr(preg_replace('/\D+/', '', $row['phone']), -6) === substr($phoneDigits, -6)) {
        $order = $row;
    } else {
        $error = 'We could not find an order with these details.';
    }
}
$steps = ['pending' => 'Order placed', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];
$pageTitle = 'Track your order';
include __DIR__ . '/includes/header.php';
?>
<div class="container narrow">
  <h1 class="page-title">Track your order</h1>
  <form method="post" class="box track-form">
    <?= csrf_field() ?>
    <div class="grid-2">
      <label>Order number<input name="order_number" required placeholder="e.g. VL-261009-AB12C" value="<?= e($_POST['order_number'] ?? '') ?>"></label>
      <label>Phone number<input name="phone" type="tel" required value="<?= e($_POST['phone'] ?? '') ?>"></label>
    </div>
    <button class="btn btn-primary" type="submit">Track Order</button>
  </form>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <?php if ($order): ?>
    <div class="box">
      <h3>Order <?= e($order['order_number']) ?></h3>
      <?php if ($order['status'] === 'cancelled'): ?>
        <div class="alert alert-error">This order was cancelled.</div>
      <?php else: $reached = true; ?>
        <ol class="timeline">
          <?php foreach ($steps as $key => $label): ?>
            <li class="<?= $reached ? 'done' : '' ?>"><?= e($label) ?></li>
            <?php if ($key === $order['status']) { $reached = false; } ?>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
      <?php if ($order['tracking_url'] !== ''): ?>
        <a class="btn btn-primary btn-block" href="<?= e($order['tracking_url']) ?>" target="_blank" rel="noopener"><?= icon('truck', 18) ?> Track your package</a>
      <?php endif; ?>
      <div class="sum-row"><span>Placed on</span><b><?= e(date('M j, Y', strtotime($order['created_at']))) ?></b></div>
      <div class="sum-row"><span>Total</span><b><?= money($order['total']) ?></b></div>
      <div class="sum-row"><span>Payment</span><b><?= e(payment_method_label($order['payment_method'])) ?> · <?= e(ucfirst($order['payment_status'] === 'cod' ? 'pay on delivery' : $order['payment_status'])) ?></b></div>
      <?php foreach (order_items((int) $order['id']) as $it): ?>
        <div class="sum-row"><span><?= (int) $it['qty'] ?> × <?= e($it['name']) ?></span><b><?= money($it['price'] * $it['qty']) ?></b></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
