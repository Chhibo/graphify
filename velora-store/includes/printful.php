<?php
/**
 * Printful integration (Printful API v1).
 *
 *  - Products you create in Printful (a "Manual order platform / API" store) are imported here
 *    with their mockup images, sizes, colors and prices.
 *  - Orders containing Printful products are sent to Printful for printing and shipping.
 *  - Printful notifies the store when a package ships (webhook) so the tracking link is saved.
 */

function printful_base(): string
{
    // PRINTFUL_API_BASE can be defined in config.php for testing against a mock server.
    return defined('PRINTFUL_API_BASE') ? rtrim(PRINTFUL_API_BASE, '/') : 'https://api.printful.com';
}

function printful_connected(): bool
{
    return setting('printful_token') !== '';
}

function printful_enabled(): bool
{
    return setting_on('printful_enabled') && printful_connected();
}

/**
 * Call the Printful API and return the "result" part of the answer.
 * Throws RuntimeException with Printful's error message on failure.
 */
function printful_request(string $method, string $path, ?array $body = null)
{
    $headers = ['Authorization: Bearer ' . setting('printful_token')];
    if (setting('printful_store_id') !== '') {
        $headers[] = 'X-PF-Store-Id: ' . setting('printful_store_id');
    }
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $res = http_request($method, printful_base() . $path, $body === null ? ['headers' => $headers] : ['headers' => $headers, 'json' => $body]);
        if ($res['status'] === 429 && $attempt < 3) {
            // Rate limited: Printful allows about 120 calls per minute.
            sleep(min(30, 5 * $attempt));
            continue;
        }
        break;
    }
    $json = $res['json'] ?? [];
    if ($res['status'] >= 200 && $res['status'] < 300 && array_key_exists('result', $json)) {
        return $json['result'];
    }
    $msg = $json['error']['message'] ?? (is_string($json['result'] ?? null) ? $json['result'] : '');
    if ($msg === '') {
        $msg = $res['error'] !== '' ? $res['error'] : 'HTTP ' . $res['status'];
    }
    throw new RuntimeException('Printful: ' . $msg);
}

/** Check the token. Returns a short description of the connected store. */
function printful_test(): string
{
    $products = printful_request('GET', '/store/products?limit=1');
    $name = '';
    try {
        $stores = printful_request('GET', '/stores');
        foreach ((array) $stores as $s) {
            if (setting('printful_store_id') === '' || (string) ($s['id'] ?? '') === setting('printful_store_id')) {
                $name = (string) ($s['name'] ?? '');
                break;
            }
        }
    } catch (RuntimeException $ex) {
        // Store-level tokens may not list stores; the product call above already proved the token works.
    }
    return $name !== '' ? $name : 'your Printful store';
}

/* ===================== Product import ===================== */

/** One page of Printful products: ['items' => [...], 'total' => int]. */
function printful_list_products(int $offset, int $limit = 20): array
{
    $headers = ['Authorization: Bearer ' . setting('printful_token')];
    if (setting('printful_store_id') !== '') {
        $headers[] = 'X-PF-Store-Id: ' . setting('printful_store_id');
    }
    $res = http_request('GET', printful_base() . '/store/products?offset=' . $offset . '&limit=' . $limit, ['headers' => $headers]);
    if ($res['status'] < 200 || $res['status'] >= 300 || !isset($res['json']['result'])) {
        throw new RuntimeException('Printful: ' . ($res['json']['error']['message'] ?? ($res['error'] ?: 'HTTP ' . $res['status'])));
    }
    return ['items' => (array) $res['json']['result'], 'total' => (int) ($res['json']['paging']['total'] ?? count($res['json']['result']))];
}

/** Description of the blank catalog product (e.g. "Unisex Staple T-Shirt | Bella + Canvas 3001"). */
function printful_catalog_description(int $catalogProductId): string
{
    static $cache = [];
    if ($catalogProductId <= 0) {
        return '';
    }
    if (!isset($cache[$catalogProductId])) {
        try {
            $r = printful_request('GET', '/products/' . $catalogProductId);
            $cache[$catalogProductId] = trim(strip_tags((string) ($r['product']['description'] ?? '')));
        } catch (RuntimeException $ex) {
            $cache[$catalogProductId] = '';
        }
    }
    return $cache[$catalogProductId];
}

