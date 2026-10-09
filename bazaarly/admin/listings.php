<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

if (is_post()) {
    $ids = array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])));
    $bulk = input('bulk');
    if ($ids && $bulk) {
        $in = implode(',', $ids);
        switch ($bulk) {
            case 'approve':
                q("UPDATE listings SET status = 'active', reject_reason = '', updated_at = ?, expires_at = CASE WHEN expires_at IS NULL OR expires_at <= ? THEN ? ELSE expires_at END WHERE id IN ($in)", [now(), now(), new_expiry()]);
                flash('success', count($ids) . ' ad(s) approved.');
                break;
            case 'reject':
                q("UPDATE listings SET status = 'rejected', reject_reason = ?, updated_at = ? WHERE id IN ($in)", [input('reason') ?: 'Does not follow our posting rules.', now()]);
                flash('success', count($ids) . ' ad(s) rejected.');
                break;
            case 'feature':
                q("UPDATE listings SET featured = 1 WHERE id IN ($in)");
                flash('success', count($ids) . ' ad(s) featured.');
                break;
            case 'unfeature':
                q("UPDATE listings SET featured = 0 WHERE id IN ($in)");
                flash('success', count($ids) . ' ad(s) un-featured.');
                break;
            case 'delete':
                foreach ($ids as $id) {
                    delete_listing($id);
                }
                flash('success', count($ids) . ' ad(s) deleted.');
                break;
        }
    } else {
        flash('warning', 'Select at least one ad first.');
    }
    back('admin/listings.php');
}

$status = input('status');
$search = input('q');
$cat = (int) input('category');
$where = '1 = 1';
$params = [];
if (in_array($status, ['active', 'pending', 'rejected', 'sold', 'expired'], true)) {
    $where .= ' AND l.status = ?';
    $params[] = $status;
} elseif ($status === 'featured') {
    $where .= ' AND l.featured = 1';
}
if ($search !== '') {
    $where .= ' AND (l.title LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR l.id = ?)';
    array_push($params, "%$search%", "%$search%", "%$search%", (int) $search);
}
if ($cat) {
    $where .= ' AND l.category_id IN (' . implode(',', category_ids_with_children($cat)) . ')';
}
if (input('user')) {
    $where .= ' AND l.user_id = ?';
    $params[] = (int) input('user');
}
$total = (int) q_val("SELECT COUNT(*) FROM listings l JOIN users u ON u.id = l.user_id WHERE $where", $params);
[$page, $pages, $offset] = paginate($total, 20, (int) input('page', '1'));
$rows = q_all(listing_select() . " WHERE $where ORDER BY l.created_at DESC LIMIT 20 OFFSET $offset", $params);

$counts = ['' => (int) q_val('SELECT COUNT(*) FROM listings')];
foreach (q_all('SELECT status, COUNT(*) AS n FROM listings GROUP BY status') as $r) {
    $counts[$r['status']] = (int) $r['n'];
}
$counts['featured'] = (int) q_val('SELECT COUNT(*) FROM listings WHERE featured = 1');

$adminActive = 'listings';
$pageTitle = 'Ads';
require APP_ROOT . '/includes/admin-top.php';
?>
<nav class="tabs">
  <?php foreach (['' => 'All', 'pending' => 'Pending', 'active' => 'Active', 'featured' => 'Featured', 'sold' => 'Sold', 'expired' => 'Expired', 'rejected' => 'Rejected'] as $k => $lbl): ?>
    <a href="?status=<?= $k ?>" class="<?= $status === $k ? 'on' : '' ?>"><?= e($lbl) ?><span><?= $counts[$k] ?? 0 ?></span></a>
  <?php endforeach; ?>
</nav>

<form class="toolbar" method="get">
  <input type="hidden" name="status" value="<?= e($status) ?>">
  <div class="input-icon grow"><?= icon('search') ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search title, seller, email or ID"></div>
  <select name="category" data-autosubmit><option value="">All categories</option><?= category_options($cat) ?></select>
  <button class="btn btn-ghost" type="submit">Filter</button>
</form>

<form method="post" class="card table-card" data-bulk-form>
  <?= csrf_field() ?>
  <div class="bulk-bar">
    <label class="check"><input type="checkbox" data-check-all><span>Select all</span></label>
    <select name="bulk" required>
      <option value="">Bulk action…</option>
      <option value="approve">Approve</option>
      <option value="reject">Reject</option>
      <option value="feature">Mark featured</option>
      <option value="unfeature">Remove featured</option>
      <option value="delete">Delete</option>
    </select>
    <input type="text" name="reason" placeholder="Rejection reason (optional)" class="reason-input">
    <button class="btn btn-primary btn-sm" type="submit" data-confirm-bulk>Apply</button>
    <span class="muted small push"><?= number_format($total) ?> ads</span>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th></th><th>Ad</th><th>Seller</th><th>Price</th><th>Status</th><th>Views</th><th>Posted</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $l): ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= (int) $l['id'] ?>" aria-label="Select ad"></td>
          <td><div class="cell-ad">
            <span class="mini-thumb"><?php if ($l['cover']): ?><img src="<?= e(upload_url($l['cover'])) ?>" alt="" loading="lazy"><?php else: ?><?= icon($l['category_icon'] ?: 'image') ?><?php endif; ?></span>
            <div><a href="<?= e(url('admin/listing-edit.php?id=' . $l['id'])) ?>"><strong><?= e(excerpt($l['title'], 60)) ?></strong></a>
              <small class="muted">#<?= (int) $l['id'] ?> · <?= e($l['category_name'] ?? 'Uncategorised') ?><?php if ($l['featured']): ?> · <span class="text-accent">★ Featured</span><?php endif; ?></small></div></div></td>
          <td><a href="<?= e(url('admin/user-edit.php?id=' . $l['user_id'])) ?>"><?= e($l['seller_name']) ?></a></td>
          <td class="nowrap"><?= e(listing_price($l)) ?></td>
          <td><?= status_badge($l['status']) ?></td>
          <td><?= number_format((int) $l['views']) ?></td>
          <td class="nowrap muted small"><?= e(format_date($l['created_at'])) ?></td>
          <td class="nowrap row-actions">
            <a class="icon-btn sm" href="<?= e(listing_url($l)) ?>" target="_blank" aria-label="View"><?= icon('eye') ?></a>
            <a class="icon-btn sm" href="<?= e(url('admin/listing-edit.php?id=' . $l['id'])) ?>" aria-label="Edit"><?= icon('edit') ?></a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="8" class="center muted">No ads found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</form>
<?= pagination_links($page, $pages) ?>
<?php require APP_ROOT . '/includes/admin-bottom.php';
