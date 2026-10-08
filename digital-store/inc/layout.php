<?php
// Public page header/footer and shared product listing pieces.
declare(strict_types=1);

function header_categories(): array
{
    if (setting('categories_in_header', '1') !== '1') {
        return [];
    }
    return db()->query('SELECT name, slug FROM categories WHERE show_in_header = 1 ORDER BY sort_order, name')->fetchAll();
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
    if (setting('custom_colors') === '1') {
        foreach (['bg' => 'color_bg', 'surface' => 'color_surface', 'text' => 'color_text'] as $var => $key) {
            if (valid_color(setting($key))) {
                $vars[] = "--$var:" . setting($key);
            }
        }
        if (valid_color(setting('color_text'))) {
            $vars[] = '--muted:' . setting('color_text') . 'b3';
        }
    }
    return $vars ? ' style="' . e(implode(';', $vars)) . '"' : '';
}

function theme_class(): string
{
    $mode = setting('theme_mode', 'auto');
    if (setting('custom_colors') === '1') {
        $mode = 'light'; // custom colors replace the automatic dark palette
    }
    return in_array($mode, ['light', 'dark'], true) ? ' class="theme-' . $mode . '"' : '';
}

function site_logo(): string
{
    $store = setting('store_name', 'Free Downloads');
    $logo = setting('logo');
    if ($logo === '' || !is_file(UPLOADS_DIR . '/' . basename($logo))) {
        return '<span class="brand-name">' . e($store) . '</span>';
    }
    $h = max(16, min(200, (int)setting('logo_height', '40')));
    $html = '<img class="logo" src="' . e(base_url('uploads/' . basename($logo))) . '" alt="' . e($store) . '" style="height:' . $h . 'px">';
    if (setting('logo_with_name') === '1') {
        $html .= '<span class="brand-name">' . e($store) . '</span>';
    }
    return $html;
}

function page_header(string $title, string $active = ''): void
{
    $store = setting('store_name', 'Free Downloads');
    $cats = header_categories();
    $pages = menu_pages('header');
    $contact = setting('contact_in_header', '1') === '1';
    $css = setting('custom_css');
    ?>
<!doctype html>
<html lang="en"<?= theme_class() ?><?= theme_style_attr() ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title === $store ? $store : "$title · $store") ?></title>
<link rel="stylesheet" href="<?= e(base_url('assets/style.css')) ?>">
<?php if (trim($css) !== ''): ?><style><?= str_replace('</', '<\/', $css) ?></style><?php endif; ?>
</head>
<body>
<header class="site-header">
  <a class="brand" href="<?= e(base_url()) ?>"><?= site_logo() ?></a>
  <?php if ($cats || $pages || $contact): ?>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-nav"
            onclick="var n=document.getElementById('site-nav');n.classList.toggle('open');this.setAttribute('aria-expanded',n.classList.contains('open'))">☰ Menu</button>
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
      <?php if (is_admin()): ?><a class="admin-link" href="<?= e(base_url('admin/')) ?>">Admin</a><?php endif; ?>
    </nav>
  <?php elseif (is_admin()): ?>
    <a class="admin-link" href="<?= e(base_url('admin/')) ?>">Admin</a>
  <?php endif; ?>
</header>
<main class="container">
<?php
}

function page_footer(): void
{
    ?>
</main>
<footer class="site-footer">
  <nav class="footer-links">
    <?php foreach (menu_pages('footer') as $pg): ?>
      <a href="<?= e(base_url('page.php?slug=' . rawurlencode($pg['slug']))) ?>"><?= e($pg['title']) ?></a>
    <?php endforeach; ?>
    <a href="<?= e(base_url('contact.php')) ?>">Contact us</a>
  </nav>
  <p><?= e(setting('footer_text') !== '' ? setting('footer_text') : '© ' . date('Y') . ' ' . setting('store_name')) ?></p>
</footer>
</body>
</html>
<?php
}

function product_card(array $p): string
{
    ob_start(); ?>
    <a class="product-card" href="<?= e(base_url('product.php?id=' . (int)$p['id'])) ?>">
      <div class="thumb">
        <?php if ($p['image']): ?><img src="<?= e(base_url('uploads/' . $p['image'])) ?>" alt="" loading="lazy"><?php endif; ?>
      </div>
      <div class="product-info">
        <div>
          <?php if (!empty($p['category_name'])): ?><small class="card-cat"><?= e($p['category_name']) ?></small><?php endif; ?>
          <h3><?= e($p['title']) ?></h3>
        </div>
        <span class="badge">FREE</span>
      </div>
    </a>
    <?php return (string)ob_get_clean();
}

function product_grid(array $products): string
{
    return '<div class="grid">' . implode('', array_map('product_card', $products)) . '</div>';
}

/** Products query with the category name attached. */
function product_select(): string
{
    return 'SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id';
}

function pagination(int $page, int $pages, string $baseUrl): string
{
    if ($pages <= 1) {
        return '';
    }
    $sep = strpos($baseUrl, '?') === false ? '?' : '&';
    $html = '<nav class="pagination">';
    if ($page > 1) {
        $html .= '<a href="' . e($baseUrl . $sep . 'page=' . ($page - 1)) . '">← Previous</a>';
    }
    $html .= '<span>Page ' . $page . ' of ' . $pages . '</span>';
    if ($page < $pages) {
        $html .= '<a href="' . e($baseUrl . $sep . 'page=' . ($page + 1)) . '">Next →</a>';
    }
    return $html . '</nav>';
}
