<?php
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\n";
foreach (['admin/', 'dashboard/', 'install/', 'ajax.php', 'oauth.php', 'cron.php', 'renew.php', 'unsubscribe.php', 'login-2fa.php'] as $p) {
    echo 'Disallow: ' . url($p) . "\n";
}
echo "\nSitemap: " . abs_url(clean_urls() ? url('sitemap.xml') : url('sitemap.php')) . "\n";
