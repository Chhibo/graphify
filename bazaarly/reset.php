<?php
require __DIR__ . '/includes/bootstrap.php';

$token = input('token');
$reset = $token !== '' ? q_one('SELECT * FROM password_resets WHERE token_hash = ? AND expires_at > ?', [hash('sha256', $token), now()]) : null;
$error = '';

if ($reset && is_post()) {
    $pw = (string) ($_POST['password'] ?? '');
    if (strlen($pw) < 8) {
        $error = 'Use at least 8 characters.';
    } elseif ($pw !== (string) ($_POST['password_confirm'] ?? '')) {
        $error = 'Passwords do not match.';
    } else {
        q('UPDATE users SET password = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $reset['user_id']]);
        q('DELETE FROM password_resets WHERE user_id = ?', [$reset['user_id']]);
        flash('success', 'Your password has been changed. You can sign in now.');
        redirect('login.php');
    }
}

$pageTitle = 'Choose a new password';
require __DIR__ . '/includes/header.php';
?>
<section class="container section-sm">
  <?php if (!$reset): ?>
    <div class="card auth-single center">
      <span class="auth-icon"><?= icon('alert') ?></span>
      <h1>Link expired</h1>
      <p class="muted">This reset link is invalid or has expired. Please request a new one.</p>
      <a class="btn btn-primary" href="<?= e(url('forgot.php')) ?>">Request new link</a>
    </div>
  <?php else: ?>
    <form method="post" class="card auth-single">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <span class="auth-icon"><?= icon('lock') ?></span>
      <h1>Choose a new password</h1>
      <?php if ($error): ?><div class="alert alert-error"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
      <div class="field"><label for="password">New password</label><input id="password" type="password" name="password" minlength="8" required autocomplete="new-password"></div>
      <div class="field"><label for="password_confirm">Confirm password</label><input id="password_confirm" type="password" name="password_confirm" required autocomplete="new-password"></div>
      <button class="btn btn-primary btn-block" type="submit">Save password</button>
    </form>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php';
