<?php
/**
 * Multi-language support.
 *
 *   __('Sign in')                  → translated plain text (escape it yourself)
 *   t('Sign in')                   → translated and HTML-escaped, ready to echo
 *   __('{n} ads', ['n' => 5])      → placeholders are replaced after translating
 *
 * Built-in translations live in includes/lang/{code}.php; anything an admin edits in
 * Admin → Languages is stored in the database and wins over the file.
 */
declare(strict_types=1);

function languages_all(bool $refresh = false): array
{
    static $all = null;
    if ($all === null || $refresh) {
        $all = [];
        try {
            foreach (q_all('SELECT * FROM languages ORDER BY sort_order, name') as $l) {
                $all[$l['code']] = $l;
            }
        } catch (Throwable) {
        }
        if (!$all) {
            $all = ['en' => ['code' => 'en', 'name' => 'English', 'direction' => 'ltr', 'enabled' => 1, 'sort_order' => 0]];
        }
    }
    return $all;
}

function languages_enabled(): array
{
    return array_filter(languages_all(), fn($l) => (int) $l['enabled'] === 1);
}

function default_language(): string
{
    $d = setting('default_language', 'en');
    return isset(languages_enabled()[$d]) ? $d : (array_key_first(languages_enabled()) ?? 'en');
}

