<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_installed();

$products = db()->query('SELECT * FROM products WHERE active = 1 ORDER BY id DESC')->fetchAll();

page_header(setting('store_name'));
?>
<section class="hero">
  <h1><?= e(setting('store_name')) ?></h1>
  <p><?= e(setting('store_tagline', 'Free digital downloads. Pick one and unlock it in minutes.')) ?></p>
</section>

<?php if (!$products): ?>
  <p class="empty">No products yet.<?php if (is_admin()): ?> <a href="admin/product_edit.php">Add your first product</a>.<?php endif; ?></p>
<?php else: ?>
  <div class="grid">
    <?php foreach ($products as $p): ?>
      <a class="product-card" href="product.php?id=<?= (int)$p['id'] ?>">
        <div class="thumb">
          <?php if ($p['image']): ?><img src="uploads/<?= e($p['image']) ?>" alt="" loading="lazy"><?php endif; ?>
        </div>
        <div class="product-info">
          <h2><?= e($p['title']) ?></h2>
          <span class="badge">FREE</span>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php page_footer();
