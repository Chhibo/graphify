<?php
/** robots.txt for search engines. Also served as /robots.txt (see .htaccess). */
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
if (setting('search_engines', '1') === '0') {
    echo "User-agent: *\nDisallow: /\n";
    exit;
}
$base = rtrim((string) parse_url(base_url(), PHP_URL_PATH), '/');
echo "User-agent: *\n";
foreach (['/admin/', '/install/', '/includes/', '/book.php', '/voucher.php', '/my-booking.php', '/wishlist.php', '/subscribe.php', '/unsubscribe.php', '/review-booking.php', '/review.php'] as $path) {
    echo 'Disallow: ' . $base . $path . "\n";
}
echo "\nSitemap: " . url('sitemap.php') . "\n";
