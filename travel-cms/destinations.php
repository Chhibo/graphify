<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/cards.php';

$items = destinations();
$pageTitle = setting('destinations_title', __('Explore Destinations'));
$metaDescription = setting('destinations_subtitle', __('Discover our handpicked destinations around the world and the trips and activities available in each one.'));
$canonical = url('destinations.php');
$activeNav = 'destinations';
$jsonLd = [[
    '@type' => 'ItemList',
    'itemListElement' => array_map(fn ($d, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $d['name'], 'url' => url('destination.php?slug=' . urlencode($d['slug']))], $items, array_keys($items)),
]];
require APP_ROOT . '/includes/header.php';
page_banner($pageTitle, $metaDescription, setting('destinations_kicker', __('Where To Next?')));
?>
<section class="py-14 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <?php if ($items): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($items as $i => $d) { destination_card($d, $i < 2 ? 'h-80' : 'h-72'); } ?>
        </div>
    <?php else: ?>
        <p class="text-center text-slate-500 py-10"><?= e(__('No destinations have been added yet.')) ?></p>
    <?php endif; ?>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
