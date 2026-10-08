<?php
// Old address of the privacy page, kept so existing links keep working.
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require_installed();

header('Location: ' . base_url('page.php?slug=privacy-policy'), true, 301);
