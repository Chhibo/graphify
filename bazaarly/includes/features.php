<?php
/**
 * Feature helpers added in v1.1: upgrades, HTTP, activity log, captcha, banned words,
 * blocking, email verification, category fields, locations, search, saved searches, cron.
 */
declare(strict_types=1);

const DB_VERSION = '3';

/* --------------------------------------------------------------- upgrades */

function run_migrations(): void
{
    require_once APP_ROOT . '/includes/schema.php';
    $pdo = db();
    $driver = $GLOBALS['config']['db']['driver'] ?? 'sqlite';
    $statements = schema_statements($driver);
    // 1. new tables
    foreach ($statements as $sql) {
        if (str_starts_with($sql, 'CREATE TABLE')) {
            $pdo->exec($sql);
        }
    }
    // 2. new columns on existing tables
    foreach (schema_added_columns() as [$table, $col, $def]) {
        try {
            $pdo->query("SELECT $col FROM $table LIMIT 1");
        } catch (Throwable) {
            try {
                $pdo->exec("ALTER TABLE $table ADD COLUMN $col " . column_definition($def, $driver));
            } catch (Throwable) {
                // another request added it meanwhile
            }
        }
    }
    // 3. indexes (SQLite)
    foreach ($statements as $sql) {
        if (!str_starts_with($sql, 'CREATE TABLE')) {
            try {
                $pdo->exec($sql);
            } catch (Throwable) {
            }
        }
    }
    $from = (int) (setting('db_version') ?: '1');
    if ($from < 2) {
        // existing members are treated as verified
        q('UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL');
        // category slugs must be unique for clean URLs
        $seen = [];
        foreach (q_all('SELECT id, slug FROM categories ORDER BY id') as $c) {
            $slug = $c['slug'];
            $n = 2;
            while (isset($seen[$slug])) {
                $slug = $c['slug'] . '-' . $n++;
            }
            $seen[$slug] = true;
            if ($slug !== $c['slug']) {
                q('UPDATE categories SET slug = ? WHERE id = ?', [$slug, $c['id']]);
            }
        }
        $extra = ['location_input' => 'text', 'cron_key' => bin2hex(random_bytes(12))];
        if (setting('notify_new_message') === '0') {
            $extra['email_tpl_new_message_enabled'] = '0';
        }
        save_settings($extra);
    }
    if ($from < 3) {
        // existing messages count as read; seed the language table
        q('UPDATE messages SET read_at = created_at WHERE read_at IS NULL');
        seed_languages();
    }
    save_settings(['db_version' => DB_VERSION]);
}

/* ------------------------------------------------------------------- HTTP */

/** Small HTTP client (cURL or streams). Returns ['status' => int, 'body' => string, 'error' => string]. */
function http_request(string $url, string $method = 'GET', array|string|null $data = null, array $headers = [], int $timeout = 12): array
{
    if (!preg_match('~^https?://~i', $url)) {
        return ['status' => 0, 'body' => '', 'error' => 'Only http(s) URLs are allowed.'];
    }
    $body = is_array($data) ? http_build_query($data) : $data;
    if ($body !== null && !preg_grep('/^content-type:/i', $headers)) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    $headers[] = 'User-Agent: Bazaarly/' . APP_VERSION;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $res = curl_exec($ch);
        $out = ['status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'body' => is_string($res) ? $res : '', 'error' => curl_error($ch)];
        curl_close($ch);
        return $out;
    }
    $ctx = stream_context_create(['http' => [
        'method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body ?? '',
        'timeout' => $timeout, 'ignore_errors' => true, 'follow_location' => 1, 'max_redirects' => 3,
    ]]);
    $res = @file_get_contents($url, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) {
            $status = (int) $m[1];
        }
    }
    return ['status' => $status, 'body' => is_string($res) ? $res : '', 'error' => $res === false ? 'Request failed.' : ''];
}

/* -------------------------------------------------------------- activity */

