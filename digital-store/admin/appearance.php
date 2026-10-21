<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/layout.php';
require dirname(__DIR__) . '/inc/admin_layout.php';
require_admin();

$defaults = [
    'color_accent' => '#5b4cf0',
    'color_accent2' => '#c03dd8',
    'color_bg' => '#f5f6fb',
    'color_surface' => '#ffffff',
    'color_text' => '#171a2b',
];
// One-click color themes: [name, main, second].
$presets = [
    ['Violet', '#5b4cf0', '#c03dd8'], ['Ocean', '#0e7bd8', '#14b8a6'], ['Emerald', '#0f9f6e', '#7cc242'],
    ['Sunset', '#f05a28', '#e8336d'], ['Rose', '#e11d74', '#f59e0b'], ['Midnight', '#334155', '#6366f1'],
    ['Gold', '#c47f00', '#e8b10a'], ['Crimson', '#c81e3a', '#7c3aed'],
];
$images = ['logo' => 'Logo', 'favicon' => 'Favicon', 'hero_image' => 'Hero background'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string)($_POST['action'] ?? 'save');

    if (strpos($action, 'remove_') === 0 && isset($images[substr($action, 7)])) {
        $key = substr($action, 7);
        if (setting($key) !== '') {
            @unlink(UPLOADS_DIR . '/' . basename(setting($key)));
        }
        save_setting($key, '');
        flash($images[$key] . ' removed.');
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

    foreach (array_keys($images) as $key) {
        $new = save_uploaded_image($_FILES[$key] ?? null, $errors);
        if ($new) {
            if (setting($key) !== '') {
                @unlink(UPLOADS_DIR . '/' . basename(setting($key)));
            }
            save_setting($key, $new);
        }
    }
    save_setting('logo_height', (string)max(16, min(200, (int)($_POST['logo_height'] ?? 40))));
    save_setting('corner_radius', (string)max(0, min(28, (int)($_POST['corner_radius'] ?? 16))));

    $mode = (string)($_POST['theme_mode'] ?? 'auto');
    save_setting('theme_mode', in_array($mode, ['auto', 'light', 'dark'], true) ? $mode : 'auto');
    $font = (string)($_POST['font'] ?? '');
    save_setting('font', isset(font_choices()[$font]) ? $font : '');
    foreach (array_keys($defaults) as $k) {
        $c = strtolower(trim((string)($_POST[$k] ?? '')));
        // Keep "default" as empty so the theme's own light/dark colors still apply.
        save_setting($k, valid_color($c) && $c !== $defaults[$k] ? $c : '');
    }
    foreach (['logo_with_name', 'custom_colors', 'categories_in_header', 'contact_in_header', 'hero_show', 'show_chips',
              'show_steps', 'show_downloads', 'announce_show'] as $k) {
        save_setting($k, isset($_POST[$k]) ? '1' : '0');
    }
    foreach (['hero_title', 'hero_kicker', 'store_tagline', 'featured_title', 'latest_title', 'footer_text',
              'announce_text', 'announce_url', 'announce_link_text'] as $k) {
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
$on = fn(string $k, string $def = '1') => setting($k, $def) === '1' ? 'checked' : '';

admin_header('Appearance', 'Make the store look like your brand',
    '<a class="btn btn-light" href="../" target="_blank">' . icon('eye', 16) . ' Preview store</a>');
?>
<?php foreach ($errors as $err): ?><?= notice(e($err), '', '', 'bad', 'x') ?><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="form">
  <?= csrf_field() ?>
  <div class="grid-2-even">
    <div>
      <div class="card">
        <h2>Logo</h2>
        <p class="card-sub">PNG with a transparent background looks best. Without a logo, the store name is shown.</p>
        <?php if (setting('logo') !== ''): ?>
          <div class="logo-preview">
            <img src="../uploads/<?= e(setting('logo')) ?>" alt="Current logo" id="logo-img" style="height:<?= (int)setting('logo_height', '40') ?>px">
          </div>
          <button class="btn btn-small btn-danger" name="action" value="remove_logo" formnovalidate><?= icon('trash', 14) ?> Remove logo</button>
        <?php endif; ?>
        <label>Upload a logo <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif"></label>
        <label>Logo height: <output id="lh-out"><?= (int)setting('logo_height', '40') ?></output> px
          <input type="range" name="logo_height" min="16" max="200" value="<?= (int)setting('logo_height', '40') ?>"
                 oninput="document.getElementById('lh-out').value=this.value;var i=document.getElementById('logo-img');if(i)i.style.height=this.value+'px'">
        </label>
        <label class="check"><input type="checkbox" name="logo_with_name" <?= $on('logo_with_name', '0') ?>> Show the store name next to the logo</label>
        <label>Favicon (the small icon in the browser tab)
          <?php if (setting('favicon') !== ''): ?><img src="../uploads/<?= e(setting('favicon')) ?>" alt="" style="width:32px;height:32px;display:block;margin:6px 0;border-radius:6px"><?php endif; ?>
          <input type="file" name="favicon" accept="image/png,image/gif,image/webp">
          <small>A square PNG, at least 64×64.</small></label>
        <?php if (setting('favicon') !== ''): ?><button class="btn btn-small btn-danger" name="action" value="remove_favicon" formnovalidate>Remove favicon</button><?php endif; ?>
      </div>

      <div class="card">
        <h2>Colors &amp; style</h2>
        <p class="card-sub">Pick a ready-made theme or your own colors.</p>
        <div class="presets">
          <?php foreach ($presets as [$name, $a, $b]): ?>
            <button type="button" class="preset" title="<?= e($name) ?>" style="background:linear-gradient(135deg,<?= $a ?>,<?= $b ?>)"
                    onclick="document.getElementById('c1').value='<?= $a ?>';document.getElementById('c2').value='<?= $b ?>'"></button>
          <?php endforeach; ?>
        </div>
        <div class="color-row">
          <label>Main color <input type="color" id="c1" name="color_accent" value="<?= e($color('color_accent')) ?>" class="color"></label>
          <label>Second color <input type="color" id="c2" name="color_accent2" value="<?= e($color('color_accent2')) ?>" class="color"><small>Used in gradients</small></label>
        </div>
        <label>Theme
          <select name="theme_mode">
            <?php foreach (['auto' => 'Automatic: follows the visitor\'s device, with a light/dark button', 'light' => 'Always light', 'dark' => 'Always dark'] as $v => $l): ?>
              <option value="<?= $v ?>" <?= setting('theme_mode', 'auto') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Font
          <select name="font">
            <?php foreach (font_choices() as $k => [$label]): ?>
              <option value="<?= e($k) ?>" <?= setting('font') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <small>Fonts other than "System default" load from Google Fonts.</small></label>
        <label>Corner roundness: <output id="cr-out"><?= (int)setting('corner_radius', '16') ?></output> px
          <input type="range" name="corner_radius" min="0" max="28" value="<?= (int)setting('corner_radius', '16') ?>" oninput="document.getElementById('cr-out').value=this.value"></label>
        <label class="check"><input type="checkbox" name="custom_colors" <?= $on('custom_colors', '0') ?>>
          <span>Use my own background and text colors <small>This turns off dark mode and uses the colors below.</small></span></label>
        <div class="color-row">
          <label>Background <input type="color" name="color_bg" value="<?= e($color('color_bg')) ?>" class="color"></label>
          <label>Cards &amp; header <input type="color" name="color_surface" value="<?= e($color('color_surface')) ?>" class="color"></label>
          <label>Text <input type="color" name="color_text" value="<?= e($color('color_text')) ?>" class="color"></label>
        </div>
        <button class="btn btn-small btn-light" name="action" value="reset_colors" formnovalidate>Reset colors</button>
      </div>

      <div class="card">
        <h2>Announcement bar</h2>
        <p class="card-sub">A colored strip at the very top of every page.</p>
        <label class="switch"><input type="checkbox" name="announce_show" <?= $on('announce_show', '0') ?>><span></span> Show announcement bar</label><br><br>
        <label>Text <input name="announce_text" value="<?= e(setting('announce_text')) ?>" placeholder="🎉 New templates added every week!"></label>
        <div class="form-row">
          <label>Link (optional) <input name="announce_url" value="<?= e(setting('announce_url')) ?>" placeholder="https://"></label>
          <label>Link text <input name="announce_link_text" value="<?= e(setting('announce_link_text')) ?>" placeholder="Learn more"></label>
        </div>
      </div>
    </div>

    <div>
      <div class="card">
        <h2>Home page</h2>
        <p class="card-sub">Order: Welcome banner → categories → Featured → How it works → category sections → Latest.</p>
        <label class="switch"><input type="checkbox" name="hero_show" <?= $on('hero_show') ?>><span></span> Welcome banner with search</label><br><br>
        <label>Small label above the title <input name="hero_kicker" value="<?= e(setting('hero_kicker')) ?>" placeholder="100% free downloads"></label>
        <label>Title <input name="hero_title" value="<?= e(setting('hero_title')) ?>" placeholder="<?= e(setting('store_name')) ?>"></label>
        <label>Text <input name="store_tagline" value="<?= e(setting('store_tagline')) ?>" placeholder="Free digital downloads. Pick one and unlock it in minutes."></label>
        <label>Background image (optional, replaces the color gradient)
          <?php if (setting('hero_image') !== ''): ?><img class="preview" src="../uploads/<?= e(setting('hero_image')) ?>" alt="" style="max-height:120px"><?php endif; ?>
          <input type="file" name="hero_image" accept="image/png,image/jpeg,image/webp"></label>
        <?php if (setting('hero_image') !== ''): ?><button class="btn btn-small btn-danger" name="action" value="remove_hero_image" formnovalidate>Remove background image</button><br><br><?php endif; ?>
        <label class="switch"><input type="checkbox" name="show_chips" <?= $on('show_chips') ?>><span></span> Category buttons under the banner</label><br><br>
        <label class="switch"><input type="checkbox" name="show_steps" <?= $on('show_steps') ?>><span></span> "How it works" steps</label><br><br>
        <label class="switch"><input type="checkbox" name="show_downloads" <?= $on('show_downloads') ?>><span></span> Show download counts</label><br><br>
        <div class="form-row">
          <label>Featured title <input name="featured_title" value="<?= e(setting('featured_title')) ?>" placeholder="Featured Items"></label>
          <label>Featured products <input type="number" min="1" max="12" name="featured_count" value="<?= (int)setting('featured_count', '3') ?>"></label>
          <label>Latest title <input name="latest_title" value="<?= e(setting('latest_title')) ?>" placeholder="Latest Items"></label>
          <label>Products per page <input type="number" min="1" max="60" name="per_page" value="<?= (int)setting('per_page', '12') ?>"></label>
          <label>Per category section <input type="number" min="1" max="24" name="home_cat_items" value="<?= (int)setting('home_cat_items', '4') ?>"></label>
        </div>
      </div>

      <div class="card">
        <h2>Header &amp; footer</h2>
        <label class="switch"><input type="checkbox" name="categories_in_header" <?= $on('categories_in_header') ?>><span></span> Categories in the header (choose them in <a href="categories.php">Categories</a>)</label><br><br>
        <label class="switch"><input type="checkbox" name="contact_in_header" <?= $on('contact_in_header') ?>><span></span> "Contact" in the header</label><br><br>
        <label>Copyright line <input name="footer_text" value="<?= e(setting('footer_text')) ?>" placeholder="© <?= date('Y') ?> <?= e(setting('store_name')) ?>. All rights reserved."></label>
        <small>The footer about text and social links are in <a href="settings.php">Settings</a>.</small>
      </div>

      <div class="card">
        <h2>Custom CSS</h2>
        <p class="card-sub">For advanced tweaks. Added to every store page.</p>
        <textarea name="custom_css" rows="6" class="mono" placeholder=".product-card { border-radius: 0; }"><?= e(setting('custom_css')) ?></textarea>
      </div>
    </div>
  </div>
  <div class="form-actions">
    <button class="btn btn-grad" name="action" value="save"><?= icon('save', 16) ?> Save appearance</button>
    <a class="btn btn-light" href="../" target="_blank"><?= icon('eye', 16) ?> Preview store</a>
  </div>
</form>
<?php admin_footer();
