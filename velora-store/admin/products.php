<?php
require __DIR__ . '/includes/auth.php';
require_admin('products');

if (is_post()) {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        q('DELETE FROM products WHERE id = ?', [$id]);
        flash('success', 'Product deleted.');
    } elseif (($_POST['action'] ?? '') === 'toggle') {
        q('UPDATE products SET active = 1 - active WHERE id = ?', [$id]);
    }
    redirect('admin/products.php?' . http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'page' => $_GET['page'] ?? ''])));
}

$search = trim((string) ($_GET['q'] ?? ''));
$params = [];
$where = '';
if ($search !== '') {
    $where = ' WHERE p.name LIKE ? OR p.brand LIKE ?';
    $params = ["%$search%", "%$search%"];
}
$perPage = 25;
$page = max(1, (int) ($_GET['page'] ?? 1));
$total = (int) q_val('SELECT COUNT(*) FROM products p' . $where, $params);
$products = q_all('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id' . $where
    . ' ORDER BY p.sort_order, p.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params);

$adminTitle = 'Products';
include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <div class="toolbar">
    <form method="get" class="grow-form"><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search products"></form>
    <a class="btn btn-primary" href="product-edit.php"><?= icon('plus', 16) ?> Add product</a>
  </div>
  <?php if ($products): ?>
  <div class="table-wrap"><table>
    <thead><tr><th></th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Home sections</th><th>Visible</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): ?>
      <tr>
        <td><img class="thumb" src="<?= e(img_url($p['image'])) ?>" alt=""></td>
        <td><a href="product-edit.php?id=<?= (int) $p['id'] ?>"><b><?= e($p['name']) ?></b></a><br><small class="muted"><?= e($p['brand']) ?></small></td>
        <td><?= e($p['category_name'] ?? '-') ?></td>
        <td><?= money($p['price']) ?><?php if ((float) $p['old_price'] > (float) $p['price']): ?> <del class="muted"><?= money($p['old_price']) ?></del><?php endif; ?></td>
        <td><?= (int) $p['stock'] < 0 ? '∞' : (int) $p['stock'] ?></td>
        <td class="tags">
          <?php if ($p['is_new']): ?><span class="tag">New</span><?php endif; ?>
          <?php if ($p['is_trending']): ?><span class="tag">Trending</span><?php endif; ?>
          <?php if ($p['is_flash']): ?><span class="tag red">Deal</span><?php endif; ?>
        </td>
        <td>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button class="switch <?= $p['active'] ? 'on' : '' ?>" type="submit" title="Show / hide"></button></form>
        </td>
        <td class="actions">
          <a class="btn btn-sm btn-light" href="product-edit.php?id=<?= (int) $p['id'] ?>">Edit</a>
          <form method="post" data-confirm="Delete “<?= e($p['name']) ?>”?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button class="btn btn-sm btn-danger-light" type="submit">Delete</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= paginate_links($total, $perPage, $page, array_filter(['q' => $search])) ?>
  <?php else: ?><p class="muted">No products yet. <a href="product-edit.php">Add your first product</a>.</p><?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
