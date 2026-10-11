<?php
/** XML sitemap for search engines. Also served as /sitemap.xml (see .htaccess). */
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');
$urls = [
    [url(), null, '1.0'],
    [url('tours.php?type=trip'), null, '0.9'],
    [url('tours.php?type=activity'), null, '0.9'],
    [url('destinations.php'), null, '0.8'],
    [url('blog.php'), null, '0.7'],
    [url('contact.php'), null, '0.5'],
];
foreach (db_all('SELECT slug, updated_at FROM ' . tbl('tours') . " WHERE status = 'active' ORDER BY id") as $r) {
    $urls[] = [url('tour.php?slug=' . urlencode($r['slug'])), $r['updated_at'], '0.9'];
}
foreach (db_all('SELECT slug, updated_at FROM ' . tbl('destinations') . " WHERE status = 'active' ORDER BY id") as $r) {
    $urls[] = [url('destination.php?slug=' . urlencode($r['slug'])), $r['updated_at'], '0.8'];
}
foreach (db_all('SELECT slug, published_at FROM ' . tbl('posts') . " WHERE status = 'published' AND published_at <= ? ORDER BY published_at DESC", [now()]) as $r) {
    $urls[] = [url('post.php?slug=' . urlencode($r['slug'])), $r['published_at'], '0.6'];
}
foreach (db_all('SELECT slug, updated_at FROM ' . tbl('pages') . " WHERE status = 'published' ORDER BY id") as $r) {
    $urls[] = [url('page.php?slug=' . urlencode($r['slug'])), $r['updated_at'], '0.4'];
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $mod, $priority]) {
    echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>'
        . ($mod ? '<lastmod>' . date('Y-m-d', strtotime($mod)) . '</lastmod>' : '')
        . '<priority>' . $priority . '</priority></url>' . "\n";
}
echo '</urlset>' . "\n";
