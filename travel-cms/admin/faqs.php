<?php
require __DIR__ . '/includes/auth.php';
require_permission('content');

$tourId = (int) ($_GET['tour'] ?? 0);
$tour = $tourId ? db_one('SELECT id, title, slug FROM ' . tbl('tours') . ' WHERE id = ?', [$tourId]) : null;
$tourId = $tour ? (int) $tour['id'] : 0;
$self = 'admin/faqs.php' . ($tourId ? '?tour=' . $tourId : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf($self);
    $id = (int) post('id');
    if (post('action') === 'delete' && $id) {
        db_exec('DELETE FROM ' . tbl('faqs') . ' WHERE id = ?', [$id]);
        delete_translations('faq', $id);
        flash('success', 'Question deleted.');
    } elseif (post('action') === 'save') {
        $data = [
            'tour_id' => $tourId ?: null,
            'category' => $tourId ? '' : mb_substr(post('category'), 0, 100),
            'question' => mb_substr(post('question'), 0, 500),
            'answer' => post('answer'),
            'sort_order' => (int) post('sort_order'),
            'status' => post('status') === 'hidden' ? 'hidden' : 'active',
        ];
        if ($data['question'] === '' || $data['answer'] === '') {
            flash('error', 'Question and answer are required.');
            redirect($self . ($id ? ($tourId ? '&' : '?') . 'edit=' . $id : ''));
        }
        if ($id) {
            db_update('faqs', $data, $id);
        } else {
            $id = db_insert('faqs', $data + ['created_at' => now()]);
        }
        save_translations_from_post('faq', $id);
        flash('success', 'Question saved.');
    }
    redirect($self);
}

$rows = $tourId
    ? db_all('SELECT * FROM ' . tbl('faqs') . ' WHERE tour_id = ? ORDER BY sort_order, id', [$tourId])
    : db_all('SELECT * FROM ' . tbl('faqs') . ' WHERE tour_id IS NULL ORDER BY category, sort_order, id');
$edit = isset($_GET['edit']) ? db_one('SELECT * FROM ' . tbl('faqs') . ' WHERE id = ?', [(int) $_GET['edit']]) : null;
$f = $edit ?: ['id' => 0, 'category' => '', 'question' => '', 'answer' => '', 'sort_order' => count($rows), 'status' => 'active'];
$categories = array_values(array_unique(array_filter(array_column(db_all('SELECT category FROM ' . tbl('faqs') . ' WHERE tour_id IS NULL'), 'category'))));
$tripsWithFaq = db_all('SELECT t.id, t.title, COUNT(f.id) AS n FROM ' . tbl('tours') . ' t JOIN ' . tbl('faqs') . ' f ON f.tour_id = t.id GROUP BY t.id, t.title ORDER BY t.title');

admin_header($tour ? 'FAQs: ' . $tour['title'] : 'FAQs', 'faqs');
?>
<div class="flex flex-wrap items-center gap-2 mb-4 text-xs font-bold">
    <a href="<?= e(url('admin/faqs.php')) ?>" class="px-3 py-2 rounded-xl <?= !$tourId ? 'bg-slate-900 text-white' : 'bg-white text-slate-600' ?>">General FAQ page</a>
    <?php foreach ($tripsWithFaq as $t): ?>
        <a href="<?= e(url('admin/faqs.php?tour=' . $t['id'])) ?>" class="px-3 py-2 rounded-xl <?= $tourId === (int) $t['id'] ? 'bg-slate-900 text-white' : 'bg-white text-slate-600' ?>"><?= e($t['title']) ?> (<?= (int) $t['n'] ?>)</a>
    <?php endforeach; ?>
    <a href="<?= e(url($tourId ? 'tour.php?slug=' . urlencode($tour['slug']) : 'faq.php')) ?>" target="_blank" class="ml-auto btn btn-light"><i class="fa-solid fa-eye"></i> View on site</a>
</div>
<?php if ($tourId): ?><p class="text-sm text-slate-500 mb-4">Questions shown on this trip's page. You can also add them directly in the <a class="text-brand-600 underline" href="<?= e(url('admin/tour-edit.php?id=' . $tourId . '#faq')) ?>">trip editor</a>.</p><?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
    <div class="xl:col-span-3 space-y-3">
        <?php $lastCat = null; foreach ($rows as $row):
            if (!$tourId && $row['category'] !== $lastCat): $lastCat = $row['category']; ?>
                <p class="text-xs font-bold uppercase tracking-widest text-slate-400 pt-2"><?= e($row['category'] ?: 'General') ?></p>
            <?php endif;
            $trCount = count(array_filter(get_translations('faq', (int) $row['id']), fn ($v) => !empty($v['question']))); ?>
            <div class="card p-4 <?= $row['status'] !== 'active' ? 'opacity-60' : '' ?> <?= $edit && (int) $edit['id'] === (int) $row['id'] ? 'ring-2 ring-brand-500' : '' ?>">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-slate-900"><?= e($row['question']) ?></p>
                        <p class="text-sm text-slate-500 mt-1 line-clamp-2"><?= e(excerpt($row['answer'], 180)) ?></p>
                        <p class="text-[11px] text-slate-400 mt-1">Order <?= (int) $row['sort_order'] ?><?= $row['status'] !== 'active' ? ' · hidden' : '' ?><?= count(languages()) > 1 ? ' · ' . $trCount . ' translation(s)' : '' ?></p>
                    </div>
                    <div class="flex gap-1 shrink-0">
                        <a href="<?= e(url($self . ($tourId ? '&' : '?') . 'edit=' . $row['id'])) ?>" class="btn btn-light !px-2.5"><i class="fa-solid fa-pen"></i></a>
                        <form method="post" data-confirm="Delete this question?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="btn btn-danger !px-2.5"><i class="fa-solid fa-trash"></i></button></form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$rows): ?><div class="card p-10 text-center text-slate-400">No questions yet. Add the first one!</div><?php endif; ?>
    </div>
    <form method="post" class="xl:col-span-2 space-y-6">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
        <div class="card p-6 space-y-4">
            <h2 class="font-bold"><?= $edit ? 'Edit question' : 'Add a question' ?></h2>
            <?php if (!$tourId): ?>
                <div>
                    <label class="lbl" for="f_category">Group</label>
                    <input class="inp" id="f_category" name="category" list="faqCats" value="<?= e($f['category']) ?>" placeholder="e.g. Booking, Payment, Travel tips">
                    <datalist id="faqCats"><?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
                    <p class="help">Questions are grouped by this name on the FAQ page.</p>
                </div>
            <?php endif; ?>
            <?php f_input('Question', 'question', $f['question'], 'text', 'required maxlength="500"'); ?>
            <?php f_textarea('Answer', 'answer', $f['answer'], 5, 'Plain text or basic HTML (links, lists, bold).', 'required'); ?>
            <div class="grid grid-cols-2 gap-4">
                <?php f_input('Order', 'sort_order', $f['sort_order'], 'number'); ?>
                <?php f_select('Status', 'status', ['active' => 'Visible', 'hidden' => 'Hidden'], $f['status']); ?>
            </div>
            <div class="flex gap-2">
                <button class="btn btn-primary flex-1 justify-center"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                <?php if ($edit): ?><a href="<?= e(url($self)) ?>" class="btn btn-light">Cancel</a><?php endif; ?>
            </div>
        </div>
        <?php translations_card('faq', (int) $f['id']); ?>
    </form>
</div>
<?php admin_footer(); ?>
