<?php
if (!defined('APP_ROOT')) {
    exit;
}
$siteName = setting('site_name', 'WanderLuxe');
$pageTitle = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' | ' . $siteName : $siteName . ' | ' . setting('site_tagline', 'Premium Travel Experiences & Adventures');
$metaDescription = $metaDescription ?? setting('meta_description', '');
$logoFirst = setting('logo_text_1', 'Wander');
$logoSecond = setting('logo_text_2', 'Luxe');
$navLinks = [
    ['tours.php?type=trip', 'Trips & Tours', 'trips'],
    ['tours.php?type=activity', 'Activities', 'activities'],
    ['blog.php', 'Travel Stories', 'blog'],
    ['index.php#reviews', 'Reviews', 'reviews'],
    ['contact.php', 'Contact', 'contact'],
];
$activeNav = $activeNav ?? '';
?>
<!DOCTYPE html>
<html lang="<?= e(setting('site_language', 'en')) ?>" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?php if ($metaDescription): ?><meta name="description" content="<?= e($metaDescription) ?>"><?php endif; ?>
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
    <?php if (setting('favicon')): ?><link rel="icon" href="<?= e(img_url(setting('favicon'))) ?>"><?php endif; ?>
    <?php require APP_ROOT . '/includes/tailwind.php'; ?>
    <?= setting('head_code') /* custom analytics / meta code from Settings */ ?>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased selection:bg-brand-500 selection:text-white">

<header class="fixed top-0 left-0 right-0 z-40 transition-all duration-300" id="mainHeader">
    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            <a href="<?= e(url()) ?>" class="flex items-center space-x-2 group">
                <?php if (setting('logo_image')): ?>
                    <img src="<?= e(img_url(setting('logo_image'))) ?>" alt="<?= e($siteName) ?>" class="h-10 w-auto">
                <?php else: ?>
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-brand-600 to-accent-500 flex items-center justify-center text-white font-bold text-xl shadow-lg shadow-brand-500/30 group-hover:scale-105 transition-transform">
                        <i class="fa-solid fa-compass"></i>
                    </div>
                <?php endif; ?>
                <span class="brand-text font-serif font-bold text-2xl tracking-tight text-white drop-shadow-md"><?= e($logoFirst) ?><span class="text-accent-400"><?= e($logoSecond) ?></span></span>
            </a>

            <div class="hidden md:flex items-center space-x-8">
                <?php foreach ($navLinks as [$href, $label, $key]): ?>
                    <a href="<?= e(url($href)) ?>" class="nav-link <?= $activeNav === $key ? 'text-accent-400' : 'text-slate-100' ?> hover:text-accent-400 font-medium transition-colors text-sm uppercase tracking-wider"><?= e($label) ?></a>
                <?php endforeach; ?>
            </div>

            <div class="hidden md:flex items-center space-x-4">
                <button type="button" onclick="openBookingModal()" class="px-5 py-2.5 bg-accent-500 hover:bg-accent-600 text-white rounded-full font-semibold shadow-lg shadow-accent-500/25 transition-all transform hover:-translate-y-0.5 flex items-center space-x-2 text-sm">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>Inquire / Book</span>
                </button>
            </div>

            <div class="md:hidden flex items-center">
                <button id="mobileMenuBtn" type="button" class="text-white p-2 rounded-lg hover:bg-white/10 focus:outline-none" aria-label="Menu">
                    <i class="fa-solid fa-bars text-2xl"></i>
                </button>
            </div>
        </div>
    </nav>

    <div id="mobileMenu" class="hidden md:hidden glass-panel border-b border-slate-200 px-4 pt-2 pb-6 space-y-1 shadow-xl">
        <?php foreach ($navLinks as [$href, $label]): ?>
            <a href="<?= e(url($href)) ?>" class="block px-3 py-2 rounded-md text-base font-medium hover:bg-brand-50"><?= e($label) ?></a>
        <?php endforeach; ?>
        <a href="<?= e(url('my-booking.php')) ?>" class="block px-3 py-2 rounded-md text-base font-medium hover:bg-brand-50">Manage My Booking</a>
        <button type="button" onclick="openBookingModal()" class="w-full mt-2 px-5 py-3 bg-accent-500 text-white rounded-xl font-semibold shadow-lg text-center flex items-center justify-center space-x-2">
            <i class="fa-solid fa-calendar-check"></i>
            <span>Book Now (Pay Later)</span>
        </button>
    </div>
</header>

<?php
/** Dark image banner used at the top of inner pages so the transparent header stays readable. */
function page_banner(string $title, string $subtitle = '', string $kicker = '', string $image = ''): void
{
    $image = $image ?: setting('banner_image', 'https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=2000&q=80');
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
    <div class="flash-toast fixed bottom-5 right-5 z-[60] bg-slate-900 text-white px-5 py-3 rounded-2xl shadow-2xl border border-slate-700 flex items-center space-x-3 max-w-sm">
        <i class="fa-solid <?= $f['type'] === 'error' ? 'fa-circle-exclamation text-rose-400' : 'fa-circle-check text-emerald-400' ?> text-lg"></i>
        <span class="text-sm font-medium"><?= e($f['message']) ?></span>
    </div>
<?php endforeach; ?>
