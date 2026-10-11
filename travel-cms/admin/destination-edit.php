<?php
require __DIR__ . '/includes/auth.php';
require_permission('content');

$id = (int) ($_GET['id'] ?? 0);
$dest = $id ? db_one('SELECT * FROM ' . tbl('destinations') . ' WHERE id = ?', [$id]) : null;
if ($id && !$dest) {
    flash('error', 'Destination not found.');
    redirect('admin/destinations.php');
}
$d = $dest ?: ['name' => '', 'slug' => '', 'country' => '', 'image' => '', 'short_description' => '', 'description' => '', 'is_featured' => 1, 'status' => 'active', 'sort_order' => 0, 'seo_title' => '', 'meta_description' => ''];
$self = 'admin/destination-edit.php' . ($id ? '?id=' . $id : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf($self);
    $data = [
        'name' => mb_substr(post('name'), 0, 150),
        'country' => mb_substr(post('country'), 0, 100),
        'short_description' => mb_substr(post('short_description'), 0, 500),
        'description' => post('description'),
        'is_featured' => post('is_featured') === '1' ? 1 : 0,
        'status' => post('status') === 'hidden' ? 'hidden' : 'active',
        'sort_order' => (int) post('sort_order'),
        'seo_title' => mb_substr(post('seo_title'), 0, 200),
        'meta_description' => mb_substr(post('meta_description'), 0, 320),
        'updated_at' => now(),
    ];
    if ($data['name'] === '') {
        flash('error', 'Name is required.');
        redirect($self);
    }
    $data['slug'] = unique_slug('destinations', post('slug') ?: $data['name'], $id);
    try {
        $data['image'] = resolve_image('image', $dest['image'] ?? '');
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
        redirect($self);
    }
    if ($dest) {
        db_update('destinations', $data, $id);
    } else {
        $data['created_at'] = now();
        $id = db_insert('destinations', $data);
    }
    // Link the selected trips/activities to this destination.
    $selected = array_map('intval', (array) ($_POST['tours'] ?? []));
    db_exec('UPDATE ' . tbl('tours') . ' SET destination_id = NULL WHERE destination_id = ?', [$id]);
    if ($selected) {
        db_exec('UPDATE ' . tbl('tours') . ' SET destination_id = ? WHERE id IN (' . implode(',', array_fill(0, count($selected), '?')) . ')', array_merge([$id], $selected));
    }
    flash('success', 'Destination saved.');
    redirect('admin/destination-edit.php?id=' . $id);
}

$tours = db_all('SELECT t.id, t.type, t.title, t.destination_id, d.name AS dest_name FROM ' . tbl('tours') . ' t LEFT JOIN ' . tbl('destinations') . ' d ON d.id = t.destination_id ORDER BY t.type DESC, t.title');
admin_header($dest ? 'Edit: ' . $dest['name'] : 'New destination', 'destinations');
?>
<a href="<?= e(url('admin/destinations.php')) ?>" class="text-xs font-bold text-slate-500 hover:text-brand-600"><i class="fa-solid fa-arrow-left mr-1"></i> All destinations</a>
<form method="post" enctype="multipart/form-data" class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-3">
    <?= csrf_field() ?>
    <div class="xl:col-span-2 space-y-6">
        <div class="card p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?php f_input('Name', 'name', $d['name'], 'text', 'required placeholder="e.g., Bali"'); ?>
                <?php f_input('Country', 'country', $d['country'], 'text', 'placeholder="e.g., Indonesia"'); ?>
            </div>
            <?php f_textarea('Short description (shown on the banner)', 'short_description', $d['short_description'], 2, '', 'maxlength="500"'); ?>
            <?php f_textarea('Full description', 'description', $d['description'], 8, 'Plain text (blank line = new paragraph) or basic HTML. Tell visitors what makes this place special.'); ?>
        </div>
        <div class="card p-6">
            <h2 class="font-bold mb-1">Trips & activities in this destination</h2>
            <p class="help mb-3">Tick the experiences to show on this destination's page. You can also choose the destination when editing a trip.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-80 overflow-y-auto">
                <?php foreach ($tours as $t): $checked = $dest && (int) $t['destination_id'] === (int) $dest['id']; ?>
                    <label class="flex items-start gap-2 text-sm bg-slate-50 rounded-lg px-3 py-2 cursor-pointer">
                        <input type="checkbox" name="tours[]" value="<?= (int) $t['id'] ?>" class="mt-1" <?= $checked ? 'checked' : '' ?>>
                        <span><?= e($t['title']) ?> <span class="text-xs text-slate-400"><?= $t['type'] === 'trip' ? 'Trip' : 'Activity' ?><?= !$checked && $t['dest_name'] ? ' · now in ' . e($t['dest_name']) : '' ?></span></span>
                    </label>
                <?php endforeach; ?>
                <?php if (!$tours): ?><p class="text-sm text-slate-400">No trips yet.</p><?php endif; ?>
            </div>
        </div>
        <div class="card p-6 space-y-4">
            <h2 class="font-bold">Search engines (SEO)</h2>
            <?php f_input('SEO title', 'seo_title', $d['seo_title'], 'text', 'maxlength="200"', 'Leave empty for "Name Tours & Activities | Site name".'); ?>
            <?php f_textarea('Meta description', 'meta_description', $d['meta_description'], 2, 'Leave empty to use the short description.', 'maxlength="320"'); ?>
        </div>
    </div>
    <div class="space-y-6">
        <div class="card p-6 space-y-4">
            <?php f_select('Status', 'status', ['active' => 'Published', 'hidden' => 'Hidden (draft)'], $d['status']); ?>
            <?php f_check('Show on homepage & footer', 'is_featured', (bool) $d['is_featured']); ?>
            <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-floppy-disk"></i> Save</button>
            <?php if ($dest): ?><a href="<?= e(url('destination.php?slug=' . urlencode($dest['slug']))) ?>" target="_blank" class="btn btn-light w-full justify-center"><i class="fa-solid fa-eye"></i> View on site</a><?php endif; ?>
        </div>
        <div class="card p-6 space-y-4"><?php f_image('Cover image', 'image', $d['image']); ?></div>
        <div class="card p-6 space-y-4">
            <?php f_input('URL slug', 'slug', $d['slug'], 'text', '', 'Leave empty to generate from the name.'); ?>
            <?php f_input('Sort order', 'sort_order', $d['sort_order'], 'number', '', 'Lower numbers are shown first.'); ?>
        </div>
    </div>
</form>
<?php admin_footer(); ?>
