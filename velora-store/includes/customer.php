<?php
/**
 * Customer accounts (login, registration, password reset).
 * Turn accounts / guest checkout on or off in Admin > Settings > General.
 */

function accounts_enabled(): bool
{
    return setting('accounts_enabled', '1') === '1';
}

function guest_checkout_allowed(): bool
{
    return !accounts_enabled() || setting('guest_checkout', '1') === '1';
}

function current_customer(bool $refresh = false): ?array
{
    static $customer = false;
    if ($customer === false || $refresh) {
        $customer = !empty($_SESSION['customer_id'])
            ? q_one('SELECT * FROM customers WHERE id = ? AND active = 1', [(int) $_SESSION['customer_id']])
            : null;
        if (!$customer) {
            unset($_SESSION['customer_id']);
        }
    }
    return $customer;
}

function customer_logged_in(): bool
{
    return current_customer() !== null;
}

function customer_login(array $c): void
{
    session_regenerate_id(true);
    $_SESSION['customer_id'] = (int) $c['id'];
    q('UPDATE customers SET last_login = ? WHERE id = ?', [now(), $c['id']]);
    current_customer(true);
}

function customer_logout(): void
{
    unset($_SESSION['customer_id']);
    session_regenerate_id(true);
}

/** Send visitors to the login page (then back to $return) when they are not logged in. */
function require_customer(string $return = 'account.php'): array
{
    $c = current_customer();
    if (!$c) {
        redirect('login.php?return=' . rawurlencode($return));
    }
    return $c;
}

/** Only allow returning to pages of this store after login. */
function safe_return(string $r, string $default = 'account.php'): string
{
    $r = ltrim(trim($r), '/');
    return preg_match('#^[a-z0-9_\-]+\.php(\?[^\s]*)?$#i', $r) ? $r : $default;
}

/** Simple per-session limit against password guessing: max 8 attempts per 10 minutes. */
function login_rate_limited(string $bucket): bool
{
    $_SESSION['rl'][$bucket] = array_filter($_SESSION['rl'][$bucket] ?? [], fn($t) => $t > time() - 600);
    return count($_SESSION['rl'][$bucket]) >= 8;
}

function login_failed(string $bucket): void
{
    $_SESSION['rl'][$bucket][] = time();
}
