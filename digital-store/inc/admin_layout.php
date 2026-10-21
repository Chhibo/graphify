<?php
declare(strict_types=1);

/**
 * $actions: extra HTML (buttons) shown at the top right, e.g. "+ Add product".
 */
function admin_header(string $title, string $subtitle = '', string $actions = ''): void
{
    $unread = unread_messages();
    $groups = [
        'Store' => [
            'index.php' => ['Dashboard', 'dashboard'],
            'products.php' => ['Products', 'package'],
            'categories.php' => ['Categories', 'folder'],
            'pages.php' => ['Pages', 'file'],
            'messages.php' => ['Messages', 'mail'],
            'subscribers.php' => ['Subscribers', 'user'],
        ],
        'Money' => [
            'reports.php' => ['Reports', 'trending'],
            'links.php' => ['Link locker', 'link'],
            'networks.php' => ['CPA Networks', 'plug'],
            'postbacks.php' => ['Postback log', 'activity'],
        ],
        'Setup' => [
            'appearance.php' => ['Appearance', 'palette'],
            'growth.php' => ['Alerts, ads & email', 'megaphone'],
            'import.php' => ['Import products', 'database'],
            'settings.php' => ['Settings', 'sliders'],
        ],
    ];
    $current = basename($_SERVER['SCRIPT_NAME']);
    // Edit pages highlight their list page.
    $current = ['product_edit.php' => 'products.php', 'page_edit.php' => 'pages.php'][$current] ?? $current;
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?> · Admin</title>
<link rel="stylesheet" href="<?= e(base_url('assets/admin.css')) ?>?v=3">
<?php if (valid_color(setting('color_accent'))): ?><style>:root{--accent:<?= setting('color_accent') ?>}</style><?php endif; ?>
</head>
<body>
<aside class="sidebar" id="sidebar">
  <a class="side-brand" href="<?= e(base_url('admin/')) ?>"><span class="mark"><?= icon('gift', 18) ?></span><span><?= e(setting('store_name')) ?></span></a>
  <nav class="side-nav">
    <?php foreach ($groups as $group => $items): ?>
      <div class="side-group"><?= e($group) ?></div>
      <?php foreach ($items as $file => [$label, $ico]): ?>
        <a href="<?= e($file) ?>" class="<?= $current === $file ? 'active' : '' ?>">
          <?= icon($ico) ?> <?= e($label) ?>
          <?php if ($file === 'messages.php' && $unread): ?><span class="count"><?= $unread ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>
  <nav class="side-nav side-bottom">
    <a href="<?= e(base_url()) ?>" target="_blank"><?= icon('external') ?> View store</a>
    <a href="logout.php"><?= icon('logout') ?> Log out</a>
  </nav>
</aside>
<div class="side-backdrop" onclick="document.body.classList.remove('side-open')"></div>
<div class="admin-wrap">
  <header class="topbar">
    <button class="icon-btn side-toggle" type="button" aria-label="Menu" onclick="document.body.classList.toggle('side-open')"><?= icon('menu') ?></button>
    <div>
      <h1><?= e($title) ?></h1>
      <?php if ($subtitle !== ''): ?><p class="sub"><?= $subtitle ?></p><?php endif; ?>
    </div>
    <div class="topbar-actions">
      <?= $actions ?>
      <a class="btn btn-light hide-sm" href="<?= e(base_url()) ?>" target="_blank"><?= icon('external', 16) ?> Store</a>
    </div>
  </header>
  <main class="admin-main">
    <?php if ($m = flash()): ?><div class="toast" role="status"><?= icon('circle-check') ?> <?= e($m) ?></div><?php endif; ?>
<?php
}

function unread_messages(): int
{
    return (int)db()->query('SELECT COUNT(*) FROM messages WHERE is_read = 0')->fetchColumn();
}

function admin_footer(): void
{
    echo "  </main>\n</div>\n</body>\n</html>\n";
}

