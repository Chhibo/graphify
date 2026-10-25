<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/cards.php';

$type = in_array($_GET['type'] ?? '', ['trip', 'activity'], true) ? $_GET['type'] : '';
$q = trim((string) ($_GET['q'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$allHidden = prices_hidden();
$max = $allHidden ? 0 : (int) ($_GET['max'] ?? 0);
$sort = $allHidden && in_array($_GET['sort'] ?? '', ['price_asc', 'price_desc'], true) ? '' : ($_GET['sort'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;

$where = ["t.status = 'active'"];
$params = [];
if ($type) {
    $where[] = 't.type = ?';
    $params[] = $type;
}
if ($q !== '') {
    // Also search translated titles and destination names (any language).
    $where[] = '(t.title LIKE ? OR t.destination LIKE ? OR t.short_description LIKE ? OR d.name LIKE ? OR d.country LIKE ?'
        . ' OR t.id IN (SELECT item_id FROM ' . tbl('translations') . " WHERE type = 'tour' AND field IN ('title', 'destination') AND value LIKE ?)"
        . ' OR t.destination_id IN (SELECT item_id FROM ' . tbl('translations') . " WHERE type = 'destination' AND field IN ('name', 'country') AND value LIKE ?))";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like, $like, $like);
}
$destination = trim((string) ($_GET['destination'] ?? ''));
if ($destination !== '') {
    $where[] = 'd.slug = ?';
    $params[] = $destination;
}
if ($category !== '') {
    $where[] = 'c.slug = ?';
    $params[] = $category;
}
if ($max > 0) {
    $where[] = 't.hide_price = 0 AND t.price <= ?';
    $params[] = $max;
}
$orders = [
    'price_asc' => 't.price ASC',
    'price_desc' => 't.price DESC',
    'rating' => 't.rating DESC, t.reviews_count DESC',
    'newest' => 't.id DESC',
];
$order = $orders[$sort] ?? 't.type DESC, t.sort_order, t.id';
$whereSql = implode(' AND ', $where);

$total = (int) db_value('SELECT COUNT(*) FROM ' . tbl('tours') . ' t LEFT JOIN ' . tbl('categories') . ' c ON c.id = t.category_id LEFT JOIN ' . tbl('destinations') . " d ON d.id = t.destination_id AND d.status = 'active' WHERE " . $whereSql, $params);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$items = tours_query($whereSql, $params, $order . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage));

$titles = [
    'trip' => [__('Trips & Tours'), __('Multi-day packages handpicked by our travel experts.'), __('Curated Packages'), 'trips'],
    'activity' => [__('Day Activities'), __('Half-day and full-day experiences led by local experts.'), __('Day Excursions'), 'activities'],
    '' => [__('Find Your Next Adventure'), __('Search every trip and activity we offer.'), __('Search Results'), 'trips'],
];
[$heading, $sub, $kicker, $activeNav] = $titles[$type];
$pageTitle = $heading;
$canonical = url('tours.php' . ($type ? '?type=' . $type : ''));
$noindex = ($q !== '' || $category !== '' || $destination !== '' || $max || $sort || $page > 1);

function page_link(array $overrides): string
{
    $query = array_merge($_GET, $overrides);
    $query = array_filter($query, fn ($v) => $v !== '' && $v !== null);
    return url('tours.php?' . http_build_query($query));
}

require APP_ROOT . '/includes/header.php';
page_banner($heading, $sub, $kicker);
?>

<section class="py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <form method="get" class="bg-white rounded-2xl shadow-lg border border-slate-100 p-4 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 -mt-24 relative z-10 mb-10">
        <div class="col-span-2">
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1"><?= e(__('Keyword')) ?></label>
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('e.g., Bali')) ?>" class="w-full bg-slate-100 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1"><?= e(__('Type')) ?></label>
            <select name="type" class="w-full bg-slate-100 border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                <option value=""><?= e(__('All')) ?></option>
                <option value="trip" <?= $type === 'trip' ? 'selected' : '' ?>><?= e(__('Trips')) ?></option>
                <option value="activity" <?= $type === 'activity' ? 'selected' : '' ?>><?= e(__('Activities')) ?></option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1"><?= e(__('Destination')) ?></label>
            <select name="destination" class="w-full bg-slate-100 border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                <option value=""><?= e(__('All')) ?></option>
                <?php foreach (destinations() as $d): ?>
                    <option value="<?= e($d['slug']) ?>" <?= $destination === $d['slug'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1"><?= e(__('Style')) ?></label>
            <select name="category" class="w-full bg-slate-100 border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                <option value=""><?= e(__('All Styles')) ?></option>
                <?php foreach (categories() as $c): ?>
                    <option value="<?= e($c['slug']) ?>" <?= $category === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1"><?= e(__('Sort by')) ?></label>
            <select name="sort" class="w-full bg-slate-100 border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                <option value=""><?= e(__('Recommended')) ?></option>
                <?php if (!$allHidden): ?>
                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>><?= e(__('Price: low to high')) ?></option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>><?= e(__('Price: high to low')) ?></option>
                <?php endif; ?>
                <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>><?= e(__('Top rated')) ?></option>
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>><?= e(__('Newest')) ?></option>
            </select>
        </div>
        <div class="flex items-end">
            <?php if ($max): ?><input type="hidden" name="max" value="<?= $max ?>"><?php endif; ?>
            <button class="w-full h-[42px] bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl text-sm"><i class="fa-solid fa-magnifying-glass me-1"></i> <?= e(__('Search')) ?></button>
        </div>
    </form>

    <div class="flex items-center justify-between mb-6 text-sm text-slate-500">
        <span><?= e(__f($total === 1 ? '%d result' : '%d results', $total)) ?><?= $max ? ' ' . e(__f('under %s', money($max))) : '' ?></span>
        <?php if ($q || $category || $destination || $max || $sort): ?><a href="<?= e(url('tours.php' . ($type ? '?type=' . $type : ''))) ?>" class="text-brand-600 font-semibold hover:underline"><?= e(__('Clear filters')) ?></a><?php endif; ?>
    </div>

    <?php if (!$items): ?>
        <div class="text-center py-16 bg-white rounded-3xl border border-slate-100">
            <i class="fa-solid fa-map-location-dot text-5xl text-slate-300 mb-4"></i>
            <p class="text-slate-500"><?= e(__('No experiences match your search. Try different filters.')) ?></p>
            <a href="<?= e(url('plan-trip.php')) ?>" class="inline-block mt-4 text-sm font-bold text-brand-600 underline"><?= e(__('Ask us to plan a custom trip')) ?></a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 <?= $type === 'activity' ? 'lg:grid-cols-4 gap-6' : 'lg:grid-cols-3 gap-8' ?>">
            <?php foreach ($items as $item) {
                $item['type'] === 'activity' && $type === 'activity' ? activity_card($item) : trip_card($item);
            } ?>
        </div>
    <?php endif; ?>

    <?php if ($pages > 1): ?>
        <nav class="flex justify-center mt-12 gap-2">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <a href="<?= e(page_link(['page' => $i])) ?>" class="w-10 h-10 flex items-center justify-center rounded-xl text-sm font-bold <?= $i === $page ? 'bg-brand-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</section>

<?php require APP_ROOT . '/includes/footer.php'; ?>
