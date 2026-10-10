<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$tzs = timezone_identifiers_list();
$groups = [
    'general' => ['General', 'globe', [
        ['site_name', 'Website name', 'text'],
        ['site_tagline', 'Tagline', 'text', 'Shown in the browser title on the home page.'],
        ['site_description', 'Meta description', 'textarea', 'Used by search engines and social networks.'],
        ['contact_email', 'Contact email', 'email', 'Receives contact-form messages.'],
        ['contact_phone', 'Contact phone', 'text'],
        ['contact_address', 'Address', 'text'],
        ['timezone', 'Timezone', 'select', '', array_combine($tzs, $tzs)],
        ['date_format', 'Date format', 'select', '', ['M j, Y' => date('M j, Y'), 'd/m/Y' => date('d/m/Y'), 'm/d/Y' => date('m/d/Y'), 'Y-m-d' => date('Y-m-d'), 'j F Y' => date('j F Y')]],
    ]],
    'appearance' => ['Appearance', 'image', [
        ['logo', 'Logo', 'image', 'PNG/SVG-style wide logo works best (about 180×40).'],
        ['favicon', 'Favicon', 'image', 'Square image, at least 64×64.'],
        ['primary_color', 'Brand colour', 'color'],
        ['accent_color', 'Accent colour (featured badges)', 'color'],
        ['default_theme', 'Default theme', 'select', 'Visitors can still switch with the moon/sun button.', ['auto' => 'Follow device setting', 'light' => 'Light', 'dark' => 'Dark']],
        ['hero_title', 'Home page headline', 'text'],
        ['hero_subtitle', 'Home page sub-headline', 'textarea'],
        ['footer_about', 'Footer text', 'textarea'],
        ['copyright_text', 'Copyright line', 'text', 'Use {year} and {site} as placeholders.'],
    ]],
    'listings' => ['Ads & users', 'package', [
        ['allow_registration', 'Allow new users to sign up', 'toggle'],
        ['require_approval', 'Ads need admin approval before going live', 'toggle'],
        ['enable_reviews', 'Allow seller reviews', 'toggle'],
        ['show_phone_to_guests', 'Show phone numbers to visitors who are not signed in', 'toggle'],
        ['enable_map', 'Show a map on ad pages', 'toggle'],
        ['enable_saved_searches', 'Let members save searches and get email alerts', 'toggle'],
        ['currency_symbol', 'Currency symbol', 'text'],
        ['currency_code', 'Currency code (ISO, for Google)', 'text', 'e.g. USD, EUR, BDT, INR, AED'],
        ['currency_position', 'Currency position', 'select', '', ['before' => 'Before amount ($100)', 'after' => 'After amount (100 $)']],
        ['currency_decimals', 'Decimals', 'select', '', ['0' => '0 (100)', '2' => '2 (100.00)']],
        ['listings_per_page', 'Ads per page', 'number'],
        ['listing_expiry_days', 'Ads expire after (days)', 'number', '0 = never expire.'],
        ['max_images', 'Max photos per ad', 'number'],
        ['max_ads_per_day', 'Max new ads per member per day', 'number', 'Stops spam floods. 0 = unlimited. Admins are never limited.'],
        ['max_image_mb', 'Max photo size (MB)', 'number', 'Server limit: ' . ini_get('upload_max_filesize') . ' per file, ' . ini_get('post_max_size') . ' per request.'],
        ['safety_tips', 'Safety tips (one per line)', 'textarea'],
    ]],
    'accounts' => ['Sign-up & login', 'user', [
        ['require_email_verification', 'Members must confirm their email before posting ads or sending messages', 'toggle'],
        ['_note_verify', '', 'info', 'Make sure emails are delivered first (Admin → Emails & SMTP → Send test). Existing members are already treated as confirmed.'],
        ['social_login', 'Enable social login buttons', 'toggle'],
        ['google_login', 'Sign in with Google', 'toggle'],
        ['google_client_id', 'Google Client ID', 'text'],
        ['google_client_secret', 'Google Client secret', 'secret'],
        ['_note_google', '', 'info', 'Create credentials at console.cloud.google.com → APIs & Services → Credentials → OAuth client ID (Web application). Authorised redirect URI: ' . full_url('oauth.php?provider=google')],
        ['facebook_login', 'Sign in with Facebook', 'toggle'],
        ['facebook_app_id', 'Facebook App ID', 'text'],
        ['facebook_app_secret', 'Facebook App secret', 'secret'],
        ['_note_facebook', '', 'info', 'Create an app at developers.facebook.com → Facebook Login. Valid OAuth redirect URI: ' . full_url('oauth.php?provider=facebook')],
    ]],
    'security' => ['Spam protection', 'shield', [
        ['captcha_provider', 'Captcha', 'select', 'Free keys: google.com/recaptcha (choose v2 “I’m not a robot”) or dash.cloudflare.com → Turnstile.', ['none' => 'Off', 'recaptcha' => 'Google reCAPTCHA v2', 'turnstile' => 'Cloudflare Turnstile']],
        ['captcha_site_key', 'Site key', 'text'],
        ['captcha_secret_key', 'Secret key', 'secret'],
        ['captcha_on_register', 'Use captcha on sign-up', 'toggle'],
        ['captcha_on_post', 'Use captcha when posting an ad', 'toggle'],
        ['captcha_on_contact', 'Use captcha on the contact form', 'toggle'],
        ['captcha_on_forgot', 'Use captcha on “forgot password”', 'toggle'],
        ['captcha_on_login', 'Use captcha on sign-in', 'toggle'],
        ['banned_words', 'Banned words & phrases', 'textarea', 'One per line (or comma separated). Checked in ads, messages, reviews and names. Whole words only, not case-sensitive.'],
        ['banned_words_action', 'When an ad contains a banned word', 'select', '', ['review' => 'Send it to admin review (pending)', 'block' => 'Block it and ask the seller to edit']],
    ]],
    'seo' => ['SEO, URLs & app', 'globe', [
        ['clean_urls', 'Clean URLs (e.g. /ad/12-iphone-15 instead of listing.php?id=12)', 'toggle', 'Works on Apache and LiteSpeed (most hosts). When you switch it on we create the .htaccess file for you and test it first. On Nginx add the rule from README.'],
        ['_htaccess_status', '', 'htaccess', ''],
        ['share_cards', 'Generate share images for ads (WhatsApp, Facebook, X previews)', 'toggle'],
        ['pwa_enabled', 'Installable app (PWA) — “Add to home screen” and offline page', 'toggle'],
        ['pwa_short_name', 'App name on the home screen', 'text', 'Short, up to 12 characters. Defaults to the site name.'],
        ['_note_sitemap', '', 'info', 'Sitemap for Google Search Console: ' . abs_url(clean_urls() ? url('sitemap.xml') : url('sitemap.php'))],
    ]],
    'social' => ['Social', 'share', [
        ['social_facebook', 'Facebook URL', 'url'],
        ['social_twitter', 'X / Twitter URL', 'url'],
        ['social_instagram', 'Instagram URL', 'url'],
        ['social_youtube', 'YouTube URL', 'url'],
        ['social_linkedin', 'LinkedIn URL', 'url'],
    ]],
    'ads' => ['Ad spaces', 'megaphone', [
        ['ad_header', 'Home page banner', 'code', 'Any HTML, e.g. an AdSense snippet or <a><img></a> banner.'],
        ['ad_sidebar', 'Sidebar (browse & ad pages)', 'code'],
        ['ad_listing', 'Below ad description', 'code'],
        ['ad_footer', 'Above footer (all pages)', 'code'],
        ['custom_head_code', 'Custom <head> code', 'code', 'Analytics, verification tags, custom CSS in <style>…'],
    ]],
    'advanced' => ['Advanced', 'shield', [
        ['mail_from', 'Send emails from', 'email', 'Leave blank to use no-reply@yourdomain.'],
        ['cookie_notice', 'Show cookie notice', 'toggle'],
        ['cookie_text', 'Cookie notice text', 'text'],
        ['pseudo_cron', 'Run scheduled jobs automatically during visits', 'toggle', 'Expires old ads and sends saved-search alerts. For exact timing set up a real cron job instead (see below).'],
        ['_note_cron', '', 'info', 'Cron job (every 15 minutes): php ' . APP_ROOT . '/cron.php — or URL: ' . full_url('cron.php?key=' . setting('cron_key'))],
        ['activity_log_days', 'Keep the activity log for (days)', 'number', '0 = keep forever.'],
        ['maintenance_mode', 'Maintenance mode (only admins can use the site)', 'toggle'],
        ['maintenance_message', 'Maintenance message', 'textarea'],
    ]],
];

