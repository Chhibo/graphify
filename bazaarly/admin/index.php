<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$weekAgo = date('Y-m-d H:i:s', time() - 7 * 86400);
$stats = [
    ['Live ads', (int) q_val("SELECT COUNT(*) FROM listings WHERE status = 'active'"), 'package', ''],
    ['Pending review', (int) q_val("SELECT COUNT(*) FROM listings WHERE status = 'pending'"), 'clock', 'warn'],
    ['Members', (int) q_val('SELECT COUNT(*) FROM users'), 'users', 'info'],
    ['Open reports', (int) q_val("SELECT COUNT(*) FROM reports WHERE status = 'open'"), 'flag', 'pink'],
];
$newAds = (int) q_val('SELECT COUNT(*) FROM listings WHERE created_at >= ?', [$weekAgo]);
$newUsers = (int) q_val('SELECT COUNT(*) FROM users WHERE created_at >= ?', [$weekAgo]);
$msgs = (int) q_val('SELECT COUNT(*) FROM messages WHERE created_at >= ?', [$weekAgo]);
$views = (int) q_val('SELECT COALESCE(SUM(views), 0) FROM listings');

$pending = q_all(listing_select() . " WHERE l.status = 'pending' ORDER BY l.created_at ASC LIMIT 6");
$users = q_all('SELECT * FROM users ORDER BY created_at DESC LIMIT 6');
$reports = q_all("SELECT r.*, l.title FROM reports r LEFT JOIN listings l ON l.id = r.listing_id WHERE r.status = 'open' ORDER BY r.created_at DESC LIMIT 5");

// ads posted per day, last 14 days
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i days"))] = 0;
}
foreach (q_all('SELECT created_at FROM listings WHERE created_at >= ?', [array_key_first($days) . ' 00:00:00']) as $r) {
    $k = substr($r['created_at'], 0, 10);
    if (isset($days[$k])) {
        $days[$k]++;
    }
}
$peak = max(1, max($days));

$adminActive = 'dashboard';
$pageTitle = 'Dashboard';
require APP_ROOT . '/includes/admin-top.php';
?>
<div class="stat-grid">
  <?php foreach ($stats as [$label, $value, $ic, $tone]): ?>
    <div class="stat"><span class="stat-icon <?= $tone ?>"><?= icon($ic) ?></span><div><strong><?= number_format($value) ?></strong><span><?= e($label) ?></span></div></div>
  <?php endforeach; ?>
</div>

<div class="admin-grid">
  <section class="card">
    <div class="card-head"><h2>New ads · last 14 days</h2><span class="muted small"><?= $newAds ?> this week</span></div>
    <div class="bar-chart" role="img" aria-label="Ads posted per day over the last 14 days">
      <?php foreach ($days as $day => $n): ?>
        <div class="bar" title="<?= e(date('M j', strtotime($day))) ?>: <?= $n ?> ad<?= $n === 1 ? '' : 's' ?>">
          <span class="bar-val"><?= $n ?: '' ?></span>
          <span class="bar-fill" style="height:<?= max(2, round($n / $peak * 100)) ?>%"></span>
          <span class="bar-label"><?= e(date('j', strtotime($day))) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <section class="card">
    <div class="card-head"><h2>This week</h2></div>
    <ul class="kv-list">
      <li><span><?= icon('package') ?>New ads</span><strong><?= $newAds ?></strong></li>
      <li><span><?= icon('user') ?>New members</span><strong><?= $newUsers ?></strong></li>
      <li><span><?= icon('message') ?>Messages sent</span><strong><?= $msgs ?></strong></li>
      <li><span><?= icon('eye') ?>Total ad views</span><strong><?= number_format($views) ?></strong></li>
    </ul>
  </section>
</div>

<div class="admin-grid">
  <section class="card">
    <div class="card-head"><h2>Waiting for approval</h2><a class="small-link" href="<?= e(url('admin/listings.php?status=pending')) ?>">View all</a></div>
    <?php if ($pending): ?>
      <ul class="mini-list">
        <?php foreach ($pending as $l): ?>
          <li>
            <a class="mini-thumb" href="<?= e(listing_url($l)) ?>" target="_blank"><?php if ($l['cover']): ?><img src="<?= e(upload_url($l['cover'])) ?>" alt=""><?php else: ?><?= icon($l['category_icon'] ?: 'image') ?><?php endif; ?></a>
            <div class="mini-body"><a href="<?= e(url('admin/listing-edit.php?id=' . $l['id'])) ?>"><?= e($l['title']) ?></a><small><?= e($l['seller_name']) ?> · <?= e(time_ago($l['created_at'])) ?></small></div>
            <form method="post" action="<?= e(url('admin/listings.php')) ?>" class="inline-actions">
              <?= csrf_field() ?><input type="hidden" name="ids[]" value="<?= (int) $l['id'] ?>">
              <button class="btn btn-sm btn-success" name="bulk" value="approve" type="submit"><?= icon('check') ?>Approve</button>
              <button class="btn btn-sm btn-ghost danger" name="bulk" value="reject" type="submit" aria-label="Reject"><?= icon('x') ?></button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="empty-state small"><?= icon('check-circle') ?><p>All caught up — nothing to review.</p></div>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-head"><h2>Newest members</h2><a class="small-link" href="<?= e(url('admin/users.php')) ?>">All users</a></div>
    <ul class="mini-list">
      <?php foreach ($users as $u): ?>
        <li>
          <?= avatar_html($u, 'sm') ?>
          <div class="mini-body"><a href="<?= e(url('admin/user-edit.php?id=' . $u['id'])) ?>"><?= e($u['name']) ?></a><small><?= e($u['email']) ?> · <?= e(time_ago($u['created_at'])) ?></small></div>
          <?php if ($u['role'] === 'admin'): ?><span class="badge badge-brand">Admin</span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>

<?php if ($reports): ?>
<section class="card">
  <div class="card-head"><h2>Open reports</h2><a class="small-link" href="<?= e(url('admin/reports.php')) ?>">Manage</a></div>
  <ul class="mini-list">
    <?php foreach ($reports as $r): ?>
      <li><span class="stat-icon pink sm"><?= icon('flag') ?></span>
        <div class="mini-body"><a href="<?= e(url('listing.php?id=' . $r['listing_id'])) ?>" target="_blank"><?= e($r['title'] ?? 'Deleted ad') ?></a><small><?= e($r['reason']) ?> · <?= e(time_ago($r['created_at'])) ?></small></div></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
<?php require APP_ROOT . '/includes/admin-bottom.php';
