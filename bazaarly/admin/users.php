<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

if (is_post()) {
    $id = (int) input('id');
    $u = q_one('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$u) {
        flash('error', 'User not found.');
        back('admin/users.php');
    }
    $self = $id === (int) $admin['id'];
    switch (input('action')) {
        case 'ban':
            if ($self) { flash('error', "You can't ban yourself."); break; }
            q("UPDATE users SET status = CASE WHEN status = 'banned' THEN 'active' ELSE 'banned' END WHERE id = ?", [$id]);
            flash('success', $u['status'] === 'banned' ? 'User unbanned.' : 'User banned. Their ads are hidden.');
            break;
        case 'verify':
            q('UPDATE users SET verified = CASE WHEN verified = 1 THEN 0 ELSE 1 END WHERE id = ?', [$id]);
            flash('success', $u['verified'] ? 'Verification removed.' : 'User marked as verified.');
            break;
        case 'role':
            if ($self) { flash('error', "You can't change your own role."); break; }
            q('UPDATE users SET role = ? WHERE id = ?', [$u['role'] === 'admin' ? 'user' : 'admin', $id]);
            flash('success', $u['role'] === 'admin' ? 'Admin rights removed.' : 'User promoted to admin.');
            break;
        case 'delete':
            if ($self) { flash('error', "You can't delete your own account here."); break; }
            delete_user_account($id);
            flash('success', 'User and all their content deleted.');
            redirect('admin/users.php');
    }
    back('admin/users.php');
}

$search = input('q');
$filter = input('filter');
$where = '1 = 1';
$params = [];
if ($search !== '') {
    $where .= ' AND (name LIKE ? OR email LIKE ? OR username LIKE ? OR phone LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%", "%$search%");
}
$where .= match ($filter) {
    'admin' => " AND role = 'admin'",
    'banned' => " AND status = 'banned'",
    'verified' => ' AND verified = 1',
    default => '',
};
$total = (int) q_val("SELECT COUNT(*) FROM users WHERE $where", $params);
[$page, $pages, $offset] = paginate($total, 25, (int) input('page', '1'));
$rows = q_all("SELECT u.*, (SELECT COUNT(*) FROM listings l WHERE l.user_id = u.id) AS ads FROM users u WHERE $where ORDER BY u.created_at DESC LIMIT 25 OFFSET $offset", $params);

$adminActive = 'users';
$pageTitle = 'Users';
require APP_ROOT . '/includes/admin-top.php';
?>
<form class="toolbar" method="get">
  <div class="input-icon grow"><?= icon('search') ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email, username or phone"></div>
  <select name="filter" data-autosubmit>
    <?php foreach (['' => 'All users', 'admin' => 'Admins', 'verified' => 'Verified', 'banned' => 'Banned'] as $k => $lbl): ?><option value="<?= $k ?>" <?= $filter === $k ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
  </select>
  <button class="btn btn-ghost" type="submit">Filter</button>
  <a class="btn btn-primary" href="<?= e(url('admin/user-edit.php')) ?>"><?= icon('plus') ?>Add user</a>
</form>

<div class="card table-card">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Ads</th><th>Joined</th><th>Last sign-in</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $u): ?>
        <tr>
          <td><div class="cell-ad"><?= avatar_html($u, 'sm') ?><div><a href="<?= e(url('admin/user-edit.php?id=' . $u['id'])) ?>"><strong><?= e($u['name']) ?></strong></a><?php if ($u['verified']): ?> <span class="verified" title="Verified"><?= icon('shield') ?></span><?php endif; ?><small class="muted"><?= e($u['email']) ?> · @<?= e($u['username']) ?></small></div></div></td>
          <td><?= $u['role'] === 'admin' ? '<span class="badge badge-brand">Admin</span>' : '<span class="muted small">User</span>' ?></td>
          <td><?= status_badge($u['status']) ?></td>
          <td><a href="<?= e(url('admin/listings.php?user=' . $u['id'])) ?>"><?= (int) $u['ads'] ?></a></td>
          <td class="nowrap muted small"><?= e(format_date($u['created_at'])) ?></td>
          <td class="nowrap muted small"><?= $u['last_login'] ? e(time_ago($u['last_login'])) : 'Never' ?></td>
          <td class="row-actions nowrap">
            <details class="dropdown align-right">
              <summary class="icon-btn sm" aria-label="Actions"><?= icon('settings') ?></summary>
              <div class="dropdown-panel">
                <a href="<?= e(url('admin/user-edit.php?id=' . $u['id'])) ?>"><?= icon('edit') ?>Edit</a>
                <a href="<?= e(url('profile.php?id=' . $u['id'])) ?>" target="_blank"><?= icon('eye') ?>Public profile</a>
                <?php foreach (['verify' => [$u['verified'] ? 'Remove verification' : 'Verify', 'shield'], 'role' => [$u['role'] === 'admin' ? 'Make regular user' : 'Make admin', 'users'], 'ban' => [$u['status'] === 'banned' ? 'Unban' : 'Ban', 'ban']] as $act => [$lbl, $ic]): ?>
                  <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="<?= $act ?>"><button type="submit"><?= icon($ic) ?><?= e($lbl) ?></button></form>
                <?php endforeach; ?>
                <form method="post" data-confirm="Delete <?= e($u['name']) ?> and ALL their ads, messages and reviews?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="delete"><button type="submit" class="danger"><?= icon('trash') ?>Delete</button></form>
              </div>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="7" class="center muted">No users found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?= pagination_links($page, $pages) ?>
<?php require APP_ROOT . '/includes/admin-bottom.php';
