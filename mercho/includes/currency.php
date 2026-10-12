<?php
/**
 * Multi-currency (Admin > Settings > Currencies).
 *
 * The store keeps every price, order and payment in its main currency (Settings > General).
 * Visitors can choose another currency: prices are converted for display only, and checkout
 * shows the amount that will really be charged in the main currency.
 *
 * money() converts automatically on store pages. Order amounts (emails, invoices, order pages,
 * admin, WhatsApp) always use the main currency: see money_base_mode().
 */

function base_currency(): array
{
    return [
        'code' => strtoupper(setting('currency_code', 'USD')),
        'symbol' => setting('currency_symbol', '$'),
        'rate' => 1.0,
        'position' => setting('currency_position', 'before') === 'after' ? 'after' : 'before',
        'decimals' => (int) setting('currency_decimals', '2'),
    ];
}

/** Extra currencies set up by the owner (code => currency), without the main one. */
function extra_currencies(): array
{
    static $list = null;
    if ($list === null) {
        $list = [];
        $base = base_currency()['code'];
        foreach ((array) json_decode(setting('currencies', '[]'), true) as $c) {
            $code = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) ($c['code'] ?? '')));
            $rate = (float) ($c['rate'] ?? 0);
            if (strlen($code) === 3 && $code !== $base && $rate > 0) {
                $list[$code] = [
                    'code' => $code,
                    'symbol' => (string) ($c['symbol'] ?? $code),
                    'rate' => $rate,
                    'position' => ($c['position'] ?? 'before') === 'after' ? 'after' : 'before',
                    'decimals' => max(0, min(3, (int) ($c['decimals'] ?? 2))),
                ];
            }
        }
    }
    return $list;
}

function multi_currency_on(): bool
{
    return setting_on('currencies_enabled') && extra_currencies();
}

/** All currencies a visitor can pick (main first). */
function store_currencies(): array
{
    $base = base_currency();
    return [$base['code'] => $base] + (multi_currency_on() ? extra_currencies() : []);
}

/** The currency the visitor chose (or the main currency). */
function display_currency(): array
{
    $all = store_currencies();
    $code = (string) ($_SESSION['currency'] ?? '');
    return $all[$code] ?? base_currency();
}

function is_base_currency_shown(): bool
{
    return display_currency()['code'] === base_currency()['code'];
}

/** From now on, money() shows amounts in the main currency (orders, emails, invoices, admin). */
function money_base_mode(bool $on = true): void
{
    $GLOBALS['MONEY_BASE_MODE'] = $on;
}

function format_currency(float $amount, array $cur): string
{
    $formatted = number_format($amount, $cur['decimals']);
    // Hide ".00" for whole amounts to match the store design ($120 instead of $120.00)
    if (setting('hide_zero_decimals', '1') === '1') {
        $formatted = preg_replace('/\.0+$/', '', $formatted);
    }
    return $cur['position'] === 'after' ? $formatted . ' ' . $cur['symbol'] : $cur['symbol'] . $formatted;
}

/** Price in the main currency (always, even when the visitor chose another currency). */
function money_base($amount): string
{
    return format_currency((float) $amount, base_currency());
}

/** Convert an amount of the main currency to another currency. */
function convert_amount(float $amount, array $cur): float
{
    return round($amount * $cur['rate'], $cur['decimals']);
}

/** Settings for assets/js/app.js formatMoney() (prices updated in the browser). */
function money_js_config(): array
{
    $cur = !empty($GLOBALS['MONEY_BASE_MODE']) ? base_currency() : display_currency();
    return ['symbol' => $cur['symbol'], 'after' => $cur['position'] === 'after', 'dec' => $cur['decimals'],
        'trim' => setting('hide_zero_decimals', '1') === '1', 'rate' => $cur['rate']];
}

/**
 * Download today's exchange rates for the extra currencies (free service, no key needed).
 * Returns an error message, or '' when the rates were updated.
 */
function update_exchange_rates(): string
{
    $list = (array) json_decode(setting('currencies', '[]'), true);
    if (!$list) {
        return 'Add at least one currency first.';
    }
    $base = base_currency()['code'];
    $res = http_request('GET', 'https://open.er-api.com/v6/latest/' . rawurlencode($base));
    $rates = $res['json']['rates'] ?? null;
    if ($res['status'] !== 200 || !is_array($rates)) {
        return 'Could not download exchange rates' . ($res['error'] !== '' ? ' (' . $res['error'] . ')' : '') . '. You can type the rates yourself.';
    }
    $missing = [];
    foreach ($list as $i => $c) {
        $code = strtoupper((string) ($c['code'] ?? ''));
        if (isset($rates[$code]) && (float) $rates[$code] > 0) {
            $list[$i]['rate'] = round((float) $rates[$code], 6);
        } else {
            $missing[] = $code;
        }
    }
    set_setting('currencies', json_encode(array_values($list)));
    set_setting('currency_rates_updated', now());
    return $missing ? 'Rates updated, except: ' . implode(', ', $missing) . ' (not found - type the rate yourself).' : '';
}
