<?php
require __DIR__ . '/includes/auth.php';
require_admin('owner');

$who = (int) ($_GET['who'] ?? 0);
$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 50;
$where = [];
$params = [];
if ($who) {
    $where[] = 'admin_id = ?';
    $params[] = $who;
}
if ($search !== '') {
    $where[] = '(action LIKE ? OR details LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$total = (int) q_val('SELECT COUNT(*) FROM activity_log' . $sqlWhere, $params);
$rows = q_all('SELECT * FROM activity_log' . $sqlWhere . ' ORDER BY id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), $params);
$admins = q_all('SELECT id, name, email FROM admins ORDER BY name');
$pages = max(1, (int) ceil($total / $per));
$adminTitle = 'Activity log';
include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <form class="toolbar" method="get">
    <select name="who" onchange="this.form.submit()">
      <option value="">Everyone</option>
      <?php foreach ($admins as $a): ?><option value="<?= (int) $a['id'] ?>" <?= $who === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?>
    </select>
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search (e.g. order number, product)" class="grow-form">
    <button class="btn btn-light" type="submit">Search</button>
  </form>
  <p class="help">Every change made in the admin, by whom and when. Page views are not logged. Kept for <?= (int) setting('activity_log_days', '180') ?: '∞' ?> days (<a href="settings.php?tab=security">change</a>).</p>
  <?php if ($rows): ?>
    <div class="table-wrap"><table>
      <thead><tr><th>When</th><th>Who</th><th>What</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="nowrap"><?= e(date('M j, Y H:i', strtotime($r['created_at']))) ?></td>
          <td><?= e($r['admin_name'] !== '' ? $r['admin_name'] : '-') ?></td>
          <td><b><?= e($r['action']) ?></b><?php if (trim((string) $r['details']) !== ''): ?><br><small class="muted"><?= e($r['details']) ?></small><?php endif; ?></td>
          <td><small class="muted"><?= e($r['ip']) ?></small></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php if ($pages > 1): ?>
      <div class="pager">
        <?php for ($i = 1; $i <= $pages && $i <= 30; $i++): ?>
          <a class="<?= $i === $page ? 'active' : '' ?>" href="?<?= e(http_build_query(['who' => $who ?: null, 'q' => $search ?: null, 'page' => $i])) ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php else: ?><p class="muted">Nothing logged yet.</p><?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
