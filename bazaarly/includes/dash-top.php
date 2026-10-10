<?php
/** @var string $dashActive @var string $pageTitle */
$me = require_login();
$unreadDash = unread_count();
if (setting('enable_saved_searches', '1') !== '1') {
    $hideSearches = true;
}
$navItems = [
    'overview' => ['dashboard/', 'layout', 'Overview'],
    'listings' => ['dashboard/listings.php', 'package', 'My ads'],
    'new' => ['dashboard/edit.php', 'plus', 'Post a new ad'],
    'messages' => ['dashboard/messages.php', 'message', 'Messages'],
    'favorites' => ['dashboard/favorites.php', 'heart', 'Saved ads'],
    'searches' => ['dashboard/searches.php', 'bell', 'Saved searches'],
    'settings' => ['dashboard/settings.php', 'settings', 'Account settings'],
];
require APP_ROOT . '/includes/header.php';
?>
<div class="container dash-shell">
  <aside class="dash-nav">
    <div class="dash-user">
      <?= avatar_html($me, 'lg') ?>
      <div><strong><?= e($me['name']) ?></strong><a href="<?= e(profile_url((int) $me['id'], $me['username'])) ?>" class="small-link">View public profile</a></div>
    </div>
    <nav>
      <?php foreach ($navItems as $_navKey => [$_navHref, $_navIcon, $_navLabel]): if ($_navKey === 'searches' && !empty($hideSearches)) continue; ?>
        <a href="<?= e(url($_navHref)) ?>" class="<?= ($dashActive ?? '') === $_navKey ? 'active' : '' ?>"><?= icon($_navIcon) ?><span><?= e($_navLabel) ?></span>
          <?php if ($_navKey === 'messages' && $unreadDash): ?><span class="badge badge-brand"><?= $unreadDash ?></span><?php endif; ?></a>
      <?php endforeach; ?>
      <?php if ($me['role'] === 'admin'): ?><a href="<?= e(url('admin/')) ?>"><?= icon('shield') ?><span>Admin panel</span></a><?php endif; ?>
    </nav>
  </aside>
  <div class="dash-content">