/** Split "Product name / Black / XL" when Printful does not send size/color fields. */
function printful_variant_options(array $v): array
{
    $size = trim((string) ($v['size'] ?? ''));
    $color = trim((string) ($v['color'] ?? ''));
    if ($size === '' && $color === '') {
        $parts = array_map('trim', explode('/', (string) ($v['name'] ?? '')));
        if (count($parts) >= 3) {
            $color = $parts[count($parts) - 2];
            $size = $parts[count($parts) - 1];
        } elseif (count($parts) === 2) {
            $size = $parts[1];
        }
    }
    return [$size, $color];
}

/**
 * Import or update one Printful product. Keeps your own edits to the description,
 * category, home page sections and old price; refreshes name, images, sizes, colors and prices.
 * Returns 'created', 'updated' or 'skipped'.
 */
function printful_import_product(int $syncProductId, ?int $categoryId): string
{
    $data = printful_request('GET', '/store/products/' . $syncProductId);
    $sp = $data['sync_product'] ?? [];
    $variants = (array) ($data['sync_variants'] ?? []);

    $rows = [];
    $images = [];
    $catalogId = 0;
    foreach ($variants as $v) {
        if (!empty($v['is_ignored'])) {
            continue;
        }
        [$size, $color] = printful_variant_options($v);
        $preview = '';
        foreach ((array) ($v['files'] ?? []) as $f) {
            if (($f['type'] ?? '') === 'preview' && !empty($f['preview_url'])) {
                $preview = (string) $f['preview_url'];
            }
        }
        if ($preview === '' && !empty($v['product']['image'])) {
            $preview = (string) $v['product']['image'];
        }
        if ($preview !== '' && !in_array($preview, $images, true)) {
            $images[] = $preview;
        }
        $catalogId = $catalogId ?: (int) ($v['product']['product_id'] ?? 0);
        $status = (string) ($v['availability_status'] ?? 'active');
        $rows[] = [
            'printful_variant_id' => (string) $v['id'],
            'size' => mb_substr($size, 0, 60),
            'color' => mb_substr($color, 0, 60),
            'price' => round((float) ($v['retail_price'] ?? 0), 2),
            'sku' => mb_substr((string) ($v['sku'] ?? ''), 0, 120),
            'image' => mb_substr($preview, 0, 500),
            'active' => in_array($status, ['active', ''], true) ? 1 : 0,
        ];
    }
    $priced = array_filter($rows, fn($r) => $r['price'] > 0 && $r['active']);
    if (!$priced) {
        return 'skipped'; // no sellable variant (missing retail price or all discontinued)
    }

    $sizes = array_values(array_unique(array_filter(array_column($priced, 'size'), 'strlen')));
    $colors = array_values(array_unique(array_filter(array_column($priced, 'color'), 'strlen')));
    $main = (string) ($sp['thumbnail_url'] ?? '') ?: ($images[0] ?? '');
    $gallery = array_values(array_filter($images, fn($i) => $i !== $main));

    $fields = [
        'name' => mb_substr((string) ($sp['name'] ?? 'Printful product'), 0, 200),
        'price' => min(array_column($priced, 'price')),
        'image' => mb_substr($main, 0, 500),
        'gallery' => json_encode(array_slice($gallery, 0, 8)),
        'sizes' => implode(',', $sizes),
        'colors' => implode(',', $colors),
        'stock' => -1,
    ];

    $existing = q_one('SELECT id, description FROM products WHERE printful_id = ?', [(string) $syncProductId]);
    if ($existing) {
        $productId = (int) $existing['id'];
        if (trim((string) $existing['description']) === '') {
            $fields['description'] = printful_catalog_description($catalogId);
        }
        db_update('products', $productId, $fields);
        $result = 'updated';
    } else {
        $fields += [
            'category_id' => $categoryId ?: null,
            'slug' => unique_slug('products', $fields['name']),
            'brand' => '',
            'description' => printful_catalog_description($catalogId),
            'old_price' => 0,
            'rating' => 5.0,
            'reviews_count' => 0,
            'is_new' => 1,
            'is_trending' => 0,
            'is_flash' => 0,
            'active' => 1,
            'sort_order' => 0,
            'printful_id' => (string) $syncProductId,
            'created_at' => now(),
        ];
        $productId = db_insert('products', $fields);
        $result = 'created';
    }

    // Replace variants, keeping ids of variants that still exist so carts keep working.
    $old = [];
    foreach (q_all('SELECT id, printful_variant_id FROM product_variants WHERE product_id = ?', [$productId]) as $o) {
        $old[$o['printful_variant_id']] = (int) $o['id'];
    }
    foreach ($rows as $r) {
        if (isset($old[$r['printful_variant_id']])) {
            db_update('product_variants', $old[$r['printful_variant_id']], $r);
            unset($old[$r['printful_variant_id']]);
        } else {
            db_insert('product_variants', $r + ['product_id' => $productId]);
        }
    }
    foreach ($old as $id) {
        q('UPDATE product_variants SET active = 0 WHERE id = ?', [$id]);
    }
    return $result;
}

