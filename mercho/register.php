<?php
require __DIR__ . '/includes/bootstrap.php';

if (!accounts_enabled()) {
    redirect('');
}
$return = safe_return((string) ($_GET['return'] ?? $_POST['return'] ?? 'account.php'));
if (customer_logged_in()) {
    redirect($return);
}
$form = ['name' => '', 'email' => '', 'phone' => ''];
$errors = [];
if (is_post()) {
    verify_csrf();
    foreach ($form as $k => $v) {
        $form[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $form['email'] = strtolower($form['email']);
    $pass = (string) ($_POST['password'] ?? '');
    if (($_POST['website'] ?? '') !== '') {
        $errors[] = 'Please try again.'; // spam bot (hidden field filled)
    }
    if (mb_strlen($form['name']) < 2) {
        $errors[] = 'Please enter your full name.';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif ((int) q_val('SELECT COUNT(*) FROM customers WHERE email = ?', [$form['email']]) > 0) {
        $errors[] = 'An account with this email already exists. Please log in or reset your password.';
    }
    if ($form['phone'] !== '' && !preg_match('/^\+?[0-9 ()\-]{6,20}$/', $form['phone'])) {
        $errors[] = 'Please enter a valid phone number.';
    }
    if (strlen($pass) < 8) {
        $errors[] = 'Your password must be at least 8 characters.';
    } elseif ($pass !== (string) ($_POST['password2'] ?? '')) {
        $errors[] = 'The two passwords do not match.';
    }
    if (!$errors) {
        $id = db_insert('customers', [
            'name' => mb_substr($form['name'], 0, 150),
            'email' => mb_substr($form['email'], 0, 190),
            'phone' => mb_substr($form['phone'], 0, 40),
            'password' => password_hash($pass, PASSWORD_DEFAULT),
            'country_code' => country_code(setting('country_default')),
            'active' => 1,
            'created_at' => now(),
        ]);
        if (!empty($_POST['newsletter']) && (int) q_val('SELECT COUNT(*) FROM subscribers WHERE email = ?', [$form['email']]) === 0) {
            db_insert('subscribers', ['email' => $form['email'], 'created_at' => now()]);
        }
        $cust = q_one('SELECT * FROM customers WHERE id = ?', [$id]);
        customer_login($cust);
        if (setting_on('notify_customer_welcome')) {
            send_template($cust['email'], 'Welcome to ' . setting('store_name'), 'Welcome, ' . explode(' ', $cust['name'])[0] . '!',
                '<p>Your account at ' . e(setting('store_name')) . ' is ready. You can now check out faster and follow all your orders in one place.</p>',
                'Start shopping', full_url('shop.php'));
        }
        flash('success', 'Your account has been created. Welcome!');
        redirect($return);
    }
}
$pageTitle = 'Create an account';
include __DIR__ . '/includes/header.php';
?>
<div class="container auth-wrap">
  <div class="auth-card">
    <h1>Create an account</h1>
    <p class="muted">Check out faster, save your address and follow your orders.</p>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="return" value="<?= e($return) ?>">
      <label>Full name<input name="name" value="<?= e($form['name']) ?>" required maxlength="150" autocomplete="name"></label>
      <label>Email<input type="email" name="email" value="<?= e($form['email']) ?>" required maxlength="190" autocomplete="email"></label>
      <label>Phone (WhatsApp) <small class="muted">(optional)</small><input type="tel" name="phone" value="<?= e($form['phone']) ?>" autocomplete="tel"></label>
      <div class="grid-2">
        <label>Password <small class="muted">(min. 8)</small><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
        <label>Repeat password<input type="password" name="password2" required minlength="8" autocomplete="new-password"></label>
      </div>
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
      <label class="check"><input type="checkbox" name="newsletter" value="1"> Send me offers and news by email</label>
      <button class="btn btn-primary btn-block" type="submit">Create account</button>
    </form>
    <p class="auth-switch">Already have an account? <a href="<?= url('login.php?return=' . rawurlencode($return)) ?>">Log in</a></p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