function log_activity(string $action, string $details = '', string $type = '', int $subjectId = 0): void
{
    try {
        db_insert('activity_log', [
            'user_id' => user_id(), 'action' => $action, 'subject_type' => $type, 'subject_id' => $subjectId,
            'details' => mb_substr($details, 0, 1000), 'ip' => client_ip(), 'created_at' => now(),
        ]);
    } catch (Throwable) {
    }
}

/* ---------------------------------------------------------------- captcha */

function captcha_provider(): string
{
    $p = setting('captcha_provider', 'none');
    return in_array($p, ['recaptcha', 'turnstile'], true) && setting('captcha_site_key') !== '' && setting('captcha_secret_key') !== '' ? $p : 'none';
}

function captcha_enabled(string $form): bool
{
    return captcha_provider() !== 'none' && setting('captcha_on_' . $form) === '1';
}

function captcha_widget(string $form): string
{
    if (!captcha_enabled($form)) {
        return '';
    }
    $key = e(setting('captcha_site_key'));
    if (captcha_provider() === 'recaptcha') {
        return '<div class="captcha"><div class="g-recaptcha" data-sitekey="' . $key . '"></div></div><script src="https://www.google.com/recaptcha/api.js" async defer></script>';
    }
    return '<div class="captcha"><div class="cf-turnstile" data-sitekey="' . $key . '"></div></div><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
}

function captcha_passed(string $form): bool
{
    if (!captcha_enabled($form)) {
        return true;
    }
    $recaptcha = captcha_provider() === 'recaptcha';
    $response = (string) ($_POST[$recaptcha ? 'g-recaptcha-response' : 'cf-turnstile-response'] ?? '');
    if ($response === '') {
        return false;
    }
    $url = $recaptcha ? 'https://www.google.com/recaptcha/api/siteverify' : 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $res = http_request($url, 'POST', ['secret' => setting('captcha_secret_key'), 'response' => $response, 'remoteip' => client_ip()]);
    $json = json_decode($res['body'], true);
    return is_array($json) && !empty($json['success']);
}

/* ---------------------------------------------------------- banned words */

function banned_words(): array
{
    $words = preg_split('/[\r\n,]+/', setting('banned_words')) ?: [];
    return array_values(array_filter(array_map(fn($w) => trim(mb_strtolower($w)), $words), fn($w) => $w !== ''));
}

/** Returns the first banned word/phrase found in the given texts, or null. */
function find_banned_word(string ...$texts): ?string
{
    $words = banned_words();
    if (!$words) {
        return null;
    }
    $hay = mb_strtolower(implode("\n", $texts));
    foreach ($words as $w) {
        if (preg_match('/(?<![\pL\pN])' . preg_quote($w, '/') . '(?![\pL\pN])/iu', $hay)) {
            return $w;
        }
    }
    return null;
}

/* --------------------------------------------------------------- blocking */

function is_blocked(int $byUser, int $blockedUser): bool
{
    return (bool) q_val('SELECT id FROM user_blocks WHERE user_id = ? AND blocked_id = ?', [$byUser, $blockedUser]);
}

/* ------------------------------------------------------ email verification */

function needs_email_verification(?array $u = null): bool
{
    $u ??= current_user();
    return $u && setting('require_email_verification') === '1' && empty($u['email_verified_at']) && $u['role'] !== 'admin';
}

function issue_token(int $userId, string $type, int $ttlSeconds): string
{
    $token = bin2hex(random_bytes(32));
    q('DELETE FROM user_tokens WHERE user_id = ? AND type = ?', [$userId, $type]);
    db_insert('user_tokens', ['user_id' => $userId, 'type' => $type, 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds)]);
    return $token;
}

function consume_token(string $token, string $type): ?int
{
    $row = $token === '' ? null : q_one('SELECT * FROM user_tokens WHERE token_hash = ? AND type = ? AND expires_at > ?', [hash('sha256', $token), $type, now()]);
    if (!$row) {
        return null;
    }
    q('DELETE FROM user_tokens WHERE user_id = ? AND type = ?', [$row['user_id'], $type]);
    return (int) $row['user_id'];
}

