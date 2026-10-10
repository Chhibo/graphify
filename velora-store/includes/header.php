<?php
/** @var string $pageTitle */
$pageTitle = isset($pageTitle) ? $pageTitle . ' | ' . setting('store_name', 'Store') : setting('store_name', 'Store');
$storeName = setting('store_name', 'VELORA');
$cartCount = cart_count();
$cartTotal = $cartCount ? cart_totals()['subtotal'] : 0;
$wishCount = count(wishlist_ids());
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
$themeColor = setting('theme_color', '#e03a3e');
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $themeColor)) {
    $themeColor = '#e03a3e';
}
$promoLink = setting('promo_link', 'shop.php?sale=1');
$promoHref = preg_match('#^https?://#i', $promoLink) ? $promoLink : url($promoLink);
?>
<!doctype html>
<?php
// Color mode: 'light' (default), 'dark', or 'auto' (follow the visitor's device setting).
$themeMode = setting('theme_mode', 'light');
?>
<html lang="en"<?= $themeMode === 'auto' ? '' : ' data-theme="' . ($themeMode === 'dark' ? 'dark' : 'light') . '"' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDescription ?? setting('store_tagline')) ?>">
<meta name="theme-color" content="<?= e($themeColor) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/style.css') ?>?v=<?= APP_VERSION ?>">
<style>:root{--primary:<?= e($themeColor) ?>}</style>
</head>
<body class="page-<?= e(basename($current, '.php')) ?>">

<?php if (setting_on('announcement_enabled') && setting('announcement_text') !== ''): ?>
<div class="topbar" id="topbar">
  <div class="container topbar-inner">
    <nav class="topbar-links">
      <?php foreach (menu('topbar') as $m): ?><a href="<?= e(menu_url((string) ($m['url'] ?? ''))) ?>"><?= e($m['label'] ?? '') ?></a><?php endforeach; ?>
    </nav>
    <p><?= e(setting('announcement_text')) ?>
      <?php if (setting('announcement_link_text') !== ''): ?><a href="<?= url('shop.php?sale=1') ?>"><?= e(setting('announcement_link_text')) ?></a><?php endif; ?></p>
    <button class="topbar-close" type="button" aria-label="Close" data-close="#topbar"><?= icon('close', 14) ?></button>
  </div>
</div>
<?php endif; ?>

<header class="site-header">
  <div class="container header-top">
    <a class="logo" href="<?= url() ?>">
      <?php if (setting('logo') !== ''): ?>
        <img src="<?= e(img_url(setting('logo'))) ?>" alt="<?= e($storeName) ?>">
      <?php else: ?>
        <span class="logo-icon"><?= icon('cart', 30) ?></span><?= e($storeName) ?>
      <?php endif; ?>
    </a>
    <div class="header-icons">
      <a class="circle-btn" href="<?= url('track.php') ?>" aria-label="Track my order" title="Track my order"><?= icon('user', 18) ?></a>
      <a class="circle-btn" href="<?= url('wishlist.php') ?>" aria-label="Wishlist" title="Wishlist"><?= icon('heart', 18) ?><?php if ($wishCount): ?><span class="badge-count"><?= $wishCount ?></span><?php endif; ?></a>
      <span class="divider"></span>
      <a class="header-cart" href="<?= url('cart.php') ?>" aria-label="Cart">
        <span class="circle-btn"><?= icon('cart', 18) ?><span class="badge-count" data-cart-count <?= $cartCount ? '' : 'hidden' ?>><?= $cartCount ?></span></span>
        <span class="cart-text"><small>Your cart,</small><b><?= money($cartTotal) ?></b></span>
      </a>
    </div>
  </div>

  <div class="container nav-wrap">
    <div class="nav-bar">
      <button class="menu-toggle" type="button" aria-label="Menu" data-toggle-menu><?= icon('menu', 20) ?> <span>MENU</span></button>
      <nav class="main-nav" id="main-nav">
        <button class="nav-close" type="button" aria-label="Close menu" data-toggle-menu><?= icon('close', 20) ?></button>
        <?php
          $here = $_SERVER['REQUEST_URI'] ?? '';
          foreach (menu('header') as $m):
            $href = menu_url((string) ($m['url'] ?? ''));
            $type = $m['type'] ?? 'link';
            $children = array_filter((array) ($m['children'] ?? []), fn($c) => trim((string) ($c['label'] ?? '')) !== '');
            $isActive = $href === $here || ($href === url() && $current === 'index.php');
            $brandList = $type === 'brands' ? brands() : [];
            $hasDrop = $type === 'categories' || ($type === 'brands' && $brandList) || $children;
        ?>
          <?php if (!$hasDrop): ?>
            <a href="<?= e($href) ?>" class="<?= $isActive ? 'active' : '' ?>"><?= e($m['label'] ?? '') ?></a>
          <?php else: ?>
            <div class="nav-item has-drop">
              <a href="<?= e($href) ?>" class="<?= $isActive ? 'active' : '' ?>"><?= e($m['label'] ?? '') ?> <?= icon('chevron-down', 13) ?></a>
              <div class="dropdown">
                <?php if ($type === 'categories'): ?>
                  <a href="<?= url('shop.php') ?>">All Products</a>
                  <?php foreach (category_tree() as $c): ?>
                    <a href="<?= url('shop.php?category=' . (int) $c['id']) ?>" class="<?= $c['children'] ? 'drop-parent' : '' ?>"><?= e($c['name']) ?></a>
                    <?php foreach ($c['children'] as $sub): ?>
                      <a href="<?= url('shop.php?category=' . (int) $sub['id']) ?>" class="drop-child"><?= e($sub['name']) ?></a>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                <?php elseif ($type === 'brands'): ?>
                  <?php foreach ($brandList as $b): ?><a href="<?= url('shop.php?brand=' . rawurlencode($b)) ?>"><?= e($b) ?></a><?php endforeach; ?>
                <?php endif; ?>
                <?php foreach ($children as $c): ?><a href="<?= e(menu_url((string) ($c['url'] ?? ''))) ?>"><?= e($c['label']) ?></a><?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
      <?php if (setting('promo_text', 'Get 30% Discount Now') !== ''): ?>
        <a class="nav-promo" href="<?= e($promoHref) ?>">
          <span><?= e(setting('promo_text', 'Get 30% Discount Now')) ?></span>
          <?php if (setting('promo_badge', 'SALE') !== ''): ?><b><?= e(setting('promo_badge', 'SALE')) ?></b><?php endif; ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</header>
<main>
<?php $flashHtml = render_flashes(); if ($flashHtml !== ''): ?><div class="container flash-wrap"><?= $flashHtml ?></div><?php endif; ?>
