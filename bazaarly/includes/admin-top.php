<?php
/** @var string $adminActive @var string $pageTitle */
$me = require_admin();
$badges = [
    'listings' => (int) q_val("SELECT COUNT(*) FROM listings WHERE status = 'pending'"),
    'verifications' => (int) q_val("SELECT COUNT(*) FROM verification_requests WHERE status = 'pending'"),
    'shops' => (int) q_val("SELECT COUNT(*) FROM shops WHERE status = 'pending'"),
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
        'locations' => ['admin/locations.php', 'map-pin', 'Locations'],
        'shops' => ['admin/shops.php', 'shop', 'Shops'],
        'import' => ['admin/import.php', 'upload', 'Import ads (CSV)'],
        'reports' => ['admin/reports.php', 'flag', 'Reports'],
        'reviews' => ['admin/reviews.php', 'star', 'Reviews'],
    ],
    'People' => [
        'users' => ['admin/users.php', 'users', 'Users'],
        'verifications' => ['admin/verifications.php', 'id-card', 'ID verification'],
        'newsletter' => ['admin/newsletter.php', 'newspaper', 'Newsletter'],
        'messages' => ['admin/messages.php', 'inbox', 'Contact inbox'],
    ],
    'Website' => [
        'pages' => ['admin/pages.php', 'file', 'Pages'],
        'languages' => ['admin/languages.php', 'languages', 'Languages'],
        'emails' => ['admin/emails.php', 'mail', 'Emails & SMTP'],
        'settings' => ['admin/settings.php', 'settings', 'Settings'],
    ],
    'System' => [
        'activity' => ['admin/activity.php', 'activity', 'Activity log'],
        'backup' => ['admin/backup.php', 'database', 'Backup'],
        'security' => ['admin/security.php', 'lock', 'My security (2FA)'],
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
      <?php foreach ($adminNav as $_navGroup => $_navItems): ?>
        <div class="admin-nav-group"><?= e($_navGroup) ?></div>
        <?php foreach ($_navItems as $_navKey => [$_navHref, $_navIcon, $_navLabel]): ?>
          <a href="<?= e(url($_navHref)) ?>" class="<?= ($adminActive ?? '') === $_navKey ? 'active' : '' ?>"><?= icon($_navIcon) ?><span><?= e($_navLabel) ?></span>
            <?php if (!empty($badges[$_navKey])): ?><span class="nav-count"><?= $badges[$_navKey] ?></span><?php endif; ?></a>
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
