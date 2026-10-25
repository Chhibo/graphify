<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_login();
$uid = (int) $me['id'];

$range = in_array((int) input('range'), [7, 30, 90], true) ? (int) input('range') : 30;
$metric = in_array(input('metric'), STAT_COLUMNS, true) ? input('metric') : 'views';
$adId = (int) input('ad');
$ad = $adId ? q_one('SELECT id, title FROM listings WHERE id = ? AND user_id = ?', [$adId, $uid]) : null;
$adId = $ad ? (int) $ad['id'] : 0;

$labels = [
    'views' => [__('Views'), 'eye', ''],
    'saves' => [__('Saves'), 'heart', 'pink'],
    'messages' => [__('Chats started'), 'message', 'info'],
    'offers' => [__('Offers'), 'hand-coins', 'warn'],
    'phone_reveals' => [__('Phone reveals'), 'phone', ''],
];
$start = date('Y-m-d', strtotime('-' . ($range - 1) . ' days'));
$prevStart = date('Y-m-d', strtotime('-' . (2 * $range - 1) . ' days'));
$adFilter = $adId ? ' AND s.listing_id = ' . $adId : '';
$sumCols = implode(', ', array_map(fn($c) => "COALESCE(SUM(s.$c), 0) AS $c", STAT_COLUMNS));

$totals = q_one("SELECT $sumCols FROM listing_stats s JOIN listings l ON l.id = s.listing_id WHERE l.user_id = ? AND s.day >= ?$adFilter", [$uid, $start]);
$prev = q_one("SELECT $sumCols FROM listing_stats s JOIN listings l ON l.id = s.listing_id WHERE l.user_id = ? AND s.day >= ? AND s.day < ?$adFilter", [$uid, $prevStart, $start]);

