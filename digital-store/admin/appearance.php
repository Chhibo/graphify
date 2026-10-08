<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin_layout.php';
require_admin();

$defaults = [
    'color_accent' => '#4f46e5',
    'color_bg' => '#f6f7fb',
    'color_surface' => '#ffffff',
    'color_text' => '#1c2030',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'remove_logo') {
        if (setting('logo') !== '') {
            @unlink(UPLOADS_DIR . '/' . basename(setting('logo')));
        }
        save_setting('logo', '');
        flash('Logo removed.');
        redirect('admin/appearance.php');
    }
    if ($action === 'reset_colors') {
        foreach ($defaults as $k => $v) {
            save_setting($k, '');
        }
        save_setting('custom_colors', '0');
        flash('Colors reset to the default theme.');
        redirect('admin/appearance.php');
    }

    $logo = save_uploaded_image($_FILES['logo'] ?? null, $errors);
    if ($logo) {
        if (setting('logo') !== '') {
            @unlink(UPLOADS_DIR . '/' . basename(setting('logo')));
        }
        save_setting('logo', $logo);
    }
    save_setting('logo_height', (string)max(16, min(200, (int)($_POST['logo_height'] ?? 40))));
    save_setting('logo_with_name', isset($_POST['logo_with_name']) ? '1' : '0');

    $mode = (string)($_POST['theme_mode'] ?? 'auto');
    save_setting('theme_mode', in_array($mode, ['auto', 'light', 'dark'], true) ? $mode : 'auto');
    save_setting('custom_colors', isset($_POST['custom_colors']) ? '1' : '0');
    foreach (array_keys($defaults) as $k) {
        $c = strtolower(trim((string)($_POST[$k] ?? '')));
        // Keep "default" as empty so the theme's own light/dark colors still apply.
        save_setting($k, valid_color($c) && $c !== $defaults[$k] ? $c : '');
    }

    save_setting('categories_in_header', isset($_POST['categories_in_header']) ? '1' : '0');
    save_setting('contact_in_header', isset($_POST['contact_in_header']) ? '1' : '0');
    save_setting('hero_show', isset($_POST['hero_show']) ? '1' : '0');
    foreach (['hero_title', 'store_tagline', 'featured_title', 'latest_title', 'footer_text'] as $k) {
        save_setting($k, trim((string)($_POST[$k] ?? '')));
    }
    save_setting('featured_count', (string)max(1, min(12, (int)($_POST['featured_count'] ?? 3))));
    save_setting('home_cat_items', (string)max(1, min(24, (int)($_POST['home_cat_items'] ?? 4))));
    save_setting('per_page', (string)max(1, min(60, (int)($_POST['per_page'] ?? 12))));
    save_setting('custom_css', (string)($_POST['custom_css'] ?? ''));

    if (!$errors) {
        flash('Appearance saved.');
        redirect('admin/appearance.php');
    }
}

$color = fn(string $k) => setting($k) !== '' ? setting($k) : $defaults[$k];

