<?php
require __DIR__ . '/includes/bootstrap.php';

$search = listing_search($_GET);
$v = $search['values'];
$where = $search['where'];
$params = $search['params'];
$category = $search['category'];
$categoryId = $v['category'];
$sort = input('sort', 'newest');
$view = input('view') === 'list' ? 'list' : 'grid';

if (!$category && input('category_slug') !== '') {
    not_found(__('This category does not exist.'));
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
$activeFilters = $search['active'];
$query = search_query($v);
$listMode = location_list_mode();
$filterFields = array_filter($search['fields'], fn($f) => $f['filterable']);
$savedOn = setting('enable_saved_searches', '1') === '1';
$alreadySaved = $savedOn && is_logged_in() && $query
    ? (bool) q_val('SELECT id FROM saved_searches WHERE user_id = ? AND query = ?', [user_id(), http_build_query($query)])
    : false;

$heading = $category ? $category['name'] : ($v['q'] !== '' ? 'Results for “' . $v['q'] . '”' : 'All ads');
$pageTitle = $heading;
if ($category && $category['description']) {
    $metaDesc = $category['description'];
}
require __DIR__ . '/includes/header.php';

$qs = function (array $changes) use ($query, $sort, $view): string {
    $all = array_merge($query, ['sort' => $sort === 'newest' ? '' : $sort, 'view' => $view === 'grid' ? '' : $view], $changes);
    return search_url($all);
};
?>
<section class="container section-sm">
  <nav class="breadcrumb"><a href="<?= e(url()) ?>"><?= t('Home') ?></a><?= icon('chevron-right') ?><a href="<?= e(search_url([])) ?>"><?= t('Ads') ?></a>
    <?php if ($category && (int) $category['parent_id'] && isset($tree[(int) $category['parent_id']])): ?><?= icon('chevron-right') ?><a href="<?= e(category_url((int) $category['parent_id'])) ?>"><?= e($tree[(int) $category['parent_id']]['name']) ?></a><?php endif; ?>
    <?php if ($category): ?><?= icon('chevron-right') ?><span><?= e($category['name']) ?></span><?php endif; ?></nav>

  <div class="browse-layout">
    <aside class="filters" id="filters">
      <form method="get" action="<?= e(url('listings.php')) ?>" class="card filter-card">
        <div class="filter-head"><h2><?= icon('filter') ?><?= t('Filters') ?></h2>
          <?php if ($activeFilters): ?><a class="small-link" href="<?= e(search_url([])) ?>"><?= t('Clear all') ?></a><?php endif; ?>
          <button type="button" class="icon-btn mobile-only" data-close-filters aria-label="<?= t('Close filters') ?>"><?= icon('x') ?></button>
        </div>
        <div class="field">
          <label for="f-q"><?= t('Keyword') ?></label>
          <div class="input-icon suggest-wrap"><?= icon('search') ?><input id="f-q" type="search" name="q" value="<?= e($v['q']) ?>" placeholder="<?= t('e.g. iPhone, sofa') ?>" autocomplete="off" data-suggest></div>
        </div>
        <div class="field">
          <label for="f-cat"><?= t('Category') ?></label>
          <select id="f-cat" name="category" data-autosubmit><option value=""><?= t('All categories') ?></option><?= category_options($categoryId) ?></select>
        </div>
        <div class="field">
          <label for="f-loc"><?= t('Location') ?></label>
          <?php if ($listMode): ?>
            <select id="f-loc" name="loc"><option value=""><?= t('Everywhere') ?></option><?= location_options($v['loc']) ?></select>
          <?php else: ?>
            <div class="input-icon"><?= icon('map-pin') ?><input id="f-loc" type="text" name="location" value="<?= e($v['location']) ?>" placeholder="<?= t('City or area') ?>"></div>
          <?php endif; ?>
        </div>
        <div class="field">
          <label><?= t('Price ({currency})', ['currency' => setting('currency_symbol')]) ?></label>
          <div class="range-row">
            <input type="number" name="min" min="0" step="any" value="<?= e($v['min']) ?>" placeholder="<?= t('Min') ?>" aria-label="<?= t('Minimum price') ?>">
            <span>–</span>
            <input type="number" name="max" min="0" step="any" value="<?= e($v['max']) ?>" placeholder="<?= t('Max') ?>" aria-label="<?= t('Maximum price') ?>">
          </div>
        </div>

        <?php foreach ($filterFields as $fid => $f): ?>
          <div class="field">
            <?php if ($f['type'] === 'number'): ?>
              <label><?= e($f['label']) ?><?= $f['unit'] !== '' ? ' <small class="muted">(' . e($f['unit']) . ')</small>' : '' ?></label>
              <div class="range-row">
                <input type="number" step="any" name="fmin[<?= $fid ?>]" value="<?= e($v['fmin'][$fid] ?? '') ?>" placeholder="<?= t('Min') ?>" aria-label="<?= e($f['label']) ?> minimum">
                <span>–</span>
                <input type="number" step="any" name="fmax[<?= $fid ?>]" value="<?= e($v['fmax'][$fid] ?? '') ?>" placeholder="<?= t('Max') ?>" aria-label="<?= e($f['label']) ?> maximum">
              </div>
            <?php elseif ($f['type'] === 'select'): ?>
              <label for="ff<?= $fid ?>"><?= e($f['label']) ?></label>
              <select id="ff<?= $fid ?>" name="f[<?= $fid ?>]"><option value=""><?= t('Any') ?></option>
                <?php foreach (field_options($f) as $o): ?><option <?= ($v['f'][$fid] ?? '') === $o ? 'selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?>
              </select>
            <?php elseif ($f['type'] === 'checkbox'): ?>
              <label class="check"><input type="checkbox" name="f[<?= $fid ?>]" value="1" <?= !empty($v['f'][$fid]) ? 'checked' : '' ?>><span><?= e($f['label']) ?></span></label>
            <?php else: ?>
              <label for="ff<?= $fid ?>"><?= e($f['label']) ?></label>
              <input id="ff<?= $fid ?>" name="f[<?= $fid ?>]" value="<?= e($v['f'][$fid] ?? '') ?>">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <div class="field">
          <label><?= t('Condition') ?></label>
          <div class="pill-options">
            <?php foreach (['' => 'Any', 'new' => 'New', 'like_new' => 'Like new', 'used' => 'Used', 'refurbished' => 'Refurbished'] as $val => $lbl): ?>
              <label class="pill-opt"><input type="radio" name="condition" value="<?= e($val) ?>" <?= $v['condition'] === $val ? 'checked' : '' ?>><span><?= e($lbl) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="field">
          <label><?= t('Date posted') ?></label>
          <div class="pill-options">
            <?php foreach (['' => 'Any time', 'today' => 'Today', 'week' => 'This week', 'month' => 'This month'] as $val => $lbl): ?>
              <label class="pill-opt"><input type="radio" name="posted" value="<?= e($val) ?>" <?= $v['posted'] === $val ? 'checked' : '' ?>><span><?= e($lbl) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
        <label class="check"><input type="checkbox" name="featured" value="1" <?= $v['featured'] ? 'checked' : '' ?>><span><?= t('Featured ads only') ?></span></label>
        <?php if ($sort !== 'newest'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
        <?php if ($view !== 'grid'): ?><input type="hidden" name="view" value="<?= e($view) ?>"><?php endif; ?>
        <button class="btn btn-primary btn-block" type="submit"><?= t('Show results') ?></button>
      </form>

      <?php if (!$category): ?>
      <div class="card filter-card cat-list-card">
        <h3><?= t('Categories') ?></h3>
        <ul class="cat-list">
          <?php foreach ($tree as $id => $c): ?>
            <li><a href="<?= e($qs(['category' => $id, 'f' => [], 'fmin' => [], 'fmax' => []])) ?>"><?= icon($c['icon']) ?><span><?= e($c['name']) ?></span><small><?= $counts[$id] ?? 0 ?></small></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php elseif (!empty($tree[$categoryId]['children'])): ?>
      <div class="card filter-card cat-list-card">
        <h3><?= e($category['name']) ?></h3>
        <ul class="cat-list">
          <?php foreach ($tree[$categoryId]['children'] as $cid => $ch): ?>
            <li><a href="<?= e($qs(['category' => $cid])) ?>"><?= icon($ch['icon']) ?><span><?= e($ch['name']) ?></span><small><?= $counts[$cid] ?? 0 ?></small></a></li>
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
          <p class="muted"><?= e(n_('{n} ad found', '{n} ads found', $total)) ?><?= $activeFilters ? ' · ' . e(search_summary($v)) : '' ?></p>
        </div>
        <div class="results-tools">
          <button class="btn btn-ghost mobile-only" type="button" data-open-filters><?= icon('filter') ?><?= t('Filters') ?><?php if ($activeFilters): ?><span class="badge badge-brand"><?= $activeFilters ?></span><?php endif; ?></button>
          <?php if ($savedOn && $query): ?>
            <?php if ($alreadySaved): ?>
              <a class="btn btn-ghost" href="<?= e(url('dashboard/searches.php')) ?>" title="<?= t('Saved') ?>"><?= icon('check') ?><span class="hide-xs"><?= t('Saved') ?></span></a>
            <?php else: ?>
              <form method="post" action="<?= e(url('dashboard/searches.php')) ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="query" value="<?= e(http_build_query($query)) ?>"><input type="hidden" name="name" value="<?= e($heading) ?>">
                <button class="btn btn-ghost" type="submit" title="<?= t('Get an email when new ads match') ?>"><?= icon('bell') ?><span class="hide-xs"><?= t('Save search') ?></span></button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
          <form method="get" action="<?= e(url('listings.php')) ?>" class="sort-form">
            <?php foreach (explode('&', http_build_query($query + ($view !== 'grid' ? ['view' => $view] : []))) as $pair): if ($pair === '') continue; [$k, $val] = array_pad(explode('=', $pair, 2), 2, ''); ?>
              <input type="hidden" name="<?= e(urldecode($k)) ?>" value="<?= e(urldecode($val)) ?>">
            <?php endforeach; ?>
            <select name="sort" aria-label="<?= t('Sort by') ?>" data-autosubmit>
              <?php foreach (['newest' => 'Newest first', 'oldest' => 'Oldest first', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'popular' => 'Most viewed'] as $k => $lbl): ?>
                <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= t($lbl) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
          <div class="seg">
            <a href="<?= e($qs(['view' => ''])) ?>" class="<?= $view === 'grid' ? 'on' : '' ?>" aria-label="<?= t('Grid view') ?>"><?= icon('grid') ?></a>
            <a href="<?= e($qs(['view' => 'list'])) ?>" class="<?= $view === 'list' ? 'on' : '' ?>" aria-label="<?= t('List view') ?>"><?= icon('list') ?></a>
          </div>
        </div>
      </div>

      <?php if ($rows): ?>
        <div class="listing-grid <?= $view === 'list' ? 'as-list' : 'cols-3' ?>">
          <?php foreach ($rows as $l) { include __DIR__ . '/includes/partials/listing-card.php'; } ?>
        </div>
        <?= pagination_links($page, $pages) ?>
      <?php else: ?>
        <div class="empty-state"><?= icon('search') ?><h3><?= t('No ads match your search') ?></h3><p><?= t('Try removing a filter or searching for something broader.') ?><?= $savedOn && $query && !$alreadySaved ? ' ' . t('Or save this search and we\'ll email you when something turns up.') : '' ?></p><a class="btn btn-ghost" href="<?= e(search_url([])) ?>"><?= t('Reset filters') ?></a></div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php';
