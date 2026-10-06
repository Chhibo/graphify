<?php
require __DIR__ . '/includes/bootstrap.php';

$page = db_one('SELECT * FROM ' . tbl('pages') . " WHERE slug = ? AND status = 'published'", [(string) ($_GET['slug'] ?? '')]);
if (!$page) {
    http_response_code(404);
}
$pageTitle = $page ? $page['title'] : 'Page not found';
$metaDescription = $page ? excerpt($page['content'], 160) : '';
require APP_ROOT . '/includes/header.php';
page_banner($pageTitle);
?>
<section class="py-14 max-w-3xl mx-auto px-4">
    <?php if ($page): ?>
        <div class="prose-content bg-white rounded-3xl p-6 sm:p-10 shadow border border-slate-100"><?= rich_text($page['content']) ?></div>
    <?php else: ?>
        <p class="text-center text-slate-500">The page you requested does not exist.</p>
    <?php endif; ?>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
