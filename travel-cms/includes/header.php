<?php
if (!defined('APP_ROOT')) {
    exit;
}
$siteName = setting('site_name', 'WanderLuxe');
$lang = current_lang();
// Pages may set: $pageTitle, $seoTitle (full title override), $metaDescription, $ogImage,
// $ogType, $canonical, $noindex and $jsonLd (list of structured-data arrays).
if (!empty($seoTitle)) {
    $pageTitle = $seoTitle;
} else {
    $pageTitle = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' | ' . $siteName : $siteName . ' | ' . setting('site_tagline', __('Premium Travel Experiences & Adventures'));
}
$metaDescription = ($metaDescription ?? '') !== '' ? $metaDescription : setting('meta_description', '');
$ogImage = !empty($ogImage) ? $ogImage : (setting('og_image') ? img_url(setting('og_image')) : '');
$canonical = $canonical ?? current_url();
$noindex = !empty($noindex) || setting('search_engines', '1') === '0';
$wishlistCount = count(wishlist_ids());
$logoFirst = setting('logo_text_1', 'Wander');
$logoSecond = setting('logo_text_2', 'Luxe');
$navLinks = [
    ['tours.php?type=trip', __('Trips & Tours'), 'trips'],
    ['tours.php?type=activity', __('Activities'), 'activities'],
    ['destinations.php', __('Destinations'), 'destinations'],
    ['plan-trip.php', __('Plan a Trip'), 'plan'],
    ['blog.php', __('Travel Stories'), 'blog'],
    ['contact.php', __('Contact'), 'contact'],
];
$activeNav = $activeNav ?? '';
$themeToggle = setting('dark_mode_toggle', '1') === '1';
$themeDefault = setting('dark_mode_default', 'light');
$thisUrl = preg_replace('#^(https?://[^/]+).*$#', '$1', base_url()) . ($_SERVER['REQUEST_URI'] ?? '/');
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= lang_dir() ?>" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <script>
        // Apply the saved light/dark theme before the page paints.
        (function () {
            try {
                var t = localStorage.getItem('tcms_theme'), d = <?= json_encode($themeDefault) ?>;
                if (!<?= $themeToggle ? 'true' : 'false' ?>) t = null;
                var dark = t ? t === 'dark' : (d === 'dark' || (d === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches));
                if (dark) document.documentElement.classList.add('dark');
            } catch (e) {}
        })();
    </script>
    <?php if ($metaDescription): ?><meta name="description" content="<?= e($metaDescription) ?>"><?php endif; ?>
    <?php if ($noindex): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
    <link rel="canonical" href="<?= e(lang_url($canonical, $lang)) ?>">
    <?php if (is_multilingual() && !$noindex): foreach (enabled_languages() as $l): ?>
        <link rel="alternate" hreflang="<?= e($l['code']) ?>" href="<?= e(lang_url($canonical, $l['code'])) ?>">
    <?php endforeach; ?>
        <link rel="alternate" hreflang="x-default" href="<?= e(lang_url($canonical, default_lang())) ?>">
    <?php endif; ?>
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:type" content="<?= e($ogType ?? 'website') ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:url" content="<?= e(lang_url($canonical, $lang)) ?>">
    <meta property="og:locale" content="<?= e(str_replace('-', '_', $lang)) ?>">
    <?php if ($metaDescription): ?><meta property="og:description" content="<?= e($metaDescription) ?>"><?php endif; ?>
    <?php if ($ogImage): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
    <meta name="twitter:card" content="<?= $ogImage ? 'summary_large_image' : 'summary' ?>">
    <?php if (setting('google_verification')): ?><meta name="google-site-verification" content="<?= e(setting('google_verification')) ?>"><?php endif; ?>
    <?php foreach ($jsonLd ?? [] as $ld) { echo json_ld($ld), "\n"; } ?>
    <?php if (setting('favicon')): ?><link rel="icon" href="<?= e(img_url(setting('favicon'))) ?>"><?php endif; ?>
    <?php require APP_ROOT . '/includes/tailwind.php'; ?>
    <?= setting('head_code') /* custom analytics / meta code from Settings */ ?>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased selection:bg-brand-500 selection:text-white">

