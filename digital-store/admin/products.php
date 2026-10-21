<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin_layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $q = db()->prepare('SELECT image, file_name FROM products WHERE id = ?');
        $q->execute([$id]);
        if ($p = $q->fetch()) {
            if ($p['image']) @unlink(UPLOADS_DIR . '/' . basename($p['image']));
            if ($p['file_name']) @unlink(FILES_DIR . '/' . basename($p['file_name']));
            db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            flash('Product deleted.');
        }
    } elseif ($action === 'feature') {
        db()->prepare('UPDATE products SET featured = 1 - featured WHERE id = ?')->execute([$id]);
    } elseif ($action === 'toggle') {
        db()->prepare('UPDATE products SET active = 1 - active WHERE id = ?')->execute([$id]);
    }
    // Go back to the same filtered list.
    $back = parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_QUERY);
    redirect('admin/products.php' . ($back ? '?' . $back : ''));
}

$catFilter = (int)($_GET['category'] ?? 0);
$search = trim((string)($_GET['q'] ?? ''));
$where = [];
$args = [];
if ($catFilter) {
    $where[] = 'p.category_id = ?';
    $args[] = $catFilter;
}
if ($search !== '') {
    $where[] = 'p.title LIKE ?';
    $args[] = '%' . $search . '%';
}
$stmt = db()->prepare('SELECT p.*, c.name AS category_name,
        (SELECT COUNT(*) FROM unlocks u WHERE u.product_id = p.id) AS opened,
        (SELECT COUNT(*) FROM unlocks u WHERE u.product_id = p.id AND u.completed_at IS NOT NULL AND u.network != \'test\') AS unlocks
    FROM products p LEFT JOIN categories c ON c.id = p.category_id
    ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY p.id DESC');
$stmt->execute($args);
$products = $stmt->fetchAll();
$categories = db()->query('SELECT id, name FROM categories ORDER BY sort_order, name')->fetchAll();
$total = (int)db()->query('SELECT COUNT(*) FROM products')->fetchColumn();

admin_header('Products', $total . ' product' . ($total === 1 ? '' : 's') . ' in your store',
    '<a class="btn btn-grad" href="product_edit.php">' . icon('plus', 16) . ' Add product</a>');
?>
<div class="toolbar">
  <form method="get">
    <label class="search-box"><?= icon('search', 16) ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search products…"></label>
    <?php if ($categories): ?>
      <select name="category" onchange="this.form.submit()">
        <option value="0">All categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $catFilter === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
    <?php if ($search !== '' || $catFilter): ?><a class="btn btn-light" href="products.php">Clear</a><?php endif; ?>
  </form>
</div>

<?php if (!$products): ?>
  <div class="card empty"><?= icon('package', 40) ?><p><?= $total ? 'No products match.' : 'No products yet.' ?></p>
    <?php if (!$total): ?><a class="btn" href="product_edit.php"><?= icon('plus', 16) ?> Add your first product</a><?php endif; ?></div>
<?php else: ?>
<div class="table-wrap"><table>
  <thead><tr><th>Product</th><th>Category</th><th>Status</th><th class="num">Views</th><th class="num">Unlocks</th><th class="num">Conv.</th><th class="num">Downloads</th><th title="Featured on the home page">★</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($products as $p): $conv = $p['opened'] ? round($p['unlocks'] / $p['opened'] * 100) : 0; ?>
    <tr>
      <td><div class="prod-cell">
        <?php if ($p['image']): ?><img class="mini" src="../uploads/<?= e($p['image']) ?>" alt=""><?php else: ?><span class="mini-empty"><?= icon('package') ?></span><?php endif; ?>
        <div><a href="product_edit.php?id=<?= (int)$p['id'] ?>"><?= e($p['title']) ?></a>
          <?php if ($p['badge'] !== ''): ?><br><span class="tag tag-accent"><?= e($p['badge']) ?></span><?php endif; ?></div>
      </div></td>
      <td><?= $p['category_name'] !== null ? e($p['category_name']) : '<span class="muted">—</span>' ?></td>
      <td><span class="tag <?= $p['active'] ? 'tag-ok' : '' ?>"><?= $p['active'] ? 'Visible' : 'Hidden' ?></span></td>
      <td class="num"><?= number_format((int)$p['views']) ?></td>
      <td class="num"><?= number_format((int)$p['unlocks']) ?></td>
      <td class="num"><?= $conv ?>%</td>
      <td class="num"><?= number_format((int)$p['downloads']) ?></td>
      <td>
        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="star <?= $p['featured'] ? 'on' : '' ?>" name="action" value="feature" title="<?= $p['featured'] ? 'Remove from Featured' : 'Add to Featured' ?>">★</button></form>
      </td>
      <td class="actions">
        <a class="icon-btn" href="../product.php?id=<?= (int)$p['id'] ?>" target="_blank" title="View"><?= icon('external', 16) ?></a>
        <a class="icon-btn" href="product_edit.php?id=<?= (int)$p['id'] ?>" title="Edit"><?= icon('pencil', 16) ?></a>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="icon-btn" name="action" value="toggle" title="<?= $p['active'] ? 'Hide' : 'Show' ?>"><?= icon($p['active'] ? 'eye' : 'x', 16) ?></button></form>
        <form method="post" onsubmit="return confirm('Delete this product and its file?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="icon-btn danger" name="action" value="delete" title="Delete"><?= icon('trash', 16) ?></button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
<?php admin_footer();
