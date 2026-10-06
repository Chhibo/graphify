<?php
require __DIR__ . '/includes/auth.php';

$id = (int) ($_GET['id'] ?? 0);
$post = $id ? db_one('SELECT * FROM ' . tbl('posts') . ' WHERE id = ?', [$id]) : null;
if ($id && !$post) {
    flash('error', 'Post not found.');
    redirect('admin/posts.php');
}
$p = $post ?: ['title' => '', 'slug' => '', 'tag' => '', 'author' => $admin['name'], 'image' => '', 'excerpt' => '', 'content' => '', 'read_time' => '', 'status' => 'published', 'published_at' => now()];
$self = 'admin/post-edit.php' . ($id ? '?id=' . $id : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf($self);
    $content = post('content');
    $words = str_word_count(strip_tags($content));
    $published = strtotime(str_replace('T', ' ', post('published_at'))) ?: time();
    $data = [
        'title' => mb_substr(post('title'), 0, 220),
        'tag' => mb_substr(post('tag'), 0, 60),
        'author' => mb_substr(post('author'), 0, 120),
        'excerpt' => post('excerpt'),
        'content' => $content,
        'read_time' => post('read_time') !== '' ? mb_substr(post('read_time'), 0, 30) : max(1, (int) ceil($words / 200)) . ' min read',
        'status' => post('status') === 'draft' ? 'draft' : 'published',
        'published_at' => date('Y-m-d H:i:s', $published),
    ];
    if ($data['title'] === '') {
        flash('error', 'Title is required.');
        redirect($self);
    }
    $data['slug'] = unique_slug('posts', post('slug') ?: $data['title'], $id);
    try {
        $data['image'] = resolve_image('image', $post['image'] ?? '');
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
        redirect($self);
    }
    if ($post) {
        db_update('posts', $data, $id);
    } else {
        $data['created_at'] = now();
        $id = db_insert('posts', $data);
    }
    flash('success', 'Post saved.');
    redirect('admin/post-edit.php?id=' . $id);
}

admin_header($post ? 'Edit post' : 'New post', 'posts');
?>
<a href="<?= e(url('admin/posts.php')) ?>" class="text-xs font-bold text-slate-500 hover:text-brand-600"><i class="fa-solid fa-arrow-left mr-1"></i> All posts</a>
<form method="post" enctype="multipart/form-data" class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-3">
    <?= csrf_field() ?>
    <div class="xl:col-span-2 card p-6 space-y-4">
        <?php f_input('Title', 'title', $p['title'], 'text', 'required'); ?>
        <?php f_textarea('Excerpt (shown on cards)', 'excerpt', $p['excerpt'], 3); ?>
        <?php f_textarea('Content', 'content', $p['content'], 18, 'Plain text (blank line = new paragraph) or HTML: <h2>, <h3>, <p>, <ul>, <li>, <a>, <img>, <blockquote>, <strong>, <em>.'); ?>
    </div>
    <div class="space-y-6">
        <div class="card p-6 space-y-4">
            <?php f_select('Status', 'status', ['published' => 'Published', 'draft' => 'Draft'], $p['status']); ?>
            <?php f_input('Publish date', 'published_at', date('Y-m-d\TH:i', strtotime($p['published_at'])), 'datetime-local', '', 'A future date schedules the post.'); ?>
            <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-floppy-disk"></i> Save post</button>
            <?php if ($post): ?><a href="<?= e(url('post.php?slug=' . urlencode($post['slug']))) ?>" target="_blank" class="btn btn-light w-full justify-center"><i class="fa-solid fa-eye"></i> View</a><?php endif; ?>
        </div>
        <div class="card p-6 space-y-4"><?php f_image('Cover image', 'image', $p['image']); ?></div>
        <div class="card p-6 space-y-4">
            <?php f_input('Tag', 'tag', $p['tag'], 'text', 'placeholder="Travel Tips"'); ?>
            <?php f_input('Author', 'author', $p['author']); ?>
            <?php f_input('Read time', 'read_time', $p['read_time'], 'text', '', 'Leave empty to calculate automatically.'); ?>
            <?php f_input('URL slug', 'slug', $p['slug']); ?>
        </div>
    </div>
</form>
<?php admin_footer(); ?>
