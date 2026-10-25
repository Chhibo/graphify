<?php
require __DIR__ . '/includes/auth.php';
require_permission('content');

$id = (int) ($_GET['id'] ?? 0);
$page = $id ? db_one('SELECT * FROM ' . tbl('pages') . ' WHERE id = ?', [$id]) : null;
if ($id && !$page) {
    redirect('admin/pages.php');
}
$p = $page ?: ['title' => '', 'slug' => '', 'content' => '', 'show_in_footer' => 1, 'status' => 'published', 'seo_title' => '', 'meta_description' => ''];
$self = 'admin/page-edit.php' . ($id ? '?id=' . $id : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf($self);
    $data = [
        'title' => mb_substr(post('title'), 0, 200),
        'content' => post('content'),
        'show_in_footer' => post('show_in_footer') === '1' ? 1 : 0,
        'status' => post('status') === 'draft' ? 'draft' : 'published',
        'seo_title' => mb_substr(post('seo_title'), 0, 200),
        'meta_description' => mb_substr(post('meta_description'), 0, 320),
        'updated_at' => now(),
    ];
    if ($data['title'] === '') {
        flash('error', 'Title is required.');
        redirect($self);
    }
    $data['slug'] = unique_slug('pages', post('slug') ?: $data['title'], $id);
    if ($page) {
        db_update('pages', $data, $id);
    } else {
        $data['created_at'] = now();
        $id = db_insert('pages', $data);
    }
    save_translations_from_post('page', $id);
    flash('success', 'Page saved.');
    redirect('admin/page-edit.php?id=' . $id);
}

admin_header($page ? 'Edit page' : 'New page', 'pages');
?>
<a href="<?= e(url('admin/pages.php')) ?>" class="text-xs font-bold text-slate-500 hover:text-brand-600"><i class="fa-solid fa-arrow-left mr-1"></i> All pages</a>
<form method="post" class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-3">
    <?= csrf_field() ?>
    <div class="xl:col-span-2 card p-6 space-y-4">
        <?php f_input('Title', 'title', $p['title'], 'text', 'required'); ?>
        <?php f_textarea('Content', 'content', $p['content'], 20, 'Plain text (blank line = new paragraph) or basic HTML.'); ?>
    </div>
    <div class="card p-6 space-y-4 h-fit">
        <?php f_select('Status', 'status', ['published' => 'Published', 'draft' => 'Draft'], $p['status']); ?>
        <?php f_input('URL slug', 'slug', $p['slug']); ?>
        <?php f_check('Link in footer', 'show_in_footer', (bool) $p['show_in_footer']); ?>
        <?php f_input('SEO title', 'seo_title', $p['seo_title'], 'text', 'maxlength="200"', 'Optional. Leave empty for "Title | Site name".'); ?>
        <?php f_textarea('Meta description', 'meta_description', $p['meta_description'], 3, 'Optional. Leave empty to use the start of the content.', 'maxlength="320"'); ?>
        <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-floppy-disk"></i> Save page</button>
        <?php if ($page): ?><a href="<?= e(url('page.php?slug=' . urlencode($page['slug']))) ?>" target="_blank" class="btn btn-light w-full justify-center"><i class="fa-solid fa-eye"></i> View</a><?php endif; ?>
    </div>
    <div class="xl:col-span-2"><?php translations_card('page', (int) ($page['id'] ?? 0)); ?></div>
</form>
<?php admin_footer(); ?>
