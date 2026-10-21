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
    echo empty_state('This category does not exist. <a href="index.php">Back to the store</a>', 'folder');
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
$chips = array_filter(all_categories(), fn($c) => $c['items'] > 0);

page_header($cat['name'], 'cat:' . $cat['slug'], ['description' => 'Free ' . $cat['name'] . ' downloads at ' . setting('store_name') . '.']);
?>
<section class="page-banner">
  <nav class="breadcrumb"><a href="index.php"><?= icon('home', 14) ?> Home</a> <?= icon('chevron-right', 14) ?> <span><?= e($cat['name']) ?></span></nav>
  <h1><?= e($cat['name']) ?></h1>
  <p><?= $total ?> free item<?= $total === 1 ? '' : 's' ?></p>
</section>
<?php if (count($chips) > 1): ?>
  <nav class="chips" aria-label="Categories">
    <?php foreach ($chips as $c): ?>
      <a class="chip<?= $c['slug'] === $cat['slug'] ? ' active' : '' ?>" href="category.php?slug=<?= e(rawurlencode($c['slug'])) ?>"><?= e($c['name']) ?> <span><?= (int)$c['items'] ?></span></a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>
<section class="home-section">
  <?php if ($products): ?>
    <?= product_grid($products) ?>
    <?= pagination($page, $pages, base_url('category.php?slug=' . rawurlencode($cat['slug']))) ?>
  <?php else: ?>
    <?= empty_state('No items in this category yet.', 'folder') ?>
  <?php endif; ?>
</section>
<?php page_footer();
