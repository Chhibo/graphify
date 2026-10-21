<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin_layout.php';
require dirname(__DIR__) . '/inc/demo.php';
require_admin();

$socials = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'x' => 'X (Twitter)', 'telegram' => 'Telegram', 'tiktok' => 'TikTok'];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        foreach (['store_name', 'footer_about', 'locker_title', 'download_button', 'contact_email', 'contact_title', 'contact_intro',
                  'postback_ips', 'meta_description', 'site_language'] as $k) {
            save_setting($k, trim((string)($_POST[$k] ?? '')));
        }
        foreach (array_keys($socials) as $k) {
            $url = trim((string)($_POST['social_' . $k] ?? ''));
            if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }
            save_setting('social_' . $k, $url);
        }
        // Tracking code (Google Analytics, Facebook Pixel…). Only the admin can set it.
        save_setting('head_code', (string)($_POST['head_code'] ?? ''));
        save_setting('footer_code', (string)($_POST['footer_code'] ?? ''));
        save_setting('offers_count', (string)max(1, min(10, (int)($_POST['offers_count'] ?? 4))));
        save_setting('download_hours', (string)max(1, (int)($_POST['download_hours'] ?? 24)));
        save_setting('contact_notify', isset($_POST['contact_notify']) ? '1' : '0');
        save_setting('trust_cloudflare', isset($_POST['trust_cloudflare']) ? '1' : '0');
        flash('Settings saved.');
        redirect('admin/settings.php');
    }
    if ($action === 'password') {
        if (!password_verify((string)($_POST['current'] ?? ''), setting('admin_pass'))) {
            $errors[] = 'Current password is wrong.';
        } elseif (strlen((string)($_POST['new'] ?? '')) < 8) {
            $errors[] = 'The new password needs at least 8 characters.';
        } else {
            save_setting('admin_pass', password_hash((string)$_POST['new'], PASSWORD_DEFAULT));
            flash('Password changed.');
            redirect('admin/settings.php');
        }
    }
    if ($action === 'new_secret') {
        save_setting('postback_secret', random_token(12));
        flash('New postback secret created. Update the postback URL in every network dashboard now.');
        redirect('admin/networks.php');
    }
    if ($action === 'demo_add') {
        install_demo_data();
        flash('Demo content added. Take a look at your store.');
        redirect('admin/settings.php#demo');
    }
    if ($action === 'demo_remove') {
        remove_demo_data();
        flash('Demo content removed.');
        redirect('admin/settings.php#demo');
    }
    if ($action === 'backup') {
        // A consistent copy of the database, streamed to the browser.
        $tmp = DATA_DIR . '/backup-' . random_token(6) . '.sqlite';
        try {
            db()->exec('VACUUM INTO ' . db()->quote($tmp));
        } catch (Throwable $ex) {
            copy(DB_FILE, $tmp);
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="store-backup-' . date('Y-m-d') . '.sqlite"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }
}

