<?php
/**
 * Mercho - one-click installer.
 * Open https://your-site.com/install/ in your browser and follow the 3 steps.
 */
declare(strict_types=1);

session_name('mercho_install');
session_start();

$root = dirname(__DIR__);
$installed = is_file($root . '/config.php');

$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'))), '/');
if ($basePath === '.' || $basePath === '/') {
    $basePath = '';
}

function h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function old(string $key, string $default = ''): string
{
    return h($_POST[$key] ?? ($_SESSION['install'][$key] ?? $default));
}

$currencies = [
    'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'CAD' => 'C$', 'AUD' => 'A$', 'AED' => 'AED', 'SAR' => 'SAR',
    'QAR' => 'QAR', 'KWD' => 'KWD', 'MAD' => 'DH', 'DZD' => 'DA', 'TND' => 'DT', 'EGP' => 'E£', 'TRY' => '₺',
    'INR' => '₹', 'PKR' => 'Rs', 'NGN' => '₦', 'ZAR' => 'R', 'BRL' => 'R$', 'MXN' => 'MX$', 'JPY' => '¥',
];

$step = $_GET['step'] ?? '1';
if ($installed) {
    $step = ($step === 'done' && isset($_SESSION['install']['admin_email'])) ? 'done' : 'done-already';
}
$errors = [];

