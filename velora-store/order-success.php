<?php
require __DIR__ . '/includes/bootstrap.php';

$number = (string) ($_GET['order'] ?? '');
$order = in_array($number, $_SESSION['my_orders'] ?? [], true)
    ? q_one('SELECT * FROM orders WHERE order_number = ?', [$number])
    : null;
if (!$order) {
    redirect('track.php');
}
$items = order_items((int) $order['id']);
$waLink = $_SESSION['wa_link'][$number] ?? '';

$pageTitle = 'Thank you for your order';
include __DIR__ . '/includes/header.php';
?>
<div class="container narrow">
  <div class="success-card">
    <div class="success-icon"><?= icon('check', 36) ?></div>
    <h1>Thank you, <?= e(explode(' ', $order['customer_name'])[0]) ?>!</h1>
    <p>Your order <b><?= e($order['order_number']) ?></b> has been received<?= $order['payment_status'] === 'paid' ? ' and paid' : '' ?>.</p>

    <?php if ($waLink !== ''): ?>
      <div class="wa-box">
        <p><b>Last step:</b> send us your order on WhatsApp so we can confirm it quickly.</p>
        <a class="btn btn-wa" href="<?= e($waLink) ?>" target="_blank" rel="noopener" id="wa-send"><?= icon('whatsapp', 20) ?> Send order on WhatsApp</a>
      </div>
      <script>setTimeout(function(){var a=document.getElementById('wa-send');if(a&&!sessionStorage.getItem('wa-<?= e($order['order_number']) ?>')){try{sessionStorage.setItem('wa-<?= e($order['order_number']) ?>','1')}catch(e){}window.location.href=a.href}},1500);</script>
    <?php elseif ($order['payment_method'] === 'cod'): ?>
      <p class="muted">We will contact you on <b><?= e($order['phone']) ?></b> to confirm your delivery.</p>
    <?php endif; ?>

    <div class="order-box">
      <?php foreach ($items as $it): ?>
        <div class="sum-item">
          <span class="si-img"><img src="<?= e(img_url($it['image'])) ?>" alt=""><b><?= (int) $it['qty'] ?></b></span>
          <span class="si-name"><?= e($it['name']) ?><small><?= e(implode(' / ', array_filter([$it['size'], $it['color']]))) ?></small></span>
          <b><?= money($it['price'] * $it['qty']) ?></b>
        </div>
      <?php endforeach; ?>
      <div class="sum-row"><span>Subtotal</span><b><?= money($order['subtotal']) ?></b></div>
      <div class="sum-row"><span>Delivery Fee</span><b><?= (float) $order['shipping'] > 0 ? money($order['shipping']) : 'Free' ?></b></div>
      <div class="sum-row total"><span>Total</span><b><?= money($order['total']) ?></b></div>
      <div class="sum-row"><span>Payment</span><b><?= e(payment_method_label($order['payment_method'])) ?></b></div>
      <div class="sum-row"><span>Deliver to</span><b><?= e($order['address'] . ', ' . $order['city']) ?></b></div>
    </div>
    <a class="btn btn-primary" href="<?= url('shop.php') ?>">Continue Shopping</a>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
