<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin_layout.php';
require_admin();

$rows = db()->query('SELECT * FROM postback_log ORDER BY id DESC LIMIT 200')->fetchAll();

admin_header('Postback log');
?>
<p class="muted">Every call from a CPA network shows up here. If a visitor says the download never unlocked, look here first. "unknown token" means the tracking macro in the postback URL is wrong.</p>
<?php if (!$rows): ?>
  <p class="muted">No postbacks received yet. Most networks have a "Test postback" button. Use it after pasting your postback URL.</p>
<?php else: ?>
<table>
  <tr><th>When</th><th>Network</th><th>Result</th><th>From IP</th><th>Data</th></tr>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e(date('Y-m-d H:i:s', (int)$r['created_at'])) ?></td>
      <td><?= e($r['network']) ?></td>
      <td class="<?= $r['result'] === 'unlocked' ? 'ok' : (strpos($r['result'], 'rejected') === 0 ? 'bad' : '') ?>"><?= e($r['result']) ?></td>
      <td><?= e($r['ip']) ?></td>
      <td><code class="wrap"><?= e($r['query']) ?></code></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php admin_footer();
