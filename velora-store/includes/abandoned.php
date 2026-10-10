<?php
/**
 * Abandoned cart reminders.
 * Carts are saved for logged-in customers and for visitors who typed their email at checkout.
 * If no order follows, one reminder email is sent after X hours (Settings > General).
 * Reminders are sent by cron.php (cron job) or automatically when an admin opens the admin panel.
 */

/** Save the current cart (call after the cart changes). */
function cart_snapshot(): void
{
    if (!setting_on('abandoned_enabled')) {
        return;
    }
    $customer = current_customer();
    $email = $customer['email'] ?? ($_SESSION['cart_contact']['email'] ?? '');
    if ($email === '' || !cart_raw()) {
        return;
    }
    if (empty($_SESSION['cart_token'])) {
        $_SESSION['cart_token'] = bin2hex(random_bytes(16));
    }
    $data = [
        'customer_id' => $customer ? (int) $customer['id'] : null,
        'email' => mb_substr($email, 0, 190),
        'name' => mb_substr($customer['name'] ?? ($_SESSION['cart_contact']['name'] ?? ''), 0, 150),
        'phone' => mb_substr($customer['phone'] ?? ($_SESSION['cart_contact']['phone'] ?? ''), 0, 40),
        'items' => json_encode(array_values(cart_raw())),
        'total' => cart_totals()['subtotal'],
        'updated_at' => now(),
    ];
    $row = q_one('SELECT id, items FROM carts WHERE token = ?', [$_SESSION['cart_token']]);
    if ($row) {
        if ($row['items'] !== $data['items']) {
            $data['reminded_at'] = null; // the cart changed: a new reminder may be sent later
        }
        db_update('carts', (int) $row['id'], $data);
    } else {
        db_insert('carts', $data + ['token' => $_SESSION['cart_token'], 'recovered' => 0]);
    }
}

/** The order was placed: this cart is no longer abandoned. */
function cart_mark_recovered(): void
{
    if (!empty($_SESSION['cart_token'])) {
        q('UPDATE carts SET recovered = 1, updated_at = ? WHERE token = ?', [now(), $_SESSION['cart_token']]);
        unset($_SESSION['cart_token']);
    }
}

/** After login: bring back the customer's last saved cart when the current cart is empty. */
function cart_restore_for_customer(int $customerId): void
{
    if (cart_raw()) {
        return;
    }
    $row = q_one('SELECT * FROM carts WHERE customer_id = ? AND recovered = 0 ORDER BY updated_at DESC', [$customerId]);
    if ($row) {
        cart_load_items((string) $row['items']);
        $_SESSION['cart_token'] = $row['token'];
    }
}

function cart_load_items(string $json): void
{
    foreach ((array) json_decode($json, true) as $line) {
        if (!empty($line['product_id'])) {
            cart_add((int) $line['product_id'], (int) ($line['qty'] ?? 1), (string) ($line['size'] ?? ''), (string) ($line['color'] ?? ''),
                (int) ($line['variant_id'] ?? 0), (array) ($line['options'] ?? []));
        }
    }
}

function abandoned_restore_url(array $cart): string
{
    return full_url('cart-restore.php?t=' . $cart['token']);
}

/** Send one reminder email. Returns true when sent. */
function send_cart_reminder(array $cart): bool
{
    $items = '';
    foreach ((array) json_decode((string) $cart['items'], true) as $line) {
        $p = q_one('SELECT id, name, image FROM products WHERE id = ? AND active = 1', [(int) ($line['product_id'] ?? 0)]);
        if ($p) {
            $variant = implode(' / ', array_filter([$line['size'] ?? '', $line['color'] ?? '']));
            $items .= '<li>' . (int) ($line['qty'] ?? 1) . ' × ' . e($p['name']) . ($variant !== '' ? ' <span style="color:#888">(' . e($variant) . ')</span>' : '') . '</li>';
        }
    }
    if ($items === '') {
        return false;
    }
    $coupon = (int) setting('abandoned_coupon_id') > 0 ? q_one('SELECT * FROM coupons WHERE id = ?', [(int) setting('abandoned_coupon_id')]) : null;
    $couponHtml = $coupon && coupon_error($coupon, PHP_INT_MAX) === ''
        ? '<p style="background:#fff4f4;border:1px dashed #e03a3e;padding:12px;border-radius:8px">🎁 Use the code <b>' . e($coupon['code']) . '</b> for ' . e(coupon_label($coupon)) . ' - it is applied automatically with the button below.</p>'
        : '';
    $first = explode(' ', trim((string) $cart['name']))[0] ?? '';
    $ok = send_template((string) $cart['email'], 'You left something in your cart - ' . setting('store_name'),
        'Hi' . ($first !== '' ? ' ' . $first : '') . ', your cart is waiting for you',
        '<p>You left these items in your cart:</p><ul>' . $items . '</ul>' . $couponHtml . '<p>They are still available - complete your order before they sell out!</p>',
        'Complete my order', abandoned_restore_url($cart));
    if ($ok) {
        q('UPDATE carts SET reminded_at = ? WHERE id = ?', [now(), $cart['id']]);
    }
    return $ok;
}

/** Send due reminders (max $limit per run). Returns the number sent. */
function send_abandoned_reminders(int $limit = 10): int
{
    if (!setting_on('abandoned_enabled')) {
        return 0;
    }
    $hours = max(1, (int) setting('abandoned_delay_hours', '3'));
    $due = q_all("SELECT * FROM carts WHERE recovered = 0 AND reminded_at IS NULL AND email <> '' AND updated_at <= ? AND updated_at >= ? ORDER BY updated_at LIMIT " . (int) $limit,
        [date('Y-m-d H:i:s', time() - $hours * 3600), date('Y-m-d H:i:s', time() - 7 * 86400)]);
    $sent = 0;
    foreach ($due as $cart) {
        // Skip carts whose owner already ordered (same email) after the cart was saved.
        if ((int) q_val("SELECT COUNT(*) FROM orders WHERE LOWER(email) = ? AND created_at >= ? AND payment_status IN ('paid','cod')", [strtolower($cart['email']), $cart['updated_at']]) > 0) {
            q('UPDATE carts SET recovered = 1 WHERE id = ?', [$cart['id']]);
            continue;
        }
        if (send_cart_reminder($cart)) {
            $sent++;
        } else {
            q('UPDATE carts SET reminded_at = ? WHERE id = ?', [now(), $cart['id']]); // do not retry forever
        }
    }
    return $sent;
}

/** Run background jobs at most every 10 minutes (called from the admin panel and cron.php). */
function run_background_jobs(bool $force = false): void
{
    if (!$force && (time() - (int) setting('last_cron_run', '0')) < 600) {
        return;
    }
    set_setting('last_cron_run', (string) time());
    try {
        send_abandoned_reminders();
    } catch (Throwable $ex) {
        // never break the page because of background work
    }
}
