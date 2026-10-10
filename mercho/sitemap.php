<?php
/** XML sitemap for Google / Bing: /sitemap.xml (or /sitemap.php). Submit it in Google Search Console. */
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');

$urls = [[abs_url(url()), null, '1.0'], [abs_url(url('shop.php')), null, '0.9'], [abs_url(url('blog.php')), null, '0.5'], [abs_url(url('contact.php')), null, '0.4']];
foreach (categories() as $c) {
    $urls[] = [abs_url(category_url($c)), null, '0.8'];
}
foreach (q_all('SELECT id, slug, created_at FROM products WHERE active = 1 ORDER BY id DESC') as $p) {
    $urls[] = [abs_url(product_url($p)), $p['created_at'], '0.8'];
}
foreach (q_all('SELECT slug, type, created_at FROM pages WHERE active = 1') as $pg) {
    $urls[] = [abs_url(page_url($pg['slug'], $pg['type'])), $pg['created_at'], $pg['type'] === 'post' ? '0.6' : '0.3'];
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $mod, $prio]) {
    echo '  <url><loc>' . e($loc) . '</loc>' . ($mod ? '<lastmod>' . e(date('Y-m-d', strtotime($mod))) . '</lastmod>' : '') . '<priority>' . $prio . '</priority></url>' . "\n";
}
echo '</urlset>';
