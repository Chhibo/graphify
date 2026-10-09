<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

if (is_post()) {
    $id = (int) input('id');
    $r = q_one('SELECT * FROM reports WHERE id = ?', [$id]);
    if ($r) {
        switch (input('action')) {
            case 'resolve':
                q("UPDATE reports SET status = 'resolved' WHERE id = ?", [$id]);
                flash('success', 'Report marked as resolved.');
                break;
            case 'reopen':
                q("UPDATE reports SET status = 'open' WHERE id = ?", [$id]);
                break;
            case 'hide':
                q("UPDATE listings SET status = 'rejected', reject_reason = ? WHERE id = ?", ['Removed after a report: ' . $r['reason'], $r['listing_id']]);
                q("UPDATE reports SET status = 'resolved' WHERE listing_id = ?", [$r['listing_id']]);
                flash('success', 'Ad hidden and reports resolved.');
                break;
            case 'delete_listing':
                delete_listing((int) $r['listing_id']);
                flash('success', 'Ad deleted.');
                break;
            case 'delete':
                q('DELETE FROM reports WHERE id = ?', [$id]);
                flash('success', 'Report deleted.');
                break;
        }
    }
    back('admin/reports.php');
}

$status = input('status', 'open') === 'resolved' ? 'resolved' : 'open';
$total = (int) q_val('SELECT COUNT(*) FROM reports WHERE status = ?', [$status]);
[$page, $pages, $offset] = paginate($total, 20, (int) input('page', '1'));
$rows = q_all("SELECT r.*, l.title, l.status AS listing_status, u.name AS reporter
    FROM reports r LEFT JOIN listings l ON l.id = r.listing_id LEFT JOIN users u ON u.id = r.user_id
    WHERE r.status = ? ORDER BY r.created_at DESC LIMIT 20 OFFSET $offset", [$status]);

$adminActive = 'reports';
$pageTitle = 'Reports';
require APP_ROOT . '/includes/admin-top.php';
?>
<nav class="tabs">
  <a href="?status=open" class="<?= $status === 'open' ? 'on' : '' ?>">Open<span><?= (int) q_val("SELECT COUNT(*) FROM reports WHERE status = 'open'") ?></span></a>
  <a href="?status=resolved" class="<?= $status === 'resolved' ? 'on' : '' ?>">Resolved<span><?= (int) q_val("SELECT COUNT(*) FROM reports WHERE status = 'resolved'") ?></span></a>
</nav>
<?php if ($rows): ?>
  <div class="report-list">
    <?php foreach ($rows as $r): ?>
      <article class="card report-item">
        <div class="report-main">
          <div class="mi-top"><span class="badge badge-danger"><?= e($r['reason']) ?></span><span class="muted small"><?= e(time_ago($r['created_at'])) ?> by <?= e($r['reporter'] ?? 'deleted user') ?></span></div>
          <h3><?php if ($r['title'] !== null): ?><a href="<?= e(url('listing.php?id=' . $r['listing_id'])) ?>" target="_blank"><?= e($r['title']) ?></a> <?= status_badge($r['listing_status']) ?><?php else: ?><span class="muted">Ad was deleted</span><?php endif; ?></h3>
          <?php if ($r['details']): ?><p><?= nl2br(e($r['details'])) ?></p><?php endif; ?>
        </div>
        <div class="mi-actions">
          <?php $btn = function (string $action, string $label, string $ic, string $cls = 'btn-ghost', string $confirm = '') use ($r) { ?>
            <form method="post" <?= $confirm ? 'data-confirm="' . e($confirm) . '"' : '' ?>><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="<?= $action ?>"><button class="btn btn-sm <?= $cls ?>" type="submit"><?= icon($ic) ?><?= e($label) ?></button></form>
          <?php }; ?>
          <?php if ($r['title'] !== null): ?>
            <a class="btn btn-sm btn-ghost" href="<?= e(url('admin/listing-edit.php?id=' . $r['listing_id'])) ?>"><?= icon('edit') ?>Edit ad</a>
            <?php if ($r['listing_status'] !== 'rejected') $btn('hide', 'Hide ad', 'ban', 'btn-ghost'); ?>
            <?php $btn('delete_listing', 'Delete ad', 'trash', 'btn-ghost danger', 'Delete this ad permanently?'); ?>
          <?php endif; ?>
          <?php $status === 'open' ? $btn('resolve', 'Resolve', 'check', 'btn-success') : $btn('reopen', 'Reopen', 'refresh'); ?>
          <?php $btn('delete', 'Dismiss', 'x', 'btn-ghost'); ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?= pagination_links($page, $pages) ?>
<?php else: ?>
  <div class="card empty-state"><?= icon('check-circle') ?><h3>No <?= $status ?> reports</h3><p>Reports from users about suspicious ads will show up here.</p></div>
<?php endif; ?>
<?php require APP_ROOT . '/includes/admin-bottom.php';
