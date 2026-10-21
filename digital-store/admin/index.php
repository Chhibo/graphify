<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/networks.php';
require dirname(__DIR__) . '/inc/admin_layout.php';
require_admin();

$since = time() - 86400 * 30;
$stats = db()->query("SELECT
        COUNT(*) AS opened,
        COALESCE(SUM(completed_at IS NOT NULL AND network != 'test'), 0) AS completed,
        COALESCE(SUM(CASE WHEN network != 'test' THEN payout END), 0) AS earned
    FROM unlocks WHERE created_at >= $since")->fetch();
$views = (int)db()->query('SELECT COALESCE(SUM(views), 0) FROM products')->fetchColumn();

// Unlocks per day for the last 14 days (server time).
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i days"))] = 0;
}
$q = db()->prepare("SELECT date(completed_at, 'unixepoch', 'localtime') AS d, COUNT(*) AS n FROM unlocks
    WHERE completed_at >= ? AND network != 'test' GROUP BY d");
$q->execute([strtotime(array_key_first($days))]);
foreach ($q as $row) {
    if (isset($days[$row['d']])) {
        $days[$row['d']] = (int)$row['n'];
    }
}

$byNetwork = db()->query("SELECT network, COUNT(*) AS leads, SUM(payout) AS earned
    FROM unlocks WHERE completed_at IS NOT NULL AND created_at >= $since AND network != 'test'
    GROUP BY network ORDER BY earned DESC")->fetchAll();
$top = db()->query("SELECT p.id, p.title, p.image, p.views,
        COUNT(u.id) AS leads, COALESCE(SUM(u.payout), 0) AS earned
    FROM products p JOIN unlocks u ON u.product_id = p.id
    WHERE u.completed_at IS NOT NULL AND u.network != 'test' AND u.created_at >= $since
    GROUP BY p.id ORDER BY leads DESC LIMIT 5")->fetchAll();
$recent = db()->query('SELECT u.*, p.title FROM unlocks u JOIN products p ON p.id = u.product_id
    WHERE u.completed_at IS NOT NULL ORDER BY u.completed_at DESC LIMIT 8')->fetchAll();

$enabled = array_filter(array_keys(network_definitions()), 'network_enabled');
$productCount = (int)db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
$unread = unread_messages();

// Getting-started checklist.
$hasKey = (bool)array_filter(array_keys(network_definitions()), fn($n) => network_setting($n, 'api_key') !== '');
$checklist = [
    ['Add your CPA API key', $hasKey, 'networks.php'],
    ['Switch on a CPA network', (bool)$enabled, 'networks.php'],
    ['Paste the postback URL into your network', (bool)db()->query("SELECT 1 FROM postback_log LIMIT 1")->fetchColumn(), 'networks.php'],
    ['Add your first product', $productCount > 0, 'product_edit.php'],
    ['Create a category', (bool)db()->query('SELECT 1 FROM categories LIMIT 1')->fetchColumn(), 'categories.php'],
    ['Upload your logo', setting('logo') !== '', 'appearance.php'],
    ['Set your contact email', setting('contact_email') !== '', 'settings.php#contact'],
    ['Delete install.php from your hosting', !is_file(dirname(__DIR__) . '/install.php'), ''],
];
$doneCount = count(array_filter($checklist, fn($c) => $c[1]));

admin_header('Dashboard', 'Last 30 days at a glance',
    '<a class="btn btn-grad" href="product_edit.php">' . icon('plus', 16) . ' Add product</a>');
?>
<?php if ($unread): ?><?= notice("You have <b>$unread</b> unread message" . ($unread === 1 ? '' : 's') . '.', 'messages.php', 'Read', 'warn', 'mail') ?><?php endif; ?>
<?php if (!$enabled): ?><?= notice('No CPA network is switched on, so visitors will not see any offers.', 'networks.php', 'Set up networks', 'bad', 'plug') ?><?php endif; ?>

<div class="stats">
  <div class="stat"><span class="stat-icon"><?= icon('eye', 22) ?></span><div><span>Product views (all time)</span><strong><?= number_format($views) ?></strong></div></div>
  <div class="stat"><span class="stat-icon"><?= icon('unlock', 22) ?></span><div><span>Lockers opened</span><strong><?= number_format((int)$stats['opened']) ?></strong></div></div>
  <div class="stat"><span class="stat-icon"><?= icon('check', 22) ?></span><div><span>Offers completed</span><strong><?= number_format((int)$stats['completed']) ?></strong></div></div>
  <div class="stat"><span class="stat-icon"><?= icon('trending', 22) ?></span><div><span>Conversion rate</span><strong><?= $stats['opened'] ? round($stats['completed'] / $stats['opened'] * 100, 1) : 0 ?>%</strong></div></div>
  <div class="stat"><span class="stat-icon"><?= icon('dollar', 22) ?></span><div><span>Earned</span><strong>$<?= number_format((float)$stats['earned'], 2) ?></strong></div></div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><div><h2>Offers completed per day</h2><p class="muted">Last 14 days · <?= array_sum($days) ?> total</p></div></div>
    <?php
    $max = max(1, max($days));
    $mag = 10 ** floor(log10($max));
    foreach ([1, 2, 5, 10] as $m) {
        if ($m * $mag >= $max) { $niceMax = $m * $mag; break; }
    }
    $niceMax = max(4, (int)$niceMax);
    $W = 700; $H = 220; $L = 34; $B = 26; $T = 8;
    $plotH = $H - $B - $T;
    $slot = ($W - $L) / count($days);
    $barW = max(6, $slot - 8);
    ?>
    <div class="chart" id="chart">
      <svg viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Bar chart of offers completed per day for the last 14 days">
        <?php for ($g = 0; $g <= 4; $g++): $y = $T + $plotH - $plotH * $g / 4; ?>
          <line class="grid-line" x1="<?= $L ?>" x2="<?= $W ?>" y1="<?= $y ?>" y2="<?= $y ?>"/>
          <text class="axis-label" x="<?= $L - 8 ?>" y="<?= $y + 4 ?>" text-anchor="end"><?= rtrim(rtrim(number_format($niceMax * $g / 4, 1), '0'), '.') ?></text>
        <?php endfor; ?>
        <?php $i = 0; foreach ($days as $d => $n):
            $x = $L + $i * $slot + ($slot - $barW) / 2;
            $h = $n ? max(4, $plotH * $n / $niceMax) : 0;
            $y = $T + $plotH - $h; ?>
          <g class="col" data-tip="<?= e(date('D, M j', strtotime($d))) ?>: <?= $n ?> completed" data-x="<?= ($x + $barW / 2) / $W ?>" data-y="<?= $y / $H ?>">
            <rect class="hit" x="<?= $L + $i * $slot ?>" y="<?= $T ?>" width="<?= $slot ?>" height="<?= $plotH ?>"/>
            <?php if ($h): ?><path class="bar" d="M<?= $x ?>,<?= $T + $plotH ?> V<?= $y + 4 ?> Q<?= $x ?>,<?= $y ?> <?= $x + 4 ?>,<?= $y ?> H<?= $x + $barW - 4 ?> Q<?= $x + $barW ?>,<?= $y ?> <?= $x + $barW ?>,<?= $y + 4 ?> V<?= $T + $plotH ?> Z"/><?php endif; ?>
            <?php if ($i % 2 === 1 || count($days) <= 7): ?><text class="axis-label" x="<?= $x + $barW / 2 ?>" y="<?= $H - 6 ?>" text-anchor="middle"><?= e(date('M j', strtotime($d))) ?></text><?php endif; ?>
          </g>
        <?php $i++; endforeach; ?>
      </svg>
      <div class="chart-tip" id="chart-tip"></div>
    </div>
  </div>

  <div class="card">
    <h2>Getting started</h2>
    <p class="card-sub"><?= $doneCount ?> of <?= count($checklist) ?> done</p>
    <div class="progress"><span style="width:<?= round($doneCount / count($checklist) * 100) ?>%"></span></div>
    <ul class="checklist">
      <?php foreach ($checklist as [$label, $done, $link]): ?>
        <li class="<?= $done ? 'done' : '' ?>"><?= icon($done ? 'circle-check' : 'circle', 18) ?> <?= e($label) ?>
          <?php if (!$done && $link): ?><a href="<?= e($link) ?>">Do it</a><?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>

<div class="grid-2-even">
  <div class="card">
    <div class="card-head"><h2>Top products</h2><a href="products.php" class="muted">All products →</a></div>
    <?php if (!$top): ?><p class="muted">No completed offers in the last 30 days yet.</p><?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Product</th><th class="num">Leads</th><th class="num">Earned</th></tr></thead>
      <tbody>
      <?php foreach ($top as $t): ?>
        <tr>
          <td><div class="prod-cell"><?php if ($t['image']): ?><img class="mini" src="../uploads/<?= e($t['image']) ?>" alt=""><?php else: ?><span class="mini-empty"><?= icon('package') ?></span><?php endif; ?>
            <a href="product_edit.php?id=<?= (int)$t['id'] ?>"><?= e($t['title']) ?></a></div></td>
          <td class="num"><?= (int)$t['leads'] ?></td>
          <td class="num">$<?= number_format((float)$t['earned'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h2>By network</h2><a href="networks.php" class="muted">Networks →</a></div>
    <?php if (!$byNetwork): ?><p class="muted">No leads yet.</p><?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Network</th><th class="num">Leads</th><th class="num">Earned</th></tr></thead>
      <tbody>
      <?php foreach ($byNetwork as $n): ?>
        <tr><td><?= e(network_definitions()[$n['network']]['label'] ?? $n['network']) ?></td>
            <td class="num"><?= (int)$n['leads'] ?></td><td class="num">$<?= number_format((float)$n['earned'], 2) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>Latest unlocks</h2><a href="postbacks.php" class="muted">Postback log →</a></div>
  <?php if (!$recent): ?><p class="muted">None yet. Open a product while logged in and use "Simulate completed offer" to test.</p><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>When</th><th>Product</th><th>Network</th><th>Offer</th><th class="num">Payout</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $r): ?>
      <tr>
        <td><?= e(date('M j, H:i', (int)$r['completed_at'])) ?></td>
        <td><?= e($r['title']) ?></td>
        <td><span class="tag <?= $r['network'] === 'test' ? '' : 'tag-accent' ?>"><?= e($r['network']) ?></span></td>
        <td><?= e($r['offer_id']) ?></td>
        <td class="num">$<?= number_format((float)$r['payout'], 2) ?></td>
        <td class="muted"><?= e($r['ip']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<script>
(function () {
  var chart = document.getElementById('chart'), tip = document.getElementById('chart-tip');
  chart.querySelectorAll('.col').forEach(function (g) {
    g.addEventListener('mouseenter', function () {
      tip.textContent = g.dataset.tip;
      tip.style.left = (g.dataset.x * 100) + '%';
      tip.style.top = (g.dataset.y * 100) + '%';
      tip.classList.add('show');
    });
    g.addEventListener('mouseleave', function () { tip.classList.remove('show'); });
  });
})();
</script>
<?php admin_footer();
