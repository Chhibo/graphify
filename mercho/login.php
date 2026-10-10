<?php
require __DIR__ . '/includes/bootstrap.php';

if (!accounts_enabled()) {
    redirect('track.php');
}
$return = safe_return((string) ($_GET['return'] ?? $_POST['return'] ?? 'account.php'));
if (customer_logged_in()) {
    redirect($return);
}
$error = '';
$email = '';
if (is_post()) {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (login_rate_limited('customer')) {
        $error = 'Too many attempts. Please wait 10 minutes and try again.';
    } else {
        $cust = q_one('SELECT * FROM customers WHERE email = ?', [$email]);
        if ($cust && password_verify((string) ($_POST['password'] ?? ''), $cust['password'])) {
            if (!(int) $cust['active']) {
                $error = 'This account is disabled. Please contact us.';
            } else {
                customer_login($cust);
                flash('success', 'Welcome back, ' . explode(' ', $cust['name'])[0] . '!');
                redirect($return);
            }
        } else {
            login_failed('customer');
            $error = 'Wrong email or password.';
        }
    }
}
$pageTitle = 'Log in';
include __DIR__ . '/includes/header.php';
?>
<div class="container auth-wrap">
  <div class="auth-card">
    <h1>Log in</h1>
    <p class="muted">Welcome back! Log in to see your orders and check out faster.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($return === 'checkout.php' && !guest_checkout_allowed()): ?><div class="alert alert-info">Please log in or create an account to place your order.</div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="return" value="<?= e($return) ?>">
      <label>Email<input type="email" name="email" value="<?= e($email) ?>" required autocomplete="email" autofocus></label>
      <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
      <div class="auth-row"><span></span><a href="<?= url('forgot-password.php') ?>">Forgot your password?</a></div>
      <button class="btn btn-primary btn-block" type="submit">Log in</button>
    </form>
    <p class="auth-switch">New here? <a href="<?= url('register.php?return=' . rawurlencode($return)) ?>">Create an account</a></p>
    <?php if ($return === 'checkout.php' && guest_checkout_allowed()): ?>
      <p class="auth-switch"><a href="<?= url('checkout.php?guest=1') ?>">Continue as guest →</a></p>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
