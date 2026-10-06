<?php
require __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('admin/categories.php');
    $id = (int) post('id');
    $action = post('action');
    if ($action === 'delete' && $id) {
        db_exec('UPDATE ' . tbl('tours') . ' SET category_id = NULL WHERE category_id = ?', [$id]);
        db_exec('DELETE FROM ' . tbl('categories') . ' WHERE id = ?', [$id]);
        flash('success', 'Category deleted.');
    } elseif ($action === 'save') {
        $name = mb_substr(post('name'), 0, 100);
        if ($name === '') {
            flash('error', 'Name is required.');
        } else {
            $data = ['name' => $name, 'slug' => unique_slug('categories', post('slug') ?: $name, $id), 'sort_order' => (int) post('sort_order')];
            $id ? db_update('categories', $data, $id) : db_insert('categories', $data);
            flash('success', 'Category saved.');
        }
    }
    redirect('admin/categories.php');
}

$rows = db_all('SELECT c.*, (SELECT COUNT(*) FROM ' . tbl('tours') . ' t WHERE t.category_id = c.id) AS n FROM ' . tbl('categories') . ' c ORDER BY c.sort_order, c.name');
admin_header('Categories', 'categories');
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="card overflow-x-auto lg:col-span-2">
        <table class="tbl">
            <thead><tr><th>Name</th><th>Slug</th><th>Order</th><th>Experiences</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $c): ?>
                <tr>
                    <td colspan="3">
                        <form method="post" class="grid grid-cols-3 gap-2">
                            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <input class="inp" name="name" value="<?= e($c['name']) ?>">
                            <input class="inp" name="slug" value="<?= e($c['slug']) ?>">
                            <div class="flex gap-2"><input class="inp" type="number" name="sort_order" value="<?= (int) $c['sort_order'] ?>"><button class="btn btn-light" title="Save"><i class="fa-solid fa-check"></i></button></div>
                        </form>
                    </td>
                    <td><?= (int) $c['n'] ?></td>
                    <td class="text-right">
                        <form method="post" data-confirm="Delete this category? Experiences in it will become uncategorised."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn btn-danger !px-2.5"><i class="fa-solid fa-trash"></i></button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-slate-400 py-8">No categories yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <form method="post" class="card p-6 space-y-4 h-fit">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <h2 class="font-bold">Add category</h2>
        <?php f_input('Name', 'name', '', 'text', 'required placeholder="e.g., Safari"'); ?>
        <?php f_input('Slug (optional)', 'slug'); ?>
        <?php f_input('Sort order', 'sort_order', '0', 'number'); ?>
        <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-plus"></i> Add category</button>
        <p class="help">Categories appear as "Travel Style" filters on the homepage search and the trip tabs.</p>
    </form>
</div>
<?php admin_footer(); ?>
