<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/cards.php';

$slug = (string) ($_GET['slug'] ?? '');
$rows = tours_query("t.slug = ? AND t.status = 'active'", [$slug]);
if (!$rows) {
    http_response_code(404);
    $pageTitle = 'Not found';
    $noindex = true;
    require APP_ROOT . '/includes/header.php';
    page_banner('Experience not found', 'The trip you are looking for is no longer available.');
    echo '<div class="text-center py-16"><a href="' . e(url('tours.php')) . '" class="px-6 py-3 bg-brand-600 text-white rounded-xl font-bold">Browse all experiences</a></div>';
    require APP_ROOT . '/includes/footer.php';
    exit;
}
$tour = $rows[0];
$inclusions = lines($tour['inclusions']);
$itinerary = lines($tour['itinerary']);
$related = tours_query("t.status = 'active' AND t.type = ? AND t.id <> ?", [$tour['type'], $tour['id']], 't.destination_id = ' . (int) $tour['destination_id'] . ' DESC, t.sort_order, t.id', 3);
$tourReviews = db_all('SELECT * FROM ' . tbl('reviews') . " WHERE status = 'approved' AND trip = ? ORDER BY created_at DESC LIMIT 6", [$tour['title']]);
$hidden = prices_hidden($tour);
$prices = tour_prices($tour);

// Gallery: the main image first, then the extra photos.
$gallery = [];
if ($tour['image']) {
    $gallery[] = ['image' => $tour['image'], 'caption' => $tour['title']];
}
foreach (db_all('SELECT image, caption FROM ' . tbl('tour_images') . ' WHERE tour_id = ? ORDER BY sort_order, id', [$tour['id']]) as $img) {
    $gallery[] = $img;
}

$link = url('tour.php?slug=' . urlencode($tour['slug']));
$listLink = url('tours.php?type=' . $tour['type']);
$pageTitle = $tour['title'];
$seoTitle = $tour['seo_title'];
$metaDescription = $tour['meta_description'] ?: excerpt($tour['short_description'] ?: $tour['description'], 160);
$ogImage = img_url($tour['image']);
$canonical = $link;
$activeNav = $tour['type'] === 'activity' ? 'activities' : 'trips';

// Structured data for Google (rich results with rating and price).
$product = [
    '@type' => 'Product',
    'name' => $tour['title'],
    'description' => $metaDescription,
    'image' => array_map(fn ($g) => img_url($g['image']), $gallery),
    'sku' => $tour['slug'],
    'brand' => ['@type' => 'Brand', 'name' => setting('site_name', 'WanderLuxe')],
    'url' => $link,
];
if ((int) $tour['reviews_count'] > 0) {
    $product['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => (float) $tour['rating'], 'reviewCount' => (int) $tour['reviews_count'], 'bestRating' => 5];
}
if (!$hidden) {
    $product['offers'] = [
        '@type' => 'Offer', 'price' => number_format($prices['adult'], 2, '.', ''), 'priceCurrency' => setting('currency_code', 'USD'),
        'availability' => 'https://schema.org/InStock', 'url' => $link,
    ];
}
$crumbs = [['Home', url()], [$tour['type'] === 'activity' ? 'Activities' : 'Trips & Tours', $listLink]];
if ($tour['destination_slug']) {
    $crumbs[] = [$tour['destination_name'], url('destination.php?slug=' . urlencode($tour['destination_slug']))];
}
$crumbs[] = [$tour['title'], $link];
$jsonLd = [$product, [
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(fn ($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1]], $crumbs, array_keys($crumbs)),
]];

require APP_ROOT . '/includes/header.php';
?>

