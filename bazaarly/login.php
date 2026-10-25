<?php
define('ALLOW_IN_MAINTENANCE', true);
require __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('dashboard/');
}
// ?next=/path lets links such as "Make an offer" bring the visitor back after signing in (local paths only)
$next = is_string($_GET['next'] ?? null) ? $_GET['next'] : '';
if ($next !== '' && preg_match('~^/(?![/\\\\])~', $next)) {
    $_SESSION['intended'] = $next;
}
$error = '';
$login = '';
if (is_post()) {
    $login = input('login');
    $password = (string) ($_POST['password'] ?? '');
    if (too_many_attempts()) {
        $error = __('Too many sign-in attempts. Please wait 15 minutes and try again.');
    } elseif (!captcha_passed('login')) {
        $error = __('Please complete the security check.');
    } else {
        $user = q_one('SELECT * FROM users WHERE email = ? OR username = ?', [strtolower($login), $login]);
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'banned') {
                $error = __('This account has been suspended. Please contact support.');
            } else {
                if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                    q('UPDATE users SET password = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                }
                flash('success', __('Welcome back, {name}!', ['name' => strtok($user['name'], ' ')]));
                complete_login($user, !empty($_POST['remember']), $user['role'] === 'admin' && setting('maintenance_mode') === '1' ? 'admin/' : 'dashboard/');
            }
        } else {
            record_attempt();
            $error = __('The email/username or password is incorrect.');
        }
    }
}
$pageTitle = __('Sign in');
require __DIR__ . '/includes/header.php';
?>
<section class="container section-sm">
  <div class="auth-wrap">
    <?php include __DIR__ . '/includes/partials/auth-aside.php'; ?>
    <form method="post" class="auth-form" novalidate>
      <?= csrf_field() ?>
      <h1><?= t('Welcome back') ?></h1>
      <p class="muted"><?= t('Sign in to manage your ads and messages.') ?></p>
      <?= social_buttons() ?>
      <?php if ($error): ?><div class="alert alert-error"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
      <div class="field">
        <label for="login"><?= t('Email or username') ?></label>
        <div class="input-icon"><?= icon('user') ?><input id="login" name="login" value="<?= e($login) ?>" autocomplete="username" required autofocus></div>
      </div>
      <div class="field">
        <label for="password"><?= t('Password') ?></label>
        <div class="input-icon"><?= icon('lock') ?><input id="password" type="password" name="password" autocomplete="current-password" required>
          <button class="pw-toggle" type="button" data-pw-toggle aria-label="<?= t('Show password') ?>"><?= icon('eye') ?></button></div>
      </div>
      <div class="row-between">
        <label class="check"><input type="checkbox" name="remember" value="1"><span><?= t('Keep me signed in') ?></span></label>
        <a class="small-link" href="<?= e(url('forgot.php')) ?>"><?= t('Forgot password?') ?></a>
      </div>
      <?= captcha_widget('login') ?>
      <button class="btn btn-primary btn-block btn-lg" type="submit"><?= t('Sign in') ?></button>
      <?php if (setting('allow_registration') === '1'): ?>
        <p class="center muted"><?= t('New here?') ?> <a href="<?= e(url('register.php')) ?>"><?= t('Create a free account') ?></a></p>
      <?php endif; ?>
    </form>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php';