/** A highlighted message with an optional link on the right. $text is HTML. */
function notice(string $text, string $linkUrl = '', string $linkText = '', string $type = 'warn', string $ico = 'megaphone'): string
{
    $link = $linkUrl !== '' ? '<a href="' . e($linkUrl) . '">' . e($linkText) . ' →</a>' : '';
    return '<div class="notice notice-' . $type . '">' . icon($ico) . ' <span>' . $text . '</span>' . $link . '</div>';
}

/**
 * One-series bar chart with a hover tooltip. $series: ['Y-m-d' => number].
 */
function bar_chart(array $series, string $unit, string $aria, string $prefix = ''): string
{
    static $n = 0;
    $id = 'chart' . (++$n);
    $max = max(1, max($series ?: [0]));
    $mag = 10 ** floor(log10($max));
    $niceMax = 10 * $mag;
    foreach ([1, 2, 5, 10] as $m) {
        if ($m * $mag >= $max) {
            $niceMax = $m * $mag;
            break;
        }
    }
    $niceMax = max(4, $niceMax);
    $W = 700; $H = 220; $L = 40; $B = 26; $T = 8;
    $plotH = $H - $B - $T;
    $count = max(1, count($series));
    $slot = ($W - $L) / $count;
    $barW = max(2, $slot - max(2, min(8, $slot * .3)));
    $r = min(4, $barW / 2);
    $every = max(1, (int)ceil($count / 7));
    $fmt = fn($v) => $prefix . rtrim(rtrim(number_format($v, $prefix ? 2 : 1), '0'), '.');
    ob_start(); ?>
    <div class="chart" id="<?= $id ?>">
      <svg viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="<?= e($aria) ?>">
        <?php for ($g = 0; $g <= 4; $g++): $y = $T + $plotH - $plotH * $g / 4; ?>
          <line class="grid-line" x1="<?= $L ?>" x2="<?= $W ?>" y1="<?= $y ?>" y2="<?= $y ?>"/>
          <text class="axis-label" x="<?= $L - 8 ?>" y="<?= $y + 4 ?>" text-anchor="end"><?= e($fmt($niceMax * $g / 4)) ?></text>
        <?php endfor; ?>
        <?php $i = 0; foreach ($series as $d => $v):
            $x = $L + $i * $slot + ($slot - $barW) / 2;
            $h = $v > 0 ? max($r + 1, $plotH * $v / $niceMax) : 0;
            $y = $T + $plotH - $h; ?>
          <g class="col" data-tip="<?= e(date('D, M j', strtotime($d)) . ': ' . $fmt((float)$v) . ' ' . $unit) ?>" data-x="<?= ($x + $barW / 2) / $W ?>" data-y="<?= $y / $H ?>">
            <rect class="hit" x="<?= $L + $i * $slot ?>" y="<?= $T ?>" width="<?= $slot ?>" height="<?= $plotH ?>"/>
            <?php if ($h): ?><path class="bar" d="M<?= $x ?>,<?= $T + $plotH ?> V<?= $y + $r ?> Q<?= $x ?>,<?= $y ?> <?= $x + $r ?>,<?= $y ?> H<?= $x + $barW - $r ?> Q<?= $x + $barW ?>,<?= $y ?> <?= $x + $barW ?>,<?= $y + $r ?> V<?= $T + $plotH ?> Z"/><?php endif; ?>
            <?php if (($count - 1 - $i) % $every === 0): ?><text class="axis-label" x="<?= $x + $barW / 2 ?>" y="<?= $H - 6 ?>" text-anchor="middle"><?= e(date('M j', strtotime($d))) ?></text><?php endif; ?>
          </g>
        <?php $i++; endforeach; ?>
      </svg>
      <div class="chart-tip"></div>
    </div>
    <script>
    (function () {
      var c = document.getElementById('<?= $id ?>'), tip = c.querySelector('.chart-tip');
      c.querySelectorAll('.col').forEach(function (g) {
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
    <?php return (string)ob_get_clean();
}