<header class="fixed top-0 inset-x-0 z-40 transition-all duration-300" id="mainHeader">
    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20 gap-3">
            <a href="<?= e(url()) ?>" class="flex items-center gap-2 group shrink-0">
                <?php if (setting('logo_image')): ?>
                    <img src="<?= e(img_url(setting('logo_image'))) ?>" alt="<?= e($siteName) ?>" class="h-10 w-auto">
                <?php else: ?>
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-brand-600 to-accent-500 flex items-center justify-center text-white font-bold text-xl shadow-lg shadow-brand-500/30 group-hover:scale-105 transition-transform">
                        <i class="fa-solid fa-compass"></i>
                    </div>
                <?php endif; ?>
                <span class="brand-text font-serif font-bold text-2xl tracking-tight text-white drop-shadow-md" dir="ltr"><?= e($logoFirst) ?><span class="text-accent-400"><?= e($logoSecond) ?></span></span>
            </a>

            <div class="hidden lg:flex items-center gap-5 xl:gap-7">
                <?php foreach ($navLinks as [$href, $label, $key]): ?>
                    <a href="<?= e(url($href)) ?>" class="nav-link <?= $activeNav === $key ? 'text-accent-400' : 'text-slate-100' ?> hover:text-accent-400 font-medium transition-colors text-xs xl:text-sm uppercase tracking-wider whitespace-nowrap"><?= e($label) ?></a>
                <?php endforeach; ?>
            </div>

            <div class="flex items-center gap-1 sm:gap-2">
                <?php if (is_multilingual()): ?>
                    <details class="relative lang-menu">
                        <summary class="nav-link list-none cursor-pointer text-slate-100 hover:text-accent-400 h-10 px-2 rounded-full flex items-center gap-1.5 text-xs font-bold uppercase" aria-label="<?= e(__('Language')) ?>">
                            <i class="fa-solid fa-globe text-base"></i><span><?= e($lang) ?></span>
                        </summary>
                        <div class="absolute end-0 mt-2 w-44 bg-white rounded-2xl shadow-2xl border border-slate-100 py-2 z-50">
                            <?php foreach (enabled_languages() as $l): ?>
                                <a href="<?= e(lang_switch_url($thisUrl, $l['code'])) ?>" hreflang="<?= e($l['code']) ?>" lang="<?= e($l['code']) ?>" class="flex items-center justify-between px-4 py-2 text-sm hover:bg-brand-50 <?= $l['code'] === $lang ? 'font-bold text-brand-700' : 'text-slate-700' ?>">
                                    <span><?= e($l['name']) ?></span><?php if ($l['code'] === $lang): ?><i class="fa-solid fa-check text-xs"></i><?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endif; ?>
                <?php if ($themeToggle): ?>
                    <button type="button" data-theme-toggle class="nav-link w-10 h-10 rounded-full flex items-center justify-center text-slate-100 hover:text-accent-400" title="<?= e(__('Dark / light mode')) ?>" aria-label="<?= e(__('Dark / light mode')) ?>">
                        <i class="fa-solid fa-moon text-lg theme-icon-dark"></i><i class="fa-solid fa-sun text-lg theme-icon-light"></i>
                    </button>
                <?php endif; ?>
                <a href="<?= e(url('wishlist.php')) ?>" class="nav-link relative w-10 h-10 rounded-full flex items-center justify-center <?= $activeNav === 'wishlist' ? 'text-accent-400' : 'text-slate-100' ?> hover:text-accent-400" title="<?= e(__('Saved trips')) ?>" aria-label="<?= e(__('Saved trips')) ?>">
                    <i class="fa-solid fa-heart text-lg"></i>
                    <span data-wishlist-count class="<?= $wishlistCount ? '' : 'hidden' ?> absolute -top-0.5 -end-0.5 bg-accent-500 text-white text-[10px] font-bold min-w-[18px] h-[18px] px-1 rounded-full flex items-center justify-center"><?= $wishlistCount ?></span>
                </a>
                <button type="button" onclick="openBookingModal()" class="hidden lg:flex px-5 py-2.5 bg-accent-500 hover:bg-accent-600 text-white rounded-full font-semibold shadow-lg shadow-accent-500/25 transition-all transform hover:-translate-y-0.5 items-center gap-2 text-sm whitespace-nowrap">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span><?= e(__('Inquire / Book')) ?></span>
                </button>
                <button id="mobileMenuBtn" type="button" class="lg:hidden text-white p-2 rounded-lg hover:bg-white/10 focus:outline-none" aria-label="<?= e(__('Menu')) ?>">
                    <i class="fa-solid fa-bars text-2xl"></i>
                </button>
            </div>
        </div>
    </nav>

    <div id="mobileMenu" class="hidden lg:hidden glass-panel border-b border-slate-200 px-4 pt-2 pb-6 space-y-1 shadow-xl">
        <?php foreach ($navLinks as [$href, $label]): ?>
            <a href="<?= e(url($href)) ?>" class="block px-3 py-2 rounded-md text-base font-medium hover:bg-brand-50"><?= e($label) ?></a>
        <?php endforeach; ?>
        <a href="<?= e(url('wishlist.php')) ?>" class="block px-3 py-2 rounded-md text-base font-medium hover:bg-brand-50"><?= e(__('Saved Trips')) ?></a>
        <a href="<?= e(url('my-booking.php')) ?>" class="block px-3 py-2 rounded-md text-base font-medium hover:bg-brand-50"><?= e(__('Manage My Booking')) ?></a>
        <button type="button" onclick="openBookingModal()" class="w-full mt-2 px-5 py-3 bg-accent-500 text-white rounded-xl font-semibold shadow-lg text-center flex items-center justify-center gap-2">
            <i class="fa-solid fa-calendar-check"></i>
            <span><?= e(__('Book Now (Pay Later)')) ?></span>
        </button>
    </div>
