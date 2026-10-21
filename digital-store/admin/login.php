<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require_installed();

$error = '';
$ip = client_ip();
const LOGIN_MAX_FAILS = 5;
const LOGIN_LOCK_MINUTES = 15;

$fails = function () use ($ip): int {
    $q = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > ?');
    $q->execute([$ip, time() - LOGIN_LOCK_MINUTES * 60]);
    return (int)$q->fetchColumn();
};
// Lost your phone? Upload an empty file named "disable-2fa" into data/ to switch two-step login off.
if (is_file(DATA_DIR . '/disable-2fa')) {
    save_setting('totp_secret', '');
    @unlink(DATA_DIR . '/disable-2fa');
}
$step = empty($_SESSION['login_pending']) ? 'password' : 'code';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (($_POST['action'] ?? '') === 'cancel') {
        unset($_SESSION['login_pending']);
        redirect('admin/login.php');
    }
    if ($fails() >= LOGIN_MAX_FAILS) {
        $error = 'Too many failed attempts. Try again in ' . LOGIN_LOCK_MINUTES . ' minutes.';
    } elseif ($step === 'password') {
        $userOk = hash_equals(setting('admin_user'), (string)($_POST['username'] ?? ''));
        $passOk = password_verify((string)($_POST['password'] ?? ''), setting('admin_pass'));
        if ($userOk && $passOk) {
            if (setting('totp_secret') !== '') {
                $_SESSION['login_pending'] = time();
                redirect('admin/login.php');
            }
            $finish = true;
        } else {
            $error = 'Wrong username or password.';
        }
    } else {
        if (time() - (int)$_SESSION['login_pending'] > 300) {
            unset($_SESSION['login_pending']);
            $error = 'That took too long. Log in again.';
            $step = 'password';
        } elseif (totp_verify(setting('totp_secret'), (string)($_POST['code'] ?? ''))) {
            $finish = true;
        } else {
            $error = 'Wrong code. Use the newest code from your authenticator app.';
        }
    }
    if (!empty($finish)) {
        db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
        unset($_SESSION['login_pending']);
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        redirect('admin/');
    }
    if ($error !== '' && strpos($error, 'Too many') !== 0) {
        db()->prepare('INSERT INTO login_attempts (ip, created_at) VALUES (?, ?)')->execute([$ip, time()]);
        db()->prepare('DELETE FROM login_attempts WHERE created_at < ?')->execute([time() - 86400]);
        $left = LOGIN_MAX_FAILS - $fails();
        if ($left > 0 && $left <= 2) {
            $error .= " $left attempt" . ($left === 1 ? '' : 's') . ' left before a ' . LOGIN_LOCK_MINUTES . '-minute lock.';
        } elseif ($left <= 0) {
            $error = 'Too many failed attempts. Try again in ' . LOGIN_LOCK_MINUTES . ' minutes.';
        }
    }
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
  <?php if ($step === 'code'): ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>6-digit code from your authenticator app
      <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus></label>
    <button class="btn btn-grad">Verify</button>
    <button class="btn btn-light" name="action" value="cancel" formnovalidate style="width:100%;margin-top:8px">Cancel</button>
  </form>
  <?php else: ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Username <input name="username" required autofocus autocomplete="username"></label>
    <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn-grad">Log in</button>
  </form>
  <?php endif; ?>
  <p class="muted" style="text-align:center;margin-bottom:0"><a href="../">← Back to the store</a></p>
</main>
</body>
</html>
