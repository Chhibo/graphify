<?php
require __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('admin/pages.php');
    if (post('action') === 'delete') {
        db_exec('DELETE FROM ' . tbl('pages') . ' WHERE id = ?', [(int) post('id')]);
        flash('success', 'Page deleted.');
    }
    redirect('admin/pages.php');
}
$rows = db_all('SELECT * FROM ' . tbl('pages') . ' ORDER BY title');
admin_header('Pages', 'pages');
?>
<div class="flex justify-between items-center mb-4">
    <p class="text-sm text-slate-500">Static pages such as About, Terms or Privacy. Pages marked "footer" are linked in the site footer.</p>
    <a href="<?= e(url('admin/page-edit.php')) ?>" class="btn btn-accent"><i class="fa-solid fa-plus"></i> New page</a>
</div>
<div class="card overflow-x-auto">
    <table class="tbl">
        <thead><tr><th>Title</th><th>URL</th><th>Footer</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $p): ?>
            <tr>
                <td class="font-semibold"><a href="<?= e(url('admin/page-edit.php?id=' . $p['id'])) ?>" class="hover:text-brand-600"><?= e($p['title']) ?></a></td>
                <td class="text-xs text-slate-500">page.php?slug=<?= e($p['slug']) ?></td>
                <td><?= $p['show_in_footer'] ? '<i class="fa-solid fa-check text-emerald-500"></i>' : '—' ?></td>
                <td><span class="px-2.5 py-1 rounded-lg text-xs font-bold <?= $p['status'] === 'published' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' ?>"><?= e(ucfirst($p['status'])) ?></span></td>
                <td class="text-right whitespace-nowrap">
                    <a href="<?= e(url('page.php?slug=' . urlencode($p['slug']))) ?>" target="_blank" class="btn btn-light !px-2.5"><i class="fa-solid fa-eye"></i></a>
                    <a href="<?= e(url('admin/page-edit.php?id=' . $p['id'])) ?>" class="btn btn-light !px-2.5"><i class="fa-solid fa-pen"></i></a>
                    <form method="post" class="inline" data-confirm="Delete this page?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger !px-2.5"><i class="fa-solid fa-trash"></i></button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-slate-400 py-10">No pages yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php admin_footer(); ?>
