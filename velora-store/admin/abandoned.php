<?php
require __DIR__ . '/includes/auth.php';
require_admin('orders');

if (is_post()) {
    verify_csrf();
    $cart = q_one('SELECT * FROM carts WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
    $action = $_POST['action'] ?? '';
    if ($action === 'run') {
        $n = send_abandoned_reminders(30);
        flash('success', $n . ' reminder(s) sent.');
    } elseif ($cart && $action === 'remind') {
        $ok = send_cart_reminder($cart);
        flash($ok ? 'success' : 'error', $ok ? 'Reminder sent to ' . $cart['email'] . '.' : 'Could not send the email: ' . (mail_last_error() ?: 'cart is empty.'));
    } elseif ($cart && $action === 'delete') {
        q('DELETE FROM carts WHERE id = ?', [$cart['id']]);
        flash('success', 'Cart deleted.');
    }
    redirect('admin/abandoned.php');
}

$rows = q_all("SELECT * FROM carts WHERE recovered = 0 AND updated_at >= ? ORDER BY updated_at DESC LIMIT 200", [date('Y-m-d H:i:s', time() - 30 * 86400)]);
$recovered = (int) q_val('SELECT COUNT(*) FROM carts WHERE recovered = 1 AND reminded_at IS NOT NULL');
$adminTitle = 'Abandoned carts';
include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <div class="toolbar">
    <p class="muted grow-form">Carts left without ordering in the last 30 days. <?= setting_on('abandoned_enabled')
        ? 'A reminder email is sent automatically ' . (int) setting('abandoned_delay_hours', '3') . ' hour(s) after the cart was left.'
        : '<b>Automatic reminders are off</b> (Settings → General).' ?>
      <?= $recovered ? '<br>Orders placed after a reminder: <b>' . $recovered . '</b>' : '' ?></p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="run"><button class="btn btn-light" type="submit">Send due reminders now</button></form>
  </div>
  <?php if ($rows): ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Customer</th><th>Items</th><th>Value</th><th>Last activity</th><th>Reminder</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $c): $lines = (array) json_decode((string) $c['items'], true); ?>
      <tr>
        <td><b><?= e($c['name'] !== '' ? $c['name'] : '-') ?></b><br><small class="muted"><?= e($c['email']) ?><?= $c['phone'] !== '' ? ' · ' . e($c['phone']) : '' ?></small><?= $c['customer_id'] ? ' <span class="tag">account</span>' : '' ?></td>
        <td><?= array_sum(array_map(fn($l) => (int) ($l['qty'] ?? 1), $lines)) ?> item(s)</td>
        <td><?= money($c['total']) ?></td>
        <td><small><?= e(date('M j, H:i', strtotime($c['updated_at']))) ?></small></td>
        <td><?= $c['reminded_at'] ? '<span class="status st-delivered">Sent ' . e(date('M j', strtotime($c['reminded_at']))) . '</span>' : '<span class="status st-pending">Not yet</span>' ?></td>
        <td class="actions">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn btn-sm btn-light" name="action" value="remind">Email</button></form>
          <?php if ($c['phone'] !== ''): ?>
            <a class="btn btn-sm btn-wa" target="_blank" rel="noopener" href="https://wa.me/<?= e(preg_replace('/\D+/', '', $c['phone'])) ?>?text=<?= rawurlencode('Hi' . ($c['name'] !== '' ? ' ' . explode(' ', $c['name'])[0] : '') . ', you left some items in your cart at ' . setting('store_name') . '. You can complete your order here: ' . abandoned_restore_url($c)) ?>">WhatsApp</a>
          <?php endif; ?>
          <form method="post" data-confirm="Delete this cart?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn btn-sm btn-danger-light" name="action" value="delete">×</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?><p class="muted">No abandoned carts. 🎉</p><?php endif; ?>
</div>
<div class="card">
  <div class="card-head"><h2>Send reminders on time (optional cron job)</h2></div>
  <p class="muted">Reminders are sent when you open the admin panel. For exact timing, add a cron job in your hosting panel (cPanel → Cron Jobs), every 15 minutes:</p>
  <code style="display:block;padding:10px;word-break:break-all">curl -s "<?= e(full_url('cron.php?key=' . setting('cron_key'))) ?>"</code>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