<section class="relative pt-36 pb-16 overflow-hidden min-h-[60vh] flex items-end">
    <div class="absolute inset-0 z-0">
        <img src="<?= e(img_url($tour['image'])) ?>" alt="<?= e($tour['title']) ?>" class="w-full h-full object-cover">
        <div class="absolute inset-0 hero-gradient"></div>
    </div>
    <div class="relative z-10 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 text-white">
        <nav class="text-xs text-slate-300 mb-4" aria-label="Breadcrumb">
            <?php foreach ($crumbs as $i => [$label, $href]): ?>
                <?php if ($i === count($crumbs) - 1): ?><span class="text-white"><?= e($label) ?></span>
                <?php else: ?><a href="<?= e($href) ?>" class="hover:text-accent-400"><?= e($label) ?></a> <i class="fa-solid fa-chevron-right text-[8px] mx-1.5 opacity-60"></i><?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div class="flex flex-wrap gap-2 mb-4">
            <?php if ($tour['category_name']): ?><span class="bg-brand-600 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider"><?= e($tour['category_name']) ?></span><?php endif; ?>
            <span class="bg-white/15 backdrop-blur text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider"><?= $tour['type'] === 'activity' ? 'Day Activity' : 'Multi-Day Trip' ?></span>
        </div>
        <h1 class="font-serif text-3xl sm:text-5xl font-bold drop-shadow-lg max-w-4xl"><?= e($tour['title']) ?></h1>
        <div class="flex flex-wrap items-center gap-5 mt-4 text-sm text-slate-200">
            <?php if ($tour['destination_slug']): ?>
                <a href="<?= e(url('destination.php?slug=' . urlencode($tour['destination_slug']))) ?>" class="hover:text-accent-400"><i class="fa-solid fa-location-dot text-brand-400 mr-1"></i> <?= e($tour['destination'] ?: $tour['destination_name']) ?></a>
            <?php else: ?>
                <span><i class="fa-solid fa-location-dot text-brand-400 mr-1"></i> <?= e($tour['destination']) ?></span>
            <?php endif; ?>
            <span><i class="fa-solid fa-clock text-brand-400 mr-1"></i> <?= e($tour['duration']) ?></span>
            <span class="text-amber-400 font-bold"><i class="fa-solid fa-star mr-1"></i> <?= e(number_format((float) $tour['rating'], 1)) ?> <span class="text-slate-300 font-normal">(<?= (int) $tour['reviews_count'] ?> reviews)</span></span>
        </div>
    </div>
</section>

