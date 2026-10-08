<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin_layout.php';
require_admin();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        foreach (['store_name', 'locker_title', 'contact_email', 'contact_title', 'contact_intro', 'postback_ips'] as $k) {
            save_setting($k, trim((string)($_POST[$k] ?? '')));
        }
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
}

admin_header('Settings');
?>
<?php foreach ($errors as $err): ?><p class="bad"><?= e($err) ?></p><?php endforeach; ?>
<form method="post" class="card form">
  <?= csrf_field() ?>
  <h2>Store</h2>
  <label>Store name <input name="store_name" value="<?= e(setting('store_name')) ?>"></label>
  <p class="muted">Logo, colors and home page sections are in <a href="appearance.php">Appearance</a>.</p>
  <h2 id="contact">Contact us page</h2>
  <label>Page title <input name="contact_title" value="<?= e(setting('contact_title', 'Contact us')) ?>"></label>
  <label>Text above the form (optional)
    <textarea name="contact_intro" rows="3" placeholder="Questions about a download? Send us a message and we'll reply within 24 hours."><?= e(setting('contact_intro')) ?></textarea></label>
  <label>Your email <input type="email" name="contact_email" value="<?= e(setting('contact_email')) ?>">
    <small>Not shown to visitors. Used for email copies of messages.</small></label>
  <label class="check"><input type="checkbox" name="contact_notify" <?= setting('contact_notify') === '1' ? 'checked' : '' ?>>
    <span>Email me a copy of every new message <small>Messages are always saved in Admin → Messages. Emails sent by shared hosting sometimes land in spam.</small></span></label>
  <h2>Locker</h2>
  <label>Locker heading <input name="locker_title" value="<?= e(setting('locker_title')) ?>"></label>
  <label>Offers to show (1–10) <input type="number" min="1" max="10" name="offers_count" value="<?= e(setting('offers_count', '4')) ?>" class="short"></label>
  <label>Download link stays valid for (hours) <input type="number" min="1" name="download_hours" value="<?= e(setting('download_hours', '24')) ?>" class="short"></label>
  <h2>Security</h2>
  <label>Only accept postbacks from these IPs (optional, comma separated)
    <input name="postback_ips" value="<?= e(setting('postback_ips')) ?>">
    <small>Leave empty unless your network publishes its postback server IPs. The secret in the URL already protects you.</small>
  </label>
  <label class="check"><input type="checkbox" name="trust_cloudflare" <?= setting('trust_cloudflare') === '1' ? 'checked' : '' ?>>
    My site is behind Cloudflare (use the real visitor IP so offers match their country)</label>
  <p class="muted">Privacy Policy, Terms and other pages are edited in <a href="pages.php">Pages</a>.</p>
  <button class="btn" name="action" value="save">Save settings</button>
</form>

<form method="post" class="card form">
  <?= csrf_field() ?>
  <h2>Change admin password</h2>
  <label>Current password <input type="password" name="current" required></label>
  <label>New password <input type="password" name="new" required minlength="8"></label>
  <button class="btn" name="action" value="password">Change password</button>
</form>

<form method="post" class="card form" onsubmit="return confirm('Old postback URLs will stop working until you paste the new ones. Continue?')">
  <?= csrf_field() ?>
  <h2>Postback secret</h2>
  <p class="muted">Create a new secret if you think your postback URL leaked.</p>
  <button class="btn btn-danger" name="action" value="new_secret">Create new secret</button>
</form>
<?php admin_footer();
