<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin_layout.php';
require_admin();

$errors = [];
$edit = ['id' => 0, 'name' => '', 'slug' => '', 'sort_order' => 0, 'show_on_home' => 1, 'show_in_header' => 1];
if (!empty($_GET['edit'])) {
    $q = db()->prepare('SELECT * FROM categories WHERE id = ?');
    $q->execute([(int)$_GET['edit']]);
    $edit = $q->fetch() ?: $edit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'delete') {
        db()->prepare('UPDATE products SET category_id = NULL WHERE category_id = ?')->execute([$id]);
        db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        flash('Category deleted. Its products are kept, without a category.');
        redirect('admin/categories.php');
    }
    if ($action === 'toggle_home' || $action === 'toggle_header') {
        $col = $action === 'toggle_home' ? 'show_on_home' : 'show_in_header';
        db()->prepare("UPDATE categories SET $col = 1 - $col WHERE id = ?")->execute([$id]);
        redirect('admin/categories.php');
    }
    if ($action === 'save') {
        $edit = [
            'id' => $id,
            'name' => trim((string)($_POST['name'] ?? '')),
            'slug' => trim((string)($_POST['slug'] ?? '')),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'show_on_home' => isset($_POST['show_on_home']) ? 1 : 0,
            'show_in_header' => isset($_POST['show_in_header']) ? 1 : 0,
        ];
        if ($edit['name'] === '') {
            $errors[] = 'Enter a category name.';
        } else {
            $slug = unique_slug('categories', slugify($edit['slug'] !== '' ? $edit['slug'] : $edit['name']), $id);
            $vals = [$edit['name'], $slug, $edit['sort_order'], $edit['show_on_home'], $edit['show_in_header']];
            if ($id) {
                db()->prepare('UPDATE categories SET name=?, slug=?, sort_order=?, show_on_home=?, show_in_header=? WHERE id=?')
                    ->execute(array_merge($vals, [$id]));
            } else {
                db()->prepare('INSERT INTO categories (name, slug, sort_order, show_on_home, show_in_header) VALUES (?,?,?,?,?)')
                    ->execute($vals);
            }
            flash('Category saved.');
            redirect('admin/categories.php');
        }
    }
}

$cats = db()->query('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS items
    FROM categories c ORDER BY c.sort_order, c.name')->fetchAll();

admin_header('Categories');
?>
<p class="muted">Categories ticked <b>Home</b> get their own section on the home page, under Featured Items.
  Categories ticked <b>Header</b> appear in the top menu. Lower <b>order</b> numbers come first.
  <?php if (setting('categories_in_header', '1') !== '1'): ?><br><span class="bad">Categories in the header menu are switched off in <a href="appearance.php">Appearance</a>.</span><?php endif; ?></p>

<?php if ($cats): ?>
<table>
  <tr><th>Order</th><th>Name</th><th>Items</th><th>Home</th><th>Header</th><th></th></tr>
  <?php foreach ($cats as $c): ?>
    <tr>
      <td><?= (int)$c['sort_order'] ?></td>
      <td><a href="../category.php?slug=<?= e(rawurlencode($c['slug'])) ?>" target="_blank"><?= e($c['name']) ?></a></td>
      <td><a href="products.php?category=<?= (int)$c['id'] ?>"><?= (int)$c['items'] ?></a></td>
      <?php foreach (['toggle_home' => 'show_on_home', 'toggle_header' => 'show_in_header'] as $act => $col): ?>
        <td><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button class="pill <?= $c[$col] ? 'on' : '' ?>" name="action" value="<?= $act ?>"><?= $c[$col] ? 'Shown' : 'Hidden' ?></button></form></td>
      <?php endforeach; ?>
      <td class="actions">
        <a class="btn btn-small" href="?edit=<?= (int)$c['id'] ?>#form">Edit</a>
        <form method="post" onsubmit="return confirm('Delete this category? Its products are kept.')"><?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button class="btn btn-small btn-danger" name="action" value="delete">Delete</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<form method="post" class="card form" id="form">
  <h2><?= $edit['id'] ? 'Edit category' : 'Add a category' ?></h2>
  <?php foreach ($errors as $err): ?><p class="bad"><?= e($err) ?></p><?php endforeach; ?>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
  <label>Name <input name="name" required value="<?= e($edit['name']) ?>" placeholder="e.g. Ebooks"></label>
  <label>Web address (optional) <input name="slug" value="<?= e($edit['slug']) ?>" placeholder="made from the name if empty">
    <small>Used in the link: category.php?slug=<b>ebooks</b></small></label>
  <label>Order <input type="number" name="sort_order" value="<?= (int)$edit['sort_order'] ?>" class="short"></label>
  <label class="check"><input type="checkbox" name="show_on_home" <?= $edit['show_on_home'] ? 'checked' : '' ?>> Show a section for this category on the home page</label>
  <label class="check"><input type="checkbox" name="show_in_header" <?= $edit['show_in_header'] ? 'checked' : '' ?>> Show in the header menu</label>
  <button class="btn" name="action" value="save">Save category</button>
  <?php if ($edit['id']): ?><a href="categories.php">Cancel</a><?php endif; ?>
</form>
<?php admin_footer();
