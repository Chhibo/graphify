<?php
/**
 * Session-based shopping cart.
 * $_SESSION['cart'][key] = ['product_id' => int, 'size' => string, 'color' => string, 'qty' => int]
 */

function cart_raw(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_add(int $productId, int $qty, string $size = '', string $color = ''): void
{
    $key = md5($productId . '|' . $size . '|' . $color);
    $current = $_SESSION['cart'][$key]['qty'] ?? 0;
    $_SESSION['cart'][$key] = [
        'product_id' => $productId,
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
    $items = [];
    foreach ($raw as $key => $line) {
        $p = $products[(int) $line['product_id']] ?? null;
        if (!$p) {
            unset($_SESSION['cart'][$key]);
            continue;
        }
        $items[] = [
            'key' => $key,
            'product' => $p,
            'size' => $line['size'],
            'color' => $line['color'],
            'qty' => (int) $line['qty'],
            'price' => (float) $p['price'],
            'line_total' => (float) $p['price'] * (int) $line['qty'],
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
