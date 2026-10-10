<?php
/**
 * Cash-on-delivery protection: block list, maximum COD order total, daily COD limit per phone,
 * and optional "awaiting confirmation" status for new COD orders.
 */

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/** Compare phone numbers by their last 9 digits so "+212 612-345678" and "0612345678" match. */
function phone_key(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    return strlen($digits) > 9 ? substr($digits, -9) : $digits;
}

function blocklist_add(string $type, string $value, string $note = ''): void
{
    $value = trim($value);
    if ($type === 'phone') {
        $value = phone_key($value);
    } elseif ($type === 'email') {
        $value = strtolower($value);
    }
    if ($value === '' || !in_array($type, ['phone', 'email', 'ip'], true)) {
        return;
    }
    if ((int) q_val('SELECT COUNT(*) FROM blocklist WHERE type = ? AND value = ?', [$type, $value]) === 0) {
        db_insert('blocklist', ['type' => $type, 'value' => mb_substr($value, 0, 190), 'note' => mb_substr($note, 0, 255), 'created_at' => now()]);
    }
}

/** True when the phone, email or IP address is on the block list. */
function is_blocked(string $phone, string $email, string $ip = ''): bool
{
    $checks = [['phone', phone_key($phone)], ['email', strtolower(trim($email))], ['ip', $ip]];
    foreach ($checks as [$type, $value]) {
        if ($value !== '' && (int) q_val('SELECT COUNT(*) FROM blocklist WHERE type = ? AND value = ?', [$type, $value]) > 0) {
            return true;
        }
    }
    return false;
}

/** Number of Cash on Delivery orders placed today with this phone number. */
function cod_orders_today(string $phone): int
{
    $key = phone_key($phone);
    $n = 0;
    foreach (q_all("SELECT phone FROM orders WHERE payment_method = 'cod' AND created_at >= ?", [date('Y-m-d 00:00:00')]) as $o) {
        if (phone_key($o['phone']) === $key) {
            $n++;
        }
    }
    return $n;
}

/** Is Cash on Delivery allowed for this order total? */
function cod_allowed_for(float $total): bool
{
    $max = (float) setting('cod_max_total', '0');
    return $max <= 0 || $total <= $max;
}
