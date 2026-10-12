<?php
require __DIR__ . '/includes/auth.php';

if (current_admin()) {
    redirect('admin/dashboard.php');
}

$error = '';
$info = '';
$pending = $_SESSION['admin_2fa'] ?? null;
if ($pending && $pending['expires'] < time()) {
    unset($_SESSION['admin_2fa']);
    $pending = null;
    $error = 'The code expired. Please sign in again.';
}
if (isset($_GET['cancel'])) {
    unset($_SESSION['admin_2fa']);
    redirect('admin/index.php');
}

if (is_post()) {
    verify_csrf();
    $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? [];
    // Allow 5 attempts per 10 minutes per session.
    $_SESSION['login_attempts'] = array_filter($_SESSION['login_attempts'], fn($t) => $t > time() - 600);
    $step = $_POST['step'] ?? 'password';

    if ($step === 'code' && !$pending) {
        $error = $error ?: 'The code expired. Please sign in again.';
    } elseif ($step === 'code') {
        // Step 2: the code sent by email
        $admin = q_one('SELECT * FROM admins WHERE id = ?', [(int) $pending['id']]);
        if (($_POST['resend'] ?? '') === '1') {
            if (time() - $pending['sent'] < 60) {
                $error = 'Please wait a minute before asking for a new code.';
            } elseif ($admin && admin_send_login_code($admin)) {
                $info = 'A new code was sent to your email.';
            } else {
                $error = 'The email could not be sent: ' . mail_last_error();
            }
        } else {
            $_SESSION['admin_2fa']['tries']++;
            $code = preg_replace('/\D+/', '', (string) ($_POST['code'] ?? ''));
            if ($admin && $_SESSION['admin_2fa']['tries'] <= 5 && password_verify($code, $pending['hash'])) {
                if (!empty($_POST['remember'])) {
                    admin_device_remember($admin);
                }
                admin_finish_login($admin);
            }
            if ($_SESSION['admin_2fa']['tries'] >= 5) {
                unset($_SESSION['admin_2fa']);
                log_activity('Login blocked: wrong code 5 times', (string) ($admin['email'] ?? ''), $admin);
                $error = 'Too many wrong codes. Please sign in again.';
            } else {
                $error = 'Wrong code. Check the latest email and try again.';
            }
        }
    } elseif (count($_SESSION['login_attempts']) >= 5) {
        $error = 'Too many attempts. Please wait 10 minutes and try again.';
    } else {
        // Step 1: email + password
        $admin = q_one('SELECT * FROM admins WHERE email = ?', [strtolower(trim((string) ($_POST['email'] ?? '')))]);
        if ($admin && password_verify((string) ($_POST['password'] ?? ''), $admin['password'])) {
            if (!admin_2fa_required() || admin_device_trusted($admin)) {
                admin_finish_login($admin);
            }
            if (admin_send_login_code($admin)) {
                redirect('admin/index.php');
            }
            unset($_SESSION['admin_2fa']);
            $error = 'Two-step login is on, but the code email could not be sent (' . mail_last_error() . '). Fix the email settings, or turn two-step login off in config.php (see README).';
        } else {
            $_SESSION['login_attempts'][] = time();
            if ($admin) {
                log_activity('Failed login (wrong password)', (string) $admin['email'], $admin);
            }
            $error = 'Wrong email or password.';
        }
    }
    $pending = $_SESSION['admin_2fa'] ?? null;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin login · <?= e(setting('store_name', 'Store')) ?></title>
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>?v=<?= APP_VERSION ?>">
</head>
<body class="login-page">
  <form method="post" class="login-card">
    <div class="brand dark"><?= e(setting('store_name', 'Store')) ?><span>admin</span></div>
    <p class="muted">Sign in to manage your store</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($info): ?><div class="alert alert-success"><?= e($info) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <?php if ($pending): ?>
      <input type="hidden" name="step" value="code">
      <p>We sent a 6-digit code to your email. Enter it to finish signing in.</p>
      <label>Login code<input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus placeholder="123456" style="font-size:22px;letter-spacing:6px;text-align:center"></label>
      <label class="inline"><input type="checkbox" name="remember" value="1" checked> Remember this device for 30 days</label>
      <button class="btn btn-primary btn-block" type="submit">Verify</button>
      <p class="small center"><button class="link-btn" type="submit" name="resend" value="1" formnovalidate>Send a new code</button> · <a href="?cancel=1">Cancel</a></p>
    <?php else: ?>
      <label>Email<input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>"></label>
      <label>Password<input type="password" name="password" required></label>
      <button class="btn btn-primary btn-block" type="submit">Sign in</button>
    <?php endif; ?>
    <a class="muted small" href="<?= url() ?>">← Back to store</a>
  </form>
</body>
</html>
