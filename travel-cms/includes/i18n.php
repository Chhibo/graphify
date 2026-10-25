<?php
/**
 * Multi-language support.
 *
 * - Languages are stored in the "languages" setting (managed in Admin > Languages).
 * - Interface texts use __('English text'). Translations come from the language
 *   pack in /lang/{code}.php, and can be overridden in the admin panel.
 * - Content (trips, destinations, posts, pages, FAQs, categories, settings texts)
 *   is translated in the "translations" table. Missing translations fall back to
 *   the default language.
 */
if (!defined('APP_ROOT')) {
    exit('No direct access');
}

/** All configured languages, keyed by code: ['code', 'name', 'dir', 'enabled']. */
function languages(): array
{
    static $langs = null;
    if ($langs === null) {
        $raw = json_decode((string) (settings_all()['languages'] ?? ''), true);
        $langs = [];
        foreach (is_array($raw) ? $raw : [] as $l) {
            if (!empty($l['code']) && preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $l['code'])) {
                $langs[$l['code']] = [
                    'code' => $l['code'],
                    'name' => (string) ($l['name'] ?? $l['code']),
                    'dir' => ($l['dir'] ?? 'ltr') === 'rtl' ? 'rtl' : 'ltr',
                    'enabled' => !empty($l['enabled']),
                ];
            }
        }
        if (!$langs) {
            $langs['en'] = ['code' => 'en', 'name' => 'English', 'dir' => 'ltr', 'enabled' => true];
        }
    }
    return $langs;
}

function default_lang(): string
{
    $code = (string) (settings_all()['default_language'] ?? '');
    $langs = languages();
    return isset($langs[$code]) ? $code : (string) array_key_first($langs);
}

function enabled_languages(): array
{
    $list = array_filter(languages(), fn ($l) => $l['enabled'] || $l['code'] === default_lang());
    return $list ?: [default_lang() => languages()[default_lang()]];
}

function is_multilingual(): bool
{
    return count(enabled_languages()) > 1;
}

/**
 * The language of the current request. Public pages use ?lang=xx (remembered in a
 * cookie); the admin panel always uses the default language.
 */
function current_lang(): string
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }
    $enabled = enabled_languages();
    if (defined('ADMIN_AREA')) {
        return $lang = default_lang();
    }
    $want = (string) ($_GET['lang'] ?? '');
    if ($want !== '' && isset($enabled[$want])) {
        if (($_COOKIE['tcms_lang'] ?? '') !== $want && !headers_sent()) {
            setcookie('tcms_lang', $want, ['expires' => time() + 31536000, 'path' => (parse_url(base_url(), PHP_URL_PATH) ?: '/'), 'samesite' => 'Lax']);
        }
        return $lang = $want;
    }
    $cookie = (string) ($_COOKIE['tcms_lang'] ?? '');
    if ($cookie !== '' && isset($enabled[$cookie])) {
        return $lang = $cookie;
    }
    if ((settings_all()['detect_browser_language'] ?? '0') === '1') {
        foreach (explode(',', (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $part) {
            $code = strtolower(substr(trim($part), 0, 2));
            if (isset($enabled[$code])) {
                return $lang = $code;
            }
        }
    }
    return $lang = default_lang();
}

function lang_dir(?string $lang = null): string
{
    $lang = $lang ?: current_lang();
    return languages()[$lang]['dir'] ?? 'ltr';
}

function is_rtl(?string $lang = null): bool
{
    return lang_dir($lang) === 'rtl';
}

/** Add ?lang=xx to a URL (removes it for the default language). */
function lang_url(string $link, string $lang): string
{
    $parts = parse_url($link);
    parse_str($parts['query'] ?? '', $q);
    unset($q['lang']);
    if ($lang !== default_lang()) {
        $q['lang'] = $lang;
    }
    $base = strtok($link, '?#');
    return $base . ($q ? '?' . http_build_query($q) : '');
}

/** Link that switches the visitor to $lang (always carries ?lang= so the choice is remembered). */
function lang_switch_url(string $link, string $lang): string
{
    $parts = parse_url($link);
    parse_str($parts['query'] ?? '', $q);
    $q['lang'] = $lang;
    return strtok($link, '?#') . '?' . http_build_query($q);
}

/* ---------------- Interface texts ---------------- */

/** Language pack shipped in /lang/{code}.php: ['strings' => [...], 'templates' => [...]]. */
function language_pack_data(string $lang): array
{
    static $packs = [];
    if (!isset($packs[$lang])) {
        $file = APP_ROOT . '/lang/' . basename($lang) . '.php';
        $data = (preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $lang) && is_file($file)) ? include $file : [];
        $data = is_array($data) ? $data : [];
        $packs[$lang] = ['strings' => $data['strings'] ?? $data, 'templates' => $data['templates'] ?? []];
    }
    return $packs[$lang];
}

