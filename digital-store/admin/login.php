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
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="admin-body">
<main class="narrow card">
  <h1>Admin login</h1>
  <?php if ($error): ?><p class="bad"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Username <input name="username" required autofocus></label>
    <label>Password <input type="password" name="password" required></label>
    <button class="btn">Log in</button>
  </form>
</main>
</body>
</html>
