<?php
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');
[$where, $params] = visible_listing_where();
$ads = q_all("SELECT l.id, l.title, l.updated_at FROM listings l JOIN users u ON u.id = l.user_id WHERE $where ORDER BY l.updated_at DESC LIMIT 45000", $params);

$urls = [[full_url(), date('c'), '1.0'], [abs_url(search_url([])), date('c'), '0.8'], [full_url('categories.php'), null, '0.6']];
foreach (categories_all() as $id => $c) {
    $urls[] = [abs_url(category_url($id)), null, '0.7'];
}
foreach (q_all('SELECT slug, updated_at FROM pages') as $p) {
    $urls[] = [abs_url(page_url($p['slug'])), date('c', strtotime($p['updated_at'])), '0.3'];
}
if (shops_enabled()) {
    foreach (q_all("SELECT s.slug, s.updated_at FROM shops s JOIN users u ON u.id = s.user_id WHERE s.status = 'active' AND u.status = 'active'") as $sh) {
        $urls[] = [abs_url(shop_url($sh)), date('c', strtotime($sh['updated_at'])), '0.6'];
    }
}
foreach ($ads as $l) {
    $urls[] = [abs_url(listing_url($l)), date('c', strtotime($l['updated_at'])), '0.6'];
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $mod, $prio]) {
    echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>' . ($mod ? '<lastmod>' . $mod . '</lastmod>' : '') . '<priority>' . $prio . '</priority></url>' . "\n";
}
echo '</urlset>';
