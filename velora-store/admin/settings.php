<?php
require __DIR__ . '/includes/auth.php';
require APP_ROOT . '/includes/whatsapp.php';
require_admin();

/*
 * Every setting shown here: [key, label, type, help, options]
 * Types: text, textarea, number, select, checkbox, image, secret, datetime, heading
 */
$tabs = [
    'general' => ['General', [
        ['', 'Store', 'heading'],
        ['store_name', 'Store name', 'text'],
        ['logo', 'Logo (optional - the store name is shown when empty)', 'image'],
        ['store_tagline', 'Footer text', 'textarea'],
        ['store_email', 'Store email', 'text'],
        ['', 'Currency & shipping', 'heading'],
        ['currency_code', 'Currency code', 'text', 'ISO code used for payments, e.g. USD, EUR, GBP, MAD. PayPal supports a limited list of currencies.'],
        ['currency_symbol', 'Currency symbol', 'text', 'Shown next to prices, e.g. $, €, DH'],
        ['currency_position', 'Symbol position', 'select', '', ['before' => 'Before price ($120)', 'after' => 'After price (120 DH)']],
        ['shipping_fee', 'Delivery fee', 'number', 'Flat fee added to every order. 0 = free delivery.'],
        ['free_shipping_over', 'Free delivery for orders over', 'number', '0 = disabled'],
        ['country_default', 'Default country at checkout', 'text'],
        ['order_prefix', 'Order number prefix', 'text'],
        ['', 'Top announcement bar', 'heading'],
        ['announcement_enabled', 'Show announcement bar', 'checkbox'],
        ['announcement_text', 'Announcement text', 'text'],
        ['announcement_link_text', 'Link text (scrolls to newsletter)', 'text'],
        ['', 'Social links (leave empty to hide)', 'heading'],
        ['social_facebook', 'Facebook URL', 'text'],
        ['social_instagram', 'Instagram URL', 'text'],
        ['social_twitter', 'X / Twitter URL', 'text'],
        ['social_github', 'GitHub URL', 'text'],
    ]],
    'home' => ['Home page', [
        ['', 'Hero', 'heading'],
        ['hero_title', 'Title', 'text'],
        ['hero_text', 'Text', 'textarea'],
        ['hero_button', 'Button text', 'text'],
        ['hero_image', 'Hero image', 'image'],
        ['stat1_value', 'Stat 1 number', 'text'], ['stat1_label', 'Stat 1 label', 'text'],
        ['stat2_value', 'Stat 2 number', 'text'], ['stat2_label', 'Stat 2 label', 'text'],
        ['stat3_value', 'Stat 3 number', 'text'], ['stat3_label', 'Stat 3 label', 'text'],
        ['', 'Flash sale (products ticked “Flash Sale”)', 'heading'],
        ['flash_enabled', 'Show flash sale', 'checkbox'],
        ['flash_badge', 'Badge', 'text'],
        ['flash_title', 'Title', 'text'],
        ['flash_text', 'Text', 'textarea'],
        ['flash_ends_at', 'Sale ends at', 'datetime'],
        ['', 'Wide banner', 'heading'],
        ['banner_enabled', 'Show banner', 'checkbox'],
        ['banner_badge', 'Badge', 'text'],
        ['banner_title', 'Title', 'text'],
        ['banner_text', 'Text', 'textarea'],
        ['banner_button', 'Button text', 'text'],
        ['banner_image', 'Banner image (wide)', 'image'],
        ['', 'Instagram & newsletter', 'heading'],
        ['instagram_handle', 'Instagram handle (empty = hide section)', 'text'],
        ['instagram_url', 'Instagram profile URL', 'text'],
        ['newsletter_title', 'Newsletter title', 'text'],
    ]],
    'payments' => ['Payments', [
        ['', 'Cash on Delivery', 'heading'],
        ['pay_cod_enabled', 'Enable Cash on Delivery', 'checkbox', 'Order details are sent to your WhatsApp number (see the WhatsApp tab).'],
        ['pay_cod_title', 'Title shown at checkout', 'text'],
        ['pay_cod_text', 'Description shown at checkout', 'text'],
        ['', 'PayPal', 'heading'],
        ['pay_paypal_enabled', 'Enable PayPal', 'checkbox'],
        ['pay_paypal_title', 'Title shown at checkout', 'text'],
        ['paypal_mode', 'Mode', 'select', 'Use Sandbox to test with fake money, then switch to Live.', ['sandbox' => 'Sandbox (testing)', 'live' => 'Live (real payments)']],
        ['paypal_client_id', 'Client ID', 'text', 'developer.paypal.com → Apps & Credentials → Create App → copy Client ID and Secret.'],
        ['paypal_secret', 'Secret', 'secret'],
        ['', 'Stripe (cards, Apple Pay, Google Pay)', 'heading'],
        ['pay_stripe_enabled', 'Enable Stripe', 'checkbox'],
        ['pay_stripe_title', 'Title shown at checkout', 'text'],
        ['stripe_secret_key', 'Secret key (sk_live_… or sk_test_…)', 'secret', 'dashboard.stripe.com → Developers → API keys. Use sk_test_… keys to test.'],
    ]],
    'whatsapp' => ['WhatsApp', [
        ['', 'Where orders are sent', 'heading'],
        ['whatsapp_number', 'Your WhatsApp number', 'text', 'With country code, digits only. Example: 212612345678 or 14155550123'],
        ['whatsapp_mode', 'How to send Cash on Delivery orders', 'select', '', [
            'link' => 'Customer sends it (no setup): WhatsApp opens with the order written, customer taps Send',
            'callmebot' => 'Automatic & free with CallMeBot (needs an API key)',
            'cloud' => 'Automatic with WhatsApp Business Cloud API (Meta)',
        ]],
        ['whatsapp_notify_online', 'Also send PayPal / Stripe orders to WhatsApp', 'checkbox'],
        ['whatsapp_float', 'Show floating WhatsApp chat button on the store', 'checkbox'],
        ['', 'CallMeBot (only for the CallMeBot mode)', 'heading'],
        ['callmebot_apikey', 'CallMeBot API key', 'secret', 'Free: add +34 644 66 32 62 to your phone contacts, send it “I allow callmebot to send me messages” on WhatsApp, and you will receive your API key.'],
        ['', 'WhatsApp Cloud API (only for the Cloud API mode)', 'heading'],
        ['wa_cloud_phone_id', 'Phone number ID', 'text', 'developers.facebook.com → your app → WhatsApp → API Setup.'],
        ['wa_cloud_token', 'Permanent access token', 'secret', 'Note: Meta only delivers free-text messages to a number that messaged your business number in the last 24 hours.'],
    ]],
];

