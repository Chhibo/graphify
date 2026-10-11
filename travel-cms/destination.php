<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/cards.php';

$dest = db_one('SELECT * FROM ' . tbl('destinations') . " WHERE slug = ? AND status = 'active'", [(string) ($_GET['slug'] ?? '')]);
if (!$dest) {
    http_response_code(404);
    $pageTitle = 'Destination not found';
    $noindex = true;
    require APP_ROOT . '/includes/header.php';
    page_banner('Destination not found', 'This destination may have been moved or removed.');
    echo '<div class="text-center py-16"><a href="' . e(url('destinations.php')) . '" class="px-6 py-3 bg-brand-600 text-white rounded-xl font-bold">All destinations</a></div>';
    require APP_ROOT . '/includes/footer.php';
    exit;
}
$trips = tours_query("t.status = 'active' AND t.type = 'trip' AND t.destination_id = ?", [$dest['id']]);
$activities = tours_query("t.status = 'active' AND t.type = 'activity' AND t.destination_id = ?", [$dest['id']]);
$others = array_filter(destinations(true), fn ($d) => (int) $d['id'] !== (int) $dest['id']);

$link = url('destination.php?slug=' . urlencode($dest['slug']));
$pageTitle = $dest['name'] . ($dest['country'] && $dest['country'] !== $dest['name'] ? ', ' . $dest['country'] : '') . ' Tours & Activities';
$seoTitle = $dest['seo_title'];
$metaDescription = $dest['meta_description'] ?: excerpt($dest['short_description'] ?: $dest['description'], 160);
$ogImage = img_url($dest['image']);
$canonical = $link;
$activeNav = 'destinations';
$jsonLd = [
    ['@type' => 'TouristDestination', 'name' => $dest['name'], 'description' => $metaDescription, 'image' => $ogImage, 'url' => $link],
    ['@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url()],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Destinations', 'item' => url('destinations.php')],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $dest['name'], 'item' => $link],
    ]],
];
require APP_ROOT . '/includes/header.php';
page_banner($dest['name'], $dest['short_description'], $dest['country'] ?: 'Destination', $dest['image']);
?>
<section class="py-14 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10 mb-14">
        <div class="lg:col-span-2 prose-content"><?= rich_text($dest['description'] ?: $dest['short_description']) ?></div>
        <div class="bg-white rounded-3xl shadow-lg border border-slate-100 p-6 h-fit space-y-4">
            <div class="flex justify-between text-sm"><span class="text-slate-500">Trips</span><b><?= count($trips) ?></b></div>
            <div class="flex justify-between text-sm"><span class="text-slate-500">Day activities</span><b><?= count($activities) ?></b></div>
            <button type="button" onclick="openBookingModal(<?= (int) ($trips[0]['id'] ?? $activities[0]['id'] ?? 0) ?>)" class="w-full px-6 py-3 bg-accent-500 hover:bg-accent-600 text-white font-bold rounded-xl shadow-lg shadow-accent-500/30 text-sm"><i class="fa-solid fa-calendar-check mr-1"></i> Book in <?= e($dest['name']) ?></button>
            <div class="pt-2 border-t border-slate-100"><p class="text-xs font-bold text-slate-500 uppercase mb-2">Share</p><?php share_buttons($link, $pageTitle); ?></div>
        </div>
    </div>

    <?php if ($trips): ?>
        <h2 class="font-serif text-3xl font-bold text-slate-900 mb-8">Trips in <?= e($dest['name']) ?></h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-14">
            <?php foreach ($trips as $t) { trip_card($t); } ?>
        </div>
    <?php endif; ?>

    <?php if ($activities): ?>
        <h2 class="font-serif text-3xl font-bold text-slate-900 mb-8">Things to Do in <?= e($dest['name']) ?></h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-14">
            <?php foreach ($activities as $a) { activity_card($a); } ?>
        </div>
    <?php endif; ?>

    <?php if (!$trips && !$activities): ?>
        <div class="text-center py-12 bg-white rounded-3xl border border-slate-100 mb-14">
            <i class="fa-solid fa-map-location-dot text-5xl text-slate-300 mb-4"></i>
            <p class="text-slate-500">New experiences in <?= e($dest['name']) ?> are coming soon. <a href="<?= e(url('contact.php')) ?>" class="text-brand-600 font-semibold underline">Ask us</a> about a custom trip!</p>
        </div>
    <?php endif; ?>

    <?php if ($others): ?>
        <h2 class="font-serif text-2xl font-bold text-slate-900 mb-6">More Destinations</h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach (array_slice($others, 0, 4) as $d) { destination_card($d, 'h-48'); } ?>
        </div>
    <?php endif; ?>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
