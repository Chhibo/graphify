<?php
/**
 * Bazaarly license server — admin page.
 * Lists your purchase codes, who you sold them to and which website uses each one.
 */
require __DIR__ . '/inc.php';

session_name('bzr_ls');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

if (!is_writable(__DIR__ . '/data')) {
    exit('The data/ folder must be writable (set its permission to 755 or 775 in your File Manager).');
}
$_SESSION['csrf'] ??= bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];
$self = strtok((string) ($_SERVER['REQUEST_URI'] ?? 'index.php'), '?') ?: 'index.php';
$go = function (string $qs = '') use ($self): never {
    header('Location: ' . $self . ($qs !== '' ? '?' . $qs : ''));
    exit;
};
$flash = function (string $type, string $msg): void {
    $_SESSION['flash'] = [$type, $msg];
};
$post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
if ($post && !hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
    $flash('error', 'Your session expired. Please try again.');
    $go();
}
$hasPassword = ls_setting('password') !== '';
$authed = !empty($_SESSION['ls_admin']);

/* ---------- first visit: choose a password ---------- */
if (!$hasPassword && $post && ($_POST['do'] ?? '') === 'setup') {
    $pw = (string) ($_POST['password'] ?? '');
    if (strlen($pw) < 10) {
        $flash('error', 'Use at least 10 characters.');
    } elseif ($pw !== (string) ($_POST['password2'] ?? '')) {
        $flash('error', 'The two passwords do not match.');
    } else {
        ls_set('password', password_hash($pw, PASSWORD_DEFAULT));
        session_regenerate_id(true);
        $_SESSION['ls_admin'] = true;
        $flash('success', 'Password saved. Now import your codes (the CSV file you received) or generate new ones.');
    }
    $go();
}

/* ---------- sign in / out ---------- */
if ($hasPassword && !$authed && $post && ($_POST['do'] ?? '') === 'login') {
    $fails = (int) ls_q("SELECT COUNT(*) FROM log WHERE action = 'login' AND result = 'fail' AND ip = ? AND created_at > ?", [ls_ip(), date('Y-m-d H:i:s', time() - 900)])->fetchColumn();
    if ($fails >= 8) {
        $flash('error', 'Too many attempts. Wait 15 minutes.');
    } elseif (password_verify((string) ($_POST['password'] ?? ''), ls_setting('password'))) {
        session_regenerate_id(true);
        $_SESSION['ls_admin'] = true;
    } else {
        ls_q("INSERT INTO log (code_id, action, domain, result, ip, created_at) VALUES (NULL, 'login', '', 'fail', ?, ?)", [ls_ip(), ls_now()]);
        sleep(1);
        $flash('error', 'Wrong password.');
    }
    $go();
}
if ($authed && $post && ($_POST['do'] ?? '') === 'logout') {
    $_SESSION = [];
    session_destroy();
    $go();
}

/* ---------- downloads ---------- */
if ($authed && isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bazaarly-codes-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['purchase_code', 'buyer', 'note', 'domain', 'activated_at', 'last_seen_at', 'blocked']);
    foreach (ls_q('SELECT * FROM codes ORDER BY id') as $r) {
        fputcsv($out, [$r['code'], $r['buyer'], $r['note'], $r['domain'], $r['activated_at'], $r['last_seen_at'], $r['blocked'] ? 'yes' : '']);
    }
    exit;
}
if ($authed && isset($_GET['hashes'])) {
    // the file that goes into Bazaarly as includes/license-data.php
    header('Content-Type: application/x-php; charset=utf-8');
    header('Content-Disposition: attachment; filename="license-data.php"');
    echo "<?php\n// Purchase code fingerprints (one-way SHA-256). The real codes are not stored in this script.\nreturn [\n";
    foreach (ls_q('SELECT hash FROM codes ORDER BY hash') as $r) {
        echo "    '" . $r['hash'] . "' => 1,\n";
    }
    echo "];\n";
    exit;
}

