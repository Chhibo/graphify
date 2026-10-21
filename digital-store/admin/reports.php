<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/networks.php';
require dirname(__DIR__) . '/inc/admin_layout.php';
require_admin();

// ----- Date range -----
$range = (string)($_GET['range'] ?? '30');
$today = date('Y-m-d');
if ($range === 'custom') {
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['from'] ?? '')) ? $_GET['from'] : date('Y-m-d', strtotime('-29 days'));
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['to'] ?? '')) ? $_GET['to'] : $today;
} else {
    $daysBack = in_array($range, ['7', '30', '90', '365'], true) ? (int)$range : 30;
    $range = (string)$daysBack;
    $from = date('Y-m-d', strtotime('-' . ($daysBack - 1) . ' days'));
    $to = $today;
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}
$start = strtotime($from . ' 00:00:00');
$end = strtotime($to . ' 23:59:59');
$tab = in_array($_GET['tab'] ?? '', ['overview', 'products', 'networks', 'countries', 'offers'], true) ? $_GET['tab'] : 'overview';

$done = "u.completed_at IS NOT NULL AND u.network != 'test'";
$q = function (string $sql) use ($start, $end): array {
    $st = db()->prepare($sql);
    $st->execute([':s' => $start, ':e' => $end]);
    return $st->fetchAll();
};
$netLabel = fn(string $n) => $n === '' ? 'No offers shown' : (network_definitions()[$n]['label'] ?? $n);

