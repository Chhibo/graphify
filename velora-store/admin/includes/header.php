<?php
/** @var string $adminTitle */
$admin = current_admin();
$self = basename($_SERVER['SCRIPT_NAME'] ?? '');
$newOrders = (int) q_val("SELECT COUNT(*) FROM orders WHERE status = 'pending' AND payment_status <> 'failed' AND payment_status <> 'unpaid'");
$counts = [
    'orders.php' => $newOrders,
    'reviews.php' => (int) q_val('SELECT COUNT(*) FROM reviews WHERE approved = 0'),
    'messages.php' => (int) q_val('SELECT COUNT(*) FROM messages WHERE is_read = 0'),
];
$menu = [
    'dashboard.php' => ['Dashboard', 'package'],
    'orders.php' => ['Orders', 'cart'],
    'products.php' => ['Products', 'heart'],
    'categories.php' => ['Categories', 'menu'],
    'coupons.php' => ['Coupons', 'tag'],
    'shipping.php' => ['Delivery options', 'truck'],
    'reviews.php' => ['Product reviews', 'star'],
    'testimonials.php' => ['Testimonials', 'check'],
    'pages.php' => ['Pages & Blog', 'mail'],
    'menus.php' => ['Menus', 'menu'],
    'messages.php' => ['Messages', 'mail'],
    'subscribers.php' => ['Subscribers', 'user'],
    'printful.php' => ['Printful', 'package'],
    'settings.php' => ['Settings', 'refresh'],
];
$active = ['order.php' => 'orders.php', 'product-edit.php' => 'products.php', 'page-edit.php' => 'pages.php'][$self] ?? $self;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($adminTitle ?? 'Admin') . ' · ' . setting('store_name', 'Store')) ?></title>
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>?v=<?= APP_VERSION ?>">
<?php if (preg_match('/^#[0-9a-fA-F]{6}$/', setting('theme_color'))): ?><style>:root{--primary:<?= e(setting('theme_color')) ?>}</style><?php endif; ?>
</head>
<body>
<div class="admin">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= url('admin/dashboard.php') ?>"><?= e(setting('store_name', 'Store')) ?><span>admin</span></a>
    <nav>
      <?php foreach ($menu as $file => [$label, $ico]): ?>
        <a href="<?= url('admin/' . $file) ?>" class="<?= $active === $file ? 'active' : '' ?>"><?= icon($ico, 18) ?> <?= e($label) ?>
          <?php if (!empty($counts[$file])): ?><b class="pill"><?= (int) $counts[$file] ?></b><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a href="<?= url() ?>" target="_blank"><?= icon('arrow-right', 16) ?> View store</a>
      <a href="<?= url('admin/account.php') ?>"><?= icon('user', 16) ?> My account</a>
      <a href="<?= url('admin/logout.php') ?>"><?= icon('close', 16) ?> Log out</a>
    </div>
  </aside>
  <div class="main">
    <header class="topbar">
      <button class="menu-btn" type="button" onclick="document.getElementById('sidebar').classList.toggle('open')"><?= icon('menu', 22) ?></button>
      <h1><?= e($adminTitle ?? 'Admin') ?></h1>
      <span class="who">Hi, <?= e($admin['name'] ?? '') ?></span>
    </header>
    <div class="content">
      <?php if (is_dir(APP_ROOT . '/install')): ?>
        <div class="alert alert-warn"><b>Security:</b> please delete the <code>install</code> folder from your server now that the store is installed.</div>
      <?php endif; ?>
      <?= render_flashes() ?>