/* ---------- Step 1: requirements ---------- */
$checks = [
    ['PHP version 7.4 or newer (you have ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '7.4.0', '>='), true],
    ['PDO extension', extension_loaded('pdo'), true],
    ['MySQL driver (pdo_mysql) or SQLite driver (pdo_sqlite)', extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite'), true],
    ['cURL extension (needed for PayPal, Stripe & automatic WhatsApp)', extension_loaded('curl'), false],
    ['mbstring extension', extension_loaded('mbstring'), true],
    ['GD extension (image uploads)', extension_loaded('gd'), false],
    ['Main folder is writable (to create config.php)', is_writable($root), true],
    ['uploads/ folder is writable', is_writable($root . '/uploads'), true],
    ['data/ folder is writable (only for SQLite)', is_writable($root . '/data'), false],
];
$requirementsOk = !in_array(false, array_map(fn($c) => $c[1] || !$c[2], $checks), true);

/* ---------- Step 2: database ---------- */
if ($step === '2' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = [
        'driver' => ($_POST['driver'] ?? 'mysql') === 'sqlite' ? 'sqlite' : 'mysql',
        'host' => trim($_POST['host'] ?? 'localhost'),
        'port' => (int) ($_POST['port'] ?? 3306) ?: 3306,
        'name' => trim($_POST['name'] ?? ''),
        'user' => trim($_POST['user'] ?? ''),
        'pass' => (string) ($_POST['pass'] ?? ''),
    ];
    if ($db['driver'] === 'sqlite') {
        $db['name'] = 'store-' . bin2hex(random_bytes(6)) . '.sqlite';
        if (!extension_loaded('pdo_sqlite')) {
            $errors[] = 'SQLite is not available on this server. Please use MySQL.';
        } elseif (!is_writable($root . '/data')) {
            $errors[] = 'The data/ folder must be writable for SQLite (set permission 755 or 775).';
        }
    } elseif ($db['name'] === '' || $db['user'] === '') {
        $errors[] = 'Please fill in the database name and username.';
    } else {
        try {
            new PDO('mysql:host=' . $db['host'] . ';port=' . $db['port'] . ';dbname=' . $db['name'] . ';charset=utf8mb4', $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (Throwable $ex) {
            $errors[] = 'Could not connect to the database: ' . $ex->getMessage();
        }
    }
    if (!$errors) {
        $_SESSION['install']['db'] = $db;
        header('Location: ?step=3');
        exit;
    }
}

/* ---------- Step 3: store + admin, then install ---------- */
if ($step === '3' && empty($_SESSION['install']['db'])) {
    header('Location: ?step=2');
    exit;
}
if ($step === '3' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $store = [
        'store_name' => trim($_POST['store_name'] ?? ''),
        'store_email' => trim($_POST['store_email'] ?? ''),
        'currency_code' => array_key_exists($_POST['currency'] ?? '', $currencies) ? $_POST['currency'] : 'USD',
        'whatsapp' => preg_replace('/\D+/', '', $_POST['whatsapp'] ?? ''),
    ];
    $store['currency_symbol'] = $currencies[$store['currency_code']];
    $admin = [
        'name' => trim($_POST['admin_name'] ?? ''),
        'email' => trim($_POST['admin_email'] ?? ''),
        'pass' => (string) ($_POST['admin_pass'] ?? ''),
    ];
    $timezone = in_array($_POST['timezone'] ?? '', timezone_identifiers_list(), true) ? $_POST['timezone'] : 'UTC';
    $demo = !empty($_POST['demo']);

    if ($store['store_name'] === '') {
        $errors[] = 'Please enter your store name.';
    }
    if (!filter_var($admin['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid admin email.';
    }
    if (strlen($admin['pass']) < 8) {
        $errors[] = 'Admin password must be at least 8 characters.';
    }
    if ($admin['name'] === '') {
        $admin['name'] = 'Admin';
    }

    if (!$errors) {
        $db = $_SESSION['install']['db'];
        try {
            require dirname(__DIR__) . '/includes/schema.php';
            require __DIR__ . '/seed.php';
            date_default_timezone_set($timezone);

            if ($db['driver'] === 'sqlite') {
                $pdo = new PDO('sqlite:' . $root . '/data/' . $db['name']);
                $pdo->exec('PRAGMA foreign_keys = ON');
            } else {
                $pdo = new PDO('mysql:host=' . $db['host'] . ';port=' . $db['port'] . ';dbname=' . $db['name'] . ';charset=utf8mb4', $db['user'], $db['pass']);
            }
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            foreach (schema_statements($db['driver']) as $sql) {
                $pdo->exec($sql);
            }
            // Fresh install: clear tables in case a previous attempt stopped half way.
            foreach (['order_items', 'orders', 'customers', 'blocklist', 'carts', 'reviews', 'product_variants', 'products', 'coupons', 'messages', 'shipping_methods', 'categories', 'testimonials', 'pages', 'subscribers', 'admins', 'settings'] as $t) {
                $pdo->exec('DELETE FROM ' . $t);
            }

            $st = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)');
            foreach (default_settings($store) as $k => $v) {
                $st->execute([$k, (string) $v]);
            }
            $pdo->prepare('INSERT INTO admins (name, email, password, created_at) VALUES (?,?,?,?)')
                ->execute([$admin['name'], strtolower($admin['email']), password_hash($admin['pass'], PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);

            if ($demo) {
                seed_demo($pdo);
            }

            $config = "<?php\n"
                . "// Generated by the Mercho installer on " . date('Y-m-d H:i') . "\n"
                . "define('DB_DRIVER', " . var_export($db['driver'], true) . ");\n"
                . "define('DB_HOST', " . var_export($db['host'], true) . ");\n"
                . "define('DB_PORT', " . var_export((int) $db['port'], true) . ");\n"
                . "define('DB_NAME', " . var_export($db['name'], true) . ");\n"
                . "define('DB_USER', " . var_export($db['user'], true) . ");\n"
                . "define('DB_PASS', " . var_export($db['pass'], true) . ");\n"
                . "// Folder of the store relative to your domain ('' = domain root, '/shop' = sub folder)\n"
                . "define('BASE_PATH', " . var_export($basePath, true) . ");\n"
                . "define('APP_TIMEZONE', " . var_export($timezone, true) . ");\n"
                . "define('APP_DEBUG', false);\n";
            if (file_put_contents($root . '/config.php', $config) === false) {
                throw new RuntimeException('Could not write config.php. Make the main folder writable and try again.');
            }
            @file_put_contents(__DIR__ . '/installed.lock', date('c'));
            $_SESSION['install'] = ['admin_email' => $admin['email']];
            header('Location: ?step=done');
            exit;
        } catch (Throwable $ex) {
            $errors[] = 'Installation failed: ' . $ex->getMessage();
        }
    }
}
$progress = ['1' => 1, '2' => 2, '3' => 3, 'done' => 4, 'done-already' => 4][$step] ?? 1;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install Mercho</title>
<style>
:root{--g:#e03a3e;--g2:#c22f33;--bg:#f8f1ee;--line:#ebe3e1;--text:#1b1f1d;--muted:#66706b;--red:#c0392b}
*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:var(--bg);color:var(--text)}
.wrap{max-width:640px;margin:40px auto;padding:0 16px}
.logo{font-weight:900;font-size:28px;letter-spacing:-.5px;text-align:center;margin-bottom:6px}.logo span{color:var(--g)}
.sub{text-align:center;color:var(--muted);margin:0 0 24px}
.steps{display:flex;gap:8px;margin-bottom:20px}.steps div{flex:1;height:6px;border-radius:9px;background:var(--line)}.steps div.on{background:var(--g)}
.card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:28px;box-shadow:0 10px 30px rgba(0,0,0,.04)}
h2{margin:0 0 6px;font-size:22px}p.hint{color:var(--muted);margin:0 0 20px;font-size:14px;line-height:1.5}
label{display:block;font-weight:600;font-size:14px;margin:14px 0 6px}
input,select{width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:10px;font:inherit;background:#fafbfa}
input:focus,select:focus{outline:2px solid var(--g);border-color:transparent}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}@media(max-width:560px){.row{grid-template-columns:1fr}}
.btn{display:inline-block;margin-top:22px;background:var(--g);color:#fff;border:0;border-radius:999px;padding:13px 28px;font-weight:700;font-size:15px;cursor:pointer;text-decoration:none}
.btn:hover{background:var(--g2)}.btn.light{background:#fff;color:var(--g);border:1px solid var(--g)}
.check{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid var(--line);font-size:14px}
.ok{color:var(--g);font-weight:700}.bad{color:var(--red);font-weight:700}.warn{color:#b9770e;font-weight:700}
.err{background:#fdecea;color:var(--red);padding:12px 14px;border-radius:10px;margin-bottom:12px;font-size:14px}
.choice{display:flex;gap:12px;margin-top:8px}.choice label{flex:1;margin:0;border:1px solid var(--line);border-radius:12px;padding:14px;cursor:pointer;font-weight:500}
.choice input{width:auto;margin-right:8px}.choice small{display:block;color:var(--muted);margin-top:4px}
.cb{display:flex;align-items:center;gap:10px;font-weight:500}.cb input{width:auto}
.success{text-align:center}.success .big{font-size:56px}
.box{background:#fff8e6;border:1px solid #f3dfae;border-radius:12px;padding:12px 14px;font-size:14px;text-align:left;margin-top:16px}
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">MERCHO<span>.</span></div>
  <p class="sub">Store installer</p>
  <div class="steps"><?php for ($i = 1; $i <= 4; $i++): ?><div class="<?= $i <= $progress ? 'on' : '' ?>"></div><?php endfor; ?></div>
  <div class="card">
  <?php foreach ($errors as $err): ?><div class="err"><?= h($err) ?></div><?php endforeach; ?>

  <?php if ($step === 'done-already'): ?>
    <div class="success">
      <div class="big">✅</div>
      <h2>The store is already installed</h2>
      <p class="hint">To reinstall, delete the file <b>config.php</b> from your store folder, then open this page again.<br>For security, you can delete the <b>install</b> folder now.</p>
      <a class="btn" href="<?= h($basePath) ?>/">Open store</a> <a class="btn light" href="<?= h($basePath) ?>/admin/">Admin panel</a>
    </div>

  <?php elseif ($step === '1'): ?>
    <h2>Step 1 · Server check</h2>
    <p class="hint">We check that your hosting has everything the store needs.</p>
    <?php foreach ($checks as [$label, $ok, $required]): ?>
      <div class="check"><span><?= h($label) ?></span>
        <?php if ($ok): ?><span class="ok">✓ OK</span><?php elseif ($required): ?><span class="bad">✗ Required</span><?php else: ?><span class="warn">! Recommended</span><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if ($requirementsOk): ?>
      <a class="btn" href="?step=2">Continue →</a>
    <?php else: ?>
      <p class="hint" style="margin-top:16px">Please fix the items marked <b>Required</b> (ask your hosting support, or change folder permissions to 755) and refresh this page.</p>
      <a class="btn" href="?step=1">Check again</a>
    <?php endif; ?>

  <?php elseif ($step === '2'): ?>
    <h2>Step 2 · Database</h2>
    <p class="hint">Create an empty MySQL database in your hosting panel (cPanel → MySQL Databases), then enter its details here.
      No database? Choose <b>SQLite</b> and the store creates its own database file automatically.</p>
    <form method="post">
      <?php $drv = $_POST['driver'] ?? (extension_loaded('pdo_mysql') ? 'mysql' : 'sqlite'); ?>
      <div class="choice">
        <label><input type="radio" name="driver" value="mysql" <?= $drv === 'mysql' ? 'checked' : '' ?> <?= extension_loaded('pdo_mysql') ? '' : 'disabled' ?>> MySQL / MariaDB <small>Recommended for live stores</small></label>
        <label><input type="radio" name="driver" value="sqlite" <?= $drv === 'sqlite' ? 'checked' : '' ?> <?= extension_loaded('pdo_sqlite') ? '' : 'disabled' ?>> SQLite <small>Zero setup, good for small stores</small></label>
      </div>
      <div id="mysql-fields">
        <div class="row">
          <div><label>Database host</label><input name="host" value="<?= old('host', 'localhost') ?>"></div>
          <div><label>Port</label><input name="port" value="<?= old('port', '3306') ?>"></div>
        </div>
        <label>Database name</label><input name="name" value="<?= old('name') ?>" placeholder="e.g. cpaneluser_store">
        <div class="row">
          <div><label>Database username</label><input name="user" value="<?= old('user') ?>"></div>
          <div><label>Database password</label><input type="password" name="pass" value=""></div>
        </div>
      </div>
      <button class="btn" type="submit">Test connection & continue →</button>
    </form>
    <script>
      (function(){var f=document.getElementById('mysql-fields');function u(){var s=document.querySelector('input[name=driver]:checked');f.style.display=s&&s.value==='sqlite'?'none':'block'}
      document.querySelectorAll('input[name=driver]').forEach(function(r){r.addEventListener('change',u)});u();})();
    </script>

  <?php elseif ($step === '3'): ?>
    <h2>Step 3 · Your store & admin account</h2>
    <p class="hint">You can change all of this later in the admin panel.</p>
    <form method="post">
      <label>Store name</label><input name="store_name" value="<?= old('store_name', 'MERCHO') ?>" required>
      <div class="row">
        <div><label>Store email</label><input type="email" name="store_email" value="<?= old('store_email') ?>"></div>
        <div><label>Currency</label>
          <select name="currency"><?php foreach ($currencies as $code => $sym): ?><option value="<?= h($code) ?>" <?= ($_POST['currency'] ?? 'USD') === $code ? 'selected' : '' ?>><?= h($code . ' (' . $sym . ')') ?></option><?php endforeach; ?></select>
        </div>
      </div>
      <label>WhatsApp number for orders <small style="font-weight:400;color:#66706b">(with country code, e.g. 212612345678)</small></label>
      <input name="whatsapp" value="<?= old('whatsapp') ?>" placeholder="212612345678">
      <label>Time zone</label>
      <select name="timezone"><?php $tzSel = $_POST['timezone'] ?? 'UTC'; foreach (timezone_identifiers_list() as $tz): ?><option <?= $tz === $tzSel ? 'selected' : '' ?>><?= h($tz) ?></option><?php endforeach; ?></select>
      <hr style="border:0;border-top:1px solid #e3e8e5;margin:22px 0 4px">
      <label>Admin name</label><input name="admin_name" value="<?= old('admin_name', 'Admin') ?>">
      <div class="row">
        <div><label>Admin email (login)</label><input type="email" name="admin_email" value="<?= old('admin_email') ?>" required></div>
        <div><label>Admin password (min. 8)</label><input type="password" name="admin_pass" minlength="8" required></div>
      </div>
      <label class="cb" style="margin-top:18px"><input type="checkbox" name="demo" value="1" checked> Add demo products, categories & pages (recommended - you can edit or delete them later)</label>
      <button class="btn" type="submit">Install store 🚀</button>
    </form>

  <?php elseif ($step === 'done'): ?>
    <div class="success">
      <div class="big">🎉</div>
      <h2>Your store is ready!</h2>
      <p class="hint">Log in to the admin panel with <b><?= h($_SESSION['install']['admin_email'] ?? 'your admin email') ?></b> to add products and set up payments (PayPal, Stripe, Cash on Delivery) and your WhatsApp number.</p>
      <a class="btn" href="<?= h($basePath) ?>/admin/">Go to admin panel</a> <a class="btn light" href="<?= h($basePath) ?>/">View store</a>
      <div class="box"><b>Important:</b> for security, delete the <b>install</b> folder from your server (File Manager → install → Delete).</div>
    </div>
  <?php endif; ?>
  </div>
</div>
</body>
</html>
