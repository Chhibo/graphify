<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

if (is_post()) {
    $action = input('action');
    $id = (int) input('id');
    if ($action === 'save') {
        $name = mb_substr(input('name'), 0, 100);
        $parent = (int) input('parent_id');
        $icon = in_array(input('icon'), category_icon_names(), true) ? input('icon') : 'tag';
        if ($name === '') {
            flash('error', 'Category name is required.');
            back('admin/categories.php');
        }
        if ($parent === $id) {
            $parent = 0;
        }
        // keep the tree two levels deep
        if ($parent && (int) q_val('SELECT parent_id FROM categories WHERE id = ?', [$parent]) !== 0) {
            $parent = (int) q_val('SELECT parent_id FROM categories WHERE id = ?', [$parent]);
        }
        if ($id && $parent && q_val('SELECT COUNT(*) FROM categories WHERE parent_id = ?', [$id])) {
            flash('error', 'This category has sub-categories, so it must stay a top-level category.');
            back('admin/categories.php');
        }
        $data = [
            'name' => $name,
            'slug' => slugify(input('slug') ?: $name),
            'parent_id' => $parent,
            'icon' => $icon,
            'description' => mb_substr(input('description'), 0, 255),
            'sort_order' => (int) input('sort_order'),
        ];
        if ($id) {
            db_update('categories', $data, 'id = ?', [$id]);
            flash('success', 'Category updated.');
        } else {
            db_insert('categories', $data + ['created_at' => now()]);
            flash('success', 'Category added.');
        }
        redirect('admin/categories.php');
    }
    if ($action === 'delete' && $id) {
        $target = (int) input('move_to');
        $ids = category_ids_with_children($id);
        $in = implode(',', $ids);
        if ($target && !in_array($target, $ids, true)) {
            q("UPDATE listings SET category_id = ? WHERE category_id IN ($in)", [$target]);
        } elseif ((int) q_val("SELECT COUNT(*) FROM listings WHERE category_id IN ($in)")) {
            flash('error', 'This category still has ads. Choose a category to move them to.');
            redirect('admin/categories.php?delete=' . $id);
        }
        q("DELETE FROM categories WHERE id IN ($in)");
        flash('success', 'Category deleted.');
        redirect('admin/categories.php');
    }
}

$tree = category_tree();
$all = categories_all();
$counts = [];
foreach (q_all('SELECT category_id, COUNT(*) AS n FROM listings GROUP BY category_id') as $r) {
    $counts[(int) $r['category_id']] = (int) $r['n'];
}
$edit = input('edit') ? ($all[(int) input('edit')] ?? null) : null;
$deleting = input('delete') ? ($all[(int) input('delete')] ?? null) : null;
$form = $edit ?? ['id' => 0, 'name' => '', 'slug' => '', 'parent_id' => 0, 'icon' => 'tag', 'description' => '', 'sort_order' => count($tree)];

$adminActive = 'categories';
$pageTitle = 'Categories';
require APP_ROOT . '/includes/admin-top.php';
?>
<div class="admin-grid wide-left">
  <section class="card table-card">
    <div class="card-head"><h2>All categories</h2><span class="muted small"><?= count($all) ?> total</span></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Name</th><th>Ads</th><th>Order</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($tree as $id => $c): ?>
          <tr class="row-parent">
            <td><span class="cat-cell"><span class="cat-icon sm"><?= icon($c['icon']) ?></span><strong><?= e($c['name']) ?></strong></span></td>
            <td><?= $counts[$id] ?? 0 ?></td>
            <td><?= (int) $c['sort_order'] ?></td>
            <td class="row-actions nowrap">
              <a class="icon-btn sm" href="?edit=<?= $id ?>" aria-label="Edit"><?= icon('edit') ?></a>
              <a class="icon-btn sm danger" href="?delete=<?= $id ?>" aria-label="Delete"><?= icon('trash') ?></a>
            </td>
          </tr>
          <?php foreach ($c['children'] as $cid => $ch): ?>
            <tr>
              <td><span class="cat-cell child"><?= icon('chevron-right') ?><?= e($ch['name']) ?></span></td>
              <td><?= $counts[$cid] ?? 0 ?></td>
              <td><?= (int) $ch['sort_order'] ?></td>
              <td class="row-actions nowrap">
                <a class="icon-btn sm" href="?edit=<?= $cid ?>" aria-label="Edit"><?= icon('edit') ?></a>
                <a class="icon-btn sm danger" href="?delete=<?= $cid ?>" aria-label="Delete"><?= icon('trash') ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
        <?php if (!$tree): ?><tr><td colspan="4" class="center muted">No categories yet. Add your first one.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <div>
    <?php if ($deleting): ?>
      <form method="post" class="card form-section danger-zone">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $deleting['id'] ?>">
        <h2>Delete “<?= e($deleting['name']) ?>”</h2>
        <p class="muted small">Sub-categories will be deleted too. Ads in these categories can be moved to another category.</p>
        <div class="field"><label for="move">Move ads to</label>
          <select id="move" name="move_to"><option value="">— Don't move (only if empty) —</option><?= category_options() ?></select></div>
        <div class="btn-row"><a class="btn btn-ghost" href="<?= e(url('admin/categories.php')) ?>">Cancel</a><button class="btn btn-danger" type="submit">Delete category</button></div>
      </form>
    <?php endif; ?>

    <form method="post" class="card form-section">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
      <h2><?= $edit ? 'Edit category' : 'Add a category' ?></h2>
      <div class="field"><label for="c-name">Name</label><input id="c-name" name="name" value="<?= e($form['name']) ?>" required></div>
      <div class="field"><label for="c-parent">Parent</label>
        <select id="c-parent" name="parent_id"><option value="0">— None (top-level) —</option>
          <?php foreach ($tree as $id => $c): if ($id === (int) $form['id']) continue; ?><option value="<?= $id ?>" <?= (int) $form['parent_id'] === $id ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="field"><label>Icon</label>
        <div class="icon-picker">
          <?php foreach (category_icon_names() as $ic): ?>
            <label title="<?= e($ic) ?>"><input type="radio" name="icon" value="<?= $ic ?>" <?= $form['icon'] === $ic ? 'checked' : '' ?>><span><?= icon($ic) ?></span></label>
          <?php endforeach; ?>
        </div></div>
      <div class="form-grid">
        <div class="field"><label for="c-slug">Slug</label><input id="c-slug" name="slug" value="<?= e($form['slug']) ?>" placeholder="auto"></div>
        <div class="field"><label for="c-order">Sort order</label><input id="c-order" type="number" name="sort_order" value="<?= (int) $form['sort_order'] ?>"></div>
      </div>
      <div class="field"><label for="c-desc">Description</label><input id="c-desc" name="description" value="<?= e($form['description']) ?>" maxlength="255"></div>
      <div class="btn-row">
        <?php if ($edit): ?><a class="btn btn-ghost" href="<?= e(url('admin/categories.php')) ?>">Cancel</a><?php endif; ?>
        <button class="btn btn-primary" type="submit"><?= $edit ? 'Save changes' : 'Add category' ?></button>
      </div>
    </form>
  </div>
</div>
<?php require APP_ROOT . '/includes/admin-bottom.php';
