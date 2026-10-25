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
        if (mb_strlen($d['name']) < 2) $errors['name'] = __('Please enter your name.');
        if (!preg_match('/^[a-zA-Z0-9_.]{3,30}$/', $d['username'])) $errors['username'] = __('Use 3–30 letters, numbers, dots or underscores.');
        elseif (q_val('SELECT id FROM users WHERE username = ? AND id <> ?', [$d['username'], $uid])) $errors['username'] = __('That username is taken.');
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = __('Enter a valid email.');
        elseif (q_val('SELECT id FROM users WHERE email = ? AND id <> ?', [$d['email'], $uid])) $errors['email'] = __('That email is used by another account.');

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
            $emailChanged = $d['email'] !== strtolower($me['email']);
            if ($emailChanged) {
                $d['email_verified_at'] = null;
            }
            db_update('users', $d, 'id = ?', [$uid]);
            if ($emailChanged && setting('require_email_verification') === '1' && $me['role'] !== 'admin') {
                send_verification_email(array_merge($me, $d));
                flash('success', __('Profile saved. We sent a confirmation link to your new email address.'));
            } else {
                if ($emailChanged) {
                    q('UPDATE users SET email_verified_at = ? WHERE id = ?', [now(), $uid]);
                }
                flash('success', __('Your profile has been updated.'));
            }
            redirect('dashboard/settings.php');
        }
        $me = array_merge($me, $d);
    } elseif ($section === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $skipCurrent = !empty($_SESSION['social_login']);
        if (!$skipCurrent && !password_verify($current, $me['password'])) $errors['current_password'] = __('Your current password is incorrect.');
        elseif (strlen($new) < 8) $errors['new_password'] = __('Use at least 8 characters.');
        elseif ($new !== (string) ($_POST['new_password_confirm'] ?? '')) $errors['new_password_confirm'] = __('Passwords do not match.');
        if (!$errors) {
            q('UPDATE users SET password = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $uid]);
            session_regenerate_id(true);
            flash('success', __('Password changed.'));
            redirect('dashboard/settings.php');
        }
    } elseif ($section === 'prefs') {
        $lang = input('lang');
        if (isset(languages_enabled()[$lang])) {
            remember_language($lang);
            current_lang($lang);
        }
        q('UPDATE users SET newsletter = ? WHERE id = ?', [empty($_POST['newsletter']) ? 0 : 1, $uid]);
        flash('success', __('Your preferences have been saved.'));
        redirect('dashboard/settings.php#prefs');
    } elseif ($section === 'unblock') {
        q('DELETE FROM user_blocks WHERE user_id = ? AND blocked_id = ?', [$uid, (int) input('blocked_id')]);
        flash('success', __('Member unblocked.'));
        redirect('dashboard/settings.php#blocked');
    } elseif ($section === 'delete') {
        if (empty($_SESSION['social_login']) && !password_verify((string) ($_POST['confirm_password'] ?? ''), $me['password'])) {
            $errors['confirm_password'] = __('Password is incorrect.');
        } elseif ($me['role'] === 'admin' && (int) q_val("SELECT COUNT(*) FROM users WHERE role = 'admin'") <= 1) {
            $errors['confirm_password'] = __('You are the only administrator. Promote another admin first.');
        } else {
            delete_user_account($uid);
            logout_user();
            start_session();
            session_regenerate_id(true);
            flash('success', __('Your account and all your ads have been deleted.'));
            redirect('index.php');
        }
    }
}