// ----- Data for each tab -----
$rows = [];
$columns = [];
switch ($tab) {
    case 'products':
        $rows = $q("SELECT COALESCE(p.title, l.title, '(deleted)') AS name, CASE WHEN u.link_id IS NOT NULL THEN 'Locked link' ELSE 'Product' END AS type,
                COUNT(*) AS opened, SUM($done) AS completed, SUM(CASE WHEN $done THEN u.payout ELSE 0 END) AS earned
            FROM unlocks u LEFT JOIN products p ON p.id = u.product_id LEFT JOIN links l ON l.id = u.link_id
            WHERE u.created_at BETWEEN :s AND :e GROUP BY u.product_id, u.link_id ORDER BY earned DESC, completed DESC");
        $columns = ['name' => 'Product / link', 'type' => 'Type'];
        break;
    case 'networks':
        $rows = array_map(function ($r) use ($netLabel) {
            $r['name'] = $netLabel($r['name']);
            return $r;
        }, $q("SELECT u.network AS name, COUNT(*) AS opened, SUM($done) AS completed, SUM(CASE WHEN $done THEN u.payout ELSE 0 END) AS earned
            FROM unlocks u WHERE u.created_at BETWEEN :s AND :e AND u.network != 'test' GROUP BY u.network ORDER BY earned DESC"));
        $columns = ['name' => 'Network'];
        break;
    case 'countries':
        $rows = array_map(function ($r) {
            $r['name'] = $r['name'] === '' ? 'Unknown' : flag($r['name']) . ' ' . $r['name'];
            return $r;
        }, $q("SELECT u.country AS name, COUNT(*) AS opened, SUM($done) AS completed, SUM(CASE WHEN $done THEN u.payout ELSE 0 END) AS earned
            FROM unlocks u WHERE u.created_at BETWEEN :s AND :e GROUP BY u.country ORDER BY earned DESC, opened DESC"));
        $columns = ['name' => 'Country'];
        break;
    case 'offers':
        $rows = array_map(function ($r) use ($netLabel) {
            $r['network'] = $netLabel($r['network']);
            return $r;
        }, $q("SELECT MAX(c.offer_name) AS name, c.offer_id, c.network, COUNT(*) AS opened,
                SUM(u.completed_at IS NOT NULL AND u.offer_id = c.offer_id) AS completed,
                SUM(CASE WHEN u.completed_at IS NOT NULL AND u.offer_id = c.offer_id THEN u.payout ELSE 0 END) AS earned
            FROM offer_clicks c LEFT JOIN unlocks u ON u.token = c.token
            WHERE c.created_at BETWEEN :s AND :e GROUP BY c.network, c.offer_id ORDER BY earned DESC, opened DESC"));
        $columns = ['name' => 'Offer', 'offer_id' => 'Offer ID', 'network' => 'Network'];
        break;
}
$openedLabel = $tab === 'offers' ? 'Clicks' : 'Lockers opened';

// ----- CSV export -----
if (isset($_GET['export'])) {
    if ($tab === 'overview') {
        $daily = $q("SELECT date(u.completed_at, 'unixepoch', 'localtime') AS day, COUNT(*) AS completed, SUM(u.payout) AS earned
            FROM unlocks u WHERE $done AND u.completed_at BETWEEN :s AND :e GROUP BY day ORDER BY day");
        csv_download("earnings-$from-to-$to.csv", ['Date', 'Offers completed', 'Earned'], $daily);
    }
    csv_download("$tab-$from-to-$to.csv", array_merge(array_values($columns), [$openedLabel, 'Completed', 'Conversion %', 'Earned']),
        array_map(function ($r) use ($columns) {
            $out = [];
            foreach (array_keys($columns) as $k) {
                $out[] = $r[$k];
            }
            return array_merge($out, [(int)$r['opened'], (int)$r['completed'],
                $r['opened'] ? round($r['completed'] / $r['opened'] * 100, 1) : 0, round((float)$r['earned'], 2)]);
        }, $rows));
}

// ----- Overview numbers -----
$totals = $q("SELECT COUNT(*) AS opened, COALESCE(SUM($done), 0) AS completed, COALESCE(SUM(CASE WHEN $done THEN u.payout END), 0) AS earned
    FROM unlocks u WHERE u.created_at BETWEEN :s AND :e")[0];
$clicks = (int)$q('SELECT COUNT(*) AS n FROM offer_clicks WHERE created_at BETWEEN :s AND :e')[0]['n'];
$leadsByDay = [];
$moneyByDay = [];
for ($t = $start; $t <= $end && count($leadsByDay) < 366; $t = strtotime('+1 day', $t)) {
    $leadsByDay[date('Y-m-d', $t)] = 0;
    $moneyByDay[date('Y-m-d', $t)] = 0;
}
foreach ($q("SELECT date(u.completed_at, 'unixepoch', 'localtime') AS day, COUNT(*) AS n, SUM(u.payout) AS m
        FROM unlocks u WHERE $done AND u.completed_at BETWEEN :s AND :e GROUP BY day") as $r) {
    if (isset($leadsByDay[$r['day']])) {
        $leadsByDay[$r['day']] = (int)$r['n'];
        $moneyByDay[$r['day']] = round((float)$r['m'], 2);
    }
}

$qs = fn(array $over) => '?' . http_build_query(array_merge(['range' => $range, 'from' => $from, 'to' => $to, 'tab' => $tab], $over));

admin_header('Reports', e(date('M j, Y', $start)) . ' – ' . e(date('M j, Y', $end)),
    '<a class="btn btn-light" href="' . e($qs(['export' => 1])) . '">' . icon('download', 16) . ' Export CSV</a>');
?>
<form class="toolbar" method="get">
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <?php foreach (['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', '365' => 'Last year'] as $v => $label): ?>
    <a class="btn <?= $range === $v ? '' : 'btn-light' ?>" href="<?= e('?' . http_build_query(['range' => $v, 'tab' => $tab])) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
  <input type="date" name="from" value="<?= e($from) ?>" style="width:auto;margin:0">
  <input type="date" name="to" value="<?= e($to) ?>" style="width:auto;margin:0">
  <button class="btn <?= $range === 'custom' ? '' : 'btn-light' ?>" name="range" value="custom">Apply</button>
</form>

<div class="stats">
  <div class="stat"><span class="stat-icon"><?= icon('unlock', 22) ?></span><div><span>Lockers opened</span><strong><?= number_format((int)$totals['opened']) ?></strong></div></div>
  <div class="stat"><span class="stat-icon"><?= icon('activity', 22) ?></span><div><span>Offer clicks</span><strong><?= number_format($clicks) ?></strong></div></div>
  <div class="stat"><span class="stat-icon"><?= icon('check', 22) ?></span><div><span>Offers completed</span><strong><?= number_format((int)$totals['completed']) ?></strong></div></div>
  <div class="stat"><span class="stat-icon"><?= icon('trending', 22) ?></span><div><span>Conversion</span><strong><?= $totals['opened'] ? round($totals['completed'] / $totals['opened'] * 100, 1) : 0 ?>%</strong></div></div>
  <div class="stat"><span class="stat-icon"><?= icon('dollar', 22) ?></span><div><span>Earned</span><strong>$<?= number_format((float)$totals['earned'], 2) ?></strong></div></div>
</div>

<nav class="tabs">
  <?php foreach (['overview' => 'Overview', 'products' => 'Products & links', 'networks' => 'Networks', 'countries' => 'Countries', 'offers' => 'Offers'] as $k => $label): ?>
    <a href="<?= e($qs(['tab' => $k])) ?>" class="<?= $tab === $k ? 'active' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($tab === 'overview'): ?>
  <div class="grid-2-even">
    <div class="card"><h2>Offers completed per day</h2><p class="card-sub"><?= array_sum($leadsByDay) ?> total</p>
      <?= bar_chart($leadsByDay, 'completed', 'Bar chart of offers completed per day') ?></div>
    <div class="card"><h2>Earnings per day</h2><p class="card-sub">$<?= number_format(array_sum($moneyByDay), 2) ?> total</p>
      <?= bar_chart($moneyByDay, 'earned', 'Bar chart of earnings per day', '$') ?></div>
  </div>
  <p class="muted">Earnings are the payouts your networks report in their postbacks. Your network dashboard is the final word on what you get paid.</p>
<?php else: ?>
  <?php if (!$rows): ?>
    <div class="card empty"><?= icon('activity', 40) ?><p>No data for this period yet.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr>
      <?php foreach ($columns as $label): ?><th><?= e($label) ?></th><?php endforeach; ?>
      <th class="num"><?= e($openedLabel) ?></th><th class="num">Completed</th><th class="num">Conv.</th><th class="num">Earned</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <?php foreach (array_keys($columns) as $k): ?><td><?= e((string)$r[$k]) ?></td><?php endforeach; ?>
        <td class="num"><?= number_format((int)$r['opened']) ?></td>
        <td class="num"><?= number_format((int)$r['completed']) ?></td>
        <td class="num"><?= $r['opened'] ? round($r['completed'] / $r['opened'] * 100, 1) : 0 ?>%</td>
        <td class="num">$<?= number_format((float)$r['earned'], 2) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <?php if ($tab === 'countries'): ?><p class="muted">Countries are known when your site is behind Cloudflare (tick it in Settings) or your host has GeoIP.</p><?php endif; ?>
  <?php if ($tab === 'offers'): ?><p class="muted">Clicks are counted when a visitor opens an offer in the popup. "Completed" matches the offer ID your network sends in the postback.</p><?php endif; ?>
<?php endif; ?>
<?php admin_footer();
