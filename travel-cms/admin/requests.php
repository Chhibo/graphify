<?php
require __DIR__ . '/includes/auth.php';
require_permission('bookings');

$statuses = trip_request_statuses();
$status = isset($statuses[$_GET['status'] ?? '']) ? $_GET['status'] : '';
$q = trim((string) ($_GET['q'] ?? ''));
$where = ['1=1'];
$params = [];
if ($status) {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[] = '(reference LIKE ? OR name LIKE ? OR email LIKE ? OR phone LIKE ? OR destinations LIKE ?)';
    array_push($params, ...array_fill(0, 5, '%' . $q . '%'));
}
$whereSql = implode(' AND ', $where);

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="trip-requests-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Reference', 'Status', 'Name', 'Email', 'Phone', 'Destinations', 'Start date', 'Flexible', 'Nights', 'Adults', 'Children', 'Budget', 'Styles', 'Accommodation', 'Message', 'Language', 'Received']);
    foreach (db_all('SELECT * FROM ' . tbl('trip_requests') . " WHERE $whereSql ORDER BY created_at DESC", $params) as $r) {
        $cells = [$r['reference'], $r['status'], $r['name'], $r['email'], $r['phone'], $r['destinations'], $r['start_date'], $r['flexible_dates'] ? 'yes' : 'no', $r['nights'], $r['adults'], $r['children'], $r['budget'], $r['styles'], $r['accommodation'], $r['message'], $r['lang'], $r['created_at']];
        fputcsv($out, array_map(fn ($c) => preg_match('/^[=@\t\r]|^[+\-](?![\d\s().\-]+$)/', (string) $c) ? "'" . $c : $c, $cells));
    }
    fclose($out);
    exit;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$total = (int) db_value('SELECT COUNT(*) FROM ' . tbl('trip_requests') . " WHERE $whereSql", $params);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$rows = db_all('SELECT * FROM ' . tbl('trip_requests') . " WHERE $whereSql ORDER BY created_at DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);
$counts = array_column(db_all('SELECT status, COUNT(*) AS n FROM ' . tbl('trip_requests') . ' GROUP BY status'), 'n', 'status');
$qs = fn (array $o) => url('admin/requests.php?' . http_build_query(array_filter(array_merge($_GET, $o), fn ($v) => $v !== '' && $v !== null)));

admin_header('Trip requests', 'requests');
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div class="flex flex-wrap gap-2 text-xs font-bold">
        <a href="<?= e(url('admin/requests.php')) ?>" class="px-3 py-2 rounded-xl <?= $status === '' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600' ?>">All (<?= array_sum($counts) ?>)</a>
        <?php foreach ($statuses as $key => [$label]): ?>
            <a href="<?= e(url('admin/requests.php?status=' . $key)) ?>" class="px-3 py-2 rounded-xl <?= $status === $key ? 'bg-slate-900 text-white' : 'bg-white text-slate-600' ?>"><?= e($label) ?> (<?= (int) ($counts[$key] ?? 0) ?>)</a>
        <?php endforeach; ?>
    </div>
    <div class="flex gap-2">
        <form method="get" class="flex gap-2">
            <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
            <input class="inp !w-56" name="q" value="<?= e($q) ?>" placeholder="Name, email, destination…">
            <button class="btn btn-light"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
        <a href="<?= e($qs(['export' => 'csv', 'page' => null])) ?>" class="btn btn-light"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        <a href="<?= e(url('plan-trip.php')) ?>" target="_blank" class="btn btn-light"><i class="fa-solid fa-eye"></i> Form</a>
    </div>
</div>
<div class="card overflow-x-auto">
    <table class="tbl">
        <thead><tr><th>Ref</th><th>Guest</th><th>Destinations</th><th>Travel</th><th>Travellers</th><th>Budget</th><th>Status</th><th>Received</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr class="<?= $r['status'] === 'new' ? 'font-semibold' : '' ?>">
                <td class="font-mono text-xs font-bold text-brand-700 whitespace-nowrap"><?= e($r['reference']) ?></td>
                <td><div><?= e($r['name']) ?></div><div class="text-xs text-slate-400 font-normal"><?= e($r['email']) ?><?= $r['phone'] ? ' · ' . e($r['phone']) : '' ?></div></td>
                <td class="max-w-[200px] truncate"><?= e($r['destinations'] ?: '—') ?></td>
                <td class="whitespace-nowrap text-xs"><?= $r['start_date'] ? e(format_date($r['start_date'])) : 'Flexible' ?><?= $r['nights'] ? '<div class="text-slate-400">' . (int) $r['nights'] . ' nights</div>' : '' ?></td>
                <td class="text-xs whitespace-nowrap"><?= (int) $r['adults'] ?> adults<?= $r['children'] ? ', ' . (int) $r['children'] . ' children' : '' ?></td>
                <td class="text-xs"><?= e($r['budget'] ?: '—') ?></td>
                <td><?= request_status_badge($r['status']) ?></td>
                <td class="text-xs text-slate-400 whitespace-nowrap font-normal"><?= e(time_ago($r['created_at'])) ?></td>
                <td><a href="<?= e(url('admin/request.php?id=' . $r['id'])) ?>" class="btn btn-light !py-1.5 !px-3">Open</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="9" class="text-center text-slate-400 py-10">No trip requests yet. Visitors send them from the "Plan a Trip" page.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php pagination($page, $pages, fn ($i) => $qs(['page' => $i])); ?>
<?php admin_footer(); ?>
