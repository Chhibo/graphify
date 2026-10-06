<?php
require __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('admin/posts.php');
    $post = db_one('SELECT * FROM ' . tbl('posts') . ' WHERE id = ?', [(int) post('id')]);
    if ($post && post('action') === 'delete') {
        db_exec('DELETE FROM ' . tbl('posts') . ' WHERE id = ?', [$post['id']]);
        delete_upload($post['image']);
        flash('success', 'Post deleted.');
    }
    redirect('admin/posts.php');
}

$rows = db_all('SELECT * FROM ' . tbl('posts') . ' ORDER BY published_at DESC');
admin_header('Blog posts', 'posts');
?>
<div class="flex justify-end mb-4"><a href="<?= e(url('admin/post-edit.php')) ?>" class="btn btn-accent"><i class="fa-solid fa-plus"></i> New post</a></div>
<div class="card overflow-x-auto">
    <table class="tbl">
        <thead><tr><th>Title</th><th>Tag</th><th>Author</th><th>Published</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $p): ?>
            <tr>
                <td><div class="flex items-center space-x-3"><img src="<?= e(img_url($p['image'])) ?>" alt="" class="w-14 h-10 rounded-lg object-cover"><a href="<?= e(url('admin/post-edit.php?id=' . $p['id'])) ?>" class="font-semibold hover:text-brand-600"><?= e($p['title']) ?></a></div></td>
                <td><?= e($p['tag']) ?></td>
                <td><?= e($p['author']) ?></td>
                <td class="whitespace-nowrap"><?= e(format_date($p['published_at'])) ?></td>
                <td><span class="px-2.5 py-1 rounded-lg text-xs font-bold <?= $p['status'] === 'published' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' ?>"><?= $p['status'] === 'published' ? (strtotime($p['published_at']) > time() ? 'Scheduled' : 'Published') : 'Draft' ?></span></td>
                <td class="text-right whitespace-nowrap">
                    <a href="<?= e(url('post.php?slug=' . urlencode($p['slug']))) ?>" target="_blank" class="btn btn-light !px-2.5"><i class="fa-solid fa-eye"></i></a>
                    <a href="<?= e(url('admin/post-edit.php?id=' . $p['id'])) ?>" class="btn btn-light !px-2.5"><i class="fa-solid fa-pen"></i></a>
                    <form method="post" class="inline" data-confirm="Delete this post?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger !px-2.5"><i class="fa-solid fa-trash"></i></button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-slate-400 py-10">No posts yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php admin_footer(); ?>
