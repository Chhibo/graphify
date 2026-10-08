<?php
// Public page header/footer.
declare(strict_types=1);

function page_header(string $title): void
{
    $store = setting('store_name', 'Free Downloads');
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title === $store ? $store : "$title · $store") ?></title>
<link rel="stylesheet" href="<?= e(base_url('assets/style.css')) ?>">
</head>
<body>
<header class="site-header">
  <a class="brand" href="<?= e(base_url()) ?>"><?= e($store) ?></a>
  <?php if (is_admin()): ?><a class="admin-link" href="<?= e(base_url('admin/')) ?>">Admin</a><?php endif; ?>
</header>
<main class="container">
<?php
}

function page_footer(): void
{
    ?>
</main>
<footer class="site-footer">
  <p>&copy; <?= date('Y') ?> <?= e(setting('store_name')) ?> ·
     <a href="<?= e(base_url('privacy.php')) ?>">Privacy</a></p>
</footer>
</body>
</html>
<?php
}
