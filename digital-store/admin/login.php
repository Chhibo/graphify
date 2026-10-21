<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require_installed();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    // Slow down password guessing.
    $_SESSION['login_fails'] = $_SESSION['login_fails'] ?? 0;
    if ($_SESSION['login_fails'] >= 5) {
        sleep(2);
    }
    $userOk = hash_equals(setting('admin_user'), (string)($_POST['username'] ?? ''));
    $passOk = password_verify((string)($_POST['password'] ?? ''), setting('admin_pass'));
    if ($userOk && $passOk) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        $_SESSION['login_fails'] = 0;
        redirect('admin/');
    }
    $_SESSION['login_fails']++;
    $error = 'Wrong username or password.';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Admin login</title>
<link rel="stylesheet" href="../assets/admin.css?v=3">
</head>
<body class="auth-body">
<main class="auth-card">
  <span class="stat-icon"><?= icon('gift', 22) ?></span>
  <h1>Welcome back</h1>
  <p class="muted" style="margin-top:0">Log in to manage <?= e(setting('store_name')) ?>.</p>
  <?php if ($error): ?><?= '<div class="notice notice-bad">' . icon('x') . ' ' . e($error) . '</div>' ?><?php endif; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Username <input name="username" required autofocus autocomplete="username"></label>
    <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn-grad">Log in</button>
  </form>
  <p class="muted" style="text-align:center;margin-bottom:0"><a href="../">← Back to the store</a></p>
</main>
</body>
</html>
