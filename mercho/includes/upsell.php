<?php
/**
 * Upsells & bundles (Admin > Settings > Sales tools, and in each product).
 *  - "Frequently bought together" on the product page (products chosen in the product, or automatic).
 *  - Bundle discount: X% off when the customer buys the product together with all its "bought together" products.
 *  - Quantity deals: "Buy 2, get 1 free".
 *  - "You may also like" suggestions in the cart and at checkout.
 */

function upsells_on(): bool
{
    return setting_on('upsell_enabled');
}

/** Product ids chosen in Admin > Product > "Frequently bought together". */
function product_upsell_ids(array $p): array
{
    return array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($p['upsell_ids'] ?? ''))),
        fn($id) => $id > 0 && $id !== (int) $p['id'])));
}

/** Products shown with this product. Chosen ones first; when none are chosen, best matches from the same category. */
function product_upsells(array $p, int $limit = 3): array
{
    $ids = product_upsell_ids($p);
    if ($ids) {
        $rows = q_all('SELECT * FROM products WHERE active = 1 AND stock <> 0 AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
        usort($rows, fn($a, $b) => array_search((int) $a['id'], $ids, true) <=> array_search((int) $b['id'], $ids, true));
        return array_slice($rows, 0, $limit);
    }
    if (empty($p['category_id'])) {
        return [];
    }
    return q_all('SELECT * FROM products WHERE active = 1 AND stock <> 0 AND category_id = ? AND id <> ? ORDER BY is_trending DESC, reviews_count DESC, id DESC LIMIT ' . (int) $limit,
        [(int) $p['category_id'], (int) $p['id']]);
}

/** "Buy 2, get 1 free" (empty when the product has no quantity deal). */
function deal_label(array $p): string
{
    $buy = (int) ($p['deal_buy'] ?? 0);
    $get = (int) ($p['deal_get'] ?? 0);
    if (!upsells_on() || $buy < 1 || $get < 1) {
        return '';
    }
    return 'Buy ' . $buy . ', get ' . $get . ' free';
}

/** Bundle discount % of a product (only with products chosen by hand). */
function bundle_pct(array $p): float
{
    return upsells_on() && product_upsell_ids($p) ? max(0, min(90, (float) ($p['bundle_discount'] ?? 0))) : 0.0;
}

/**
 * Discounts earned by the cart: quantity deals and bundles.
 * Returns [['label' => 'Buy 2, get 1 free: Hoodie', 'amount' => 25.0], ...]
 */
function cart_deals(array $items): array
{
    if (!upsells_on() || !$items) {
        return [];
    }
    $byProduct = [];
    foreach ($items as $it) {
        $id = (int) $it['product']['id'];
        $byProduct[$id] = $byProduct[$id] ?? ['product' => $it['product'], 'qty' => 0, 'min' => INF];
        $byProduct[$id]['qty'] += (int) $it['qty'];
        $byProduct[$id]['min'] = min($byProduct[$id]['min'], (float) $it['price']);
    }
    $deals = [];
    // Buy X, get Y free: the cheapest units of that product are free.
    foreach ($byProduct as $row) {
        $buy = (int) ($row['product']['deal_buy'] ?? 0);
        $get = (int) ($row['product']['deal_get'] ?? 0);
        if ($buy > 0 && $get > 0) {
            $free = intdiv($row['qty'], $buy + $get) * $get;
            if ($free > 0) {
                $deals[] = ['label' => deal_label($row['product']) . ': ' . $row['product']['name'], 'amount' => round($free * $row['min'], 2)];
            }
        }
    }
    // Bundles: the product + all its "bought together" products are in the cart.
    $used = [];
    foreach ($byProduct as $id => $row) {
        $pct = bundle_pct($row['product']);
        $others = product_upsell_ids($row['product']);
        if ($pct <= 0 || isset($used[$id]) || array_diff($others, array_keys($byProduct)) || array_intersect($others, array_keys($used))) {
            continue;
        }
        $sum = $row['min'];
        foreach ($others as $o) {
            $sum += $byProduct[$o]['min'];
            $used[$o] = true;
        }
        $used[$id] = true;
        $deals[] = ['label' => 'Bundle -' . rtrim(rtrim(number_format($pct, 2), '0'), '.') . '%: ' . $row['product']['name'], 'amount' => round($sum * $pct / 100, 2)];
    }
    return $deals;
}

/** "You may also like" in the cart / at checkout: products not in the cart yet. */
function cart_suggestions(array $items, int $limit = 4): array
{
    if (!upsells_on() || !setting_on('cart_upsell_enabled') || !$items) {
        return [];
    }
    $inCart = array_map(fn($it) => (int) $it['product']['id'], $items);
    $ids = [];
    foreach ($items as $it) {
        foreach (product_upsell_ids($it['product']) as $id) {
            if (!in_array($id, $inCart, true)) {
                $ids[] = $id;
            }
        }
    }
    $ids = array_values(array_unique($ids));
    $rows = [];
    if ($ids) {
        $rows = q_all('SELECT * FROM products WHERE active = 1 AND stock <> 0 AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') LIMIT ' . (int) $limit, $ids);
    }
    if (count($rows) < $limit) {
        $exclude = array_merge($inCart, array_map(fn($r) => (int) $r['id'], $rows));
        $cats = array_values(array_unique(array_filter(array_map(fn($it) => (int) $it['product']['category_id'], $items))));
        $where = 'active = 1 AND stock <> 0 AND id NOT IN (' . implode(',', array_fill(0, count($exclude), '?')) . ')';
        $params = $exclude;
        $order = 'is_trending DESC, id DESC';
        if ($cats) {
            $order = 'CASE WHEN category_id IN (' . implode(',', array_map('intval', $cats)) . ') THEN 0 ELSE 1 END, ' . $order;
        }
        $rows = array_merge($rows, q_all("SELECT * FROM products WHERE $where ORDER BY $order LIMIT " . (int) ($limit - count($rows)), $params));
    }
    return $rows;
}

/** Can this product be added to the cart without choosing options? */
function product_quick_add(array $p): bool
{
    return !str_list($p['sizes']) && !str_list($p['colors']) && !product_extra_options($p) && !product_variants((int) $p['id']);
}

/** Label of an order's discount line: coupon code and/or deals. */
function order_discount_label(array $order): string
{
    $note = trim((string) ($order['discount_note'] ?? ''));
    return $note !== '' ? $note : (string) ($order['coupon_code'] ?? '');
}

/* ---------- Order on WhatsApp (Admin > Settings > Sales tools) ---------- */

function wa_order_on(): bool
{
    return setting_on('wa_order_enabled') && preg_replace('/\D+/', '', setting('whatsapp_number')) !== '';
}

function wa_order_link(string $text): string
{
    return 'https://wa.me/' . preg_replace('/\D+/', '', setting('whatsapp_number')) . '?text=' . rawurlencode($text);
}

/** WhatsApp message with the whole cart. */
function wa_cart_text(array $items, array $totals): string
{
    $lines = [setting('wa_order_text', 'Hello! I would like to order:'), ''];
    foreach ($items as $it) {
        $variant = implode(' / ', array_filter(array_merge([$it['size'], $it['color']], array_values($it['options']))));
        $lines[] = '• ' . $it['qty'] . ' × ' . $it['product']['name'] . ($variant !== '' ? ' (' . $variant . ')' : '') . ' - ' . money($it['line_total']);
    }
    $lines[] = '';
    $lines[] = 'Total: ' . money($totals['total']) . ($totals['shipping'] > 0 ? ' (with delivery)' : '');
    return implode("\n", $lines);
}
