<?php
/** @var string|null $pageTitle @var string|null $metaDesc @var string|null $ogImage @var string|null $bodyClass */
$siteName = setting('site_name');
$title = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' · ' . $siteName : $siteName . ' – ' . __(setting('site_tagline'));
$desc = $metaDesc ?? __(setting('site_description'));
$me = current_user();
$unread = $me ? unread_count() : 0;
$brand = valid_color(setting('primary_color'), '#0d9488');
$accent = valid_color(setting('accent_color'), '#f97316');
$headerPages = q_all('SELECT title, slug FROM pages WHERE in_header = 1 ORDER BY id');
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
?><!doctype html>
<html lang="<?= e(current_lang()) ?>" dir="<?= lang_dir() ?>" data-default-theme="<?= e(setting('default_theme', 'auto')) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e(excerpt($desc, 160)) ?>">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e(excerpt($desc, 160)) ?>">
<meta property="og:type" content="<?= e($ogType ?? 'website') ?>">
<meta property="og:url" content="<?= e($canonical ?? abs_url($_SERVER['REQUEST_URI'] ?? url())) ?>">
<?php if (!empty($canonical)): ?><link rel="canonical" href="<?= e($canonical) ?>"><?php endif; ?>
<?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= e($ogImage) ?>">
<?php if (!empty($ogImageSize)): ?><meta property="og:image:width" content="<?= (int) $ogImageSize[0] ?>"><meta property="og:image:height" content="<?= (int) $ogImageSize[1] ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image"><meta name="twitter:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<meta name="twitter:title" content="<?= e($title) ?>">
<?php if (!empty($ogPrice)): ?><meta property="product:price:amount" content="<?= e($ogPrice) ?>"><meta property="product:price:currency" content="<?= e(setting('currency_code', 'USD')) ?>"><?php endif; ?>
<?php if (!empty($jsonLd)): ?><script type="application/ld+json"><?= json_encode(array_filter($jsonLd, fn($x) => $x !== null && $x !== []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script><?php endif; ?>
<?php if (setting('pwa_enabled') === '1'): ?><link rel="manifest" href="<?= e(clean_urls() ? url('manifest.webmanifest') : url('manifest.php')) ?>">
<link rel="apple-touch-icon" href="<?= e(url('icon.php?s=192')) ?>"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes"><?php endif; ?>
<meta name="theme-color" content="<?= e($brand) ?>">
<?php if (setting('favicon')): ?><link rel="icon" href="<?= e(upload_url(setting('favicon'))) ?>"><?php else: ?><link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<style>:root{--brand:<?= $brand ?>;--accent:<?= $accent ?>}</style>
<script>(function(){var d=document.documentElement,p=null;try{p=localStorage.getItem('bzr-theme')}catch(e){}p=p||d.dataset.defaultTheme;if(p==='auto'||!p){p=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'}d.dataset.theme=p})();</script>
<?= setting('custom_head_code') ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<?= icon_sprite() ?>
<a class="skip-link" href="#main"><?= t('Skip to content') ?></a>
<header class="site-header">
  <div class="container header-row">
    <a class="logo" href="<?= e(url()) ?>">
      <?php if (setting('logo')): ?>
        <img src="<?= e(upload_url(setting('logo'))) ?>" alt="<?= e($siteName) ?>">
      <?php else: ?>
        <span class="logo-mark"><?= icon('store') ?></span><span class="logo-text"><?= e($siteName) ?></span>
      <?php endif; ?>
    </a>

    <nav class="main-nav" id="mainNav" aria-label="<?= t('Main') ?>">
      <a href="<?= e(url()) ?>" class="<?= $current === 'index.php' ? 'active' : '' ?>"><?= t('Home') ?></a>
      <a href="<?= e(search_url([])) ?>" class="<?= $current === 'listings.php' ? 'active' : '' ?>"><?= t('Browse ads') ?></a>
      <a href="<?= e(url('categories.php')) ?>" class="<?= $current === 'categories.php' ? 'active' : '' ?>"><?= t('Categories') ?></a>
      <?php if (shops_enabled()): ?><a href="<?= e(clean_urls() ? url('shops') : url('shops.php')) ?>" class="<?= in_array($current, ['shops.php', 'shop.php'], true) ? 'active' : '' ?>"><?= t('Shops') ?></a><?php endif; ?>
      <?php foreach ($headerPages as $hp): ?>
        <a href="<?= e(page_url($hp['slug'])) ?>"><?= t($hp['title']) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('contact.php')) ?>" class="<?= $current === 'contact.php' ? 'active' : '' ?>"><?= t('Contact') ?></a>
      <div class="mobile-only nav-mobile-extra">
        <?php if (!$me): ?>
          <a href="<?= e(url('login.php')) ?>"><?= t('Sign in') ?></a>
          <?php if (setting('allow_registration') === '1'): ?><a href="<?= e(url('register.php')) ?>"><?= t('Create account') ?></a><?php endif; ?>
        <?php endif; ?>
      </div>
    </nav>

    <div class="header-actions">
      <?= language_switcher() ?>
      <button class="icon-btn" type="button" data-theme-toggle aria-label="<?= t('Toggle dark mode') ?>"><?= icon('moon', 'show-light') ?><?= icon('sun', 'show-dark') ?></button>
      <?php if ($me): ?>
        <a class="icon-btn" href="<?= e(url('dashboard/messages.php')) ?>" aria-label="<?= t('Messages') ?>"><?= icon('message') ?><?php if ($unread): ?><span class="dot-badge"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?></a>
        <details class="dropdown user-menu">
          <summary aria-label="<?= t('Account menu') ?>"><?= avatar_html($me, 'sm') ?><?= icon('chevron-down', 'hide-mobile') ?></summary>
          <div class="dropdown-panel">
            <div class="dropdown-head"><strong><?= e($me['name']) ?></strong><small>@<?= e($me['username']) ?></small></div>
            <a href="<?= e(url('dashboard/')) ?>"><?= icon('layout') ?><?= t('Dashboard') ?></a>
            <a href="<?= e(url('dashboard/listings.php')) ?>"><?= icon('package') ?><?= t('My ads') ?></a>
            <a href="<?= e(url('dashboard/stats.php')) ?>"><?= icon('chart') ?><?= t('Statistics') ?></a>
            <a href="<?= e(url('dashboard/favorites.php')) ?>"><?= icon('heart') ?><?= t('Saved ads') ?></a>
            <?php if (setting('enable_saved_searches', '1') === '1'): ?><a href="<?= e(url('dashboard/searches.php')) ?>"><?= icon('bell') ?><?= t('Saved searches') ?></a><?php endif; ?>
            <a href="<?= e(url('dashboard/messages.php')) ?>"><?= icon('message') ?><?= t('Messages') ?><?php if ($unread): ?><span class="badge badge-brand"><?= $unread ?></span><?php endif; ?></a>
            <a href="<?= e(url('dashboard/settings.php')) ?>"><?= icon('settings') ?><?= t('Account settings') ?></a>
            <?php if ($me['role'] === 'admin'): ?><a href="<?= e(url('admin/')) ?>"><?= icon('shield') ?><?= t('Admin panel') ?></a><?php endif; ?>
            <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button type="submit"><?= icon('log-out') ?><?= t('Sign out') ?></button></form>
          </div>
        </details>
      <?php else: ?>
        <a class="btn btn-ghost hide-mobile" href="<?= e(url('login.php')) ?>"><?= t('Sign in') ?></a>
      <?php endif; ?>
      <a class="btn btn-primary" href="<?= e(url('dashboard/edit.php')) ?>"><?= icon('plus') ?><span class="hide-xs"><?= t('Post ad') ?></span></a>
      <button class="icon-btn nav-toggle" type="button" aria-controls="mainNav" aria-expanded="false" aria-label="<?= t('Menu') ?>"><?= icon('menu') ?></button>
    </div>
  </div>
</header>
<?php if ($me && needs_email_verification($me)): ?>
<div class="verify-bar"><div class="container">
  <?= icon('mail') ?><span><?= t('Please confirm your email address ({email}) to post ads and send messages.', ['email' => $me['email']]) ?></span>
  <form method="post" action="<?= e(url('verify.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="resend"><button class="btn btn-sm btn-light" type="submit"><?= t('Resend email') ?></button></form>
</div></div>
<?php endif; ?>
<main id="main">
<?= render_flashes() ?>
