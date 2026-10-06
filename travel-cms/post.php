<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/cards.php';

$post = db_one('SELECT * FROM ' . tbl('posts') . " WHERE slug = ? AND status = 'published' AND published_at <= ?", [(string) ($_GET['slug'] ?? ''), now()]);
if (!$post) {
    http_response_code(404);
    $pageTitle = 'Story not found';
    require APP_ROOT . '/includes/header.php';
    page_banner('Story not found', 'This article may have been moved or removed.');
    echo '<div class="text-center py-16"><a href="' . e(url('blog.php')) . '" class="px-6 py-3 bg-brand-600 text-white rounded-xl font-bold">Back to the blog</a></div>';
    require APP_ROOT . '/includes/footer.php';
    exit;
}
$more = db_all('SELECT * FROM ' . tbl('posts') . " WHERE status = 'published' AND published_at <= ? AND id <> ? ORDER BY published_at DESC LIMIT 3", [now(), $post['id']]);

$pageTitle = $post['title'];
$metaDescription = excerpt($post['excerpt'] ?: $post['content'], 160);
$ogImage = img_url($post['image']);
$activeNav = 'blog';
require APP_ROOT . '/includes/header.php';
page_banner($post['title'], '', $post['tag'], $post['image']);
?>
<article class="py-14 max-w-3xl mx-auto px-4">
    <div class="flex items-center space-x-3 text-xs text-slate-400 pb-6 mb-6 border-b border-slate-200">
        <?php if ($post['author']): ?><span><i class="fa-solid fa-user-pen mr-1 text-brand-500"></i>By <?= e($post['author']) ?></span><span>•</span><?php endif; ?>
        <span><?= e(format_date($post['published_at'])) ?></span>
        <?php if ($post['read_time']): ?><span>•</span><span><?= e($post['read_time']) ?></span><?php endif; ?>
    </div>
    <div class="prose-content"><?= rich_text($post['content']) ?></div>
    <div class="mt-10 p-6 rounded-2xl bg-gradient-to-r from-brand-700 to-brand-500 text-white flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
            <h3 class="font-serif text-xl font-bold">Ready for your own adventure?</h3>
            <p class="text-sm text-brand-100">Book now and pay on arrival &mdash; no card needed.</p>
        </div>
        <button type="button" onclick="openBookingModal()" class="px-6 py-3 bg-accent-500 hover:bg-accent-600 rounded-xl font-bold text-sm whitespace-nowrap">Book a Trip</button>
    </div>
</article>
<?php if ($more): ?>
<section class="pb-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <h2 class="font-serif text-3xl font-bold text-slate-900 mb-8">More Travel Stories</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8"><?php foreach ($more as $p) { post_card($p); } ?></div>
</section>
<?php endif; ?>
<?php require APP_ROOT . '/includes/footer.php'; ?>
