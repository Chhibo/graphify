<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

$id = (int) input('id');
$user = $id ? q_one('SELECT * FROM users WHERE id = ?', [$id]) : null;
if ($id && !$user) {
    flash('error', 'User not found.');
    redirect('admin/users.php');
}
$self = $user && (int) $user['id'] === (int) $admin['id'];
$errors = [];
$d = $user ?? ['name' => '', 'username' => '', 'email' => '', 'phone' => '', 'location' => '', 'bio' => '', 'role' => 'user', 'status' => 'active', 'verified' => 0];

if (is_post()) {
    $d = array_merge($d, [
        'name' => mb_substr(input('name'), 0, 100),
        'username' => input('username'),
        'email' => strtolower(input('email')),
        'phone' => mb_substr(input('phone'), 0, 40),
        'location' => mb_substr(input('location'), 0, 150),
        'bio' => mb_substr(trim((string) ($_POST['bio'] ?? '')), 0, 1000),
        'role' => $self ? $d['role'] : (input('role') === 'admin' ? 'admin' : 'user'),
        'status' => $self ? 'active' : (input('status') === 'banned' ? 'banned' : 'active'),
        'verified' => empty($_POST['verified']) ? 0 : 1,
    ]);
    $pw = (string) ($_POST['password'] ?? '');
    if (mb_strlen($d['name']) < 2) $errors['name'] = 'Name is required.';
    if (!preg_match('/^[a-zA-Z0-9_.]{3,30}$/', $d['username'])) $errors['username'] = 'Use 3–30 letters, numbers, dots or underscores.';
    elseif (q_val('SELECT id FROM users WHERE username = ? AND id <> ?', [$d['username'], $id])) $errors['username'] = 'Username taken.';
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email.';
    elseif (q_val('SELECT id FROM users WHERE email = ? AND id <> ?', [$d['email'], $id])) $errors['email'] = 'Email already in use.';
    if (!$user && strlen($pw) < 8) $errors['password'] = 'Set a password of at least 8 characters.';
    if ($user && $pw !== '' && strlen($pw) < 8) $errors['password'] = 'Use at least 8 characters.';

    if (!$errors) {
        $data = array_intersect_key($d, array_flip(['name', 'username', 'email', 'phone', 'location', 'bio', 'role', 'status', 'verified']));
        if ($pw !== '') {
            $data['password'] = password_hash($pw, PASSWORD_DEFAULT);
        }
        if ($user) {
            db_update('users', $data, 'id = ?', [$id]);
            flash('success', 'User saved.');
        } else {
            $id = db_insert('users', $data + ['avatar' => '', 'created_at' => now()]);
            flash('success', 'User created.');
        }
        redirect('admin/user-edit.php?id=' . $id);
    }
}
$stats = $user ? [
    'ads' => (int) q_val('SELECT COUNT(*) FROM listings WHERE user_id = ?', [$id]),
    'active' => (int) q_val("SELECT COUNT(*) FROM listings WHERE user_id = ? AND status = 'active'", [$id]),
    'reviews' => seller_rating($id),
] : null;
$err = fn(string $k) => isset($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : '';

$adminActive = 'users';
$pageTitle = $user ? 'Edit user' : 'Add user';
require APP_ROOT . '/includes/admin-top.php';
?>
<div class="page-actions">
  <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/users.php')) ?>"><?= icon('chevron-left') ?>All users</a>
  <?php if ($user): ?>
    <span class="push"></span>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/listings.php?user=' . $id)) ?>"><?= icon('package') ?>Their ads (<?= $stats['ads'] ?>)</a>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('profile.php?id=' . $id)) ?>" target="_blank"><?= icon('eye') ?>Public profile</a>
  <?php endif; ?>
</div>

<div class="admin-grid wide-left">
  <form method="post" class="card form-section">
    <?= csrf_field() ?>
    <h2>Account details</h2>
    <div class="form-grid">
      <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>"><label for="u-name">Full name</label><input id="u-name" name="name" value="<?= e($d['name']) ?>" required><?= $err('name') ?></div>
      <div class="field<?= isset($errors['username']) ? ' has-error' : '' ?>"><label for="u-user">Username</label><input id="u-user" name="username" value="<?= e($d['username']) ?>" required><?= $err('username') ?></div>
      <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>"><label for="u-email">Email</label><input id="u-email" type="email" name="email" value="<?= e($d['email']) ?>" required><?= $err('email') ?></div>
      <div class="field"><label for="u-phone">Phone</label><input id="u-phone" name="phone" value="<?= e($d['phone']) ?>"></div>
    </div>
    <div class="field"><label for="u-loc">Location</label><input id="u-loc" name="location" value="<?= e($d['location']) ?>"></div>
    <div class="field"><label for="u-bio">Bio</label><textarea id="u-bio" name="bio" rows="3"><?= e((string) $d['bio']) ?></textarea></div>
    <div class="form-grid">
      <div class="field"><label for="u-role">Role</label><select id="u-role" name="role" <?= $self ? 'disabled' : '' ?>><option value="user">User</option><option value="admin" <?= $d['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option></select></div>
      <div class="field"><label for="u-status">Status</label><select id="u-status" name="status" <?= $self ? 'disabled' : '' ?>><option value="active">Active</option><option value="banned" <?= $d['status'] === 'banned' ? 'selected' : '' ?>>Banned</option></select></div>
    </div>
    <label class="switch"><input type="checkbox" name="verified" value="1" <?= $d['verified'] ? 'checked' : '' ?>><span class="switch-ui"></span><span>Verified seller badge</span></label>
    <div class="field<?= isset($errors['password']) ? ' has-error' : '' ?>"><label for="u-pw"><?= $user ? 'New password <small class="muted">(leave blank to keep)</small>' : 'Password' ?></label><input id="u-pw" type="password" name="password" autocomplete="new-password"><?= $err('password') ?></div>
    <button class="btn btn-primary" type="submit"><?= $user ? 'Save user' : 'Create user' ?></button>
  </form>

  <?php if ($user): ?>
    <div>
      <section class="card side-card center">
        <?= avatar_html($user, 'xl') ?>
        <h3><?= e($user['name']) ?></h3>
        <p class="muted small">Joined <?= e(format_date($user['created_at'])) ?><br>Last sign-in <?= $user['last_login'] ? e(time_ago($user['last_login'])) : 'never' ?></p>
        <ul class="kv-list">
          <li><span>Total ads</span><strong><?= $stats['ads'] ?></strong></li>
          <li><span>Active ads</span><strong><?= $stats['active'] ?></strong></li>
          <li><span>Rating</span><strong><?= $stats['reviews'][1] ? $stats['reviews'][0] . ' (' . $stats['reviews'][1] . ')' : '—' ?></strong></li>
        </ul>
      </section>
      <?php if (!$self): ?>
        <form method="post" action="<?= e(url('admin/users.php')) ?>" class="card form-section danger-zone" data-confirm="Delete this user and ALL their content?">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="delete">
          <h2>Danger zone</h2><p class="muted small">Deletes the account with all ads, photos, messages and reviews.</p>
          <button class="btn btn-danger btn-block" type="submit"><?= icon('trash') ?>Delete user</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php require APP_ROOT . '/includes/admin-bottom.php';
