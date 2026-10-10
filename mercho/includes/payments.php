<?php
/**
 * PayPal (Orders v2 REST API) and Stripe (Checkout Sessions) integrations.
 * No SDK or Composer needed - plain cURL calls.
 */

/* ===================== PayPal ===================== */

function paypal_base(): string
{
    return setting('paypal_mode', 'sandbox') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
}

function paypal_token(): string
{
    $res = http_request('POST', paypal_base() . '/v1/oauth2/token', [
        'basic' => setting('paypal_client_id') . ':' . setting('paypal_secret'),
        'form' => ['grant_type' => 'client_credentials'],
        'headers' => ['Accept: application/json'],
    ]);
    if (empty($res['json']['access_token'])) {
        throw new RuntimeException('PayPal login failed. Check your PayPal Client ID / Secret and mode (sandbox/live). ' . $res['error']);
    }
    return $res['json']['access_token'];
}

/** Create a PayPal order and return the approval URL the customer must visit. */
function paypal_create(array $order): string
{
    $currency = strtoupper(setting('currency_code', 'USD'));
    $res = http_request('POST', paypal_base() . '/v2/checkout/orders', [
        'headers' => ['Authorization: Bearer ' . paypal_token(), 'PayPal-Request-Id: ' . $order['order_number']],
        'json' => [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order['order_number'],
                'invoice_id' => $order['order_number'],
                'description' => 'Order ' . $order['order_number'],
                'amount' => ['currency_code' => $currency, 'value' => number_format((float) $order['total'], 2, '.', '')],
            ]],
            'application_context' => [
                'brand_name' => mb_substr(setting('store_name', 'Store'), 0, 120),
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
                'return_url' => full_url('pay.php?gateway=paypal&action=return&order=' . rawurlencode($order['order_number'])),
                'cancel_url' => full_url('pay.php?gateway=paypal&action=cancel&order=' . rawurlencode($order['order_number'])),
            ],
        ],
    ]);
    $id = $res['json']['id'] ?? '';
    $approve = '';
    foreach ($res['json']['links'] ?? [] as $link) {
        if (in_array($link['rel'] ?? '', ['approve', 'payer-action'], true)) {
            $approve = $link['href'];
        }
    }
    if ($id === '' || $approve === '') {
        throw new RuntimeException('Could not start PayPal payment: ' . ($res['json']['message'] ?? $res['error'] ?: 'unknown error'));
    }
    q('UPDATE orders SET payment_ref = ? WHERE id = ?', [$id, $order['id']]);
    return $approve;
}

/** Capture an approved PayPal order. Returns true when the money was received for the right amount. */
function paypal_capture(array $order, string $paypalOrderId): bool
{
    if ($paypalOrderId === '' || $paypalOrderId !== $order['payment_ref']) {
        return false;
    }
    $token = paypal_token();
    $res = http_request('POST', paypal_base() . '/v2/checkout/orders/' . rawurlencode($paypalOrderId) . '/capture', [
        'headers' => ['Authorization: Bearer ' . $token, 'PayPal-Request-Id: capture-' . $order['order_number']],
        'json' => new stdClass(),
    ]);
    $data = $res['json'] ?? [];
    // Already captured earlier (e.g. page refresh): read the order instead.
    if (($data['name'] ?? '') === 'UNPROCESSABLE_ENTITY' || $res['status'] >= 400) {
        $res = http_request('GET', paypal_base() . '/v2/checkout/orders/' . rawurlencode($paypalOrderId), [
            'headers' => ['Authorization: Bearer ' . $token],
        ]);
        $data = $res['json'] ?? [];
    }
    if (($data['status'] ?? '') !== 'COMPLETED') {
        return false;
    }
    $capture = $data['purchase_units'][0]['payments']['captures'][0] ?? null;
    if (!$capture || ($capture['status'] ?? '') !== 'COMPLETED') {
        return false;
    }
    $paid = (float) ($capture['amount']['value'] ?? 0);
    return abs($paid - (float) $order['total']) < 0.01
        && strtoupper($capture['amount']['currency_code'] ?? '') === strtoupper(setting('currency_code', 'USD'));
}

/* ===================== Stripe ===================== */

function stripe_zero_decimal(string $currency): bool
{
    return in_array(strtolower($currency), ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'], true);
}

function stripe_amount(float $amount, string $currency): int
{
    return stripe_zero_decimal($currency) ? (int) round($amount) : (int) round($amount * 100);
}

/** Create a Stripe Checkout Session and return its hosted payment page URL. */
function stripe_create(array $order): string
{
    $currency = strtolower(setting('currency_code', 'USD'));
    $form = [
        'mode' => 'payment',
        'client_reference_id' => $order['order_number'],
        'success_url' => full_url('pay.php?gateway=stripe&action=return&order=' . rawurlencode($order['order_number'])) . '&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => full_url('pay.php?gateway=stripe&action=cancel&order=' . rawurlencode($order['order_number'])),
        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => $currency,
                'unit_amount' => stripe_amount((float) $order['total'], $currency),
                'product_data' => ['name' => setting('store_name', 'Store') . ' - Order ' . $order['order_number']],
            ],
        ]],
        'metadata' => ['order_number' => $order['order_number']],
    ];
    if (filter_var($order['email'], FILTER_VALIDATE_EMAIL)) {
        $form['customer_email'] = $order['email'];
    }
    // http_build_query encodes "{CHECKOUT_SESSION_ID}" - Stripe expects it literally, so restore the braces.
    $body = str_replace(['%7BCHECKOUT_SESSION_ID%7D'], ['{CHECKOUT_SESSION_ID}'], http_build_query($form));
    $res = http_request('POST', 'https://api.stripe.com/v1/checkout/sessions', [
        'basic' => setting('stripe_secret_key') . ':',
        'form' => $body,
        'headers' => ['Idempotency-Key: ' . $order['order_number'] . '-' . substr(md5((string) $order['total']), 0, 8)],
    ]);
    if (empty($res['json']['url']) || empty($res['json']['id'])) {
        throw new RuntimeException('Could not start card payment: ' . ($res['json']['error']['message'] ?? $res['error'] ?: 'unknown error'));
    }
    q('UPDATE orders SET payment_ref = ? WHERE id = ?', [$res['json']['id'], $order['id']]);
    return $res['json']['url'];
}

/** Verify a Stripe Checkout Session is paid and belongs to this order. */
function stripe_verify(array $order, string $sessionId): bool
{
    if ($sessionId === '' || $sessionId !== $order['payment_ref']) {
        return false;
    }
    $res = http_request('GET', 'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sessionId), [
        'basic' => setting('stripe_secret_key') . ':',
    ]);
    $s = $res['json'] ?? [];
    $currency = strtolower(setting('currency_code', 'USD'));
    return ($s['payment_status'] ?? '') === 'paid'
        && ($s['client_reference_id'] ?? '') === $order['order_number']
        && (int) ($s['amount_total'] ?? -1) === stripe_amount((float) $order['total'], $currency);
}