<section class="py-14 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-3 gap-10">
    <div class="lg:col-span-2 space-y-8">
        <?php if (count($gallery) > 1): ?>
        <div class="grid grid-cols-4 grid-rows-2 gap-2 h-72 sm:h-96 rounded-3xl overflow-hidden">
            <?php foreach (array_slice($gallery, 0, 5) as $i => $g):
                $more = count($gallery) - 5;
                $span = $i === 0 ? 'col-span-4 sm:col-span-2 row-span-2' : 'hidden sm:block';
                if (count($gallery) === 2) { $span = $i === 0 ? 'col-span-2 row-span-2' : 'col-span-2 row-span-2'; }
                elseif (count($gallery) === 3 && $i > 0) { $span = 'hidden sm:block col-span-2'; } ?>
                <button type="button" onclick="openLightbox(<?= $i ?>)" class="relative overflow-hidden group <?= $span ?>">
                    <img src="<?= e(img_url($g['image'])) ?>" alt="<?= e($g['caption'] ?: $tour['title']) ?>" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <?php if ($i === 4 && $more > 0): ?><span class="absolute inset-0 bg-slate-900/60 text-white font-bold text-lg flex items-center justify-center">+<?= $more ?> photos</span><?php endif; ?>
                    <?php if ($i === 0): ?><span class="absolute bottom-3 left-3 bg-white/90 text-slate-800 text-xs font-bold px-3 py-1.5 rounded-full shadow"><i class="fa-solid fa-images mr-1"></i> <?= count($gallery) ?> photos</span><?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="hidden">
            <?php foreach ($gallery as $g): ?><span data-gallery-item data-full="<?= e(img_url($g['image'])) ?>" data-caption="<?= e($g['caption']) ?>"></span><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 bg-white border border-slate-100 shadow p-5 rounded-2xl text-xs">
            <div><span class="text-slate-400 block mb-1 font-medium">Duration</span><span class="font-bold text-slate-800"><?= e($tour['duration']) ?></span></div>
            <div><span class="text-slate-400 block mb-1 font-medium">Group Size</span><span class="font-bold text-slate-800">Max <?= (int) $tour['max_guests'] ?> People</span></div>
            <div><span class="text-slate-400 block mb-1 font-medium">Location</span><span class="font-bold text-slate-800"><?= e($tour['destination'] ?: $tour['destination_name']) ?></span></div>
            <div><span class="text-slate-400 block mb-1 font-medium">Payment</span><span class="font-bold text-emerald-600">Pay on Arrival</span></div>
        </div>

        <div>
            <h2 class="font-serif text-2xl font-bold text-slate-900 mb-4">Overview</h2>
            <div class="prose-content"><?= rich_text($tour['description'] ?: $tour['short_description']) ?></div>
        </div>

        <?php if ($inclusions): ?>
        <div>
            <h2 class="font-serif text-2xl font-bold text-slate-900 mb-4">What's Included</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <?php foreach ($inclusions as $inc): ?>
                    <div class="flex items-center space-x-2 bg-brand-50 rounded-xl px-4 py-3"><i class="fa-solid fa-circle-check text-brand-600"></i><span><?= e($inc) ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($itinerary): ?>
        <div>
            <h2 class="font-serif text-2xl font-bold text-slate-900 mb-4">Itinerary</h2>
            <ol class="relative border-l-2 border-brand-100 ml-3 space-y-6">
                <?php foreach ($itinerary as $i => $step):
                    $parts = explode('|', $step, 2); ?>
                    <li class="ml-6">
                        <span class="absolute -left-[13px] w-6 h-6 rounded-full bg-brand-600 text-white text-[10px] font-bold flex items-center justify-center"><?= $i + 1 ?></span>
                        <h3 class="font-bold text-slate-900"><?= e(trim($parts[0])) ?></h3>
                        <?php if (isset($parts[1])): ?><p class="text-sm text-slate-600 mt-1"><?= e(trim($parts[1])) ?></p><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <?php endif; ?>

        <div class="flex flex-wrap items-center justify-between gap-3 border-y border-slate-200 py-4">
            <span class="text-sm font-bold text-slate-700">Share this <?= $tour['type'] === 'activity' ? 'activity' : 'trip' ?></span>
            <?php share_buttons($link, $tour['title']); ?>
        </div>

        <?php if ($tourReviews): ?>
        <div class="bg-slate-900 rounded-3xl p-6 space-y-4">
            <h2 class="font-serif text-2xl font-bold text-white">Traveler Reviews</h2>
            <?php foreach ($tourReviews as $r) { review_card($r); } ?>
        </div>
        <?php endif; ?>
    </div>

    <aside>
        <div class="lg:sticky lg:top-28 bg-white rounded-3xl shadow-xl border border-slate-100 overflow-hidden">
            <div class="bg-gradient-to-r from-brand-700 to-brand-500 p-6 text-white">
                <?php if ($hidden): ?>
                    <span class="text-xs uppercase tracking-wider text-brand-100 block">Price</span>
                    <span class="text-3xl font-extrabold"><?= e(tour_price($tour)) ?></span>
                    <span class="block text-xs text-brand-100 mt-1">Book now &mdash; we will confirm the price with you.</span>
                <?php else: ?>
                    <span class="text-xs uppercase tracking-wider text-brand-100 block">Price per adult</span>
                    <span class="text-4xl font-extrabold"><?= e(money($prices['adult'])) ?></span>
                    <?php if ($tour['allow_children'] || $tour['allow_infants']): ?>
                        <div class="mt-3 pt-3 border-t border-white/20 text-xs space-y-1 text-brand-50">
                            <?php if ($tour['allow_children']): ?><div class="flex justify-between"><span>Child (<?= e(setting('child_age_label', 'Ages 2-11')) ?>)</span><b><?= e(money($prices['child'])) ?></b></div><?php endif; ?>
                            <?php if ($tour['allow_infants']): ?><div class="flex justify-between"><span>Infant (<?= e(setting('infant_age_label', 'Under 2')) ?>)</span><b><?= $prices['infant'] > 0 ? e(money($prices['infant'])) : 'Free' ?></b></div><?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="p-6 space-y-4 text-sm">
                <p class="flex items-center"><i class="fa-solid fa-shield-halved text-brand-600 w-6"></i> No online payment required</p>
                <p class="flex items-center"><i class="fa-solid fa-ticket text-brand-600 w-6"></i> Instant reservation voucher</p>
                <p class="flex items-center"><i class="fa-solid fa-hand-holding-dollar text-brand-600 w-6"></i> Pay cash or card on arrival</p>
                <p class="flex items-center"><i class="fa-solid fa-users text-brand-600 w-6"></i> Up to <?= (int) $tour['max_guests'] ?> guests per booking</p>
                <?php if (!$tour['allow_children'] || !$tour['allow_infants']): ?>
                    <p class="flex items-center text-amber-700"><i class="fa-solid fa-child-reaching w-6"></i> <?= !$tour['allow_children'] ? 'Adults only' : 'Not suitable for infants' ?></p>
                <?php endif; ?>
                <button type="button" onclick="openBookingModal(<?= (int) $tour['id'] ?>)" class="w-full mt-2 px-6 py-3 bg-accent-500 hover:bg-accent-600 text-white font-bold rounded-xl shadow-lg shadow-accent-500/30 transition">
                    <i class="fa-solid fa-calendar-check mr-1"></i> Book This Experience
                </button>
                <?php $saved = in_wishlist((int) $tour['id']); ?>
                <button type="button" data-wishlist="<?= (int) $tour['id'] ?>" aria-pressed="<?= $saved ? 'true' : 'false' ?>" class="w-full px-6 py-3 border border-slate-200 font-bold rounded-xl hover:bg-slate-50 transition <?= $saved ? 'text-rose-500' : 'text-slate-400' ?>">
                    <i class="fa-solid fa-heart mr-1"></i> <span data-wishlist-label class="text-slate-700"><?= $saved ? 'Saved' : 'Save' ?></span> <span class="text-slate-700">to wishlist</span>
                </button>
                <?php if (setting('contact_whatsapp')): ?>
                    <a href="https://wa.me/<?= e(preg_replace('/\D/', '', setting('contact_whatsapp'))) ?>?text=<?= rawurlencode('Hi! I have a question about: ' . $tour['title'] . ' ' . $link) ?>" target="_blank" rel="noopener" class="block text-center w-full px-6 py-3 border border-emerald-500 text-emerald-600 font-bold rounded-xl hover:bg-emerald-50 transition">
                        <i class="fa-brands fa-whatsapp mr-1"></i> Ask on WhatsApp
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</section>