function send_verification_email(array $u): bool
{
    $token = issue_token((int) $u['id'], 'verify', 48 * 3600);
    return send_template('verify_email', $u['email'], ['name' => $u['name'], 'action_url' => full_url('verify.php?token=' . $token)]);
}

/** Stop unverified users from posting / messaging. */
function require_verified_email(string $back = 'dashboard/'): void
{
    if (needs_email_verification()) {
        flash('warning', __('Please verify your email address first. Check your inbox for the link, or resend it from your dashboard.'));
        redirect($back);
    }
}

/* -------------------------------------------------------- category fields */

const FIELD_TYPES = ['text' => 'Text', 'number' => 'Number', 'select' => 'Dropdown list', 'checkbox' => 'Yes / No'];

function fields_all(bool $refresh = false): array
{
    static $all = null;
    if ($all === null || $refresh) {
        $all = [];
        foreach (q_all('SELECT * FROM category_fields ORDER BY sort_order, id') as $f) {
            $all[(int) $f['id']] = $f;
        }
    }
    return $all;
}

/** Fields that apply to a category: its own plus its parent's. */
function fields_for_category(int $catId): array
{
    if (!$catId) {
        return [];
    }
    $cat = categories_all()[$catId] ?? null;
    $ids = [$catId];
    if ($cat && (int) $cat['parent_id']) {
        $ids[] = (int) $cat['parent_id'];
    }
    return array_filter(fields_all(), fn($f) => in_array((int) $f['category_id'], $ids, true));
}

function field_options(array $f): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $f['options']) ?: []), fn($o) => $o !== ''));
}

function listing_field_values(int $listingId): array
{
    $out = [];
    foreach (q_all('SELECT field_id, value FROM listing_field_values WHERE listing_id = ?', [$listingId]) as $r) {
        $out[(int) $r['field_id']] = $r['value'];
    }
    return $out;
}

/** Normalise posted values for a category. Returns [values, errors]. */
function clean_field_input(int $catId, array $input): array
{
    $values = [];
    $errors = [];
    foreach (fields_for_category($catId) as $id => $f) {
        $raw = $input[$id] ?? '';
        $v = is_string($raw) ? trim($raw) : '';
        switch ($f['type']) {
            case 'number':
                $v = str_replace([',', ' '], '', $v);
                if ($v !== '' && !is_numeric($v)) {
                    $errors['cf_' . $id] = __('{field} must be a number.', ['field' => __($f['label'])]);
                    continue 2;
                }
                break;
            case 'select':
                if ($v !== '' && !in_array($v, field_options($f), true)) {
                    $v = '';
                }
                break;
            case 'checkbox':
                $v = $v !== '' ? '1' : '';
                break;
            default:
                $v = mb_substr($v, 0, 190);
        }
        if ($v === '' && $f['required'] && $f['type'] !== 'checkbox') {
            $errors['cf_' . $id] = __('{field} is required.', ['field' => __($f['label'])]);
            continue;
        }
        if ($v !== '') {
            $values[$id] = $v;
        }
    }
    return [$values, $errors];
}

function save_listing_fields(int $listingId, array $values): void
{
    q('DELETE FROM listing_field_values WHERE listing_id = ?', [$listingId]);
    foreach ($values as $fid => $v) {
        db_insert('listing_field_values', ['listing_id' => $listingId, 'field_id' => $fid, 'value' => (string) $v]);
    }
}

function format_field_value(array $f, string $v): string
{
    if ($f['type'] === 'checkbox') {
        return $v === '1' ? __('Yes') : __('No');
    }
    if ($f['type'] === 'number' && is_numeric($v)) {
        // thousands separators for big numbers only (keeps years like 2019 readable)
        $v = rtrim(rtrim(number_format((float) $v, 2, '.', abs((float) $v) >= 10000 ? ',' : ''), '0'), '.');
    }
    if ($f['type'] === 'select') {
        $v = __($v);
    }
    return $v . ($f['unit'] !== '' ? ' ' . $f['unit'] : '');
}

