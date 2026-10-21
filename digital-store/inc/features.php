<?php
// Helpers for the extra features: Telegram alerts, countries, pretty URLs,
// YouTube previews, two-step login codes and CSV export.
declare(strict_types=1);

/* ---------- Telegram ---------- */

function telegram_ready(): bool
{
    return setting('tg_token') !== '' && setting('tg_chat') !== '';
}

/** Send a message to the owner's Telegram. Returns '' on success or the error text. */
function telegram_send(string $text): string
{
    if (!telegram_ready()) {
        return 'Telegram bot token or chat ID is empty.';
    }
    if (!function_exists('curl_init')) {
        return 'PHP cURL extension is not enabled.';
    }
    $ch = curl_init('https://api.telegram.org/bot' . rawurlencode(setting('tg_token')) . '/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['chat_id' => setting('tg_chat'), 'text' => $text, 'disable_web_page_preview' => 1]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 6,
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        return 'Could not reach Telegram: ' . $err;
    }
    $data = json_decode((string)$body, true);
    return !empty($data['ok']) ? '' : 'Telegram said: ' . ($data['description'] ?? 'unknown error');
}

/* ---------- Countries ---------- */

/** Two-letter country code of the visitor, or '' when unknown. */
function visitor_country(): string
{
    $code = '';
    if (setting('trust_cloudflare') === '1' && !empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
        $code = $_SERVER['HTTP_CF_IPCOUNTRY'];
    } elseif (!empty($_SERVER['GEOIP_COUNTRY_CODE'])) {
        $code = $_SERVER['GEOIP_COUNTRY_CODE']; // set by the web server's GeoIP module
    } elseif (!empty($_SERVER['MM_COUNTRY_CODE'])) {
        $code = $_SERVER['MM_COUNTRY_CODE'];
    }
    $code = strtoupper(trim((string)$code));
    return preg_match('/^[A-Z]{2}$/', $code) && !in_array($code, ['XX', 'T1'], true) ? $code : '';
}

/** "us, GB,ca" -> ['US','GB','CA'] */
function country_list(string $raw): array
{
    preg_match_all('/[A-Za-z]{2}/', $raw, $m);
    return array_values(array_unique(array_map('strtoupper', $m[0])));
}

/** Flag emoji for a country code. */
function flag(string $code): string
{
    if (!preg_match('/^[A-Z]{2}$/', $code)) {
        return '🌐';
    }
    return mb_chr(0x1F1E6 + ord($code[0]) - 65) . mb_chr(0x1F1E6 + ord($code[1]) - 65);
}

/* ---------- Pretty URLs ---------- */

function pretty_urls(): bool
{
    return setting('pretty_urls') === '1';
}

/** Asks this site for /l/__rewrite_test and checks the rewrite rules answered. */
function rewrite_works(): bool
{
    if (!function_exists('curl_init')) {
        return false;
    }
    $ch = curl_init(base_url('l/__rewrite_test'));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 6, CURLOPT_FOLLOWLOCATION => true]);
    $body = curl_exec($ch);
    curl_close($ch);
    return is_string($body) && trim($body) === 'rewrite-ok';
}

function url_product(array $p): string
{
    if (pretty_urls() && ($p['slug'] ?? '') !== '') {
        return base_url('product/' . rawurlencode($p['slug']));
    }
    return base_url('product.php?id=' . (int)$p['id']);
}

function url_category(string $slug): string
{
    return pretty_urls() ? base_url('category/' . rawurlencode($slug)) : base_url('category.php?slug=' . rawurlencode($slug));
}

function url_page(string $slug): string
{
    return pretty_urls() ? base_url('page/' . rawurlencode($slug)) : base_url('page.php?slug=' . rawurlencode($slug));
}

function url_link(string $code): string
{
    return pretty_urls() ? base_url('l/' . rawurlencode($code)) : base_url('go.php?c=' . rawurlencode($code));
}

/* ---------- Media ---------- */

/** YouTube video ID from any YouTube URL, or ''. */
function youtube_id(string $url): string
{
    if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
        return $m[1];
    }
    return '';
}

function human_size(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $n = (float)$bytes;
    while ($n >= 1024 && $i < 3) {
        $n /= 1024;
        $i++;
    }
    return ($i ? number_format($n, $n < 10 ? 1 : 0) : (string)$bytes) . ' ' . $units[$i];
}

/* ---------- Two-step login (TOTP, works with Google Authenticator) ---------- */

function base32_encode_bytes(string $bin): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }
    return $out;
}

function base32_decode_str(string $b32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32))) as $c) {
        $bits .= str_pad(decbin(strpos($alphabet, $c)), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

function totp_code(string $secret, int $step): string
{
    $hash = hash_hmac('sha1', pack('N*', 0, $step), base32_decode_str($secret), true);
    $o = ord($hash[19]) & 0xf;
    $n = ((ord($hash[$o]) & 0x7f) << 24) | (ord($hash[$o + 1]) << 16) | (ord($hash[$o + 2]) << 8) | ord($hash[$o + 3]);
    return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Accepts the current code and the ones just before/after (clock drift). */
function totp_verify(string $secret, string $code): bool
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) {
        return false;
    }
    $step = intdiv(time(), 30);
    foreach ([-1, 0, 1] as $d) {
        if (hash_equals(totp_code($secret, $step + $d), $code)) {
            return true;
        }
    }
    return false;
}

/* ---------- CSV ---------- */

function csv_download(string $filename, array $header, iterable $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // so Excel opens UTF-8 correctly
    fputcsv($out, $header, ',', '"', '');
    foreach ($rows as $row) {
        // Stop spreadsheet apps from running cell values as formulas.
        fputcsv($out, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, array_values($row)), ',', '"', '');
    }
    fclose($out);
    exit;
}
