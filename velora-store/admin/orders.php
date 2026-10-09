<?php
require __DIR__ . '/includes/auth.php';
require_admin();

$status = (string) ($_GET['status'] ?? '');
$search = trim((string) ($_GET['q'] ?? ''));
$showAll = !empty($_GET['all']);
$where = [];
$params = [];
if (!$showAll) {
    // Hide abandoned online checkouts (customer never finished paying) unless asked.
    $where[] = "payment_status IN ('paid','cod')";
}
if (isset(order_status_labels()[$status])) {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($search !== '') {
    $where[] = '(order_number LIKE ? OR customer_name LIKE ? OR phone LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%");
}
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$perPage = 25;
$page = max(1, (int) ($_GET['page'] ?? 1));
$total = (int) q_val('SELECT COUNT(*) FROM orders' . $sqlWhere, $params);
$orders = q_all('SELECT * FROM orders' . $sqlWhere . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params);

$adminTitle = 'Orders';
include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <form class="toolbar" method="get">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search order #, name or phone">
    <select name="status" onchange="this.form.submit()">
      <option value="">All statuses</option>
      <?php foreach (order_status_labels() as $k => $l): ?><option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select>
    <label class="inline"><input type="checkbox" name="all" value="1" <?= $showAll ? 'checked' : '' ?> onchange="this.form.submit()"> Show unpaid / failed online checkouts</label>
    <button class="btn btn-sm" type="submit">Filter</button>
  </form>
  <?php if ($orders): ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Order</th><th>Date</th><th>Customer</th><th>City</th><th>Total</th><th>Method</th><th>Payment</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
      <tr onclick="location='order.php?id=<?= (int) $o['id'] ?>'" class="clickable">
        <td><b><?= e($o['order_number']) ?></b></td>
        <td><?= e(date('M j, Y H:i', strtotime($o['created_at']))) ?></td>
        <td><?= e($o['customer_name']) ?><br><small class="muted"><?= e($o['phone']) ?></small></td>
        <td><?= e($o['city']) ?></td>
        <td><b><?= money($o['total']) ?></b></td>
        <td><?= e(payment_method_label($o['payment_method'])) ?></td>
        <td><?= payment_badge($o['payment_status']) ?></td>
        <td><?= admin_status_badge($o['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= paginate_links($total, $perPage, $page, array_filter(['status' => $status, 'q' => $search, 'all' => $showAll ? 1 : ''])) ?>
  <?php else: ?><p class="muted">No orders found.</p><?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