/* ------------------------------------------------------------- locations */

function locations_all(bool $refresh = false): array
{
    static $all = null;
    if ($all === null || $refresh) {
        $all = [];
        try {
            foreach (q_all('SELECT * FROM locations ORDER BY sort_order, name') as $l) {
                $all[(int) $l['id']] = $l;
            }
        } catch (Throwable) {
        }
    }
    return $all;
}

function location_tree(): array
{
    $tree = [];
    $all = locations_all();
    foreach ($all as $id => $l) {
        if (!(int) $l['parent_id'] || !isset($all[(int) $l['parent_id']])) {
            $tree[$id] = $l + ['children' => []];
        }
    }
    foreach ($all as $id => $l) {
        if ((int) $l['parent_id'] && isset($tree[(int) $l['parent_id']])) {
            $tree[(int) $l['parent_id']]['children'][$id] = $l;
        }
    }
    return $tree;
}

function location_list_mode(): bool
{
    return setting('location_input') === 'list' && locations_all();
}

function location_label(int $id): string
{
    $all = locations_all();
    $l = $all[$id] ?? null;
    if (!$l) {
        return '';
    }
    $p = $all[(int) $l['parent_id']] ?? null;
    return $p ? $l['name'] . ', ' . $p['name'] : $l['name'];
}

function location_ids_with_children(int $id): array
{
    $ids = [$id];
    foreach (locations_all() as $lid => $l) {
        if ((int) $l['parent_id'] === $id) {
            $ids[] = $lid;
        }
    }
    return $ids;
}

function location_options(int $selected = 0): string
{
    $out = '';
    foreach (location_tree() as $id => $l) {
        $out .= '<option value="' . $id . '"' . ($id === $selected ? ' selected' : '') . '>' . e($l['name']) . '</option>';
        foreach ($l['children'] as $cid => $c) {
            $out .= '<option value="' . $cid . '"' . ($cid === $selected ? ' selected' : '') . '>&nbsp;&nbsp;&nbsp;' . e($c['name']) . '</option>';
        }
    }
    return $out;
}

/* ---------------------------------------------------------------- search */

/**
 * Turns a query array (from $_GET or a saved search) into SQL conditions.
 * Returns ['where', 'params', 'values' (normalised), 'category', 'location', 'active'].
 */
