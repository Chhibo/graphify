<?php
require __DIR__ . '/includes/bootstrap.php';

$page = q_one('SELECT * FROM pages WHERE slug = ? AND active = 1', [(string) ($_GET['slug'] ?? '')]);
if (!$page) {
    http_response_code(404);
}
$pageTitle = $page['title'] ?? 'Page not found';
include __DIR__ . '/includes/header.php';
?>
<div class="container narrow">
  <nav class="breadcrumb"><a href="<?= url() ?>">Home</a> <span>›</span>
    <?php if ($page && $page['type'] === 'post'): ?><a href="<?= url('blog.php') ?>">Blog</a> <span>›</span><?php endif; ?>
    <span><?= e($pageTitle) ?></span></nav>
  <?php if ($page): ?>
    <article class="content">
      <h1 class="page-title"><?= e($page['title']) ?></h1>
      <?php if ($page['type'] === 'post'): ?><p class="muted"><?= e(date('F j, Y', strtotime($page['created_at']))) ?></p><?php endif; ?>
      <?php if ($page['image'] !== ''): ?><img class="content-img" src="<?= e(img_url($page['image'])) ?>" alt=""><?php endif; ?>
      <?php /* Page HTML is written by the store admin in the admin panel. */ ?>
      <div class="content-body"><?= rich_text((string) $page['content']) ?></div>
    </article>
  <?php else: ?>
    <div class="empty"><h3>Page not found</h3><a class="btn btn-primary" href="<?= url() ?>">Back home</a></div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
