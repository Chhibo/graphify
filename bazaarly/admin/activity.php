<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

if (is_post() && input('action') === 'clear') {
    $days = max(0, (int) input('days'));
    $n = q('DELETE FROM activity_log WHERE created_at < ?', [date('Y-m-d H:i:s', time() - $days * 86400)])->rowCount();
    log_activity('activity.clear', "Removed $n entries older than $days days");
    flash('success', "Removed $n log entries.");
    redirect('admin/activity.php');
}

$who = (int) input('user');
$type = input('type');
$search = input('q');
$where = '1 = 1';
$params = [];
if ($who) {
    $where .= ' AND a.user_id = ?';
    $params[] = $who;
}
if ($type !== '') {
    $where .= ' AND a.action LIKE ?';
    $params[] = $type . '.%';
}
if ($search !== '') {
    $where .= ' AND (a.details LIKE ? OR a.action LIKE ?)';
    array_push($params, "%$search%", "%$search%");
}
$total = (int) q_val("SELECT COUNT(*) FROM activity_log a WHERE $where", $params);
[$page, $pages, $offset] = paginate($total, 50, (int) input('page', '1'));
$rows = q_all("SELECT a.*, u.name, u.avatar, u.username FROM activity_log a LEFT JOIN users u ON u.id = a.user_id WHERE $where ORDER BY a.id DESC LIMIT 50 OFFSET $offset", $params);
$admins = q_all("SELECT DISTINCT u.id, u.name FROM activity_log a JOIN users u ON u.id = a.user_id ORDER BY u.name");
$types = ['listing' => 'Ads', 'user' => 'Users', 'category' => 'Categories', 'field' => 'Custom fields', 'location' => 'Locations', 'settings' => 'Settings',
    'page' => 'Pages', 'report' => 'Reports', 'review' => 'Reviews', 'email_template' => 'Email templates', 'backup' => 'Backups', 'import' => 'Imports', 'admin' => 'Sign-ins'];

$link = function (array $r): string {
    $id = (int) $r['subject_id'];
    return match ($r['subject_type']) {
        'listing' => $id ? url('admin/listing-edit.php?id=' . $id) : '',
        'user' => $id ? url('admin/user-edit.php?id=' . $id) : '',
        'category' => $id ? url('admin/category-fields.php?category=' . $id) : '',
        'page' => $id ? url('admin/pages.php?id=' . $id) : '',
        default => '',
    };
};
$iconFor = fn(string $action) => match (strtok($action, '.')) {
    'listing' => 'package', 'user' => 'user', 'category', 'field' => 'folder', 'location' => 'map-pin', 'settings' => 'settings',
    'page' => 'file', 'report' => 'flag', 'review' => 'star', 'email_template', 'contact' => 'mail', 'backup' => 'database',
    'import' => 'upload', 'admin' => 'log-in', default => 'activity',
};

$adminActive = 'activity';
$pageTitle = 'Activity log';
require APP_ROOT . '/includes/admin-top.php';
?>
<form class="toolbar" method="get">
  <div class="input-icon grow"><?= icon('search') ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search the log"></div>
  <select name="user" data-autosubmit><option value="">All admins</option><?php foreach ($admins as $a): ?><option value="<?= (int) $a['id'] ?>" <?= $who === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select>
  <select name="type" data-autosubmit><option value="">Everything</option><?php foreach ($types as $k => $lbl): ?><option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?></select>
  <button class="btn btn-ghost" type="submit">Filter</button>
</form>

<div class="card table-card">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $href = $link($r); ?>
        <tr>
          <td class="nowrap small"><span title="<?= e($r['created_at']) ?>"><?= e(time_ago($r['created_at'])) ?></span></td>
          <td class="nowrap"><?php if ($r['name']): ?><span class="cat-cell"><?= avatar_html(['id' => $r['user_id'], 'name' => $r['name'], 'avatar' => $r['avatar']], 'sm') ?><?= e($r['name']) ?></span><?php else: ?><span class="muted">System</span><?php endif; ?></td>
          <td class="nowrap"><span class="log-action"><?= icon($iconFor($r['action'])) ?><?= e(ucfirst(str_replace(['.', '_'], [' · ', ' '], $r['action']))) ?></span></td>
          <td><?= $href ? '<a href="' . e($href) . '">' . e(excerpt((string) $r['details'], 120)) . '</a>' : e(excerpt((string) $r['details'], 120)) ?></td>
          <td class="muted small nowrap"><?= e($r['ip']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5" class="center muted">Nothing logged yet. Admin actions (approvals, edits, deletions, settings changes…) appear here.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?= pagination_links($page, $pages) ?>

<form method="post" class="card form-section inline-form" data-confirm="Delete old log entries?">
  <?= csrf_field() ?><input type="hidden" name="action" value="clear">
  <span><?= icon('trash') ?> Remove entries older than</span>
  <select name="days"><option value="30">30 days</option><option value="90" selected>90 days</option><option value="365">1 year</option><option value="0">Everything</option></select>
  <button class="btn btn-ghost btn-sm" type="submit">Clean up</button>
  <span class="muted small push">Entries older than <?= (int) setting('activity_log_days', '180') ?> days are removed automatically.</span>
</form>
<?php require APP_ROOT . '/includes/admin-bottom.php';
