<?php
define('ADMIN_PUBLIC_PAGE', true);
require __DIR__ . '/includes/auth.php';

if (current_admin()) {
    redirect('admin/index.php');
}

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(post('email'));
    $attempts = $_SESSION['login_attempts'] ?? ['n' => 0, 't' => time()];
    if (time() - $attempts['t'] > 900) {
        $attempts = ['n' => 0, 't' => time()];
    }
    if (!verify_csrf()) {
        $error = 'Security token expired. Please try again.';
    } elseif ($attempts['n'] >= 5) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $user = db_one('SELECT * FROM ' . tbl('users') . ' WHERE email = ?', [$email]);
        if ($user && password_verify((string) ($_POST['password'] ?? ''), $user['password'])) {
            session_regenerate_id(true);
            unset($_SESSION['login_attempts']);
            $_SESSION['admin_id'] = (int) $user['id'];
            $_SESSION['admin_seen'] = time();
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                db_update('users', ['password' => password_hash((string) $_POST['password'], PASSWORD_DEFAULT)], (int) $user['id']);
            }
            db_update('users', ['last_login' => now()], (int) $user['id']);
            $return = $_SESSION['admin_return'] ?? '';
            unset($_SESSION['admin_return']);
            redirect($return && strpos($return, '/admin/') !== false && strpos($return, '//') !== 0 ? $return : 'admin/index.php');
        }
        $attempts['n']++;
        $_SESSION['login_attempts'] = $attempts;
        usleep(400000);
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login · <?= e(setting('site_name', 'Travel CMS')) ?></title>
    <meta name="robots" content="noindex">
    <?php require APP_ROOT . '/includes/tailwind.php'; ?>
</head>
<body class="min-h-screen font-sans relative flex items-center justify-center p-4">
    <div class="absolute inset-0">
        <img src="<?= e(img_url(setting('hero_image', 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=2000&q=80'))) ?>" alt="" class="w-full h-full object-cover">
        <div class="absolute inset-0 hero-gradient"></div>
    </div>
    <div class="relative w-full max-w-md">
        <div class="text-center mb-6">
            <div class="inline-flex items-center space-x-2">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-brand-600 to-accent-500 flex items-center justify-center text-white text-xl shadow-lg"><i class="fa-solid fa-compass"></i></div>
                <span class="font-serif font-bold text-3xl text-white"><?= e(setting('logo_text_1', 'Wander')) ?><span class="text-accent-400"><?= e(setting('logo_text_2', 'Luxe')) ?></span></span>
            </div>
        </div>
        <form method="post" class="glass-panel rounded-3xl p-8 shadow-2xl space-y-4">
            <?= csrf_field() ?>
            <h1 class="font-serif text-2xl font-bold text-slate-900">Admin Login</h1>
            <?php foreach (get_flashes() as $f): ?><p class="text-sm text-rose-600"><?= e($f['message']) ?></p><?php endforeach; ?>
            <?php if ($error): ?><p class="text-sm bg-rose-50 text-rose-700 border border-rose-200 rounded-xl px-3 py-2"><i class="fa-solid fa-circle-exclamation mr-1"></i><?= e($error) ?></p><?php endif; ?>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Email</label>
                <input type="email" name="email" value="<?= e($email) ?>" required autofocus class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Password</label>
                <input type="password" name="password" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <button class="w-full bg-gradient-to-r from-brand-600 to-brand-500 hover:from-brand-700 text-white font-bold py-3 rounded-xl shadow-lg"><i class="fa-solid fa-right-to-bracket mr-1"></i> Sign In</button>
            <a href="<?= e(url()) ?>" class="block text-center text-xs text-slate-500 hover:text-brand-600">&larr; Back to website</a>
        </form>
    </div>
</body>
</html>
