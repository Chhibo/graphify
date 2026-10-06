<?php
require __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('admin/messages.php');
    $id = (int) post('id');
    if (post('action') === 'delete') {
        db_exec('DELETE FROM ' . tbl('messages') . ' WHERE id = ?', [$id]);
        flash('success', 'Message deleted.');
    } elseif (post('action') === 'unread') {
        db_exec('UPDATE ' . tbl('messages') . ' SET is_read = 0 WHERE id = ?', [$id]);
    }
    redirect('admin/messages.php');
}

$view = isset($_GET['id']) ? db_one('SELECT * FROM ' . tbl('messages') . ' WHERE id = ?', [(int) $_GET['id']]) : null;
if ($view && !$view['is_read']) {
    db_exec('UPDATE ' . tbl('messages') . ' SET is_read = 1 WHERE id = ?', [$view['id']]);
}
$rows = db_all('SELECT * FROM ' . tbl('messages') . ' ORDER BY created_at DESC LIMIT 200');
admin_header('Messages', 'messages');
?>
<div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
    <div class="card xl:col-span-2 overflow-hidden divide-y divide-slate-100">
        <?php foreach ($rows as $m): ?>
            <a href="?id=<?= (int) $m['id'] ?>" class="block p-4 hover:bg-slate-50 <?= $view && $view['id'] === $m['id'] ? 'bg-brand-50' : '' ?>">
                <div class="flex justify-between text-sm"><span class="<?= $m['is_read'] ? 'text-slate-600' : 'font-bold text-slate-900' ?>"><?= !$m['is_read'] ? '<span class="inline-block w-2 h-2 bg-accent-500 rounded-full mr-1"></span>' : '' ?><?= e($m['name']) ?></span><span class="text-xs text-slate-400"><?= e(time_ago($m['created_at'])) ?></span></div>
                <p class="text-xs text-slate-500 truncate"><?= e($m['subject'] ?: excerpt($m['message'], 70)) ?></p>
            </a>
        <?php endforeach; ?>
        <?php if (!$rows): ?><p class="p-10 text-center text-slate-400 text-sm">No messages yet.</p><?php endif; ?>
    </div>
    <div class="xl:col-span-3">
        <?php if ($view): ?>
            <div class="card p-6 space-y-4">
                <div class="flex flex-wrap justify-between gap-3">
                    <div>
                        <h2 class="font-bold text-lg"><?= e($view['subject'] ?: '(no subject)') ?></h2>
                        <p class="text-sm text-slate-500"><?= e($view['name']) ?> · <a class="text-brand-600" href="mailto:<?= e($view['email']) ?>"><?= e($view['email']) ?></a><?= $view['phone'] ? ' · ' . e($view['phone']) : '' ?></p>
                        <p class="text-xs text-slate-400"><?= e(format_date($view['created_at'], 'M j, Y H:i')) ?></p>
                    </div>
                    <div class="flex gap-2 h-fit">
                        <a href="mailto:<?= e($view['email']) ?>?subject=<?= rawurlencode('Re: ' . ($view['subject'] ?: 'Your message')) ?>" class="btn btn-primary"><i class="fa-solid fa-reply"></i> Reply</a>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $view['id'] ?>"><button name="action" value="unread" class="btn btn-light" title="Mark unread"><i class="fa-solid fa-envelope"></i></button></form>
                        <form method="post" data-confirm="Delete this message?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $view['id'] ?>"><button name="action" value="delete" class="btn btn-danger"><i class="fa-solid fa-trash"></i></button></form>
                    </div>
                </div>
                <div class="text-sm text-slate-700 leading-relaxed border-t border-slate-100 pt-4"><?= nl2br(e($view['message'])) ?></div>
            </div>
        <?php else: ?>
            <div class="card p-10 text-center text-slate-400"><i class="fa-regular fa-envelope-open text-4xl mb-2"></i><p class="text-sm">Select a message to read it.</p></div>
        <?php endif; ?>
    </div>
</div>
<?php admin_footer(); ?>
