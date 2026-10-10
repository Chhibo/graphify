<?php
/**
 * Session-based shopping cart.
 * $_SESSION['cart'][key] = ['product_id' => int, 'variant_id' => int, 'size' => string, 'color' => string, 'qty' => int]
 * variant_id is set for products that have size/color variants (e.g. imported from Printful).
 * options holds custom option choices, e.g. ['Gift wrap' => 'Yes'].
 * $_SESSION['coupon'] = coupon code, $_SESSION['shipping_method'] = chosen shipping method id.
 */

function cart_raw(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_add(int $productId, int $qty, string $size = '', string $color = '', int $variantId = 0, array $options = []): void
{
    $key = md5($productId . '|' . $variantId . '|' . $size . '|' . $color . '|' . json_encode($options));
    $current = $_SESSION['cart'][$key]['qty'] ?? 0;
    $_SESSION['cart'][$key] = [
        'product_id' => $productId,
        'variant_id' => $variantId,
        'size' => $size,
        'color' => $color,
        'options' => $options,
        'qty' => min(99, max(1, $current + $qty)),
    ];
}

function cart_set_qty(string $key, int $qty): void
{
    if (!isset($_SESSION['cart'][$key])) {
        return;
    }
    if ($qty <= 0) {
        unset($_SESSION['cart'][$key]);
    } else {
        $_SESSION['cart'][$key]['qty'] = min(99, $qty);
    }
}

function cart_clear(): void
{
    unset($_SESSION['cart']);
}

function cart_count(): int
{
    return array_sum(array_column(cart_raw(), 'qty'));
}

/** Cart lines joined with current product data (prices always come from the database). */
function cart_items(): array
{
    $raw = cart_raw();
    if (!$raw) {
        return [];
    }
    $ids = array_unique(array_map(fn($l) => (int) $l['product_id'], $raw));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $products = [];
    foreach (q_all("SELECT * FROM products WHERE active = 1 AND id IN ($placeholders)", array_values($ids)) as $p) {
        $products[(int) $p['id']] = $p;
    }
    $variantIds = array_filter(array_map(fn($l) => (int) ($l['variant_id'] ?? 0), $raw));
    $variants = [];
    if ($variantIds) {
        $vp = implode(',', array_fill(0, count($variantIds), '?'));
        foreach (q_all("SELECT * FROM product_variants WHERE active = 1 AND id IN ($vp)", array_values($variantIds)) as $v) {
            $variants[(int) $v['id']] = $v;
        }
    }
    $items = [];
    foreach ($raw as $key => $line) {
        $p = $products[(int) $line['product_id']] ?? null;
        $variantId = (int) ($line['variant_id'] ?? 0);
        $variant = $variantId ? ($variants[$variantId] ?? null) : null;
        if (!$p || ($variantId && (!$variant || (int) $variant['product_id'] !== (int) $p['id']))) {
            unset($_SESSION['cart'][$key]); // product removed or this size/color no longer available
            continue;
        }
        [$options, $extra] = resolve_options($p, (array) ($line['options'] ?? []));
        $price = ($variant ? variant_price($variant, $p) : (float) $p['price']) + $extra;
        $items[] = [
            'key' => $key,
            'product' => $p,
            'variant' => $variant,
            'size' => $line['size'],
            'color' => $line['color'],
            'options' => $options,
            'qty' => (int) $line['qty'],
            'price' => $price,
            'line_total' => $price * (int) $line['qty'],
        ];
    }
    return $items;
}

/**
 * Totals of the cart: subtotal, coupon discount, shipping (chosen method) and total.
 * Also returns the available shipping methods and the coupon in use.
 */
function cart_totals(?array $items = null): array
{
    $items = $items ?? cart_items();
    $subtotal = round(array_sum(array_column($items, 'line_total')), 2);

    // Shipping
    $methods = cart_shipping_options($items);
    $method = null;
    $shipping = 0.0;
    $needsShipping = (bool) array_filter($items, fn($it) => (int) ($it['product']['shipping_enabled'] ?? 1) === 1);
    if ($methods) {
        foreach ($methods as $m) {
            if ((int) $m['id'] === (int) ($_SESSION['shipping_method'] ?? 0)) {
                $method = $m;
            }
        }
        $method = $method ?? $methods[0];
        $shipping = (float) $method['cost'];
        if ((float) ($method['free_over'] ?? 0) > 0 && $subtotal >= (float) $method['free_over']) {
            $shipping = 0.0; // free delivery above this option's amount
        }
    } elseif ($needsShipping && !shipping_methods()) {
        $shipping = (float) setting('shipping_fee', '0'); // no shipping methods set up: old flat fee
    }
    $freeOver = (float) setting('free_shipping_over', '0');
    if ($subtotal <= 0 || ($freeOver > 0 && $subtotal >= $freeOver)) {
        $shipping = 0.0;
    }

    // Coupon
    $coupon = null;
    $discount = 0.0;
    $couponError = '';
    if (!empty($_SESSION['coupon']) && $items) {
        $c = find_coupon((string) $_SESSION['coupon']);
        $couponError = coupon_error($c, $subtotal);
        if ($couponError === '') {
            $coupon = $c;
            if ($c['type'] === 'percent') {
                $discount = $subtotal * min(100, (float) $c['value']) / 100;
            } elseif ($c['type'] === 'fixed') {
                $discount = min($subtotal, (float) $c['value']);
            } else {
                $shipping = 0.0; // free_shipping
            }
        }
    }
    $discount = round($discount, 2);

    return [
        'subtotal' => $subtotal,
        'discount' => $discount,
        'shipping' => round($shipping, 2),
        'total' => round(max(0, $subtotal - $discount) + $shipping, 2),
        'coupon' => $coupon,
        'coupon_error' => $couponError,
        'shipping_methods' => $methods,
        'shipping_method' => $method,
        // Delivery options exist, but none covers the customer's country/city.
        'no_delivery' => $needsShipping && !$methods && (bool) shipping_methods(),
    ];
}

/** Active size/color variants of a product (empty for simple products). */
function product_variants(int $productId): array
{
    return q_all('SELECT * FROM product_variants WHERE product_id = ? AND active = 1 ORDER BY id', [$productId]);
}

function find_variant(int $productId, string $size, string $color): ?array
{
    foreach (product_variants($productId) as $v) {
        if ($v['size'] === $size && $v['color'] === $color) {
            return $v;
        }
    }
    return null;
}

/** Price of a variant: its own price, or the product price when no special price is set. */
function variant_price(array $v, array $p): float
{
    return (float) $v['price'] > 0 ? (float) $v['price'] : (float) $p['price'];
}

/**
 * Save stock per size/color for a store's own product (not Printful).
 * Rows come from the admin grid: vs_key[] = "size|color", vs_stock[], vs_price[].
 */
function save_variant_stock(int $productId, bool $enabled, array $sizes, array $colors): void
{
    if (!$enabled) {
        q("DELETE FROM product_variants WHERE product_id = ? AND printful_variant_id = ''", [$productId]);
        return;
    }
    $posted = [];
    foreach ((array) ($_POST['vs_key'] ?? []) as $i => $key) {
        $stock = trim((string) ($_POST['vs_stock'][$i] ?? ''));
        $posted[(string) $key] = [
            'stock' => $stock === '' ? -1 : max(0, (int) $stock),
            'price' => max(0, round((float) ($_POST['vs_price'][$i] ?? 0), 2)),
        ];
    }
    $existing = [];
    foreach (q_all("SELECT id, size, color FROM product_variants WHERE product_id = ? AND printful_variant_id = ''", [$productId]) as $v) {
        $existing[$v['size'] . '|' . $v['color']] = (int) $v['id'];
    }
    $keep = [];
    foreach ($sizes ?: [''] as $size) {
        foreach ($colors ?: [''] as $color) {
            $key = $size . '|' . $color;
            $row = ['size' => mb_substr($size, 0, 60), 'color' => mb_substr($color, 0, 60), 'active' => 1,
                'stock' => $posted[$key]['stock'] ?? -1, 'price' => $posted[$key]['price'] ?? 0];
            if (isset($existing[$key])) {
                db_update('product_variants', $existing[$key], $row);
                $keep[] = $existing[$key];
            } else {
                $keep[] = db_insert('product_variants', $row + ['product_id' => $productId, 'printful_variant_id' => '', 'sku' => '', 'image' => '']);
            }
        }
    }
    foreach ($existing as $id) {
        if (!in_array($id, $keep, true)) {
            q('DELETE FROM product_variants WHERE id = ?', [$id]);
        }
    }
}
