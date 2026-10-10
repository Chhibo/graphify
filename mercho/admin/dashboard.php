<?php
require __DIR__ . '/includes/auth.php';
require_admin();

$revenue = (float) q_val("SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status = 'paid' OR (payment_method = 'cod' AND status = 'delivered')");
$pendingCod = (float) q_val("SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_method = 'cod' AND status NOT IN ('delivered','cancelled')");
$ordersTotal = (int) q_val("SELECT COUNT(*) FROM orders WHERE payment_status IN ('paid','cod')");
$ordersToday = (int) q_val("SELECT COUNT(*) FROM orders WHERE payment_status IN ('paid','cod') AND created_at >= ?", [date('Y-m-d 00:00:00')]);
$products = (int) q_val('SELECT COUNT(*) FROM products');
$recent = q_all("SELECT * FROM orders WHERE payment_status IN ('paid','cod') ORDER BY id DESC LIMIT 8");
$lowStock = q_all('SELECT id, name, stock FROM products WHERE stock >= 0 AND stock <= 5 ORDER BY stock LIMIT 6');
$methods = enabled_payment_methods();

$adminTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>
<?php if (!$methods): ?><div class="alert alert-error">No payment method is enabled - customers cannot check out. <a href="settings.php?tab=payments">Enable one</a>.</div><?php endif; ?>
<?php if (setting('whatsapp_number') === '' && isset($methods['cod'])): ?><div class="alert alert-warn">Add your WhatsApp number to receive Cash on Delivery orders. <a href="settings.php?tab=whatsapp">Add it now</a>.</div><?php endif; ?>

<?php if (admin_can('reports')): ?>
<div class="stats">
  <div class="stat"><span>Revenue (collected)</span><strong><?= money($revenue) ?></strong></div>
  <div class="stat"><span>COD to collect</span><strong><?= money($pendingCod) ?></strong></div>
  <div class="stat"><span>Orders today</span><strong><?= $ordersToday ?></strong></div>
  <div class="stat"><span>Total orders</span><strong><?= $ordersTotal ?></strong></div>
  <div class="stat"><span>Products</span><strong><?= $products ?></strong></div>
</div><?php endif; ?>


<div class="grid-main">
  <?php if (admin_can('orders')): ?>
  <div class="card">
    <div class="card-head"><h2>Recent orders</h2><a href="orders.php">View all →</a></div>
    <?php if ($recent): ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $o): ?>
        <tr onclick="location='order.php?id=<?= (int) $o['id'] ?>'" class="clickable">
          <td><b><?= e($o['order_number']) ?></b><br><small class="muted"><?= e(date('M j, H:i', strtotime($o['created_at']))) ?></small></td>
          <td><?= e($o['customer_name']) ?><br><small class="muted"><?= e($o['phone']) ?></small></td>
          <td><?= money($o['total']) ?></td>
          <td><?= payment_badge($o['payment_status']) ?></td>
          <td><?= admin_status_badge($o['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php else: ?><p class="muted">No orders yet. Share your store link to get your first order!</p><?php endif; ?>
  </div>
  <?php else: ?><div class="card"><p>Welcome, <?= e(current_admin()['name']) ?>! Use the menu on the left to get started.</p></div><?php endif; ?>
  <?php if (admin_can('settings')): ?>
  <div class="card">
    <div class="card-head"><h2>Quick setup</h2></div>
    <ul class="checklist">
      <li class="<?= setting('logo') !== '' || setting('store_name') !== 'MERCHO' ? 'ok' : '' ?>"><a href="settings.php">Store name & logo</a></li>
      <li class="<?= isset($methods['cod']) || isset($methods['paypal']) || isset($methods['stripe']) ? 'ok' : '' ?>"><a href="settings.php?tab=payments">Payment methods</a></li>
      <li class="<?= setting('whatsapp_number') !== '' ? 'ok' : '' ?>"><a href="settings.php?tab=whatsapp">WhatsApp number</a></li>
      <li class="<?= $products > 0 ? 'ok' : '' ?>"><a href="product-edit.php">Add products</a></li>
      <li class="<?= setting('hero_image') !== 'assets/img/demo/hero.svg' ? 'ok' : '' ?>"><a href="settings.php?tab=home">Home page images</a></li>
    </ul>
    <?php if ($lowStock): ?>
      <div class="card-head" style="margin-top:18px"><h2>Low stock</h2></div>
      <?php foreach ($lowStock as $p): ?>
        <div class="row-between"><a href="product-edit.php?id=<?= (int) $p['id'] ?>"><?= e($p['name']) ?></a><span class="status <?= (int) $p['stock'] === 0 ? 'st-cancelled' : 'st-pending' ?>"><?= (int) $p['stock'] ?> left</span></div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
