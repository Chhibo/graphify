<?php
/** @var string|null $pageTitle @var string|null $metaDesc @var string|null $ogImage @var string|null $bodyClass */
$siteName = setting('site_name');
$title = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' · ' . $siteName : $siteName . ' – ' . setting('site_tagline');
$desc = $metaDesc ?? setting('site_description');
$me = current_user();
$unread = $me ? unread_count() : 0;
$brand = valid_color(setting('primary_color'), '#0d9488');
$accent = valid_color(setting('accent_color'), '#f97316');
$headerPages = q_all('SELECT title, slug FROM pages WHERE in_header = 1 ORDER BY id');
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
?><!doctype html>
<html lang="en" data-default-theme="<?= e(setting('default_theme', 'auto')) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e(excerpt($desc, 160)) ?>">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e(excerpt($desc, 160)) ?>">
<?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<meta name="theme-color" content="<?= e($brand) ?>">
<?php if (setting('favicon')): ?><link rel="icon" href="<?= e(upload_url(setting('favicon'))) ?>"><?php else: ?><link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<style>:root{--brand:<?= $brand ?>;--accent:<?= $accent ?>}</style>
<script>(function(){var d=document.documentElement,p=null;try{p=localStorage.getItem('bzr-theme')}catch(e){}p=p||d.dataset.defaultTheme;if(p==='auto'||!p){p=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'}d.dataset.theme=p})();</script>
<?= setting('custom_head_code') ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<?= icon_sprite() ?>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
  <div class="container header-row">
    <a class="logo" href="<?= e(url()) ?>">
      <?php if (setting('logo')): ?>
        <img src="<?= e(upload_url(setting('logo'))) ?>" alt="<?= e($siteName) ?>">
      <?php else: ?>
        <span class="logo-mark"><?= icon('store') ?></span><span class="logo-text"><?= e($siteName) ?></span>
      <?php endif; ?>
    </a>

    <nav class="main-nav" id="mainNav" aria-label="Main">
      <a href="<?= e(url()) ?>" class="<?= $current === 'index.php' ? 'active' : '' ?>">Home</a>
      <a href="<?= e(url('listings.php')) ?>" class="<?= $current === 'listings.php' ? 'active' : '' ?>">Browse ads</a>
      <a href="<?= e(url('categories.php')) ?>" class="<?= $current === 'categories.php' ? 'active' : '' ?>">Categories</a>
      <?php foreach ($headerPages as $hp): ?>
        <a href="<?= e(url('page.php?slug=' . $hp['slug'])) ?>"><?= e($hp['title']) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('contact.php')) ?>" class="<?= $current === 'contact.php' ? 'active' : '' ?>">Contact</a>
      <div class="mobile-only nav-mobile-extra">
        <?php if (!$me): ?>
          <a href="<?= e(url('login.php')) ?>">Sign in</a>
          <?php if (setting('allow_registration') === '1'): ?><a href="<?= e(url('register.php')) ?>">Create account</a><?php endif; ?>
        <?php endif; ?>
      </div>
    </nav>

    <div class="header-actions">
      <button class="icon-btn" type="button" data-theme-toggle aria-label="Toggle dark mode"><?= icon('moon', 'show-light') ?><?= icon('sun', 'show-dark') ?></button>
      <?php if ($me): ?>
        <a class="icon-btn" href="<?= e(url('dashboard/messages.php')) ?>" aria-label="Messages"><?= icon('message') ?><?php if ($unread): ?><span class="dot-badge"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?></a>
        <details class="dropdown user-menu">
          <summary aria-label="Account menu"><?= avatar_html($me, 'sm') ?><?= icon('chevron-down', 'hide-mobile') ?></summary>
          <div class="dropdown-panel">
            <div class="dropdown-head"><strong><?= e($me['name']) ?></strong><small>@<?= e($me['username']) ?></small></div>
            <a href="<?= e(url('dashboard/')) ?>"><?= icon('layout') ?>Dashboard</a>
            <a href="<?= e(url('dashboard/listings.php')) ?>"><?= icon('package') ?>My ads</a>
            <a href="<?= e(url('dashboard/favorites.php')) ?>"><?= icon('heart') ?>Saved ads</a>
            <a href="<?= e(url('dashboard/messages.php')) ?>"><?= icon('message') ?>Messages<?php if ($unread): ?><span class="badge badge-brand"><?= $unread ?></span><?php endif; ?></a>
            <a href="<?= e(url('dashboard/settings.php')) ?>"><?= icon('settings') ?>Account settings</a>
            <?php if ($me['role'] === 'admin'): ?><a href="<?= e(url('admin/')) ?>"><?= icon('shield') ?>Admin panel</a><?php endif; ?>
            <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button type="submit"><?= icon('log-out') ?>Sign out</button></form>
          </div>
        </details>
      <?php else: ?>
        <a class="btn btn-ghost hide-mobile" href="<?= e(url('login.php')) ?>">Sign in</a>
      <?php endif; ?>
      <a class="btn btn-primary" href="<?= e(url('dashboard/edit.php')) ?>"><?= icon('plus') ?><span class="hide-xs">Post ad</span></a>
      <button class="icon-btn nav-toggle" type="button" aria-controls="mainNav" aria-expanded="false" aria-label="Menu"><?= icon('menu') ?></button>
    </div>
  </div>
</header>
<main id="main">
<?= render_flashes() ?>
