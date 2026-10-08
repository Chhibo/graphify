<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_installed();

$stmt = db()->prepare('SELECT * FROM categories WHERE slug = ?');
$stmt->execute([(string)($_GET['slug'] ?? '')]);
$cat = $stmt->fetch();
if (!$cat) {
    http_response_code(404);
    page_header('Not found');
    echo '<p class="empty">This category does not exist. <a href="index.php">Back to the store</a></p>';
    page_footer();
    exit;
}

$perPage = max(1, (int)setting('per_page', '12'));
$count = db()->prepare('SELECT COUNT(*) FROM products WHERE active = 1 AND category_id = ?');
$count->execute([$cat['id']]);
$total = (int)$count->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min(max(1, (int)($_GET['page'] ?? 1)), $pages);

$stmt = db()->prepare(product_select() . ' WHERE p.active = 1 AND p.category_id = ? ORDER BY p.id DESC LIMIT ? OFFSET ?');
$stmt->execute([$cat['id'], $perPage, ($page - 1) * $perPage]);
$products = $stmt->fetchAll();

page_header($cat['name'], 'cat:' . $cat['slug']);
?>
<section class="home-section">
  <div class="section-head"><h1><?= e($cat['name']) ?></h1><span class="muted"><?= $total ?> item<?= $total === 1 ? '' : 's' ?></span></div>
  <?php if ($products): ?>
    <?= product_grid($products) ?>
    <?= pagination($page, $pages, base_url('category.php?slug=' . rawurlencode($cat['slug']))) ?>
  <?php else: ?>
    <p class="empty">No items in this category yet.</p>
  <?php endif; ?>
</section>
<?php page_footer();
