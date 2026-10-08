<?php
// CPA network adapters. Each network knows how to fetch offers for a visitor
// and tag every offer link with our unlock token so the postback can find it.
declare(strict_types=1);

/**
 * Every network's admin fields. Defaults are filled in on install and can be
 * edited later in Admin -> CPA Networks.
 */
function network_definitions(): array
{
    return [
        'ogads' => [
            'label' => 'OGAds',
            'fields' => [
                'api_key' => ['label' => 'API key', 'secret' => true,
                    'help' => 'OGAds dashboard → API. Looks like 12345|abcdef…'],
                'endpoint' => ['label' => 'Offers API URL', 'default' => 'https://unlockcontent.net/api/v2',
                    'help' => 'Leave as is unless OGAds gives you a different API URL.'],
                'sub_param' => ['label' => 'Tracking parameter', 'default' => 'aff_sub4',
                    'help' => 'The sub-ID we put your unlock token in. Use the same macro in the postback URL.'],
                'ctype' => ['label' => 'Offer type (ctype)', 'default' => '3',
                    'help' => '1 = app installs (CPI), 2 = actions/surveys (CPA), 3 = both.'],
            ],
            'postback_macro' => '{aff_sub4}',
            'payout_macro' => '{payout}',
            'offer_macro' => '{offer_id}',
        ],
        'adbluemedia' => [
            'label' => 'AdBlueMedia',
            'fields' => [
                'user_id' => ['label' => 'User ID',
                    'help' => 'Your numeric AdBlueMedia account / publisher ID (shown on the API or feed page).'],
                'api_key' => ['label' => 'API key', 'secret' => true,
                    'help' => 'AdBlueMedia dashboard → API / Offer Feed.'],
                'endpoint' => ['label' => 'Offer feed URL',
                    'default' => 'https://d2xohqmdyl2cj3.cloudfront.net/public/offers/feed.php?user_id={user_id}&api_key={api_key}&s1={token}&s2=',
                    'help' => 'Copy the feed URL from your AdBlueMedia API page if it differs. Placeholders: {user_id} {api_key} {token} {ip} {ua}.'],
                'sub_param' => ['label' => 'Tracking parameter', 'default' => 's1',
                    'help' => 'The sub-ID we put your unlock token in. Use the same macro in the postback URL.'],
            ],
            'postback_macro' => '{s1}',
            'payout_macro' => '{payout}',
            'offer_macro' => '{offer_id}',
        ],
    ];
}

function network_setting(string $net, string $field): string
{
    $defs = network_definitions();
    $default = $defs[$net]['fields'][$field]['default'] ?? '';
    return setting("net_{$net}_{$field}", $default);
}

function network_enabled(string $net): bool
{
    return setting("net_{$net}_enabled") === '1';
}

/** Enabled networks, in the order they should be tried. */
function networks_to_try(): array
{
    $enabled = array_values(array_filter(array_keys(network_definitions()), 'network_enabled'));
    if (setting('network_mode', 'priority') === 'random') {
        shuffle($enabled);
        return $enabled;
    }
    usort($enabled, fn($a, $b) => (int)setting("net_{$a}_priority", '1') <=> (int)setting("net_{$b}_priority", '1'));
    return $enabled;
}

/**
 * Fetch offers from one network.
 * Returns a list of ['id','title','description','image','payout','url'].
 * Throws RuntimeException with a readable message on failure.
 */
function fetch_offers(string $net, string $token, string $ip, string $ua, int $max): array
{
    switch ($net) {
        case 'ogads':
            return fetch_ogads($token, $ip, $ua, $max);
        case 'adbluemedia':
            return fetch_adbluemedia($token, $ip, $ua, $max);
    }
    throw new RuntimeException("Unknown network: $net");
}

function fetch_ogads(string $token, string $ip, string $ua, int $max): array
{
    $key = network_setting('ogads', 'api_key');
    if ($key === '') {
        throw new RuntimeException('OGAds API key is empty.');
    }
    $sub = network_setting('ogads', 'sub_param') ?: 'aff_sub4';
    $query = http_build_query([
        'ip' => $ip,
        'user_agent' => $ua,
        'ctype' => network_setting('ogads', 'ctype') ?: '3',
        'max' => $max,
        $sub => $token,
    ]);
    $endpoint = network_setting('ogads', 'endpoint');
    $data = http_get_json($endpoint . (strpos($endpoint, '?') === false ? '?' : '&') . $query, [
        'Authorization: Bearer ' . $key,
    ]);
    if (isset($data['success']) && !$data['success']) {
        throw new RuntimeException('OGAds: ' . (is_string($data['error'] ?? null) ? $data['error'] : 'request refused'));
    }
    return normalize_offers($data['offers'] ?? $data, $sub, $token, $max);
}