/** Hide store products whose Printful product was deleted. */
function printful_hide_missing(array $seenIds): int
{
    $hidden = 0;
    foreach (q_all("SELECT id, printful_id FROM products WHERE printful_id <> '' AND active = 1") as $p) {
        if (!in_array($p['printful_id'], $seenIds, true)) {
            q('UPDATE products SET active = 0 WHERE id = ?', [$p['id']]);
            $hidden++;
        }
    }
    return $hidden;
}

/* ===================== Orders ===================== */

function order_has_printful_items(int $orderId): bool
{
    return (int) q_val("SELECT COUNT(*) FROM order_items WHERE order_id = ? AND printful_variant_id <> ''", [$orderId]) > 0;
}

/**
 * Create the order in Printful. With $confirm = false it is saved as a draft in Printful
 * (you review and pay it in your Printful dashboard); with true it goes straight to production.
 */
function printful_send_order(array $order, bool $confirm): string
{
    if ($order['printful_order_id'] !== '') {
        return $order['printful_order_id'];
    }
    $items = [];
    foreach (order_items((int) $order['id']) as $it) {
        if ($it['printful_variant_id'] === '') {
            continue;
        }
        $items[] = [
            'sync_variant_id' => (int) $it['printful_variant_id'],
            'quantity' => (int) $it['qty'],
            'retail_price' => number_format((float) $it['price'], 2, '.', ''),
            'name' => $it['name'],
        ];
    }
    if (!$items) {
        throw new RuntimeException('This order has no Printful products.');
    }
    $countryCode = $order['country_code'] !== '' ? $order['country_code'] : country_code((string) $order['country']);
    if ($countryCode === '') {
        throw new RuntimeException('The order has no valid country. Edit the country code on the order first.');
    }
    $recipient = [
        'name' => $order['customer_name'],
        'address1' => $order['address'],
        'city' => $order['city'],
        'country_code' => $countryCode,
        'zip' => $order['zip'],
        'phone' => $order['phone'],
    ];
    if (filter_var($order['email'], FILTER_VALIDATE_EMAIL)) {
        $recipient['email'] = $order['email'];
    }
    $state = trim((string) $order['state']);
    if ($state !== '') {
        if (preg_match('/^[A-Za-z]{2,3}$/', $state)) {
            $recipient['state_code'] = strtoupper($state);
        } else {
            $recipient['state_name'] = $state;
        }
    }
    $payload = [
        'external_id' => substr(preg_replace('/[^A-Za-z0-9_-]/', '', $order['order_number']), 0, 32),
        'shipping' => 'STANDARD',
        'recipient' => $recipient,
        'items' => $items,
        'retail_costs' => [
            'currency' => strtoupper(setting('currency_code', 'USD')),
            'subtotal' => number_format((float) $order['subtotal'], 2, '.', ''),
            'discount' => number_format((float) ($order['discount'] ?? 0), 2, '.', ''),
            'shipping' => number_format((float) $order['shipping'], 2, '.', ''),
            'total' => number_format((float) $order['total'], 2, '.', ''),
        ],
    ];
    $result = printful_request('POST', '/orders' . ($confirm ? '?confirm=1' : ''), $payload);
    $pfId = (string) ($result['id'] ?? '');
    if ($pfId === '') {
        throw new RuntimeException('Printful did not return an order id.');
    }
    q('UPDATE orders SET printful_order_id = ?, printful_status = ?, updated_at = ? WHERE id = ?',
        [$pfId, (string) ($result['status'] ?? ($confirm ? 'pending' : 'draft')), now(), $order['id']]);
    return $pfId;
}

