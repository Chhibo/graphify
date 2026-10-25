<?php
require __DIR__ . '/includes/bootstrap.php';

// Resend the confirmation email
if (is_post() && input('action') === 'resend') {
    $me = require_login();
    if (!empty($me['email_verified_at'])) {
        flash('info', __('Your email is already confirmed.'));
    } elseif (time() - (int) ($_SESSION['verify_sent_at'] ?? 0) < 120) {
        flash('warning', __('We just sent an email. Please wait a couple of minutes before asking again.'));
    } else {
        $_SESSION['verify_sent_at'] = time();
        send_verification_email($me)
            ? flash('success', __('Confirmation email sent to {email}. Check your inbox and spam folder.', ['email' => $me['email']]))
            : flash('error', __('We could not send the email right now. Please try again later or contact us.'));
    }
    back('dashboard/');
}

$uid = consume_token(input('token'), 'verify');
if ($uid) {
    q('UPDATE users SET email_verified_at = ? WHERE id = ?', [now(), $uid]);
    flash('success', __('Thanks! Your email address is confirmed.'));
    redirect(is_logged_in() ? 'dashboard/' : 'login.php');
}

$pageTitle = __('Confirm email');
require __DIR__ . '/includes/header.php';
?>
<section class="container section-sm">
  <div class="card auth-single center">
    <span class="auth-icon"><?= icon('alert') ?></span>
    <h1><?= t('Link expired') ?></h1>
    <p class="muted"><?= t('This confirmation link is invalid or has already been used. Sign in and use “Resend email” to get a new one.') ?></p>
    <a class="btn btn-primary" href="<?= e(url(is_logged_in() ? 'dashboard/' : 'login.php')) ?>"><?= is_logged_in() ? t('Go to dashboard') : t('Sign in') ?></a>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php';
