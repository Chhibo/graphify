<?php
require __DIR__ . '/includes/auth.php';

$type = ($_GET['type'] ?? 'trip') === 'activity' ? 'activity' : 'trip';
$self = 'admin/tours.php?type=' . $type;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf($self);
    $id = (int) post('id');
    $tour = db_one('SELECT * FROM ' . tbl('tours') . ' WHERE id = ?', [$id]);
    if ($tour) {
        switch (post('action')) {
            case 'delete':
                db_exec('DELETE FROM ' . tbl('tours') . ' WHERE id = ?', [$id]);
                delete_upload($tour['image']);
                flash('success', '"' . $tour['title'] . '" deleted. Existing bookings are kept.');
                break;
            case 'toggle':
                db_update('tours', ['status' => $tour['status'] === 'active' ? 'hidden' : 'active', 'updated_at' => now()], $id);
                flash('success', 'Visibility updated.');
                break;
            case 'feature':
                db_update('tours', ['is_featured' => $tour['is_featured'] ? 0 : 1, 'updated_at' => now()], $id);
                flash('success', 'Homepage setting updated.');
                break;
            case 'duplicate':
                unset($tour['id']);
                $tour['title'] .= ' (copy)';
                $tour['slug'] = unique_slug('tours', $tour['title']);
                $tour['status'] = 'hidden';
                $tour['created_at'] = $tour['updated_at'] = now();
                // Give the copy its own image file so deleting one never breaks the other.
                $src = APP_ROOT . '/uploads/' . basename((string) $tour['image']);
                if ($tour['image'] !== '' && !preg_match('#^(https?:)?//#i', $tour['image']) && is_file($src)) {
                    $copy = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . pathinfo($src, PATHINFO_EXTENSION);
                    $tour['image'] = @copy($src, APP_ROOT . '/uploads/' . $copy) ? $copy : '';
                }
                $newId = db_insert('tours', $tour);
                flash('success', 'Duplicated as a hidden draft.');
                redirect('admin/tour-edit.php?id=' . $newId);
        }
    }
    redirect($self);
}

$q = trim((string) ($_GET['q'] ?? ''));
$params = [$type];
$where = 't.type = ?';
if ($q !== '') {
    $where .= ' AND (t.title LIKE ? OR t.destination LIKE ?)';
    array_push($params, '%' . $q . '%', '%' . $q . '%');
}
$rows = db_all('SELECT t.*, c.name AS category_name, (SELECT COUNT(*) FROM ' . tbl('bookings') . ' b WHERE b.tour_id = t.id) AS bookings FROM ' . tbl('tours') . ' t LEFT JOIN ' . tbl('categories') . " c ON c.id = t.category_id WHERE $where ORDER BY t.sort_order, t.id", $params);
$label = $type === 'trip' ? 'Trips & Tours' : 'Activities';

admin_header($label, $type === 'trip' ? 'trips' : 'activities');
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <form method="get" class="flex gap-2">
        <input type="hidden" name="type" value="<?= $type ?>">
        <input class="inp !w-64" name="q" value="<?= e($q) ?>" placeholder="Search title or destination…">
        <button class="btn btn-light"><i class="fa-solid fa-magnifying-glass"></i></button>
    </form>
    <a href="<?= e(url('admin/tour-edit.php?type=' . $type)) ?>" class="btn btn-accent"><i class="fa-solid fa-plus"></i> Add <?= $type === 'trip' ? 'trip' : 'activity' ?></a>
</div>
<div class="card overflow-x-auto">
    <table class="tbl">
        <thead><tr><th>Experience</th><th>Category</th><th>Price</th><th>Duration</th><th>Bookings</th><th>Homepage</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $t): ?>
            <tr>
                <td>
                    <div class="flex items-center space-x-3">
                        <img src="<?= e(img_url($t['image'])) ?>" alt="" class="w-14 h-10 rounded-lg object-cover">
                        <div><a href="<?= e(url('admin/tour-edit.php?id=' . $t['id'])) ?>" class="font-semibold hover:text-brand-600"><?= e($t['title']) ?></a><div class="text-xs text-slate-400"><i class="fa-solid fa-location-dot"></i> <?= e($t['destination']) ?></div></div>
                    </div>
                </td>
                <td><?= e($t['category_name'] ?? '—') ?></td>
                <td class="font-semibold whitespace-nowrap"><?= e(money($t['price'])) ?><?= $t['hide_price'] ? '<div class="text-[10px] font-bold text-amber-600 uppercase">Hidden: on request</div>' : '' ?></td>
                <td class="whitespace-nowrap"><?= e($t['duration']) ?></td>
                <td><a href="<?= e(url('admin/bookings.php?tour=' . $t['id'])) ?>" class="text-brand-600 font-bold"><?= (int) $t['bookings'] ?></a></td>
                <td>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="action" value="feature">
                        <button title="Toggle homepage" class="<?= $t['is_featured'] ? 'text-amber-500' : 'text-slate-300' ?> text-lg"><i class="fa-solid fa-star"></i></button></form>
                </td>
                <td>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="action" value="toggle">
                        <button class="inline-block px-2.5 py-1 rounded-lg text-xs font-bold <?= $t['status'] === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' ?>"><?= $t['status'] === 'active' ? 'Published' : 'Hidden' ?></button></form>
                </td>
                <td class="text-right whitespace-nowrap">
                    <a href="<?= e(url('tour.php?slug=' . urlencode($t['slug']))) ?>" target="_blank" class="btn btn-light !px-2.5" title="View"><i class="fa-solid fa-eye"></i></a>
                    <a href="<?= e(url('admin/tour-edit.php?id=' . $t['id'])) ?>" class="btn btn-light !px-2.5" title="Edit"><i class="fa-solid fa-pen"></i></a>
                    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="action" value="duplicate"><button class="btn btn-light !px-2.5" title="Duplicate"><i class="fa-solid fa-copy"></i></button></form>
                    <form method="post" class="inline" data-confirm="Delete this experience?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger !px-2.5" title="Delete"><i class="fa-solid fa-trash"></i></button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-slate-400 py-10">Nothing here yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php admin_footer(); ?>
