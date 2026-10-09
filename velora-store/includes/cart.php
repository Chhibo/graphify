<?php
/**
 * Session-based shopping cart.
 * $_SESSION['cart'][key] = ['product_id' => int, 'variant_id' => int, 'size' => string, 'color' => string, 'qty' => int]
 * variant_id is set for products that have size/color variants (e.g. imported from Printful).
 */

function cart_raw(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_add(int $productId, int $qty, string $size = '', string $color = '', int $variantId = 0): void
{
    $key = md5($productId . '|' . $variantId . '|' . $size . '|' . $color);
    $current = $_SESSION['cart'][$key]['qty'] ?? 0;
    $_SESSION['cart'][$key] = [
        'product_id' => $productId,
        'variant_id' => $variantId,
        'size' => $size,
        'color' => $color,
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
        $price = $variant ? (float) $variant['price'] : (float) $p['price'];
        $items[] = [
            'key' => $key,
            'product' => $p,
            'variant' => $variant,
            'size' => $line['size'],
            'color' => $line['color'],
            'qty' => (int) $line['qty'],
            'price' => $price,
            'line_total' => $price * (int) $line['qty'],
        ];
    }
    return $items;
}

function cart_totals(?array $items = null): array
{
    $items = $items ?? cart_items();
    $subtotal = array_sum(array_column($items, 'line_total'));
    $shipping = (float) setting('shipping_fee', '0');
    $freeOver = (float) setting('free_shipping_over', '0');
    if ($subtotal <= 0 || ($freeOver > 0 && $subtotal >= $freeOver)) {
        $shipping = 0.0;
    }
    return [
        'subtotal' => round($subtotal, 2),
        'shipping' => round($shipping, 2),
        'total' => round($subtotal + $shipping, 2),
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
