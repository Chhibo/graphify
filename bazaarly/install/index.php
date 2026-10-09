<?php
/**
 * Bazaarly web installer.
 * Open https://your-site.com/install/ in a browser and follow the steps.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.0.0');

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('Bazaarly needs PHP 8.1 or newer. This server runs PHP ' . PHP_VERSION . '. Please switch the PHP version in your hosting control panel.');
}

require APP_ROOT . '/includes/functions.php';
require APP_ROOT . '/includes/icons.php';
require APP_ROOT . '/includes/schema.php';
require APP_ROOT . '/includes/demo.php';

define('BASE_PATH', detect_base_path());
date_default_timezone_set(@date_default_timezone_get() ?: 'UTC');
ini_set('display_errors', '0');

$installed = is_file(APP_ROOT . '/config.php');

/* ---------------------------------------------------------- requirements */
function writable_check(string $rel): bool
{
    $path = APP_ROOT . ($rel === '' ? '' : '/' . $rel);
    if (!is_dir($path)) {
        @mkdir($path, 0775, true);
    }
    return is_dir($path) && is_writable($path);
}

$checks = [
    ['PHP 8.1 or newer', true, 'You have PHP ' . PHP_VERSION, true],
    ['PDO database extension', extension_loaded('pdo'), 'Required to talk to the database', true],
    ['SQLite or MySQL driver', extension_loaded('pdo_sqlite') || extension_loaded('pdo_mysql'),
        trim((extension_loaded('pdo_sqlite') ? 'SQLite ✓ ' : '') . (extension_loaded('pdo_mysql') ? 'MySQL ✓' : '')) ?: 'Enable pdo_sqlite or pdo_mysql', true],
    ['mbstring extension', extension_loaded('mbstring'), 'Needed for multi-language text', true],
    ['GD image extension', extension_loaded('gd'), 'Recommended: resizes & compresses photos', false],
    ['Main folder is writable', writable_check(''), 'Needed once to create config.php', false],
    ['uploads/ folder is writable', writable_check('uploads'), 'Photos are stored here', true],
    ['data/ folder is writable', writable_check('data'), 'Database & sessions are stored here', true],
];
$canInstall = !in_array(false, array_map(fn($c) => !$c[3] || $c[1], $checks), true);

/* -------------------------------------------------------------- process */
$errors = [];
$done = false;
$manualConfig = '';
$in = [
    'db_driver' => extension_loaded('pdo_sqlite') ? 'sqlite' : 'mysql',
    'db_host' => 'localhost', 'db_port' => '3306', 'db_name' => '', 'db_user' => '', 'db_pass' => '',
    'site_name' => 'Bazaarly', 'currency_symbol' => '$',
    'admin_name' => '', 'admin_username' => 'admin', 'admin_email' => '', 'demo' => '1',
];