function listing_search(array $in): array
{
    [$where, $params] = visible_listing_where();
    $str = fn(string $k) => is_string($in[$k] ?? null) ? trim($in[$k]) : '';
    $v = [
        'q' => mb_substr($str('q'), 0, 100),
        'category' => (int) $str('category'),
        'location' => mb_substr($str('location'), 0, 100),
        'loc' => (int) $str('loc'),
        'min' => is_numeric($str('min')) ? $str('min') : '',
        'max' => is_numeric($str('max')) ? $str('max') : '',
        'condition' => in_array($str('condition'), ['new', 'like_new', 'used', 'refurbished'], true) ? $str('condition') : '',
        'posted' => in_array($str('posted'), ['today', 'week', 'month'], true) ? $str('posted') : '',
        'featured' => $str('featured') === '1' ? '1' : '',
        'f' => [], 'fmin' => [], 'fmax' => [],
    ];
    if (!$v['category'] && $str('category_slug') !== '') {
        foreach (categories_all() as $cid => $c) {
            if ($c['slug'] === $str('category_slug')) {
                $v['category'] = $cid;
                break;
            }
        }
    }
    $active = 0;
    if ($v['q'] !== '') {
        $where .= ' AND (l.title LIKE ? OR l.description LIKE ? OR l.tags LIKE ?)';
        $like = '%' . $v['q'] . '%';
        array_push($params, $like, $like, $like);
        $active++;
    }
    $category = $v['category'] ? (categories_all()[$v['category']] ?? null) : null;
    if ($category) {
        $where .= ' AND l.category_id IN (' . implode(',', array_map('intval', category_ids_with_children($v['category']))) . ')';
        $active++;
    } else {
        $v['category'] = 0;
    }
    $location = $v['loc'] ? (locations_all()[$v['loc']] ?? null) : null;
    if ($location) {
        $where .= ' AND l.location_id IN (' . implode(',', array_map('intval', location_ids_with_children($v['loc']))) . ')';
        $active++;
    } else {
        $v['loc'] = 0;
    }
    if ($v['location'] !== '') {
        $where .= ' AND l.location LIKE ?';
        $params[] = '%' . $v['location'] . '%';
        $active++;
    }
    if ($v['min'] !== '') {
        $where .= " AND l.price >= ? AND l.price_type IN ('fixed','negotiable')";
        $params[] = (float) $v['min'];
        $active++;
    }
    if ($v['max'] !== '') {
        $where .= " AND l.price <= ? AND l.price_type <> 'contact'";
        $params[] = (float) $v['max'];
        $active++;
    }
    if ($v['condition'] !== '') {
        $where .= ' AND l.item_condition = ?';
        $params[] = $v['condition'];
        $active++;
    }
    if ($v['posted'] !== '') {
        $where .= ' AND l.created_at >= ?';
        $params[] = date('Y-m-d H:i:s', time() - ['today' => 1, 'week' => 7, 'month' => 30][$v['posted']] * 86400);
        $active++;
    }
    if ($v['featured']) {
        $where .= ' AND l.featured = 1';
        $active++;
    }
    // custom field filters
    $fields = $category ? fields_for_category($v['category']) : [];
    $exists = 'EXISTS (SELECT 1 FROM listing_field_values fv WHERE fv.listing_id = l.id AND fv.field_id = ? AND ';
    foreach ($fields as $fid => $f) {
        if (!$f['filterable']) {
            continue;
        }
        $val = is_array($in['f'] ?? null) && is_string($in['f'][$fid] ?? null) ? trim($in['f'][$fid]) : '';
        if ($f['type'] === 'number') {
            foreach (['fmin' => '>=', 'fmax' => '<='] as $key => $op) {
                $n = is_array($in[$key] ?? null) && is_string($in[$key][$fid] ?? null) ? trim($in[$key][$fid]) : '';
                if ($n !== '' && is_numeric($n)) {
                    $where .= ' AND ' . $exists . "CAST(fv.value AS DECIMAL(20,4)) $op ?)";
                    array_push($params, $fid, (float) $n);
                    $v[$key][$fid] = $n;
                    $active++;
                }
            }
        } elseif ($val !== '') {
            if ($f['type'] === 'text') {
                $where .= ' AND ' . $exists . 'fv.value LIKE ?)';
                array_push($params, $fid, '%' . $val . '%');
            } else {
                $where .= ' AND ' . $exists . 'fv.value = ?)';
                array_push($params, $fid, $f['type'] === 'checkbox' ? '1' : $val);
            }
            $v['f'][$fid] = $val;
            $active++;
        }
    }
    return ['where' => $where, 'params' => $params, 'values' => $v, 'category' => $category, 'location' => $location, 'fields' => $fields, 'active' => $active];
}

/** Query-string array for a normalised search (used for links & saved searches). */
function search_query(array $v): array
{
    $out = [];
    foreach (['q', 'category', 'location', 'loc', 'min', 'max', 'condition', 'posted', 'featured'] as $k) {
        if (($v[$k] ?? '') !== '' && ($v[$k] ?? 0) !== 0) {
            $out[$k] = $v[$k];
        }
    }
    foreach (['f', 'fmin', 'fmax'] as $k) {
        if (!empty($v[$k])) {
            $out[$k] = $v[$k];
        }
    }
    return $out;
}

