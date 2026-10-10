<?php
require __DIR__ . '/includes/auth.php';
require_admin('orders');

if (is_post()) {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'delete') {
        q('DELETE FROM blocklist WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
        flash('success', 'Removed from the block list.');
    } else {
        $type = (string) ($_POST['type'] ?? 'phone');
        $value = trim((string) ($_POST['value'] ?? ''));
        if ($value === '') {
            flash('error', 'Please enter a phone number, email or IP address.');
        } else {
            blocklist_add($type, $value, trim((string) ($_POST['note'] ?? '')));
            flash('success', 'Added to the block list.');
        }
    }
    redirect('admin/blocklist.php');
}
$rows = q_all('SELECT * FROM blocklist ORDER BY id DESC');
$adminTitle = 'Block list';
include __DIR__ . '/includes/header.php';
?>
<div class="grid-main">
  <div class="card">
    <p class="muted">Customers on this list cannot place orders (any payment method). Phone numbers are matched on their last 9 digits, so “+212 612 345 678” and “0612345678” are the same.
      More protection: <a href="settings.php?tab=payments">Settings → Payments → Cash on Delivery</a>.</p>
    <?php if ($rows): ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Type</th><th>Value</th><th>Note</th><th>Added</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><span class="tag"><?= e(ucfirst($r['type'])) ?></span></td>
          <td><b><?= e($r['type'] === 'phone' ? '…' . $r['value'] : $r['value']) ?></b></td>
          <td><?= e($r['note']) ?></td>
          <td><small><?= e(date('M j, Y', strtotime($r['created_at']))) ?></small></td>
          <td class="actions"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-light">Unblock</button></form></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php else: ?><p class="muted">Nobody is blocked. Use the “Block this customer” button on an order page, or add someone here.</p><?php endif; ?>
  </div>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <div class="card-head"><h2>Block someone</h2></div>
    <label>Type<select name="type"><option value="phone">Phone number</option><option value="email">Email</option><option value="ip">IP address</option></select></label>
    <label>Value<input name="value" required></label>
    <label>Note <small class="muted">(optional)</small><input name="note" placeholder="e.g. refused 2 deliveries"></label>
    <button class="btn btn-primary btn-block" type="submit">Add to block list</button>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
