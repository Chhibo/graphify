<?php
require __DIR__ . '/includes/bootstrap.php';

if (!accounts_enabled()) {
    redirect('');
}
$sent = false;
if (is_post()) {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $cust = filter_var($email, FILTER_VALIDATE_EMAIL) ? q_one('SELECT * FROM customers WHERE email = ? AND active = 1', [$email]) : null;
    if ($cust && !login_rate_limited('reset')) {
        login_failed('reset'); // counts towards the limit so the form cannot be used to spam an inbox
        $token = bin2hex(random_bytes(32));
        q('UPDATE customers SET reset_token = ?, reset_expires = ? WHERE id = ?', [hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600), $cust['id']]);
        send_template($cust['email'], 'Reset your password - ' . setting('store_name'), 'Reset your password',
            '<p>Hello ' . e(explode(' ', $cust['name'])[0]) . ',</p><p>Click the button below to choose a new password. This link is valid for 1 hour.</p><p style="color:#888;font-size:13px">If you did not ask for this, you can ignore this email.</p>',
            'Choose a new password', full_url('reset-password.php?token=' . $token));
    }
    $sent = true; // same answer whether the email exists or not (privacy)
}
$pageTitle = 'Forgot password';
include __DIR__ . '/includes/header.php';
?>
<div class="container auth-wrap">
  <div class="auth-card">
    <h1>Forgot your password?</h1>
    <?php if ($sent): ?>
      <div class="alert alert-success">If an account exists for this email, we have sent a link to reset your password. Please check your inbox (and spam folder).</div>
      <a class="btn btn-primary btn-block" href="<?= url('login.php') ?>">Back to log in</a>
    <?php else: ?>
      <p class="muted">Enter your email and we will send you a link to choose a new password.</p>
      <form method="post">
        <?= csrf_field() ?>
        <label>Email<input type="email" name="email" required autocomplete="email" autofocus></label>
        <button class="btn btn-primary btn-block" type="submit">Send reset link</button>
      </form>
      <p class="auth-switch"><a href="<?= url('login.php') ?>">← Back to log in</a></p>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