/** Human summary of a saved search, e.g. "iphone · Phones · under $500". */
function search_summary(array $v): string
{
    $parts = [];
    if ($v['q'] !== '') $parts[] = '“' . $v['q'] . '”';
    if ($v['category']) $parts[] = categories_all()[$v['category']]['name'] ?? '';
    if ($v['loc']) $parts[] = location_label($v['loc']);
    if ($v['location'] !== '') $parts[] = $v['location'];
    if ($v['min'] !== '' && $v['max'] !== '') $parts[] = money($v['min']) . ' – ' . money($v['max']);
    elseif ($v['min'] !== '') $parts[] = __('from {price}', ['price' => money($v['min'])]);
    elseif ($v['max'] !== '') $parts[] = __('under {price}', ['price' => money($v['max'])]);
    if ($v['condition'] !== '') $parts[] = condition_label($v['condition']);
    $all = fields_all();
    foreach ($v['f'] as $fid => $val) {
        if (isset($all[$fid])) $parts[] = __($all[$fid]['label']) . ': ' . ($all[$fid]['type'] === 'checkbox' ? __('Yes') : __($val));
    }
    foreach ($v['fmin'] as $fid => $n) {
        if (isset($all[$fid])) $parts[] = __($all[$fid]['label']) . ' ≥ ' . $n;
    }
    foreach ($v['fmax'] as $fid => $n) {
        if (isset($all[$fid])) $parts[] = __($all[$fid]['label']) . ' ≤ ' . $n;
    }
    if ($v['featured']) $parts[] = __('featured only');
    return $parts ? implode(' · ', array_filter($parts)) : __('All ads');
}

/* -------------------------------------------------------- saved searches */

