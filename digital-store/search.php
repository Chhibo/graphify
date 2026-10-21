<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_installed();

$q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 100));
$products = [];
$total = 0;
$perPage = max(1, (int)setting('per_page', '12'));
$page = max(1, (int)($_GET['page'] ?? 1));

if ($q !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $where = " WHERE p.active = 1 AND (p.title LIKE :q ESCAPE '\\' OR p.description LIKE :q ESCAPE '\\' OR c.name LIKE :q ESCAPE '\\')";
    $count = db()->prepare('SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id = p.category_id' . $where);
    $count->execute([':q' => $like]);
    $total = (int)$count->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    // Title matches first, then newest.
    $stmt = db()->prepare(product_select() . $where . ' ORDER BY (p.title LIKE :q ESCAPE \'\\\') DESC, p.id DESC LIMIT :lim OFFSET :off');
    $stmt->bindValue(':q', $like);
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();
}

page_header($q !== '' ? 'Search: ' . $q : 'Search');
?>
<section class="page-banner">
  <h1><?= $q !== '' ? 'Results for “' . e($q) . '”' : 'Search' ?></h1>
  <form class="hero-search" action="<?= e(base_url('search.php')) ?>" method="get" role="search">
    <?= icon('search', 20) ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search downloads…" aria-label="Search" autofocus>
    <button class="btn">Search</button>
  </form>
  <?php if ($q !== ''): ?><p><?= $total ?> result<?= $total === 1 ? '' : 's' ?></p><?php endif; ?>
</section>
<section class="home-section">
  <?php if ($products): ?>
    <?= product_grid($products) ?>
    <?= pagination($page, $pages, base_url('search.php?q=' . rawurlencode($q))) ?>
  <?php elseif ($q !== ''): ?>
    <?= empty_state('Nothing found. Try another word, or <a href="' . e(base_url()) . '">browse everything</a>.', 'search') ?>
  <?php endif; ?>
</section>
<?php page_footer();
