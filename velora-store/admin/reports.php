<?php
require __DIR__ . '/includes/auth.php';
require_admin('reports');

/* ---------- Period ---------- */
$ranges = ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', '365' => 'Last 12 months'];
$range = isset($ranges[$_GET['range'] ?? '']) ? $_GET['range'] : '30';
$from = strtotime($_GET['from'] ?? '') ?: strtotime('-' . ((int) $range - 1) . ' days', strtotime('today'));
$to = strtotime($_GET['to'] ?? '') ?: strtotime('today');
if ($to < $from) {
    [$from, $to] = [$to, $from];
}
$custom = isset($_GET['from']) && $_GET['from'] !== '';
$days = (int) round(($to - $from) / 86400) + 1;
$fromSql = date('Y-m-d 00:00:00', $from);
$toSql = date('Y-m-d 23:59:59', $to);
$prevFromSql = date('Y-m-d 00:00:00', $from - $days * 86400);
$prevToSql = date('Y-m-d 23:59:59', $from - 86400);

// A sale = a confirmed order (paid online, or cash on delivery) that was not cancelled.
$sale = "payment_status IN ('paid','cod') AND status NOT IN ('cancelled','unconfirmed')";

$kpi = function (string $a, string $b) use ($sale): array {
    $r = q_one("SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS revenue, COALESCE(SUM(discount),0) AS discount FROM orders WHERE $sale AND created_at BETWEEN ? AND ?", [$a, $b]);
    $items = (int) q_val("SELECT COALESCE(SUM(i.qty),0) FROM order_items i JOIN orders o ON o.id = i.order_id WHERE $sale AND o.created_at BETWEEN ? AND ?", [$a, $b]);
    return ['orders' => (int) $r['n'], 'revenue' => (float) $r['revenue'], 'aov' => $r['n'] ? (float) $r['revenue'] / $r['n'] : 0, 'items' => $items];
};
$now = $kpi($fromSql, $toSql);
$prev = $kpi($prevFromSql, $prevToSql);

/* ---------- Revenue over time: by day (≤ 62 days), else by month ---------- */
$byMonth = $days > 62;
$buckets = [];
for ($t = $from; $t <= $to; $t = strtotime($byMonth ? '+1 month' : '+1 day', $t)) {
    $key = date($byMonth ? 'Y-m' : 'Y-m-d', $t);
    $buckets[$key] = ['label' => date($byMonth ? 'M Y' : 'M j', $t), 'value' => 0.0, 'orders' => 0];
    if ($byMonth) {
        $t = strtotime(date('Y-m-01', $t));
    }
}
foreach (q_all("SELECT created_at, total FROM orders WHERE $sale AND created_at BETWEEN ? AND ?", [$fromSql, $toSql]) as $o) {
    $key = substr($o['created_at'], 0, $byMonth ? 7 : 10);
    if (isset($buckets[$key])) {
        $buckets[$key]['value'] += (float) $o['total'];
        $buckets[$key]['orders']++;
    }
}

