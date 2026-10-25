<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

if (is_post()) {
    $shop = q_one('SELECT s.*, u.name AS owner, u.email FROM shops s JOIN users u ON u.id = s.user_id WHERE s.id = ?', [(int) input('id')]);
    if ($shop) {
        $action = input('action');
        if ($action === 'approve' || $action === 'reject') {
            $status = $action === 'approve' ? 'active' : 'rejected';
            $reason = $action === 'reject' ? (input('reason') ?: 'Please add more details about your business.') : '';
            q('UPDATE shops SET status = ?, reject_reason = ?, updated_at = ? WHERE id = ?', [$status, $reason, now(), $shop['id']]);
            send_template('shop_status', $shop['email'], ['name' => $shop['owner'], 'shop_name' => $shop['name'],
                'status' => $status === 'active' ? 'approved' : 'not approved', 'reason' => $reason,
                'action_url' => $status === 'active' ? abs_url(shop_url($shop)) : full_url('dashboard/shop.php')]);
            log_activity('shop.' . $action, $shop['name'] . ($reason ? " ($reason)" : ''), 'user', (int) $shop['user_id']);
            flash('success', $status === 'active' ? 'Shop approved.' : 'Shop rejected.');
        } elseif ($action === 'delete') {
            delete_upload($shop['logo']);
            delete_upload($shop['banner']);
            q('DELETE FROM shops WHERE id = ?', [$shop['id']]);
            log_activity('shop.delete', $shop['name'], 'user', (int) $shop['user_id']);
            flash('success', 'Shop deleted.');
        }
    }
    back('admin/shops.php');
}

$status = in_array(input('status'), ['pending', 'active', 'rejected'], true) ? input('status') : 'pending';
$counts = [];
foreach (q_all('SELECT status, COUNT(*) AS n FROM shops GROUP BY status') as $r) {
    $counts[$r['status']] = (int) $r['n'];
}
$rows = q_all("SELECT s.*, u.name AS owner, u.email, u.verified,
    (SELECT COUNT(*) FROM listings l WHERE l.user_id = s.user_id AND l.status = 'active') AS ads
    FROM shops s JOIN users u ON u.id = s.user_id WHERE s.status = ? ORDER BY s.updated_at DESC LIMIT 200", [$status]);

$adminActive = 'shops';
$pageTitle = 'Shops';
require APP_ROOT . '/includes/admin-top.php';
?>
<?php if (!shops_enabled()): ?><div class="alert alert-warning"><?= icon('info') ?><span>Shop pages are switched off. Turn them on in Settings → Ads & users.</span></div><?php endif; ?>
<nav class="tabs">
  <?php foreach (['pending' => 'Waiting for approval', 'active' => 'Live', 'rejected' => 'Rejected'] as $k => $lbl): ?>
    <a href="?status=<?= $k ?>" class="<?= $status === $k ? 'on' : '' ?>"><?= e($lbl) ?><span><?= $counts[$k] ?? 0 ?></span></a>
  <?php endforeach; ?>
</nav>
<?php if ($rows): ?>
  <div class="report-list">
    <?php foreach ($rows as $s): ?>
      <article class="card report-item shop-admin-item">
        <?php if ($s['logo']): ?><img class="shop-logo sm" src="<?= e(upload_url($s['logo'])) ?>" alt=""><?php else: ?><span class="shop-logo sm shop-logo-ph"><?= icon('shop') ?></span><?php endif; ?>
        <div class="report-main">
          <h3><a href="<?= e(shop_url($s)) ?>" target="_blank"><?= e($s['name']) ?></a></h3>
          <p class="muted small">by <a href="<?= e(url('admin/user-edit.php?id=' . $s['user_id'])) ?>"><?= e($s['owner']) ?></a> (<?= e($s['email']) ?>)<?= $s['verified'] ? ' · ID verified' : '' ?> · <?= (int) $s['ads'] ?> live ads · updated <?= e(time_ago($s['updated_at'])) ?></p>
          <?php if ($s['tagline']): ?><p><?= e($s['tagline']) ?></p><?php endif; ?>
          <p class="small"><?= e(excerpt((string) $s['description'], 300)) ?></p>
          <p class="muted small"><?= e(implode(' · ', array_filter([$s['phone'], $s['website'], $s['address']]))) ?></p>
          <?php if ($s['reject_reason']): ?><p class="mi-note"><?= icon('info') ?><?= e($s['reject_reason']) ?></p><?php endif; ?>
        </div>
        <div class="mi-actions">
          <?php if ($s['status'] !== 'active'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn-success btn-sm" name="action" value="approve"><?= icon('check') ?>Approve</button></form><?php endif; ?>
          <?php if ($s['status'] !== 'rejected'): ?>
            <form method="post" class="reject-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input name="reason" placeholder="Reason (sent to owner)" class="reason-input"><button class="btn btn-ghost btn-sm" name="action" value="reject">Reject</button></form>
          <?php endif; ?>
          <form method="post" data-confirm="Delete this shop page? The owner's ads stay online."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn-ghost btn-sm danger" name="action" value="delete"><?= icon('trash') ?>Delete</button></form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="card empty-state"><?= icon('shop') ?><h3>Nothing here</h3><p>Shops created by members appear here.</p></div>
<?php endif; ?>
<?php require APP_ROOT . '/includes/admin-bottom.php';
