<?php
/**
 * Ads & analytics: Facebook (Meta) Pixel, TikTok Pixel and Google Analytics 4, plus the cookie notice.
 * Admin > Settings > Tracking & Cookies.
 *
 * Shop events (view product, add to cart, start checkout, purchase) are queued with track_event()
 * and sent to every pixel that is set up. When the cookie notice is on, nothing loads until the visitor accepts.
 */

function tracking_enabled(): bool
{
    return setting('fb_pixel_id') !== '' || setting('tiktok_pixel_id') !== '' || setting('ga4_id') !== '';
}

/**
 * Queue an event. $data: ['value' => 12.5, 'items' => [['id' => 3, 'name' => 'Shirt', 'price' => 12.5, 'qty' => 1]], 'order_id' => '...'].
 * $nextPage = true keeps it for the next page (used before a redirect, e.g. add to cart).
 */
function track_event(string $name, array $data, bool $nextPage = false): void
{
    if (!tracking_enabled()) {
        return;
    }
    $data['currency'] = strtoupper(setting('currency_code', 'USD'));
    $event = ['name' => $name, 'data' => $data];
    if ($nextPage) {
        $_SESSION['track_events'][] = $event;
    } else {
        $GLOBALS['TRACK_EVENTS'][] = $event;
    }
}

function track_item(array $p, float $price, int $qty = 1): array
{
    return ['id' => (string) $p['id'], 'name' => (string) $p['name'], 'price' => round($price, 2), 'qty' => $qty];
}

/** <head> part: pixel loaders (only run after consent when the cookie notice is on) + extra head code. */
function tracking_head(): string
{
    $html = '';
    if (tracking_enabled()) {
        $cfg = [
            'fb' => preg_replace('/\D+/', '', setting('fb_pixel_id')),
            'tt' => preg_replace('/[^A-Za-z0-9]/', '', setting('tiktok_pixel_id')),
            'ga' => preg_replace('/[^A-Za-z0-9\-]/', '', setting('ga4_id')),
            'consent' => setting_on('cookie_enabled'),
        ];
        $html .= '<script>window.SHOP_TRACK_CFG=' . json_encode($cfg) . ';</script>' . "\n"
            . '<script src="' . asset('js/tracking.js') . '?v=' . APP_VERSION . '"></script>' . "\n";
    }
    if (trim(setting('head_code')) !== '') {
        $html .= setting('head_code') . "\n"; // written by the store owner (e.g. Google Search Console verification)
    }
    return $html;
}

/** Before </body>: the queued events + the cookie notice. */
function tracking_footer(): string
{
    $html = '';
    $events = array_merge($_SESSION['track_events'] ?? [], $GLOBALS['TRACK_EVENTS'] ?? []);
    unset($_SESSION['track_events']);
    if ($events && tracking_enabled()) {
        $html .= '<script>' . implode('', array_map(fn($ev) => 'SHOP_TRACK.push(' . json_encode($ev, JSON_HEX_TAG) . ');', $events)) . '</script>';
    }
    if (setting_on('cookie_enabled')) {
        $link = setting('cookie_link');
        $html .= '<div class="cookie-bar" id="cookie-bar" hidden><p>' . e(setting('cookie_text'))
            . ($link !== '' && setting('cookie_link_text') !== '' ? ' <a href="' . e(menu_url($link)) . '">' . e(setting('cookie_link_text')) . '</a>' : '')
            . '</p><div class="cookie-actions"><button type="button" class="btn btn-outline btn-sm" data-cookie="no">Decline</button>'
            . '<button type="button" class="btn btn-primary btn-sm" data-cookie="yes">Accept</button></div></div>';
    }
    return $html;
}
