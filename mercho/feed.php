<?php
/**
 * Product feed for ads catalogs: Google Merchant Center (Google Shopping), Meta (Facebook / Instagram Shop)
 * and TikTok Catalog all read this format. Admin > Settings > Sales tools shows the links.
 *
 *   feed.php                 XML (RSS 2.0 with g: tags) - recommended
 *   feed.php?format=csv      CSV
 *   &currency=EUR            prices in another currency set up in Admin > Settings > Currencies
 */
require __DIR__ . '/includes/bootstrap.php';

if (!setting_on('feed_enabled')) {
    http_response_code(404);
    exit('Product feed is turned off.');
}

$cur = base_currency();
$code = strtoupper((string) ($_GET['currency'] ?? ''));
if ($code !== '' && isset(store_currencies()[$code])) {
    $cur = store_currencies()[$code];
}
$price = fn(float $amount) => number_format(convert_amount($amount, $cur), 2, '.', '') . ' ' . $cur['code'];
$plain = fn(string $html, int $max) => mb_substr(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['<br', '</p>', '</li>'], [' <br', '</p> ', '</li> '], $html)), ENT_QUOTES, 'UTF-8'))), 0, $max);

$cats = [];
foreach (categories() as $c) {
    $cats[(int) $c['id']] = $c;
}
$catPath = function (?int $id) use ($cats): string {
    $c = $cats[(int) $id] ?? null;
    if (!$c) {
        return '';
    }
    $parent = !empty($c['parent_id']) && isset($cats[(int) $c['parent_id']]) ? $cats[(int) $c['parent_id']]['name'] . ' > ' : '';
    return $parent . $c['name'];
};

// One feed item per product, or one per size/color when the product has size/color options with their own stock.
$items = [];
foreach (q_all('SELECT * FROM products WHERE active = 1 ORDER BY id') as $p) {
    $images = array_map(fn($i) => abs_url(img_url($i)), product_images($p));
    if (!$images) {
        continue; // ads platforms reject products without an image
    }
    $desc = $plain((string) ($p['description'] ?: $p['short_description'] ?: $p['name']), 5000);
    $base = [
        'title' => mb_substr($p['name'], 0, 150),
        'description' => $desc !== '' ? $desc : $p['name'],
        'link' => abs_url(product_url($p)),
        'image_link' => $images[0],
        'additional_image_link' => implode(',', array_slice($images, 1, 10)),
        'brand' => $p['brand'] !== '' ? $p['brand'] : setting('store_name'),
        'condition' => 'new',
        'product_type' => $catPath($p['category_id'] ? (int) $p['category_id'] : null),
        'identifier_exists' => 'no',
        'item_group_id' => '',
        'size' => '',
        'color' => '',
    ];
    $prices = function (float $sale) use ($p, $price): array {
        $old = (float) $p['old_price'];
        return $old > $sale ? ['price' => $price($old), 'sale_price' => $price($sale)] : ['price' => $price($sale), 'sale_price' => ''];
    };
    $variants = product_variants((int) $p['id']);
    if ($variants) {
        foreach ($variants as $v) {
            $items[] = array_merge($base, $prices(variant_price($v, $p)), [
                'id' => 'P' . $p['id'] . '-V' . $v['id'],
                'item_group_id' => 'P' . $p['id'],
                'size' => (string) $v['size'],
                'color' => (string) $v['color'],
                'image_link' => $v['image'] !== '' ? abs_url(img_url($v['image'])) : $base['image_link'],
                'availability' => (int) $v['stock'] === 0 || (int) $p['stock'] === 0 ? 'out_of_stock' : 'in_stock',
            ]);
        }
    } else {
        $items[] = array_merge($base, $prices((float) $p['price']), [
            'id' => 'P' . $p['id'],
            'size' => implode(',', str_list($p['sizes'])),
            'color' => implode('/', array_slice(str_list($p['colors']), 0, 3)),
            'availability' => (int) $p['stock'] === 0 ? 'out_of_stock' : 'in_stock',
        ]);
    }
}

$columns = ['id', 'item_group_id', 'title', 'description', 'link', 'image_link', 'additional_image_link', 'availability', 'price', 'sale_price',
    'brand', 'condition', 'product_type', 'identifier_exists', 'size', 'color'];

if (($_GET['format'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: inline; filename="products-feed.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $columns);
    foreach ($items as $it) {
        fputcsv($out, array_map(fn($c) => (string) ($it[$c] ?? ''), $columns));
    }
    exit;
}

header('Content-Type: application/xml; charset=utf-8');
$x = fn($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel>' . "\n";
echo '<title>' . $x(setting('store_name')) . '</title><link>' . $x(abs_url(url())) . '</link><description>' . $x(setting('store_tagline') ?: setting('store_name')) . '</description>' . "\n";
foreach ($items as $it) {
    echo '<item>';
    foreach ($columns as $c) {
        if (($it[$c] ?? '') === '') {
            continue;
        }
        if ($c === 'additional_image_link') {
            foreach (explode(',', $it[$c]) as $img) {
                echo '<g:additional_image_link>' . $x($img) . '</g:additional_image_link>';
            }
            continue;
        }
        echo in_array($c, ['title', 'description', 'link'], true) ? '<' . $c . '>' . $x($it[$c]) . '</' . $c . '>' : '<g:' . $c . '>' . $x($it[$c]) . '</g:' . $c . '>';
    }
    echo "</item>\n";
}
echo '</channel></rss>';
