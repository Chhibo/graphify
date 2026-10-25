<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_admin();
$uid = (int) $me['id'];

if (is_post()) {
    $action = input('action');
    if ($action === 'start') {
        $_SESSION['2fa_setup'] = totp_new_secret();
        redirect('admin/security.php#setup');
    }
    if ($action === 'confirm') {
        $secret = (string) ($_SESSION['2fa_setup'] ?? '');
        if ($secret !== '' && totp_verify($secret, input('code'))) {
            [$plain, $hashes] = recovery_codes_generate();
            q('UPDATE users SET totp_secret = ?, totp_enabled = 1, recovery_codes = ? WHERE id = ?', [$secret, $hashes, $uid]);
            unset($_SESSION['2fa_setup']);
            $_SESSION['show_recovery'] = $plain;
            log_activity('security.2fa_on', 'Two-factor sign-in enabled', 'user', $uid);
            flash('success', 'Two-factor sign-in is on. Save your recovery codes now.');
        } else {
            flash('error', 'That code did not match. Make sure you scanned the new QR code and that your phone’s time is correct.');
        }
        redirect('admin/security.php');
    }
    if (in_array($action, ['disable', 'regenerate'], true)) {
        if (!totp_verify((string) $me['totp_secret'], input('code'))) {
            flash('error', 'Enter a current code from your authenticator app to confirm.');
            redirect('admin/security.php');
        }
        if ($action === 'disable') {
            if (setting('require_admin_2fa') === '1') {
                flash('error', 'Two-factor is required for all admins. Switch that off in Settings → Sign-up & login first.');
                redirect('admin/security.php');
            }
            q("UPDATE users SET totp_secret = '', totp_enabled = 0, recovery_codes = NULL WHERE id = ?", [$uid]);
            log_activity('security.2fa_off', 'Two-factor sign-in disabled', 'user', $uid);
            flash('success', 'Two-factor sign-in is off.');
        } else {
            [$plain, $hashes] = recovery_codes_generate();
            q('UPDATE users SET recovery_codes = ? WHERE id = ?', [$hashes, $uid]);
            $_SESSION['show_recovery'] = $plain;
            log_activity('security.recovery_codes', 'New recovery codes created', 'user', $uid);
            flash('success', 'New recovery codes created. The old ones no longer work.');
        }
        redirect('admin/security.php');
    }
}

$me = current_user(true);
$setupSecret = $_SESSION['2fa_setup'] ?? null;
$showCodes = $_SESSION['show_recovery'] ?? null;
unset($_SESSION['show_recovery']);
$codesLeft = count(json_decode((string) $me['recovery_codes'], true) ?: []);
$admins = q_all("SELECT id, name, email, totp_enabled FROM users WHERE role = 'admin' ORDER BY name");

$adminActive = 'security';
$pageTitle = 'My security';
require APP_ROOT . '/includes/admin-top.php';
?>
<?php if (setting('require_admin_2fa') === '1' && !$me['totp_enabled']): ?>
  <div class="alert alert-warning"><?= icon('lock') ?><span>Two-factor sign-in is required for administrators. Set it up below to continue using the admin panel.</span></div>
<?php endif; ?>

<div class="admin-grid">
  <section class="card form-section">
    <h2><?= icon('smartphone-key') ?>Two-factor sign-in</h2>
    <?php if ($me['totp_enabled']): ?>
      <div class="verify-state good inline"><span class="badge badge-success"><?= icon('check') ?>On</span><span>When you sign in, you'll be asked for a 6-digit code from your authenticator app.</span></div>
      <p class="muted small">Recovery codes left: <strong><?= $codesLeft ?></strong></p>
      <?php if ($showCodes): ?>
        <div class="recovery-box">
          <p><strong>Your recovery codes</strong> — each works once if you lose your phone. Save them somewhere safe; they won't be shown again.</p>
          <ul class="recovery-codes"><?php foreach ($showCodes as $c): ?><li><code><?= e($c) ?></code></li><?php endforeach; ?></ul>
          <button class="btn btn-ghost btn-sm" type="button" data-copy-text="<?= e(implode("\n", $showCodes)) ?>"><?= icon('copy') ?>Copy codes</button>
        </div>
      <?php endif; ?>
      <form method="post" class="inline-form plain">
        <?= csrf_field() ?>
        <input name="code" class="otp-input sm" inputmode="numeric" maxlength="6" placeholder="Current code" required autocomplete="one-time-code">
        <button class="btn btn-ghost btn-sm" name="action" value="regenerate"><?= icon('refresh') ?>New recovery codes</button>
        <button class="btn btn-ghost btn-sm danger" name="action" value="disable">Turn off</button>
      </form>
    <?php elseif ($setupSecret): ?>
      <ol class="setup-steps" id="setup">
        <li>Install an authenticator app on your phone (Google Authenticator, Microsoft Authenticator, Authy, 1Password…).</li>
        <li>In the app, add an account and scan this QR code:
          <div class="qr-box"><div data-qr="<?= e(totp_uri($setupSecret, $me['email'])) ?>" aria-label="QR code"></div>
            <p class="muted small">Can't scan? Enter this key manually:<br><code class="secret-key"><?= e(trim(chunk_split($setupSecret, 4, ' '))) ?></code></p></div></li>
        <li>Type the 6-digit code the app shows:
          <form method="post" class="inline-form plain"><?= csrf_field() ?><input type="hidden" name="action" value="confirm">
            <input name="code" class="otp-input" inputmode="numeric" maxlength="7" placeholder="123456" required autocomplete="one-time-code" autofocus>
            <button class="btn btn-primary" type="submit">Turn on</button></form></li>
      </ol>
    <?php else: ?>
      <p>Protect the admin panel even if someone learns your password: after your password you'll also enter a code from an app on your phone.</p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="start"><button class="btn btn-primary" type="submit"><?= icon('lock') ?>Set up two-factor sign-in</button></form>
    <?php endif; ?>
  </section>

  <section class="card table-card">
    <div class="card-head"><h2>Administrators</h2></div>
    <ul class="mini-list">
      <?php foreach ($admins as $a): ?>
        <li><?= avatar_html($a + ['avatar' => ''], 'sm') ?><div class="mini-body"><span><?= e($a['name']) ?></span><small><?= e($a['email']) ?></small></div>
          <?= $a['totp_enabled'] ? '<span class="badge badge-success">2FA on</span>' : '<span class="badge badge-warning">2FA off</span>' ?></li>
      <?php endforeach; ?>
    </ul>
    <p class="muted small" style="padding:0 20px 16px">To require two-factor for every admin, switch on <em>Admins must use two-factor sign-in</em> in Settings → Sign-up & login.</p>
  </section>
</div>
<script src="<?= e(asset('js/vendor/qrcode.js')) ?>" defer></script>
<?php require APP_ROOT . '/includes/admin-bottom.php';
