<?php
/** robots.txt (served at /robots.txt by the .htaccess rule). */
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
$base = BASE_PATH;
echo "User-agent: *\n";
foreach (['/admin/', '/install/', '/cart.php', '/checkout.php', '/account.php', '/login.php', '/register.php', '/wishlist.php', '/order-success.php', '/pay.php', '/track.php'] as $path) {
    echo 'Disallow: ' . $base . $path . "\n";
}
echo "\nSitemap: " . abs_url(url(pretty_urls() ? 'sitemap.xml' : 'sitemap.php')) . "\n";
