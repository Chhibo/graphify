<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_login();
$uid = (int) $me['id'];

if (is_post()) {
    $id = (int) input('id');
    $l = q_one('SELECT * FROM listings WHERE id = ? AND user_id = ?', [$id, $uid]);
    if (!$l) {
        flash('error', 'Ad not found.');
        redirect('dashboard/listings.php');
    }
    switch (input('action')) {
        case 'delete':
            delete_listing($id);
            flash('success', 'Ad deleted.');
            break;
        case 'sold':
            q("UPDATE listings SET status = 'sold', updated_at = ? WHERE id = ?", [now(), $id]);
            flash('success', 'Marked as sold. Congratulations! 🎉');
            break;
        case 'relist':
        case 'renew':
            if ($l['status'] === 'rejected' || $l['status'] === 'pending') {
                flash('error', 'Edit and resubmit this ad instead.');
                break;
            }
            $status = setting('require_approval') === '1' && $me['role'] !== 'admin' && $l['status'] !== 'active' ? 'pending' : 'active';
            q('UPDATE listings SET status = ?, expires_at = ?, updated_at = ? WHERE id = ?', [$status, new_expiry(), now(), $id]);
            flash('success', $status === 'pending' ? 'Ad sent for review.' : 'Your ad is live again.');
            break;
    }
    back('dashboard/listings.php');
}

$tab = input('status', 'all');
$tabs = ['all' => 'All', 'active' => 'Active', 'pending' => 'Pending', 'sold' => 'Sold', 'expired' => 'Expired', 'rejected' => 'Rejected'];
$counts = ['all' => 0];
foreach (q_all('SELECT status, COUNT(*) AS n FROM listings WHERE user_id = ? GROUP BY status', [$uid]) as $r) {
    $counts[$r['status']] = (int) $r['n'];
    $counts['all'] += (int) $r['n'];
}
$where = 'l.user_id = ?';
$params = [$uid];
if (isset($tabs[$tab]) && $tab !== 'all') {
    $where .= ' AND l.status = ?';
    $params[] = $tab;
}
$total = (int) q_val("SELECT COUNT(*) FROM listings l WHERE $where", $params);
[$page, $pages, $offset] = paginate($total, 10, (int) input('page', '1'));
$rows = q_all(listing_select() . " WHERE $where ORDER BY l.created_at DESC LIMIT 10 OFFSET $offset", $params);

$dashActive = 'listings';
$pageTitle = 'My ads';
require APP_ROOT . '/includes/dash-top.php';
?>
<div class="dash-head">
  <div><h1>My ads</h1><p class="muted">Edit, renew, mark as sold or remove your ads.</p></div>
  <a class="btn btn-primary" href="<?= e(url('dashboard/edit.php')) ?>"><?= icon('plus') ?>Post a new ad</a>
</div>

<nav class="tabs">
  <?php foreach ($tabs as $k => $lbl): ?>
    <a href="?status=<?= $k ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= e($lbl) ?><span><?= $counts[$k] ?? 0 ?></span></a>
  <?php endforeach; ?>
</nav>

<?php if ($rows): ?>
  <div class="manage-list">
    <?php foreach ($rows as $l): ?>
      <article class="card manage-item">
        <a class="mi-thumb" href="<?= e(listing_url($l)) ?>"><?php if ($l['cover']): ?><img src="<?= e(upload_url($l['cover'])) ?>" alt="" loading="lazy"><?php else: ?><?= icon($l['category_icon'] ?: 'image') ?><?php endif; ?></a>
        <div class="mi-body">
          <div class="mi-top"><?= status_badge($l['status']) ?><?php if ($l['featured']): ?><span class="badge badge-accent"><?= icon('zap') ?>Featured</span><?php endif; ?></div>
          <h3><a href="<?= e(listing_url($l)) ?>"><?= e($l['title']) ?></a></h3>
          <div class="mi-price"><?= e(listing_price($l)) ?></div>
          <div class="meta-row small">
            <span><?= icon('folder') ?><?= e($l['category_name'] ?? '—') ?></span>
            <span><?= icon('eye') ?><?= number_format((int) $l['views']) ?> views</span>
            <span><?= icon('calendar') ?>Posted <?= e(format_date($l['created_at'])) ?></span>
            <?php if ($l['expires_at'] && $l['status'] === 'active'): ?><span><?= icon('clock') ?>Expires <?= e(format_date($l['expires_at'])) ?></span><?php endif; ?>
          </div>
          <?php if ($l['status'] === 'rejected' && $l['reject_reason']): ?><p class="mi-note"><?= icon('info') ?>Rejected: <?= e($l['reject_reason']) ?></p><?php endif; ?>
        </div>
        <div class="mi-actions">
          <a class="btn btn-ghost btn-sm" href="<?= e(url('dashboard/edit.php?id=' . $l['id'])) ?>"><?= icon('edit') ?>Edit</a>
          <?php if ($l['status'] === 'active'): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $l['id'] ?>"><input type="hidden" name="action" value="sold"><button class="btn btn-ghost btn-sm" type="submit"><?= icon('check-circle') ?>Mark sold</button></form>
          <?php elseif (in_array($l['status'], ['sold', 'expired'], true)): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $l['id'] ?>"><input type="hidden" name="action" value="renew"><button class="btn btn-ghost btn-sm" type="submit"><?= icon('refresh') ?><?= $l['status'] === 'sold' ? 'Relist' : 'Renew' ?></button></form>
          <?php endif; ?>
          <form method="post" data-confirm="Delete this ad permanently? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $l['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-ghost btn-sm danger" type="submit"><?= icon('trash') ?>Delete</button></form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?= pagination_links($page, $pages) ?>
<?php else: ?>
  <div class="card empty-state"><?= icon('package') ?><h3>No ads here</h3><p>Ads you post will show up in this list.</p><a class="btn btn-primary" href="<?= e(url('dashboard/edit.php')) ?>">Post an ad</a></div>
<?php endif; ?>
<?php require APP_ROOT . '/includes/dash-bottom.php';
