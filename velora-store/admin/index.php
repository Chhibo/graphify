<?php
require __DIR__ . '/includes/auth.php';

if (current_admin()) {
    redirect('admin/dashboard.php');
}

$error = '';
if (is_post()) {
    verify_csrf();
    $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? [];
    // Allow 5 attempts per 10 minutes per session.
    $_SESSION['login_attempts'] = array_filter($_SESSION['login_attempts'], fn($t) => $t > time() - 600);
    if (count($_SESSION['login_attempts']) >= 5) {
        $error = 'Too many attempts. Please wait 10 minutes and try again.';
    } else {
        $admin = q_one('SELECT * FROM admins WHERE email = ?', [strtolower(trim((string) ($_POST['email'] ?? '')))]);
        if ($admin && password_verify((string) ($_POST['password'] ?? ''), $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['login_attempts'] = [];
            redirect('admin/dashboard.php');
        }
        $_SESSION['login_attempts'][] = time();
        $error = 'Wrong email or password.';
    }
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
    <?= csrf_field() ?>
    <label>Email<input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>"></label>
    <label>Password<input type="password" name="password" required></label>
    <button class="btn btn-primary btn-block" type="submit">Sign in</button>
    <a class="muted small" href="<?= url() ?>">← Back to store</a>
  </form>
</body>
</html>
