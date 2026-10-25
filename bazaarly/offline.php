<?php
define('ALLOW_IN_MAINTENANCE', true);
require __DIR__ . '/includes/bootstrap.php';
$brand = valid_color(setting('primary_color'), '#0d9488');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('Offline') ?> · <?= e(setting('site_name')) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<style>:root{--brand:<?= $brand ?>}</style>
</head>
<body class="center-page">
<?= icon_sprite() ?>
<div class="card narrow center">
  <span class="auth-icon"><?= icon('globe') ?></span>
  <h1><?= t('You\'re offline') ?></h1>
  <p class="muted"><?= t('Check your internet connection. Pages you opened recently are still available.') ?></p>
  <button class="btn btn-primary" type="button" onclick="location.reload()"><?= icon('refresh') ?><?= t('Try again') ?></button>
</div>
</body>
</html>