</header>

<?php
/** Dark image banner used at the top of inner pages so the transparent header stays readable. */
function page_banner(string $title, string $subtitle = '', string $kicker = '', string $image = ''): void
{
    $image = $image ?: setting('banner_image', 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?auto=format&fit=crop&w=2000&q=80');
    ?>
    <section class="relative pt-36 pb-20 overflow-hidden">
        <div class="absolute inset-0 z-0">
            <img src="<?= e(img_url($image)) ?>" alt="" class="w-full h-full object-cover">
            <div class="absolute inset-0 hero-gradient"></div>
        </div>
        <div class="relative z-10 max-w-4xl mx-auto px-4 text-center text-white">
            <?php if ($kicker): ?>
                <span class="inline-flex items-center px-4 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-accent-400 font-semibold text-xs tracking-wide uppercase mb-4"><?= e($kicker) ?></span>
            <?php endif; ?>
            <h1 class="font-serif text-3xl sm:text-5xl font-bold tracking-tight drop-shadow-lg"><?= e($title) ?></h1>
            <?php if ($subtitle): ?>
                <p class="max-w-2xl mx-auto text-base sm:text-lg text-slate-200 font-light mt-4"><?= e($subtitle) ?></p>
            <?php endif; ?>
        </div>
    </section>
    <?php
}

foreach (get_flashes() as $f): ?>
    <div class="flash-toast fixed bottom-5 end-5 z-[60] bg-slate-900 text-white px-5 py-3 rounded-2xl shadow-2xl border border-slate-700 flex items-center gap-3 max-w-sm">
        <i class="fa-solid <?= $f['type'] === 'error' ? 'fa-circle-exclamation text-rose-400' : 'fa-circle-check text-emerald-400' ?> text-lg"></i>
        <span class="text-sm font-medium"><?= e($f['message']) ?></span>
    </div>
<?php endforeach; ?>
