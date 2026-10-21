<?php
// Public page header/footer and shared product listing pieces.
declare(strict_types=1);

/** Fonts offered in Admin -> Appearance. '' = the device's own font (fastest). */
function font_choices(): array
{
    return [
        '' => ['System default', ''],
        'inter' => ['Inter', 'Inter:wght@400;500;600;700;800'],
        'poppins' => ['Poppins', 'Poppins:wght@400;500;600;700;800'],
        'jakarta' => ['Plus Jakarta Sans', 'Plus+Jakarta+Sans:wght@400;500;600;700;800'],
        'dm-sans' => ['DM Sans', 'DM+Sans:wght@400;500;600;700;800'],
        'outfit' => ['Outfit', 'Outfit:wght@400;500;600;700;800'],
        'nunito' => ['Nunito', 'Nunito:wght@400;600;700;800'],
    ];
}

function header_categories(): array
{
    if (setting('categories_in_header', '1') !== '1') {
        return [];
    }
    return db()->query('SELECT name, slug FROM categories WHERE show_in_header = 1 ORDER BY sort_order, name')->fetchAll();
}

function all_categories(): array
{
    static $cats = null;
    if ($cats === null) {
        $cats = db()->query('SELECT c.name, c.slug, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.active = 1) AS items
            FROM categories c ORDER BY c.sort_order, c.name')->fetchAll();
    }
    return $cats;
}

function menu_pages(string $where): array
{
    $col = $where === 'header' ? 'show_in_header' : 'show_in_footer';
    return db()->query("SELECT title, slug FROM pages WHERE $col = 1 ORDER BY sort_order, title")->fetchAll();
}

/** Theme colors chosen in Admin -> Appearance, as inline CSS variables. */
function theme_style_attr(): string
{
    $vars = [];
    if (valid_color(setting('color_accent'))) {
        $vars[] = '--accent:' . setting('color_accent');
    }
    if (valid_color(setting('color_accent2'))) {
        $vars[] = '--accent-2:' . setting('color_accent2');
    }
    if (setting('custom_colors') === '1') {
        foreach (['bg' => 'color_bg', 'surface' => 'color_surface', 'text' => 'color_text'] as $var => $key) {
            if (valid_color(setting($key))) {
                $vars[] = "--$var:" . setting($key);
            }
        }
        if (valid_color(setting('color_text'))) {
            $vars[] = '--muted:' . setting('color_text') . 'a6';
            $vars[] = '--border:' . setting('color_text') . '1f';
        }
    }
    $font = font_choices()[setting('font')] ?? null;
    if ($font && $font[1] !== '') {
        $vars[] = "--font:'" . $font[0] . "', system-ui, sans-serif";
    }
    $vars[] = '--radius:' . max(0, min(28, (int)setting('corner_radius', '16'))) . 'px';
    return ' style="' . e(implode(';', $vars)) . '"';
}

function theme_mode(): string
{
    if (setting('custom_colors') === '1') {
        return 'light'; // custom colors replace the automatic dark palette
    }
    $mode = setting('theme_mode', 'auto');
    return in_array($mode, ['light', 'dark'], true) ? $mode : 'auto';
}

function site_logo(): string
{
    $store = setting('store_name', 'Free Downloads');
    $logo = setting('logo');
    if ($logo === '' || !is_file(UPLOADS_DIR . '/' . basename($logo))) {
        return '<span class="brand-mark">' . icon('gift', 20) . '</span><span class="brand-name">' . e($store) . '</span>';
    }
    $h = max(16, min(200, (int)setting('logo_height', '40')));
    $html = '<img class="logo" src="' . e(base_url('uploads/' . basename($logo))) . '" alt="' . e($store) . '" style="height:' . $h . 'px">';
    if (setting('logo_with_name') === '1') {
        $html .= '<span class="brand-name">' . e($store) . '</span>';
    }
    return $html;
}

function social_links(): array
{
    $links = [];
    foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'x-brand' => 'X', 'telegram' => 'Telegram', 'tiktok' => 'TikTok'] as $key => $label) {
        $url = setting('social_' . str_replace('-brand', '', $key));
        if (preg_match('#^https?://#i', $url)) {
            $links[] = [$key, $label, $url];
        }
    }
    return $links;
}

/**
 * $meta: description, image (absolute URL), type.
 */
function page_header(string $title, string $active = '', array $meta = []): void
{
    $store = setting('store_name', 'Free Downloads');
    $fullTitle = $title === $store ? $store : "$title · $store";
    $desc = $meta['description'] ?? setting_or('meta_description', setting_or('store_tagline', 'Free digital downloads.'));
    $desc = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($desc))), 0, 160);
    $image = $meta['image'] ?? (setting('logo') !== '' ? base_url('uploads/' . setting('logo')) : '');
    $canonical = current_url();
    $cats = header_categories();
    $pages = menu_pages('header');
    $contact = setting('contact_in_header', '1') === '1';
    $mode = theme_mode();
    $font = font_choices()[setting('font')] ?? null;
    $css = setting('custom_css');
    $q = (string)($_GET['q'] ?? '');
    ?>