// AJAX: create / repair the .htaccess files (used by the Clean URLs switch and the repair button)
if (is_post() && input('action') === 'htaccess') {
    $report = ensure_htaccess(true);
    log_activity('settings.htaccess', 'Repaired .htaccess files: ' . json_encode($report));
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => !in_array('failed', $report, true),
        'report' => $report,
        'server' => server_software(),
        'apache' => server_uses_htaccess(),
    ]);
    exit;
}

if (is_post()) {
    $values = [];
    foreach ($groups as [, , $fields]) {
        foreach ($fields as $f) {
            [$key, , $type] = $f;
            if ($type === 'info' || $type === 'htaccess') {
                continue;
            }
            if ($type === 'secret') {
                if ((string) ($_POST[$key] ?? '') !== '') {
                    $values[$key] = trim((string) $_POST[$key]);
                } elseif (!empty($_POST['clear_' . $key])) {
                    $values[$key] = '';
                }
                continue;
            }
            if ($type === 'toggle') {
                $values[$key] = empty($_POST[$key]) ? '0' : '1';
            } elseif ($type === 'image') {
                $file = normalize_files($_FILES[$key] ?? null);
                if (!empty($_POST['remove_' . $key])) {
                    delete_upload(setting($key));
                    $values[$key] = '';
                }
                if ($file) {
                    $res = store_image($file[0], 'site', 600, 128);
                    if (is_string($res)) {
                        flash('error', $res);
                    } else {
                        delete_upload(setting($key));
                        if ($res['thumb'] !== $res['path']) {
                            delete_upload($key === 'favicon' ? $res['path'] : $res['thumb']);
                        }
                        $values[$key] = $key === 'favicon' ? $res['thumb'] : $res['path'];
                    }
                }
            } elseif (array_key_exists($key, $_POST)) {
                $v = trim((string) $_POST[$key]);
                if ($type === 'number') $v = (string) max(0, (int) $v);
                if ($type === 'color') $v = valid_color($v, setting($key));
                if ($type === 'select' && !array_key_exists($v, $f[4])) $v = setting($key);
                $values[$key] = $v;
            }
        }
    }
    if (isset($values['listings_per_page'])) $values['listings_per_page'] = (string) max(1, min(100, (int) $values['listings_per_page']));
    if (isset($values['max_images'])) $values['max_images'] = (string) max(1, min(30, (int) $values['max_images']));
    if (isset($values['max_image_mb'])) $values['max_image_mb'] = (string) max(1, (int) $values['max_image_mb']);
    if (isset($values['site_name']) && $values['site_name'] === '') $values['site_name'] = 'Bazaarly';
    if (setting('cron_key') === '') {
        $values['cron_key'] = bin2hex(random_bytes(12));
    }
    $changed = array_keys(array_filter($values, fn($v, $k) => (string) setting($k) !== (string) $v && !str_contains($k, 'secret') && !str_contains($k, 'password'), ARRAY_FILTER_USE_BOTH));
    save_settings($values);
    log_activity('settings.update', $changed ? 'Changed: ' . implode(', ', array_slice($changed, 0, 15)) : 'No changes');
    flash('success', 'Settings saved.');
    redirect('admin/settings.php' . (input('tab') ? '#' . preg_replace('/[^a-z]/', '', input('tab')) : ''));
}

