<?php
require __DIR__ . '/includes/bootstrap.php';

$filters = [
    'category_id' => (int) ($_GET['category'] ?? 0),
    'brand' => trim((string) ($_GET['brand'] ?? '')),
    'search' => trim((string) ($_GET['q'] ?? '')),
    'sale' => !empty($_GET['sale']),
    'min' => is_numeric($_GET['min'] ?? null) ? $_GET['min'] : '',
    'max' => is_numeric($_GET['max'] ?? null) ? $_GET['max'] : '',
    'sort' => in_array($_GET['sort'] ?? '', ['new', 'price_asc', 'price_desc', 'rating', 'popular'], true) ? $_GET['sort'] : 'manual',
];
$perPage = 12;
$page = max(1, (int) ($_GET['page'] ?? 1));
$total = find_products($filters, true);
$products = find_products($filters + ['limit' => $perPage, 'offset' => ($page - 1) * $perPage]);

$title = 'All Products';
foreach (categories() as $c) {
    if ((int) $c['id'] === $filters['category_id']) {
        $title = $c['name'];
    }
}
if ($filters['brand'] !== '') {
    $title = $filters['brand'];
}
if ($filters['sale']) {
    $title = 'On Sale';
}
if ($filters['sort'] === 'new' && $title === 'All Products') {
    $title = 'New Arrivals';
}
if ($filters['search'] !== '') {
    $title = 'Search: “' . $filters['search'] . '”';
}

$query = array_filter($_GET, fn($v) => $v !== '' && $v !== null);
unset($query['page']);
$pageTitle = $title;
include __DIR__ . '/includes/header.php';
?>
<div class="container">
  <nav class="breadcrumb"><a href="<?= url() ?>">Home</a> <span>›</span> <span><?= e($title) ?></span></nav>

  <div class="shop-layout">
    <aside class="filters" id="filters">
      <form method="get">
        <div class="filters-head"><h3>Filters</h3><button class="icon-btn close-filters" type="button" data-close="#filters"><?= icon('close', 18) ?></button></div>
        <div class="filter-group">
          <h4>Search</h4>
          <input type="search" name="q" value="<?= e($filters['search']) ?>" placeholder="Search for products...">
        </div>
        <div class="filter-group">
          <h4>Categories</h4>
          <label class="radio"><input type="radio" name="category" value="" <?= $filters['category_id'] === 0 ? 'checked' : '' ?>> All</label>
          <?php foreach (categories() as $c): ?>
            <label class="radio<?= $c['depth'] ? ' sub' : '' ?>"><input type="radio" name="category" value="<?= (int) $c['id'] ?>" <?= $filters['category_id'] === (int) $c['id'] ? 'checked' : '' ?>> <?= e($c['name']) ?> <small>(<?= (int) $c['product_count'] ?>)</small></label>
          <?php endforeach; ?>
        </div>
        <div class="filter-group">
          <h4>Brand</h4>
          <select name="brand">
            <option value="">All brands</option>
            <?php foreach (brands() as $b): ?><option <?= $filters['brand'] === $b ? 'selected' : '' ?>><?= e($b) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="filter-group">
          <h4>Price</h4>
          <div class="price-range">
            <input type="number" name="min" min="0" placeholder="Min" value="<?= e($filters['min']) ?>">
            <span>–</span>
            <input type="number" name="max" min="0" placeholder="Max" value="<?= e($filters['max']) ?>">
          </div>
        </div>
        <div class="filter-group">
          <label class="check"><input type="checkbox" name="sale" value="1" <?= $filters['sale'] ? 'checked' : '' ?>> On sale only</label>
        </div>
        <input type="hidden" name="sort" value="<?= e($filters['sort'] === 'manual' ? '' : $filters['sort']) ?>">
        <button class="btn btn-primary btn-block" type="submit">Apply Filter</button>
        <a class="btn btn-ghost btn-block" href="<?= url('shop.php') ?>">Reset</a>
      </form>
    </aside>

    <section class="shop-main">
      <div class="shop-head">
        <h1><?= e($title) ?></h1>
        <div class="shop-tools">
          <span class="muted">Showing <?= $total ? (($page - 1) * $perPage + 1) . '–' . min($total, $page * $perPage) : 0 ?> of <?= $total ?> Products</span>
          <form method="get" class="sort-form">
            <?php foreach ($query as $k => $v): if ($k !== 'sort' && is_scalar($v)): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endif; endforeach; ?>
            <label>Sort by:
              <select name="sort" data-autosubmit>
                <?php foreach (['' => 'Featured', 'new' => 'Newest', 'popular' => 'Most Popular', 'rating' => 'Top Rated', 'price_asc' => 'Price: Low to High', 'price_desc' => 'Price: High to Low'] as $k => $label): ?>
                  <option value="<?= e($k) ?>" <?= ($filters['sort'] === 'manual' ? '' : $filters['sort']) === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </form>
          <button class="btn btn-outline btn-sm open-filters" type="button" data-open="#filters">Filters</button>
        </div>
      </div>

      <?php if ($products): ?>
        <div class="product-grid cols-3">
          <?php foreach ($products as $p) { include __DIR__ . '/includes/product-card.php'; } ?>
        </div>
        <?= paginate_links($total, $perPage, $page, $query) ?>
      <?php else: ?>
        <div class="empty">
          <?= icon('search', 40) ?>
          <h3>No products found</h3>
          <p>Try another search or remove some filters.</p>
          <a class="btn btn-primary" href="<?= url('shop.php') ?>">See all products</a>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
