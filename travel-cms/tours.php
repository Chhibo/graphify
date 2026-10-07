<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/cards.php';

$type = in_array($_GET['type'] ?? '', ['trip', 'activity'], true) ? $_GET['type'] : '';
$q = trim((string) ($_GET['q'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$max = (int) ($_GET['max'] ?? 0);
$sort = $_GET['sort'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;

$where = ["t.status = 'active'"];
$params = [];
if ($type) {
    $where[] = 't.type = ?';
    $params[] = $type;
}
if ($q !== '') {
    $where[] = '(t.title LIKE ? OR t.destination LIKE ? OR t.short_description LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
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

$total = (int) db_value('SELECT COUNT(*) FROM ' . tbl('tours') . ' t LEFT JOIN ' . tbl('categories') . ' c ON c.id = t.category_id WHERE ' . $whereSql, $params);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$items = tours_query($whereSql, $params, $order . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage));

$titles = [
    'trip' => ['Trips & Tours', 'Multi-day packages handpicked by our travel experts.', 'Curated Packages', 'trips'],
    'activity' => ['Day Activities', 'Half-day and full-day experiences led by local experts.', 'Day Excursions', 'activities'],
    '' => ['Find Your Next Adventure', 'Search every trip and activity we offer.', 'Search Results', 'trips'],
];
[$heading, $sub, $kicker, $activeNav] = $titles[$type];
$pageTitle = $heading;

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
    <form method="get" class="bg-white rounded-2xl shadow-lg border border-slate-100 p-4 grid grid-cols-1 md:grid-cols-6 gap-3 -mt-24 relative z-10 mb-10">
        <div class="md:col-span-2">
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Destination / keyword</label>
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="e.g., Bali" class="w-full bg-slate-100 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Type</label>
            <select name="type" class="w-full bg-slate-100 border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                <option value="">All</option>
                <option value="trip" <?= $type === 'trip' ? 'selected' : '' ?>>Trips</option>
                <option value="activity" <?= $type === 'activity' ? 'selected' : '' ?>>Activities</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Style</label>
            <select name="category" class="w-full bg-slate-100 border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                <option value="">All Styles</option>
                <?php foreach (categories() as $c): ?>
                    <option value="<?= e($c['slug']) ?>" <?= $category === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Sort by</label>
            <select name="sort" class="w-full bg-slate-100 border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                <option value="">Recommended</option>
                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
                <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Top rated</option>
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
            </select>
        </div>
        <div class="flex items-end">
            <?php if ($max): ?><input type="hidden" name="max" value="<?= $max ?>"><?php endif; ?>
            <button class="w-full h-[42px] bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl text-sm"><i class="fa-solid fa-magnifying-glass mr-1"></i> Search</button>
        </div>
    </form>

    <div class="flex items-center justify-between mb-6 text-sm text-slate-500">
        <span><?= $total ?> result<?= $total === 1 ? '' : 's' ?><?= $max ? ' under ' . e(money($max)) : '' ?></span>
        <?php if ($q || $category || $max || $sort): ?><a href="<?= e(url('tours.php' . ($type ? '?type=' . $type : ''))) ?>" class="text-brand-600 font-semibold hover:underline">Clear filters</a><?php endif; ?>
    </div>

    <?php if (!$items): ?>
        <div class="text-center py-16 bg-white rounded-3xl border border-slate-100">
            <i class="fa-solid fa-map-location-dot text-5xl text-slate-300 mb-4"></i>
            <p class="text-slate-500">No experiences match your search. Try different filters.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 <?= $type === 'activity' ? 'lg:grid-cols-4 gap-6' : 'lg:grid-cols-3 gap-8' ?>">
            <?php foreach ($items as $item) {
                $item['type'] === 'activity' && $type === 'activity' ? activity_card($item) : trip_card($item);
            } ?>
        </div>
    <?php endif; ?>

    <?php if ($pages > 1): ?>
        <nav class="flex justify-center mt-12 space-x-2">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <a href="<?= e(page_link(['page' => $i])) ?>" class="w-10 h-10 flex items-center justify-center rounded-xl text-sm font-bold <?= $i === $page ? 'bg-brand-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</section>

<?php require APP_ROOT . '/includes/footer.php'; ?>
