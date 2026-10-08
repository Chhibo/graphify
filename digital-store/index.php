<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_installed();

$perPage = max(1, (int)setting('per_page', '12'));
$page = max(1, (int)($_GET['page'] ?? 1));

// Featured: the products ticked "Featured", newest first.
$featured = [];
if ($page === 1) {
    $stmt = db()->prepare(product_select() . ' WHERE p.active = 1 AND p.featured = 1 ORDER BY p.id DESC LIMIT ?');
    $stmt->execute([max(1, (int)setting('featured_count', '3'))]);
    $featured = $stmt->fetchAll();
}

// One section per category marked "Show on home page".
$catSections = [];
if ($page === 1) {
    $limit = max(1, (int)setting('home_cat_items', '4'));
    $items = db()->prepare(product_select() . ' WHERE p.active = 1 AND p.category_id = ? ORDER BY p.id DESC LIMIT ' . $limit);
    foreach (db()->query('SELECT * FROM categories WHERE show_on_home = 1 ORDER BY sort_order, name') as $cat) {
        $items->execute([$cat['id']]);
        if ($rows = $items->fetchAll()) {
            $catSections[] = [$cat, $rows];
        }
    }
}

// Latest: every visible product, paginated.
$total = (int)db()->query('SELECT COUNT(*) FROM products WHERE active = 1')->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$stmt = db()->prepare(product_select() . ' WHERE p.active = 1 ORDER BY p.id DESC LIMIT ? OFFSET ?');
$stmt->execute([$perPage, ($page - 1) * $perPage]);
$latest = $stmt->fetchAll();

page_header(setting('store_name'), 'home');
?>
<?php if ($page === 1 && setting('hero_show', '1') === '1'): ?>
<section class="hero">
  <h1><?= e(setting_or('hero_title', setting('store_name'))) ?></h1>
  <p><?= e(setting_or('store_tagline', 'Free digital downloads. Pick one and unlock it in minutes.')) ?></p>
</section>
<?php endif; ?>

<?php if (!$total): ?>
  <p class="empty">No products yet.<?php if (is_admin()): ?> <a href="admin/product_edit.php">Add your first product</a>.<?php endif; ?></p>
<?php endif; ?>

<?php if ($featured): ?>
<section class="home-section featured">
  <div class="section-head"><h2><?= e(setting_or('featured_title', 'Featured Items')) ?></h2></div>
  <?= product_grid($featured) ?>
</section>
<?php endif; ?>

<?php foreach ($catSections as [$cat, $rows]): ?>
<section class="home-section">
  <div class="section-head">
    <h2><?= e($cat['name']) ?></h2>
    <a href="category.php?slug=<?= e(rawurlencode($cat['slug'])) ?>">View all →</a>
  </div>
  <?= product_grid($rows) ?>
</section>
<?php endforeach; ?>

<?php if ($latest): ?>
<section class="home-section" id="latest">
  <div class="section-head"><h2><?= e(setting_or('latest_title', 'Latest Items')) ?></h2></div>
  <?= product_grid($latest) ?>
  <?= pagination($page, $pages, base_url()) ?>
</section>
<?php endif; ?>
<?php page_footer();
