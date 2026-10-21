<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_installed();

$stmt = db()->prepare('SELECT * FROM pages WHERE slug = ?');
$stmt->execute([(string)($_GET['slug'] ?? '')]);
$pg = $stmt->fetch();
if (!$pg) {
    http_response_code(404);
    page_header('Not found');
    echo empty_state('This page does not exist. <a href="index.php">Back to the store</a>', 'file');
    page_footer();
    exit;
}

page_header($pg['title'], 'page:' . $pg['slug'], ['description' => $pg['content']]);
?>
<section class="page-banner">
  <nav class="breadcrumb"><a href="index.php"><?= icon('home', 14) ?> Home</a> <?= icon('chevron-right', 14) ?> <span><?= e($pg['title']) ?></span></nav>
  <h1><?= e($pg['title']) ?></h1>
  <p>Last updated <?= e(date('F j, Y', (int)$pg['updated_at'])) ?></p>
</section>
<article class="card prose">
  <?= render_content($pg['content']) ?>
</article>
<?php page_footer();