$days = [];
for ($i = $range - 1; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i days"))] = 0;
}
foreach (q_all("SELECT s.day, SUM(s.$metric) AS n FROM listing_stats s JOIN listings l ON l.id = s.listing_id WHERE l.user_id = ? AND s.day >= ?$adFilter GROUP BY s.day", [$uid, $start]) as $r) {
    $k = substr((string) $r['day'], 0, 10);
    if (isset($days[$k])) {
        $days[$k] = (int) $r['n'];
    }
}
$peak = max(1, max($days));
$ads = q_all("SELECT l.id, l.title, l.status, l.views AS all_views, l.price, l.price_type,
        (SELECT COUNT(*) FROM favorites f WHERE f.listing_id = l.id) AS fans,
        $sumCols
    FROM listings l LEFT JOIN listing_stats s ON s.listing_id = l.id AND s.day >= ?
    WHERE l.user_id = ? GROUP BY l.id, l.title, l.status, l.views, l.price, l.price_type ORDER BY views DESC, l.id DESC", [$start, $uid]);
$since = q_val('SELECT MIN(s.day) FROM listing_stats s JOIN listings l ON l.id = s.listing_id WHERE l.user_id = ?', [$uid]);

$link = fn(array $over) => '?' . http_build_query(array_filter(array_merge(['range' => $range, 'metric' => $metric, 'ad' => $adId ?: null], $over)));
$change = function (string $c) use ($totals, $prev): string {
    $now = (int) $totals[$c];
    $before = (int) $prev[$c];
    if ($before === 0) {
        return $now > 0 ? '<span class="delta up">' . t('new') . '</span>' : '';
    }
    $pct = (int) round(($now - $before) / $before * 100);
    return '<span class="delta ' . ($pct >= 0 ? 'up' : 'down') . '">' . ($pct >= 0 ? '+' : '') . $pct . '%</span>';
};

$dashActive = 'stats';
$pageTitle = __('Statistics');
require APP_ROOT . '/includes/dash-top.php';
?>
<div class="dash-head">
  <div><h1><?= t('Statistics') ?></h1><p class="muted"><?= $ad ? t('How “{title}” is doing.', ['title' => $ad['title']]) : t('How buyers interact with your ads.') ?></p></div>
  <div class="stats-filters">
    <form method="get" class="inline-form plain">
      <input type="hidden" name="range" value="<?= $range ?>"><input type="hidden" name="metric" value="<?= e($metric) ?>">
      <select name="ad" data-autosubmit aria-label="<?= t('Ad') ?>"><option value=""><?= t('All my ads') ?></option>
        <?php foreach ($ads as $a): ?><option value="<?= (int) $a['id'] ?>" <?= $adId === (int) $a['id'] ? 'selected' : '' ?>><?= e(excerpt($a['title'], 50)) ?></option><?php endforeach; ?></select>
    </form>
    <div class="seg text-seg">
      <?php foreach ([7, 30, 90] as $r): ?><a href="<?= e($link(['range' => $r])) ?>" class="<?= $range === $r ? 'on' : '' ?>"><?= t('{n} days', ['n' => $r]) ?></a><?php endforeach; ?>
    </div>
  </div>
</div>

<div class="stat-grid five">
  <?php foreach ($labels as $col => [$lbl, $ic, $tone]): ?>
    <a class="stat stat-link<?= $metric === $col ? ' on' : '' ?>" href="<?= e($link(['metric' => $col])) ?>">
      <span class="stat-icon <?= $tone ?>"><?= icon($ic) ?></span>
      <div><strong><?= number_format((int) $totals[$col]) ?></strong><span><?= e($lbl) ?> <?= $change($col) ?></span></div>
    </a>
  <?php endforeach; ?>
</div>

<section class="card chart-card">
  <div class="card-head"><h2><?= e($labels[$metric][0]) ?> · <?= t('last {n} days', ['n' => $range]) ?></h2><span class="muted small"><?= t('Compared with the {n} days before', ['n' => $range]) ?></span></div>
  <div class="stats-chart<?= $range > 30 ? ' dense' : '' ?>" role="img" aria-label="<?= t('{metric} per day', ['metric' => $labels[$metric][0]]) ?>">
    <div class="sc-grid"><span><?= number_format($peak) ?></span><span><?= number_format((int) round($peak / 2)) ?></span><span>0</span></div>
    <div class="sc-bars">
      <?php $i = 0; foreach ($days as $day => $n): $i++; ?>
        <div class="sc-col" data-tip="<?= e(format_date($day)) ?> · <?= e($labels[$metric][0]) ?>: <?= number_format($n) ?>" tabindex="0">
          <span class="sc-bar" style="height:<?= $n ? max(2, round($n / $peak * 100)) : 0 ?>%"></span>
          <?php if ($range <= 7 || $i % ($range > 30 ? 15 : 5) === 1 || $i === count($days)): ?><span class="sc-label"><?= e(localize_date(date('j M', strtotime($day)))) ?></span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php if ($since): ?><p class="muted small chart-note"><?= t('Daily statistics are collected since {date}.', ['date' => format_date((string) $since)]) ?></p><?php endif; ?>
</section>

<section class="card table-card">
  <div class="card-head"><h2><?= t('Your ads') ?></h2><span class="muted small"><?= t('last {n} days', ['n' => $range]) ?></span></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th><?= t('Ad') ?></th><?php foreach ($labels as $col => [$lbl]): ?><th class="num"><?= e($lbl) ?></th><?php endforeach; ?><th class="num"><?= t('Saved by') ?></th><th class="num"><?= t('All-time views') ?></th></tr></thead>
      <tbody>
      <?php foreach ($ads as $a): ?>
        <tr class="<?= $adId === (int) $a['id'] ? 'row-unread' : '' ?>">
          <td><a href="<?= e($link(['ad' => $a['id']])) ?>"><strong><?= e(excerpt($a['title'], 50)) ?></strong></a> <?= status_badge($a['status']) ?></td>
          <?php foreach (STAT_COLUMNS as $col): ?><td class="num"><?= number_format((int) $a[$col]) ?></td><?php endforeach; ?>
          <td class="num"><?= (int) $a['fans'] ?></td>
          <td class="num"><?= number_format((int) $a['all_views']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$ads): ?><tr><td colspan="8" class="center muted"><?= t('Post an ad to start seeing statistics.') ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<p class="muted small"><?= t('Tip: ads with 5 or more clear photos and a fair price get more chats. Ads that get views but no chats may need a lower price or better photos.') ?></p>
<?php require APP_ROOT . '/includes/dash-bottom.php';
