<?php
require __DIR__ . '/includes/bootstrap.php';

$page = q_one('SELECT * FROM pages WHERE slug = ?', [input('slug')]);
if (!$page) {
    not_found();
}
$pageTitle = $page['title'];
$metaDesc = excerpt((string) $page['content'], 160);
require __DIR__ . '/includes/header.php';
?>
<section class="container section-sm">
  <nav class="breadcrumb"><a href="<?= e(url()) ?>">Home</a><?= icon('chevron-right') ?><span><?= e($page['title']) ?></span></nav>
  <article class="card prose-card">
    <h1><?= e($page['title']) ?></h1>
    <div class="prose"><?= $page['content'] /* trusted admin HTML */ ?></div>
    <p class="muted small">Last updated <?= e(format_date($page['updated_at'])) ?></p>
  </article>
</section>
<?php require __DIR__ . '/includes/footer.php';