<?php if ($related): ?>
<section class="pb-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <h2 class="font-serif text-3xl font-bold text-slate-900 mb-8">You May Also Like</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($related as $r) { trip_card($r); } ?>
    </div>
</section>
<?php endif; ?>

<?php if (count($gallery) > 1): ?>
<!-- Gallery lightbox -->
<div id="lightbox" class="fixed inset-0 z-[55] hidden flex-col items-center justify-center bg-slate-950/95 p-4" data-modal>
    <button type="button" onclick="closeModal('lightbox')" class="absolute top-4 right-4 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white text-xl" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    <button type="button" onclick="lightboxStep(-1)" class="absolute left-2 sm:left-6 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 text-white text-xl" aria-label="Previous photo"><i class="fa-solid fa-chevron-left"></i></button>
    <img id="lightboxImg" src="" alt="" class="max-h-[80vh] max-w-full rounded-2xl shadow-2xl object-contain">
    <p class="text-white text-sm mt-4"><span id="lightboxCaption"></span> <span id="lightboxCounter" class="text-slate-400 ml-2"></span></p>
    <button type="button" onclick="lightboxStep(1)" class="absolute right-2 sm:right-6 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 text-white text-xl" aria-label="Next photo"><i class="fa-solid fa-chevron-right"></i></button>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/includes/footer.php'; ?>
