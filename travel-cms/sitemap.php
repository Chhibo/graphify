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
    [url('plan-trip.php'), null, '0.6'],
    [url('contact.php'), null, '0.5'],
];
if (db_value('SELECT COUNT(*) FROM ' . tbl('faqs') . " WHERE tour_id IS NULL AND status = 'active'")) {
    $urls[] = [url('faq.php'), null, '0.5'];
}
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
// Every enabled extra language gets its own URL (?lang=xx), linked with hreflang alternates.
$langs = array_keys(enabled_languages());
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
foreach ($urls as [$loc, $mod, $priority]) {
    $alternates = '';
    if (count($langs) > 1) {
        foreach ($langs as $code) {
            $alternates .= '<xhtml:link rel="alternate" hreflang="' . $code . '" href="' . htmlspecialchars($code === default_lang() ? $loc : lang_switch_url($loc, $code), ENT_XML1) . '"/>';
        }
    }
    foreach (count($langs) > 1 ? $langs : [default_lang()] as $code) {
        $href = $code === default_lang() ? $loc : lang_switch_url($loc, $code);
        echo '  <url><loc>' . htmlspecialchars($href, ENT_XML1) . '</loc>'
            . ($mod ? '<lastmod>' . date('Y-m-d', strtotime($mod)) . '</lastmod>' : '')
            . '<priority>' . $priority . '</priority>' . $alternates . '</url>' . "\n";
    }
}
echo '</urlset>' . "\n";
