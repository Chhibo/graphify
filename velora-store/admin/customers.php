<?php
require __DIR__ . '/includes/auth.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle') {
        q('UPDATE customers SET active = 1 - active WHERE id = ?', [$id]);
        flash('success', 'Customer updated.');
    } elseif ($action === 'password') {
        $pass = (string) ($_POST['password'] ?? '');
        if (strlen($pass) < 8) {
            flash('error', 'The new password must be at least 8 characters.');
        } else {
            q("UPDATE customers SET password = ?, reset_token = '' WHERE id = ?", [password_hash($pass, PASSWORD_DEFAULT), $id]);
            flash('success', 'Password changed. Tell the customer their new password.');
        }
    } elseif ($action === 'delete') {
        q('UPDATE orders SET customer_id = NULL WHERE customer_id = ?', [$id]); // orders are kept
        q('DELETE FROM customers WHERE id = ?', [$id]);
        flash('success', 'Customer account deleted. Their orders were kept.');
        redirect('admin/customers.php');
    }
    redirect('admin/customers.php?id=' . $id);
}

$view = isset($_GET['id']) ? q_one('SELECT * FROM customers WHERE id = ?', [(int) $_GET['id']]) : null;
$search = trim((string) ($_GET['q'] ?? ''));
$params = [];
$where = '';
if ($search !== '') {
    $where = ' WHERE c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?';
    $params = ["%$search%", "%$search%", "%$search%"];
}
$perPage = 30;
$page = max(1, (int) ($_GET['page'] ?? 1));
$total = (int) q_val('SELECT COUNT(*) FROM customers c' . $where, $params);
$rows = q_all("SELECT c.*,
        (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id AND o.payment_status IN ('paid','cod')) AS order_count,
        (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.customer_id = c.id AND o.payment_status IN ('paid','cod') AND o.status <> 'cancelled') AS spent
    FROM customers c" . $where . ' ORDER BY c.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params);

$adminTitle = $view ? 'Customer: ' . $view['name'] : 'Customers';
include __DIR__ . '/includes/header.php';
?>
<?php if ($view): $orders = q_all('SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC', [$view['id']]); ?>
  <p><a href="customers.php">← All customers</a></p>
  <div class="grid-main">
    <div class="card">
      <div class="card-head"><h2>Orders (<?= count($orders) ?>)</h2></div>
      <?php if ($orders): ?>
        <div class="table-wrap"><table>
          <thead><tr><th>Order</th><th>Date</th><th>Total</th><th>Payment</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($orders as $o): ?>
            <tr class="clickable" onclick="location='order.php?id=<?= (int) $o['id'] ?>'">
              <td><b><?= e($o['order_number']) ?></b></td><td><?= e(date('M j, Y', strtotime($o['created_at']))) ?></td>
              <td><?= money($o['total']) ?></td><td><?= payment_badge($o['payment_status']) ?></td><td><?= admin_status_badge($o['status']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php else: ?><p class="muted">No orders yet.</p><?php endif; ?>
    </div>
    <div>
      <div class="card">
        <div class="card-head"><h2>Details</h2><?= (int) $view['active'] ? '<span class="status st-delivered">Active</span>' : '<span class="status st-cancelled">Disabled</span>' ?></div>
        <div class="kv"><span>Name</span><b><?= e($view['name']) ?></b></div>
        <div class="kv"><span>Email</span><b><a href="mailto:<?= e($view['email']) ?>"><?= e($view['email']) ?></a></b></div>
        <div class="kv"><span>Phone</span><b><?= e($view['phone'] ?: '-') ?></b></div>
        <div class="kv"><span>Address</span><b><?= e(implode(', ', array_filter([$view['address'], $view['city'], $view['state'], $view['zip'], $view['country_code']], 'strlen')) ?: '-') ?></b></div>
        <div class="kv"><span>Registered</span><b><?= e(date('M j, Y', strtotime($view['created_at']))) ?></b></div>
        <div class="kv"><span>Last login</span><b><?= $view['last_login'] ? e(date('M j, Y H:i', strtotime($view['last_login']))) : '-' ?></b></div>
        <?php if ($view['phone'] !== ''): ?><a class="btn btn-wa btn-block" href="https://wa.me/<?= e(preg_replace('/\D+/', '', $view['phone'])) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 16) ?> WhatsApp</a><?php endif; ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $view['id'] ?>"><input type="hidden" name="action" value="toggle">
          <button class="btn btn-light btn-block" type="submit"><?= (int) $view['active'] ? 'Disable account (cannot log in)' : 'Enable account' ?></button></form>
      </div>
      <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $view['id'] ?>"><input type="hidden" name="action" value="password">
        <div class="card-head"><h2>Set a new password</h2></div>
        <input type="text" name="password" minlength="8" placeholder="New password (min. 8)" autocomplete="off">
        <button class="btn btn-light btn-block" type="submit">Change password</button>
        <p class="help" style="margin-top:8px">Customers can also reset it themselves with “Forgot your password?”.</p>
      </form>
      <form method="post" class="card" data-confirm="Delete this customer account? Their orders are kept."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $view['id'] ?>"><input type="hidden" name="action" value="delete">
        <button class="btn btn-danger btn-block" type="submit">Delete account</button></form>
    </div>
  </div>
<?php else: ?>
  <div class="card">
    <div class="toolbar">
      <form method="get" class="grow-form"><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email or phone"></form>
      <span class="muted"><?= $total ?> customer(s) · <a href="settings.php">Account settings</a></span>
    </div>
    <?php if ($rows): ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Orders</th><th>Spent</th><th>Registered</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $c): ?>
        <tr class="clickable" onclick="location='?id=<?= (int) $c['id'] ?>'">
          <td><b><?= e($c['name']) ?></b><?= (int) $c['active'] ? '' : ' <span class="status st-cancelled">Disabled</span>' ?></td>
          <td><?= e($c['email']) ?></td><td><?= e($c['phone']) ?></td>
          <td><?= (int) $c['order_count'] ?></td><td><?= money($c['spent']) ?></td>
          <td><small><?= e(date('M j, Y', strtotime($c['created_at']))) ?></small></td>
          <td><a class="btn btn-sm btn-light" href="?id=<?= (int) $c['id'] ?>">View</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?= paginate_links($total, $perPage, $page, array_filter(['q' => $search])) ?>
    <?php else: ?><p class="muted">No customer accounts yet.</p><?php endif; ?>
  </div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
