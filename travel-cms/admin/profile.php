<?php
require __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('admin/profile.php');
    $me = db_one('SELECT * FROM ' . tbl('users') . ' WHERE id = ?', [$admin['id']]);
    $name = mb_substr(post('name'), 0, 120);
    $email = strtolower(post('email'));
    $new = (string) ($_POST['new_password'] ?? '');
    if (!password_verify((string) ($_POST['current_password'] ?? ''), $me['password'])) {
        flash('error', 'Your current password is incorrect.');
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Enter your name and a valid email.');
    } elseif (db_value('SELECT COUNT(*) FROM ' . tbl('users') . ' WHERE email = ? AND id <> ?', [$email, $me['id']])) {
        flash('error', 'That email is used by another administrator.');
    } elseif ($new !== '' && (strlen($new) < 8 || $new !== ($_POST['confirm_password'] ?? ''))) {
        flash('error', 'New password must be at least 8 characters and match the confirmation.');
    } else {
        $data = ['name' => $name, 'email' => $email];
        if ($new !== '') {
            $data['password'] = password_hash($new, PASSWORD_DEFAULT);
        }
        db_update('users', $data, (int) $me['id']);
        session_regenerate_id(true);
        flash('success', 'Profile updated.');
    }
    redirect('admin/profile.php');
}

admin_header('My profile', 'profile');
?>
<form method="post" class="card p-6 space-y-4 max-w-xl">
    <?= csrf_field() ?>
    <?php f_input('Name', 'name', $admin['name'], 'text', 'required'); ?>
    <?php f_input('Email (login)', 'email', $admin['email'], 'email', 'required'); ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <?php f_input('New password', 'new_password', '', 'password', 'minlength="8" autocomplete="new-password"', 'Leave empty to keep your current password.'); ?>
        <?php f_input('Confirm new password', 'confirm_password', '', 'password', 'autocomplete="new-password"'); ?>
    </div>
    <?php f_input('Current password (required to save)', 'current_password', '', 'password', 'required autocomplete="current-password"'); ?>
    <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Update profile</button>
</form>
<?php admin_footer(); ?>
