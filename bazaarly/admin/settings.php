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
        ['notify_new_message', 'Email users when they get a new message', 'toggle'],
        ['currency_symbol', 'Currency symbol', 'text'],
        ['currency_position', 'Currency position', 'select', '', ['before' => 'Before amount ($100)', 'after' => 'After amount (100 $)']],
        ['currency_decimals', 'Decimals', 'select', '', ['0' => '0 (100)', '2' => '2 (100.00)']],
        ['listings_per_page', 'Ads per page', 'number'],
        ['listing_expiry_days', 'Ads expire after (days)', 'number', '0 = never expire.'],
        ['max_images', 'Max photos per ad', 'number'],
        ['max_image_mb', 'Max photo size (MB)', 'number', 'Server limit: ' . ini_get('upload_max_filesize') . ' per file, ' . ini_get('post_max_size') . ' per request.'],
        ['safety_tips', 'Safety tips (one per line)', 'textarea'],
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
        ['maintenance_mode', 'Maintenance mode (only admins can use the site)', 'toggle'],
        ['maintenance_message', 'Maintenance message', 'textarea'],
    ]],
];

if (is_post()) {
    $values = [];
    foreach ($groups as [, , $fields]) {
        foreach ($fields as $f) {
            [$key, , $type] = $f;
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
    save_settings($values);
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
          <?php if ($type === 'toggle'): ?>
            <label class="switch setting-toggle"><input type="checkbox" name="<?= $key ?>" value="1" <?= $val === '1' ? 'checked' : '' ?>><span class="switch-ui"></span><span><?= e($flabel) ?></span></label>
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
