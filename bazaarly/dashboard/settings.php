<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_login();
$uid = (int) $me['id'];
$errors = [];
$section = input('section');

if (is_post()) {
    if ($section === 'profile') {
        $d = [
            'name' => mb_substr(input('name'), 0, 100),
            'username' => input('username'),
            'email' => strtolower(input('email')),
            'phone' => mb_substr(input('phone'), 0, 40),
            'location' => mb_substr(input('location'), 0, 150),
            'bio' => mb_substr(trim((string) ($_POST['bio'] ?? '')), 0, 1000),
        ];
        if (mb_strlen($d['name']) < 2) $errors['name'] = 'Please enter your name.';
        if (!preg_match('/^[a-zA-Z0-9_.]{3,30}$/', $d['username'])) $errors['username'] = 'Use 3–30 letters, numbers, dots or underscores.';
        elseif (q_val('SELECT id FROM users WHERE username = ? AND id <> ?', [$d['username'], $uid])) $errors['username'] = 'That username is taken.';
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email.';
        elseif (q_val('SELECT id FROM users WHERE email = ? AND id <> ?', [$d['email'], $uid])) $errors['email'] = 'That email is used by another account.';

        $avatar = normalize_files($_FILES['avatar'] ?? null);
        if (!$errors && $avatar) {
            $res = store_image($avatar[0], 'avatars', 400, 400);
            if (is_string($res)) {
                $errors['avatar'] = $res;
            } else {
                delete_upload($me['avatar']);
                if ($res['thumb'] !== $res['path']) {
                    delete_upload($res['path']);
                }
                $d['avatar'] = $res['thumb'];
            }
        }
        if (!$errors && !empty($_POST['remove_avatar'])) {
            delete_upload($me['avatar']);
            $d['avatar'] = '';
        }
        if (!$errors) {
            db_update('users', $d, 'id = ?', [$uid]);
            flash('success', 'Your profile has been updated.');
            redirect('dashboard/settings.php');
        }
        $me = array_merge($me, $d);
    } elseif ($section === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify($current, $me['password'])) $errors['current_password'] = 'Your current password is incorrect.';
        elseif (strlen($new) < 8) $errors['new_password'] = 'Use at least 8 characters.';
        elseif ($new !== (string) ($_POST['new_password_confirm'] ?? '')) $errors['new_password_confirm'] = 'Passwords do not match.';
        if (!$errors) {
            q('UPDATE users SET password = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $uid]);
            session_regenerate_id(true);
            flash('success', 'Password changed.');
            redirect('dashboard/settings.php');
        }
    } elseif ($section === 'delete') {
        if (!password_verify((string) ($_POST['confirm_password'] ?? ''), $me['password'])) {
            $errors['confirm_password'] = 'Password is incorrect.';
        } elseif ($me['role'] === 'admin' && (int) q_val("SELECT COUNT(*) FROM users WHERE role = 'admin'") <= 1) {
            $errors['confirm_password'] = 'You are the only administrator. Promote another admin first.';
        } else {
            delete_user_account($uid);
            logout_user();
            start_session();
            session_regenerate_id(true);
            flash('success', 'Your account and all your ads have been deleted.');
            redirect('index.php');
        }
    }
}

$err = fn(string $k) => isset($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : '';
$dashActive = 'settings';
$pageTitle = 'Account settings';
require APP_ROOT . '/includes/dash-top.php';
?>
<div class="dash-head"><div><h1>Account settings</h1><p class="muted">Manage your profile, password and account.</p></div></div>

<form method="post" enctype="multipart/form-data" class="card form-section">
  <?= csrf_field() ?><input type="hidden" name="section" value="profile">
  <h2>Profile</h2>
  <div class="avatar-edit">
    <?= avatar_html($me, 'xl') ?>
    <div>
      <label class="btn btn-ghost btn-sm file-btn"><?= icon('upload') ?>Upload photo<input type="file" name="avatar" accept="image/*" data-avatar-input></label>
      <?php if ($me['avatar']): ?><label class="check small"><input type="checkbox" name="remove_avatar" value="1"><span>Remove photo</span></label><?php endif; ?>
      <?= $err('avatar') ?>
    </div>
  </div>
  <div class="form-grid">
    <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>"><label for="s-name">Full name</label><input id="s-name" name="name" value="<?= e($me['name']) ?>" required><?= $err('name') ?></div>
    <div class="field<?= isset($errors['username']) ? ' has-error' : '' ?>"><label for="s-user">Username</label><input id="s-user" name="username" value="<?= e($me['username']) ?>" required><?= $err('username') ?></div>
    <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>"><label for="s-email">Email</label><input id="s-email" type="email" name="email" value="<?= e($me['email']) ?>" required><?= $err('email') ?></div>
    <div class="field"><label for="s-phone">Phone</label><input id="s-phone" type="tel" name="phone" value="<?= e($me['phone']) ?>"></div>
  </div>
  <div class="field"><label for="s-loc">Location</label><input id="s-loc" name="location" value="<?= e($me['location']) ?>" placeholder="City, area"></div>
  <div class="field"><label for="s-bio">About you</label><textarea id="s-bio" name="bio" rows="3" maxlength="1000" placeholder="A short introduction shown on your public profile"><?= e((string) $me['bio']) ?></textarea></div>
  <button class="btn btn-primary" type="submit">Save profile</button>
</form>

<form method="post" class="card form-section">
  <?= csrf_field() ?><input type="hidden" name="section" value="password">
  <h2>Change password</h2>
  <div class="form-grid three">
    <div class="field<?= isset($errors['current_password']) ? ' has-error' : '' ?>"><label for="p-cur">Current password</label><input id="p-cur" type="password" name="current_password" autocomplete="current-password" required><?= $err('current_password') ?></div>
    <div class="field<?= isset($errors['new_password']) ? ' has-error' : '' ?>"><label for="p-new">New password</label><input id="p-new" type="password" name="new_password" autocomplete="new-password" minlength="8" required><?= $err('new_password') ?></div>
    <div class="field<?= isset($errors['new_password_confirm']) ? ' has-error' : '' ?>"><label for="p-new2">Confirm new password</label><input id="p-new2" type="password" name="new_password_confirm" autocomplete="new-password" required><?= $err('new_password_confirm') ?></div>
  </div>
  <button class="btn btn-primary" type="submit">Update password</button>
</form>

<form method="post" class="card form-section danger-zone" data-confirm="This will permanently delete your account, ads, messages and reviews. Continue?">
  <?= csrf_field() ?><input type="hidden" name="section" value="delete">
  <h2>Delete account</h2>
  <p class="muted">Permanently remove your account and everything you've posted. This cannot be undone.</p>
  <div class="field<?= isset($errors['confirm_password']) ? ' has-error' : '' ?>" style="max-width:320px"><label for="d-pw">Confirm with your password</label><input id="d-pw" type="password" name="confirm_password" required><?= $err('confirm_password') ?></div>
  <button class="btn btn-danger" type="submit"><?= icon('trash') ?>Delete my account</button>
</form>
<?php require APP_ROOT . '/includes/dash-bottom.php';