function run_saved_search_alerts(int $limit = 200): int
{
    if (setting('enable_saved_searches', '1') !== '1') {
        return 0;
    }
    $sent = 0;
    $rows = q_all("SELECT s.*, u.name AS user_name, u.email FROM saved_searches s JOIN users u ON u.id = s.user_id
        WHERE s.alerts = 1 AND u.status = 'active' ORDER BY s.last_alert_at ASC LIMIT " . (int) $limit);
    foreach ($rows as $s) {
        parse_str((string) $s['query'], $query);
        $r = listing_search($query);
        $since = $s['last_alert_at'] ?: $s['created_at'];
        $params = array_merge($r['params'], [$since, $s['user_id']]);
        $total = (int) q_val("SELECT COUNT(*) FROM listings l JOIN users u ON u.id = l.user_id WHERE {$r['where']} AND l.created_at > ? AND l.user_id <> ?", $params);
        q('UPDATE saved_searches SET last_alert_at = ? WHERE id = ?', [now(), $s['id']]);
        if (!$total) {
            continue;
        }
        $ads = q_all(listing_select() . " WHERE {$r['where']} AND l.created_at > ? AND l.user_id <> ? ORDER BY l.created_at DESC LIMIT 10", $params);
        $lines = array_map(fn($l) => '• ' . $l['title'] . ' — ' . listing_price($l) . "\n  " . abs_url(listing_url($l)), $ads);
        if (send_template('saved_search_alert', $s['email'], [
            'name' => $s['user_name'], 'search_name' => $s['name'], 'count' => $total,
            'ads' => implode("\n", $lines), 'action_url' => abs_url(search_url(search_query($r['values']))),
        ])) {
            $sent++;
        }
    }
    return $sent;
}

/* ------------------------------------------------------------------ cron */

function run_cron(): array
{
    save_settings(['last_cron_run' => (string) time()]);
    $log = [];
    expire_listings();
    $log[] = 'Expired old ads.';
    $log[] = 'Saved-search alerts sent: ' . run_saved_search_alerts();
    $log[] = 'Expiry reminders sent: ' . run_expiry_reminders();
    $log[] = 'Queued emails sent: ' . process_mail_queue(200);
    $log[] = 'Purchase code: ' . license_recheck();
    if (setting('duplicate_detection', 'flag') !== 'off') {
        $log[] = 'Photo fingerprints added: ' . backfill_phashes();
    }
    q('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    q('DELETE FROM user_tokens WHERE expires_at < ?', [now()]);
    q('DELETE FROM password_resets WHERE expires_at < ?', [now()]);
    $days = (int) setting('activity_log_days', '180');
    if ($days > 0) {
        q('DELETE FROM activity_log WHERE created_at < ?', [date('Y-m-d H:i:s', time() - $days * 86400)]);
    }
    $log[] = 'Cleaned up old tokens and logs.';
    return $log;
}

/** Runs scheduled jobs after the page is sent, at most every 15 minutes, when no real cron job is set up. */
function maybe_pseudo_cron(): void
{
    if (setting('pseudo_cron', '1') !== '1' || is_post()) {
        return;
    }
    if (time() - (int) setting('last_cron_run', '0') < 900) {
        // between full runs, keep the email queue moving (newsletters, price-drop alerts…)
        if (time() - (int) setting('last_queue_run', '0') >= 60 && mail_queue_pending() > 0) {
            save_settings(['last_queue_run' => (string) time()]);
            register_shutdown_function(function () {
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                try {
                    process_mail_queue(30);
                } catch (Throwable $e) {
                    error_log('Bazaarly mail queue: ' . $e->getMessage());
                }
            });
        }
        return;
    }
    save_settings(['last_cron_run' => (string) time()]);
    register_shutdown_function(function () {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        try {
            run_cron();
        } catch (Throwable $e) {
            error_log('Bazaarly cron: ' . $e->getMessage());
        }
    });
}

/* --------------------------------------------------------- recently viewed */

function remember_view(int $id): void
{
    $ids = array_values(array_diff($_SESSION['recent'] ?? [], [$id]));
    array_unshift($ids, $id);
    $_SESSION['recent'] = array_slice($ids, 0, 12);
}

function recently_viewed(int $limit = 8, int $exclude = 0): array
{
    $ids = array_values(array_diff(array_map('intval', $_SESSION['recent'] ?? []), [$exclude]));
    if (!$ids) {
        return [];
    }
    [$w, $p] = visible_listing_where();
    $rows = q_all(listing_select() . " WHERE $w AND l.id IN (" . implode(',', $ids) . ')', $p);
    $byId = array_column($rows, null, 'id');
    $out = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $out[] = $byId[$id];
        }
    }
    return array_slice($out, 0, $limit);
}

/* ---------------------------------------------------------- social login */

function social_providers(): array
{
    $out = [];
    if (setting('social_login') === '1') {
        if (setting('google_login') === '1' && setting('google_client_id') !== '' && setting('google_client_secret') !== '') {
            $out['google'] = 'Google';
        }
        if (setting('facebook_login') === '1' && setting('facebook_app_id') !== '' && setting('facebook_app_secret') !== '') {
            $out['facebook'] = 'Facebook';
        }
    }
    return $out;
}

function social_buttons(string $verb = 'Continue'): string
{
    $providers = social_providers();
    if (!$providers) {
        return '';
    }
    $logos = [
        'google' => '<svg viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>',
        'facebook' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#1877F2" d="M24 12a12 12 0 1 0-13.9 11.9v-8.4H7.1V12h3V9.4c0-3 1.8-4.7 4.5-4.7 1.3 0 2.7.2 2.7.2v2.9h-1.5c-1.5 0-2 .9-2 1.9V12h3.4l-.5 3.5h-2.9v8.4A12 12 0 0 0 24 12z"/></svg>',
    ];
    $out = '<div class="social-login">';
    foreach ($providers as $key => $label) {
        $out .= '<a class="btn btn-ghost btn-block social-btn" href="' . e(url('oauth.php?provider=' . $key)) . '">' . $logos[$key] . e("$verb with $label") . '</a>';
    }
    return $out . '<div class="or-sep"><span>or</span></div></div>';
}

