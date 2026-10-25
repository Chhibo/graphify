<?php
require __DIR__ . '/includes/auth.php';
require_permission('content');

$others = array_filter(languages(), fn ($l) => $l['code'] !== default_lang());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('admin/categories.php');
    $id = (int) post('id');
    $action = post('action');
    if ($action === 'delete' && $id) {
        db_exec('UPDATE ' . tbl('tours') . ' SET category_id = NULL WHERE category_id = ?', [$id]);
        db_exec('DELETE FROM ' . tbl('categories') . ' WHERE id = ?', [$id]);
        delete_translations('category', $id);
        flash('success', 'Category deleted.');
    } elseif ($action === 'save') {
        $name = mb_substr(post('name'), 0, 100);
        if ($name === '') {
            flash('error', 'Name is required.');
        } else {
            $data = ['name' => $name, 'slug' => unique_slug('categories', post('slug') ?: $name, $id), 'sort_order' => (int) post('sort_order')];
            if ($id) {
                db_update('categories', $data, $id);
            } else {
                $id = db_insert('categories', $data);
            }
            save_translations_from_post('category', $id);
            flash('success', 'Category saved.');
        }
    }
    redirect('admin/categories.php');
}

$rows = db_all('SELECT c.*, (SELECT COUNT(*) FROM ' . tbl('tours') . ' t WHERE t.category_id = c.id) AS n FROM ' . tbl('categories') . ' c ORDER BY c.sort_order, c.name');
admin_header('Categories', 'categories');
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-3">
        <?php foreach ($rows as $c): $tr = get_translations('category', (int) $c['id']); ?>
            <div class="card p-4 flex flex-wrap items-end gap-3">
                <form method="post" class="flex flex-wrap items-end gap-3 flex-1">
                    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <div class="flex-1 min-w-[140px]"><label class="lbl">Name</label><input class="inp" name="name" value="<?= e($c['name']) ?>"></div>
                    <?php foreach ($others as $l): ?>
                        <div class="flex-1 min-w-[120px]"><label class="lbl"><?= e($l['name']) ?></label><input class="inp" dir="<?= $l['dir'] ?>" name="tr[<?= e($l['code']) ?>][name]" value="<?= e($tr[$l['code']]['name'] ?? '') ?>"></div>
                    <?php endforeach; ?>
                    <div class="w-36"><label class="lbl">Slug</label><input class="inp" name="slug" value="<?= e($c['slug']) ?>"></div>
                    <div class="w-20"><label class="lbl">Order</label><input class="inp" type="number" name="sort_order" value="<?= (int) $c['sort_order'] ?>"></div>
                    <button class="btn btn-light" title="Save"><i class="fa-solid fa-check"></i></button>
                </form>
                <span class="text-xs text-slate-400 pb-3"><?= (int) $c['n'] ?> experiences</span>
                <form method="post" data-confirm="Delete this category? Experiences in it will become uncategorised."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn btn-danger !px-2.5"><i class="fa-solid fa-trash"></i></button></form>
            </div>
        <?php endforeach; ?>
        <?php if (!$rows): ?><div class="card p-8 text-center text-slate-400">No categories yet.</div><?php endif; ?>
    </div>
    <form method="post" class="card p-6 space-y-4 h-fit">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <h2 class="font-bold">Add category</h2>
        <?php f_input('Name', 'name', '', 'text', 'required placeholder="e.g., Safari"'); ?>
        <?php foreach ($others as $l): ?>
            <div><label class="lbl">Name in <?= e($l['name']) ?></label><input class="inp" dir="<?= $l['dir'] ?>" name="tr[<?= e($l['code']) ?>][name]"></div>
        <?php endforeach; ?>
        <?php f_input('Slug (optional)', 'slug'); ?>
        <?php f_input('Sort order', 'sort_order', '0', 'number'); ?>
        <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-plus"></i> Add category</button>
        <p class="help">Categories appear as "Travel Style" filters on the homepage search, the trip tabs and the trip planner.</p>
    </form>
</div>
<?php admin_footer(); ?>