$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'general';

if (is_post()) {
    verify_csrf();
    $tab = isset($tabs[$_POST['tab'] ?? '']) ? $_POST['tab'] : 'general';

    if (($_POST['action'] ?? '') === 'test_whatsapp') {
        $text = '✅ Test message from ' . setting('store_name') . ' - your WhatsApp order notifications work!';
        if (setting('whatsapp_mode', 'link') === 'link') {
            header('Location: ' . whatsapp_link($text));
            exit;
        }
        $ok = whatsapp_send_auto($text);
        flash($ok ? 'success' : 'error', $ok ? 'Test message sent. Check your WhatsApp.' : 'Sending failed. Check your number, mode and API key / token (save the settings first).');
        redirect('admin/settings.php?tab=whatsapp');
    }

    $errors = [];
    foreach ($tabs[$tab][1] as $f) {
        [$key, , $type] = $f;
        if ($key === '') {
            continue;
        }
        switch ($type) {
            case 'checkbox':
                set_setting($key, post_flag($key) ? '1' : '0');
                break;
            case 'secret':
                $v = trim((string) ($_POST[$key] ?? ''));
                if ($v !== '') {
                    set_setting($key, $v);
                }
                if (!empty($_POST[$key . '__clear'])) {
                    set_setting($key, '');
                }
                break;
            case 'image':
                try {
                    $img = upload_image($_FILES[$key] ?? null);
                    if ($img) {
                        set_setting($key, $img);
                    } elseif (!empty($_POST[$key . '__remove'])) {
                        set_setting($key, '');
                    }
                } catch (RuntimeException $ex) {
                    $errors[] = $ex->getMessage();
                }
                break;
            case 'select':
                $v = (string) ($_POST[$key] ?? '');
                if (isset($f[4][$v])) {
                    set_setting($key, $v);
                }
                break;
            case 'number':
                set_setting($key, (string) max(0, round((float) ($_POST[$key] ?? 0), 2)));
                break;
            default:
                $v = trim((string) ($_POST[$key] ?? ''));
                if ($key === 'whatsapp_number') {
                    $v = preg_replace('/\D+/', '', $v);
                }
                if ($key === 'currency_code') {
                    $v = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $v), 0, 3)) ?: 'USD';
                }
                set_setting($key, $v);
        }
    }
    foreach ($errors as $err) {
        flash('error', $err);
    }
    settings_all(true);
    if ($tab === 'payments' && !enabled_payment_methods()) {
        flash('error', 'Warning: no payment method is active. Enable at least one (PayPal needs Client ID + Secret, Stripe needs a Secret key).');
    } else {
        flash('success', 'Settings saved.');
    }
    redirect('admin/settings.php?tab=' . $tab);
}