admin_header('Appearance');
?>
<?php foreach ($errors as $err): ?><p class="bad"><?= e($err) ?></p><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="form">
  <?= csrf_field() ?>

  <div class="card">
    <h2>Logo</h2>
    <?php if (setting('logo') !== ''): ?>
      <div class="logo-preview">
        <img src="../uploads/<?= e(setting('logo')) ?>" alt="Current logo" id="logo-img" style="height:<?= (int)setting('logo_height', '40') ?>px">
      </div>
      <button class="btn btn-small btn-danger" name="action" value="remove_logo" formnovalidate>Remove logo</button>
    <?php endif; ?>
    <label>Upload a logo (PNG with a transparent background looks best)
      <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif"></label>
    <label>Logo height: <output id="lh-out"><?= (int)setting('logo_height', '40') ?></output> px
      <input type="range" name="logo_height" min="16" max="200" value="<?= (int)setting('logo_height', '40') ?>"
             oninput="document.getElementById('lh-out').value=this.value;var i=document.getElementById('logo-img');if(i)i.style.height=this.value+'px'">
    </label>
    <label class="check"><input type="checkbox" name="logo_with_name" <?= setting('logo_with_name') === '1' ? 'checked' : '' ?>> Show the store name next to the logo</label>
    <p class="muted">Without a logo, the store name is shown as text.</p>
  </div>

  <div class="card">
    <h2>Colors</h2>
    <label>Theme
      <select name="theme_mode">
        <?php foreach (['auto' => 'Automatic (light or dark, following the visitor\'s device)', 'light' => 'Always light', 'dark' => 'Always dark'] as $v => $l): ?>
          <option value="<?= $v ?>" <?= setting('theme_mode', 'auto') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Main color (buttons, links) <input type="color" name="color_accent" value="<?= e($color('color_accent')) ?>" class="color"></label>
    <label class="check"><input type="checkbox" name="custom_colors" <?= setting('custom_colors') === '1' ? 'checked' : '' ?>>
      <span>Use my own background and text colors below <small>This replaces the theme setting above with your colors.</small></span></label>
    <div class="color-row">
      <label>Page background <input type="color" name="color_bg" value="<?= e($color('color_bg')) ?>" class="color"></label>
      <label>Cards and header <input type="color" name="color_surface" value="<?= e($color('color_surface')) ?>" class="color"></label>
      <label>Text <input type="color" name="color_text" value="<?= e($color('color_text')) ?>" class="color"></label>
    </div>
    <button class="btn btn-small btn-light" name="action" value="reset_colors" formnovalidate>Reset colors</button>
  </div>

  <div class="card">
    <h2>Header menu</h2>
    <label class="check"><input type="checkbox" name="categories_in_header" <?= setting('categories_in_header', '1') === '1' ? 'checked' : '' ?>>
      Show categories in the header (choose which ones in <a href="categories.php">Categories</a>)</label>
    <label class="check"><input type="checkbox" name="contact_in_header" <?= setting('contact_in_header', '1') === '1' ? 'checked' : '' ?>> Show "Contact" in the header</label>
    <p class="muted">Pages can be added to the header from <a href="pages.php">Pages</a>.</p>
  </div>

  <div class="card">
    <h2>Home page</h2>
    <label class="check"><input type="checkbox" name="hero_show" <?= setting('hero_show', '1') === '1' ? 'checked' : '' ?>> Show the welcome title at the top</label>
    <label>Welcome title <input name="hero_title" value="<?= e(setting('hero_title')) ?>" placeholder="<?= e(setting('store_name')) ?>"></label>
    <label>Welcome text <input name="store_tagline" value="<?= e(setting('store_tagline', 'Free digital downloads. Pick one and unlock it in minutes.')) ?>"></label>
    <label>Featured section title <input name="featured_title" value="<?= e(setting('featured_title', 'Featured Items')) ?>"></label>
    <label>Featured products to show <input type="number" min="1" max="12" name="featured_count" value="<?= (int)setting('featured_count', '3') ?>" class="short">
      <small>Tick "Featured" on a product (or the ☆ in the Products list) to put it here.</small></label>
    <label>Products per category section <input type="number" min="1" max="24" name="home_cat_items" value="<?= (int)setting('home_cat_items', '4') ?>" class="short"></label>
    <label>Latest section title <input name="latest_title" value="<?= e(setting('latest_title', 'Latest Items')) ?>"></label>
    <label>Products per page in Latest and category pages <input type="number" min="1" max="60" name="per_page" value="<?= (int)setting('per_page', '12') ?>" class="short"></label>
    <p class="muted">Order on the home page: Welcome → Featured Items → one section per category → Latest Items.</p>
  </div>

  <div class="card">
    <h2>Footer</h2>
    <label>Footer text <input name="footer_text" value="<?= e(setting('footer_text')) ?>" placeholder="© <?= date('Y') ?> <?= e(setting('store_name')) ?>"></label>
  </div>

  <div class="card">
    <h2>Custom CSS (advanced)</h2>
    <label>Extra CSS added to every store page
      <textarea name="custom_css" rows="6" class="mono" placeholder=".product-card { border-radius: 0; }"><?= e(setting('custom_css')) ?></textarea></label>
  </div>

  <button class="btn" name="action" value="save">Save appearance</button>
  <a href="../" target="_blank">View store ↗</a>
</form>
<?php admin_footer();
