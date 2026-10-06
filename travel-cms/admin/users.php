<?php
require __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('admin/users.php');
    if (post('action') === 'delete') {
        $id = (int) post('id');
        if ($id === (int) $admin['id']) {
            flash('error', 'You cannot delete your own account.');
        } else {
            db_exec('DELETE FROM ' . tbl('users') . ' WHERE id = ?', [$id]);
            flash('success', 'Administrator removed.');
        }
    } elseif (post('action') === 'add') {
        $name = mb_substr(post('name'), 0, 120);
        $email = strtolower(post('email'));
        $pass = (string) ($_POST['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
            flash('error', 'Enter a name, valid email and a password of at least 8 characters.');
        } elseif (db_value('SELECT COUNT(*) FROM ' . tbl('users') . ' WHERE email = ?', [$email])) {
            flash('error', 'An administrator with that email already exists.');
        } else {
            db_insert('users', ['name' => $name, 'email' => $email, 'password' => password_hash($pass, PASSWORD_DEFAULT), 'role' => 'admin', 'created_at' => now()]);
            flash('success', 'Administrator added.');
        }
    }
    redirect('admin/users.php');
}

$rows = db_all('SELECT id, name, email, last_login, created_at FROM ' . tbl('users') . ' ORDER BY id');
admin_header('Administrators', 'users');
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="card overflow-x-auto lg:col-span-2">
        <table class="tbl">
            <thead><tr><th>Name</th><th>Email</th><th>Last login</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $u): ?>
                <tr>
                    <td class="font-semibold"><?= e($u['name']) ?><?= (int) $u['id'] === (int) $admin['id'] ? ' <span class="text-xs text-brand-600">(you)</span>' : '' ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td class="text-xs text-slate-500"><?= $u['last_login'] ? e(time_ago($u['last_login'])) : 'Never' ?></td>
                    <td class="text-right">
                        <?php if ((int) $u['id'] !== (int) $admin['id']): ?>
                            <form method="post" data-confirm="Remove this administrator?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn btn-danger !px-2.5"><i class="fa-solid fa-trash"></i></button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <form method="post" class="card p-6 space-y-4 h-fit">
        <?= csrf_field() ?><input type="hidden" name="action" value="add">
        <h2 class="font-bold">Add administrator</h2>
        <?php f_input('Name', 'name', '', 'text', 'required'); ?>
        <?php f_input('Email', 'email', '', 'email', 'required'); ?>
        <?php f_input('Password', 'password', '', 'password', 'required minlength="8" autocomplete="new-password"'); ?>
        <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-user-plus"></i> Add</button>
    </form>
</div>
<?php admin_footer(); ?>
