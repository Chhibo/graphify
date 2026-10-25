<?php
require __DIR__ . '/includes/auth.php';
require_permission('reports');

// ---- Period -------------------------------------------------------------
$presets = [
    '30d' => ['Last 30 days', date('Y-m-d', strtotime('-29 days')), date('Y-m-d')],
    '90d' => ['Last 90 days', date('Y-m-d', strtotime('-89 days')), date('Y-m-d')],
    'month' => ['This month', date('Y-m-01'), date('Y-m-t')],
    'lastmonth' => ['Last month', date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
    'year' => ['This year', date('Y-01-01'), date('Y-12-31')],
    '12m' => ['Last 12 months', date('Y-m-01', strtotime('-11 months')), date('Y-m-d')],
];
$range = $_GET['range'] ?? '30d';
$basis = ($_GET['basis'] ?? 'created') === 'travel' ? 'travel' : 'created';
if ($range === 'custom' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '')) {
    [$from, $to] = [$_GET['from'], $_GET['to']];
    $label = format_date($from) . ' – ' . format_date($to);
} else {
    $range = isset($presets[$range]) ? $range : '30d';
    [$label, $from, $to] = $presets[$range];
}
$b = tbl('bookings');
$dateCol = $basis === 'travel' ? 'travel_date' : 'DATE(created_at)';
$inRange = "$dateCol BETWEEN ? AND ?";
$p = [$from, $to];
$valid = "status <> 'cancelled'";
$priced = "status IN ('confirmed','completed') AND price_on_request = 0";

