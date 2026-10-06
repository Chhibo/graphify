<?php
/**
 * WanderLuxe Travel CMS - Web Installer
 *
 * Step 1: server requirements check
 * Step 2: database, website and administrator details
 * Step 3: done
 */

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.0.0');
$configFile = APP_ROOT . '/config.php';

session_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

function h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function detect_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php')));
    $path = rtrim($path, '/');
    return ($https ? 'https' : 'http') . '://' . $host . $path;
}

$step = $_GET['step'] ?? '1';
$errors = [];
$alreadyInstalled = is_file($configFile);

if (empty($_SESSION['install_token'])) {
    $_SESSION['install_token'] = bin2hex(random_bytes(16));
}

/* ---------------------------------------------------------------------
 * Requirements
 * ------------------------------------------------------------------ */
$requirements = [
    ['PHP version 7.4 or newer (you have ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '7.4.0', '>=')],
    ['PDO MySQL extension', extension_loaded('pdo_mysql')],
    ['Mbstring extension', extension_loaded('mbstring')],
    ['JSON extension', function_exists('json_encode')],
    ['OpenSSL / random_bytes', function_exists('random_bytes')],
    ['Root folder writable (to create config.php)', is_writable(APP_ROOT) || (is_file($configFile) && is_writable($configFile))],
    ['/uploads folder writable', is_dir(APP_ROOT . '/uploads') && is_writable(APP_ROOT . '/uploads')],
];
$requirementsOk = true;
foreach ($requirements as $i => $r) {
    // A non-writable root is a warning only: the config can be copied manually.
    if (!$r[1] && $i !== 5) {
        $requirementsOk = false;
    }
}

$timezones = timezone_identifiers_list();
$form = [
    'db_host' => 'localhost', 'db_port' => '3306', 'db_name' => '', 'db_user' => '', 'db_pass' => '', 'db_prefix' => 'tc_',
    'site_name' => 'WanderLuxe', 'logo_text_1' => 'Wander', 'logo_text_2' => 'Luxe', 'base_url' => detect_base_url(),
    'timezone' => date_default_timezone_get() ?: 'UTC',
    'admin_name' => '', 'admin_email' => '', 'admin_pass' => '', 'admin_pass2' => '', 'demo' => '1',
];
$manualConfig = null;

/* ---------------------------------------------------------------------
 * Handle installation
 * ------------------------------------------------------------------ */
if (!$alreadyInstalled && $step === '2' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $k => $v) {
        $form[$k] = trim((string) ($_POST[$k] ?? ($k === 'demo' ? '' : $v)));
    }
    if (!hash_equals($_SESSION['install_token'], (string) ($_POST['token'] ?? ''))) {
        $errors[] = 'Session expired, please submit the form again.';
    }
    if (!$requirementsOk) {
        $errors[] = 'Your server does not meet the minimum requirements.';
    }
    if ($form['db_host'] === '' || $form['db_name'] === '' || $form['db_user'] === '') {
        $errors[] = 'Database host, name and username are required.';
    }
    if (!preg_match('/^[A-Za-z0-9_]*$/', $form['db_prefix'])) {
        $errors[] = 'Table prefix may only contain letters, numbers and underscores.';
    }
    if ($form['site_name'] === '') {
        $errors[] = 'Website name is required.';
    }
    if (!preg_match('#^https?://#i', $form['base_url'])) {
        $errors[] = 'Website URL must start with http:// or https://';
    }
    if (!in_array($form['timezone'], $timezones, true)) {
        $form['timezone'] = 'UTC';
    }
    if ($form['admin_name'] === '' || !filter_var($form['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Administrator name and a valid email are required.';
    }
    if (strlen($form['admin_pass']) < 8) {
        $errors[] = 'Administrator password must be at least 8 characters.';
    } elseif ($form['admin_pass'] !== $form['admin_pass2']) {
        $errors[] = 'Administrator passwords do not match.';
    }

    $pdo = null;
    if (!$errors) {
        $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
        $port = (int) $form['db_port'] ?: 3306;
        try {
            $pdo = new PDO("mysql:host={$form['db_host']};port={$port};dbname={$form['db_name']};charset=utf8mb4", $form['db_user'], $form['db_pass'], $opts);
        } catch (PDOException $e) {
            // The database may not exist yet: try to create it.
            try {
                $tmp = new PDO("mysql:host={$form['db_host']};port={$port};charset=utf8mb4", $form['db_user'], $form['db_pass'], $opts);
                $tmp->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $form['db_name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $pdo = new PDO("mysql:host={$form['db_host']};port={$port};dbname={$form['db_name']};charset=utf8mb4", $form['db_user'], $form['db_pass'], $opts);
            } catch (PDOException $e2) {
                $errors[] = 'Could not connect to the database: ' . $e->getMessage();
            }
        }
    }

    if ($pdo && !$errors) {
        $prefix = $form['db_prefix'];
        $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote(str_replace('_', '\_', $prefix) . 'users'))->fetchColumn();
        if ($exists) {
            $errors[] = 'The database already contains a Travel CMS installation with the prefix "' . $prefix . '". Use a different prefix or an empty database.';
        }
    }

    if ($pdo && !$errors) {
        try {
            $schema = str_replace('{prefix}', $prefix, file_get_contents(__DIR__ . '/schema.sql'));
            $schema = preg_replace('/^--.*$/m', '', $schema);
            foreach (array_filter(array_map('trim', explode(';', $schema))) as $sql) {
                $pdo->exec($sql);
            }

            require __DIR__ . '/seed.php';
            seed_default_settings($pdo, $prefix, [
                'site_name' => $form['site_name'],
                'logo_text_1' => $form['logo_text_1'] !== '' ? $form['logo_text_1'] : $form['site_name'],
                'logo_text_2' => $form['logo_text_2'],
                'email' => $form['admin_email'],
                'timezone' => $form['timezone'],
            ]);
            if ($form['demo'] === '1') {
                seed_demo_content($pdo, $prefix);
            }
            $pdo->prepare('INSERT INTO `' . $prefix . 'users` (name, email, password, role, created_at) VALUES (?, ?, ?, ?, ?)')
                ->execute([$form['admin_name'], strtolower($form['admin_email']), password_hash($form['admin_pass'], PASSWORD_DEFAULT), 'admin', date('Y-m-d H:i:s')]);

            $config = [
                'db_host' => $form['db_host'],
                'db_port' => (int) $form['db_port'] ?: 3306,
                'db_name' => $form['db_name'],
                'db_user' => $form['db_user'],
                'db_pass' => $form['db_pass'],
                'db_prefix' => $prefix,
                'base_url' => rtrim($form['base_url'], '/'),
                'timezone' => $form['timezone'],
                'app_key' => bin2hex(random_bytes(32)),
                'debug' => false,
                'installed_at' => date('c'),
            ];
            $php = "<?php\n// WanderLuxe Travel CMS configuration - generated by the installer.\n// To reinstall, delete this file and open /install/ in your browser.\nreturn " . var_export($config, true) . ";\n";

            if (@file_put_contents($configFile, $php) === false) {
                $manualConfig = $php;
            } else {
                @chmod($configFile, 0640);
            }
            $_SESSION['install_done'] = ['base_url' => $config['base_url'], 'email' => $form['admin_email'], 'manual' => $manualConfig];
            header('Location: ?step=3');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Installation failed: ' . $e->getMessage();
        }
    }
}

$done = $_SESSION['install_done'] ?? null;
if ($step === '3' && !$done) {
    $step = $alreadyInstalled ? 'installed' : '1';
}
if ($alreadyInstalled && $step !== '3') {
    $step = 'installed';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Travel CMS</title>
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/tailwind.min.css">
    <style>.inp{width:100%;background:#f8fafc;border:1px solid #e2e8f0;border-radius:.75rem;padding:.6rem .8rem;font-size:.875rem}.inp:focus{outline:none;box-shadow:0 0 0 2px #14b8a6}.lbl{display:block;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#475569;margin-bottom:.25rem}</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-brand-700 font-sans text-slate-800 py-10 px-4">
<div class="max-w-3xl mx-auto">
    <div class="text-center text-white mb-8">
        <div class="inline-flex items-center space-x-2">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-brand-600 to-accent-500 flex items-center justify-center text-xl shadow-lg"><i class="fa-solid fa-compass"></i></div>
            <span class="font-serif font-bold text-3xl">Travel<span class="text-accent-400">CMS</span></span>
        </div>
        <p class="text-slate-300 text-sm mt-2">Installation Wizard · v<?= APP_VERSION ?></p>
    </div>

    <?php if (in_array($step, ['1', '2', '3'], true)): ?>
    <div class="flex items-center justify-center mb-6 text-xs font-bold">
        <?php foreach (['1' => 'Requirements', '2' => 'Configuration', '3' => 'Finish'] as $n => $label): $on = (int) $step >= (int) $n; ?>
            <div class="flex items-center">
                <span class="w-7 h-7 rounded-full flex items-center justify-center <?= $on ? 'bg-accent-500 text-white' : 'bg-white/20 text-white/60' ?>"><?= $n ?></span>
                <span class="ml-2 mr-4 <?= $on ? 'text-white' : 'text-white/50' ?>"><?= $label ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-3xl shadow-2xl p-6 sm:p-10">
    <?php if ($step === 'installed'): ?>
        <div class="text-center">
            <i class="fa-solid fa-lock text-5xl text-brand-600 mb-4"></i>
            <h1 class="font-serif text-2xl font-bold mb-2">Already installed</h1>
            <p class="text-sm text-slate-600 mb-6">Travel CMS is already installed. To reinstall, delete <code>config.php</code> from the root folder and reload this page.<br>For security, you should delete the <code>/install</code> folder.</p>
            <a href="../" class="px-6 py-3 bg-brand-600 text-white rounded-xl font-bold text-sm">Go to website</a>
        </div>

    <?php elseif ($step === '1'): ?>
        <h1 class="font-serif text-2xl font-bold mb-1">Welcome!</h1>
        <p class="text-sm text-slate-600 mb-6">This wizard will set up your travel booking website in about a minute. Have your MySQL database name, username and password ready (create them in your hosting control panel, e.g. cPanel &rarr; MySQL Databases).</p>
        <ul class="divide-y divide-slate-100 border border-slate-100 rounded-2xl mb-6">
            <?php foreach ($requirements as $i => [$label, $ok]): ?>
                <li class="flex items-center justify-between px-4 py-3 text-sm">
                    <span><?= h($label) ?></span>
                    <?php if ($ok): ?><i class="fa-solid fa-circle-check text-emerald-500"></i>
                    <?php elseif ($i === 5): ?><span class="text-amber-500 text-xs font-bold"><i class="fa-solid fa-triangle-exclamation"></i> You will copy config.php manually</span>
                    <?php else: ?><i class="fa-solid fa-circle-xmark text-rose-500"></i><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($requirementsOk): ?>
            <a href="?step=2" class="block text-center w-full bg-accent-500 hover:bg-orange-600 text-white font-bold py-3 rounded-xl">Continue <i class="fa-solid fa-arrow-right ml-1"></i></a>
        <?php else: ?>
            <p class="text-sm text-rose-600 font-semibold">Please fix the items marked in red (ask your hosting provider) and reload this page.</p>
        <?php endif; ?>

    <?php elseif ($step === '2'): ?>
        <?php if ($errors): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-700 rounded-xl p-4 mb-6 text-sm">
                <?php foreach ($errors as $err): ?><p><i class="fa-solid fa-circle-exclamation mr-1"></i> <?= h($err) ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="post" action="?step=2" class="space-y-8">
            <input type="hidden" name="token" value="<?= h($_SESSION['install_token']) ?>">
            <fieldset>
                <legend class="font-bold text-lg mb-3"><i class="fa-solid fa-database text-brand-600 mr-2"></i>Database</legend>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2"><label class="lbl">Database host</label><input class="inp" name="db_host" value="<?= h($form['db_host']) ?>" required></div>
                    <div><label class="lbl">Port</label><input class="inp" name="db_port" value="<?= h($form['db_port']) ?>"></div>
                    <div><label class="lbl">Database name</label><input class="inp" name="db_name" value="<?= h($form['db_name']) ?>" required></div>
                    <div><label class="lbl">Username</label><input class="inp" name="db_user" value="<?= h($form['db_user']) ?>" required autocomplete="off"></div>
                    <div><label class="lbl">Password</label><input class="inp" type="password" name="db_pass" value="<?= h($form['db_pass']) ?>" autocomplete="new-password"></div>
                    <div><label class="lbl">Table prefix</label><input class="inp" name="db_prefix" value="<?= h($form['db_prefix']) ?>"></div>
                </div>
            </fieldset>
            <fieldset>
                <legend class="font-bold text-lg mb-3"><i class="fa-solid fa-globe text-brand-600 mr-2"></i>Website</legend>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div><label class="lbl">Website name</label><input class="inp" name="site_name" value="<?= h($form['site_name']) ?>" required></div>
                    <div><label class="lbl">Website URL</label><input class="inp" name="base_url" value="<?= h($form['base_url']) ?>" required></div>
                    <div><label class="lbl">Logo text (white part)</label><input class="inp" name="logo_text_1" value="<?= h($form['logo_text_1']) ?>"></div>
                    <div><label class="lbl">Logo text (orange part)</label><input class="inp" name="logo_text_2" value="<?= h($form['logo_text_2']) ?>"></div>
                    <div class="sm:col-span-2"><label class="lbl">Timezone</label>
                        <select class="inp" name="timezone"><?php foreach ($timezones as $tz): ?><option <?= $tz === $form['timezone'] ? 'selected' : '' ?>><?= h($tz) ?></option><?php endforeach; ?></select>
                    </div>
                    <label class="sm:col-span-2 flex items-center space-x-2 text-sm bg-brand-50 rounded-xl p-3">
                        <input type="checkbox" name="demo" value="1" <?= $form['demo'] === '1' ? 'checked' : '' ?>>
                        <span>Install demo content (sample trips, activities, blog posts, reviews & pages) &mdash; recommended</span>
                    </label>
                </div>
            </fieldset>
            <fieldset>
                <legend class="font-bold text-lg mb-3"><i class="fa-solid fa-user-shield text-brand-600 mr-2"></i>Administrator account</legend>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div><label class="lbl">Your name</label><input class="inp" name="admin_name" value="<?= h($form['admin_name']) ?>" required></div>
                    <div><label class="lbl">Email (used to log in)</label><input class="inp" type="email" name="admin_email" value="<?= h($form['admin_email']) ?>" required></div>
                    <div><label class="lbl">Password (min 8 characters)</label><input class="inp" type="password" name="admin_pass" required minlength="8" autocomplete="new-password"></div>
                    <div><label class="lbl">Confirm password</label><input class="inp" type="password" name="admin_pass2" required minlength="8" autocomplete="new-password"></div>
                </div>
            </fieldset>
            <button class="w-full bg-accent-500 hover:bg-orange-600 text-white font-bold py-3 rounded-xl"><i class="fa-solid fa-rocket mr-1"></i> Install Now</button>
        </form>

    <?php elseif ($step === '3'): ?>
        <div class="text-center">
            <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto text-3xl mb-4"><i class="fa-solid fa-circle-check"></i></div>
            <h1 class="font-serif text-2xl font-bold mb-2">Installation complete!</h1>
            <?php if (!empty($done['manual'])): ?>
                <div class="text-left bg-amber-50 border border-amber-200 rounded-xl p-4 my-4 text-sm">
                    <p class="font-bold text-amber-700 mb-2"><i class="fa-solid fa-triangle-exclamation"></i> One more step</p>
                    <p class="mb-2">The installer could not write <code>config.php</code>. Create a file named <code>config.php</code> in the root folder of the script with the content below:</p>
                    <textarea class="inp font-mono text-xs" rows="14" readonly onclick="this.select()"><?= h($done['manual']) ?></textarea>
                </div>
            <?php endif; ?>
            <p class="text-sm text-slate-600 mb-2">Log in to your admin panel with <b><?= h($done['email']) ?></b> and the password you chose.</p>
            <p class="text-sm text-rose-600 font-semibold mb-6"><i class="fa-solid fa-shield-halved"></i> For security, delete the <code>/install</code> folder from your server now.</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="<?= h($done['base_url']) ?>/" class="px-6 py-3 bg-slate-200 hover:bg-slate-300 rounded-xl font-bold text-sm"><i class="fa-solid fa-globe mr-1"></i> View website</a>
                <a href="<?= h($done['base_url']) ?>/admin/" class="px-6 py-3 bg-brand-600 hover:bg-brand-700 text-white rounded-xl font-bold text-sm"><i class="fa-solid fa-gauge mr-1"></i> Open admin panel</a>
            </div>
        </div>
        <?php unset($_SESSION['install_done']); ?>
    <?php endif; ?>
    </div>
    <p class="text-center text-xs text-slate-400 mt-6">Travel CMS &middot; Pay-On-Arrival Booking System</p>
</div>
</body>
</html>
