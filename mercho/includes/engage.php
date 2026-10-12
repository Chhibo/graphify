<?php
/**
 * Product questions (Q&A), "notify me when back in stock" and low-stock alerts.
 * Settings: Admin > Settings > Sales tools. Admin pages: Product questions, Stock & alerts.
 */

/* ---------- Product questions ---------- */

function qa_on(): bool
{
    return setting_on('qa_enabled');
}

/** Answered and published questions of a product. */
function product_questions(int $productId): array
{
    return q_all("SELECT * FROM product_questions WHERE product_id = ? AND published = 1 AND answer IS NOT NULL AND answer <> '' ORDER BY answered_at DESC", [$productId]);
}

/* ---------- Back in stock ---------- */

function stock_alerts_on(): bool
{
    return setting_on('stock_alerts_enabled');
}

/** Save a "notify me" request. Returns an error message or ''. */
function stock_alert_add(array $p, string $email, string $size, string $color): string
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }
    $variantId = null;
    if (product_variants((int) $p['id'])) {
        $v = find_variant((int) $p['id'], $size, $color);
        if (!$v) {
            return 'Please choose a size and color.';
        }
        $variantId = (int) $v['id'];
    }
    $exists = q_val('SELECT COUNT(*) FROM stock_alerts WHERE product_id = ? AND LOWER(email) = ? AND notified_at IS NULL AND '
        . ($variantId ? 'variant_id = ?' : 'variant_id IS NULL'),
        array_merge([(int) $p['id'], strtolower($email)], $variantId ? [$variantId] : []));
    if ((int) $exists === 0) {
        db_insert('stock_alerts', ['product_id' => (int) $p['id'], 'variant_id' => $variantId, 'email' => mb_substr($email, 0, 190), 'created_at' => now()]);
    }
    return '';
}

/** Email the customers waiting for products that are available again. Returns the number of emails sent. */
function process_stock_alerts(?int $productId = null, int $limit = 50): int
{
    if (!stock_alerts_on()) {
        return 0;
    }
    $rows = q_all('SELECT a.*, p.name, p.slug, p.image, p.stock AS p_stock, p.active, v.stock AS v_stock, v.size, v.color, v.active AS v_active
        FROM stock_alerts a JOIN products p ON p.id = a.product_id LEFT JOIN product_variants v ON v.id = a.variant_id
        WHERE a.notified_at IS NULL' . ($productId ? ' AND a.product_id = ' . (int) $productId : '') . ' ORDER BY a.id LIMIT ' . (int) $limit);
    $sent = 0;
    foreach ($rows as $a) {
        $available = (int) $a['active'] === 1 && (int) $a['p_stock'] !== 0
            && (empty($a['variant_id']) || ((int) $a['v_active'] === 1 && (int) $a['v_stock'] !== 0));
        if (!$available) {
            continue;
        }
        $variant = implode(' / ', array_filter([(string) $a['size'], (string) $a['color']]));
        $p = ['id' => $a['product_id'], 'slug' => $a['slug']];
        send_template($a['email'], $a['name'] . ' is back in stock - ' . setting('store_name'), 'Good news: it is back!',
            '<p><b>' . e($a['name']) . '</b>' . ($variant !== '' ? ' (' . e($variant) . ')' : '') . ' is available again. Quantities may be limited, so order soon.</p>',
            'Shop now', abs_url(product_url($p)));
        q('UPDATE stock_alerts SET notified_at = ? WHERE id = ?', [now(), $a['id']]);
        $sent++;
    }
    return $sent;
}

/* ---------- Low stock ---------- */

function low_stock_threshold(): int
{
    return max(0, (int) setting('low_stock_threshold', '3'));
}

/** Products and size/color options with little stock left (0 = sold out). */
function low_stock_items(int $limit = 100): array
{
    $t = low_stock_threshold();
    $products = q_all('SELECT id, name, image, stock, NULL AS size, NULL AS color FROM products WHERE active = 1 AND variant_stock = 0 AND stock >= 0 AND stock <= ? AND id NOT IN (SELECT product_id FROM product_variants WHERE active = 1) ORDER BY stock, name LIMIT ' . (int) $limit, [$t]);
    $variants = q_all('SELECT p.id, p.name, p.image, v.stock, v.size, v.color FROM product_variants v JOIN products p ON p.id = v.product_id WHERE p.active = 1 AND v.active = 1 AND v.stock >= 0 AND v.stock <= ? ORDER BY v.stock, p.name LIMIT ' . (int) $limit, [$t]);
    $all = array_merge($products, $variants);
    usort($all, fn($a, $b) => (int) $a['stock'] <=> (int) $b['stock']);
    return array_slice($all, 0, $limit);
}

/** Current stock of the products/options of an order (called before and after the stock goes down). */
function order_stock_levels(int $orderId): array
{
    $levels = [];
    foreach (order_items($orderId) as $item) {
        if (!empty($item['variant_id'])) {
            $v = q_one('SELECT v.stock, v.size, v.color, p.name FROM product_variants v JOIN products p ON p.id = v.product_id WHERE v.id = ?', [(int) $item['variant_id']]);
            if ($v) {
                $levels['v' . $item['variant_id']] = ['stock' => (int) $v['stock'], 'name' => $v['name'] . ' (' . implode(' / ', array_filter([$v['size'], $v['color']])) . ')'];
            }
        } elseif (!empty($item['product_id'])) {
            $p = q_one('SELECT stock, name FROM products WHERE id = ?', [(int) $item['product_id']]);
            if ($p) {
                $levels['p' . $item['product_id']] = ['stock' => (int) $p['stock'], 'name' => $p['name']];
            }
        }
    }
    return $levels;
}

/** Email the owner when an order brings a product to the low-stock level. Never throws. */
function notify_low_stock(array $before, array $after): void
{
    if (!setting_on('notify_low_stock') || admin_email() === '') {
        return;
    }
    $t = low_stock_threshold();
    $rows = '';
    foreach ($after as $k => $now) {
        $was = $before[$k]['stock'] ?? null;
        if ($now['stock'] >= 0 && $now['stock'] <= $t && ($was === null || $was > $t || ($was > 0 && $now['stock'] === 0))) {
            $rows .= '<li>' . e($now['name']) . ': <b>' . ($now['stock'] === 0 ? 'sold out' : $now['stock'] . ' left') . '</b></li>';
        }
    }
    if ($rows === '') {
        return;
    }
    try {
        send_template(admin_email(), 'Low stock alert - ' . setting('store_name'), 'Some products are running low',
            '<ul>' . $rows . '</ul><p>Add stock in the admin so you do not miss sales.</p>', 'Open stock page', full_url('admin/stock.php'));
    } catch (Throwable $ex) {
        // never block an order because of an email
    }
}
