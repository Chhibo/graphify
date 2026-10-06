<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/cards.php';

$trips = tours_query("t.type = 'trip' AND t.status = 'active' AND t.is_featured = 1", [], 't.sort_order, t.id', (int) setting('home_trips_count', 6));
$activities = tours_query("t.type = 'activity' AND t.status = 'active' AND t.is_featured = 1", [], 't.sort_order, t.id', (int) setting('home_activities_count', 4));
$posts = db_all('SELECT * FROM ' . tbl('posts') . " WHERE status = 'published' AND published_at <= ? ORDER BY published_at DESC LIMIT 3", [now()]);
$reviews = db_all('SELECT * FROM ' . tbl('reviews') . " WHERE status = 'approved' ORDER BY created_at DESC LIMIT 10");
$reviewStats = db_one('SELECT COUNT(*) AS n, AVG(rating) AS avg FROM ' . tbl('reviews') . " WHERE status = 'approved'");
$avgRating = setting('average_rating', $reviewStats['n'] ? number_format((float) $reviewStats['avg'], 1) : '5.0');
$reviewSummary = setting('reviews_summary', 'Based on ' . (int) $reviewStats['n'] . ' verified reviews');

// Only show filter tabs for categories that actually have featured trips.
$tripCats = [];
foreach ($trips as $t) {
    if ($t['category_slug']) {
        $tripCats[$t['category_slug']] = $t['category_name'];
    }
}

