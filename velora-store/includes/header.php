<?php
/** @var string $pageTitle */
$pageTitle = isset($pageTitle) ? $pageTitle . ' | ' . setting('store_name', 'Store') : setting('store_name', 'Store');
$storeName = setting('store_name', 'VELORA');
$cartCount = cart_count();
$wishCount = count(wishlist_ids());
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDescription ?? setting('store_tagline')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/style.css') ?>?v=<?= APP_VERSION ?>">
<script>try{var t=localStorage.getItem('theme');if(t)document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
</head>
<body>

<?php if (setting_on('announcement_enabled') && setting('announcement_text') !== ''): ?>
<div class="topbar" id="topbar">
  <div class="container topbar-inner">
    <p><?= e(setting('announcement_text')) ?>
      <?php if (setting('announcement_link_text') !== ''): ?><a href="<?= url('#newsletter') ?>"><?= e(setting('announcement_link_text')) ?></a><?php endif; ?></p>
    <button class="topbar-close" type="button" aria-label="Close" data-close="#topbar"><?= icon('close', 16) ?></button>
  </div>
</div>
<?php endif; ?>

<header class="site-header">
  <div class="container header-inner">
    <button class="icon-btn menu-toggle" type="button" aria-label="Menu" data-toggle-menu><?= icon('menu', 22) ?></button>
    <a class="logo" href="<?= url() ?>">
      <?php if (setting('logo') !== ''): ?>
        <img src="<?= e(img_url(setting('logo'))) ?>" alt="<?= e($storeName) ?>">
      <?php else: ?>
        <?= e($storeName) ?><sup>®</sup>
      <?php endif; ?>
    </a>
    <nav class="main-nav" id="main-nav">
      <div class="nav-item has-drop">
        <a href="<?= url('shop.php') ?>" class="<?= $current === 'shop.php' ? 'active' : '' ?>">Shop <?= icon('chevron-down', 14) ?></a>
        <div class="dropdown">
          <a href="<?= url('shop.php') ?>">All Products</a>
          <?php foreach (categories() as $c): ?>
            <a href="<?= url('shop.php?category=' . (int) $c['id']) ?>"><?= e($c['name']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <a href="<?= url('shop.php?sale=1') ?>">On Sale</a>
      <a href="<?= url('shop.php?sort=new') ?>">New Arrivals</a>
      <div class="nav-item has-drop">
        <a href="<?= url('shop.php') ?>">Brands <?= icon('chevron-down', 14) ?></a>
        <div class="dropdown">
          <?php foreach (brands() as $b): ?>
            <a href="<?= url('shop.php?brand=' . rawurlencode($b)) ?>"><?= e($b) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <a href="<?= url('blog.php') ?>" class="<?= $current === 'blog.php' ? 'active' : '' ?>">Blog</a>
    </nav>
    <form class="search" action="<?= url('shop.php') ?>" method="get" role="search">
      <?= icon('search', 18) ?>
      <input type="search" name="q" placeholder="Search for products..." value="<?= e($_GET['q'] ?? '') ?>">
    </form>
    <div class="header-icons">
      <a class="icon-btn search-mobile" href="<?= url('shop.php') ?>" aria-label="Search"><?= icon('search') ?></a>
      <button class="icon-btn" type="button" aria-label="Toggle dark mode" data-theme-toggle><span class="i-moon"><?= icon('moon') ?></span><span class="i-sun"><?= icon('sun') ?></span></button>
      <a class="icon-btn" href="<?= url('wishlist.php') ?>" aria-label="Wishlist"><?= icon('heart') ?><?php if ($wishCount): ?><span class="badge-count"><?= $wishCount ?></span><?php endif; ?></a>
      <a class="icon-btn" href="<?= url('cart.php') ?>" aria-label="Cart"><?= icon('cart') ?><span class="badge-count" data-cart-count <?= $cartCount ? '' : 'hidden' ?>><?= $cartCount ?></span></a>
      <a class="icon-btn" href="<?= url('track.php') ?>" aria-label="Track my order"><?= icon('user') ?></a>
    </div>
  </div>
</header>
<main>
<?php $flashHtml = render_flashes(); if ($flashHtml !== ''): ?><div class="container flash-wrap"><?= $flashHtml ?></div><?php endif; ?>
