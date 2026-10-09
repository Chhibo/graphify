<?php
require __DIR__ . '/includes/auth.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        q('UPDATE products SET category_id = NULL WHERE category_id = ?', [$id]);
        q('DELETE FROM categories WHERE id = ?', [$id]);
        flash('success', 'Category deleted. Its products were kept without a category.');
        redirect('admin/categories.php');
    }
    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name === '') {
        flash('error', 'Category name is required.');
        redirect('admin/categories.php' . ($id ? '?edit=' . $id : ''));
    }
    $data = ['name' => $name, 'slug' => unique_slug('categories', $name, $id), 'sort_order' => (int) ($_POST['sort_order'] ?? 0)];
    try {
        $img = upload_image($_FILES['image'] ?? null);
        if ($img) {
            $data['image'] = $img;
        }
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
        redirect('admin/categories.php' . ($id ? '?edit=' . $id : ''));
    }
    if ($id) {
        db_update('categories', $id, $data);
    } else {
        db_insert('categories', $data + ['image' => '']);
    }
    flash('success', 'Category saved.');
    redirect('admin/categories.php');
}

$edit = isset($_GET['edit']) ? q_one('SELECT * FROM categories WHERE id = ?', [(int) $_GET['edit']]) : null;
$cats = categories();
$adminTitle = 'Categories (Dress Styles)';
include __DIR__ . '/includes/header.php';
?>
<div class="grid-main">
  <div class="card">
    <p class="muted">Categories are shown in the “Best For Your Categories” slider on the home page and in the Shop menu.</p>
    <div class="table-wrap"><table>
      <thead><tr><th></th><th>Name</th><th>Products</th><th>Order</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($cats as $c): ?>
        <tr>
          <td><img class="thumb" src="<?= e(img_url($c['image'])) ?>" alt=""></td>
          <td><b><?= e($c['name']) ?></b></td>
          <td><?= (int) $c['product_count'] ?></td>
          <td><?= (int) $c['sort_order'] ?></td>
          <td class="actions">
            <a class="btn btn-sm btn-light" href="?edit=<?= (int) $c['id'] ?>">Edit</a>
            <form method="post" data-confirm="Delete this category?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn btn-sm btn-danger-light">Delete</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <form method="post" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <div class="card-head"><h2><?= $edit ? 'Edit category' : 'Add category' ?></h2></div>
    <label>Name<input name="name" value="<?= e($edit['name'] ?? '') ?>" required></label>
    <label>Sort order<input name="sort_order" type="number" value="<?= (int) ($edit['sort_order'] ?? 0) ?>"></label>
    <?php if (!empty($edit['image'])): ?><img class="preview" src="<?= e(img_url($edit['image'])) ?>" alt=""><?php endif; ?>
    <label>Image <small class="muted">(portrait photo works best)</small><input type="file" name="image" accept="image/*"></label>
    <button class="btn btn-primary btn-block" type="submit">Save</button>
    <?php if ($edit): ?><a class="btn btn-light btn-block" href="categories.php">Cancel</a><?php endif; ?>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
