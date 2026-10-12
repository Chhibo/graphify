<?php
require __DIR__ . '/includes/auth.php';
require_admin('products');

if (is_post()) {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'notify') {
        $n = process_stock_alerts(null, 200);
        activity('Sent back-in-stock emails', $n . ' email(s)');
        flash('success', $n ? $n . ' customer(s) were emailed that their product is back in stock.' : 'Nobody to email: the products they wait for are still sold out.');
    } elseif (($_POST['action'] ?? '') === 'delete_alert') {
        q('DELETE FROM stock_alerts WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
        flash('success', 'Request deleted.');
    }
    redirect('admin/stock.php');
}

$low = low_stock_items(200);
$waiting = q_all('SELECT a.*, p.name, v.size, v.color FROM stock_alerts a JOIN products p ON p.id = a.product_id
    LEFT JOIN product_variants v ON v.id = a.variant_id WHERE a.notified_at IS NULL ORDER BY a.id DESC LIMIT 300');
$sentCount = (int) q_val('SELECT COUNT(*) FROM stock_alerts WHERE notified_at IS NOT NULL');
$adminTitle = 'Stock & alerts';
include __DIR__ . '/includes/header.php';
?>
<div class="grid-main">
  <div class="card">
    <div class="card-head"><h2>Low stock (<?= low_stock_threshold() ?> or less)</h2><a class="btn btn-sm btn-light" href="settings.php?tab=sales">Settings</a></div>
    <?php if ($low): ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Product</th><th>Option</th><th>Stock</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($low as $r): ?>
          <tr>
            <td><img class="thumb" src="<?= e(img_url($r['image'])) ?>" alt=""> <?= e($r['name']) ?></td>
            <td><?= e(implode(' / ', array_filter([(string) $r['size'], (string) $r['color']])) ?: '-') ?></td>
            <td><?= (int) $r['stock'] === 0 ? '<span class="status st-cancelled">Sold out</span>' : '<b>' . (int) $r['stock'] . '</b> left' ?></td>
            <td><a class="btn btn-sm btn-light" href="product-edit.php?id=<?= (int) $r['id'] ?>">Add stock</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php else: ?><p class="muted">All good: no product is running low. 🎉</p><?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h2>Waiting for “back in stock”</h2></div>
    <p class="help">Customers who asked to be emailed. They are emailed automatically when you add stock to the product (and every few minutes while you use the admin).</p>
    <?php if ($waiting): ?>
      <form method="post"><?= csrf_field() ?><button class="btn btn-sm btn-primary" name="action" value="notify">Check & email now</button></form>
      <?php foreach ($waiting as $w): ?>
        <div class="kv">
          <span><b><?= e($w['name']) ?></b><?= $w['size'] || $w['color'] ? ' <small class="muted">(' . e(implode(' / ', array_filter([(string) $w['size'], (string) $w['color']]))) . ')</small>' : '' ?><br><small class="muted"><?= e($w['email']) ?> · <?= e(date('M j', strtotime($w['created_at']))) ?></small></span>
          <form method="post" data-confirm="Delete this request?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $w['id'] ?>"><button class="icon-x" name="action" value="delete_alert" aria-label="Delete">×</button></form>
        </div>
      <?php endforeach; ?>
    <?php else: ?><p class="muted">Nobody is waiting.</p><?php endif; ?>
    <p class="help"><?= $sentCount ?> back-in-stock email(s) sent so far.</p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