/** Built-in translations of the interface (source English text => translation). */
function language_pack(string $lang): array
{
    return language_pack_data($lang)['strings'];
}

/** Default email / WhatsApp texts of a pack: ['email' => [key => [subject, body]], 'wa' => [key => text]]. */
function language_pack_templates(string $lang): array
{
    return language_pack_data($lang)['templates'];
}

/** Interface texts edited in Admin > Languages, keyed by md5(source). */
function ui_overrides(string $lang): array
{
    static $cache = [];
    if (!isset($cache[$lang])) {
        $cache[$lang] = [];
        try {
            foreach (db_all('SELECT field, value FROM ' . tbl('translations') . " WHERE lang = ? AND type = 'ui'", [$lang]) as $r) {
                if ((string) $r['value'] !== '') {
                    $cache[$lang][$r['field']] = $r['value'];
                }
            }
        } catch (PDOException $ex) {
            // Table not created yet (during upgrade).
        }
    }
    return $cache[$lang];
}

/** Translate an interface text. */
function __(string $text, ?string $lang = null): string
{
    if ($lang === null && defined('ADMIN_AREA')) {
        return $text; // the admin panel is in English
    }
    $lang = $lang ?: current_lang();
    $over = ui_overrides($lang);
    $key = md5($text);
    if (isset($over[$key])) {
        return $over[$key];
    }
    $pack = language_pack($lang);
    return (isset($pack[$text]) && $pack[$text] !== '') ? $pack[$text] : $text;
}

/** Translate with sprintf placeholders, e.g. __f('%d results', $n). */
function __f(string $text, ...$args): string
{
    return vsprintf(__($text), $args);
}

/* ---------------- Content translations ---------------- */

/** Translatable fields per content type (field => label). */
function translatable_fields(string $type): array
{
    $seo = ['seo_title' => 'SEO title', 'meta_description' => 'Meta description'];
    $map = [
        'tour' => ['title' => 'Title', 'destination' => 'Location label', 'duration' => 'Duration', 'short_description' => 'Short description',
            'description' => 'Full description', 'inclusions' => "What's included (one per line)", 'itinerary' => 'Itinerary (one step per line)'] + $seo,
        'destination' => ['name' => 'Name', 'country' => 'Country', 'short_description' => 'Short description', 'description' => 'Full description'] + $seo,
        'post' => ['title' => 'Title', 'tag' => 'Tag', 'read_time' => 'Read time', 'excerpt' => 'Excerpt', 'content' => 'Content'] + $seo,
        'page' => ['title' => 'Title', 'content' => 'Content'] + $seo,
        'faq' => ['question' => 'Question', 'answer' => 'Answer', 'category' => 'Category'],
        'category' => ['name' => 'Name'],
    ];
    return $map[$type] ?? [];
}

/** Long fields are edited with a textarea. */
function is_long_field(string $field): bool
{
    return in_array($field, ['short_description', 'description', 'inclusions', 'itinerary', 'excerpt', 'content', 'answer', 'meta_description'], true);
}

