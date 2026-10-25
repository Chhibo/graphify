<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/cards.php';

$tag = trim((string) ($_GET['tag'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 9;
$where = "status = 'published' AND published_at <= ?";
$params = [now()];
if ($tag !== '') {
    $where .= ' AND tag = ?';
    $params[] = $tag;
}
$total = (int) db_value('SELECT COUNT(*) FROM ' . tbl('posts') . ' WHERE ' . $where, $params);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$posts = localize_rows(db_all('SELECT * FROM ' . tbl('posts') . ' WHERE ' . $where . ' ORDER BY published_at DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params), 'post');
$tags = db_all('SELECT tag, COUNT(*) AS n FROM ' . tbl('posts') . " WHERE status = 'published' AND tag <> '' GROUP BY tag ORDER BY tag");
// Tag buttons filter on the original tag but show its translation.
$tagLabels = [];
$allPosts = db_all('SELECT id, tag FROM ' . tbl('posts') . " WHERE status = 'published' AND tag <> ''");
foreach (array_map(null, $allPosts, localize_rows($allPosts, 'post')) as [$orig, $tr]) {
    $tagLabels[$orig['tag']] = $tr['tag'];
}

$pageTitle = __('Travel Stories');
$activeNav = 'blog';
require APP_ROOT . '/includes/header.php';
page_banner(setting('blog_title', __('Latest Travel Stories & Advice')), setting('blog_subtitle', __('Read expert tips, destination highlights, and travel itineraries written by world explorers.')), setting('blog_kicker', __('Insiders Guide')));
?>
<section class="py-14 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <?php if ($tags): ?>
        <div class="flex flex-wrap gap-2 mb-10 justify-center">
            <a href="<?= e(url('blog.php')) ?>" class="px-4 py-2 rounded-xl text-sm font-semibold <?= $tag === '' ? 'bg-brand-600 text-white shadow-md' : 'bg-slate-200 text-slate-700 hover:bg-slate-300' ?>"><?= e(__('All')) ?></a>
            <?php foreach ($tags as $t): ?>
                <a href="<?= e(url('blog.php?tag=' . urlencode($t['tag']))) ?>" class="px-4 py-2 rounded-xl text-sm font-semibold <?= $tag === $t['tag'] ? 'bg-brand-600 text-white shadow-md' : 'bg-slate-200 text-slate-700 hover:bg-slate-300' ?>"><?= e($tagLabels[$t['tag']] ?? $t['tag']) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <?php foreach ($posts as $p) { post_card($p); } ?>
    </div>
    <?php if (!$posts): ?><p class="text-center text-slate-500 py-10"><?= e(__('No stories published yet.')) ?></p><?php endif; ?>
    <?php if ($pages > 1): ?>
        <nav class="flex justify-center mt-12 gap-2">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <a href="<?= e(url('blog.php?' . http_build_query(array_filter(['tag' => $tag, 'page' => $i])))) ?>" class="w-10 h-10 flex items-center justify-center rounded-xl text-sm font-bold <?= $i === $page ? 'bg-brand-600 text-white' : 'bg-white border border-slate-200 text-slate-600' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
