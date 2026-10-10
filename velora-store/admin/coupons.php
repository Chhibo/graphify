<?php
require __DIR__ . '/includes/auth.php';
require_admin();

$types = ['percent' => 'Percentage (%)', 'fixed' => 'Fixed amount', 'free_shipping' => 'Free delivery'];

if (is_post()) {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        q('DELETE FROM coupons WHERE id = ?', [$id]);
        flash('success', 'Coupon deleted.');
        redirect('admin/coupons.php');
    }
    $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_POST['code'] ?? '')));
    $type = isset($types[$_POST['type'] ?? '']) ? $_POST['type'] : 'percent';
    $date = function (string $key): ?string {
        $v = trim((string) ($_POST[$key] ?? ''));
        return $v !== '' && strtotime($v) ? date('Y-m-d H:i:s', strtotime($v)) : null;
    };
    $data = [
        'code' => mb_substr($code, 0, 40),
        'type' => $type,
        'value' => max(0, round((float) ($_POST['value'] ?? 0), 2)),
        'min_order' => max(0, round((float) ($_POST['min_order'] ?? 0), 2)),
        'max_uses' => max(0, (int) ($_POST['max_uses'] ?? 0)),
        'starts_at' => $date('starts_at'),
        'expires_at' => $date('expires_at'),
        'active' => post_flag('active'),
    ];
    $error = '';
    if ($data['code'] === '') {
        $error = 'Please enter a coupon code (letters and numbers).';
    } elseif ((int) q_val('SELECT COUNT(*) FROM coupons WHERE UPPER(code) = ? AND id <> ?', [$data['code'], $id]) > 0) {
        $error = 'This code already exists.';
    } elseif ($type === 'percent' && ($data['value'] <= 0 || $data['value'] > 100)) {
        $error = 'Percentage must be between 1 and 100.';
    } elseif ($type === 'fixed' && $data['value'] <= 0) {
        $error = 'Please enter the discount amount.';
    }
    if ($error !== '') {
        flash('error', $error);
        redirect('admin/coupons.php' . ($id ? '?edit=' . $id : ''));
    }
    if ($id) {
        db_update('coupons', $id, $data);
    } else {
        db_insert('coupons', $data + ['used_count' => 0, 'created_at' => now()]);
    }
    flash('success', 'Coupon saved.');
    redirect('admin/coupons.php');
}

$edit = isset($_GET['edit']) ? q_one('SELECT * FROM coupons WHERE id = ?', [(int) $_GET['edit']]) : null;
$rows = q_all('SELECT * FROM coupons ORDER BY id DESC');
$adminTitle = 'Coupons';
include __DIR__ . '/includes/header.php';
?>
<div class="grid-main">
  <div class="card">
    <p class="muted">Customers enter coupon codes in the cart or at checkout. A coupon can also be shown in the <a href="settings.php?tab=popup">subscribe popup</a>.</p>
    <?php if ($rows): ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Code</th><th>Discount</th><th>Min. order</th><th>Used</th><th>Valid</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $c): $err = coupon_error($c, PHP_INT_MAX); ?>
        <tr>
          <td><b><?= e($c['code']) ?></b></td>
          <td><?= e(coupon_label($c)) ?></td>
          <td><?= (float) $c['min_order'] > 0 ? money($c['min_order']) : '-' ?></td>
          <td><?= (int) $c['used_count'] ?><?= (int) $c['max_uses'] > 0 ? ' / ' . (int) $c['max_uses'] : '' ?></td>
          <td><small><?= $c['starts_at'] ? e(date('M j, Y', strtotime($c['starts_at']))) : 'Now' ?> → <?= $c['expires_at'] ? e(date('M j, Y', strtotime($c['expires_at']))) : 'No end' ?></small></td>
          <td><?= $err === '' ? '<span class="status st-delivered">Active</span>' : '<span class="status st-cancelled" title="' . e($err) . '">Inactive</span>' ?></td>
          <td class="actions">
            <a class="btn btn-sm btn-light" href="?edit=<?= (int) $c['id'] ?>">Edit</a>
            <form method="post" data-confirm="Delete coupon <?= e($c['code']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn btn-sm btn-danger-light">Delete</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php else: ?><p class="muted">No coupons yet.</p><?php endif; ?>
  </div>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <div class="card-head"><h2><?= $edit ? 'Edit coupon' : 'Add coupon' ?></h2></div>
    <label>Code<input name="code" value="<?= e($edit['code'] ?? '') ?>" required placeholder="e.g. SUMMER20" style="text-transform:uppercase"></label>
    <div class="grid-2">
      <label>Type
        <select name="type"><?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>" <?= ($edit['type'] ?? 'percent') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
      </label>
      <label>Value <small class="muted">(% or amount)</small><input name="value" type="number" step="0.01" min="0" value="<?= e($edit['value'] ?? '') ?>"></label>
    </div>
    <div class="grid-2">
      <label>Minimum order <small class="muted">(0 = none)</small><input name="min_order" type="number" step="0.01" min="0" value="<?= e($edit['min_order'] ?? '0') ?>"></label>
      <label>Usage limit <small class="muted">(0 = unlimited)</small><input name="max_uses" type="number" min="0" value="<?= (int) ($edit['max_uses'] ?? 0) ?>"></label>
    </div>
    <div class="grid-2">
      <label>Starts <small class="muted">(optional)</small><input name="starts_at" type="datetime-local" value="<?= !empty($edit['starts_at']) ? e(date('Y-m-d\TH:i', strtotime($edit['starts_at']))) : '' ?>"></label>
      <label>Expires <small class="muted">(optional)</small><input name="expires_at" type="datetime-local" value="<?= !empty($edit['expires_at']) ? e(date('Y-m-d\TH:i', strtotime($edit['expires_at']))) : '' ?>"></label>
    </div>
    <label class="inline"><input type="checkbox" name="active" value="1" <?= ($edit['active'] ?? 1) ? 'checked' : '' ?>> Active</label>
    <button class="btn btn-primary btn-block" type="submit">Save coupon</button>
    <?php if ($edit): ?><a class="btn btn-light btn-block" href="coupons.php">Cancel</a><?php endif; ?>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