// ---- Key figures ----------------------------------------------------------
$k = db_one("SELECT COUNT(*) AS bookings, COALESCE(SUM(CASE WHEN $valid THEN guests END),0) AS guests,
    COALESCE(SUM(CASE WHEN $priced THEN total END),0) AS revenue,
    SUM(status = 'cancelled') AS cancelled, SUM(price_on_request = 1 AND status <> 'cancelled') AS on_request,
    AVG(CASE WHEN $valid THEN DATEDIFF(travel_date, DATE(created_at)) END) AS lead_days
    FROM $b WHERE $inRange", $p);
$pricedCount = (int) db_value("SELECT COUNT(*) FROM $b WHERE $inRange AND $priced", $p);
$avgValue = $pricedCount ? $k['revenue'] / $pricedCount : 0;
$cancelRate = $k['bookings'] ? $k['cancelled'] / $k['bookings'] * 100 : 0;
$req = db_one('SELECT COUNT(*) AS n, SUM(status = \'booked\') AS booked FROM ' . tbl('trip_requests') . ' WHERE DATE(created_at) BETWEEN ? AND ?', $p);
$subs = (int) db_value('SELECT COUNT(*) FROM ' . tbl('subscribers') . ' WHERE DATE(created_at) BETWEEN ? AND ?', $p);
$rev = db_one('SELECT COUNT(*) AS n, AVG(rating) AS avg, SUM(verified) AS v FROM ' . tbl('reviews') . " WHERE status = 'approved' AND DATE(created_at) BETWEEN ? AND ?", $p);

// ---- Breakdowns -----------------------------------------------------------
$byTour = db_all("SELECT tour_title AS name, COUNT(*) AS bookings, SUM(guests) AS guests, COALESCE(SUM(CASE WHEN $priced THEN total END),0) AS revenue
    FROM $b WHERE $inRange AND $valid GROUP BY tour_title ORDER BY bookings DESC, revenue DESC LIMIT 10", $p);
$byDest = db_all("SELECT COALESCE(d.name, 'No destination') AS name, COUNT(*) AS bookings, SUM(x.guests) AS guests, COALESCE(SUM(CASE WHEN x.status IN ('confirmed','completed') AND x.price_on_request = 0 THEN x.total END),0) AS revenue
    FROM $b x LEFT JOIN " . tbl('tours') . ' t ON t.id = x.tour_id LEFT JOIN ' . tbl('destinations') . " d ON d.id = t.destination_id
    WHERE " . str_replace(['travel_date', 'created_at'], ['x.travel_date', 'x.created_at'], $inRange) . " AND x.status <> 'cancelled' GROUP BY d.name ORDER BY bookings DESC LIMIT 10", $p);
$byStatus = array_column(db_all("SELECT status, COUNT(*) AS n FROM $b WHERE $inRange GROUP BY status", $p), 'n', 'status');
$byLang = db_all("SELECT COALESCE(NULLIF(lang, ''), ?) AS lang, COUNT(*) AS n FROM $b WHERE $inRange GROUP BY COALESCE(NULLIF(lang, ''), ?) ORDER BY n DESC", [default_lang(), $from, $to, default_lang()]);
$byWeekday = array_column(db_all("SELECT WEEKDAY(travel_date) AS d, COUNT(*) AS n FROM $b WHERE $inRange AND $valid GROUP BY WEEKDAY(travel_date)", $p), 'n', 'd');

// Last 12 months (always shown, independent of the period filter).
$months = [];
for ($i = 11; $i >= 0; $i--) {
    $months[date('Y-m', strtotime("first day of -$i months"))] = ['bookings' => 0, 'revenue' => 0.0];
}
$monthCol = $basis === 'travel' ? 'travel_date' : 'created_at';
foreach (db_all("SELECT DATE_FORMAT($monthCol, '%Y-%m') AS m, COUNT(*) AS n, COALESCE(SUM(CASE WHEN $priced THEN total END),0) AS r FROM $b WHERE $monthCol >= ? AND $valid GROUP BY m", [array_key_first($months) . '-01']) as $row) {
    if (isset($months[$row['m']])) {
        $months[$row['m']] = ['bookings' => (int) $row['n'], 'revenue' => (float) $row['r']];
    }
}

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Report', $label . ' (' . ($basis === 'travel' ? 'by travel date' : 'by booking date') . ')']);
    fputcsv($out, []);
    foreach (['Bookings' => $k['bookings'], 'Guests' => $k['guests'], 'Revenue (confirmed + completed)' => $k['revenue'], 'Average booking value' => round($avgValue, 2),
        'Cancellation rate %' => round($cancelRate, 1), 'Price on request bookings' => $k['on_request'], 'Trip requests' => $req['n'], 'New subscribers' => $subs] as $name => $v) {
        fputcsv($out, [$name, $v]);
    }
    fputcsv($out, []);
    fputcsv($out, ['Experience', 'Bookings', 'Guests', 'Revenue']);
    foreach ($byTour as $r) {
        fputcsv($out, [$r['name'], $r['bookings'], $r['guests'], $r['revenue']]);
    }
    fputcsv($out, []);
    fputcsv($out, ['Destination', 'Bookings', 'Guests', 'Revenue']);
    foreach ($byDest as $r) {
        fputcsv($out, [$r['name'], $r['bookings'], $r['guests'], $r['revenue']]);
    }
    fputcsv($out, []);
    fputcsv($out, ['Month', 'Bookings', 'Revenue']);
    foreach ($months as $m => $v) {
        fputcsv($out, [$m, $v['bookings'], $v['revenue']]);
    }
    fclose($out);
    exit;
}

/** A single-series column chart (bookings or revenue per month) with hover values. */
function column_chart(array $data, string $format): void
{
    $max = max(1, max($data));
    echo '<div class="flex items-end h-44 gap-1.5" role="img">';
    foreach ($data as $label => $v) {
        $text = $format === 'money' ? money($v) : (string) $v;
        echo '<div class="flex-1 flex flex-col items-center justify-end h-full group relative">'
            . '<span class="absolute -top-6 text-[10px] font-bold text-slate-700 bg-white shadow px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 whitespace-nowrap pointer-events-none">' . e($text) . '</span>'
            . '<div class="w-full rounded-t bg-brand-600 group-hover:bg-brand-700 transition" style="height:' . ($v > 0 ? max(2, round($v / $max * 100)) : 0) . '%" title="' . e(date('M Y', strtotime($label . '-01')) . ': ' . $text) . '"></div>'
            . '<span class="text-[10px] text-slate-400 mt-1">' . e(date('M', strtotime($label . '-01'))) . '</span></div>';
    }
    echo '</div>';
}

/** Horizontal bar used inside tables (share of the largest value). */
function share_bar(float $v, float $max): string
{
    $w = $max > 0 ? round($v / $max * 100) : 0;
    return '<div class="h-1.5 bg-slate-100 rounded-full mt-1"><div class="h-1.5 rounded-full bg-brand-600" style="width:' . $w . '%"></div></div>';
}

$qs = fn (array $o) => url('admin/reports.php?' . http_build_query(array_filter(array_merge($_GET, $o), fn ($v) => $v !== '' && $v !== null)));
admin_header('Reports', 'reports');
?>
<form method="get" class="card p-4 mb-6 flex flex-wrap items-end gap-3">
    <div class="flex flex-wrap gap-1.5 text-xs font-bold">
        <?php foreach ($presets as $key => [$name]): ?>
            <a href="<?= e($qs(['range' => $key, 'from' => null, 'to' => null])) ?>" class="px-3 py-2 rounded-xl <?= $range === $key ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>"><?= e($name) ?></a>
        <?php endforeach; ?>
    </div>
    <input type="hidden" name="range" value="custom">
    <div><label class="lbl">From</label><input class="inp" type="date" name="from" value="<?= e($from) ?>"></div>
    <div><label class="lbl">To</label><input class="inp" type="date" name="to" value="<?= e($to) ?>"></div>
    <div><label class="lbl">Count bookings by</label>
        <select class="inp" name="basis"><option value="created">Booking date</option><option value="travel" <?= $basis === 'travel' ? 'selected' : '' ?>>Travel date</option></select>
    </div>
    <button class="btn btn-primary"><i class="fa-solid fa-filter"></i> Apply</button>
    <a href="<?= e($qs(['export' => 'csv'])) ?>" class="btn btn-light ml-auto"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
</form>
<p class="text-sm text-slate-500 mb-4"><b class="text-slate-800"><?= e($label) ?></b> · <?= $basis === 'travel' ? 'by travel date' : 'by booking date' ?>. Revenue counts confirmed and completed bookings with a set price (amounts are paid on arrival).</p>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php foreach ([
        ['Bookings', number_format((int) $k['bookings']), ((int) $k['on_request']) . ' with price on request'],
        ['Guests', number_format((int) $k['guests']), $k['lead_days'] !== null ? 'Booked ' . round((float) $k['lead_days']) . ' days ahead on average' : ''],
        ['Revenue', money($k['revenue']), 'Average booking ' . money(round($avgValue))],
        ['Cancellation rate', number_format($cancelRate, 1) . '%', (int) $k['cancelled'] . ' cancelled'],
        ['Trip requests', number_format((int) $req['n']), (int) $req['booked'] . ' converted to bookings'],
        ['New subscribers', number_format($subs), 'Newsletter sign-ups'],
        ['New reviews', number_format((int) $rev['n']), $rev['n'] ? number_format((float) $rev['avg'], 1) . '/5 average · ' . (int) $rev['v'] . ' verified' : 'No reviews in this period'],
        ['Confirmed', number_format((int) ($byStatus['confirmed'] ?? 0) + (int) ($byStatus['completed'] ?? 0)), (int) ($byStatus['pending'] ?? 0) . ' still pending'],
    ] as [$name, $value, $sub]): ?>
        <div class="card p-5">
            <p class="text-xs text-slate-500 font-semibold"><?= e($name) ?></p>
            <p class="text-2xl font-extrabold text-slate-900 mt-1"><?= e($value) ?></p>
            <p class="text-[11px] text-slate-400 mt-1"><?= e($sub) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
    <div class="card p-5">
        <h2 class="font-bold mb-1">Bookings per month</h2>
        <p class="text-xs text-slate-400 mb-8">Last 12 months, excluding cancellations</p>
        <?php column_chart(array_map(fn ($m) => $m['bookings'], $months), 'count'); ?>
    </div>
    <div class="card p-5">
        <h2 class="font-bold mb-1">Revenue per month</h2>
        <p class="text-xs text-slate-400 mb-8">Last 12 months, confirmed and completed bookings</p>
        <?php column_chart(array_map(fn ($m) => $m['revenue'], $months), 'money'); ?>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
    <?php foreach (['Top experiences' => $byTour, 'Destinations' => $byDest] as $title => $rows):
        $maxB = $rows ? max(array_column($rows, 'bookings')) : 0; ?>
        <div class="card overflow-hidden">
            <h2 class="font-bold p-5 pb-3"><?= e($title) ?></h2>
            <table class="tbl">
                <thead><tr><th><?= $title === 'Destinations' ? 'Destination' : 'Experience' ?></th><th class="text-right">Bookings</th><th class="text-right">Guests</th><th class="text-right">Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr><td class="max-w-[260px]"><div class="truncate"><?= e($r['name']) ?></div><?= share_bar((float) $r['bookings'], (float) $maxB) ?></td>
                        <td class="text-right font-semibold"><?= (int) $r['bookings'] ?></td><td class="text-right"><?= (int) $r['guests'] ?></td><td class="text-right whitespace-nowrap"><?= e(money($r['revenue'])) ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="4" class="text-center text-slate-400 py-8">No bookings in this period.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="card p-5">
        <h2 class="font-bold mb-3">By status</h2>
        <?php $totalStatus = array_sum($byStatus); foreach (booking_statuses() as $key => [$name]): $n = (int) ($byStatus[$key] ?? 0); ?>
            <div class="flex items-center justify-between py-1.5 text-sm"><span><?= status_badge($key) ?></span><span class="font-semibold"><?= $n ?> <span class="text-xs text-slate-400 font-normal"><?= $totalStatus ? round($n / $totalStatus * 100) . '%' : '' ?></span></span></div>
        <?php endforeach; ?>
    </div>
    <div class="card p-5">
        <h2 class="font-bold mb-3">Busiest travel days</h2>
        <?php $maxW = $byWeekday ? max($byWeekday) : 0; foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $i => $day): $n = (int) ($byWeekday[$i] ?? 0); ?>
            <div class="py-1 text-sm"><div class="flex justify-between"><span><?= $day ?></span><span class="font-semibold"><?= $n ?></span></div><?= share_bar($n, (float) $maxW) ?></div>
        <?php endforeach; ?>
    </div>
    <div class="card p-5">
        <h2 class="font-bold mb-3">Booking language</h2>
        <?php $maxL = $byLang ? max(array_column($byLang, 'n')) : 0; foreach ($byLang as $l): ?>
            <div class="py-1 text-sm"><div class="flex justify-between"><span><?= e(languages()[$l['lang']]['name'] ?? strtoupper($l['lang'])) ?></span><span class="font-semibold"><?= (int) $l['n'] ?></span></div><?= share_bar((float) $l['n'], (float) $maxL) ?></div>
        <?php endforeach; ?>
        <?php if (!$byLang): ?><p class="text-sm text-slate-400">No bookings in this period.</p><?php endif; ?>
    </div>
</div>
<?php admin_footer(); ?>
