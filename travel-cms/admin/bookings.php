<?php
require __DIR__ . '/includes/auth.php';

$statuses = booking_statuses();

// Bulk / single actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('admin/bookings.php');
    $ids = array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])));
    $action = post('action');
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        if ($action === 'delete') {
            db_exec('DELETE FROM ' . tbl('bookings') . " WHERE id IN ($in)", $ids);
            flash('success', count($ids) . ' booking(s) deleted.');
        } elseif (isset($statuses[$action])) {
            db_exec('UPDATE ' . tbl('bookings') . " SET status = ?, updated_at = ? WHERE id IN ($in)", array_merge([$action, now()], $ids));
            flash('success', count($ids) . ' booking(s) marked as ' . $statuses[$action][0] . '.');
        }
    } else {
        flash('error', 'Select at least one booking.');
    }
    redirect($_SERVER['HTTP_REFERER'] ?? 'admin/bookings.php');
}

$status = $_GET['status'] ?? '';
$q = trim((string) ($_GET['q'] ?? ''));
$when = $_GET['when'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$tourId = (int) ($_GET['tour'] ?? 0);

$where = ['1=1'];
$params = [];
if (isset($statuses[$status])) {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[] = '(reference LIKE ? OR customer_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ? OR tour_title LIKE ?)';
    array_push($params, ...array_fill(0, 5, '%' . $q . '%'));
}
if ($when === 'upcoming') {
    $where[] = "travel_date >= ? AND status IN ('pending','confirmed')";
    $params[] = date('Y-m-d');
} elseif ($when === 'today') {
    $where[] = 'travel_date = ?';
    $params[] = date('Y-m-d');
} elseif ($when === 'past') {
    $where[] = 'travel_date < ?';
    $params[] = date('Y-m-d');
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $where[] = 'travel_date >= ?';
    $params[] = $from;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $where[] = 'travel_date <= ?';
    $params[] = $to;
}
if ($tourId) {
    $where[] = 'tour_id = ?';
    $params[] = $tourId;
}
$whereSql = implode(' AND ', $where);
$order = $when === 'upcoming' ? 'travel_date ASC, id ASC' : 'created_at DESC';

// CSV export of the current filter
if (($_GET['export'] ?? '') === 'csv') {
    $rows = db_all('SELECT * FROM ' . tbl('bookings') . " WHERE $whereSql ORDER BY $order", $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bookings-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Reference', 'Status', 'Experience', 'Travel date', 'Guests', 'Unit price', 'Total', 'Name', 'Email', 'Phone', 'Notes', 'Admin notes', 'Booked at']);
    foreach ($rows as $r) {
        $cells = [$r['reference'], $r['status'], $r['tour_title'], $r['travel_date'], $r['guests'], $r['price_on_request'] ? 'On request' : $r['unit_price'], $r['price_on_request'] ? 'On request' : $r['total'], $r['customer_name'], $r['customer_email'], $r['customer_phone'], $r['notes'], $r['admin_notes'], $r['created_at']];
        // Neutralise spreadsheet formulas in user-provided values.
        $cells = array_map(fn ($c) => preg_match('/^[=@\t\r]|^[+\-](?![\d\s().\-]+$)/', (string) $c) ? "'" . $c : $c, $cells);
        fputcsv($out, $cells);
    }
    fclose($out);
    exit;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$total = (int) db_value('SELECT COUNT(*) FROM ' . tbl('bookings') . " WHERE $whereSql", $params);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$rows = db_all('SELECT * FROM ' . tbl('bookings') . " WHERE $whereSql ORDER BY $order LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);
$counts = [];
foreach (db_all('SELECT status, COUNT(*) AS n FROM ' . tbl('bookings') . ' GROUP BY status') as $c) {
    $counts[$c['status']] = (int) $c['n'];
}
$tours = db_all('SELECT id, title FROM ' . tbl('tours') . ' ORDER BY title');
$qs = fn (array $o) => url('admin/bookings.php?' . http_build_query(array_filter(array_merge($_GET, $o), fn ($v) => $v !== '' && $v !== null && $v !== 0)));

admin_header('Bookings', 'bookings');
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div class="flex flex-wrap gap-2 text-xs font-bold">
        <a href="<?= e(url('admin/bookings.php')) ?>" class="px-3 py-2 rounded-xl <?= $status === '' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600' ?>">All (<?= array_sum($counts) ?>)</a>
        <?php foreach ($statuses as $key => [$label]): ?>
            <a href="<?= e(url('admin/bookings.php?status=' . $key)) ?>" class="px-3 py-2 rounded-xl <?= $status === $key ? 'bg-slate-900 text-white' : 'bg-white text-slate-600' ?>"><?= e($label) ?> (<?= $counts[$key] ?? 0 ?>)</a>
        <?php endforeach; ?>
    </div>
    <div class="flex gap-2">
        <a href="<?= e($qs(['export' => 'csv', 'page' => null])) ?>" class="btn btn-light"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        <a href="<?= e(url('admin/booking.php')) ?>" class="btn btn-accent"><i class="fa-solid fa-plus"></i> New booking</a>
    </div>
</div>

<form method="get" class="card p-4 mb-4 grid grid-cols-2 md:grid-cols-6 gap-3 items-end">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <div class="col-span-2"><label class="lbl">Search</label><input class="inp" name="q" value="<?= e($q) ?>" placeholder="Reference, name, email, phone…"></div>
    <div><label class="lbl">Travel from</label><input class="inp" type="date" name="from" value="<?= e($from) ?>"></div>
    <div><label class="lbl">Travel to</label><input class="inp" type="date" name="to" value="<?= e($to) ?>"></div>
    <div><label class="lbl">Experience</label>
        <select class="inp" name="tour"><option value="">All</option><?php foreach ($tours as $t): ?><option value="<?= (int) $t['id'] ?>" <?= $tourId === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['title']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="flex gap-2">
        <select class="inp" name="when"><option value="">Any time</option><option value="upcoming" <?= $when === 'upcoming' ? 'selected' : '' ?>>Upcoming</option><option value="today" <?= $when === 'today' ? 'selected' : '' ?>>Today</option><option value="past" <?= $when === 'past' ? 'selected' : '' ?>>Past</option></select>
        <button class="btn btn-primary"><i class="fa-solid fa-filter"></i></button>
    </div>
</form>

<form method="post" class="card overflow-hidden" data-confirm="Apply this action to the selected bookings?">
    <?= csrf_field() ?>
    <div class="flex flex-wrap items-center gap-2 p-3 border-b border-slate-100 text-xs">
        <span class="text-slate-500 font-semibold">With selected:</span>
        <select name="action" class="inp !w-auto !py-1.5">
            <?php foreach ($statuses as $key => [$label]): ?><option value="<?= $key ?>">Mark as <?= e($label) ?></option><?php endforeach; ?>
            <option value="delete">Delete permanently</option>
        </select>
        <button class="btn btn-light !py-1.5">Apply</button>
        <span class="ml-auto text-slate-400"><?= $total ?> booking(s)</span>
    </div>
    <div class="overflow-x-auto">
        <table class="tbl">
            <thead><tr><th class="w-8"><input type="checkbox" onclick="document.querySelectorAll('.rowchk').forEach(c => c.checked = this.checked)"></th><th>Ref</th><th>Guest</th><th>Experience</th><th>Travel date</th><th>Guests</th><th>Total</th><th>Status</th><th>Booked</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><input type="checkbox" class="rowchk" name="ids[]" value="<?= (int) $r['id'] ?>"></td>
                    <td class="font-mono text-xs font-bold text-brand-700 whitespace-nowrap"><?= e($r['reference']) ?></td>
                    <td><div class="font-semibold"><?= e($r['customer_name']) ?></div><div class="text-xs text-slate-400"><?= e($r['customer_email']) ?> · <?= e($r['customer_phone']) ?></div></td>
                    <td class="max-w-[220px] truncate"><?= e($r['tour_title']) ?></td>
                    <td class="whitespace-nowrap"><?= e(format_date($r['travel_date'])) ?></td>
                    <td><?= (int) $r['guests'] ?></td>
                    <td class="whitespace-nowrap font-semibold"><?= e(booking_total($r)) ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td class="text-xs text-slate-400 whitespace-nowrap"><?= e(time_ago($r['created_at'])) ?></td>
                    <td><a href="<?= e(url('admin/booking.php?id=' . $r['id'])) ?>" class="btn btn-light !py-1.5 !px-3">Open</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="10" class="text-center text-slate-400 py-10">No bookings found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</form>
<?php pagination($page, $pages, fn ($i) => $qs(['page' => $i])); ?>
<?php admin_footer(); ?>
