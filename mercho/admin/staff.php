<?php
require __DIR__ . '/includes/auth.php';
$me = require_admin('owner');

if (is_post()) {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? 'save';
    $target = $id ? q_one('SELECT * FROM admins WHERE id = ?', [$id]) : null;
    if ($action === 'delete') {
        if ($target && (int) $target['id'] !== (int) $me['id']) {
            q('DELETE FROM admins WHERE id = ?', [$id]);
            flash('success', 'Staff account deleted.');
        }
        redirect('admin/staff.php');
    }
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $pass = (string) ($_POST['password'] ?? '');
    $isOwner = post_flag('is_owner');
    $perms = array_values(array_intersect(array_keys(admin_sections()), (array) ($_POST['perms'] ?? [])));
    if ($target && (int) $target['id'] === (int) $me['id']) {
        $isOwner = 1; // you cannot remove your own owner access
    }
    $error = '';
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a name and a valid email.';
    } elseif ((int) q_val('SELECT COUNT(*) FROM admins WHERE email = ? AND id <> ?', [$email, $id]) > 0) {
        $error = 'Another admin already uses this email.';
    } elseif (!$target && strlen($pass) < 8) {
        $error = 'Please set a password of at least 8 characters.';
    } elseif ($pass !== '' && strlen($pass) < 8) {
        $error = 'The password must be at least 8 characters.';
    }
    if ($error !== '') {
        flash('error', $error);
        redirect('admin/staff.php' . ($id ? '?edit=' . $id : ''));
    }
    $data = ['name' => mb_substr($name, 0, 120), 'email' => $email, 'is_owner' => $isOwner, 'permissions' => implode(',', $perms)];
    if ($pass !== '') {
        $data['password'] = password_hash($pass, PASSWORD_DEFAULT);
    }
    if ($target) {
        db_update('admins', $id, $data);
    } else {
        db_insert('admins', $data + ['created_at' => now()]);
    }
    flash('success', 'Staff account saved. They log in at ' . full_url('admin/') . ' with their email and password.');
    redirect('admin/staff.php');
}

$edit = isset($_GET['edit']) ? q_one('SELECT * FROM admins WHERE id = ?', [(int) $_GET['edit']]) : null;
$rows = q_all('SELECT * FROM admins ORDER BY is_owner DESC, id');
$adminTitle = 'Staff accounts';
include __DIR__ . '/includes/header.php';
$sections = admin_sections();
?>
<div class="grid-main">
  <div class="card">
    <p class="muted">Give team members their own login with access only to what they need (for example, orders and customers but not payments or settings).</p>
    <div class="table-wrap"><table>
      <thead><tr><th>Name</th><th>Email</th><th>Access</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><b><?= e($r['name']) ?></b><?= (int) $r['id'] === (int) $me['id'] ? ' <small class="muted">(you)</small>' : '' ?></td>
          <td><?= e($r['email']) ?></td>
          <td><?php if ((int) $r['is_owner']): ?><span class="status st-delivered">Owner - full access</span>
            <?php else: foreach (array_filter(explode(',', $r['permissions'])) as $pp): ?><span class="tag"><?= e(ucfirst($pp)) ?></span><?php endforeach; endif; ?></td>
          <td class="actions">
            <a class="btn btn-sm btn-light" href="?edit=<?= (int) $r['id'] ?>">Edit</a>
            <?php if ((int) $r['id'] !== (int) $me['id']): ?>
              <form method="post" data-confirm="Delete the account of <?= e($r['name']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-danger-light">Delete</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <div class="card-head"><h2><?= $edit ? 'Edit account' : 'Add staff member' ?></h2></div>
    <label>Name<input name="name" value="<?= e($edit['name'] ?? '') ?>" required></label>
    <label>Email (login)<input type="email" name="email" value="<?= e($edit['email'] ?? '') ?>" required></label>
    <label>Password <small class="muted"><?= $edit ? '(leave empty to keep)' : '(min. 8)' ?></small><input type="text" name="password" autocomplete="new-password" <?= $edit ? '' : 'required minlength="8"' ?>></label>
    <?php $isSelf = $edit && (int) $edit['id'] === (int) $me['id']; ?>
    <label class="inline"><input type="checkbox" name="is_owner" value="1" data-toggle-target="#perm-box" <?= (int) ($edit['is_owner'] ?? 0) ? 'checked' : '' ?> <?= $isSelf ? 'disabled checked' : '' ?>> Owner (full access, can manage staff & backups)</label>
    <div id="perm-box" <?= (int) ($edit['is_owner'] ?? 0) ? 'hidden' : '' ?>>
      <h3 class="sub">Access to</h3>
      <?php $have = explode(',', (string) ($edit['permissions'] ?? 'orders')); ?>
      <?php foreach ($sections as $k => $label): ?>
        <label class="inline small"><input type="checkbox" name="perms[]" value="<?= e($k) ?>" <?= in_array($k, $have, true) ? 'checked' : '' ?>> <?= e($label) ?></label>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-primary btn-block" type="submit">Save</button>
    <?php if ($edit): ?><a class="btn btn-light btn-block" href="staff.php">Cancel</a><?php endif; ?>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
