<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$catId = (int) input('category');
$cat = categories_all()[$catId] ?? null;
if (!$cat) {
    flash('error', 'Choose a category first.');
    redirect('admin/categories.php');
}
$parent = (int) $cat['parent_id'] ? (categories_all()[(int) $cat['parent_id']] ?? null) : null;

if (is_post()) {
    $action = input('action');
    $id = (int) input('id');
    if ($action === 'save') {
        $type = array_key_exists(input('type'), FIELD_TYPES) ? input('type') : 'text';
        $options = implode("\n", array_values(array_unique(array_filter(array_map('trim', preg_split('/\r?\n/', (string) ($_POST['options'] ?? '')) ?: [])))));
        $data = [
            'label' => mb_substr(input('label'), 0, 100),
            'type' => $type,
            'options' => $type === 'select' ? $options : '',
            'unit' => $type === 'number' || $type === 'text' ? mb_substr(input('unit'), 0, 30) : '',
            'required' => empty($_POST['required']) ? 0 : 1,
            'filterable' => empty($_POST['filterable']) ? 0 : 1,
            'sort_order' => (int) input('sort_order'),
        ];
        if ($data['label'] === '') {
            flash('error', 'Give the field a name.');
        } elseif ($type === 'select' && !$options) {
            flash('error', 'A dropdown needs at least one option (one per line).');
        } elseif ($id) {
            db_update('category_fields', $data, 'id = ? AND category_id = ?', [$id, $catId]);
            log_activity('field.update', $cat['name'] . ' → ' . $data['label'], 'category', $catId);
            flash('success', 'Field updated.');
            redirect('admin/category-fields.php?category=' . $catId);
        } else {
            db_insert('category_fields', $data + ['category_id' => $catId]);
            log_activity('field.create', $cat['name'] . ' → ' . $data['label'], 'category', $catId);
            flash('success', 'Field added.');
            redirect('admin/category-fields.php?category=' . $catId);
        }
    } elseif ($action === 'delete' && $id) {
        $f = q_one('SELECT * FROM category_fields WHERE id = ? AND category_id = ?', [$id, $catId]);
        if ($f) {
            q('DELETE FROM listing_field_values WHERE field_id = ?', [$id]);
            q('DELETE FROM category_fields WHERE id = ?', [$id]);
            log_activity('field.delete', $cat['name'] . ' → ' . $f['label'], 'category', $catId);
            flash('success', 'Field deleted.');
        }
        redirect('admin/category-fields.php?category=' . $catId);
    }
}

$fields = q_all('SELECT f.*, (SELECT COUNT(*) FROM listing_field_values v WHERE v.field_id = f.id) AS used FROM category_fields f WHERE f.category_id = ? ORDER BY f.sort_order, f.id', [$catId]);
$inherited = $parent ? q_all('SELECT * FROM category_fields WHERE category_id = ? ORDER BY sort_order, id', [$parent['id']]) : [];
$edit = input('edit') ? q_one('SELECT * FROM category_fields WHERE id = ? AND category_id = ?', [(int) input('edit'), $catId]) : null;
$form = $edit ?? ['id' => 0, 'label' => '', 'type' => 'text', 'options' => '', 'unit' => '', 'required' => 0, 'filterable' => 1, 'sort_order' => count($fields)];
if (is_post() && input('action') === 'save') {
    $form = array_merge($form, ['label' => input('label'), 'type' => input('type'), 'options' => (string) ($_POST['options'] ?? ''), 'unit' => input('unit')]);
}

$adminActive = 'categories';
$pageTitle = 'Fields · ' . $cat['name'];
require APP_ROOT . '/includes/admin-top.php';
?>
<div class="page-actions">
  <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/categories.php')) ?>"><?= icon('chevron-left') ?>Categories</a>
  <span class="muted small">Extra details sellers fill in when posting in <strong><?= e($cat['name']) ?></strong><?= $parent ? '' : ' and all its sub-categories' ?>. Buyers can filter by them.</span>
</div>