/** Works out the visitor's language once per request (URL ?lang=, session, cookie, profile, browser). */
function current_lang(?string $set = null): string
{
    static $lang = null;
    if ($set !== null) {
        $lang = $set;
    }
    if ($lang !== null) {
        return $lang;
    }
    $enabled = languages_enabled();
    $pick = null;
    $wanted = is_string($_GET['lang'] ?? null) ? strtolower($_GET['lang']) : '';
    if ($wanted !== '' && isset($enabled[$wanted])) {
        $pick = $wanted;
        remember_language($pick);
    }
    foreach ([$_SESSION['lang'] ?? null, $_COOKIE['bzr_lang'] ?? null, current_user()['lang'] ?? null] as $cand) {
        if ($pick === null && is_string($cand) && isset($enabled[$cand])) {
            $pick = $cand;
        }
    }
    if ($pick === null && setting('detect_browser_language', '1') === '1') {
        foreach (explode(',', (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $part) {
            $code = strtolower(substr(trim($part), 0, 2));
            if (isset($enabled[$code])) {
                $pick = $code;
                break;
            }
        }
    }
    return $lang = $pick ?? default_language();
}

function lang_dir(): string
{
    return (languages_all()[current_lang()]['direction'] ?? 'ltr') === 'rtl' ? 'rtl' : 'ltr';
}

/** Built-in + admin-edited translations for a language. */
function translations_for(string $code, bool $refresh = false): array
{
    static $cache = [];
    if (!isset($cache[$code]) || $refresh) {
        $dict = [];
        $file = APP_ROOT . '/includes/lang/' . preg_replace('/[^a-z_-]/', '', $code) . '.php';
        if (is_file($file)) {
            $dict = (array) require $file;
        }
        try {
            foreach (q_all('SELECT msgid, msgstr FROM translations WHERE lang = ?', [$code]) as $r) {
                if ($r['msgstr'] !== '') {
                    $dict[$r['msgid']] = $r['msgstr'];
                }
            }
        } catch (Throwable) {
        }
        $cache[$code] = $dict;
    }
    return $cache[$code];
}

function __(string $text, array $vars = []): string
{
    // English can be customised too (e.g. rename "Post ad" to "Sell"), so every language is looked up
    $dict = translations_for(current_lang());
    if (isset($dict[$text]) && $dict[$text] !== '') {
        $text = $dict[$text];
    }
    if ($vars) {
        $map = [];
        foreach ($vars as $k => $v) {
            $map['{' . $k . '}'] = (string) $v;
        }
        $text = strtr($text, $map);
    }
    return $text;
}

/** Translated + escaped. */
function t(string $text, array $vars = []): string
{
    return e(__($text, $vars));
}

/** Plural helper: n_('{n} ad', '{n} ads', $n). */
function n_(string $one, string $many, int $n): string
{
    return __($n === 1 ? $one : $many, ['n' => number_format($n)]);
}

/** URL of the current page in another language. */
/** Keeps a visitor's language choice in the session, a cookie and (when signed in) their account. */
function remember_language(string $code): void
{
    $_SESSION['lang'] = $code;
    if (!headers_sent()) {
        setcookie('bzr_lang', $code, ['expires' => time() + 31536000, 'path' => BASE_PATH === '' ? '/' : BASE_PATH . '/', 'samesite' => 'Lax', 'secure' => is_https()]);
    }
    if (user_id()) {
        q('UPDATE users SET lang = ? WHERE id = ?', [$code, user_id()]);
    }
}

function lang_url(string $code): string
{
    $q = $_GET;
    $q['lang'] = $code;
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    return $path . '?' . http_build_query($q);
}

function language_switcher(string $class = ''): string
{
    $langs = languages_enabled();
    if (count($langs) < 2) {
        return '';
    }
    $cur = current_lang();
    $out = '<details class="dropdown lang-menu ' . e($class) . '"><summary class="icon-btn" aria-label="' . t('Language') . '">' . icon('languages')
        . '<span class="lang-code">' . e(strtoupper($cur)) . '</span></summary><div class="dropdown-panel">';
    foreach ($langs as $code => $l) {
        $out .= '<a href="' . e(lang_url($code)) . '" hreflang="' . e($code) . '" lang="' . e($code) . '"' . ($code === $cur ? ' class="active"' : '') . '>'
            . e($l['name']) . ($code === $cur ? icon('check', 'push') : '') . '</a>';
    }
    return $out . '</div></details>';
}

/** Default languages for new installs and upgrades. */
function seed_languages(): void
{
    $defaults = [
        ['en', 'English', 'ltr', 1, 0],
        ['bn', 'বাংলা', 'ltr', 1, 1],
        ['ar', 'العربية', 'rtl', 0, 2],
    ];
    foreach ($defaults as [$code, $name, $dir, $on, $order]) {
        if (!q_val('SELECT code FROM languages WHERE code = ?', [$code])) {
            db_insert('languages', ['code' => $code, 'name' => $name, 'direction' => $dir, 'enabled' => $on, 'sort_order' => $order]);
        }
    }
    languages_all(true);
}

/** Every translatable text in the code (found by scanning for __(), t() and n_() calls). */
function translatable_strings(): array
{
    $found = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $path = str_replace('\\', '/', $f->getPathname());
        if (!str_ends_with($path, '.php') || preg_match('~/(admin|install|data|uploads|includes/lang)/~', $path)) {
            continue;
        }
        $src = (string) file_get_contents($path);
        if (preg_match_all('~(?<![\w>$])(?:__|t)\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'~', $src, $m)) {
            foreach ($m[1] as $s) {
                $found[stripslashes($s)] = true;
            }
        }
        if (preg_match_all('~(?<![\w>$])n_\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*,\s*\'((?:[^\'\\\\]|\\\\.)*)\'~', $src, $m)) {
            foreach (array_merge($m[1], $m[2]) as $s) {
                $found[stripslashes($s)] = true;
            }
        }
    }
    // texts the admin typed in settings / categories / email-free content shown to visitors
    foreach (['site_tagline', 'site_description', 'hero_title', 'hero_subtitle', 'footer_about', 'cookie_text', 'maintenance_message'] as $k) {
        if (setting($k) !== '') {
            $found[setting($k)] = true;
        }
    }
    foreach (preg_split('/\r?\n/', setting('safety_tips')) ?: [] as $tip) {
        if (trim($tip) !== '') {
            $found[trim($tip)] = true;
        }
    }
    foreach (preg_split('/\r?\n/', setting('quick_replies')) ?: [] as $qr) {
        if (trim($qr) !== '') {
            $found[trim($qr)] = true;
        }
    }
    foreach (categories_all() as $c) {
        $found[$c['name']] = true;
    }
    foreach (fields_all() as $f) {
        $found[$f['label']] = true;
        foreach (field_options($f) as $o) {
            $found[$o] = true;
        }
    }
    foreach (q_all('SELECT title FROM pages') as $p) {
        $found[$p['title']] = true;
    }
    foreach (array_merge(I18N_EXTRA, JS_STRINGS) as $x) {
        $found[$x] = true;
    }
    ksort($found, SORT_NATURAL | SORT_FLAG_CASE);
    return array_keys($found);
}

/** Texts built from variables (so the scanner can't see them) that still need translating. */
const I18N_EXTRA = [
    'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December',
    'Jan', 'Feb', 'Mar', 'Apr', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
    'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun',
    'Active', 'Pending', 'Rejected', 'Sold', 'Expired', 'Banned', 'Open', 'Resolved', 'Approved', 'Accepted', 'Declined', 'Countered', 'Withdrawn',
    '{n} year ago', '{n} years ago', '{n} month ago', '{n} months ago', '{n} week ago', '{n} weeks ago',
    '{n} day ago', '{n} days ago', '{n} hour ago', '{n} hours ago', '{n} minute ago', '{n} minutes ago',
    'Scam or fraud', 'Wrong category', 'Prohibited item', 'Duplicate ad', 'Offensive content', 'Item already sold', 'Other',
    'Passport', 'National ID card', 'Driving licence', 'Business registration',
    'Fixed price', 'Negotiable', 'Free', 'Ask for price', 'Not applicable', 'New', 'Like new', 'Used', 'Refurbished',
    'Overview', 'My ads', 'Post a new ad', 'Messages', 'Statistics', 'Saved ads', 'Saved searches', 'My shop', 'Get verified', 'Account settings',
];

/** Texts used by assets/js/app.js; sent to the browser as window.BZR.i18n. */
const JS_STRINGS = [
    'Copied!', 'Sending…', 'Something went wrong. Please try again.', 'Seen', 'Sent', 'Remove photo', 'Loading…',
    'New messages', 'Please choose a photo (JPG, PNG, WEBP or GIF).', 'That photo is too large.', 'Show number', 'Saved', 'Save',
];

function js_strings(): array
{
    $out = [];
    foreach (JS_STRINGS as $s) {
        $out[$s] = __($s);
    }
    return $out;
}
