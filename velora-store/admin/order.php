<?php
require __DIR__ . '/includes/auth.php';
require APP_ROOT . '/includes/whatsapp.php';
require_admin();

$order = q_one('SELECT * FROM orders WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
if (!$order) {
    flash('error', 'Order not found.');
    redirect('admin/orders.php');
}

if (is_post()) {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'delete') {
        q('DELETE FROM order_items WHERE order_id = ?', [$order['id']]);
        q('DELETE FROM orders WHERE id = ?', [$order['id']]);
        flash('success', 'Order deleted.');
        redirect('admin/orders.php');
    }
    $status = (string) ($_POST['status'] ?? $order['status']);
    $payment = (string) ($_POST['payment_status'] ?? $order['payment_status']);
    if (!isset(order_status_labels()[$status])) {
        $status = $order['status'];
    }
    if (!in_array($payment, ['paid', 'unpaid', 'cod', 'failed'], true)) {
        $payment = $order['payment_status'];
    }
    q('UPDATE orders SET status = ?, payment_status = ?, notes = ?, updated_at = ? WHERE id = ?',
        [$status, $payment, (string) ($_POST['notes'] ?? $order['notes']), now(), $order['id']]);
    if ($payment === 'paid' || $payment === 'cod') {
        order_reduce_stock((int) $order['id']);
    }
    flash('success', 'Order updated.');
    redirect('admin/order.php?id=' . (int) $order['id']);
}

$items = order_items((int) $order['id']);
$customerWa = 'https://wa.me/' . preg_replace('/\D+/', '', $order['phone']) . '?text=' . rawurlencode('Hello ' . $order['customer_name'] . ', about your order ' . $order['order_number'] . ' at ' . setting('store_name') . ': ');
$adminTitle = 'Order ' . $order['order_number'];
include __DIR__ . '/includes/header.php';
?>
<p><a href="orders.php">← All orders</a></p>
<div class="grid-main">
  <div>
    <div class="card">
      <div class="card-head"><h2>Items</h2><span><?= payment_badge($order['payment_status']) ?> <?= admin_status_badge($order['status']) ?></span></div>
      <div class="table-wrap"><table>
        <thead><tr><th></th><th>Product</th><th>Price</th><th>Qty</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><img class="thumb" src="<?= e(img_url($it['image'])) ?>" alt=""></td>
            <td><b><?= e($it['name']) ?></b><br><small class="muted"><?= e(implode(' / ', array_filter([$it['size'], $it['color']]))) ?></small></td>
            <td><?= money($it['price']) ?></td>
            <td><?= (int) $it['qty'] ?></td>
            <td><?= money($it['price'] * $it['qty']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <div class="totals">
        <div><span>Subtotal</span><b><?= money($order['subtotal']) ?></b></div>
        <div><span>Shipping</span><b><?= money($order['shipping']) ?></b></div>
        <div class="grand"><span>Total</span><b><?= money($order['total']) ?></b></div>
      </div>
    </div>

    <form method="post" class="card">
      <?= csrf_field() ?>
      <div class="card-head"><h2>Update order</h2></div>
      <div class="grid-2">
        <label>Order status
          <select name="status"><?php foreach (order_status_labels() as $k => $l): ?><option value="<?= e($k) ?>" <?= $order['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
        </label>
        <label>Payment status
          <select name="payment_status">
            <?php foreach (['cod' => 'Cash on delivery (to collect)', 'paid' => 'Paid', 'unpaid' => 'Awaiting payment', 'failed' => 'Payment failed'] as $k => $l): ?>
              <option value="<?= e($k) ?>" <?= $order['payment_status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <label>Notes<textarea name="notes" rows="3"><?= e($order['notes']) ?></textarea></label>
      <button class="btn btn-primary" type="submit">Save changes</button>
    </form>
  </div>

  <div>
    <div class="card">
      <div class="card-head"><h2>Customer</h2></div>
      <p><b><?= e($order['customer_name']) ?></b><br>
        <?= e($order['phone']) ?><br>
        <?php if ($order['email'] !== ''): ?><a href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a><br><?php endif; ?>
        <?= e($order['address']) ?><br><?= e($order['city']) ?><?= $order['country'] !== '' ? ', ' . e($order['country']) : '' ?></p>
      <a class="btn btn-wa btn-block" href="<?= e($customerWa) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 16) ?> Message customer</a>
    </div>
    <div class="card">
      <div class="card-head"><h2>Details</h2></div>
      <div class="kv"><span>Placed</span><b><?= e(date('M j, Y H:i', strtotime($order['created_at']))) ?></b></div>
      <div class="kv"><span>Method</span><b><?= e(payment_method_label($order['payment_method'])) ?></b></div>
      <?php if ($order['payment_ref'] !== ''): ?><div class="kv"><span>Payment ID</span><b class="break"><?= e($order['payment_ref']) ?></b></div><?php endif; ?>
      <?php if (setting('whatsapp_mode') !== 'link' && $order['payment_method'] === 'cod'): ?>
        <div class="kv"><span>WhatsApp alert</span><b><?= (int) $order['whatsapp_sent'] ? 'Sent ✓' : 'Not sent' ?></b></div>
      <?php endif; ?>
      <a class="btn btn-sm btn-light btn-block" href="<?= e(whatsapp_link(order_text($order))) ?>" target="_blank" rel="noopener">Copy order to WhatsApp</a>
    </div>
    <form method="post" class="card" data-confirm="Delete this order permanently?">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete">
      <button class="btn btn-danger btn-block" type="submit">Delete order</button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