/* ------------------------------------------------------- notifications */

/** Email the owner when an admin approves or rejects an ad. */
function notify_listing_status(int $listingId, string $newStatus, string $reason = ''): void
{
    if (!in_array($newStatus, ['active', 'rejected'], true)) {
        return;
    }
    $l = q_one('SELECT l.id, l.title, u.name, u.email FROM listings l JOIN users u ON u.id = l.user_id WHERE l.id = ?', [$listingId]);
    if (!$l) {
        return;
    }
    if ($newStatus === 'active') {
        send_template('ad_approved', $l['email'], ['name' => $l['name'], 'ad_title' => $l['title'], 'action_url' => abs_url(listing_url($l))]);
    } else {
        send_template('ad_rejected', $l['email'], ['name' => $l['name'], 'ad_title' => $l['title'],
            'reason' => $reason ?: 'It does not follow our posting rules.', 'action_url' => full_url('dashboard/edit.php?id=' . $l['id'])]);
    }
}

/* -------------------------------------------------------------- messaging */

/**
 * Sends a chat message about a listing. Creates the conversation when needed.
 * Returns the conversation id, or an error message string.
 */
function post_message(int $listingId, array $sender, string $body, ?array $conv = null, ?array $image = null): int|string
{
    $body = trim($body);
    if (mb_strlen($body) < 2 && !$image) {
        return __('Please write a message.');
    }
    $listing = q_one('SELECT id, title, user_id FROM listings WHERE id = ?', [$listingId]);
    if (!$listing) {
        return __('This ad no longer exists.');
    }
    $senderId = (int) $sender['id'];
    if (!$conv) {
        $conv = q_one('SELECT * FROM conversations WHERE listing_id = ? AND buyer_id = ?', [$listingId, $senderId]);
    }
    $recipientId = $conv
        ? ((int) $conv['buyer_id'] === $senderId ? (int) $conv['seller_id'] : (int) $conv['buyer_id'])
        : (int) $listing['user_id'];
    if ($recipientId === $senderId) {
        return __('You cannot message yourself.');
    }
    if (is_blocked($senderId, $recipientId)) {
        return __('You have blocked this member. Unblock them to send messages.');
    }
    if (is_blocked($recipientId, $senderId)) {
        return __('This member is not accepting messages from you.');
    }
    if ($sender['role'] !== 'admin' && ($bad = find_banned_word($body)) !== null) {
        return __('Your message contains a word or phrase that is not allowed (“{word}”).', ['word' => $bad]);
    }
    $convId = $conv ? (int) $conv['id'] : db_insert('conversations', [
        'listing_id' => $listingId, 'buyer_id' => $senderId, 'seller_id' => $recipientId,
        'buyer_unread' => 0, 'seller_unread' => 0, 'last_message_at' => now(), 'created_at' => now(),
    ]);
    $isBuyer = !$conv || (int) $conv['buyer_id'] === $senderId;
    db_insert('messages', ['conversation_id' => $convId, 'sender_id' => $senderId, 'body' => mb_substr($body, 0, 4000),
        'image' => $image['path'] ?? '', 'thumb' => $image['thumb'] ?? '', 'offer_id' => 0, 'created_at' => now()]);
    $col = $isBuyer ? 'seller_unread' : 'buyer_unread';
    q("UPDATE conversations SET $col = $col + 1, last_message_at = ? WHERE id = ?", [now(), $convId]);
    $recipient = q_one('SELECT name, email FROM users WHERE id = ?', [$recipientId]);
    if ($recipient) {
        send_template('new_message', $recipient['email'], [
            'name' => $recipient['name'], 'sender_name' => $sender['name'], 'ad_title' => $listing['title'],
            'message' => $body !== '' ? mb_substr($body, 0, 500) : '[' . __('photo') . ']', 'action_url' => full_url('dashboard/messages.php?c=' . $convId),
        ]);
    }
    return $convId;
}
