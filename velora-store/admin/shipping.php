<?php
require __DIR__ . '/includes/auth.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        q('DELETE FROM shipping_methods WHERE id = ?', [$id]);
        flash('success', 'Delivery option deleted.');
        redirect('admin/shipping.php');
    }
    $data = [
        'name' => mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120),
        'description' => mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 255),
        'cost' => max(0, round((float) ($_POST['cost'] ?? 0), 2)),
        'active' => post_flag('active'),
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
    ];
    if ($data['name'] === '') {
        flash('error', 'Please enter a name.');
    } else {
        $id ? db_update('shipping_methods', $id, $data) : db_insert('shipping_methods', $data);
        flash('success', 'Delivery option saved.');
    }
    redirect('admin/shipping.php');
}

$edit = isset($_GET['edit']) ? q_one('SELECT * FROM shipping_methods WHERE id = ?', [(int) $_GET['edit']]) : null;
$rows = shipping_methods(false);
$adminTitle = 'Delivery options';
include __DIR__ . '/includes/header.php';
?>
<div class="grid-main">
  <div class="card">
    <p class="muted">Customers choose one of these at checkout. In each product you can turn delivery off, or choose which options are available for it.
      <?php if ((float) setting('free_shipping_over') > 0): ?> Orders over <?= money(setting('free_shipping_over')) ?> get free delivery (<a href="settings.php">change</a>).<?php endif; ?></p>
    <?php if ($rows): ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Name</th><th>Description</th><th>Price</th><th>Active</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $m): ?>
        <tr>
          <td><b><?= e($m['name']) ?></b></td>
          <td><?= e($m['description']) ?></td>
          <td><?= (float) $m['cost'] > 0 ? money($m['cost']) : 'Free' ?></td>
          <td><?= $m['active'] ? 'Yes' : 'No' ?></td>
          <td class="actions">
            <a class="btn btn-sm btn-light" href="?edit=<?= (int) $m['id'] ?>">Edit</a>
            <form method="post" data-confirm="Delete this delivery option?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn btn-sm btn-danger-light">Delete</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php else: ?><p class="muted">No delivery options: the flat delivery fee from Settings is used.</p><?php endif; ?>
  </div>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <div class="card-head"><h2><?= $edit ? 'Edit delivery option' : 'Add delivery option' ?></h2></div>
    <label>Name<input name="name" value="<?= e($edit['name'] ?? '') ?>" required placeholder="e.g. Express Delivery"></label>
    <label>Description <small class="muted">(optional)</small><input name="description" value="<?= e($edit['description'] ?? '') ?>" placeholder="e.g. 1-2 business days"></label>
    <div class="grid-2">
      <label>Price <small class="muted">(0 = free)</small><input name="cost" type="number" step="0.01" min="0" value="<?= e($edit['cost'] ?? '0') ?>"></label>
      <label>Sort order<input name="sort_order" type="number" value="<?= (int) ($edit['sort_order'] ?? 0) ?>"></label>
    </div>
    <label class="inline"><input type="checkbox" name="active" value="1" <?= ($edit['active'] ?? 1) ? 'checked' : '' ?>> Active</label>
    <button class="btn btn-primary btn-block" type="submit">Save</button>
    <?php if ($edit): ?><a class="btn btn-light btn-block" href="shipping.php">Cancel</a><?php endif; ?>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