<!doctype html>
<html lang="<?= e(setting_or('site_language', 'en')) ?>"<?= $mode !== 'auto' ? ' class="theme-' . $mode . '"' : '' ?><?= theme_style_attr() ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:site_name" content="<?= e($store) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:type" content="<?= e($meta['type'] ?? 'website') ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<?php if ($image !== ''): ?><meta property="og:image" content="<?= e($image) ?>">
<meta name="twitter:card" content="summary_large_image"><?php endif; ?>
<?php if (valid_color(setting('color_accent'))): ?><meta name="theme-color" content="<?= e(setting('color_accent')) ?>"><?php endif; ?>
<?php if (setting('favicon') !== ''): ?><link rel="icon" href="<?= e(base_url('uploads/' . setting('favicon'))) ?>"><?php endif; ?>
<?php if ($mode === 'auto'): ?>
<script>try{var t=localStorage.getItem('theme');if(t==='light'||t==='dark')document.documentElement.classList.add('theme-'+t)}catch(e){}</script>
<?php endif; ?>
<?php if ($font && $font[1] !== ''): ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=<?= e($font[1]) ?>&display=swap">
<?php endif; ?>
<link rel="stylesheet" href="<?= e(base_url('assets/style.css')) ?>?v=3">
<?php if (trim($css) !== ''): ?><style><?= str_replace('</', '<\/', $css) ?></style><?php endif; ?>
<?= setting('head_code') ?>
</head>
<body>
<?php if (setting('announce_show') === '1' && trim(setting('announce_text')) !== ''): ?>
  <div class="announce">
    <?= icon('megaphone', 16) ?>
    <span><?= e(setting('announce_text')) ?></span>
    <?php if (preg_match('#^https?://#i', setting('announce_url'))): ?>
      <a href="<?= e(setting('announce_url')) ?>"><?= e(setting_or('announce_link_text', 'Learn more')) ?> →</a>
    <?php endif; ?>
  </div>
<?php endif; ?>
<header class="site-header">
  <div class="header-inner">
    <a class="brand" href="<?= e(base_url()) ?>"><?= site_logo() ?></a>
    <nav class="site-nav" id="site-nav">
      <a href="<?= e(base_url()) ?>" class="<?= $active === 'home' ? 'active' : '' ?>">Home</a>
      <?php foreach ($cats as $c): ?>
        <a href="<?= e(base_url('category.php?slug=' . rawurlencode($c['slug']))) ?>" class="<?= $active === 'cat:' . $c['slug'] ? 'active' : '' ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
      <?php foreach ($pages as $pg): ?>
        <a href="<?= e(base_url('page.php?slug=' . rawurlencode($pg['slug']))) ?>" class="<?= $active === 'page:' . $pg['slug'] ? 'active' : '' ?>"><?= e($pg['title']) ?></a>
      <?php endforeach; ?>
      <?php if ($contact): ?>
        <a href="<?= e(base_url('contact.php')) ?>" class="<?= $active === 'contact' ? 'active' : '' ?>">Contact</a>
      <?php endif; ?>
      <form class="nav-search" action="<?= e(base_url('search.php')) ?>" method="get" role="search">
        <?= icon('search', 16) ?>
        <input type="search" name="q" placeholder="Search downloads…" value="<?= e($q) ?>" aria-label="Search">
      </form>
    </nav>
    <div class="header-actions">
      <a class="icon-btn search-link" href="<?= e(base_url('search.php')) ?>" aria-label="Search"><?= icon('search') ?></a>
      <?php if ($mode === 'auto'): ?>
        <button class="icon-btn theme-toggle" type="button" aria-label="Switch light/dark mode" onclick="toggleTheme()">
          <span class="when-light"><?= icon('moon') ?></span><span class="when-dark"><?= icon('sun') ?></span>
        </button>
      <?php endif; ?>
      <?php if (is_admin()): ?><a class="icon-btn" href="<?= e(base_url('admin/')) ?>" title="Admin panel"><?= icon('dashboard') ?></a><?php endif; ?>
      <button class="icon-btn menu-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Menu"
              onclick="var n=document.getElementById('site-nav');n.classList.toggle('open');this.setAttribute('aria-expanded',n.classList.contains('open'))"><?= icon('menu', 20) ?></button>
    </div>
  </div>
</header>
<main class="container">
<?php
}

