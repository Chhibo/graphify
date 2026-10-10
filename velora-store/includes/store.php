<?php
/**
 * Store features: rich text, custom product options, coupons, shipping methods,
 * editable menus, product reviews and simple email sending.
 */

/* ===================== Rich text ===================== */

/**
 * Output text written in the admin editor. Content with HTML tags (from the visual editor) is shown as is
 * (only the store admin can write it); plain text (old products, Printful) keeps its line breaks.
 */
function rich_text(?string $text): string
{
    $text = (string) $text;
    if ($text !== strip_tags($text)) {
        // Remove scripts/inline event handlers in case HTML was pasted from another site.
        $text = preg_replace('#<(script|style|iframe(?![^>]*(youtube\.com|youtube-nocookie\.com|player\.vimeo\.com|google\.com/maps)))[^>]*>.*?</\1>#is', '', $text);
        $text = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', (string) $text);
        $text = preg_replace('#(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2#i', '$1="#"', (string) $text);
        return '<div class="rich">' . $text . '</div>';
    }
    return '<div class="rich">' . nl2br(e($text)) . '</div>';
}

/* ===================== Custom product options ===================== */

/**
 * Extra options of a product, e.g. [['name' => 'Material', 'values' => [['label' => 'Silk', 'price' => 5.0], ...]]]
 */
function product_extra_options(array $p): array
{
    $list = json_decode((string) ($p['extra_options'] ?? ''), true);
    if (!is_array($list)) {
        return [];
    }
    $out = [];
    foreach ($list as $o) {
        $name = trim((string) ($o['name'] ?? ''));
        $values = [];
        foreach ((array) ($o['values'] ?? []) as $v) {
            $label = trim((string) ($v['label'] ?? ''));
            if ($label !== '') {
                $values[] = ['label' => $label, 'price' => round((float) ($v['price'] ?? 0), 2)];
            }
        }
        if ($name !== '' && $values) {
            $out[] = ['name' => $name, 'values' => $values];
        }
    }
    return $out;
}

/**
 * Parse the admin text "Cotton, Silk +5, Wool +7.50" into values with extra prices.
 */
function parse_option_values(string $text): array
{
    $values = [];
    foreach (explode(',', $text) as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $price = 0.0;
        if (preg_match('/^(.*?)\s*\(?\s*\+\s*\$?\s*([0-9]+(?:\.[0-9]+)?)\s*\)?$/', $part, $m)) {
            $part = trim($m[1]);
            $price = (float) $m[2];
        }
        if ($part !== '') {
            $values[] = ['label' => mb_substr($part, 0, 60), 'price' => round($price, 2)];
        }
    }
    return $values;
}

function option_values_text(array $values): string
{
    return implode(', ', array_map(fn($v) => $v['label'] . ($v['price'] > 0 ? ' +' . rtrim(rtrim(number_format($v['price'], 2, '.', ''), '0'), '.') : ''), $values));
}

/**
 * Check the options a customer chose against the product. Returns [validChoices, extraPrice].
 * Unknown values fall back to the first value of the option.
 */
function resolve_options(array $p, array $chosen): array
{
    $result = [];
    $extra = 0.0;
    foreach (product_extra_options($p) as $i => $o) {
        $pick = $o['values'][0];
        foreach ($o['values'] as $v) {
            if ((string) ($chosen[$o['name']] ?? ($chosen[$i] ?? '')) === $v['label']) {
                $pick = $v;
            }
        }
        $result[$o['name']] = $pick['label'];
        $extra += $pick['price'];
    }
    return [$result, $extra];
}

function options_text(array $options): string
{
    return implode(', ', array_map(fn($k, $v) => $k . ': ' . $v, array_keys($options), $options));
}

/** "Additional information" rows of a product: [['Material', 'Cotton'], ...] */
function product_additional_info(array $p): array
{
    $rows = json_decode((string) ($p['additional_info'] ?? ''), true);
    $out = [];
    foreach (is_array($rows) ? $rows : [] as $r) {
        if (is_array($r) && trim((string) ($r[0] ?? '')) !== '') {
            $out[] = [trim((string) $r[0]), trim((string) ($r[1] ?? ''))];
        }
    }
    return $out;
}

/* ===================== Coupons ===================== */

function find_coupon(string $code): ?array
{
    $code = strtoupper(trim($code));
    return $code === '' ? null : q_one('SELECT * FROM coupons WHERE UPPER(code) = ?', [$code]);
}

/** Returns an error message, or '' when the coupon can be used for this subtotal. */
function coupon_error(?array $c, float $subtotal): string
{
    if (!$c || !(int) $c['active']) {
        return 'This coupon code is not valid.';
    }
    $now = now();
    if (!empty($c['starts_at']) && $c['starts_at'] > $now) {
        return 'This coupon is not active yet.';
    }
    if (!empty($c['expires_at']) && $c['expires_at'] < $now) {
        return 'This coupon has expired.';
    }
    if ((int) $c['max_uses'] > 0 && (int) $c['used_count'] >= (int) $c['max_uses']) {
        return 'This coupon has reached its usage limit.';
    }
    if ((float) $c['min_order'] > 0 && $subtotal < (float) $c['min_order']) {
        return 'This coupon needs a minimum order of ' . money($c['min_order']) . '.';
    }
    return '';
}

function coupon_label(array $c): string
{
    if ($c['type'] === 'free_shipping') {
        return 'Free delivery';
    }
    return $c['type'] === 'percent' ? rtrim(rtrim(number_format((float) $c['value'], 2), '0'), '.') . '% off' : money($c['value']) . ' off';
}

