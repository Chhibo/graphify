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

$downloads = (int)db()->query('SELECT COALESCE(SUM(downloads), 0) FROM products')->fetchColumn();
$chips = array_filter(all_categories(), fn($c) => $c['items'] > 0);
$heroImg = setting('hero_image');

page_header(setting('store_name'), 'home');
?>
<?php if ($page === 1 && setting('hero_show', '1') === '1'): ?>
<section class="hero<?= $heroImg !== '' ? ' has-image' : '' ?>"<?= $heroImg !== '' ? ' style="--hero-img:url(\'' . e(base_url('uploads/' . $heroImg)) . '\')"' : '' ?>>
  <div class="hero-inner">
    <span class="hero-kicker"><?= icon('gift', 15) ?> <?= e(setting_or('hero_kicker', '100% free downloads')) ?></span>
    <h1><?= e(setting_or('hero_title', setting('store_name'))) ?></h1>
    <p><?= e(setting_or('store_tagline', 'Free digital downloads. Pick one and unlock it in minutes.')) ?></p>
    <form class="hero-search" action="<?= e(base_url('search.php')) ?>" method="get" role="search">
      <?= icon('search', 20) ?>
      <input type="search" name="q" placeholder="What are you looking for?" aria-label="Search downloads">
      <button class="btn">Search</button>
    </form>
    <?php if ($total): ?>
      <div class="hero-stats">
        <span><strong><?= short_number($total) ?></strong> free item<?= $total === 1 ? '' : 's' ?></span>
        <?php if ($chips): ?><span><strong><?= count($chips) ?></strong> categor<?= count($chips) === 1 ? 'y' : 'ies' ?></span><?php endif; ?>
        <?php if ($downloads > 0 && setting('show_downloads', '1') === '1'): ?><span><strong><?= short_number($downloads) ?></strong> downloads</span><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($page === 1 && $chips && setting('show_chips', '1') === '1'): ?>
  <nav class="chips" aria-label="Categories">
    <?php foreach ($chips as $c): ?>
      <a class="chip" href="<?= e(url_category($c['slug'])) ?>"><?= icon('folder', 15) ?> <?= e($c['name']) ?> <span><?= (int)$c['items'] ?></span></a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<?php if (!$total): ?>
  <?= empty_state('No products yet.' . (is_admin() ? ' <a href="admin/product_edit.php">Add your first product</a>.' : '')) ?>
<?php endif; ?>

<?php if ($featured): ?>
<section class="home-section featured">
  <?= section_head(setting_or('featured_title', 'Featured Items')) ?>
  <?= product_grid($featured, 'grid-featured') ?>
</section>
<?php endif; ?>

<?php if ($page === 1 && $total && setting('show_steps', '1') === '1'): ?>
<section class="steps">
  <div class="step"><span class="step-icon"><?= icon('search', 22) ?></span><div><strong>1. Pick a product</strong><p>Browse and choose any free download.</p></div></div>
  <div class="step"><span class="step-icon"><?= icon('zap', 22) ?></span><div><strong>2. Complete one quick offer</strong><p>Choose any offer from the list. It only takes a minute or two.</p></div></div>
  <div class="step"><span class="step-icon"><?= icon('download', 22) ?></span><div><strong>3. Download instantly</strong><p>Your download unlocks automatically.</p></div></div>
</section>
<?php endif; ?>

<?php foreach ($catSections as [$cat, $rows]): ?>
<section class="home-section">
  <?= section_head($cat['name'], url_category($cat['slug'])) ?>
  <?= product_grid($rows) ?>
</section>
<?php endforeach; ?>

<?php if ($latest): ?>
<section class="home-section" id="latest">
  <?= section_head(setting_or('latest_title', 'Latest Items')) ?>
  <?= product_grid($latest) ?>
  <?= pagination($page, $pages, base_url()) ?>
</section>
<?php endif; ?>
<?php page_footer();