<div class="admin-grid wide-left">
  <div>
    <section class="card table-card">
      <div class="card-head"><h2><?= e($cat['name']) ?> fields</h2><span class="muted small"><?= count($fields) ?></span></div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Filter</th><th>Used</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($fields as $f): ?>
            <tr>
              <td><strong><?= e($f['label']) ?></strong><?= $f['unit'] ? ' <span class="muted small">(' . e($f['unit']) . ')</span>' : '' ?>
                <?php if ($f['type'] === 'select'): ?><small class="muted block"><?= e(excerpt(str_replace("\n", ', ', (string) $f['options']), 70)) ?></small><?php endif; ?></td>
              <td><span class="badge"><?= e(FIELD_TYPES[$f['type']] ?? $f['type']) ?></span></td>
              <td><?= $f['required'] ? icon('check', 'text-success') : '' ?></td>
              <td><?= $f['filterable'] ? icon('check', 'text-success') : '' ?></td>
              <td class="muted small"><?= (int) $f['used'] ?> ads</td>
              <td class="row-actions nowrap">
                <a class="icon-btn sm" href="?category=<?= $catId ?>&edit=<?= (int) $f['id'] ?>" aria-label="Edit"><?= icon('edit') ?></a>
                <form method="post" style="display:inline" data-confirm="Delete this field and the values sellers entered for it?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><button class="icon-btn sm danger" type="submit" aria-label="Delete"><?= icon('trash') ?></button></form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$fields): ?><tr><td colspan="6" class="center muted">No fields yet. Add one on the right — e.g. “Brand”, “Year”, “Mileage”.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php if ($inherited): ?>
      <section class="card side-card">
        <h3><?= icon('folder') ?>Also shown here (from <?= e($parent['name']) ?>)</h3>
        <p class="muted small"><?= e(implode(', ', array_column($inherited, 'label'))) ?> — <a href="?category=<?= (int) $parent['id'] ?>">edit</a></p>
      </section>
    <?php endif; ?>
  </div>

  <form method="post" class="card form-section">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
    <h2><?= $edit ? 'Edit field' : 'Add a field' ?></h2>
    <div class="field"><label for="fl">Field name</label><input id="fl" name="label" value="<?= e($form['label']) ?>" placeholder="e.g. Brand, Year, Bedrooms" required></div>
    <div class="form-grid">
      <div class="field"><label for="ft">Type</label>
        <select id="ft" name="type" data-field-type><?php foreach (FIELD_TYPES as $k => $lbl): ?><option value="<?= $k ?>" <?= $form['type'] === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?></select></div>
      <div class="field" data-show-for="number text"><label for="fu">Unit <small class="muted">(optional)</small></label><input id="fu" name="unit" value="<?= e($form['unit']) ?>" placeholder="km, sqft, GB…"></div>
    </div>
    <div class="field" data-show-for="select"><label for="fo">Options <small class="muted">(one per line)</small></label><textarea id="fo" name="options" rows="6" placeholder="Toyota&#10;Honda&#10;BMW"><?= e((string) $form['options']) ?></textarea></div>
    <div class="field"><label for="fs">Order</label><input id="fs" type="number" name="sort_order" value="<?= (int) $form['sort_order'] ?>"></div>
    <label class="switch"><input type="checkbox" name="required" value="1" <?= $form['required'] ? 'checked' : '' ?>><span class="switch-ui"></span><span>Required when posting</span></label>
    <label class="switch"><input type="checkbox" name="filterable" value="1" <?= $form['filterable'] ? 'checked' : '' ?>><span class="switch-ui"></span><span>Show as a search filter</span></label>
    <div class="btn-row">
      <?php if ($edit): ?><a class="btn btn-ghost" href="?category=<?= $catId ?>">Cancel</a><?php endif; ?>
      <button class="btn btn-primary" type="submit"><?= $edit ? 'Save field' : 'Add field' ?></button>
    </div>
  </form>
</div>
<?php require APP_ROOT . '/includes/admin-bottom.php';
