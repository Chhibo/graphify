<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/cards.php';

$slug = (string) ($_GET['slug'] ?? '');
$rows = tours_query("t.slug = ? AND t.status = 'active'", [$slug]);
if (!$rows) {
    http_response_code(404);
    $pageTitle = 'Not found';
    require APP_ROOT . '/includes/header.php';
    page_banner('Experience not found', 'The trip you are looking for is no longer available.');
    echo '<div class="text-center py-16"><a href="' . e(url('tours.php')) . '" class="px-6 py-3 bg-brand-600 text-white rounded-xl font-bold">Browse all experiences</a></div>';
    require APP_ROOT . '/includes/footer.php';
    exit;
}
$tour = $rows[0];
$inclusions = lines($tour['inclusions']);
$itinerary = lines($tour['itinerary']);
$related = tours_query("t.status = 'active' AND t.type = ? AND t.id <> ?", [$tour['type'], $tour['id']], 't.sort_order, t.id', 3);
$tourReviews = db_all('SELECT * FROM ' . tbl('reviews') . " WHERE status = 'approved' AND trip = ? ORDER BY created_at DESC LIMIT 6", [$tour['title']]);

$pageTitle = $tour['title'];
$metaDescription = excerpt($tour['short_description'] ?: $tour['description'], 160);
$ogImage = img_url($tour['image']);
$activeNav = $tour['type'] === 'activity' ? 'activities' : 'trips';
require APP_ROOT . '/includes/header.php';
?>

<section class="relative pt-36 pb-16 overflow-hidden min-h-[60vh] flex items-end">
    <div class="absolute inset-0 z-0">
        <img src="<?= e(img_url($tour['image'])) ?>" alt="<?= e($tour['title']) ?>" class="w-full h-full object-cover">
        <div class="absolute inset-0 hero-gradient"></div>
    </div>
    <div class="relative z-10 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 text-white">
        <div class="flex flex-wrap gap-2 mb-4">
            <?php if ($tour['category_name']): ?><span class="bg-brand-600 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider"><?= e($tour['category_name']) ?></span><?php endif; ?>
            <span class="bg-white/15 backdrop-blur text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider"><?= $tour['type'] === 'activity' ? 'Day Activity' : 'Multi-Day Trip' ?></span>
        </div>
        <h1 class="font-serif text-3xl sm:text-5xl font-bold drop-shadow-lg max-w-4xl"><?= e($tour['title']) ?></h1>
        <div class="flex flex-wrap items-center gap-5 mt-4 text-sm text-slate-200">
            <span><i class="fa-solid fa-location-dot text-brand-400 mr-1"></i> <?= e($tour['destination']) ?></span>
            <span><i class="fa-solid fa-clock text-brand-400 mr-1"></i> <?= e($tour['duration']) ?></span>
            <span class="text-amber-400 font-bold"><i class="fa-solid fa-star mr-1"></i> <?= e(number_format((float) $tour['rating'], 1)) ?> <span class="text-slate-300 font-normal">(<?= (int) $tour['reviews_count'] ?> reviews)</span></span>
        </div>
    </div>
</section>

<section class="py-14 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-3 gap-10">
    <div class="lg:col-span-2 space-y-8">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 bg-white border border-slate-100 shadow p-5 rounded-2xl text-xs">
            <div><span class="text-slate-400 block mb-1 font-medium">Duration</span><span class="font-bold text-slate-800"><?= e($tour['duration']) ?></span></div>
            <div><span class="text-slate-400 block mb-1 font-medium">Group Size</span><span class="font-bold text-slate-800">Max <?= (int) $tour['max_guests'] ?> People</span></div>
            <div><span class="text-slate-400 block mb-1 font-medium">Location</span><span class="font-bold text-slate-800"><?= e($tour['destination']) ?></span></div>
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
                <span class="text-xs uppercase tracking-wider text-brand-100 block">Price per guest</span>
                <span class="text-4xl font-extrabold"><?= e(money($tour['price'])) ?></span>
            </div>
            <div class="p-6 space-y-4 text-sm">
                <p class="flex items-center"><i class="fa-solid fa-shield-halved text-brand-600 w-6"></i> No online payment required</p>
                <p class="flex items-center"><i class="fa-solid fa-ticket text-brand-600 w-6"></i> Instant reservation voucher</p>
                <p class="flex items-center"><i class="fa-solid fa-hand-holding-dollar text-brand-600 w-6"></i> Pay cash or card on arrival</p>
                <p class="flex items-center"><i class="fa-solid fa-users text-brand-600 w-6"></i> Up to <?= (int) $tour['max_guests'] ?> guests per booking</p>
                <button type="button" onclick="openBookingModal(<?= (int) $tour['id'] ?>)" class="w-full mt-2 px-6 py-3 bg-accent-500 hover:bg-accent-600 text-white font-bold rounded-xl shadow-lg shadow-accent-500/30 transition">
                    <i class="fa-solid fa-calendar-check mr-1"></i> Book This Experience
                </button>
                <?php if (setting('contact_whatsapp')): ?>
                    <a href="https://wa.me/<?= e(preg_replace('/\D/', '', setting('contact_whatsapp'))) ?>?text=<?= rawurlencode('Hi! I have a question about: ' . $tour['title']) ?>" target="_blank" rel="noopener" class="block text-center w-full px-6 py-3 border border-emerald-500 text-emerald-600 font-bold rounded-xl hover:bg-emerald-50 transition">
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

<?php require APP_ROOT . '/includes/footer.php'; ?>
