<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_login();

$total = (int) q_val('SELECT COUNT(*) FROM favorites WHERE user_id = ?', [$me['id']]);
[$page, $pages, $offset] = paginate($total, 12, (int) input('page', '1'));
$rows = q_all(str_replace('FROM listings l', 'FROM favorites f JOIN listings l ON l.id = f.listing_id', listing_select())
    . " WHERE f.user_id = ? ORDER BY f.created_at DESC LIMIT 12 OFFSET $offset", [$me['id']]);

$dashActive = 'favorites';
$pageTitle = 'Saved ads';
require APP_ROOT . '/includes/dash-top.php';
?>
<div class="dash-head"><div><h1>Saved ads</h1><p class="muted">Ads you've tapped the heart on. Tap it again to remove.</p></div></div>
<?php if ($rows): ?>
  <div class="listing-grid cols-3">
    <?php foreach ($rows as $l) { include APP_ROOT . '/includes/partials/listing-card.php'; } ?>
  </div>
  <?= pagination_links($page, $pages) ?>
<?php else: ?>
  <div class="card empty-state"><?= icon('heart') ?><h3>No saved ads yet</h3><p>Tap the heart on any ad to save it for later.</p><a class="btn btn-primary" href="<?= e(url('listings.php')) ?>">Browse ads</a></div>
<?php endif; ?>
<?php require APP_ROOT . '/includes/dash-bottom.php';