$activeNav = '';
require APP_ROOT . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="relative min-h-screen flex items-center justify-center pt-20 pb-16 overflow-hidden">
    <div class="absolute inset-0 z-0">
        <img src="<?= e(img_url(setting('hero_image', 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=2000&q=80'))) ?>" alt="" class="w-full h-full object-cover scale-105">
        <div class="absolute inset-0 hero-gradient"></div>
    </div>

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-white">
        <span class="inline-flex items-center space-x-2 px-4 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-accent-400 font-semibold text-xs sm:text-sm tracking-wide uppercase mb-6 shadow-inner">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
            <span><?= e(setting('hero_badge', 'Unforgettable Luxury & Adventure Tours')) ?></span>
        </span>
        <h1 class="font-serif text-4xl sm:text-6xl lg:text-7xl font-bold tracking-tight mb-6 leading-tight drop-shadow-lg">
            <?= e(setting('hero_title_1', "Explore the World's Most")) ?> <br class="hidden sm:inline"><span class="text-transparent bg-clip-text bg-gradient-to-r from-accent-400 via-brand-400 to-teal-300"><?= e(setting('hero_title_2', 'Extraordinary Places')) ?></span>
        </h1>
        <p class="max-w-2xl mx-auto text-lg sm:text-xl text-slate-200 font-light mb-10 leading-relaxed drop-shadow">
            <?= e(setting('hero_subtitle', 'Handcrafted guided itineraries, thrilling outdoor activities, and hassle-free instant reservations with zero upfront fees.')) ?>
        </p>

        <!-- Search & Filter Card -->
        <form method="get" action="<?= e(url('tours.php')) ?>" class="max-w-5xl mx-auto glass-panel rounded-2xl sm:rounded-3xl p-4 sm:p-6 shadow-2xl text-slate-800 border border-white/30 text-left">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5" for="heroQ">
                        <i class="fa-solid fa-location-dot text-brand-500 mr-1"></i> Destination
                    </label>
                    <input type="text" id="heroQ" name="q" placeholder="e.g., Bali, Switzerland, Egypt" class="w-full bg-slate-100 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition-all font-medium">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5" for="heroCat">
                        <i class="fa-solid fa-layer-group text-brand-500 mr-1"></i> Travel Style
                    </label>
                    <select id="heroCat" name="category" class="w-full bg-slate-100 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 font-medium text-slate-700">
                        <option value="">All Styles</option>
                        <?php foreach (categories() as $c): ?>
                            <option value="<?= e($c['slug']) ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5" for="heroMax">
                        <i class="fa-solid fa-wallet text-brand-500 mr-1"></i> Max Budget (<?= e(setting('currency_symbol', '$')) ?>)
                    </label>
                    <select id="heroMax" name="max" class="w-full bg-slate-100 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 font-medium text-slate-700">
                        <option value="">Any Price</option>
                        <?php foreach (array_filter(array_map('intval', explode(',', setting('budget_options', '1000,2000,3500')))) as $b): ?>
                            <option value="<?= $b ?>">Under <?= e(money($b)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full h-[46px] bg-gradient-to-r from-brand-600 to-brand-500 hover:from-brand-700 hover:to-brand-600 text-white font-bold rounded-xl shadow-lg shadow-brand-500/30 flex items-center justify-center space-x-2 transition-all transform hover:scale-[1.02]">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <span>Find Trips</span>
                    </button>
                </div>
            </div>
        </form>

        <div class="mt-12 flex flex-wrap justify-center items-center gap-6 text-sm text-slate-300 font-medium">
            <span class="flex items-center space-x-2"><i class="fa-solid fa-circle-check text-brand-400"></i><span><?= e(setting('trust_1', 'Instant Voucher Code')) ?></span></span>
            <span class="flex items-center space-x-2"><i class="fa-solid fa-shield-halved text-brand-400"></i><span><?= e(setting('trust_2', 'Zero Upfront Payment')) ?></span></span>
            <span class="flex items-center space-x-2"><i class="fa-solid fa-star text-amber-400"></i><span><?= e(setting('trust_3', $avgRating . '/5 Average Rating')) ?></span></span>
        </div>
    </div>
</section>

<!-- Featured Trips Section -->
<section id="trips" class="py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-12">
        <div>
            <span class="text-brand-600 font-bold uppercase tracking-widest text-xs"><?= e(setting('trips_kicker', 'Curated Packages')) ?></span>
            <h2 class="font-serif text-3xl sm:text-4xl font-bold mt-1 text-slate-900"><?= e(setting('trips_title', 'Featured Travel Destinations')) ?></h2>
        </div>
        <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
            <button type="button" data-filter-btn="all" class="px-4 py-2 rounded-xl text-sm font-semibold transition-all bg-brand-600 text-white shadow-md">All Trips</button>
            <?php foreach ($tripCats as $slug => $name): ?>
                <button type="button" data-filter-btn="<?= e($slug) ?>" class="px-4 py-2 rounded-xl text-sm font-semibold transition-all bg-slate-200 text-slate-700 hover:bg-slate-300"><?= e($name) ?></button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($trips as $trip) { trip_card($trip); } ?>
    </div>
    <p id="tripsEmpty" class="<?= $trips ? 'hidden' : '' ?> text-center text-slate-500 py-10">No trips found in this category.</p>
    <div class="text-center mt-10">
        <a href="<?= e(url('tours.php?type=trip')) ?>" class="inline-flex items-center px-6 py-3 rounded-full border-2 border-brand-600 text-brand-700 font-bold text-sm hover:bg-brand-600 hover:text-white transition">View All Trips <i class="fa-solid fa-arrow-right ml-2"></i></a>
    </div>
</section>

<?php if ($activities): ?>
<!-- Activities Section -->
<section id="activities" class="py-20 bg-slate-100 relative overflow-hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <span class="text-accent-500 font-bold uppercase tracking-widest text-xs"><?= e(setting('activities_kicker', 'Day Excursions')) ?></span>
            <h2 class="font-serif text-3xl sm:text-4xl font-bold mt-1 text-slate-900"><?= e(setting('activities_title', 'Popular Outdoor Activities')) ?></h2>
            <p class="text-slate-600 text-sm mt-2"><?= e(setting('activities_subtitle', 'Elevate your vacation with thrilling half-day and full-day immersive experiences led by local experts.')) ?></p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach ($activities as $act) { activity_card($act); } ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($posts): ?>
<!-- Blog Section -->
<section id="blog" class="py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center max-w-2xl mx-auto mb-12">
        <span class="text-brand-600 font-bold uppercase tracking-widest text-xs"><?= e(setting('blog_kicker', 'Insiders Guide')) ?></span>
        <h2 class="font-serif text-3xl sm:text-4xl font-bold mt-1 text-slate-900"><?= e(setting('blog_title', 'Latest Travel Stories & Advice')) ?></h2>
        <p class="text-slate-600 text-sm mt-2"><?= e(setting('blog_subtitle', 'Read expert tips, destination highlights, and travel itineraries written by world explorers.')) ?></p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <?php foreach ($posts as $p) { post_card($p); } ?>
    </div>
</section>
<?php endif; ?>

<!-- Reviews Section -->
<section id="reviews" class="py-20 bg-slate-900 text-white relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">
            <div class="lg:col-span-5 space-y-8">
                <div>
                    <span class="text-accent-400 font-bold uppercase tracking-widest text-xs">Verified Feedback</span>
                    <h2 class="font-serif text-3xl sm:text-4xl font-bold mt-1 text-white">What Travelers Say</h2>
                    <div class="flex items-center space-x-3 mt-4">
                        <div class="text-4xl font-extrabold text-amber-400"><?= e($avgRating) ?></div>
                        <div>
                            <div class="flex text-amber-400 text-sm"><?= stars((int) round((float) $avgRating)) ?></div>
                            <div class="text-xs text-slate-400 mt-1"><?= e($reviewSummary) ?></div>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-800/80 p-6 rounded-2xl border border-slate-700/80 shadow-xl">
                    <h3 class="font-bold text-lg mb-4 text-white flex items-center justify-between">
                        <span>Share Your Experience</span>
                        <i class="fa-solid fa-pen-to-square text-accent-400"></i>
                    </h3>
                    <form method="post" action="<?= e(url('review.php')) ?>" class="space-y-4">
                        <?= csrf_field() ?>
                        <input type="text" name="website" value="" class="hidden" tabindex="-1" autocomplete="off">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Your Name</label>
                            <input type="text" name="name" required maxlength="150" placeholder="e.g., Sarah M." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Trip / Activity Visited</label>
                            <input type="text" name="trip" required maxlength="200" list="reviewTrips" placeholder="e.g., Bali Luxury Escape" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <datalist id="reviewTrips">
                                <?php foreach (bookable_tours() as $o): ?><option value="<?= e($o['title']) ?>"><?php endforeach; ?>
                            </datalist>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Rating</label>
                            <select name="rating" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm text-amber-400 focus:outline-none focus:ring-2 focus:ring-brand-500 font-bold">
                                <option value="5">★★★★★ (5/5) Exceptional</option>
                                <option value="4">★★★★☆ (4/5) Great</option>
                                <option value="3">★★★☆☆ (3/5) Average</option>
                                <option value="2">★★☆☆☆ (2/5) Poor</option>
                                <option value="1">★☆☆☆☆ (1/5) Bad</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Your Review</label>
                            <textarea name="content" required rows="3" maxlength="2000" placeholder="Tell us about your adventure..." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-brand-500"></textarea>
                        </div>
                        <button type="submit" class="w-full bg-brand-600 hover:bg-brand-500 text-white font-bold py-2.5 rounded-xl transition-all shadow-lg text-sm">Submit Review</button>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-7 space-y-4 max-h-[650px] overflow-y-auto pr-2 hide-scrollbar">
                <?php foreach ($reviews as $r) { review_card($r); } ?>
                <?php if (!$reviews): ?><p class="text-slate-400 text-sm">Be the first to share your experience!</p><?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Why Choose Us -->
<section id="about" class="py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
        <?php
        $featureDefaults = [
            1 => ['fa-handshake', 'No Online Payment Needed', 'Reserve your slot instantly without entering credit card details. Pay directly to your tour guide upon arrival.', 'bg-brand-100 text-brand-600'],
            2 => ['fa-user-shield', 'Verified Local Guides', 'Every excursion is led by certified, multilingual experts committed to safety, authenticity, and fun.', 'bg-accent-100 text-accent-500'],
            3 => ['fa-headset', '24/7 Dedicated Support', 'Our concierge desk is available round-the-clock on WhatsApp & phone to assist with trip customisations.', 'bg-teal-100 text-teal-600'],
        ];
        foreach ($featureDefaults as $i => [$icon, $title, $text, $cls]): ?>
            <div class="p-8 rounded-2xl bg-white border border-slate-100 shadow-lg">
                <div class="w-14 h-14 mx-auto mb-6 <?= $cls ?> rounded-2xl flex items-center justify-center text-2xl">
                    <i class="fa-solid <?= e(setting('feature_' . $i . '_icon', $icon)) ?>"></i>
                </div>
                <h3 class="font-bold text-lg mb-2"><?= e(setting('feature_' . $i . '_title', $title)) ?></h3>
                <p class="text-slate-600 text-sm"><?= e(setting('feature_' . $i . '_text', $text)) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require APP_ROOT . '/includes/footer.php'; ?>
