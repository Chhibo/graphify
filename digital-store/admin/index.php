<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/networks.php';
require dirname(__DIR__) . '/inc/admin_layout.php';
require_admin();

$since = time() - 86400 * 30;
$stats = db()->query("SELECT
        COUNT(*) AS opened,
        SUM(completed_at IS NOT NULL AND network != 'test') AS completed,
        COALESCE(SUM(CASE WHEN network != 'test' THEN payout END), 0) AS earned
    FROM unlocks WHERE created_at >= $since")->fetch();
$byNetwork = db()->query("SELECT network, COUNT(*) AS leads, SUM(payout) AS earned
    FROM unlocks WHERE completed_at IS NOT NULL AND created_at >= $since AND network != 'test'
    GROUP BY network")->fetchAll();
$recent = db()->query('SELECT u.*, p.title FROM unlocks u JOIN products p ON p.id = u.product_id
    WHERE u.completed_at IS NOT NULL ORDER BY u.completed_at DESC LIMIT 15')->fetchAll();
$enabled = array_filter(array_keys(network_definitions()), 'network_enabled');
$productCount = (int)db()->query('SELECT COUNT(*) FROM products')->fetchColumn();

admin_header('Dashboard');
?>
<?php if (is_file(dirname(__DIR__) . '/install.php')): ?>
  <p class="warn">Delete <code>install.php</code> from your hosting. It is locked, but it is safer gone.</p>
<?php endif; ?>
<?php if ($unread = unread_messages()): ?>
  <p class="warn">You have <?= $unread ?> unread message<?= $unread === 1 ? '' : 's' ?>. <a href="messages.php">Read them</a>.</p>
<?php endif; ?>
<?php if (!$enabled): ?>
  <p class="warn">No CPA network is switched on, so visitors will not see any offers. <a href="networks.php">Set up your networks</a>.</p>
<?php endif; ?>
<?php if ($productCount === 0): ?>
  <p class="warn">You have no products yet. <a href="product_edit.php">Add one</a>.</p>
<?php endif; ?>

<div class="stats">
  <div class="stat"><span>Lockers opened (30 days)</span><strong><?= (int)$stats['opened'] ?></strong></div>
  <div class="stat"><span>Offers completed</span><strong><?= (int)$stats['completed'] ?></strong></div>
  <div class="stat"><span>Conversion rate</span><strong><?= $stats['opened'] ? round($stats['completed'] / $stats['opened'] * 100, 1) : 0 ?>%</strong></div>
  <div class="stat"><span>Earned</span><strong>$<?= number_format((float)$stats['earned'], 2) ?></strong></div>
</div>

<?php if ($byNetwork): ?>
<h2>By network (30 days)</h2>
<table>
  <tr><th>Network</th><th>Leads</th><th>Earned</th></tr>
  <?php foreach ($byNetwork as $n): ?>
    <tr><td><?= e(network_definitions()[$n['network']]['label'] ?? $n['network']) ?></td>
        <td><?= (int)$n['leads'] ?></td><td>$<?= number_format((float)$n['earned'], 2) ?></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Latest unlocks</h2>
<?php if (!$recent): ?>
  <p class="muted">None yet.</p>
<?php else: ?>
<table>
  <tr><th>When</th><th>Product</th><th>Network</th><th>Offer</th><th>Payout</th><th>IP</th></tr>
  <?php foreach ($recent as $r): ?>
    <tr>
      <td><?= e(date('Y-m-d H:i', (int)$r['completed_at'])) ?></td>
      <td><?= e($r['title']) ?></td>
      <td><?= e($r['network']) ?></td>
      <td><?= e($r['offer_id']) ?></td>
      <td>$<?= number_format((float)$r['payout'], 2) ?></td>
      <td><?= e($r['ip']) ?></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php admin_footer();
