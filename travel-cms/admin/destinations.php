<?php
require __DIR__ . '/includes/auth.php';
require_permission('content');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('admin/destinations.php');
    $dest = db_one('SELECT * FROM ' . tbl('destinations') . ' WHERE id = ?', [(int) post('id')]);
    if ($dest) {
        switch (post('action')) {
            case 'delete':
                db_exec('UPDATE ' . tbl('tours') . ' SET destination_id = NULL WHERE destination_id = ?', [$dest['id']]);
                db_exec('DELETE FROM ' . tbl('destinations') . ' WHERE id = ?', [$dest['id']]);
                delete_upload($dest['image']);
                flash('success', '"' . $dest['name'] . '" deleted. Its trips were kept.');
                break;
            case 'toggle':
                db_update('destinations', ['status' => $dest['status'] === 'active' ? 'hidden' : 'active', 'updated_at' => now()], (int) $dest['id']);
                flash('success', 'Visibility updated.');
                break;
            case 'feature':
                db_update('destinations', ['is_featured' => $dest['is_featured'] ? 0 : 1, 'updated_at' => now()], (int) $dest['id']);
                flash('success', 'Homepage setting updated.');
                break;
        }
    }
    redirect('admin/destinations.php');
}

$rows = db_all('SELECT d.*, (SELECT COUNT(*) FROM ' . tbl('tours') . ' t WHERE t.destination_id = d.id) AS tours_count FROM ' . tbl('destinations') . ' d ORDER BY d.sort_order, d.name');
admin_header('Destinations', 'destinations');
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <p class="text-sm text-slate-500">Each destination gets its own page listing its trips and activities. Starred destinations appear on the homepage and in the footer.</p>
    <a href="<?= e(url('admin/destination-edit.php')) ?>" class="btn btn-accent"><i class="fa-solid fa-plus"></i> Add destination</a>
</div>
<div class="card overflow-x-auto">
    <table class="tbl">
        <thead><tr><th>Destination</th><th>Country</th><th>Experiences</th><th>Homepage</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $d): ?>
            <tr>
                <td>
                    <div class="flex items-center space-x-3">
                        <img src="<?= e(img_url($d['image'])) ?>" alt="" class="w-14 h-10 rounded-lg object-cover">
                        <a href="<?= e(url('admin/destination-edit.php?id=' . $d['id'])) ?>" class="font-semibold hover:text-brand-600"><?= e($d['name']) ?></a>
                    </div>
                </td>
                <td><?= e($d['country']) ?></td>
                <td><?= (int) $d['tours_count'] ?></td>
                <td>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $d['id'] ?>"><input type="hidden" name="action" value="feature">
                        <button title="Toggle homepage" class="<?= $d['is_featured'] ? 'text-amber-500' : 'text-slate-300' ?> text-lg"><i class="fa-solid fa-star"></i></button></form>
                </td>
                <td>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $d['id'] ?>"><input type="hidden" name="action" value="toggle">
                        <button class="inline-block px-2.5 py-1 rounded-lg text-xs font-bold <?= $d['status'] === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' ?>"><?= $d['status'] === 'active' ? 'Published' : 'Hidden' ?></button></form>
                </td>
                <td class="text-right whitespace-nowrap">
                    <a href="<?= e(url('destination.php?slug=' . urlencode($d['slug']))) ?>" target="_blank" class="btn btn-light !px-2.5" title="View"><i class="fa-solid fa-eye"></i></a>
                    <a href="<?= e(url('admin/destination-edit.php?id=' . $d['id'])) ?>" class="btn btn-light !px-2.5" title="Edit"><i class="fa-solid fa-pen"></i></a>
                    <form method="post" class="inline" data-confirm="Delete this destination? Its trips will be kept but unlinked."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $d['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger !px-2.5" title="Delete"><i class="fa-solid fa-trash"></i></button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-slate-400 py-10">No destinations yet. Add your first one!</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php admin_footer(); ?>
