<?php
require __DIR__ . '/includes/bootstrap.php';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$cust = strlen($token) === 64
    ? q_one('SELECT * FROM customers WHERE reset_token = ? AND reset_expires > ? AND active = 1', [hash('sha256', $token), now()])
    : null;
$error = '';
if ($cust && is_post()) {
    verify_csrf();
    $pass = (string) ($_POST['password'] ?? '');
    if (strlen($pass) < 8) {
        $error = 'Your password must be at least 8 characters.';
    } elseif ($pass !== (string) ($_POST['password2'] ?? '')) {
        $error = 'The two passwords do not match.';
    } else {
        q("UPDATE customers SET password = ?, reset_token = '', reset_expires = NULL WHERE id = ?", [password_hash($pass, PASSWORD_DEFAULT), $cust['id']]);
        customer_login($cust);
        flash('success', 'Your password has been changed.');
        redirect('account.php');
    }
}
$pageTitle = 'Choose a new password';
include __DIR__ . '/includes/header.php';
?>
<div class="container auth-wrap">
  <div class="auth-card">
    <h1>Choose a new password</h1>
    <?php if (!$cust): ?>
      <div class="alert alert-error">This link is invalid or has expired.</div>
      <a class="btn btn-primary btn-block" href="<?= url('forgot-password.php') ?>">Get a new link</a>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label>New password <small class="muted">(min. 8)</small><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
        <label>Repeat new password<input type="password" name="password2" required minlength="8" autocomplete="new-password"></label>
        <button class="btn btn-primary btn-block" type="submit">Save new password</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