function fetch_adbluemedia(string $token, string $ip, string $ua, int $max): array
{
    $key = network_setting('adbluemedia', 'api_key');
    if ($key === '') {
        throw new RuntimeException('AdBlueMedia API key is empty.');
    }
    $url = strtr(network_setting('adbluemedia', 'endpoint'), [
        '{user_id}' => rawurlencode(network_setting('adbluemedia', 'user_id')),
        '{api_key}' => rawurlencode($key),
        '{token}' => rawurlencode($token),
        '{ip}' => rawurlencode($ip),
        '{ua}' => rawurlencode($ua),
    ]);
    $data = http_get_json($url, [
        // Some feeds geo-target on these headers rather than on query params.
        'X-Forwarded-For: ' . $ip,
        'User-Agent: ' . $ua,
    ]);
    if (isset($data['error']) && is_string($data['error']) && $data['error'] !== '') {
        throw new RuntimeException('AdBlueMedia: ' . $data['error']);
    }
    $sub = network_setting('adbluemedia', 'sub_param') ?: 's1';
    return normalize_offers($data['offers'] ?? $data, $sub, $token, $max);
}

/** Turn whatever shape a network returns into our common offer format. */
function normalize_offers($list, string $subParam, string $token, int $max): array
{
    if (!is_array($list)) {
        return [];
    }
    $pick = function (array $o, array $keys): string {
        foreach ($keys as $k) {
            if (isset($o[$k]) && is_scalar($o[$k]) && trim((string)$o[$k]) !== '') {
                return trim((string)$o[$k]);
            }
        }
        return '';
    };
    $offers = [];
    foreach ($list as $o) {
        if (!is_array($o)) {
            continue;
        }
        $url = $pick($o, ['link', 'url', 'tracking_url', 'offer_url']);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            continue;
        }
        $offers[] = [
            'id' => $pick($o, ['offerid', 'offer_id', 'id']),
            'title' => strip_tags($pick($o, ['name_short', 'anchor', 'name', 'title'])),
            'description' => strip_tags($pick($o, ['adcopy', 'conversion', 'description', 'requirements'])),
            'image' => $pick($o, ['picture', 'network_icon', 'image', 'icon', 'img']),
            'payout' => (float)$pick($o, ['payout', 'rate', 'amount']),
            'url' => add_sub_param($url, $subParam, $token),
        ];
        if (count($offers) >= $max) {
            break;
        }
    }
    return $offers;
}

/** Make sure the offer link carries our token, without duplicating it. */
function add_sub_param(string $url, string $param, string $token): string
{
    if (strpos($url, $token) !== false) {
        return $url;
    }
    return $url . (strpos($url, '?') === false ? '?' : '&') . rawurlencode($param) . '=' . rawurlencode($token);
}

function http_get_json(string $url, array $headers = []): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is not enabled on this hosting.');
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false) {
        throw new RuntimeException('Connection failed: ' . $err);
    }
    // Strip a JSONP wrapper like callback({...}); if the feed returns one.
    $trim = trim((string)$body);
    if ($trim !== '' && $trim[0] !== '{' && $trim[0] !== '[' && preg_match('/^[\w$.]*\((.*)\);?$/s', $trim, $m)) {
        $trim = $m[1];
    }
    $data = json_decode($trim, true);
    if (!is_array($data)) {
        throw new RuntimeException("Unexpected response (HTTP $code): " . mb_substr($trim, 0, 200));
    }
    if ($code >= 400) {
        $msg = $data['message'] ?? $data['error'] ?? "HTTP $code";
        throw new RuntimeException(is_string($msg) ? $msg : "HTTP $code");
    }
    return $data;
}

/** The URL to paste into a network's postback / callback settings. */
function postback_url(string $net): string
{
    $def = network_definitions()[$net];
    $sub = network_setting($net, 'sub_param');
    $macro = $sub !== '' ? '{' . $sub . '}' : $def['postback_macro'];
    return base_url('postback.php') . '?network=' . $net
        . '&secret=' . setting('postback_secret')
        . '&token=' . $macro
        . '&payout=' . $def['payout_macro']
        . '&offer=' . $def['offer_macro'];
}
