<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/cards.php';

$ids = wishlist_ids();
$items = $ids ? tours_query("t.status = 'active' AND t.id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')', $ids, 't.type DESC, t.title') : [];

$pageTitle = __('Saved Trips');
$noindex = true;
$activeNav = 'wishlist';
require APP_ROOT . '/includes/header.php';
page_banner(__('Your Saved Trips'), __('Trips and activities you saved with the heart button. They stay on this device.'), __('Wishlist'));
?>
<section class="py-14 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <?php if ($items): ?>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-8">
            <p class="text-sm text-slate-500"><?= e(__f(count($items) === 1 ? '%d saved experience' : '%d saved experiences', count($items))) ?></p>
            <?php
            $listText = __('My travel wishlist:') . "\n" . implode("\n", array_map(fn ($t) => '• ' . $t['title'] . ' - ' . url('tour.php?slug=' . urlencode($t['slug'])), $items));
            ?>
            <a href="https://wa.me/?text=<?= rawurlencode($listText) ?>" target="_blank" rel="noopener" class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-bold"><i class="fa-brands fa-whatsapp me-1"></i> <?= e(__('Share my list')) ?></a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($items as $t): ?>
                <div data-wishlist-card class="relative">
                    <?php trip_card($t); ?>
                    <button type="button" data-wishlist="<?= (int) $t['id'] ?>" data-wishlist-remove-card class="absolute -top-3 -start-3 z-10 px-3 py-1.5 rounded-full bg-white shadow-lg text-xs font-bold text-rose-500 hover:bg-rose-50"><i class="fa-solid fa-xmark me-1"></i> <?= e(__('Remove')) ?></button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-16 bg-white rounded-3xl border border-slate-100 max-w-2xl mx-auto">
            <div class="w-16 h-16 rounded-full bg-rose-50 text-rose-400 flex items-center justify-center text-3xl mx-auto mb-4"><i class="fa-regular fa-heart"></i></div>
            <h2 class="font-serif text-2xl font-bold text-slate-900"><?= e(__('Your wishlist is empty')) ?></h2>
            <p class="text-slate-500 text-sm mt-2 mb-6"><?= e(__('Tap the heart on any trip or activity to save it here.')) ?> <i class="fa-solid fa-heart text-rose-500"></i></p>
            <a href="<?= e(url('tours.php')) ?>" class="px-6 py-3 bg-brand-600 hover:bg-brand-700 text-white rounded-xl font-bold text-sm"><?= e(__('Browse experiences')) ?></a>
        </div>
    <?php endif; ?>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
