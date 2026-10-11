<?php
require __DIR__ . '/includes/auth.php';
require_permission('subscribers');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('admin/subscribers.php');
    $action = post('action');
    if ($action === 'add') {
        $added = 0;
        // One email per line; "Name <email>" or "email, name" also accepted.
        foreach (lines(post('emails')) as $line) {
            if (preg_match('/^(.*)<([^>]+)>$/', $line, $m)) {
                [$name, $email] = [trim($m[1], " \"'"), trim($m[2])];
            } else {
                $parts = array_map('trim', explode(',', $line, 2));
                [$email, $name] = [$parts[0], $parts[1] ?? ''];
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL) && subscribe_email($email, $name, 'admin') !== 'exists') {
                $added++;
            }
        }
        flash('success', $added . ' subscriber(s) added.');
    } else {
        $ids = array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])));
        if (!$ids) {
            flash('error', 'Select at least one subscriber.');
        } else {
            $in = implode(',', array_fill(0, count($ids), '?'));
            if ($action === 'delete') {
                db_exec('DELETE FROM ' . tbl('subscribers') . " WHERE id IN ($in)", $ids);
                flash('success', count($ids) . ' subscriber(s) deleted.');
            } elseif ($action === 'unsubscribe') {
                db_exec('UPDATE ' . tbl('subscribers') . " SET status = 'unsubscribed', unsubscribed_at = ? WHERE id IN ($in)", array_merge([now()], $ids));
                flash('success', count($ids) . ' subscriber(s) unsubscribed.');
            } elseif ($action === 'activate') {
                db_exec('UPDATE ' . tbl('subscribers') . " SET status = 'active', unsubscribed_at = NULL WHERE id IN ($in)", $ids);
                flash('success', count($ids) . ' subscriber(s) re-activated.');
            }
        }
    }
    redirect($_SERVER['HTTP_REFERER'] ?? 'admin/subscribers.php');
}