if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST' && $canInstall) {
    foreach ($in as $k => $_) {
        $in[$k] = trim((string) ($_POST[$k] ?? ($k === 'demo' ? '' : $in[$k])));
    }
    $in['db_pass'] = (string) ($_POST['db_pass'] ?? '');
    $pw = (string) ($_POST['admin_password'] ?? '');
    $in['admin_email'] = strtolower($in['admin_email']);

    if (!in_array($in['db_driver'], ['sqlite', 'mysql'], true)) $errors[] = 'Choose a database type.';
    if ($in['db_driver'] === 'sqlite' && !extension_loaded('pdo_sqlite')) $errors[] = 'SQLite is not available on this server. Choose MySQL.';
    if ($in['db_driver'] === 'mysql' && !extension_loaded('pdo_mysql')) $errors[] = 'MySQL support (pdo_mysql) is not available on this server.';
    if ($in['db_driver'] === 'mysql' && ($in['db_name'] === '' || $in['db_user'] === '')) $errors[] = 'Enter the MySQL database name and username.';
    if ($in['site_name'] === '') $errors[] = 'Enter a name for your website.';
    if (mb_strlen($in['admin_name']) < 2) $errors[] = 'Enter the administrator\'s name.';
    if (!preg_match('/^[a-zA-Z0-9_.]{3,30}$/', $in['admin_username'])) $errors[] = 'Admin username: use 3–30 letters, numbers, dots or underscores.';
    if (!filter_var($in['admin_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid admin email address.';
    if (strlen($pw) < 8) $errors[] = 'The admin password must be at least 8 characters.';
    elseif ($pw !== (string) ($_POST['admin_password_confirm'] ?? '')) $errors[] = 'The two admin passwords do not match.';

    if (!$errors) {
        if ($in['db_driver'] === 'sqlite') {
            $dbConf = ['driver' => 'sqlite', 'path' => 'data/bazaarly_' . bin2hex(random_bytes(8)) . '.sqlite'];
        } else {
            $dbConf = ['driver' => 'mysql', 'host' => $in['db_host'] ?: 'localhost', 'port' => (int) ($in['db_port'] ?: 3306),
                'name' => $in['db_name'], 'user' => $in['db_user'], 'pass' => $in['db_pass']];
        }
        try {
            try {
                $pdo = db_connect($dbConf);
            } catch (PDOException $ex) {
                // Unknown database: try to create it (works on local servers like XAMPP/MAMP)
                if ($dbConf['driver'] === 'mysql' && str_contains($ex->getMessage(), '1049')) {
                    $tmp = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $dbConf['host'], $dbConf['port']), $dbConf['user'], $dbConf['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $tmp->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $dbConf['name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                    $pdo = db_connect($dbConf);
                } else {
                    throw $ex;
                }
            }
            $GLOBALS['config'] = ['db' => $dbConf];

            $hasUsers = false;
            try {
                $hasUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
            } catch (Throwable) {
            }
            if ($hasUsers) {
                throw new RuntimeException('This database already contains a Bazaarly installation. Use an empty database, or delete the existing tables first.');
            }

            foreach (schema_statements($dbConf['driver']) as $sql) {
                $pdo->exec($sql);
            }
            save_settings([
                'site_name' => $in['site_name'],
                'currency_symbol' => $in['currency_symbol'] ?: '$',
                'contact_email' => $in['admin_email'],
                'timezone' => date_default_timezone_get(),
            ]);
            db_insert('users', [
                'name' => $in['admin_name'], 'username' => $in['admin_username'], 'email' => $in['admin_email'],
                'password' => password_hash($pw, PASSWORD_DEFAULT), 'phone' => '', 'location' => '', 'bio' => '',
                'avatar' => '', 'role' => 'admin', 'status' => 'active', 'verified' => 1, 'created_at' => now(),
            ]);
            $catIds = install_default_categories(db());
            install_default_pages(db());
            if ($in['demo'] === '1') {
                install_demo_content(db(), $catIds);
            }

            $config = "<?php\n// Generated by the Bazaarly installer on " . date('Y-m-d H:i') . ".\n// Keep this file private. Delete it to run the installer again.\nreturn " . var_export([
                'db' => $dbConf,
                'app_key' => bin2hex(random_bytes(16)),
                'debug' => false,
            ], true) . ";\n";
            if (@file_put_contents(APP_ROOT . '/config.php', $config, LOCK_EX) === false) {
                $manualConfig = $config;
            } else {
                @chmod(APP_ROOT . '/config.php', 0640);
            }
            $done = true;
        } catch (Throwable $ex) {
            $msg = $ex->getMessage();
            if (str_contains($msg, '1045')) $msg = 'MySQL refused the username or password. Double-check them in your hosting panel.';
            elseif (str_contains($msg, '2002')) $msg = 'Could not reach the MySQL server. "localhost" is correct on most hosts — check the host and port.';
            $errors[] = 'Database error: ' . $msg;
            if (($dbConf['driver'] ?? '') === 'sqlite' && isset($dbConf['path'])) {
                @unlink(APP_ROOT . '/' . $dbConf['path']);
            }
        }
    }
}
$step = $done ? 3 : (($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['setup'])) && $canInstall ? 2 : 1);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Install Bazaarly</title>
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script>(function(){var d=document.documentElement;d.dataset.theme=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'})();</script>
</head>
<body class="install-body">
<?= icon_sprite() ?>
<main class="install-wrap">
  <div class="install-brand"><span class="logo-mark"><?= icon('store') ?></span><span>Bazaarly <small>installer</small></span></div>

  <?php if ($installed && !$done): ?>
    <div class="card install-card center">
      <span class="auth-icon"><?= icon('check-circle') ?></span>
      <h1>Already installed</h1>
      <p class="muted">Bazaarly is already set up on this server. For security, delete the <code>install</code> folder.<br>To start over, delete <code>config.php</code> and reload this page.</p>
      <div class="btn-row center"><a class="btn btn-primary" href="<?= e(url()) ?>">Open website</a><a class="btn btn-ghost" href="<?= e(url('admin/')) ?>">Admin panel</a></div>
    </div>
  <?php else: ?>

  <ol class="install-steps">
    <li class="<?= $step >= 1 ? 'on' : '' ?>"><span>1</span>Server check</li>
    <li class="<?= $step >= 2 ? 'on' : '' ?>"><span>2</span>Setup</li>
    <li class="<?= $step >= 3 ? 'on' : '' ?>"><span>3</span>Done</li>
  </ol>

  <?php if ($step === 1): ?>
    <div class="card install-card">
      <h1>Welcome! 👋</h1>
      <p class="muted">This wizard will set up your classifieds website in about a minute. First, let's make sure your server is ready.</p>
      <ul class="req-list">
        <?php foreach ($checks as [$label, $ok, $note, $required]): ?>
          <li class="<?= $ok ? 'ok' : ($required ? 'bad' : 'warn') ?>">
            <span class="req-icon"><?= icon($ok ? 'check' : ($required ? 'x' : 'alert')) ?></span>
            <span><strong><?= e($label) ?></strong><small><?= e($note) ?><?= !$ok && !$required ? ' (optional)' : '' ?></small></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($canInstall): ?>
        <a class="btn btn-primary btn-lg btn-block" href="?setup=1">Continue<?= icon('arrow-right') ?></a>
      <?php else: ?>
        <div class="alert alert-error"><?= icon('alert') ?><span>Please fix the red items above, then reload this page. On most hosts you can change folder permissions to 755 (or 775) in the File Manager, and enable PHP extensions under “Select PHP version”.</span></div>
        <a class="btn btn-ghost btn-block" href="">Check again</a>
      <?php endif; ?>
    </div>

  <?php elseif ($step === 2): ?>
    <form method="post" class="card install-card" autocomplete="off">
      <h1>Set up your website</h1>
      <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= icon('alert') ?><span><?= e($er) ?></span></div><?php endforeach; ?>

      <h2 class="h-sm">1. Database</h2>
      <div class="db-choice">
        <label class="choice-card"><input type="radio" name="db_driver" value="sqlite" <?= $in['db_driver'] === 'sqlite' ? 'checked' : '' ?> <?= extension_loaded('pdo_sqlite') ? '' : 'disabled' ?> data-db-choice>
          <span><strong><?= icon('zap') ?>Easy (SQLite)</strong><small>Recommended for beginners. Nothing to configure — the database is a file inside the <code>data</code> folder.</small></span></label>
        <label class="choice-card"><input type="radio" name="db_driver" value="mysql" <?= $in['db_driver'] === 'mysql' ? 'checked' : '' ?> <?= extension_loaded('pdo_mysql') ? '' : 'disabled' ?> data-db-choice>
          <span><strong><?= icon('building') ?>MySQL / MariaDB</strong><small>Best for big sites. Create a database in your hosting panel (cPanel → MySQL Databases) first.</small></span></label>
      </div>
      <div class="mysql-fields" data-mysql-fields <?= $in['db_driver'] === 'mysql' ? '' : 'hidden' ?>>
        <div class="form-grid">
          <div class="field"><label for="db_host">Database host</label><input id="db_host" name="db_host" value="<?= e($in['db_host']) ?>"><small class="help">Usually “localhost”.</small></div>
          <div class="field"><label for="db_port">Port</label><input id="db_port" name="db_port" value="<?= e($in['db_port']) ?>"></div>
          <div class="field"><label for="db_name">Database name</label><input id="db_name" name="db_name" value="<?= e($in['db_name']) ?>"></div>
          <div class="field"><label for="db_user">Database username</label><input id="db_user" name="db_user" value="<?= e($in['db_user']) ?>"></div>
        </div>
        <div class="field"><label for="db_pass">Database password</label><input id="db_pass" type="password" name="db_pass" value="<?= e($in['db_pass']) ?>"></div>
      </div>

      <h2 class="h-sm">2. Your website</h2>
      <div class="form-grid">
        <div class="field"><label for="site_name">Website name</label><input id="site_name" name="site_name" value="<?= e($in['site_name']) ?>" required></div>
        <div class="field"><label for="currency_symbol">Currency symbol</label><input id="currency_symbol" name="currency_symbol" value="<?= e($in['currency_symbol']) ?>" maxlength="5"><small class="help">e.g. $, €, £, ৳, ₹, AED</small></div>
      </div>

      <h2 class="h-sm">3. Administrator account</h2>
      <div class="form-grid">
        <div class="field"><label for="admin_name">Your name</label><input id="admin_name" name="admin_name" value="<?= e($in['admin_name']) ?>" required></div>
        <div class="field"><label for="admin_username">Username</label><input id="admin_username" name="admin_username" value="<?= e($in['admin_username']) ?>" required></div>
      </div>
      <div class="field"><label for="admin_email">Email</label><input id="admin_email" type="email" name="admin_email" value="<?= e($in['admin_email']) ?>" required></div>
      <div class="form-grid">
        <div class="field"><label for="admin_password">Password</label><input id="admin_password" type="password" name="admin_password" minlength="8" required autocomplete="new-password"><small class="help">At least 8 characters.</small></div>
        <div class="field"><label for="admin_password_confirm">Confirm password</label><input id="admin_password_confirm" type="password" name="admin_password_confirm" required autocomplete="new-password"></div>
      </div>

      <label class="switch"><input type="checkbox" name="demo" value="1" <?= $in['demo'] === '1' ? 'checked' : '' ?>><span class="switch-ui"></span><span>Add sample ads so the site doesn't look empty <small class="muted">(you can delete them later)</small></span></label>

      <button class="btn btn-primary btn-lg btn-block" type="submit" data-loading="Installing…"><?= icon('zap') ?>Install now</button>
    </form>

  <?php else: ?>
    <div class="card install-card center">
      <span class="auth-icon success"><?= icon('check-circle') ?></span>
      <h1>All done! 🎉</h1>
      <p class="muted">Your classifieds website is ready. Sign in with the admin account you just created.</p>
      <?php if ($manualConfig): ?>
        <div class="alert alert-warning"><?= icon('alert') ?><span>We couldn't write <code>config.php</code> automatically. Create a file called <strong>config.php</strong> in the main folder with exactly this content:</span></div>
        <textarea class="mono" rows="14" readonly onclick="this.select()"><?= e($manualConfig) ?></textarea>
      <?php endif; ?>
      <div class="alert alert-info"><?= icon('shield') ?><span>For security, <strong>delete the <code>install</code> folder</strong> from your server now.</span></div>
      <div class="btn-row center">
        <a class="btn btn-primary btn-lg" href="<?= e(url('login.php')) ?>"><?= icon('log-in') ?>Sign in to admin</a>
        <a class="btn btn-ghost btn-lg" href="<?= e(url()) ?>">View website</a>
      </div>
    </div>
  <?php endif; ?>
  <?php endif; ?>
  <p class="install-foot muted small">Bazaarly v<?= APP_VERSION ?></p>
</main>
<script>
document.querySelectorAll('[data-db-choice]').forEach(function (r) {
  r.addEventListener('change', function () {
    document.querySelector('[data-mysql-fields]').hidden = this.value !== 'mysql';
  });
});
document.querySelectorAll('[data-loading]').forEach(function (b) {
  b.form && b.form.addEventListener('submit', function () { b.disabled = true; b.textContent = b.dataset.loading; });
});
</script>
</body>
</html>
