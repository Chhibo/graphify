<?php
require __DIR__ . '/includes/bootstrap.php';

$posts = q_all("SELECT * FROM pages WHERE type = 'post' AND active = 1 ORDER BY created_at DESC, id DESC");
$pageTitle = 'Blog';
include __DIR__ . '/includes/header.php';
?>
<div class="container">
  <nav class="breadcrumb"><a href="<?= url() ?>">Home</a> <span>›</span> <span>Blog</span></nav>
  <h1 class="page-title">Blog</h1>
  <?php if ($posts): ?>
    <div class="blog-grid">
      <?php foreach ($posts as $post): ?>
        <a class="blog-card" href="<?= url('page.php?slug=' . rawurlencode($post['slug'])) ?>">
          <img src="<?= e(img_url($post['image'])) ?>" alt="" loading="lazy">
          <div>
            <small class="muted"><?= e(date('F j, Y', strtotime($post['created_at']))) ?></small>
            <h3><?= e($post['title']) ?></h3>
            <p><?= e(excerpt((string) $post['content'])) ?></p>
            <span class="link-more">Read more <?= icon('arrow-right', 14) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty"><h3>No posts yet</h3></div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
