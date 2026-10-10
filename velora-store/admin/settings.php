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
        ['theme_color', 'Main color of the website', 'color', 'Buttons, links, badges, top bar and promo box use this color.'],
        ['theme_mode', 'Light / dark mode', 'select', 'Light is recommended. “Automatic” shows dark mode to visitors whose phone or computer is set to dark mode.', [
            'light' => 'Light (white background)',
            'dark' => 'Dark (black background)',
            'auto' => 'Automatic (follow the visitor\'s device)',
        ]],
        ['store_tagline', 'Footer text', 'textarea'],
        ['store_email', 'Store email', 'text'],
        ['', 'Currency & shipping', 'heading'],
        ['currency_code', 'Currency code', 'text', 'ISO code used for payments, e.g. USD, EUR, GBP, MAD. PayPal supports a limited list of currencies.'],
        ['currency_symbol', 'Currency symbol', 'text', 'Shown next to prices, e.g. $, €, DH'],
        ['currency_position', 'Symbol position', 'select', '', ['before' => 'Before price ($120)', 'after' => 'After price (120 DH)']],
        ['shipping_fee', 'Delivery fee', 'number', 'Flat fee added to every order. 0 = free delivery.'],
        ['free_shipping_over', 'Free delivery for orders over', 'number', '0 = disabled'],
        ['country_default', 'Default country at checkout', 'select', '', ['' => '- Let the customer choose -'] + countries()],
        ['order_prefix', 'Order number prefix', 'text'],
        ['', 'Header promo box (red box at the end of the menu bar)', 'heading'],
        ['promo_text', 'Promo text (empty = hide box)', 'text'],
        ['promo_badge', 'Badge text', 'text'],
        ['promo_link', 'Link', 'text', 'Example: shop.php?sale=1 or a full https:// address'],
        ['', 'Top announcement bar', 'heading'],
        ['announcement_enabled', 'Show announcement bar', 'checkbox'],
        ['announcement_text', 'Announcement text', 'text'],
        ['announcement_link_text', 'Link text (opens the On Sale page)', 'text'],
        ['', 'Social links (leave empty to hide)', 'heading'],
        ['social_facebook', 'Facebook URL', 'text'],
        ['social_instagram', 'Instagram URL', 'text'],
        ['social_twitter', 'X / Twitter URL', 'text'],
        ['social_github', 'GitHub URL', 'text'],
        ['', 'Customer accounts & checkout', 'heading'],
        ['accounts_enabled', 'Customers can create an account and log in', 'checkbox', 'Customers get a “My account” page with their orders, address and password.'],
        ['guest_checkout', 'Allow guest checkout (order without an account)', 'checkbox', 'When off, customers must log in or create an account before they can place an order.'],
        ['', 'Product reviews', 'heading'],
        ['reviews_enabled', 'Let customers write reviews on product pages', 'checkbox'],
        ['reviews_moderate', 'Approve reviews before they are published', 'checkbox', 'New reviews wait in Admin → Product reviews.'],
    ]],
    'home' => ['Home page', [
        ['', 'Hero', 'heading'],
        ['hero_subtitle', 'Small text above the title', 'text'],
        ['hero_title', 'Title', 'text'],
        ['hero_text', 'Text', 'textarea'],
        ['hero_button', 'Button text', 'text'],
        ['hero_image', 'Hero image', 'image'],
        ['stat1_value', 'Stat 1 number', 'text'], ['stat1_label', 'Stat 1 label', 'text'],
        ['stat2_value', 'Stat 2 number', 'text'], ['stat2_label', 'Stat 2 label', 'text'],
        ['stat3_value', 'Stat 3 number', 'text'], ['stat3_label', 'Stat 3 label', 'text'],
        ['', 'Best For Your Categories', 'heading'],
        ['categories_enabled', 'Show categories slider', 'checkbox'],
        ['categories_title', 'Title', 'text'],
        ['', 'Deal of the Days (products ticked “Deal of the Days”, 2 shown)', 'heading'],
        ['flash_enabled', 'Show Deal of the Days', 'checkbox'],
        ['deal_title', 'Title', 'text'],
        ['deal_text', 'Text', 'textarea'],
        ['flash_ends_at', 'Deal expires at', 'datetime'],
        ['deal_button', 'Button text', 'text'],
        ['', 'Wide banner', 'heading'],
        ['banner_enabled', 'Show banner (hidden by default)', 'checkbox'],
        ['banner_badge', 'Badge', 'text'],
        ['banner_title', 'Title', 'text'],
        ['banner_text', 'Text', 'textarea'],
        ['banner_button', 'Button text', 'text'],
        ['banner_image', 'Banner image (wide)', 'image'],
        ['', 'Instagram & newsletter', 'heading'],
        ['instagram_handle', 'Instagram handle (empty = hide section)', 'text'],
        ['instagram_url', 'Instagram profile URL', 'text'],
        ['newsletter_enabled', 'Show newsletter box above the footer', 'checkbox'],
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
    'popup' => ['Popup', [
        ['', 'Subscribe / first order popup', 'heading'],
        ['popup_enabled', 'Show the popup on the store', 'checkbox', 'Preview it any time by adding ?popup=1 to your store address.'],
        ['popup_coupon_id', 'Coupon shown in the popup', 'select', 'Create coupons in Admin → Coupons. The code is shown with a “copy” button, sent by email and applied to the cart when the visitor subscribes.',
            ['' => '- No coupon -'] + array_column(array_map(fn($c) => ['id' => (string) $c['id'], 'label' => $c['code'] . ' (' . coupon_label($c) . ')'], q_all('SELECT * FROM coupons ORDER BY id DESC')), 'label', 'id')],
        ['popup_image', 'Image (left side)', 'image'],
        ['popup_badge_value', 'Round badge - big text (e.g. 10%, empty = hide)', 'text'],
        ['popup_badge_text', 'Round badge - small text (e.g. OFF)', 'text'],
        ['popup_subtitle', 'Small title (e.g. FIRST ORDER OFFER)', 'text'],
        ['popup_title', 'Title', 'text', 'Put a word between [brackets] to show it in the main color, e.g. Take [10%] Off Your First Order'],
        ['popup_text', 'Text', 'textarea'],
        ['popup_link_text', 'Link text (e.g. SHOP NOW, empty = hide)', 'text'],
        ['popup_link', 'Link address', 'text', 'e.g. shop.php or shop.php?sale=1'],
        ['popup_delay', 'Show after (seconds)', 'number'],
        ['popup_days', 'After closing, show again after (days)', 'number', '0 = never show again to the same visitor'],
    ]],
    'contact' => ['Contact page', [
        ['', 'Contact page (contact.php)', 'heading'],
        ['contact_title', 'Title', 'text'],
        ['contact_text', 'Text above the form', 'textarea'],
        ['contact_email', 'Email that receives messages', 'text', 'Messages are always saved in Admin → Messages. Email delivery needs a server that can send email.'],
        ['', 'Info box (right side)', 'heading'],
        ['contact_image', 'Image', 'image'],
        ['contact_box_title', 'Box title', 'text'],
        ['contact_address', 'Address', 'textarea'],
        ['contact_phone', 'Phone', 'text'],
        ['contact_hours_title', 'Opening hours title', 'text'],
        ['contact_hours', 'Opening hours', 'textarea'],
        ['contact_map', 'Google Maps embed link (optional)', 'text', 'Google Maps → Share → Embed a map → copy only the link inside src="..." (starts with https://www.google.com/maps/embed?)'],
    ]],
    'email' => ['Email', [
        ['', 'How emails are sent', 'heading'],
        ['mail_driver', 'Sending method', 'select', 'SMTP (recommended): emails are sent from your own domain email account, like a real mail program. Fewer emails end up in spam.', [
            'smtp' => 'SMTP - my hosting / domain email account (recommended)',
            'mail' => 'PHP mail() - server default (no setup, may land in spam)',
        ]],
        ['mail_from_email', 'Sender email (From)', 'text', 'Use an address on your domain, e.g. shop@yourdomain.com. With SMTP it should be the same as the SMTP username.'],
        ['mail_from_name', 'Sender name', 'text'],
        ['', 'SMTP server (from your hosting: cPanel → Email Accounts → Connect Devices)', 'heading'],
        ['smtp_host', 'SMTP host', 'text', 'Usually mail.yourdomain.com (Gmail: smtp.gmail.com, Hostinger: smtp.hostinger.com)'],
        ['smtp_encryption', 'Encryption', 'select', '', ['ssl' => 'SSL (port 465) - recommended', 'tls' => 'TLS / STARTTLS (port 587)', 'none' => 'None (port 25, not recommended)']],
        ['smtp_port', 'Port', 'text', '465 for SSL, 587 for TLS'],
        ['smtp_username', 'SMTP username', 'text', 'Usually your full email address'],
        ['smtp_password', 'SMTP password', 'secret', 'The password of that email account (Gmail: an “App password”)'],
        ['', 'Notifications', 'heading'],
        ['admin_notify_email', 'Send store notifications to', 'text', 'Your email for new orders, messages and reviews'],
        ['notify_admin_order', 'Email me when a new order is placed', 'checkbox'],
        ['notify_admin_message', 'Email me new contact messages', 'checkbox'],
        ['notify_admin_review', 'Email me new product reviews', 'checkbox'],
        ['notify_customer_order', 'Send customers an order confirmation email', 'checkbox'],
        ['notify_customer_status', 'Email customers when their order status changes (you can untick it per order)', 'checkbox'],
        ['notify_customer_welcome', 'Send a welcome email when a customer creates an account', 'checkbox'],
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
            case 'color':
                $v = (string) ($_POST[$key] ?? '');
                if (preg_match('/^#[0-9a-fA-F]{6}$/', $v)) {
                    set_setting($key, strtolower($v));
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
    if (($_POST['action'] ?? '') === 'test_email') {
        // Settings above were saved first, so the test uses what is in the form.
        $to = trim((string) ($_POST['test_to'] ?? '')) ?: admin_email();
        $ok = send_template($to, 'Test email from ' . setting('store_name'), 'It works! 🎉',
            '<p>Your store can send emails. Sending method: <b>' . e(setting('mail_driver') === 'smtp' ? 'SMTP (' . setting('smtp_host') . ')' : 'PHP mail()') . '</b>.</p>');
        flash($ok ? 'success' : 'error', $ok ? 'Test email sent to ' . $to . '. Check the inbox (and spam folder).' : 'Sending failed: ' . mail_last_error());
        redirect('admin/settings.php?tab=email');
    }
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
        <?php elseif ($type === 'color'): ?>
          <span class="color-row"><input type="color" name="<?= e($key) ?>" value="<?= e($val !== '' ? $val : '#e03a3e') ?>">
            <?php foreach (['#e03a3e', '#111111', '#2f5d46', '#1d4ed8', '#7c3aed', '#db2777', '#ea580c'] as $sw): ?>
              <button type="button" class="swatch" style="background:<?= $sw ?>" onclick="this.parentNode.querySelector('input').value='<?= $sw ?>'" aria-label="<?= $sw ?>"></button>
            <?php endforeach; ?></span>
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
    <?php if ($k === 'email'): ?>
      <input type="email" name="test_to" placeholder="Send test to (default: <?= e(admin_email() ?: 'your email') ?>)" style="max-width:320px;margin:0">
      <button class="btn btn-light" type="submit" name="action" value="test_email" formnovalidate>Send test email</button>
    <?php endif; ?>
    <?php if ($k === 'whatsapp' && setting('whatsapp_number') !== ''): ?>
      <button class="btn btn-light" type="submit" name="action" value="test_whatsapp" formtarget="<?= setting('whatsapp_mode', 'link') === 'link' ? '_blank' : '_self' ?>">Send test message</button>
    <?php endif; ?>
  </div>
</form>
<?php endforeach; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
