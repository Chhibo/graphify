<?php
/**
 * WhatsApp order notifications.
 *
 * Modes (Admin > Settings > WhatsApp):
 *  - "link"      : No setup. After a Cash-on-Delivery order the customer is sent to WhatsApp with the
 *                  full order details already written, addressed to your number. They just press Send.
 *  - "callmebot" : Automatic & free. The order is pushed to your WhatsApp by the CallMeBot service.
 *  - "cloud"     : Automatic via the official WhatsApp Business Cloud API (Meta).
 * In every mode the order is also saved in Admin > Orders.
 */

function whatsapp_number(): string
{
    return preg_replace('/\D+/', '', setting('whatsapp_number'));
}

/** wa.me link that opens WhatsApp with the order text pre-filled. */
function whatsapp_link(string $text): string
{
    return 'https://wa.me/' . whatsapp_number() . '?text=' . rawurlencode($text);
}

/** Try to push the message automatically (callmebot / cloud modes). Returns true on success. */
function whatsapp_send_auto(string $text): bool
{
    $number = whatsapp_number();
    if ($number === '') {
        return false;
    }
    $mode = setting('whatsapp_mode', 'link');

    if ($mode === 'callmebot' && setting('callmebot_apikey') !== '') {
        $res = http_request('GET', 'https://api.callmebot.com/whatsapp.php?' . http_build_query([
            'phone' => '+' . $number,
            'text' => $text,
            'apikey' => setting('callmebot_apikey'),
        ]));
        return $res['status'] >= 200 && $res['status'] < 300;
    }

    if ($mode === 'cloud' && setting('wa_cloud_token') !== '' && setting('wa_cloud_phone_id') !== '') {
        $res = http_request('POST', 'https://graph.facebook.com/v20.0/' . rawurlencode(setting('wa_cloud_phone_id')) . '/messages', [
            'headers' => ['Authorization: Bearer ' . setting('wa_cloud_token')],
            'json' => [
                'messaging_product' => 'whatsapp',
                'to' => $number,
                'type' => 'text',
                'text' => ['preview_url' => false, 'body' => $text],
            ],
        ]);
        return !empty($res['json']['messages'][0]['id']);
    }
    return false;
}

/**
 * Notify the store owner about an order. Records the result on the order.
 * Returns the wa.me link the customer should be sent to (link mode), or '' when nothing else is needed.
 */
function whatsapp_notify_order(array $order): string
{
    if (whatsapp_number() === '') {
        return '';
    }
    $text = order_text($order);
    if (setting('whatsapp_mode', 'link') !== 'link') {
        $ok = whatsapp_send_auto($text);
        q('UPDATE orders SET whatsapp_sent = ? WHERE id = ?', [$ok ? 1 : 0, $order['id']]);
        // If the automatic sending failed, fall back to the customer link so the order is never lost.
        return $ok ? '' : whatsapp_link($text);
    }
    return whatsapp_link($text);
}