$adminTitle = 'Settings';
include __DIR__ . '/includes/header.php';
?>
<div class="tabs">
  <?php foreach ($tabs as $k => [$label]): ?>
    <button type="button" data-tab="<?= e($k) ?>" class="<?= $tab === $k ? 'active' : '' ?>"><?= e($label) ?></button>
  <?php endforeach; ?>
</div>

<?php foreach ($tabs as $k => [$label, $fields]): ?>
<form method="post" enctype="multipart/form-data" class="card tab-panel" id="<?= e($k) ?>" <?= $tab === $k ? '' : 'hidden' ?>>
  <?= csrf_field() ?>
  <input type="hidden" name="tab" value="<?= e($k) ?>">
  <?php foreach ($fields as $f):
      [$key, $flabel, $type] = $f;
      $help = $f[3] ?? '';
      $val = $key !== '' ? setting($key) : '';
      ?>
    <?php if ($type === 'heading'): ?>
      <h3 class="sub"><?= e($flabel) ?></h3>
    <?php elseif ($type === 'checkbox'): ?>
      <label class="inline"><input type="checkbox" name="<?= e($key) ?>" value="1" <?= $val === '1' ? 'checked' : '' ?>> <?= e($flabel) ?></label>
      <?php if ($help): ?><p class="help"><?= e($help) ?></p><?php endif; ?>
    <?php else: ?>
      <label><?= e($flabel) ?>
        <?php if ($type === 'textarea'): ?>
          <textarea name="<?= e($key) ?>" rows="3"><?= e($val) ?></textarea>
        <?php elseif ($type === 'select'): ?>
          <select name="<?= e($key) ?>"><?php foreach ($f[4] as $ov => $ol): ?><option value="<?= e($ov) ?>" <?= $val === (string) $ov ? 'selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?></select>
        <?php elseif ($type === 'secret'): ?>
          <input type="password" name="<?= e($key) ?>" value="" autocomplete="new-password" placeholder="<?= $val !== '' ? '•••••••• saved - leave empty to keep' : 'not set' ?>">
        <?php elseif ($type === 'image'): ?>
          <?php if ($val !== ''): ?><img class="preview" src="<?= e(img_url($val)) ?>" alt=""><?php endif; ?>
          <input type="file" name="<?= e($key) ?>" accept="image/*">
        <?php elseif ($type === 'datetime'): ?>
          <input type="datetime-local" name="<?= e($key) ?>" value="<?= e($val) ?>">
        <?php elseif ($type === 'number'): ?>
          <input type="number" step="0.01" min="0" name="<?= e($key) ?>" value="<?= e($val) ?>">
        <?php else: ?>
          <input name="<?= e($key) ?>" value="<?= e($val) ?>">
        <?php endif; ?>
      </label>
      <?php if ($type === 'secret' && $val !== ''): ?><label class="inline small"><input type="checkbox" name="<?= e($key) ?>__clear" value="1"> Remove saved value</label><?php endif; ?>
      <?php if ($type === 'image' && $val !== ''): ?><label class="inline small"><input type="checkbox" name="<?= e($key) ?>__remove" value="1"> Remove image</label><?php endif; ?>
      <?php if ($help): ?><p class="help"><?= e($help) ?></p><?php endif; ?>
    <?php endif; ?>
  <?php endforeach; ?>

  <?php if ($k === 'payments'): ?>
    <div class="alert alert-info">Online payments are confirmed with PayPal / Stripe before an order is marked <b>Paid</b>. Your site must use <b>https://</b> for live payments.</div>
  <?php endif; ?>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit">Save settings</button>
    <?php if ($k === 'whatsapp' && setting('whatsapp_number') !== ''): ?>
      <button class="btn btn-light" type="submit" name="action" value="test_whatsapp" formtarget="<?= setting('whatsapp_mode', 'link') === 'link' ? '_blank' : '_self' ?>">Send test message</button>
    <?php endif; ?>
  </div>
</form>
<?php endforeach; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
