<?php
require __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('dashboard/');
}
$closed = setting('allow_registration') !== '1';
$errors = [];
$d = ['name' => '', 'username' => '', 'email' => '', 'phone' => '', 'location' => ''];

if (is_post() && !$closed) {
    foreach ($d as $k => $_) {
        $d[$k] = mb_substr(input($k), 0, 150);
    }
    $d['email'] = strtolower($d['email']);
    $pw = (string) ($_POST['password'] ?? '');
    $pw2 = (string) ($_POST['password_confirm'] ?? '');

    if (mb_strlen($d['name']) < 2) $errors['name'] = 'Please enter your name.';
    if (!preg_match('/^[a-zA-Z0-9_.]{3,30}$/', $d['username'])) $errors['username'] = 'Use 3–30 letters, numbers, dots or underscores.';
    elseif (q_val('SELECT id FROM users WHERE username = ?', [$d['username']])) $errors['username'] = 'That username is already taken.';
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Please enter a valid email address.';
    elseif (q_val('SELECT id FROM users WHERE email = ?', [$d['email']])) $errors['email'] = 'An account with this email already exists.';
    if (strlen($pw) < 8) $errors['password'] = 'Use at least 8 characters.';
    elseif ($pw !== $pw2) $errors['password_confirm'] = 'Passwords do not match.';
    if (empty($_POST['terms'])) $errors['terms'] = 'Please accept the terms to continue.';
    if (input('website') !== '') $errors['name'] = 'Spam detected.';

    if (!$errors) {
        $id = db_insert('users', $d + [
            'password' => password_hash($pw, PASSWORD_DEFAULT),
            'bio' => '',
            'role' => 'user',
            'status' => 'active',
            'verified' => 0,
            'created_at' => now(),
        ]);
        login_user(q_one('SELECT * FROM users WHERE id = ?', [$id]));
        flash('success', 'Your account is ready. Welcome to ' . setting('site_name') . '!');
        header('Location: ' . intended_url('dashboard/'));
        exit;
    }
}

$err = fn(string $k) => isset($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : '';
$pageTitle = 'Create account';
require __DIR__ . '/includes/header.php';
?>
<section class="container section-sm">
  <div class="auth-wrap">
    <?php include __DIR__ . '/includes/partials/auth-aside.php'; ?>
    <?php if ($closed): ?>
      <div class="auth-form"><h1>Registration is closed</h1><p class="muted">New sign-ups are currently disabled. Please check back later.</p><a class="btn btn-primary" href="<?= e(url('login.php')) ?>">Sign in</a></div>
    <?php else: ?>
    <form method="post" class="auth-form" novalidate>
      <?= csrf_field() ?>
      <h1>Create your account</h1>
      <p class="muted">It's free and takes less than a minute.</p>
      <div class="form-grid">
        <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>"><label for="name">Full name</label><input id="name" name="name" value="<?= e($d['name']) ?>" autocomplete="name" required><?= $err('name') ?></div>
        <div class="field<?= isset($errors['username']) ? ' has-error' : '' ?>"><label for="username">Username</label><input id="username" name="username" value="<?= e($d['username']) ?>" autocomplete="username" required><?= $err('username') ?></div>
      </div>
      <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>"><label for="email">Email</label><div class="input-icon"><?= icon('mail') ?><input id="email" type="email" name="email" value="<?= e($d['email']) ?>" autocomplete="email" required></div><?= $err('email') ?></div>
      <div class="form-grid">
        <div class="field"><label for="phone">Phone <small class="muted">(optional)</small></label><input id="phone" type="tel" name="phone" value="<?= e($d['phone']) ?>" autocomplete="tel"></div>
        <div class="field"><label for="location">City <small class="muted">(optional)</small></label><input id="location" name="location" value="<?= e($d['location']) ?>"></div>
      </div>
      <div class="form-grid">
        <div class="field<?= isset($errors['password']) ? ' has-error' : '' ?>"><label for="password">Password</label><div class="input-icon"><?= icon('lock') ?><input id="password" type="password" name="password" autocomplete="new-password" required minlength="8"><button class="pw-toggle" type="button" data-pw-toggle aria-label="Show password"><?= icon('eye') ?></button></div><?= $err('password') ?></div>
        <div class="field<?= isset($errors['password_confirm']) ? ' has-error' : '' ?>"><label for="password_confirm">Confirm password</label><div class="input-icon"><?= icon('lock') ?><input id="password_confirm" type="password" name="password_confirm" autocomplete="new-password" required></div><?= $err('password_confirm') ?></div>
      </div>
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
      <label class="check<?= isset($errors['terms']) ? ' has-error' : '' ?>"><input type="checkbox" name="terms" value="1" <?= !empty($_POST['terms']) ? 'checked' : '' ?>><span>I agree to the <a href="<?= e(url('page.php?slug=terms')) ?>" target="_blank">terms</a> and <a href="<?= e(url('page.php?slug=privacy')) ?>" target="_blank">privacy policy</a></span></label>
      <?= $err('terms') ?>
      <button class="btn btn-primary btn-block btn-lg" type="submit">Create account</button>
      <p class="center muted">Already have an account? <a href="<?= e(url('login.php')) ?>">Sign in</a></p>
    </form>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php';