$status = in_array($_GET['status'] ?? '', ['active', 'unsubscribed'], true) ? $_GET['status'] : '';
$q = trim((string) ($_GET['q'] ?? ''));
$where = ['1=1'];
$params = [];
if ($status) {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[] = '(email LIKE ? OR name LIKE ?)';
    array_push($params, '%' . $q . '%', '%' . $q . '%');
}
$whereSql = implode(' AND ', $where);

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscribers-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Email', 'Name', 'Status', 'Source', 'Subscribed at', 'Unsubscribed at', 'Unsubscribe link']);
    foreach (db_all('SELECT * FROM ' . tbl('subscribers') . " WHERE $whereSql ORDER BY created_at DESC", $params) as $r) {
        $cells = [$r['email'], $r['name'], $r['status'], $r['source'], $r['created_at'], $r['unsubscribed_at'], unsubscribe_url($r['email'])];
        fputcsv($out, array_map(fn ($c) => preg_match('/^[=+\-@\t\r]/', (string) $c) ? "'" . $c : $c, $cells));
    }
    fclose($out);
    exit;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$total = (int) db_value('SELECT COUNT(*) FROM ' . tbl('subscribers') . " WHERE $whereSql", $params);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$rows = db_all('SELECT * FROM ' . tbl('subscribers') . " WHERE $whereSql ORDER BY created_at DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);
$counts = ['active' => 0, 'unsubscribed' => 0];
foreach (db_all('SELECT status, COUNT(*) AS n FROM ' . tbl('subscribers') . ' GROUP BY status') as $c) {
    $counts[$c['status']] = (int) $c['n'];
}
$thisMonth = (int) db_value('SELECT COUNT(*) FROM ' . tbl('subscribers') . ' WHERE created_at >= ?', [date('Y-m-01 00:00:00')]);
$qs = fn (array $o) => url('admin/subscribers.php?' . http_build_query(array_filter(array_merge($_GET, $o), fn ($v) => $v !== '' && $v !== null)));

admin_header('Newsletter subscribers', 'subscribers');
?>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="card p-5"><p class="text-xs text-slate-500 font-semibold">Active subscribers</p><p class="text-2xl font-extrabold text-slate-900"><?= $counts['active'] ?></p></div>
    <div class="card p-5"><p class="text-xs text-slate-500 font-semibold">New this month</p><p class="text-2xl font-extrabold text-brand-600"><?= $thisMonth ?></p></div>
    <div class="card p-5"><p class="text-xs text-slate-500 font-semibold">Unsubscribed</p><p class="text-2xl font-extrabold text-slate-400"><?= $counts['unsubscribed'] ?></p></div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
    <div class="xl:col-span-3 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex gap-2 text-xs font-bold">
                <?php foreach (['' => 'All', 'active' => 'Active', 'unsubscribed' => 'Unsubscribed'] as $k => $label): ?>
                    <a href="<?= e($qs(['status' => $k, 'page' => null])) ?>" class="px-3 py-2 rounded-xl <?= $status === $k ? 'bg-slate-900 text-white' : 'bg-white text-slate-600' ?>"><?= $label ?></a>
                <?php endforeach; ?>
            </div>
            <div class="flex gap-2">
                <form method="get" class="flex gap-2">
                    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
                    <input class="inp !w-56" name="q" value="<?= e($q) ?>" placeholder="Search email or name…">
                    <button class="btn btn-light"><i class="fa-solid fa-magnifying-glass"></i></button>
                </form>
                <a href="<?= e($qs(['export' => 'csv', 'page' => null])) ?>" class="btn btn-primary"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
            </div>
        </div>
        <form method="post" class="card overflow-hidden" data-confirm="Apply this action to the selected subscribers?">
            <?= csrf_field() ?>
            <div class="flex flex-wrap items-center gap-2 p-3 border-b border-slate-100 text-xs">
                <span class="text-slate-500 font-semibold">With selected:</span>
                <select name="action" class="inp !w-auto !py-1.5">
                    <option value="unsubscribe">Unsubscribe</option>
                    <option value="activate">Re-activate</option>
                    <option value="delete">Delete permanently</option>
                </select>
                <button class="btn btn-light !py-1.5">Apply</button>
                <span class="ml-auto text-slate-400"><?= $total ?> subscriber(s)</span>
            </div>
            <div class="overflow-x-auto">
                <table class="tbl">
                    <thead><tr><th class="w-8"><input type="checkbox" onclick="document.querySelectorAll('.rowchk').forEach(c => c.checked = this.checked)"></th><th>Email</th><th>Name</th><th>Source</th><th>Status</th><th>Subscribed</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><input type="checkbox" class="rowchk" name="ids[]" value="<?= (int) $r['id'] ?>"></td>
                            <td class="font-semibold"><a href="mailto:<?= e($r['email']) ?>" class="hover:text-brand-600"><?= e($r['email']) ?></a></td>
                            <td><?= e($r['name']) ?></td>
                            <td class="text-xs text-slate-500"><?= e(ucfirst($r['source'])) ?></td>
                            <td><span class="px-2.5 py-1 rounded-lg text-xs font-bold <?= $r['status'] === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                            <td class="text-xs text-slate-500 whitespace-nowrap"><?= e(format_date($r['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-slate-400 py-10">No subscribers yet. The footer form<?= setting('newsletter_popup', '0') === '1' ? ' and popup' : '' ?> will collect them.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
        <?php pagination($page, $pages, fn ($i) => $qs(['page' => $i])); ?>
    </div>
    <div class="space-y-6">
        <form method="post" class="card p-6 space-y-4">
            <?= csrf_field() ?><input type="hidden" name="action" value="add">
            <h2 class="font-bold">Add subscribers</h2>
            <?php f_textarea('Emails (one per line)', 'emails', '', 5, 'Formats: email@example.com, "email, Name" or "Name <email>".'); ?>
            <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-plus"></i> Add</button>
        </form>
        <div class="card p-6 text-sm text-slate-600 space-y-2">
            <h2 class="font-bold text-slate-900">Sending your newsletter</h2>
            <p>Export the CSV and import it into Mailchimp, Brevo, MailerLite or your email tool. The file includes each subscriber's personal unsubscribe link.</p>
            <?php if (can('settings')): ?><a href="<?= e(url('admin/settings.php?tab=newsletter')) ?>" class="text-brand-600 font-bold text-xs">Newsletter & popup settings →</a><?php endif; ?>
        </div>
    </div>
</div>
<?php admin_footer(); ?>