/** Called after an order is confirmed: sends it to Printful when the automatic option is on. Never throws. */
function printful_auto_send(int $orderId): void
{
    if (!printful_enabled() || !order_has_printful_items($orderId)) {
        return;
    }
    $order = q_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (!$order || $order['printful_order_id'] !== '') {
        return;
    }
    $auto = $order['payment_status'] === 'paid' ? setting_on('printful_auto_paid') : ($order['payment_method'] === 'cod' && setting_on('printful_auto_cod'));
    if (!$auto) {
        return;
    }
    try {
        printful_send_order($order, setting_on('printful_confirm'));
    } catch (Throwable $ex) {
        q('UPDATE orders SET printful_status = ? WHERE id = ?', ['error: ' . mb_substr($ex->getMessage(), 0, 30), $orderId]);
    }
}

/** Read the latest status + tracking from Printful and save it on the order. */
function printful_refresh_order(array $order): array
{
    $r = printful_request('GET', '/orders/' . rawurlencode($order['printful_order_id']));
    printful_apply_status($order, (string) ($r['status'] ?? ''), (array) ($r['shipments'] ?? []));
    return $r;
}

function printful_apply_status(array $order, string $pfStatus, array $shipments): void
{
    $tracking = $order['tracking_url'];
    foreach ($shipments as $s) {
        if (!empty($s['tracking_url'])) {
            $tracking = (string) $s['tracking_url'];
        }
    }
    $status = $order['status'];
    if ($pfStatus === 'fulfilled' || ($shipments && in_array($status, ['pending', 'processing'], true))) {
        $status = $status === 'delivered' ? 'delivered' : 'shipped';
    } elseif (in_array($pfStatus, ['inprocess', 'pending'], true) && $status === 'pending') {
        $status = 'processing';
    }
    q('UPDATE orders SET printful_status = ?, tracking_url = ?, status = ?, updated_at = ? WHERE id = ?',
        [$pfStatus !== '' ? $pfStatus : $order['printful_status'], mb_substr($tracking, 0, 255), $status, now(), $order['id']]);
}

/** Ask Printful to call our webhook when packages ship or orders change. */
function printful_register_webhook(): void
{
    printful_request('POST', '/webhooks', [
        'url' => full_url('printful-webhook.php?key=' . setting('printful_webhook_key')),
        'types' => ['package_shipped', 'order_updated', 'order_failed', 'order_canceled', 'order_put_hold'],
    ]);
}

function printful_status_label(string $s): string
{
    $labels = [
        'draft' => 'Draft (confirm & pay it in Printful)',
        'pending' => 'Submitted, waiting for payment/processing',
        'failed' => 'Failed (see Printful)',
        'canceled' => 'Cancelled',
        'inprocess' => 'In production',
        'onhold' => 'On hold (see Printful)',
        'partial' => 'Partially shipped',
        'fulfilled' => 'Shipped',
    ];
    return $labels[$s] ?? ($s === '' ? 'Not sent' : $s);
}
