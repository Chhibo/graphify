<?php
define('ALLOW_IN_MAINTENANCE', true);
require __DIR__ . '/includes/bootstrap.php';

$pending = $_SESSION['2fa_pending'] ?? null;
if (!$pending || time() - (int) $pending['at'] > 600) {
    unset($_SESSION['2fa_pending']);
    flash('info', __('Please sign in again.'));
    redirect('login.php');
}
$user = q_one('SELECT * FROM users WHERE id = ?', [$pending['uid']]);
if (!$user || empty($user['totp_enabled']) || $user['status'] === 'banned') {
    unset($_SESSION['2fa_pending']);
    redirect('login.php');
}
$error = '';
if (is_post()) {
    $code = input('code');
    if (too_many_attempts()) {
        $error = __('Too many attempts. Please wait 15 minutes and try again.');
    } elseif (totp_verify($user['totp_secret'], $code) || (strlen(preg_replace('/\s/', '', $code)) === 9 && use_recovery_code($user, $code))) {
        unset($_SESSION['2fa_pending']);
        $user['totp_enabled'] = 0; // already checked: finish the normal sign-in
        $usedRecovery = strlen(preg_replace('/\D/', '', $code)) !== 6;
        if ($usedRecovery) {
            flash('warning', __('You used a recovery code. Each code works only once — make new ones in your security settings if you are running low.'));
        }
        complete_login($user, (bool) $pending['remember'], $pending['fallback'] ?? 'dashboard/', (bool) $pending['social']);
    } else {
        record_attempt();
        $error = __('That code is not correct. Check the time on your phone and try again.');
    }
}
$pageTitle = __('Two-factor sign-in');
require __DIR__ . '/includes/header.php';
?>
<section class="container section-sm">
  <form method="post" class="card auth-single" autocomplete="off">
    <?= csrf_field() ?>
    <span class="auth-icon"><?= icon('smartphone-key') ?></span>
    <h1><?= t('Enter your security code') ?></h1>
    <p class="muted"><?= t('Open your authenticator app and type the 6-digit code for {site}.', ['site' => setting('site_name')]) ?></p>
    <?php if ($error): ?><div class="alert alert-error"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
    <div class="field"><label for="code"><?= t('Security code') ?></label>
      <input id="code" name="code" class="otp-input" inputmode="numeric" autocomplete="one-time-code" maxlength="12" required autofocus placeholder="123 456"></div>
    <button class="btn btn-primary btn-block btn-lg" type="submit"><?= t('Verify and sign in') ?></button>
    <p class="muted small center"><?= t('Lost your phone? Enter one of your recovery codes (like abcd-efgh) instead.') ?></p>
    <p class="center"><a class="small-link" href="<?= e(url('login.php')) ?>"><?= t('Cancel') ?></a></p>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php';
