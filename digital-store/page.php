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
    echo '<p class="empty">This page does not exist. <a href="index.php">Back to the store</a></p>';
    page_footer();
    exit;
}

page_header($pg['title'], 'page:' . $pg['slug']);
?>
<article class="card prose">
  <h1><?= e($pg['title']) ?></h1>
  <?= render_content($pg['content']) ?>
  <p class="muted"><small>Last updated <?= e(date('F j, Y', (int)$pg['updated_at'])) ?></small></p>
</article>
<?php page_footer();
