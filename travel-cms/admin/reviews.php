<?php
require __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('admin/reviews.php');
    $id = (int) post('id');
    $review = $id ? db_one('SELECT * FROM ' . tbl('reviews') . ' WHERE id = ?', [$id]) : null;
    switch (post('action')) {
        case 'approve':
        case 'unapprove':
            if ($review) {
                db_exec('UPDATE ' . tbl('reviews') . ' SET status = ? WHERE id = ?', [post('action') === 'approve' ? 'approved' : 'pending', $id]);
                flash('success', 'Review updated.');
            }
            break;
        case 'delete':
            if ($review) {
                db_exec('DELETE FROM ' . tbl('reviews') . ' WHERE id = ?', [$id]);
                delete_upload($review['avatar']);
                flash('success', 'Review deleted.');
            }
            break;
        case 'save':
            $data = [
                'name' => mb_substr(post('name'), 0, 150),
                'trip' => mb_substr(post('trip'), 0, 200),
                'rating' => max(1, min(5, (int) post('rating'))),
                'content' => post('content'),
                'status' => post('status') === 'approved' ? 'approved' : 'pending',
            ];
            if ($data['name'] === '' || $data['content'] === '') {
                flash('error', 'Name and review text are required.');
                break;
            }
            try {
                $data['avatar'] = resolve_image('avatar', $review['avatar'] ?? '');
            } catch (RuntimeException $ex) {
                flash('error', $ex->getMessage());
                break;
            }
            if ($review) {
                db_update('reviews', $data, $id);
            } else {
                db_insert('reviews', $data + ['created_at' => now(), 'ip_address' => client_ip()]);
            }
            flash('success', 'Review saved.');
            break;
    }
    redirect($_SERVER['HTTP_REFERER'] ?? 'admin/reviews.php');
}

$status = in_array($_GET['status'] ?? '', ['pending', 'approved'], true) ? $_GET['status'] : '';
$rows = db_all('SELECT * FROM ' . tbl('reviews') . ($status ? ' WHERE status = ?' : '') . ' ORDER BY status = \'pending\' DESC, created_at DESC', $status ? [$status] : []);
$edit = isset($_GET['edit']) ? db_one('SELECT * FROM ' . tbl('reviews') . ' WHERE id = ?', [(int) $_GET['edit']]) : null;
$r = $edit ?: ['id' => 0, 'name' => '', 'trip' => '', 'rating' => 5, 'content' => '', 'avatar' => '', 'status' => 'approved'];

admin_header('Reviews', 'reviews');
?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-3">
        <div class="flex gap-2 text-xs font-bold">
            <?php foreach (['' => 'All', 'pending' => 'Awaiting approval', 'approved' => 'Approved'] as $k => $label): ?>
                <a href="?status=<?= $k ?>" class="px-3 py-2 rounded-xl <?= $status === $k ? 'bg-slate-900 text-white' : 'bg-white text-slate-600' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
        <?php foreach ($rows as $row): ?>
            <div class="card p-5 <?= $row['status'] === 'pending' ? 'border-l-4 !border-l-amber-400' : '' ?>">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-bold"><?= e($row['name']) ?> <span class="text-amber-400 text-xs ml-1"><?= stars((int) $row['rating']) ?></span></p>
                        <p class="text-xs text-brand-600"><?= e($row['trip']) ?> · <span class="text-slate-400"><?= e(time_ago($row['created_at'])) ?></span></p>
                    </div>
                    <div class="flex gap-1">
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                            <?php if ($row['status'] === 'pending'): ?>
                                <button name="action" value="approve" class="btn btn-primary !py-1.5"><i class="fa-solid fa-check"></i> Approve</button>
                            <?php else: ?>
                                <button name="action" value="unapprove" class="btn btn-light !py-1.5"><i class="fa-solid fa-eye-slash"></i> Hide</button>
                            <?php endif; ?>
                        </form>
                        <a href="?edit=<?= (int) $row['id'] ?>" class="btn btn-light !py-1.5 !px-2.5"><i class="fa-solid fa-pen"></i></a>
                        <form method="post" data-confirm="Delete this review?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button name="action" value="delete" class="btn btn-danger !py-1.5 !px-2.5"><i class="fa-solid fa-trash"></i></button></form>
                    </div>
                </div>
                <p class="text-sm text-slate-600 mt-3 italic">"<?= e($row['content']) ?>"</p>
            </div>
        <?php endforeach; ?>
        <?php if (!$rows): ?><div class="card p-10 text-center text-slate-400">No reviews.</div><?php endif; ?>
    </div>
    <form method="post" enctype="multipart/form-data" class="card p-6 space-y-4 h-fit">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <h2 class="font-bold"><?= $edit ? 'Edit review' : 'Add a review' ?></h2>
        <?php f_input('Name', 'name', $r['name'], 'text', 'required'); ?>
        <?php f_input('Trip / activity', 'trip', $r['trip']); ?>
        <?php f_select('Rating', 'rating', [5 => '★★★★★ 5', 4 => '★★★★ 4', 3 => '★★★ 3', 2 => '★★ 2', 1 => '★ 1'], $r['rating']); ?>
        <?php f_textarea('Review', 'content', $r['content'], 4, '', 'required'); ?>
        <?php f_select('Status', 'status', ['approved' => 'Approved (visible)', 'pending' => 'Pending (hidden)'], $r['status']); ?>
        <?php f_image('Avatar photo (optional)', 'avatar', $r['avatar']); ?>
        <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-floppy-disk"></i> Save review</button>
        <?php if ($edit): ?><a href="<?= e(url('admin/reviews.php')) ?>" class="btn btn-light w-full justify-center">Cancel</a><?php endif; ?>
    </form>
</div>
<?php admin_footer(); ?>
