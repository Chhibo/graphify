<?php
/** @var string $adminActive @var string $pageTitle */
$me = require_admin();
$badges = [
    'listings' => (int) q_val("SELECT COUNT(*) FROM listings WHERE status = 'pending'"),
    'reports' => (int) q_val("SELECT COUNT(*) FROM reports WHERE status = 'open'"),
    'messages' => (int) q_val('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0'),
];
$adminNav = [
    'Overview' => [
        'dashboard' => ['admin/', 'layout', 'Dashboard'],
    ],
    'Marketplace' => [
        'listings' => ['admin/listings.php', 'package', 'Ads'],
        'categories' => ['admin/categories.php', 'folder', 'Categories'],
        'reports' => ['admin/reports.php', 'flag', 'Reports'],
        'reviews' => ['admin/reviews.php', 'star', 'Reviews'],
    ],
    'People' => [
        'users' => ['admin/users.php', 'users', 'Users'],
        'messages' => ['admin/messages.php', 'inbox', 'Contact inbox'],
    ],
    'Website' => [
        'pages' => ['admin/pages.php', 'file', 'Pages'],
        'settings' => ['admin/settings.php', 'settings', 'Settings'],
    ],
];
$brand = valid_color(setting('primary_color'), '#0d9488');
?><!doctype html>
<html lang="en" data-default-theme="<?= e(setting('default_theme', 'auto')) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e(($pageTitle ?? 'Admin') . ' · Admin · ' . setting('site_name')) ?></title>
<?php if (setting('favicon')): ?><link rel="icon" href="<?= e(upload_url(setting('favicon'))) ?>"><?php else: ?><link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<style>:root{--brand:<?= $brand ?>;--accent:<?= valid_color(setting('accent_color'), '#f97316') ?>}</style>
<script>(function(){var d=document.documentElement,p=null;try{p=localStorage.getItem('bzr-theme')}catch(e){}p=p||d.dataset.defaultTheme;if(p==='auto'||!p){p=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'}d.dataset.theme=p})();</script>
</head>
<body class="admin-body">
<?= icon_sprite() ?>
<div class="admin-shell">
  <aside class="admin-side" id="adminSide">
    <a class="admin-logo" href="<?= e(url('admin/')) ?>"><span class="logo-mark"><?= icon('store') ?></span><span><?= e(setting('site_name')) ?><small>Admin</small></span></a>
    <nav>
      <?php foreach ($adminNav as $group => $items): ?>
        <div class="admin-nav-group"><?= e($group) ?></div>
        <?php foreach ($items as $key => [$href, $ic, $label]): ?>
          <a href="<?= e(url($href)) ?>" class="<?= ($adminActive ?? '') === $key ? 'active' : '' ?>"><?= icon($ic) ?><span><?= e($label) ?></span>
            <?php if (!empty($badges[$key])): ?><span class="nav-count"><?= $badges[$key] ?></span><?php endif; ?></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="admin-side-foot">
      <a href="<?= e(url()) ?>" target="_blank"><?= icon('external') ?><span>View website</span></a>
      <a href="<?= e(url('dashboard/')) ?>"><?= icon('user') ?><span>My dashboard</span></a>
    </div>
  </aside>
  <div class="admin-main">
    <header class="admin-top">
      <button class="icon-btn admin-menu" type="button" data-admin-menu aria-label="Menu"><?= icon('menu') ?></button>
      <h1 class="admin-title"><?= e($pageTitle ?? 'Admin') ?></h1>
      <div class="admin-top-actions">
        <?php if (setting('maintenance_mode') === '1'): ?><a class="badge badge-warning" href="<?= e(url('admin/settings.php#maintenance')) ?>"><?= icon('alert') ?>Maintenance on</a><?php endif; ?>
        <button class="icon-btn" type="button" data-theme-toggle aria-label="Toggle dark mode"><?= icon('moon', 'show-light') ?><?= icon('sun', 'show-dark') ?></button>
        <details class="dropdown user-menu">
          <summary><?= avatar_html($me, 'sm') ?></summary>
          <div class="dropdown-panel">
            <div class="dropdown-head"><strong><?= e($me['name']) ?></strong><small><?= e($me['email']) ?></small></div>
            <a href="<?= e(url('dashboard/settings.php')) ?>"><?= icon('settings') ?>My account</a>
            <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button type="submit"><?= icon('log-out') ?>Sign out</button></form>
          </div>
        </details>
      </div>
    </header>
    <div class="admin-content">
      <?= render_flashes() ?>
