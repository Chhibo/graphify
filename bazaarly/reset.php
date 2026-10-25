<?php
require __DIR__ . '/includes/bootstrap.php';

$token = input('token');
$reset = $token !== '' ? q_one('SELECT * FROM password_resets WHERE token_hash = ? AND expires_at > ?', [hash('sha256', $token), now()]) : null;
$error = '';

if ($reset && is_post()) {
    $pw = (string) ($_POST['password'] ?? '');
    if (strlen($pw) < 8) {
        $error = __('Use at least 8 characters.');
    } elseif ($pw !== (string) ($_POST['password_confirm'] ?? '')) {
        $error = __('Passwords do not match.');
    } else {
        q('UPDATE users SET password = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $reset['user_id']]);
        q('DELETE FROM password_resets WHERE user_id = ?', [$reset['user_id']]);
        flash('success', __('Your password has been changed. You can sign in now.'));
        redirect('login.php');
    }
}

$pageTitle = __('Choose a new password');
require __DIR__ . '/includes/header.php';
?>
<section class="container section-sm">
  <?php if (!$reset): ?>
    <div class="card auth-single center">
      <span class="auth-icon"><?= icon('alert') ?></span>
      <h1><?= t('Link expired') ?></h1>
      <p class="muted"><?= t('This reset link is invalid or has expired. Please request a new one.') ?></p>
      <a class="btn btn-primary" href="<?= e(url('forgot.php')) ?>"><?= t('Request new link') ?></a>
    </div>
  <?php else: ?>
    <form method="post" class="card auth-single">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <span class="auth-icon"><?= icon('lock') ?></span>
      <h1><?= t('Choose a new password') ?></h1>
      <?php if ($error): ?><div class="alert alert-error"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
      <div class="field"><label for="password"><?= t('New password') ?></label><input id="password" type="password" name="password" minlength="8" required autocomplete="new-password"></div>
      <div class="field"><label for="password_confirm"><?= t('Confirm password') ?></label><input id="password_confirm" type="password" name="password_confirm" required autocomplete="new-password"></div>
      <button class="btn btn-primary btn-block" type="submit"><?= t('Save password') ?></button>
    </form>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php';
