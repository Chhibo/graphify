<?php
/**
 * Purchase code protection.
 *
 * includes/license-data.php holds one-way fingerprints (SHA-256) of the valid purchase codes, so the
 * real codes cannot be read from the files. When LICENSE_SERVER is set, each code is also registered
 * with the seller's license server, which allows one code per domain. If that server cannot be
 * reached the install still works and the domain is reported later by the scheduled jobs.
 */

/** Address of the license server's api.php, e.g. https://license.example.com/api.php ('' = offline check only). */
const LICENSE_SERVER = '';
const LICENSE_SALT = 'bazaarly-license-v1';
/** How often an installed site re-checks its code (seconds). */
const LICENSE_RECHECK = 7 * 86400;

function license_normalize(string $code): string
{
    $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    return str_starts_with($c, 'BZR') && strlen($c) === 19 ? substr($c, 3) : $c;
}

function license_hash(string $code): string
{
    return hash('sha256', LICENSE_SALT . '|' . license_normalize($code));
}

/** "BZR-7K4Q-****-****-PLA9" — safe to show on screen. */
function license_mask(string $code): string
{
    $c = license_normalize($code);
    if (strlen($c) !== 16) {
        return '—';
    }
    return 'BZR-' . substr($c, 0, 4) . '-****-****-' . substr($c, 12, 4);
}

function license_format(string $code): string
{
    $c = license_normalize($code);
    return strlen($c) === 16 ? 'BZR-' . implode('-', str_split($c, 4)) : trim($code);
}

/** True when the code is one of the codes shipped with this copy of the script. */
function license_known(string $code): bool
{
    static $hashes = null;
    if ($hashes === null) {
        $file = APP_ROOT . '/includes/license-data.php';
        $hashes = is_file($file) ? (array) require $file : [];
    }
    $c = license_normalize($code);
    return strlen($c) === 16 && isset($hashes[license_hash($c)]);
}

/** The site's domain without "www." and port, e.g. "example.com". */
function license_domain(?string $host = null): string
{
    $host = $host ?? (string) ($_SERVER['HTTP_HOST'] ?? parse_url((string) setting('site_url'), PHP_URL_HOST) ?? '');
    $host = strtolower(trim(preg_replace('/:\d+$/', '', $host) ?? ''));
    return preg_replace('/^www\./', '', $host) ?? $host;
}

/** Test and development addresses never use up an activation. */
function license_is_local(string $domain): bool
{
    return $domain === '' || $domain === 'localhost' || $domain === '[::1]'
        || (bool) preg_match('/\.(test|local|localhost|example|invalid)$/', $domain)
        || (bool) preg_match('/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/', $domain);
}

/**
 * Asks the license server about a code. Returns the decoded answer, or null when no server is
 * configured or it could not be reached (the caller then carries on without it).
 */
function license_server_call(string $action, string $code, string $domain): ?array
{
    if (LICENSE_SERVER === '') {
        return null;
    }
    $res = http_request(LICENSE_SERVER, 'POST', [
        'action' => $action, 'code' => license_format($code), 'domain' => $domain,
        'site_url' => full_url(), 'version' => APP_VERSION,
    ], ['Accept: application/json'], 10);
    $data = json_decode((string) $res['body'], true);
    if ($res['status'] < 200 || $res['status'] >= 500 || !is_array($data) || !isset($data['status'])) {
        return null;
    }
    return $data;
}

/**
 * Checks and registers a purchase code for this domain.
 * Returns ['ok' => bool, 'status' => active|pending|local|offline, 'error' => string].
 */
function license_activate(string $code, ?string $domain = null): array
{
    $domain = $domain ?? license_domain();
    if (!license_known($code)) {
        return ['ok' => false, 'status' => 'invalid', 'error' => 'This purchase code is not valid. Check it and try again (it looks like BZR-XXXX-XXXX-XXXX-XXXX).'];
    }
    if (license_is_local($domain)) {
        return ['ok' => true, 'status' => 'local', 'error' => ''];
    }
    $answer = license_server_call('activate', $code, $domain);
    if ($answer === null) {
        return ['ok' => true, 'status' => LICENSE_SERVER === '' ? 'offline' : 'pending', 'error' => ''];
    }
    if (($answer['status'] ?? '') === 'active') {
        return ['ok' => true, 'status' => 'active', 'error' => ''];
    }
    return ['ok' => false, 'status' => (string) $answer['status'], 'error' => (string) ($answer['message'] ?? 'This purchase code cannot be used on this website.')];
}

/** Saves the result of license_activate() for the admin panel and the scheduled re-checks. */
function license_store(string $code, array $result, ?string $domain = null): void
{
    save_settings([
        'license_code' => license_format($code),
        'license_domain' => $domain ?? license_domain(),
        'license_status' => $result['status'],
        'license_message' => $result['error'],
        'license_checked_at' => (string) time(),
    ]);
}

/** Run by the scheduled jobs: reports installs made while the server was offline and re-checks codes now and then. */
function license_recheck(bool $force = false): string
{
    $code = setting('license_code');
    if ($code === '' || LICENSE_SERVER === '') {
        return $code === '' ? 'no purchase code' : 'offline check only';
    }
    if (!$force && time() - (int) setting('license_checked_at', '0') < LICENSE_RECHECK && setting('license_status') !== 'pending') {
        return 'checked recently';
    }
    $domain = setting('license_domain') ?: license_domain();
    if (license_is_local($domain)) {
        return 'local install';
    }
    $answer = license_server_call(setting('license_status') === 'pending' ? 'activate' : 'check', $code, $domain);
    if ($answer === null) {
        save_settings(['license_checked_at' => (string) (time() - LICENSE_RECHECK + 86400)]); // try again tomorrow
        return 'server not reachable';
    }
    $status = (string) $answer['status'];
    save_settings([
        'license_status' => $status === 'active' ? 'active' : $status,
        'license_message' => $status === 'active' ? '' : (string) ($answer['message'] ?? ''),
        'license_checked_at' => (string) time(),
    ]);
    return $status;
}

/** Message for the admin panel when the purchase code needs attention ('' = all good). */
function license_problem(): string
{
    $code = setting('license_code');
    if ($code === '' || !license_known($code)) {
        return 'Enter your purchase code to register this website.';
    }
    // the domain is only compared on admin pages: visitors could send any Host header
    $here = license_domain();
    $saved = setting('license_domain');
    if (isset($_SERVER['HTTP_HOST']) && !license_is_local($here) && $here !== $saved) {
        return license_is_local($saved)
            ? "This website was installed on a test address. Enter your purchase code again to register $here."
            : "Your purchase code is registered for $saved, but this website now runs on $here. Enter your purchase code again to move it.";
    }
    return match (setting('license_status')) {
        'used' => setting('license_message') ?: 'This purchase code is already used on another website.',
        'blocked' => setting('license_message') ?: 'This purchase code has been disabled. Please contact the seller.',
        'invalid' => 'This purchase code is not valid.',
        default => '',
    };
}
