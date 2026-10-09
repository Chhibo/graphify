<?php
/** @var string $dashActive @var string $pageTitle */
$me = require_login();
$unreadDash = unread_count();
$navItems = [
    'overview' => ['dashboard/', 'layout', 'Overview'],
    'listings' => ['dashboard/listings.php', 'package', 'My ads'],
    'new' => ['dashboard/edit.php', 'plus', 'Post a new ad'],
    'messages' => ['dashboard/messages.php', 'message', 'Messages'],
    'favorites' => ['dashboard/favorites.php', 'heart', 'Saved ads'],
    'settings' => ['dashboard/settings.php', 'settings', 'Account settings'],
];
require APP_ROOT . '/includes/header.php';
?>
<div class="container dash-shell">
  <aside class="dash-nav">
    <div class="dash-user">
      <?= avatar_html($me, 'lg') ?>
      <div><strong><?= e($me['name']) ?></strong><a href="<?= e(url('profile.php?id=' . $me['id'])) ?>" class="small-link">View public profile</a></div>
    </div>
    <nav>
      <?php foreach ($navItems as $key => [$href, $ic, $label]): ?>
        <a href="<?= e(url($href)) ?>" class="<?= ($dashActive ?? '') === $key ? 'active' : '' ?>"><?= icon($ic) ?><span><?= e($label) ?></span>
          <?php if ($key === 'messages' && $unreadDash): ?><span class="badge badge-brand"><?= $unreadDash ?></span><?php endif; ?></a>
      <?php endforeach; ?>
      <?php if ($me['role'] === 'admin'): ?><a href="<?= e(url('admin/')) ?>"><?= icon('shield') ?><span>Admin panel</span></a><?php endif; ?>
    </nav>
  </aside>
  <div class="dash-content">
