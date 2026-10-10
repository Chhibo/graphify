<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

if (is_post()) {
    $action = input('action');
    $id = (int) input('id');
    if ($action === 'mode') {
        $mode = input('location_input') === 'list' ? 'list' : 'text';
        save_settings(['location_input' => $mode]);
        log_activity('settings.update', 'Location input: ' . $mode);
        flash('success', $mode === 'list' ? 'Sellers now pick a location from your list.' : 'Sellers now type their location freely.');
        redirect('admin/locations.php');
    }
    if ($action === 'save') {
        $name = mb_substr(input('name'), 0, 100);
        $parent = (int) input('parent_id');
        if ($parent === $id || ($parent && (int) (locations_all()[$parent]['parent_id'] ?? 0))) {
            $parent = 0; // keep it two levels deep
        }
        if ($name === '') {
            flash('error', 'Enter a name.');
        } elseif ($id) {
            db_update('locations', ['name' => $name, 'slug' => slugify($name), 'parent_id' => $parent, 'sort_order' => (int) input('sort_order')], 'id = ?', [$id]);
            // keep the stored label on ads in sync
            locations_all(true);
            foreach (location_ids_with_children($id) as $lid) {
                q('UPDATE listings SET location = ? WHERE location_id = ?', [location_label($lid), $lid]);
            }
            log_activity('location.update', $name, 'location', $id);
            flash('success', 'Location updated.');
        } else {
            $newId = db_insert('locations', ['name' => $name, 'slug' => slugify($name), 'parent_id' => $parent, 'sort_order' => (int) input('sort_order')]);
            log_activity('location.create', $name, 'location', $newId);
            flash('success', 'Location added.');
        }
        redirect('admin/locations.php');
    }
    if ($action === 'bulk') {
        $added = 0;
        foreach (preg_split('/\r?\n/', (string) ($_POST['lines'] ?? '')) ?: [] as $line) {
            $parts = array_values(array_filter(array_map('trim', explode('>', $line)), fn($p) => $p !== ''));
            if (!$parts) {
                continue;
            }
            $cityName = mb_substr($parts[0], 0, 100);
            $city = q_one('SELECT id FROM locations WHERE parent_id = 0 AND name = ?', [$cityName]);
            $cityId = $city ? (int) $city['id'] : db_insert('locations', ['name' => $cityName, 'slug' => slugify($cityName), 'parent_id' => 0, 'sort_order' => 0]);
            $added += $city ? 0 : 1;
            foreach (array_map('trim', explode(',', $parts[1] ?? '')) as $area) {
                if ($area !== '' && !q_val('SELECT id FROM locations WHERE parent_id = ? AND name = ?', [$cityId, $area])) {
                    db_insert('locations', ['name' => mb_substr($area, 0, 100), 'slug' => slugify($area), 'parent_id' => $cityId, 'sort_order' => 0]);
                    $added++;
                }
            }
        }
        log_activity('location.bulk', "$added locations added");
        flash('success', "$added location(s) added.");
        redirect('admin/locations.php');
    }
    if ($action === 'delete' && $id) {
        $ids = location_ids_with_children($id);
        $in = implode(',', $ids);
        q("UPDATE listings SET location_id = 0 WHERE location_id IN ($in)");
        q("DELETE FROM locations WHERE id IN ($in)");
        log_activity('location.delete', (string) (locations_all()[$id]['name'] ?? $id), 'location', $id);
        flash('success', 'Location deleted. Ads keep their location text.');
        redirect('admin/locations.php');
    }
}

$tree = location_tree();
$counts = [];
foreach (q_all('SELECT location_id, COUNT(*) AS n FROM listings WHERE location_id > 0 GROUP BY location_id') as $r) {
    $counts[(int) $r['location_id']] = (int) $r['n'];
}
$edit = input('edit') ? (locations_all()[(int) input('edit')] ?? null) : null;
$form = $edit ?? ['id' => 0, 'name' => '', 'parent_id' => (int) input('parent'), 'sort_order' => 0];
$mode = setting('location_input');

