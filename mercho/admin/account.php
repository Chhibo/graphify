<?php
require __DIR__ . '/includes/auth.php';
$admin = require_admin();

if (is_post()) {
    verify_csrf();
    $row = q_one('SELECT * FROM admins WHERE id = ?', [$admin['id']]);
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $new = (string) ($_POST['new_password'] ?? '');
    if (!password_verify((string) ($_POST['current_password'] ?? ''), $row['password'])) {
        flash('error', 'Your current password is not correct.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') {
        flash('error', 'Please enter a name and a valid email.');
    } elseif ($new !== '' && strlen($new) < 8) {
        flash('error', 'The new password must be at least 8 characters.');
    } else {
        $data = ['name' => $name, 'email' => $email];
        if ($new !== '') {
            $data['password'] = password_hash($new, PASSWORD_DEFAULT);
        }
        db_update('admins', (int) $admin['id'], $data);
        flash('success', 'Account updated.');
    }
    redirect('admin/account.php');
}
$adminTitle = 'My account';
include __DIR__ . '/includes/header.php';
?>
<form method="post" class="card narrow">
  <?= csrf_field() ?>
  <label>Name<input name="name" value="<?= e($admin['name']) ?>" required></label>
  <label>Email (login)<input type="email" name="email" value="<?= e($admin['email']) ?>" required></label>
  <label>New password <small class="muted">(leave empty to keep the current one)</small><input type="password" name="new_password" minlength="8" autocomplete="new-password"></label>
  <label>Current password *<input type="password" name="current_password" required autocomplete="current-password"></label>
  <button class="btn btn-primary" type="submit">Save</button>
</form>
<?php include __DIR__ . '/includes/footer.php'; ?>
