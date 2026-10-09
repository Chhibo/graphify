<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_login();
$uid = (int) $me['id'];

$stats = [
    'active' => (int) q_val("SELECT COUNT(*) FROM listings WHERE user_id = ? AND status = 'active'", [$uid]),
    'pending' => (int) q_val("SELECT COUNT(*) FROM listings WHERE user_id = ? AND status = 'pending'", [$uid]),
    'views' => (int) q_val('SELECT COALESCE(SUM(views), 0) FROM listings WHERE user_id = ?', [$uid]),
    'saves' => (int) q_val('SELECT COUNT(*) FROM favorites f JOIN listings l ON l.id = f.listing_id WHERE l.user_id = ?', [$uid]),
];
$recent = q_all(listing_select() . ' WHERE l.user_id = ? ORDER BY l.updated_at DESC LIMIT 5', [$uid]);
$convs = q_all('SELECT c.*, l.title, CASE WHEN c.buyer_id = ? THEN c.buyer_unread ELSE c.seller_unread END AS unread,
        u.name AS other_name, u.avatar AS other_avatar, u.id AS other_id
        FROM conversations c
        JOIN listings l ON l.id = c.listing_id
        JOIN users u ON u.id = CASE WHEN c.buyer_id = ? THEN c.seller_id ELSE c.buyer_id END
        WHERE c.buyer_id = ? OR c.seller_id = ? ORDER BY c.last_message_at DESC LIMIT 5', [$uid, $uid, $uid, $uid]);

$dashActive = 'overview';
$pageTitle = 'Dashboard';
require APP_ROOT . '/includes/dash-top.php';
?>
<div class="dash-head">
  <div><h1>Hi, <?= e(strtok($me['name'], ' ')) ?> 👋</h1><p class="muted">Here's what's happening with your ads.</p></div>
  <a class="btn btn-primary" href="<?= e(url('dashboard/edit.php')) ?>"><?= icon('plus') ?>Post a new ad</a>
</div>

<div class="stat-grid">
  <div class="stat"><span class="stat-icon"><?= icon('package') ?></span><div><strong><?= $stats['active'] ?></strong><span>Active ads</span></div></div>
  <div class="stat"><span class="stat-icon warn"><?= icon('clock') ?></span><div><strong><?= $stats['pending'] ?></strong><span>Pending review</span></div></div>
  <div class="stat"><span class="stat-icon info"><?= icon('eye') ?></span><div><strong><?= number_format($stats['views']) ?></strong><span>Total views</span></div></div>
  <div class="stat"><span class="stat-icon pink"><?= icon('heart') ?></span><div><strong><?= $stats['saves'] ?></strong><span>Times saved</span></div></div>
</div>

<div class="dash-grid">
  <section class="card">
    <div class="card-head"><h2>Recent ads</h2><a class="small-link" href="<?= e(url('dashboard/listings.php')) ?>">View all</a></div>
    <?php if ($recent): ?>
      <ul class="mini-list">
        <?php foreach ($recent as $l): ?>
          <li>
            <a class="mini-thumb" href="<?= e(listing_url($l)) ?>"><?php if ($l['cover']): ?><img src="<?= e(upload_url($l['cover'])) ?>" alt=""><?php else: ?><?= icon($l['category_icon'] ?: 'image') ?><?php endif; ?></a>
            <div class="mini-body"><a href="<?= e(listing_url($l)) ?>"><?= e($l['title']) ?></a><small><?= e(listing_price($l)) ?> · <?= number_format((int) $l['views']) ?> views</small></div>
            <?= status_badge($l['status']) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="empty-state small"><?= icon('package') ?><p>You haven't posted any ads yet.</p><a class="btn btn-primary btn-sm" href="<?= e(url('dashboard/edit.php')) ?>">Post your first ad</a></div>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-head"><h2>Latest messages</h2><a class="small-link" href="<?= e(url('dashboard/messages.php')) ?>">Inbox</a></div>
    <?php if ($convs): ?>
      <ul class="mini-list">
        <?php foreach ($convs as $c): ?>
          <li class="<?= $c['unread'] ? 'unread' : '' ?>">
            <?= avatar_html(['id' => $c['other_id'], 'name' => $c['other_name'], 'avatar' => $c['other_avatar']], 'sm') ?>
            <div class="mini-body"><a href="<?= e(url('dashboard/messages.php?c=' . $c['id'])) ?>"><?= e($c['other_name']) ?></a><small><?= e(excerpt($c['title'], 50)) ?> · <?= e(time_ago($c['last_message_at'])) ?></small></div>
            <?php if ($c['unread']): ?><span class="badge badge-brand"><?= (int) $c['unread'] ?> new</span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="empty-state small"><?= icon('inbox') ?><p>No messages yet.</p></div>
    <?php endif; ?>
  </section>
</div>
<?php require APP_ROOT . '/includes/dash-bottom.php';