/** Replace the fields of $rows with their translation in $lang (falls back to the original). */
function localize_rows(array $rows, string $type, ?string $lang = null): array
{
    $lang = $lang ?: current_lang();
    if (!$rows || $lang === default_lang()) {
        return $rows;
    }
    $ids = array_values(array_unique(array_filter(array_map(fn ($r) => (int) ($r['id'] ?? 0), $rows))));
    if (!$ids) {
        return $rows;
    }
    $map = [];
    $sql = 'SELECT item_id, field, value FROM ' . tbl('translations') . ' WHERE lang = ? AND type = ? AND item_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
    foreach (db_all($sql, array_merge([$lang, $type], $ids)) as $t) {
        if (trim((string) $t['value']) !== '') {
            $map[(int) $t['item_id']][$t['field']] = $t['value'];
        }
    }
    foreach ($rows as &$row) {
        foreach ($map[(int) ($row['id'] ?? 0)] ?? [] as $field => $value) {
            if (array_key_exists($field, $row)) {
                $row[$field] = $value;
            }
        }
    }
    return $rows;
}

function localize_row(?array $row, string $type, ?string $lang = null): ?array
{
    return $row ? localize_rows([$row], $type, $lang)[0] : null;
}

/** All translations of an item: [lang => [field => value]]. */
function get_translations(string $type, int $id): array
{
    $out = [];
    foreach (db_all('SELECT lang, field, value FROM ' . tbl('translations') . ' WHERE type = ? AND item_id = ?', [$type, $id]) as $r) {
        $out[$r['lang']][$r['field']] = $r['value'];
    }
    return $out;
}

/** Save translations from a form: $data[lang][field] = value. Empty values are removed. */
function save_translations(string $type, int $id, array $data, ?array $fields = null): void
{
    $fields = $fields ?? array_keys(translatable_fields($type));
    foreach ($data as $lang => $values) {
        if (!isset(languages()[$lang]) || !is_array($values)) {
            continue;
        }
        foreach ($fields as $field) {
            if (!array_key_exists($field, $values)) {
                continue;
            }
            $value = trim((string) $values[$field]);
            if ($value === '') {
                db_exec('DELETE FROM ' . tbl('translations') . ' WHERE lang = ? AND type = ? AND item_id = ? AND field = ?', [$lang, $type, $id, $field]);
            } else {
                db_exec('INSERT INTO ' . tbl('translations') . ' (lang, type, item_id, field, value) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$lang, $type, $id, $field, $value]);
            }
        }
    }
}

function delete_translations(string $type, int $id): void
{
    db_exec('DELETE FROM ' . tbl('translations') . ' WHERE type = ? AND item_id = ?', [$type, $id]);
}

/** Translated settings texts (hero, section titles, footer...) for a language. */
function setting_translations(string $lang): array
{
    static $cache = [];
    if (!isset($cache[$lang])) {
        $cache[$lang] = [];
        try {
            foreach (db_all('SELECT field, value FROM ' . tbl('translations') . " WHERE lang = ? AND type = 'setting'", [$lang]) as $r) {
                if (trim((string) $r['value']) !== '') {
                    $cache[$lang][$r['field']] = $r['value'];
                }
            }
        } catch (PDOException $ex) {
            // Table not created yet (during upgrade).
        }
    }
    return $cache[$lang];
}

/* ---------------- Dates ---------------- */

/** Format a timestamp in the current language when the PHP "intl" extension is available. */
function localized_date(int $ts, string $format, ?string $lang = null): string
{
    $lang = $lang ?: current_lang();
    if (strpos($lang, 'en') === 0 || !class_exists('IntlDateFormatter')) {
        return date($format, $ts);
    }
    $patterns = [
        'M j, Y' => 'd MMM y', 'D, M j, Y' => 'EEEE d MMMM y', 'M j' => 'd MMM', 'F Y' => 'MMMM y',
        'j F Y' => 'd MMMM y', 'M j, Y H:i' => 'd MMM y HH:mm',
    ];
    if (!isset($patterns[$format])) {
        return date($format, $ts);
    }
    $fmt = new IntlDateFormatter($lang, IntlDateFormatter::NONE, IntlDateFormatter::NONE, date_default_timezone_get(), IntlDateFormatter::GREGORIAN, $patterns[$format]);
    $out = $fmt->format($ts);
    return $out === false ? date($format, $ts) : $out;
}
