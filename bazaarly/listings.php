<?php
require __DIR__ . '/includes/bootstrap.php';

[$where, $params] = visible_listing_where();
$q = input('q');
$categoryId = (int) input('category');
$location = input('location');
$min = input('min');
$max = input('max');
$condition = input('condition');
$posted = input('posted');
$featuredOnly = input('featured') === '1';
$sort = input('sort', 'newest');
$view = input('view') === 'list' ? 'list' : 'grid';

if ($q !== '') {
    $where .= ' AND (l.title LIKE ? OR l.description LIKE ? OR l.tags LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
$category = $categoryId ? (categories_all()[$categoryId] ?? null) : null;
if ($category) {
    $ids = category_ids_with_children($categoryId);
    $where .= ' AND l.category_id IN (' . implode(',', array_map('intval', $ids)) . ')';
}
if ($location !== '') {
    $where .= ' AND l.location LIKE ?';
    $params[] = '%' . $location . '%';
}
if ($min !== '' && is_numeric($min)) {
    $where .= " AND l.price >= ? AND l.price_type IN ('fixed','negotiable')";
    $params[] = (float) $min;
}
if ($max !== '' && is_numeric($max)) {
    $where .= " AND l.price <= ? AND l.price_type <> 'contact'";
    $params[] = (float) $max;
}
if (in_array($condition, ['new', 'like_new', 'used', 'refurbished'], true)) {
    $where .= ' AND l.item_condition = ?';
    $params[] = $condition;
}
$postedMap = ['today' => 1, 'week' => 7, 'month' => 30];
if (isset($postedMap[$posted])) {
    $where .= ' AND l.created_at >= ?';
    $params[] = date('Y-m-d H:i:s', time() - $postedMap[$posted] * 86400);
}
if ($featuredOnly) {
    $where .= ' AND l.featured = 1';
}
$orders = [
    'newest' => 'l.featured DESC, l.created_at DESC',
    'oldest' => 'l.created_at ASC',
    'price_asc' => "CASE WHEN l.price_type IN ('fixed','negotiable') THEN 0 ELSE 1 END, l.price ASC",
    'price_desc' => 'l.price DESC',
    'popular' => 'l.views DESC',
];
$order = $orders[$sort] ?? $orders['newest'];

$total = (int) q_val("SELECT COUNT(*) FROM listings l JOIN users u ON u.id = l.user_id WHERE $where", $params);
$perPage = max(1, (int) setting('listings_per_page', '12'));
[$page, $pages, $offset] = paginate($total, $perPage, (int) input('page', '1'));
$rows = q_all(listing_select() . " WHERE $where ORDER BY $order LIMIT $perPage OFFSET $offset", $params);

$tree = category_tree();
$counts = category_counts();
$activeFilters = array_filter([$q, $categoryId ?: '', $location, $min, $max, $condition, $posted, $featuredOnly ? '1' : '']);

$heading = $category ? $category['name'] : ($q !== '' ? 'Results for “' . $q . '”' : 'All ads');
$pageTitle = $heading;
require __DIR__ . '/includes/header.php';

function qs_with(array $changes): string
{
    $qs = array_merge($_GET, $changes);
    unset($qs['page']);
    return '?' . http_build_query(array_filter($qs, fn($v) => $v !== '' && $v !== null));
}
?>
<section class="container section-sm">
  <nav class="breadcrumb"><a href="<?= e(url()) ?>">Home</a><?= icon('chevron-right') ?><a href="<?= e(url('listings.php')) ?>">Ads</a>
    <?php if ($category): ?><?= icon('chevron-right') ?><span><?= e($category['name']) ?></span><?php endif; ?></nav>

  <div class="browse-layout">
    <aside class="filters" id="filters">
      <form method="get" class="card filter-card">
        <div class="filter-head"><h2><?= icon('filter') ?>Filters</h2>
          <?php if ($activeFilters): ?><a class="small-link" href="<?= e(url('listings.php')) ?>">Clear all</a><?php endif; ?>
          <button type="button" class="icon-btn mobile-only" data-close-filters aria-label="Close filters"><?= icon('x') ?></button>
        </div>
        <div class="field">
          <label for="f-q">Keyword</label>
          <div class="input-icon"><?= icon('search') ?><input id="f-q" type="search" name="q" value="<?= e($q) ?>" placeholder="e.g. iPhone, sofa"></div>
        </div>
        <div class="field">
          <label for="f-cat">Category</label>
          <select id="f-cat" name="category"><option value="">All categories</option><?= category_options($categoryId) ?></select>
        </div>
        <div class="field">
          <label for="f-loc">Location</label>
          <div class="input-icon"><?= icon('map-pin') ?><input id="f-loc" type="text" name="location" value="<?= e($location) ?>" placeholder="City or area"></div>
        </div>
        <div class="field">
          <label>Price (<?= e(setting('currency_symbol')) ?>)</label>
          <div class="range-row">
            <input type="number" name="min" min="0" step="any" value="<?= e($min) ?>" placeholder="Min" aria-label="Minimum price">
            <span>–</span>
            <input type="number" name="max" min="0" step="any" value="<?= e($max) ?>" placeholder="Max" aria-label="Maximum price">
          </div>
        </div>
        <div class="field">
          <label>Condition</label>
          <div class="pill-options">
            <?php foreach (['' => 'Any', 'new' => 'New', 'like_new' => 'Like new', 'used' => 'Used', 'refurbished' => 'Refurbished'] as $val => $lbl): ?>
              <label class="pill-opt"><input type="radio" name="condition" value="<?= e($val) ?>" <?= $condition === $val ? 'checked' : '' ?>><span><?= e($lbl) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="field">
          <label>Date posted</label>
          <div class="pill-options">
            <?php foreach (['' => 'Any time', 'today' => 'Today', 'week' => 'This week', 'month' => 'This month'] as $val => $lbl): ?>
              <label class="pill-opt"><input type="radio" name="posted" value="<?= e($val) ?>" <?= $posted === $val ? 'checked' : '' ?>><span><?= e($lbl) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
        <label class="check"><input type="checkbox" name="featured" value="1" <?= $featuredOnly ? 'checked' : '' ?>><span>Featured ads only</span></label>
        <input type="hidden" name="sort" value="<?= e($sort) ?>">
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <button class="btn btn-primary btn-block" type="submit">Show results</button>
      </form>

      <?php if (!$category): ?>
      <div class="card filter-card cat-list-card">
        <h3>Categories</h3>
        <ul class="cat-list">
          <?php foreach ($tree as $id => $c): ?>
            <li><a href="<?= e(qs_with(['category' => $id])) ?>"><?= icon($c['icon']) ?><span><?= e($c['name']) ?></span><small><?= $counts[$id] ?? 0 ?></small></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php elseif (!empty($tree[$categoryId]['children'])): ?>
      <div class="card filter-card cat-list-card">
        <h3><?= e($category['name']) ?></h3>
        <ul class="cat-list">
          <?php foreach ($tree[$categoryId]['children'] as $cid => $ch): ?>
            <li><a href="<?= e(qs_with(['category' => $cid])) ?>"><?= icon($ch['icon']) ?><span><?= e($ch['name']) ?></span><small><?= $counts[$cid] ?? 0 ?></small></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      <?= ad_slot('sidebar') ?>
    </aside>

    <div class="results">
      <div class="results-bar">
        <div>
          <h1 class="results-title"><?= e($heading) ?></h1>
          <p class="muted"><?= number_format($total) ?> ad<?= $total === 1 ? '' : 's' ?> found</p>
        </div>
        <div class="results-tools">
          <button class="btn btn-ghost mobile-only" type="button" data-open-filters><?= icon('filter') ?>Filters<?php if ($activeFilters): ?><span class="badge badge-brand"><?= count($activeFilters) ?></span><?php endif; ?></button>
          <form method="get" class="sort-form">
            <?php foreach ($_GET as $k => $v): if (in_array($k, ['sort', 'page'], true) || !is_string($v)) continue; ?>
              <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
            <?php endforeach; ?>
            <select name="sort" aria-label="Sort by" data-autosubmit>
              <?php foreach (['newest' => 'Newest first', 'oldest' => 'Oldest first', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'popular' => 'Most viewed'] as $k => $lbl): ?>
                <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
          <div class="seg">
            <a href="<?= e(qs_with(['view' => 'grid'])) ?>" class="<?= $view === 'grid' ? 'on' : '' ?>" aria-label="Grid view"><?= icon('grid') ?></a>
            <a href="<?= e(qs_with(['view' => 'list'])) ?>" class="<?= $view === 'list' ? 'on' : '' ?>" aria-label="List view"><?= icon('list') ?></a>
          </div>
        </div>
      </div>

      <?php if ($rows): ?>
        <div class="listing-grid <?= $view === 'list' ? 'as-list' : 'cols-3' ?>">
          <?php foreach ($rows as $l) { include __DIR__ . '/includes/partials/listing-card.php'; } ?>
        </div>
        <?= pagination_links($page, $pages) ?>
      <?php else: ?>
        <div class="empty-state"><?= icon('search') ?><h3>No ads match your search</h3><p>Try removing a filter or searching for something broader.</p><a class="btn btn-ghost" href="<?= e(url('listings.php')) ?>">Reset filters</a></div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php';