function page_footer(): void
{
    $about = setting('footer_about');
    $socials = social_links();
    $cats = array_slice(array_filter(all_categories(), fn($c) => $c['items'] > 0), 0, 8);
    $pages = menu_pages('footer');
    ?>
</main>
<footer class="site-footer">
  <div class="footer-grid">
    <div class="footer-brand">
      <a class="brand" href="<?= e(base_url()) ?>"><?= site_logo() ?></a>
      <p><?= e($about !== '' ? $about : setting_or('store_tagline', 'Free digital downloads. Pick one and unlock it in minutes.')) ?></p>
      <?php if ($socials): ?>
        <div class="socials">
          <?php foreach ($socials as [$icon, $label, $url]): ?>
            <a href="<?= e($url) ?>" target="_blank" rel="noopener" aria-label="<?= e($label) ?>" title="<?= e($label) ?>"><?= icon($icon) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php if ($cats): ?>
      <div>
        <h4>Categories</h4>
        <?php foreach ($cats as $c): ?><a href="<?= e(base_url('category.php?slug=' . rawurlencode($c['slug']))) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div>
      <h4>Information</h4>
      <?php foreach ($pages as $pg): ?>
        <a href="<?= e(base_url('page.php?slug=' . rawurlencode($pg['slug']))) ?>"><?= e($pg['title']) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(base_url('contact.php')) ?>">Contact us</a>
    </div>
  </div>
  <div class="footer-bottom">
    <p><?= e(setting('footer_text') !== '' ? setting('footer_text') : '© ' . date('Y') . ' ' . setting('store_name') . '. All rights reserved.') ?></p>
  </div>
</footer>
<button class="to-top" type="button" aria-label="Back to top" onclick="window.scrollTo({top:0,behavior:'smooth'})"><?= icon('arrow-right', 18) ?></button>
<script>
function toggleTheme(){var r=document.documentElement,d=r.classList.contains('theme-dark')||(!r.classList.contains('theme-light')&&matchMedia('(prefers-color-scheme: dark)').matches);r.classList.remove('theme-light','theme-dark');r.classList.add(d?'theme-light':'theme-dark');try{localStorage.setItem('theme',d?'light':'dark')}catch(e){}}
(function(){var b=document.querySelector('.to-top');addEventListener('scroll',function(){b.classList.toggle('show',scrollY>600)},{passive:true})})();
</script>
<?= setting('footer_code') ?>
</body>
</html>
<?php
}

function product_card(array $p): string
{
    $showCount = setting('show_downloads', '1') === '1';
    ob_start(); ?>
    <a class="product-card" href="<?= e(base_url('product.php?id=' . (int)$p['id'])) ?>">
      <div class="thumb">
        <?php if ($p['image']): ?><img src="<?= e(base_url('uploads/' . $p['image'])) ?>" alt="" loading="lazy">
        <?php else: ?><span class="thumb-empty"><?= icon('package', 40) ?></span><?php endif; ?>
        <div class="thumb-badges">
          <?php if (($p['badge'] ?? '') !== ''): ?><span class="ribbon"><?= e($p['badge']) ?></span><?php endif; ?>
          <?php if (!empty($p['featured'])): ?><span class="ribbon ribbon-star" title="Featured"><?= icon('star', 12) ?></span><?php endif; ?>
        </div>
      </div>
      <div class="product-info">
        <?php if (!empty($p['category_name'])): ?><span class="card-cat"><?= e($p['category_name']) ?></span><?php endif; ?>
        <h3><?= e($p['title']) ?></h3>
        <div class="card-foot">
          <span class="free-pill"><?= icon('gift', 13) ?> Free</span>
          <?php if ($showCount && (int)$p['downloads'] > 0): ?>
            <span class="card-stat"><?= icon('download', 13) ?> <?= short_number((int)$p['downloads']) ?></span>
          <?php endif; ?>
          <span class="card-go"><?= icon('arrow-right', 16) ?></span>
        </div>
      </div>
    </a>
    <?php return (string)ob_get_clean();
}

function product_grid(array $products, string $extraClass = ''): string
{
    return '<div class="grid ' . e($extraClass) . '">' . implode('', array_map('product_card', $products)) . '</div>';
}

/** Products query with the category name attached. */
function product_select(): string
{
    return 'SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id';
}

function section_head(string $title, string $link = '', string $linkText = 'View all'): string
{
    $html = '<div class="section-head"><h2>' . e($title) . '</h2>';
    if ($link !== '') {
        $html .= '<a class="section-link" href="' . e($link) . '">' . e($linkText) . ' ' . icon('arrow-right', 16) . '</a>';
    }
    return $html . '</div>';
}

function pagination(int $page, int $pages, string $baseUrl): string
{
    if ($pages <= 1) {
        return '';
    }
    $sep = strpos($baseUrl, '?') === false ? '?' : '&';
    $url = fn(int $n) => e($baseUrl . $sep . 'page=' . $n);
    $html = '<nav class="pagination" aria-label="Pages">';
    $html .= $page > 1 ? '<a href="' . $url($page - 1) . '">←</a>' : '<span class="disabled">←</span>';
    for ($i = 1; $i <= $pages; $i++) {
        if ($i === 1 || $i === $pages || abs($i - $page) <= 1) {
            $html .= $i === $page ? '<span class="current">' . $i . '</span>' : '<a href="' . $url($i) . '">' . $i . '</a>';
        } elseif (abs($i - $page) === 2) {
            $html .= '<span class="gap">…</span>';
        }
    }
    $html .= $page < $pages ? '<a href="' . $url($page + 1) . '">→</a>' : '<span class="disabled">→</span>';
    return $html . '</nav>';
}

function empty_state(string $text, string $icon = 'package'): string
{
    return '<div class="empty-state">' . icon($icon, 40) . '<p>' . $text . '</p></div>';
}