$adminActive = 'locations';
$pageTitle = 'Locations';
require APP_ROOT . '/includes/admin-top.php';
?>
<form method="post" class="card form-section mode-card">
  <?= csrf_field() ?><input type="hidden" name="action" value="mode">
  <h2><?= icon('map-pin') ?>How sellers enter a location</h2>
  <div class="db-choice">
    <label class="choice-card"><input type="radio" name="location_input" value="list" <?= $mode === 'list' ? 'checked' : '' ?> data-autosubmit>
      <span><strong>Pick from my list</strong><small>Cleaner data and accurate location filters. Add your cities and areas below.</small></span></label>
    <label class="choice-card"><input type="radio" name="location_input" value="text" <?= $mode !== 'list' ? 'checked' : '' ?> data-autosubmit>
      <span><strong>Type freely</strong><small>Sellers type any city or area. Simple, but filters match text only.</small></span></label>
  </div>
  <?php if ($mode === 'list' && !$tree): ?><p class="alert alert-warning"><?= icon('alert') ?><span>Your list is empty, so sellers still type freely until you add locations.</span></p><?php endif; ?>
</form>

<div class="admin-grid wide-left">
  <section class="card table-card">
    <div class="card-head"><h2>Cities & areas</h2><span class="muted small"><?= count(locations_all()) ?> total</span></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Name</th><th>Ads</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($tree as $id => $l): $sum = ($counts[$id] ?? 0) + array_sum(array_intersect_key($counts, $l['children'])); ?>
          <tr class="row-parent">
            <td><span class="cat-cell"><span class="cat-icon sm"><?= icon('map-pin') ?></span><strong><?= e($l['name']) ?></strong><small class="muted"><?= count($l['children']) ?> areas</small></span></td>
            <td><?= $sum ?></td>
            <td class="row-actions nowrap">
              <a class="icon-btn sm" href="?parent=<?= $id ?>#loc-form" aria-label="Add area" title="Add area"><?= icon('plus') ?></a>
              <a class="icon-btn sm" href="?edit=<?= $id ?>#loc-form" aria-label="Edit"><?= icon('edit') ?></a>
              <form method="post" style="display:inline" data-confirm="Delete <?= e($l['name']) ?> and its areas?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $id ?>"><button class="icon-btn sm danger" type="submit" aria-label="Delete"><?= icon('trash') ?></button></form>
            </td>
          </tr>
          <?php foreach ($l['children'] as $cid => $c): ?>
            <tr>
              <td><span class="cat-cell child"><?= icon('chevron-right') ?><?= e($c['name']) ?></span></td>
              <td><?= $counts[$cid] ?? 0 ?></td>
              <td class="row-actions nowrap">
                <a class="icon-btn sm" href="?edit=<?= $cid ?>#loc-form" aria-label="Edit"><?= icon('edit') ?></a>
                <form method="post" style="display:inline" data-confirm="Delete <?= e($c['name']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $cid ?>"><button class="icon-btn sm danger" type="submit" aria-label="Delete"><?= icon('trash') ?></button></form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
        <?php if (!$tree): ?><tr><td colspan="3" class="center muted">No locations yet. Use “Add many at once” to get started quickly.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <div>
    <form method="post" class="card form-section" id="loc-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
      <h2><?= $edit ? 'Edit location' : 'Add a location' ?></h2>
      <div class="field"><label for="ln">Name</label><input id="ln" name="name" value="<?= e($form['name']) ?>" required></div>
      <div class="field"><label for="lp">Inside</label>
        <select id="lp" name="parent_id"><option value="0">— Nothing (it's a city / region) —</option>
          <?php foreach ($tree as $id => $l): if ($id === (int) $form['id']) continue; ?><option value="<?= $id ?>" <?= (int) $form['parent_id'] === $id ? 'selected' : '' ?>><?= e($l['name']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="field"><label for="lo">Order</label><input id="lo" type="number" name="sort_order" value="<?= (int) $form['sort_order'] ?>"></div>
      <div class="btn-row"><?php if ($edit): ?><a class="btn btn-ghost" href="<?= e(url('admin/locations.php')) ?>">Cancel</a><?php endif; ?><button class="btn btn-primary" type="submit"><?= $edit ? 'Save' : 'Add location' ?></button></div>
    </form>

    <form method="post" class="card form-section">
      <?= csrf_field() ?><input type="hidden" name="action" value="bulk">
      <h2>Add many at once</h2>
      <p class="muted small">One city per line. Add areas after a <code>&gt;</code>, separated by commas.</p>
      <textarea name="lines" rows="6" class="mono" placeholder="Dhaka > Gulshan, Banani, Dhanmondi, Uttara&#10;Chittagong > Agrabad, Nasirabad&#10;Sylhet"></textarea>
      <div class="btn-row"><button class="btn btn-primary" type="submit"><?= icon('plus') ?>Add locations</button></div>
    </form>
  </div>
</div>
<?php require APP_ROOT . '/includes/admin-bottom.php';