admin_header('Settings', 'Store details, contact page, SEO, tracking code and security');
?>
<?php foreach ($errors as $err): ?><?= notice(e($err), '', '', 'bad', 'x') ?><?php endforeach; ?>
<form method="post" class="form">
  <?= csrf_field() ?>
  <div class="grid-2-even">
    <div>
      <div class="card">
        <h2>Store</h2>
        <p class="card-sub">Logo, colors and home page sections are in <a href="appearance.php">Appearance</a>.</p>
        <label>Store name <input name="store_name" value="<?= e(setting('store_name')) ?>"></label>
        <label>About text in the footer
          <textarea name="footer_about" rows="3" placeholder="A short sentence about your store"><?= e(setting('footer_about')) ?></textarea></label>
        <label>Site language code <input name="site_language" class="short" value="<?= e(setting_or('site_language', 'en')) ?>" placeholder="en">
          <small>For search engines and screen readers: en, fr, es, ar, de…</small></label>
      </div>

      <div class="card" id="contact">
        <h2>Contact us page</h2>
        <p class="card-sub">Messages arrive in <a href="messages.php">Messages</a>.</p>
        <label>Page title <input name="contact_title" value="<?= e(setting('contact_title', 'Contact us')) ?>"></label>
        <label>Text next to the form (optional)
          <textarea name="contact_intro" rows="3" placeholder="Questions about a download? Send us a message and we'll reply within 24 hours."><?= e(setting('contact_intro')) ?></textarea></label>
        <label>Your email <input type="email" name="contact_email" value="<?= e(setting('contact_email')) ?>">
          <small>Not shown to visitors. Used for email copies of messages.</small></label>
        <label class="check"><input type="checkbox" name="contact_notify" <?= setting('contact_notify') === '1' ? 'checked' : '' ?>>
          <span>Email me a copy of every new message <small>Emails sent by shared hosting sometimes land in spam.</small></span></label>
      </div>

      <div class="card">
        <h2>Offer locker</h2>
        <p class="card-sub">The popup visitors see when they click download.</p>
        <label>Download button text <input name="download_button" value="<?= e(setting('download_button')) ?>" placeholder="Download for free"></label>
        <label>Popup heading <input name="locker_title" value="<?= e(setting('locker_title')) ?>" placeholder="Complete one offer to unlock your free download"></label>
        <div class="form-row">
          <label>Offers to show <input type="number" min="1" max="10" name="offers_count" value="<?= e(setting('offers_count', '4')) ?>" class="short"></label>
          <label>Link valid for (hours) <input type="number" min="1" name="download_hours" value="<?= e(setting('download_hours', '24')) ?>" class="short"></label>
        </div>
      </div>
    </div>

    <div>
      <div class="card">
        <h2>SEO &amp; social</h2>
        <p class="card-sub">Submit <code><?= e(base_url('sitemap.php')) ?></code> in Google Search Console so Google finds every product.</p>
        <label>Store description for Google <textarea name="meta_description" rows="2" maxlength="160" placeholder="Free ebooks, templates and presets. New downloads every week."><?= e(setting('meta_description')) ?></textarea>
          <small>About 150 characters. Product pages use their own description.</small></label>
        <?php foreach ($socials as $k => $label): ?>
          <label><?= e($label) ?> <input name="social_<?= $k ?>" value="<?= e(setting('social_' . $k)) ?>" placeholder="https://"></label>
        <?php endforeach; ?>
        <small>Filled links appear as icons in the footer and on the contact page.</small>
      </div>

      <div class="card">
        <h2>Tracking code</h2>
        <p class="card-sub">Paste code from Google Analytics, Facebook Pixel, Microsoft Clarity…</p>
        <label>Inside &lt;head&gt; <textarea name="head_code" rows="4" class="mono" placeholder="<script>…</script>"><?= e(setting('head_code')) ?></textarea></label>
        <label>Before &lt;/body&gt; <textarea name="footer_code" rows="3" class="mono"><?= e(setting('footer_code')) ?></textarea></label>
      </div>

      <div class="card">
        <h2>Security</h2>
        <label>Only accept postbacks from these IPs (optional, comma separated)
          <input name="postback_ips" value="<?= e(setting('postback_ips')) ?>">
          <small>Leave empty unless your network publishes its postback server IPs. The secret in the URL already protects you.</small>
        </label>
        <label class="check"><input type="checkbox" name="trust_cloudflare" <?= setting('trust_cloudflare') === '1' ? 'checked' : '' ?>>
          <span>My site is behind Cloudflare <small>Uses the real visitor IP so offers match their country.</small></span></label>
      </div>
    </div>
  </div>
  <div class="form-actions"><button class="btn btn-grad" name="action" value="save"><?= icon('save', 16) ?> Save settings</button></div>
</form>

<div class="grid-2-even">
  <form method="post" class="card form">
    <?= csrf_field() ?>
    <h2>Change admin password</h2>
    <p class="card-sub">Use at least 8 characters.</p>
    <label>Current password <input type="password" name="current" required autocomplete="current-password"></label>
    <label>New password <input type="password" name="new" required minlength="8" autocomplete="new-password"></label>
    <button class="btn" name="action" value="password">Change password</button>
  </form>
  <div>
    <form method="post" class="card" id="demo"<?= demo_installed() ? ' onsubmit="return confirm(\'Remove all demo products, demo pages and empty demo categories? Demo products you edited are removed too.\')"' : '' ?>>
      <?= csrf_field() ?>
      <h2>Demo content</h2>
      <?php if (demo_installed()): ?>
        <p class="card-sub">Your store is showing <b>demo products</b>, categories and About/FAQ pages. Remove them before you launch. Your own products are not touched.</p>
        <button class="btn btn-danger" name="action" value="demo_remove"><?= icon('trash', 16) ?> Remove demo content</button>
      <?php else: ?>
        <p class="card-sub">Add 12 sample products, 4 categories and About/FAQ pages to see how the store looks. You can remove them again with one click.</p>
        <button class="btn btn-light" name="action" value="demo_add"><?= icon('package', 16) ?> Add demo content</button>
      <?php endif; ?>
    </form>
    <form method="post" class="card">
      <?= csrf_field() ?>
      <h2>Backup</h2>
      <p class="card-sub">Download a copy of your database: products, categories, pages, settings, messages and stats. Product files and images are in the <code>data/files</code> and <code>uploads</code> folders; back those up from your hosting.</p>
      <button class="btn btn-light" name="action" value="backup"><?= icon('database', 16) ?> Download backup</button>
    </form>
    <form method="post" class="card" onsubmit="return confirm('Old postback URLs will stop working until you paste the new ones. Continue?')">
      <?= csrf_field() ?>
      <h2>Postback secret</h2>
      <p class="card-sub">Create a new secret if you think your postback URL leaked.</p>
      <button class="btn btn-danger" name="action" value="new_secret">Create new secret</button>
    </form>
  </div>
</div>
<?php admin_footer();
