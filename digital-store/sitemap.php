<?php
// XML sitemap for Google Search Console: submit https://your-domain/sitemap.php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require_installed();

header('Content-Type: application/xml; charset=utf-8');
$urls = [[base_url(), null]];
foreach (db()->query('SELECT id, created_at FROM products WHERE active = 1 ORDER BY id DESC') as $p) {
    $urls[] = [base_url('product.php?id=' . $p['id']), (int)$p['created_at']];
}
foreach (db()->query('SELECT slug FROM categories') as $c) {
    $urls[] = [base_url('category.php?slug=' . rawurlencode($c['slug'])), null];
}
foreach (db()->query('SELECT slug, updated_at FROM pages') as $pg) {
    $urls[] = [base_url('page.php?slug=' . rawurlencode($pg['slug'])), (int)$pg['updated_at']];
}
$urls[] = [base_url('contact.php'), null];

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n", '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
foreach ($urls as [$loc, $mod]) {
    echo '  <url><loc>', htmlspecialchars($loc, ENT_XML1), '</loc>', $mod ? '<lastmod>' . date('Y-m-d', $mod) . '</lastmod>' : '', "</url>\n";
}
echo "</urlset>\n";
