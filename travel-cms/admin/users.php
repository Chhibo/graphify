<?php
require __DIR__ . '/includes/auth.php';
require_permission('users');

$roles = admin_roles();
$adminCount = fn () => (int) db_value('SELECT COUNT(*) FROM ' . tbl('users') . " WHERE role = 'admin'");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('admin/users.php');
    $id = (int) post('id');
    $target = $id ? db_one('SELECT id, role FROM ' . tbl('users') . ' WHERE id = ?', [$id]) : null;
    $action = post('action');

    if ($action === 'delete' && $target) {
        if ($id === (int) $admin['id']) {
            flash('error', 'You cannot delete your own account.');
        } elseif ($target['role'] === 'admin' && $adminCount() <= 1) {
            flash('error', 'You cannot delete the last administrator.');
        } else {
            db_exec('DELETE FROM ' . tbl('users') . ' WHERE id = ?', [$id]);
            flash('success', 'Staff account removed.');
        }
    } elseif ($action === 'role' && $target && isset($roles[post('role')])) {
        if ($id === (int) $admin['id']) {
            flash('error', 'You cannot change your own role.');
        } elseif ($target['role'] === 'admin' && post('role') !== 'admin' && $adminCount() <= 1) {
            flash('error', 'At least one administrator is required.');
        } else {
            db_update('users', ['role' => post('role')], $id);
            flash('success', 'Role updated.');
        }
    } elseif ($action === 'password' && $target) {
        $pass = (string) ($_POST['password'] ?? '');
        if (strlen($pass) < 8) {
            flash('error', 'The new password must be at least 8 characters.');
        } else {
            db_update('users', ['password' => password_hash($pass, PASSWORD_DEFAULT)], $id);
            flash('success', 'Password changed.');
        }
    } elseif ($action === 'add') {
        $name = mb_substr(post('name'), 0, 120);
        $email = strtolower(post('email'));
        $pass = (string) ($_POST['password'] ?? '');
        $role = isset($roles[post('role')]) ? post('role') : 'agent';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
            flash('error', 'Enter a name, valid email and a password of at least 8 characters.');
        } elseif (db_value('SELECT COUNT(*) FROM ' . tbl('users') . ' WHERE email = ?', [$email])) {
            flash('error', 'A staff account with that email already exists.');
        } else {
            db_insert('users', ['name' => $name, 'email' => $email, 'password' => password_hash($pass, PASSWORD_DEFAULT), 'role' => $role, 'created_at' => now()]);
            flash('success', 'Staff account added.');
        }
    }
    redirect('admin/users.php');
}

$rows = db_all('SELECT id, name, email, role, last_login, created_at FROM ' . tbl('users') . ' ORDER BY id');
admin_header('Staff accounts', 'users');
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="card overflow-x-auto">
            <table class="tbl">
                <thead><tr><th>Name</th><th>Role</th><th>Last login</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $u): $self = (int) $u['id'] === (int) $admin['id']; ?>
                    <tr>
                        <td><div class="font-semibold"><?= e($u['name']) ?><?= $self ? ' <span class="text-xs text-brand-600">(you)</span>' : '' ?></div><div class="text-xs text-slate-400"><?= e($u['email']) ?></div></td>
                        <td>
                            <?php if ($self): ?>
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-brand-100 text-brand-700"><?= e(role_label($u['role'])) ?></span>
                            <?php else: ?>
                                <form method="post" class="flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <select name="role" class="inp !py-1.5 !w-auto" onchange="this.form.submit()">
                                        <?php foreach ($roles as $key => [$label]): ?><option value="<?= $key ?>" <?= $u['role'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                                    </select>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td class="text-xs text-slate-500 whitespace-nowrap"><?= $u['last_login'] ? e(time_ago($u['last_login'])) : 'Never' ?></td>
                        <td class="text-right whitespace-nowrap">
                            <?php if (!$self): ?>
                                <form method="post" class="inline-flex gap-1" onsubmit="var p = prompt('New password for <?= e(addslashes($u['name'])) ?> (min 8 characters):'); if (!p) return false; this.password.value = p;">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="password">
                                    <button class="btn btn-light !px-2.5" title="Set password"><i class="fa-solid fa-key"></i></button>
                                </form>
                                <form method="post" class="inline" data-confirm="Remove this staff account?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn btn-danger !px-2.5" title="Remove"><i class="fa-solid fa-trash"></i></button></form>
                            <?php else: ?>
                                <a href="<?= e(url('admin/profile.php')) ?>" class="btn btn-light !px-2.5" title="My profile"><i class="fa-solid fa-user-pen"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card p-6">
            <h2 class="font-bold mb-3">What each role can do</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <?php foreach ($roles as $key => [$label, $desc]): ?>
                    <div class="bg-slate-50 rounded-xl p-4"><p class="font-bold mb-1"><?= e($label) ?></p><p class="text-xs text-slate-500"><?= e($desc) ?></p></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <form method="post" class="card p-6 space-y-4 h-fit">
        <?= csrf_field() ?><input type="hidden" name="action" value="add">
        <h2 class="font-bold">Add staff member</h2>
        <?php f_input('Name', 'name', '', 'text', 'required'); ?>
        <?php f_input('Email (used to log in)', 'email', '', 'email', 'required'); ?>
        <?php f_input('Password', 'password', '', 'password', 'required minlength="8" autocomplete="new-password"'); ?>
        <?php f_select('Role', 'role', array_map(fn ($r) => $r[0], $roles), 'agent'); ?>
        <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-user-plus"></i> Add</button>
    </form>
</div>
<?php admin_footer(); ?>