/* ---------- Rankings ---------- */
$topProducts = q_all("SELECT i.name, SUM(i.qty) AS qty, SUM(i.qty * i.price) AS revenue FROM order_items i JOIN orders o ON o.id = i.order_id
    WHERE $sale AND o.created_at BETWEEN ? AND ? GROUP BY i.name ORDER BY revenue DESC LIMIT 10", [$fromSql, $toSql]);
$topCustomers = q_all("SELECT MAX(customer_name) AS name, phone, COUNT(*) AS n, SUM(total) AS spent FROM orders
    WHERE $sale AND created_at BETWEEN ? AND ? GROUP BY phone ORDER BY spent DESC LIMIT 10", [$fromSql, $toSql]);
$byPayment = q_all("SELECT payment_method AS k, COUNT(*) AS n, SUM(total) AS v FROM orders WHERE $sale AND created_at BETWEEN ? AND ? GROUP BY payment_method ORDER BY v DESC", [$fromSql, $toSql]);
$byCity = q_all("SELECT MAX(city) AS k, COUNT(*) AS n, SUM(total) AS v FROM orders WHERE $sale AND created_at BETWEEN ? AND ? GROUP BY LOWER(city) ORDER BY n DESC LIMIT 8", [$fromSql, $toSql]);
$coupons = q_all("SELECT coupon_code AS k, COUNT(*) AS n, SUM(discount) AS v FROM orders WHERE $sale AND coupon_code <> '' AND created_at BETWEEN ? AND ? GROUP BY coupon_code ORDER BY n DESC", [$fromSql, $toSql]);
$statuses = q_all("SELECT status AS k, COUNT(*) AS n FROM orders WHERE payment_status <> 'unpaid' AND created_at BETWEEN ? AND ? GROUP BY status", [$fromSql, $toSql]);

/** Compact money for axis ticks: $950, $1.2K, $3.4M */
function money_compact(float $v): string
{
    $sym = setting('currency_symbol', '$');
    $after = setting('currency_position') === 'after';
    $n = $v >= 1e6 ? round($v / 1e6, 1) . 'M' : ($v >= 1e3 ? round($v / 1e3, 1) . 'K' : (string) round($v));
    return $after ? $n . ' ' . $sym : $sym . $n;
}

/** Signed change vs. the previous period. Up is good for every metric on this page. */
function delta_html(float $now, float $prev): string
{
    if ($prev <= 0) {
        return $now > 0 ? '<span class="delta up">New</span>' : '<span class="delta">-</span>';
    }
    $pct = ($now - $prev) / $prev * 100;
    $cls = $pct > 0.5 ? 'up' : ($pct < -0.5 ? 'down' : '');
    return '<span class="delta ' . $cls . '">' . ($pct > 0 ? '▲ +' : ($pct < 0 ? '▼ ' : '')) . number_format($pct, 0) . '%</span>';
}

/** Column chart (single series) as inline SVG: ≤24px columns, 4px rounded tops, hairline grid. */
function column_chart(array $buckets): string
{
    $W = 900;
    $H = 260;
    $padL = 52;
    $padB = 26;
    $padT = 10;
    $n = max(1, count($buckets));
    $max = max(1.0, max(array_column($buckets, 'value')));
    // Round the top tick to a clean number
    $mag = 10 ** floor(log10($max));
    $top = ceil($max / $mag * 2) / 2 * $mag;
    $plotW = $W - $padL - 6;
    $plotH = $H - $padB - $padT;
    $band = $plotW / $n;
    $barW = min(24, max(3, $band - 2)); // 2px surface gap between touching columns
    $svg = '<svg class="chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Revenue over time">';
    for ($i = 0; $i <= 4; $i++) {
        $y = $padT + $plotH - $plotH * $i / 4;
        $svg .= '<line class="grid" x1="' . $padL . '" x2="' . ($W - 6) . '" y1="' . $y . '" y2="' . $y . '"/>'
            . '<text class="tick" x="' . ($padL - 8) . '" y="' . ($y + 4) . '" text-anchor="end">' . e(money_compact($top * $i / 4)) . '</text>';
    }
    $labelEvery = (int) ceil($n / 10);
    $i = 0;
    foreach ($buckets as $b) {
        $x = $padL + $band * $i + ($band - $barW) / 2;
        $h = $b['value'] > 0 ? max(2, $plotH * $b['value'] / $top) : 0;
        $y = $padT + $plotH - $h;
        $r = min(4, $barW / 2, $h);
        $tip = e($b['label'] . ': ' . money($b['value']) . ' · ' . $b['orders'] . ' order' . ($b['orders'] === 1 ? '' : 's'));
        // Invisible full-height hit area so small columns are easy to hover
        $svg .= '<g class="col" data-tip="' . $tip . '"><rect class="hit" x="' . ($padL + $band * $i) . '" y="' . $padT . '" width="' . $band . '" height="' . $plotH . '"/>';
        if ($h > 0) {
            $svg .= '<path class="bar" d="M' . $x . ',' . ($y + $h) . ' V' . ($y + $r) . ' Q' . $x . ',' . $y . ' ' . ($x + $r) . ',' . $y
                . ' H' . ($x + $barW - $r) . ' Q' . ($x + $barW) . ',' . $y . ' ' . ($x + $barW) . ',' . ($y + $r) . ' V' . ($y + $h) . ' Z"/>';
        }
        $svg .= '</g>';
        if ($i % $labelEvery === 0) {
            $svg .= '<text class="tick" x="' . ($padL + $band * $i + $band / 2) . '" y="' . ($H - 8) . '" text-anchor="middle">' . e($b['label']) . '</text>';
        }
        $i++;
    }
    $svg .= '<line class="axis" x1="' . $padL . '" x2="' . ($W - 6) . '" y1="' . ($padT + $plotH) . '" y2="' . ($padT + $plotH) . '"/></svg>';
    return $svg;
}

/** Horizontal bars for a ranking (magnitude, one hue). */
function bar_list(array $rows, string $valueKey, callable $fmt, callable $label): string
{
    if (!$rows) {
        return '<p class="muted">No data for this period.</p>';
    }
    $max = max(1.0, max(array_map(fn($r) => (float) $r[$valueKey], $rows)));
    $html = '<div class="hbars">';
    foreach ($rows as $r) {
        $pct = (float) $r[$valueKey] / $max * 100;
        $html .= '<div class="hbar" data-tip="' . e($label($r) . ': ' . $fmt($r)) . '"><div class="hbar-top"><span>' . e($label($r)) . '</span><b>' . e($fmt($r)) . '</b></div>'
            . '<div class="hbar-track"><span style="width:' . max(1, round($pct, 1)) . '%"></span></div></div>';
    }
    return $html . '</div>';
}

$adminTitle = 'Reports';
include __DIR__ . '/includes/header.php';
?>
<form class="report-filters" method="get">
  <?php foreach ($ranges as $k => $l): ?>
    <a class="btn btn-sm <?= !$custom && $range === (string) $k ? 'btn-primary' : 'btn-light' ?>" href="?range=<?= $k ?>"><?= e($l) ?></a>
  <?php endforeach; ?>
  <span class="custom-range">
    <input type="date" name="from" value="<?= e(date('Y-m-d', $from)) ?>" aria-label="From"> →
    <input type="date" name="to" value="<?= e(date('Y-m-d', $to)) ?>" aria-label="To">
    <button class="btn btn-sm btn-light" type="submit">Apply</button>
  </span>
</form>

<div class="stats">
  <div class="stat"><span>Revenue</span><strong><?= money($now['revenue']) ?></strong><?= delta_html($now['revenue'], $prev['revenue']) ?></div>
  <div class="stat"><span>Orders</span><strong><?= number_format($now['orders']) ?></strong><?= delta_html($now['orders'], $prev['orders']) ?></div>
  <div class="stat"><span>Average order</span><strong><?= money($now['aov']) ?></strong><?= delta_html($now['aov'], $prev['aov']) ?></div>
  <div class="stat"><span>Items sold</span><strong><?= number_format($now['items']) ?></strong><?= delta_html($now['items'], $prev['items']) ?></div>
</div>
<p class="muted small" style="margin:-8px 0 16px"><?= e(date('M j, Y', $from)) ?> - <?= e(date('M j, Y', $to)) ?> · compared with the previous <?= $days ?> days. Counts confirmed orders (paid or cash on delivery), without cancelled ones.</p>

<div class="card">
  <div class="card-head"><h2>Revenue by <?= $byMonth ? 'month' : 'day' ?></h2></div>
  <div class="chart-wrap"><?= column_chart($buckets) ?><div class="chart-tip" hidden></div></div>
  <details class="table-view"><summary>Show as table</summary>
    <div class="table-wrap"><table><thead><tr><th><?= $byMonth ? 'Month' : 'Day' ?></th><th>Orders</th><th>Revenue</th></tr></thead><tbody>
      <?php foreach (array_reverse($buckets) as $b): ?><tr><td><?= e($b['label']) ?></td><td><?= (int) $b['orders'] ?></td><td><?= money($b['value']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </details>
</div>

<div class="report-grid">
  <div class="card"><div class="card-head"><h2>Best-selling products</h2></div>
    <?= bar_list($topProducts, 'revenue', fn($r) => money($r['revenue']) . ' · ' . (int) $r['qty'] . ' sold', fn($r) => $r['name']) ?></div>
  <div class="card"><div class="card-head"><h2>Best customers</h2></div>
    <?= bar_list($topCustomers, 'spent', fn($r) => money($r['spent']) . ' · ' . (int) $r['n'] . ' order' . ((int) $r['n'] === 1 ? '' : 's'), fn($r) => $r['name']) ?></div>
  <div class="card"><div class="card-head"><h2>Payment methods</h2></div>
    <?= bar_list($byPayment, 'v', fn($r) => money($r['v']) . ' · ' . (int) $r['n'], fn($r) => payment_method_label($r['k'])) ?></div>
  <div class="card"><div class="card-head"><h2>Top cities</h2></div>
    <?= bar_list($byCity, 'n', fn($r) => (int) $r['n'] . ' orders · ' . money($r['v']), fn($r) => $r['k'] !== '' ? $r['k'] : '-') ?></div>
  <div class="card"><div class="card-head"><h2>Coupons used</h2></div>
    <?= bar_list($coupons, 'n', fn($r) => (int) $r['n'] . ' orders · -' . money($r['v']), fn($r) => $r['k']) ?></div>
  <div class="card"><div class="card-head"><h2>Orders by status</h2></div>
    <?php if ($statuses): ?>
      <?php foreach ($statuses as $st): ?><div class="kv"><span><?= admin_status_badge($st['k']) ?></span><b><?= (int) $st['n'] ?></b></div><?php endforeach; ?>
    <?php else: ?><p class="muted">No data for this period.</p><?php endif; ?>
  </div>
</div>

<script>
// Hover tooltip for columns and bars
(function () {
  var tip = document.querySelector('.chart-tip'), wrap = document.querySelector('.chart-wrap');
  document.querySelectorAll('.col').forEach(function (g) {
    g.addEventListener('mousemove', function (e) {
      var box = wrap.getBoundingClientRect();
      tip.textContent = g.getAttribute('data-tip'); tip.hidden = false;
      tip.style.left = Math.min(box.width - 180, Math.max(0, e.clientX - box.left + 12)) + 'px';
      tip.style.top = (e.clientY - box.top - 40) + 'px';
      g.classList.add('on');
    });
    g.addEventListener('mouseleave', function () { tip.hidden = true; g.classList.remove('on'); });
  });
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