$blocked = q_all('SELECT u.id, u.name, u.username, u.avatar, b.created_at FROM user_blocks b JOIN users u ON u.id = b.blocked_id WHERE b.user_id = ? ORDER BY b.created_at DESC', [$uid]);
$social = q_all('SELECT provider, created_at FROM user_social WHERE user_id = ?', [$uid]);
$err = fn(string $k) => isset($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : '';
$dashActive = 'settings';
$pageTitle = __('Account settings');
require APP_ROOT . '/includes/dash-top.php';
?>
<div class="dash-head"><div><h1><?= t('Account settings') ?></h1><p class="muted"><?= t('Manage your profile, password and account.') ?></p></div></div>

<form method="post" enctype="multipart/form-data" class="card form-section">
  <?= csrf_field() ?><input type="hidden" name="section" value="profile">
  <h2><?= t('Profile') ?></h2>
  <div class="avatar-edit">
    <?= avatar_html($me, 'xl') ?>
    <div>
      <label class="btn btn-ghost btn-sm file-btn"><?= icon('upload') ?><?= t('Upload photo') ?><input type="file" name="avatar" accept="image/*" data-avatar-input></label>
      <?php if ($me['avatar']): ?><label class="check small"><input type="checkbox" name="remove_avatar" value="1"><span><?= t('Remove photo') ?></span></label><?php endif; ?>
      <?= $err('avatar') ?>
    </div>
  </div>
  <div class="form-grid">
    <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>"><label for="s-name"><?= t('Full name') ?></label><input id="s-name" name="name" value="<?= e($me['name']) ?>" required><?= $err('name') ?></div>
    <div class="field<?= isset($errors['username']) ? ' has-error' : '' ?>"><label for="s-user"><?= t('Username') ?></label><input id="s-user" name="username" value="<?= e($me['username']) ?>" required><?= $err('username') ?></div>
    <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>"><label for="s-email"><?= t('Email') ?></label><input id="s-email" type="email" name="email" value="<?= e($me['email']) ?>" required><?= $err('email') ?></div>
    <div class="field"><label for="s-phone"><?= t('Phone') ?></label><input id="s-phone" type="tel" name="phone" value="<?= e($me['phone']) ?>"></div>
  </div>
  <div class="field"><label for="s-loc"><?= t('Location') ?></label><input id="s-loc" name="location" value="<?= e($me['location']) ?>" placeholder="<?= t('City, area') ?>"></div>
  <div class="field"><label for="s-bio"><?= t('About you') ?></label><textarea id="s-bio" name="bio" rows="3" maxlength="1000" placeholder="<?= t('A short introduction shown on your public profile') ?>"><?= e((string) $me['bio']) ?></textarea></div>
  <button class="btn btn-primary" type="submit"><?= t('Save profile') ?></button>
</form>

<form method="post" class="card form-section">
  <?= csrf_field() ?><input type="hidden" name="section" value="password">
  <h2><?= t('Change password') ?></h2>
  <?php if ($social): ?><p class="muted small"><?= icon('info') ?> <?= t('Connected sign-in: {list}.', ['list' => implode(', ', array_map(fn($x) => ucfirst($x['provider']), $social))]) ?><?= !empty($_SESSION['social_login']) ? ' ' . t('You can set a password without entering the old one.') : '' ?></p><?php endif; ?>
  <div class="form-grid three">
    <?php if (empty($_SESSION['social_login'])): ?><div class="field<?= isset($errors['current_password']) ? ' has-error' : '' ?>"><label for="p-cur"><?= t('Current password') ?></label><input id="p-cur" type="password" name="current_password" autocomplete="current-password" required><?= $err('current_password') ?></div><?php endif; ?>
    <div class="field<?= isset($errors['new_password']) ? ' has-error' : '' ?>"><label for="p-new"><?= t('New password') ?></label><input id="p-new" type="password" name="new_password" autocomplete="new-password" minlength="8" required><?= $err('new_password') ?></div>
    <div class="field<?= isset($errors['new_password_confirm']) ? ' has-error' : '' ?>"><label for="p-new2"><?= t('Confirm new password') ?></label><input id="p-new2" type="password" name="new_password_confirm" autocomplete="new-password" required><?= $err('new_password_confirm') ?></div>
  </div>
  <button class="btn btn-primary" type="submit"><?= t('Update password') ?></button>
</form>

<form method="post" class="card form-section" id="prefs">
  <?= csrf_field() ?><input type="hidden" name="section" value="prefs">
  <h2><?= t('Preferences') ?></h2>
  <div class="form-grid">
    <?php if (count(languages_enabled()) > 1): ?>
      <div class="field"><label for="pr-lang"><?= t('Language') ?></label><select id="pr-lang" name="lang">
        <?php foreach (languages_enabled() as $code => $l): ?><option value="<?= e($code) ?>" <?= current_lang() === $code ? 'selected' : '' ?>><?= e($l['name']) ?></option><?php endforeach; ?>
      </select></div>
    <?php endif; ?>
    <div class="field"><label class="switch"><input type="checkbox" name="newsletter" value="1" <?= !empty($me['newsletter']) ? 'checked' : '' ?>><span class="switch-ui"></span><span><?= t('Send me the newsletter with tips and site news') ?></span></label>
      <small class="muted"><?= t('Emails about your own ads and messages are always sent.') ?></small></div>
  </div>
  <?php if ($me['role'] === 'admin'): ?>
    <p class="small"><?= icon('smartphone-key') ?> <?= !empty($me['totp_enabled']) ? t('Two-factor sign-in is on for your account.') : t('Two-factor sign-in is off for your account.') ?> <a href="<?= e(url('admin/security.php')) ?>"><?= t('Manage') ?></a></p>
  <?php endif; ?>
  <button class="btn btn-primary" type="submit"><?= t('Save preferences') ?></button>
</form>

<section class="card form-section" id="blocked">
  <h2><?= t('Blocked members') ?></h2>
  <?php if ($blocked): ?>
    <ul class="mini-list flush">
      <?php foreach ($blocked as $b): ?>
        <li><?= avatar_html($b, 'sm') ?><div class="mini-body"><a href="<?= e(profile_url((int) $b['id'], $b['username'])) ?>"><?= e($b['name']) ?></a><small><?= t('Blocked {when}', ['when' => time_ago($b['created_at'])]) ?></small></div>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="section" value="unblock"><input type="hidden" name="blocked_id" value="<?= (int) $b['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= t('Unblock') ?></button></form></li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="muted small"><?= t('You haven\'t blocked anyone. You can block a member from a conversation or their profile.') ?></p>
  <?php endif; ?>
</section>

<form method="post" class="card form-section danger-zone" data-confirm="<?= t('This will permanently delete your account, ads, messages and reviews. Continue?') ?>">
  <?= csrf_field() ?><input type="hidden" name="section" value="delete">
  <h2><?= t('Delete account') ?></h2>
  <p class="muted"><?= t('Permanently remove your account and everything you\'ve posted. This cannot be undone.') ?></p>
  <?php if (empty($_SESSION['social_login'])): ?><div class="field<?= isset($errors['confirm_password']) ? ' has-error' : '' ?>" style="max-width:320px"><label for="d-pw"><?= t('Confirm with your password') ?></label><input id="d-pw" type="password" name="confirm_password" required><?= $err('confirm_password') ?></div><?php else: ?><?= $err('confirm_password') ?><?php endif; ?>
  <button class="btn btn-danger" type="submit"><?= icon('trash') ?><?= t('Delete my account') ?></button>
</form>
<?php require APP_ROOT . '/includes/dash-bottom.php';