/* ---------- actions ---------- */
if ($authed && $post) {
    $do = (string) ($_POST['do'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $back = (string) ($_POST['back'] ?? '');
    if ($do === 'import') {
        $tmp = $_FILES['csv']['tmp_name'] ?? '';
        $added = 0;
        if ($tmp !== '' && is_uploaded_file($tmp)) {
            preg_match_all('/BZR-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}/i', (string) file_get_contents($tmp), $m);
            foreach ($m[0] as $c) {
                $added += ls_add_code($c) ? 1 : 0;
            }
        }
        $flash($added ? 'success' : 'error', $added ? "$added codes imported." : 'No new codes found in that file.');
    } elseif ($do === 'generate') {
        $n = max(1, min(5000, (int) ($_POST['count'] ?? 0)));
        $made = 0;
        while ($made < $n) {
            $made += ls_add_code(ls_new_code()) ? 1 : 0;
        }
        $flash('success', "$made new codes created. Download the new license-data.php and put it in Bazaarly's includes/ folder before you send the script to new buyers.");
    } elseif ($id && $do === 'save') {
        ls_q('UPDATE codes SET buyer = ?, note = ? WHERE id = ?', [mb_substr(trim((string) ($_POST['buyer'] ?? '')), 0, 190), mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 500), $id]);
        $flash('success', 'Saved.');
    } elseif ($id && $do === 'reset') {
        ls_q("UPDATE codes SET domain = '', site_url = '', activated_at = NULL WHERE id = ?", [$id]);
        $flash('success', 'Website removed — the buyer can now use this code on a new domain.');
    } elseif ($id && $do === 'block') {
        ls_q('UPDATE codes SET blocked = 1 - blocked WHERE id = ?', [$id]);
        $flash('success', 'Updated.');
    } elseif ($do === 'password') {
        $pw = (string) ($_POST['password'] ?? '');
        if (strlen($pw) >= 10) {
            ls_set('password', password_hash($pw, PASSWORD_DEFAULT));
            $flash('success', 'Password changed.');
        } else {
            $flash('error', 'Use at least 10 characters.');
        }
    }
    $go(preg_match('/^[a-z0-9=&%._+-]*$/i', $back) ? $back : '');
}

/* ---------- page data ---------- */
$msg = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$filter = in_array($_GET['f'] ?? '', ['free', 'sold', 'active', 'blocked'], true) ? $_GET['f'] : 'all';
$search = trim((string) ($_GET['q'] ?? ''));
$counts = $rows = [];
$next = null;
$page = max(1, (int) ($_GET['p'] ?? 1));
$pages = 1;
if ($authed) {
    $c = fn(string $w) => (int) ls_q("SELECT COUNT(*) FROM codes WHERE $w")->fetchColumn();
    $counts = ['all' => $c('1'), 'free' => $c("buyer = '' AND domain = '' AND blocked = 0"), 'sold' => $c("buyer <> ''"),
        'active' => $c("domain <> ''"), 'blocked' => $c('blocked = 1')];
    $where = ['all' => '1', 'free' => "buyer = '' AND domain = '' AND blocked = 0", 'sold' => "buyer <> ''", 'active' => "domain <> ''", 'blocked' => 'blocked = 1'][$filter];
    $params = [];
    if ($search !== '') {
        $where .= ' AND (code LIKE ? OR buyer LIKE ? OR note LIKE ? OR domain LIKE ? OR hash = ?)';
        $like = '%' . $search . '%';
        $params = [$like, $like, $like, $like, ls_hash($search)];
    }
    $total = (int) ls_q("SELECT COUNT(*) FROM codes WHERE $where", $params)->fetchColumn();
    $pages = max(1, (int) ceil($total / 100));
    $page = min($page, $pages);
    $rows = ls_q("SELECT * FROM codes WHERE $where ORDER BY id LIMIT 100 OFFSET " . (($page - 1) * 100), $params)->fetchAll();
    $next = ls_q("SELECT * FROM codes WHERE buyer = '' AND domain = '' AND blocked = 0 ORDER BY id LIMIT 1")->fetch();
    $log = ls_q("SELECT l.*, c.code FROM log l LEFT JOIN codes c ON c.id = l.code_id WHERE l.action <> 'login' ORDER BY l.id DESC LIMIT 30")->fetchAll();
}
$back = http_build_query(array_filter(['f' => $filter !== 'all' ? $filter : null, 'q' => $search ?: null, 'p' => $page > 1 ? $page : null]));
$apiUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'your-site.com') . rtrim(dirname($self), '/') . '/api.php';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Bazaarly licenses</title>
<style>
:root { --brand: #0d9488; --bg: #f5f6f8; --surface: #fff; --text: #0f172a; --muted: #64748b; --border: #e3e7ed; --danger: #dc2626; --ok: #16a34a; --warn: #d97706; }
@media (prefers-color-scheme: dark) { :root { --bg: #0a0f1a; --surface: #111827; --text: #e8edf5; --muted: #94a3b8; --border: #233047; } }
* { box-sizing: border-box; }
body { margin: 0; font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); }
a { color: var(--brand); }
.wrap { max-width: 1180px; margin: 0 auto; padding: 24px 16px 60px; }
.top { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 20px; }
.top h1 { font-size: 1.4rem; margin: 0; }
.card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 18px 20px; margin-bottom: 18px; }
.narrow { max-width: 420px; margin: 80px auto; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; }
input, select, textarea { font: inherit; color: inherit; background: var(--bg); border: 1px solid var(--border); border-radius: 9px; padding: .5rem .7rem; width: 100%; }
label { display: block; font-weight: 600; font-size: .88rem; margin: 10px 0 4px; }
button, .btn { font: inherit; font-weight: 600; border: 1px solid var(--border); background: var(--surface); color: var(--text); border-radius: 9px; padding: .45rem .85rem; cursor: pointer; text-decoration: none; display: inline-block; white-space: nowrap; }
.primary { background: var(--brand); border-color: var(--brand); color: #fff; }
.danger { color: var(--danger); }
.sm { padding: .25rem .55rem; font-size: .82rem; }
.alert { padding: .7rem 1rem; border-radius: 10px; margin-bottom: 16px; border: 1px solid; }
.alert.success { border-color: var(--ok); color: var(--ok); } .alert.error { border-color: var(--danger); color: var(--danger); }
.tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px; }
.tabs a { padding: .35rem .8rem; border-radius: 999px; border: 1px solid var(--border); text-decoration: none; color: var(--muted); font-weight: 600; font-size: .88rem; }
.tabs a.on { background: var(--brand); border-color: var(--brand); color: #fff; }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: .9rem; }
th, td { text-align: left; padding: 8px; border-bottom: 1px solid var(--border); vertical-align: middle; }
th { color: var(--muted); font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; }
code, .mono { font-family: ui-monospace, Menlo, Consolas, monospace; }
td code, .pill { white-space: nowrap; }
.pill { display: inline-block; padding: .1rem .5rem; border-radius: 999px; font-size: .75rem; font-weight: 700; }
.p-free { background: color-mix(in srgb, var(--muted) 15%, transparent); color: var(--muted); }
.p-sold { background: color-mix(in srgb, var(--warn) 15%, transparent); color: var(--warn); }
.p-active { background: color-mix(in srgb, var(--ok) 15%, transparent); color: var(--ok); }
.p-blocked { background: color-mix(in srgb, var(--danger) 15%, transparent); color: var(--danger); }
.row-form { display: flex; gap: 6px; }
.row-form input { min-width: 120px; padding: .3rem .5rem; }
.actions { display: flex; gap: 4px; justify-content: flex-end; }
.actions form { margin: 0; }
.next { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
.next code { font-size: 1.15rem; padding: .35rem .6rem; border-radius: 8px; background: var(--bg); border: 1px dashed var(--brand); }
.muted { color: var(--muted); } .small { font-size: .84rem; }
.pager { display: flex; gap: 6px; margin-top: 12px; }
</style>
</head>
<body>
<div class="wrap">
<?php if ($msg): ?><div class="alert <?= h($msg[0]) ?>"><?= h($msg[1]) ?></div><?php endif; ?>

<?php if (!$hasPassword): ?>
  <form method="post" class="card narrow">
    <h1>Bazaarly license server</h1>
    <p class="muted">First visit: choose the password for this page.</p>
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="do" value="setup">
    <label for="pw">Password</label><input id="pw" type="password" name="password" minlength="10" required autocomplete="new-password">
    <label for="pw2">Repeat password</label><input id="pw2" type="password" name="password2" required autocomplete="new-password">
    <p><button class="primary" type="submit">Save password</button></p>
  </form>
<?php elseif (!$authed): ?>
  <form method="post" class="card narrow">
    <h1>Bazaarly licenses</h1>
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="do" value="login">
    <label for="pw">Password</label><input id="pw" type="password" name="password" required autofocus autocomplete="current-password">
    <p><button class="primary" type="submit">Sign in</button></p>
  </form>
<?php else: ?>
  <div class="top">
    <h1>Bazaarly licenses</h1>
    <form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><button name="do" value="logout" type="submit">Sign out</button></form>
  </div>

  <div class="card">
    <?php if ($next): ?>
      <div class="next"><span><strong>Next free code</strong> — give this to your next buyer:</span><code id="nextCode"><?= h($next['code']) ?></code>
        <button type="button" class="sm" onclick="navigator.clipboard.writeText(document.getElementById('nextCode').textContent);this.textContent='Copied!'">Copy</button></div>
      <form method="post" class="row-form" style="margin-top:10px;max-width:640px">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="do" value="save"><input type="hidden" name="id" value="<?= (int) $next['id'] ?>"><input type="hidden" name="back" value="<?= h($back) ?>">
        <input name="buyer" placeholder="Buyer name or email" required><input name="note" placeholder="Note (order number, price…)"><button class="primary" type="submit">Mark as sold</button>
      </form>
    <?php else: ?>
      <p><strong>No free codes left.</strong> Generate new ones below.</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="tabs">
      <?php foreach (['all' => 'All', 'free' => 'Free', 'sold' => 'Sold', 'active' => 'In use', 'blocked' => 'Blocked'] as $k => $lbl): ?>
        <a href="?f=<?= $k ?>" class="<?= $filter === $k ? 'on' : '' ?>"><?= $lbl ?> (<?= $counts[$k] ?>)</a>
      <?php endforeach; ?>
    </div>
    <form method="get" class="row-form" style="max-width:520px;margin-bottom:10px">
      <input type="hidden" name="f" value="<?= h($filter) ?>"><input type="search" name="q" value="<?= h($search) ?>" placeholder="Search code, buyer, note or domain"><button type="submit">Search</button>
    </form>
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Code</th><th>Status</th><th>Buyer / note</th><th>Website</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $st = $r['blocked'] ? 'blocked' : ($r['domain'] !== '' ? 'active' : ($r['buyer'] !== '' ? 'sold' : 'free')); ?>
          <tr>
            <td class="muted"><?= (int) $r['id'] ?></td>
            <td><code><?= h($r['code']) ?></code></td>
            <td><span class="pill p-<?= $st ?>"><?= ['free' => 'Free', 'sold' => 'Sold', 'active' => 'In use', 'blocked' => 'Blocked'][$st] ?></span></td>
            <td><form method="post" class="row-form"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="do" value="save"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="back" value="<?= h($back) ?>">
              <input name="buyer" value="<?= h($r['buyer']) ?>" placeholder="Buyer"><input name="note" value="<?= h($r['note']) ?>" placeholder="Note"><button class="sm" type="submit">Save</button></form></td>
            <td><?php if ($r['domain'] !== ''): ?><a href="<?= h($r['site_url'] ?: 'https://' . $r['domain']) ?>" target="_blank" rel="noopener noreferrer"><?= h($r['domain']) ?></a><br><span class="muted small">since <?= h(substr((string) $r['activated_at'], 0, 10)) ?><?= $r['version'] ? ' · v' . h($r['version']) : '' ?><?= $r['last_seen_at'] ? ' · seen ' . h(substr((string) $r['last_seen_at'], 0, 10)) : '' ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
            <td><div class="actions">
              <?php if ($r['domain'] !== ''): ?><form method="post" onsubmit="return confirm('Free this code so it can be used on a new domain?')"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="do" value="reset"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="back" value="<?= h($back) ?>"><button class="sm" type="submit">Reset website</button></form><?php endif; ?>
              <form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="do" value="block"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="back" value="<?= h($back) ?>"><button class="sm <?= $r['blocked'] ? '' : 'danger' ?>" type="submit"><?= $r['blocked'] ? 'Unblock' : 'Block' ?></button></form>
            </div></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="muted">No codes here. <?= $counts['all'] ? '' : 'Import the CSV file of codes you received, or generate new ones below.' ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pages > 1): ?><div class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="btn sm<?= $i === $page ? ' primary' : '' ?>" href="?<?= h(http_build_query(['f' => $filter, 'q' => $search, 'p' => $i])) ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
  </div>

  <div class="grid">
    <form method="post" enctype="multipart/form-data" class="card">
      <h3>Import codes</h3>
      <p class="muted small">Upload the CSV file of purchase codes. Codes already here are skipped.</p>
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="do" value="import">
      <input type="file" name="csv" accept=".csv,.txt" required>
      <p><button class="primary" type="submit">Import</button></p>
    </form>
    <form method="post" class="card">
      <h3>Generate more codes</h3>
      <p class="muted small">After generating, download the new <code>license-data.php</code> and replace <code>includes/license-data.php</code> in Bazaarly, then zip the script again for new buyers.</p>
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="do" value="generate">
      <label for="cnt">How many</label><input id="cnt" type="number" name="count" value="100" min="1" max="5000">
      <p><button class="primary" type="submit">Generate</button></p>
    </form>
    <div class="card">
      <h3>Downloads &amp; setup</h3>
      <p><a class="btn" href="?export=1">Export all codes (CSV)</a></p>
      <p><a class="btn" href="?hashes=1">Download license-data.php</a></p>
      <p class="muted small">API address for Bazaarly's <code>includes/license.php</code> (LICENSE_SERVER):<br><code><?= h($apiUrl) ?></code></p>
      <form method="post" class="row-form"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="do" value="password"><input type="password" name="password" placeholder="New password" minlength="10" autocomplete="new-password"><button type="submit">Change</button></form>
    </div>
  </div>

  <div class="card">
    <h3>Recent activity</h3>
    <div class="table-wrap"><table>
      <thead><tr><th>When (UTC)</th><th>Code</th><th>Request</th><th>Domain</th><th>Result</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($log as $l): ?>
        <tr><td class="small"><?= h($l['created_at']) ?></td><td><code class="small"><?= h($l['code'] ?? '—') ?></code></td><td><?= h($l['action']) ?></td><td><?= h($l['domain']) ?></td><td><?= h($l['result']) ?></td><td class="muted small"><?= h($l['ip']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$log): ?><tr><td colspan="6" class="muted">Nothing yet. Installs and checks from your buyers appear here.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
<?php endif; ?>
</div>
</body>
</html>
