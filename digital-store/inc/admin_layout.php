<?php
declare(strict_types=1);

function admin_header(string $title): void
{
    $nav = [
        'index.php' => 'Dashboard',
        'products.php' => 'Products',
        'networks.php' => 'CPA Networks',
        'postbacks.php' => 'Postback log',
        'settings.php' => 'Settings',
    ];
    $current = basename($_SERVER['SCRIPT_NAME']);
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?> · Admin</title>
<link rel="stylesheet" href="<?= e(base_url('assets/style.css')) ?>">
</head>
<body class="admin-body">
<header class="admin-header">
  <a class="brand" href="<?= e(base_url('admin/')) ?>"><?= e(setting('store_name')) ?> · Admin</a>
  <nav>
    <?php foreach ($nav as $file => $label): ?>
      <a href="<?= e($file) ?>" class="<?= $current === $file ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
    <a href="<?= e(base_url()) ?>" target="_blank">View store ↗</a>
    <a href="logout.php">Log out</a>
  </nav>
</header>
<main class="admin-main">
  <?php if ($m = flash()): ?><p class="flash"><?= e($m) ?></p><?php endif; ?>
  <h1><?= e($title) ?></h1>
<?php
}

function admin_footer(): void
{
    echo "</main>\n</body>\n</html>\n";
}