$adminActive = 'settings';
$pageTitle = 'Settings';
require APP_ROOT . '/includes/admin-top.php';
?>
<form method="post" enctype="multipart/form-data" class="settings-layout" data-tabs>
  <?= csrf_field() ?>
  <input type="hidden" name="tab" value="" data-tab-input>
  <nav class="settings-nav">
    <?php $first = true; foreach ($groups as $gk => [$label, $ic]): ?>
      <a href="#<?= $gk ?>" data-tab="<?= $gk ?>" class="<?= $first ? 'on' : '' ?>"><?= icon($ic) ?><?= e($label) ?></a>
    <?php $first = false; endforeach; ?>
  </nav>
  <div class="settings-panels">
    <?php $first = true; foreach ($groups as $gk => [$label, $ic, $fields]): ?>
      <section class="card form-section" id="<?= $gk ?>" data-panel="<?= $gk ?>" <?= $first ? '' : 'hidden' ?>>
        <h2><?= e($label) ?></h2>
        <?php if ($gk === 'advanced'): ?><span id="maintenance"></span><?php endif; ?>
        <?php foreach ($fields as $f): [$key, $flabel, $type] = $f; $help = $f[3] ?? ''; $val = setting($key); ?>
          <?php if ($type === 'htaccess'): ?>
            <div class="htaccess-box" data-htaccess-box>
              <div class="htaccess-head"><strong><?= icon('file') ?>.htaccess files</strong><span class="muted small">Server: <?= e(server_software()) ?></span></div>
              <ul class="htaccess-list">
                <?php foreach (array_keys(htaccess_targets()) as $rel): $exists = is_file(APP_ROOT . '/' . $rel); ?>
                  <li data-ht-file="<?= e($rel) ?>"><?= icon($exists ? 'check-circle' : 'alert', $exists ? 'text-success' : 'text-warning') ?><code><?= e($rel) ?></code><span class="muted small"><?= $exists ? 'present' : 'missing' ?></span></li>
                <?php endforeach; ?>
              </ul>
              <p class="ht-result" data-ht-result hidden></p>
              <button class="btn btn-ghost btn-sm" type="button" data-fix-htaccess data-endpoint="<?= e(url('admin/settings.php')) ?>" data-test="<?= e(url('rewrite-test')) ?>"><?= icon('wrench') ?>Create / repair .htaccess files</button>
              <small class="help block">These hidden files switch on URL rewriting and keep the <code>data/</code> and <code>uploads/</code> folders safe. Use this button if you moved the site to another folder or domain.</small>
            </div>
          <?php elseif ($type === 'info'): ?>
            <p class="setting-note"><?= icon('info') ?><span><?= e($help) ?></span></p>
          <?php elseif ($type === 'secret'): ?>
            <div class="field">
              <label for="s-<?= $key ?>"><?= e($flabel) ?></label>
              <input id="s-<?= $key ?>" type="password" name="<?= $key ?>" value="" autocomplete="new-password" placeholder="<?= $val !== '' ? '•••••••• saved — leave blank to keep' : 'Not set' ?>">
              <?php if ($val !== ''): ?><label class="check small"><input type="checkbox" name="clear_<?= $key ?>" value="1"><span>Remove saved value</span></label><?php endif; ?>
            </div>
          <?php elseif ($type === 'toggle'): ?>
            <label class="switch setting-toggle"><input type="checkbox" name="<?= $key ?>" value="1" <?= $val === '1' ? 'checked' : '' ?><?= $key === 'clean_urls' ? ' data-rewrite-test="' . e(url('rewrite-test')) . '"' : '' ?>><span class="switch-ui"></span><span><?= e($flabel) ?><?php if ($help): ?><small class="help block"><?= e($help) ?></small><?php endif; ?></span></label>
          <?php else: ?>
            <div class="field">
              <label for="s-<?= $key ?>"><?= e($flabel) ?></label>
              <?php if ($type === 'textarea'): ?>
                <textarea id="s-<?= $key ?>" name="<?= $key ?>" rows="3"><?= e($val) ?></textarea>
              <?php elseif ($type === 'code'): ?>
                <textarea id="s-<?= $key ?>" name="<?= $key ?>" rows="4" class="mono" spellcheck="false"><?= e($val) ?></textarea>
              <?php elseif ($type === 'select'): ?>
                <select id="s-<?= $key ?>" name="<?= $key ?>"><?php foreach ($f[4] as $ov => $ol): ?><option value="<?= e((string) $ov) ?>" <?= (string) $ov === $val ? 'selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?></select>
              <?php elseif ($type === 'color'): ?>
                <div class="color-field"><input type="color" id="s-<?= $key ?>" name="<?= $key ?>" value="<?= e(valid_color($val, '#0d9488')) ?>" data-color-sync><code><?= e($val) ?></code></div>
              <?php elseif ($type === 'image'): ?>
                <div class="image-setting">
                  <?php if ($val): ?><img src="<?= e(upload_url($val)) ?>" alt=""><?php else: ?><span class="muted small">No image — the default is used.</span><?php endif; ?>
                  <input type="file" id="s-<?= $key ?>" name="<?= $key ?>" accept="image/png,image/jpeg,image/webp,image/gif">
                  <?php if ($val): ?><label class="check small"><input type="checkbox" name="remove_<?= $key ?>" value="1"><span>Remove</span></label><?php endif; ?>
                </div>
              <?php else: ?>
                <input id="s-<?= $key ?>" type="<?= $type === 'number' ? 'number' : ($type === 'email' ? 'email' : ($type === 'url' ? 'url' : 'text')) ?>" name="<?= $key ?>" value="<?= e($val) ?>" <?= $type === 'number' ? 'min="0"' : '' ?>>
              <?php endif; ?>
              <?php if ($help): ?><small class="help"><?= e($help) ?></small><?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </section>
    <?php $first = false; endforeach; ?>
    <div class="settings-save"><button class="btn btn-primary btn-lg" type="submit"><?= icon('check') ?>Save settings</button></div>
  </div>
</form>
<?php require APP_ROOT . '/includes/admin-bottom.php';