/* ===================== Shipping methods ===================== */

function shipping_methods(bool $activeOnly = true): array
{
    static $cache = [];
    if (!isset($cache[$activeOnly])) {
        $cache[$activeOnly] = q_all('SELECT * FROM shipping_methods' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY sort_order, id');
    }
    return $cache[$activeOnly];
}

/** Shipping method ids allowed for a product ([] = all methods). */
function product_shipping_ids(array $p): array
{
    return array_values(array_filter(array_map('intval', explode(',', (string) ($p['shipping_methods'] ?? '')))));
}

/**
 * Shipping methods the customer can choose for these cart items.
 * Only products with shipping enabled count. Methods must be allowed by every product;
 * if the products have nothing in common, all their methods are offered.
 */
function cart_shipping_options(array $items): array
{
    $shippable = array_filter($items, fn($it) => (int) ($it['product']['shipping_enabled'] ?? 1) === 1);
    if (!$shippable) {
        return [];
    }
    $all = shipping_methods();
    $common = null;
    $union = [];
    foreach ($shippable as $it) {
        $allowed = product_shipping_ids($it['product']);
        $ids = $allowed ? $allowed : array_map('intval', array_column($all, 'id'));
        $common = $common === null ? $ids : array_values(array_intersect($common, $ids));
        $union = array_merge($union, $ids);
    }
    $ids = $common ?: array_unique($union);
    return array_values(array_filter($all, fn($m) => in_array((int) $m['id'], $ids, true)));
}

/* ===================== Menus ===================== */

function default_menus(): array
{
    return [
        'header' => [
            ['label' => 'Home', 'url' => '', 'type' => 'link'],
            ['label' => 'Shop', 'url' => 'shop.php', 'type' => 'categories'],
            ['label' => 'New Arrivals', 'url' => 'shop.php?sort=new', 'type' => 'link'],
            ['label' => 'On Sale', 'url' => 'shop.php?sale=1', 'type' => 'link'],
            ['label' => 'Brands', 'url' => 'shop.php', 'type' => 'brands'],
            ['label' => 'Blog', 'url' => 'blog.php', 'type' => 'link'],
            ['label' => 'Contact', 'url' => 'contact.php', 'type' => 'link'],
        ],
        'topbar' => [
            ['label' => 'About', 'url' => 'page.php?slug=about'],
            ['label' => 'Track Order', 'url' => 'track.php'],
            ['label' => 'Wishlist', 'url' => 'wishlist.php'],
            ['label' => 'Checkout', 'url' => 'checkout.php'],
        ],
        'footer' => [
            ['title' => 'Company', 'links' => [
                ['label' => 'About', 'url' => 'page.php?slug=about'], ['label' => 'Contact', 'url' => 'contact.php'],
                ['label' => 'FAQ', 'url' => 'page.php?slug=faq'], ['label' => 'Blog', 'url' => 'blog.php']]],
            ['title' => 'Help', 'links' => [
                ['label' => 'Customer Support', 'url' => 'page.php?slug=customer-support'], ['label' => 'Delivery Details', 'url' => 'page.php?slug=delivery-details'],
                ['label' => 'Terms & Conditions', 'url' => 'page.php?slug=terms'], ['label' => 'Privacy Policy', 'url' => 'page.php?slug=privacy']]],
            ['title' => 'FAQ', 'links' => [
                ['label' => 'Track Order', 'url' => 'track.php'], ['label' => 'My Cart', 'url' => 'cart.php'],
                ['label' => 'Wishlist', 'url' => 'wishlist.php'], ['label' => 'Payments', 'url' => 'checkout.php']]],
            ['title' => 'Resources', 'links' => [
                ['label' => 'New Arrivals', 'url' => 'shop.php?sort=new'], ['label' => 'On Sale', 'url' => 'shop.php?sale=1'],
                ['label' => 'Style Tips', 'url' => 'blog.php'], ['label' => 'All Products', 'url' => 'shop.php']]],
        ],
    ];
}

function menu(string $name): array
{
    $saved = json_decode(setting('menu_' . $name), true);
    return is_array($saved) ? $saved : (default_menus()[$name] ?? []);
}

/** Menu links may be store pages ("shop.php?sale=1"), full addresses ("https://...") or anchors ("#"). */
function menu_url(string $u): string
{
    $u = trim($u);
    if ($u === '' || $u === '/') {
        return url();
    }
    if (preg_match('#^(https?://|mailto:|tel:|\#)#i', $u)) {
        return $u;
    }
    if (preg_match('#^[a-z]+:#i', $u)) {
        return '#'; // block javascript: and other schemes
    }
    return url(ltrim($u, '/'));
}

/* ===================== Product reviews ===================== */

function product_reviews(int $productId): array
{
    return q_all('SELECT * FROM reviews WHERE product_id = ? AND approved = 1 ORDER BY id DESC', [$productId]);
}

/** Recalculate a product's rating and review count from its approved reviews. */
function refresh_product_rating(int $productId): void
{
    $row = q_one('SELECT COUNT(*) AS n, AVG(rating) AS avg FROM reviews WHERE product_id = ? AND approved = 1', [$productId]);
    if ($row && (int) $row['n'] > 0) {
        q('UPDATE products SET rating = ?, reviews_count = ? WHERE id = ?', [round((float) $row['avg'] * 2) / 2, (int) $row['n'], $productId]);
    }
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out !== '' ? $out : '?';
}

/* ===================== Email ===================== */
// Sending is done by includes/mailer.php (PHP mail() or your SMTP server, see Admin > Settings > Email).
