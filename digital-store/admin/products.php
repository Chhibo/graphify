<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin_layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        $q = db()->prepare('SELECT image, file_name FROM products WHERE id = ?');
        $q->execute([$id]);
        if ($p = $q->fetch()) {
            if ($p['image']) @unlink(UPLOADS_DIR . '/' . basename($p['image']));
            if ($p['file_name']) @unlink(FILES_DIR . '/' . basename($p['file_name']));
            db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            flash('Product deleted.');
        }
    } elseif (($_POST['action'] ?? '') === 'toggle') {
        db()->prepare('UPDATE products SET active = 1 - active WHERE id = ?')->execute([$id]);
    }
    redirect('admin/products.php');
}

$products = db()->query('SELECT p.*,
        (SELECT COUNT(*) FROM unlocks u WHERE u.product_id = p.id AND u.completed_at IS NOT NULL) AS unlocks
    FROM products p ORDER BY p.id DESC')->fetchAll();

admin_header('Products');
?>
<p><a class="btn" href="product_edit.php">+ Add product</a></p>
<?php if (!$products): ?>
  <p class="muted">No products yet.</p>
<?php else: ?>
<table>
  <tr><th></th><th>Title</th><th>Status</th><th>Unlocks</th><th>Downloads</th><th></th></tr>
  <?php foreach ($products as $p): ?>
    <tr>
      <td><?php if ($p['image']): ?><img class="mini" src="../uploads/<?= e($p['image']) ?>" alt=""><?php endif; ?></td>
      <td><a href="../product.php?id=<?= (int)$p['id'] ?>" target="_blank"><?= e($p['title']) ?></a></td>
      <td><?= $p['active'] ? '<span class="ok">Visible</span>' : '<span class="muted">Hidden</span>' ?></td>
      <td><?= (int)$p['unlocks'] ?></td>
      <td><?= (int)$p['downloads'] ?></td>
      <td class="actions">
        <a class="btn btn-small" href="product_edit.php?id=<?= (int)$p['id'] ?>">Edit</a>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="btn btn-small btn-light" name="action" value="toggle"><?= $p['active'] ? 'Hide' : 'Show' ?></button></form>
        <form method="post" onsubmit="return confirm('Delete this product and its file?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="btn btn-small btn-danger" name="action" value="delete">Delete</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php admin_footer();
