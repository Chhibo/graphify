<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/networks.php';

$checks = [
    'PHP 7.4 or newer' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO SQLite extension' => extension_loaded('pdo_sqlite'),
    'cURL extension' => extension_loaded('curl'),
    'mbstring extension' => extension_loaded('mbstring'),
    'data/ folder is writable' => is_writable(DATA_DIR),
    'data/files/ folder is writable' => is_writable(FILES_DIR),
    'uploads/ folder is writable' => is_writable(UPLOADS_DIR),
];
$allOk = !in_array(false, $checks, true);
$error = '';
$done = false;

if (is_installed()) {
    $done = true;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $allOk) {
    $store = trim($_POST['store_name'] ?? '');
    $user = trim($_POST['username'] ?? '');
    $pass = (string)($_POST['password'] ?? '');
    if ($store === '' || $user === '' || strlen($pass) < 8) {
        $error = 'Fill in every field. The password needs at least 8 characters.';
    } else {
        $dbFile = 'store-' . random_token(12) . '.sqlite';
        $GLOBALS['installDb'] = new PDO('sqlite:' . DATA_DIR . '/' . $dbFile);
        db(); // creates the tables
        save_setting('store_name', $store);
        save_setting('admin_user', $user);
        save_setting('admin_pass', password_hash($pass, PASSWORD_DEFAULT));
        save_setting('postback_secret', random_token(12));
        save_setting('network_mode', 'priority');
        save_setting('offers_count', '4');
        save_setting('download_hours', '24');
        save_setting('locker_title', 'Complete one offer to unlock your free download');
        foreach (network_definitions() as $net => $def) {
            foreach ($def['fields'] as $field => $f) {
                save_setting("net_{$net}_{$field}", $f['default'] ?? '');
            }
            save_setting("net_{$net}_enabled", '0');
        }
        save_setting('net_ogads_priority', '1');
        save_setting('net_adbluemedia_priority', '2');
        file_put_contents(CONFIG_FILE, "<?php\n// Created by install.php on " . date('c')
            . "\nreturn ['db_file' => " . var_export($dbFile, true) . "];\n");
        $done = true;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install your store</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="admin-body">
<main class="narrow card">
  <h1>Install your store</h1>
  <?php if ($done): ?>
    <p class="ok">Your store is installed.</p>
    <ol>
      <li>For safety, delete <code>install.php</code> from your hosting (it is already locked).</li>
      <li><a href="admin/login.php">Log in to the admin panel</a>, add your API keys under <b>CPA Networks</b> and switch them on.</li>
      <li>Copy each network's postback URL into that network's dashboard.</li>
      <li>Add your first product.</li>
    </ol>
  <?php else: ?>
    <h2>Server check</h2>
    <ul class="checks">
      <?php foreach ($checks as $label => $ok): ?>
        <li class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✔' : '✘' ?> <?= e($label) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php if (!$allOk): ?>
      <p class="bad">Fix the items marked ✘ first. Folders usually need permission 755 or 775 in your hosting File Manager. Missing extensions can be enabled in cPanel → "Select PHP Version".</p>
    <?php else: ?>
      <?php if ($error): ?><p class="bad"><?= e($error) ?></p><?php endif; ?>
      <form method="post">
        <label>Store name <input name="store_name" required value="<?= e($_POST['store_name'] ?? 'My Free Downloads') ?>"></label>
        <label>Admin username <input name="username" required value="<?= e($_POST['username'] ?? 'admin') ?>"></label>
        <label>Admin password (8+ characters) <input type="password" name="password" required minlength="8"></label>
        <button class="btn">Install</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</main>
</body>
</html>
