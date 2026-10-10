<?php
require __DIR__ . '/includes/auth.php';
require_admin('products');

$id = (int) ($_GET['id'] ?? 0);
$p = $id ? q_one('SELECT * FROM products WHERE id = ?', [$id]) : null;
if ($id && !$p) {
    flash('error', 'Product not found.');
    redirect('admin/products.php');
}
$p = $p ?? [
    'id' => 0, 'category_id' => null, 'name' => '', 'brand' => '', 'description' => '', 'price' => '', 'old_price' => '',
    'image' => '', 'gallery' => '[]', 'sizes' => 'S,M,L,XL', 'colors' => '', 'rating' => '5.0', 'reviews_count' => 0,
    'stock' => -1, 'is_new' => 1, 'is_trending' => 0, 'is_flash' => 0, 'active' => 1, 'sort_order' => 0,
    'short_description' => '', 'extra_options' => '[]', 'additional_info' => '[]', 'shipping_enabled' => 1, 'shipping_methods' => '',
];
$gallery = json_decode((string) $p['gallery'], true) ?: [];
$errors = [];

if (is_post()) {
    verify_csrf();
    $data = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'brand' => trim((string) ($_POST['brand'] ?? '')),
        'category_id' => (int) ($_POST['category_id'] ?? 0) ?: null,
        'description' => trim((string) ($_POST['description'] ?? '')),
        'short_description' => trim((string) ($_POST['short_description'] ?? '')),
        'price' => round((float) ($_POST['price'] ?? 0), 2),
        'old_price' => round((float) ($_POST['old_price'] ?? 0), 2),
        'sizes' => implode(',', str_list($_POST['sizes'] ?? '')),
        'colors' => implode(',', str_list($_POST['colors'] ?? '')),
        'rating' => max(0, min(5, round((float) ($_POST['rating'] ?? 5), 1))),
        'reviews_count' => max(0, (int) ($_POST['reviews_count'] ?? 0)),
        'stock' => trim((string) ($_POST['stock'] ?? '')) === '' ? -1 : max(0, (int) $_POST['stock']),
        'is_new' => post_flag('is_new'),
        'is_trending' => post_flag('is_trending'),
        'is_flash' => post_flag('is_flash'),
        'active' => post_flag('active'),
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        'shipping_enabled' => post_flag('shipping_enabled'),
        'variant_stock' => empty($p['printful_id']) ? post_flag('variant_stock') : 0,
        'meta_title' => mb_substr(trim((string) ($_POST['meta_title'] ?? '')), 0, 200),
        'meta_description' => mb_substr(trim((string) ($_POST['meta_description'] ?? '')), 0, 300),
        'shipping_methods' => implode(',', array_map('intval', (array) ($_POST['shipping_methods'] ?? []))),
    ];
    // Custom options: one row per option, values like "Cotton, Silk +5"
    $opts = [];
    foreach ((array) ($_POST['opt_name'] ?? []) as $i => $name) {
        $name = mb_substr(trim((string) $name), 0, 60);
        $values = parse_option_values((string) ($_POST['opt_values'][$i] ?? ''));
        if ($name !== '' && $values) {
            $opts[] = ['name' => $name, 'values' => $values];
        }
    }
    $data['extra_options'] = json_encode($opts);
    // Additional information rows (shown in the "Additional Information" tab)
    $info = [];
    foreach ((array) ($_POST['info_key'] ?? []) as $i => $k) {
        $k = mb_substr(trim((string) $k), 0, 80);
        if ($k !== '') {
            $info[] = [$k, mb_substr(trim((string) ($_POST['info_val'][$i] ?? '')), 0, 255)];
        }
    }
    $data['additional_info'] = json_encode($info);
    if ($data['name'] === '') {
        $errors[] = 'Product name is required.';
    }
    if ($data['price'] <= 0) {
        $errors[] = 'Price must be greater than 0.';
    }
    try {
        $main = upload_image($_FILES['image'] ?? null);
        if ($main) {
            $data['image'] = $main;
        } elseif (preg_match('#^https?://#i', trim((string) ($_POST['image_url'] ?? '')))) {
            $data['image'] = trim((string) $_POST['image_url']);
        }
        // Keep the gallery images that were not removed, then add new uploads.
        $keep = array_values(array_intersect($gallery, (array) ($_POST['keep_gallery'] ?? [])));
        foreach (files_list($_FILES['gallery'] ?? null) as $f) {
            $up = upload_image($f);
            if ($up) {
                $keep[] = $up;
            }
        }
        $data['gallery'] = json_encode(array_slice($keep, 0, 8));
    } catch (RuntimeException $ex) {
        $errors[] = $ex->getMessage();
    }

    if (!$errors) {
        $slugIn = trim((string) ($_POST['slug'] ?? ''));
        $data['slug'] = unique_slug('products', $slugIn !== '' ? $slugIn : $data['name'], (int) $p['id']);
        if ($p['id']) {
            db_update('products', (int) $p['id'], $data);
            $savedId = (int) $p['id'];
        } else {
            $data['created_at'] = now();
            $savedId = db_insert('products', $data);
        }
        if (empty($p['printful_id'])) {
            save_variant_stock($savedId, (bool) $data['variant_stock'], str_list($data['sizes']), str_list($data['colors']));
        }
        flash('success', $p['id'] ? 'Product saved.' : 'Product created.');
        redirect('admin/product-edit.php?id=' . $savedId);
    }
    $p = array_merge($p, $data, ['image' => $data['image'] ?? $p['image']]);
}

$adminTitle = $p['id'] ? 'Edit product' : 'Add product';
include __DIR__ . '/includes/header.php';
?>
<p><a href="products.php">← All products</a><?php if ($p['id']): ?> · <a href="<?= e(product_url($p)) ?>" target="_blank">View in store</a><?php endif; ?></p>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
<?php if (!empty($p['printful_id'])): ?>
  <div class="alert alert-info"><b>Printful product.</b> Name, images, sizes, colors and prices come from Printful and are refreshed on every sync (<?= count(product_variants((int) $p['id'])) ?> size/color options). Change them in Printful, then click <a href="printful.php">Sync products</a>. Your description, category, home page sections and old price are kept.</div>
<?php endif; ?>
<form method="post" enctype="multipart/form-data" class="grid-main">
  <?= csrf_field() ?>
  <div>
    <div class="card">
      <label>Product name *<input name="name" value="<?= e($p['name']) ?>" required></label>
      <label>Short description <small class="muted">(shown under the product name)</small><textarea name="short_description" rows="4" data-editor="mini"><?= e($p['short_description']) ?></textarea></label>
      <label>Description <small class="muted">(shown in the “Description” tab)</small><textarea name="description" rows="10" data-editor="basic"><?= e($p['description']) ?></textarea></label>
      <div class="grid-3">
        <label>Price *<input name="price" type="number" step="0.01" min="0" value="<?= e($p['price']) ?>" required></label>
        <label>Old price <small class="muted">(for discount)</small><input name="old_price" type="number" step="0.01" min="0" value="<?= (float) $p['old_price'] > 0 ? e($p['old_price']) : '' ?>"></label>
        <label data-hide-when-variant-stock>Stock <small class="muted">(empty = unlimited)</small><input name="stock" type="number" min="0" value="<?= (int) $p['stock'] < 0 ? '' : (int) $p['stock'] ?>"></label>
      </div>
      <div class="grid-2">
        <label>Sizes <small class="muted">(comma separated)</small><input name="sizes" value="<?= e($p['sizes']) ?>" placeholder="S,M,L,XL"></label>
        <label>Colors <small class="muted">(comma separated)</small><input name="colors" value="<?= e($p['colors']) ?>" placeholder="Black,White"></label>
      </div>
      <?php if (empty($p['printful_id'])): ?>
        <label class="inline"><input type="checkbox" name="variant_stock" value="1" id="variant-stock-toggle" <?= (int) ($p['variant_stock'] ?? 0) ? 'checked' : '' ?>> Track stock for each size / color</label>
        <div id="variant-grid" <?= (int) ($p['variant_stock'] ?? 0) ? '' : 'hidden' ?>>
          <p class="help">Stock for each combination (empty = unlimited, 0 = sold out). Price is optional: leave empty to use the product price.</p>
          <?php
            $existing = [];
            foreach (q_all("SELECT size, color, stock, price FROM product_variants WHERE product_id = ? AND printful_variant_id = '' AND active = 1", [(int) $p['id']]) as $v) {
                $existing[$v['size'] . '|' . $v['color']] = ['stock' => (int) $v['stock'], 'price' => (float) $v['price']];
            }
          ?>
          <div class="table-wrap"><table class="vs-table"><thead><tr><th>Size</th><th>Color</th><th>Stock</th><th>Price</th></tr></thead><tbody id="vs-body"></tbody></table></div>
          <script>window.VARIANT_STOCK = <?= json_encode((object) $existing) ?>;</script>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-head"><h2>Custom options</h2><button type="button" class="btn btn-sm btn-light" data-add-row="#opt-rows"><?= icon('plus', 14) ?> Add option</button></div>
      <p class="help">Extra choices besides size and color, e.g. <b>Material</b> → <code>Cotton, Silk +5, Wool +7.50</code>. Write <code>+amount</code> after a value to add to the price.</p>
      <div id="opt-rows" class="rows">
        <?php $optRows = product_extra_options($p) ?: [['name' => '', 'values' => []]]; ?>
        <?php foreach ($optRows as $o): ?>
          <div class="row-item">
            <input name="opt_name[]" value="<?= e($o['name']) ?>" placeholder="Option name (e.g. Material)">
            <input name="opt_values[]" value="<?= e(option_values_text($o['values'])) ?>" placeholder="Values, comma separated (e.g. Cotton, Silk +5)" class="grow">
            <button type="button" class="icon-x" data-remove-row aria-label="Remove">×</button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2>Additional information</h2><button type="button" class="btn btn-sm btn-light" data-add-row="#info-rows"><?= icon('plus', 14) ?> Add row</button></div>
      <p class="help">Shown in the “Additional Information” tab, e.g. Material → 100% Cotton. Sizes, colors and options are added automatically.</p>
      <div id="info-rows" class="rows">
        <?php $infoRows = product_additional_info($p) ?: [['', '']]; ?>
        <?php foreach ($infoRows as [$k, $v]): ?>
          <div class="row-item">
            <input name="info_key[]" value="<?= e($k) ?>" placeholder="Name (e.g. Material)">
            <input name="info_val[]" value="<?= e($v) ?>" placeholder="Value (e.g. 100% Cotton)" class="grow">
            <button type="button" class="icon-x" data-remove-row aria-label="Remove">×</button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h2>Images</h2></div>
      <div class="img-row">
        <img class="preview" src="<?= e(img_url($p['image'])) ?>" alt="">
        <div class="grow">
          <label>Main image<input type="file" name="image" accept="image/*"></label>
          <label>…or image URL<input name="image_url" placeholder="https://..." value=""></label>
        </div>
      </div>
      <label>Extra gallery images <small class="muted">(up to 8)</small><input type="file" name="gallery[]" accept="image/*" multiple></label>
      <?php if ($gallery): ?>
        <div class="gallery-edit">
          <?php foreach ($gallery as $g): ?>
            <label><img src="<?= e(img_url($g)) ?>" alt=""><span><input type="checkbox" name="keep_gallery[]" value="<?= e($g) ?>" checked> keep</span></label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div>
    <div class="card">
      <label class="inline"><input type="checkbox" name="active" value="1" <?= $p['active'] ? 'checked' : '' ?>> Visible in store</label>
      <label>Category
        <select name="category_id">
          <option value="">- None -</option>
          <?php foreach (categories() as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) $p['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= $c['depth'] ? '&nbsp;&nbsp;— ' : '' ?><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Brand<input name="brand" value="<?= e($p['brand']) ?>" list="brands"></label>
      <datalist id="brands"><?php foreach (brands() as $b): ?><option value="<?= e($b) ?>"><?php endforeach; ?></datalist>
      <h3 class="sub">Show on home page</h3>
      <label class="inline"><input type="checkbox" name="is_new" value="1" <?= $p['is_new'] ? 'checked' : '' ?>> New Arrivals</label>
      <label class="inline"><input type="checkbox" name="is_trending" value="1" <?= $p['is_trending'] ? 'checked' : '' ?>> Trending Now</label>
      <label class="inline"><input type="checkbox" name="is_flash" value="1" <?= $p['is_flash'] ? 'checked' : '' ?>> Deal of the Days</label>
      <div class="grid-2">
        <label>Rating (0-5)<input name="rating" type="number" step="0.5" min="0" max="5" value="<?= e($p['rating']) ?>"></label>
        <label>Reviews<input name="reviews_count" type="number" min="0" value="<?= (int) $p['reviews_count'] ?>"></label>
      </div>
      <label>Sort order <small class="muted">(lower shows first)</small><input name="sort_order" type="number" value="<?= (int) $p['sort_order'] ?>"></label>

      <h3 class="sub">SEO (Google)</h3>
      <label>Page address <small class="muted">(e.g. black-hoodie)</small><input name="slug" value="<?= e($p['slug'] ?? '') ?>" placeholder="created from the name"></label>
      <label>SEO title <small class="muted">(optional)</small><input name="meta_title" maxlength="200" value="<?= e($p['meta_title'] ?? '') ?>" placeholder="<?= e($p['name']) ?>"></label>
      <label>SEO description <small class="muted">(optional, ~155 characters)</small><textarea name="meta_description" rows="3" maxlength="300"><?= e($p['meta_description'] ?? '') ?></textarea></label>

      <h3 class="sub">Shipping</h3>
      <label class="inline"><input type="checkbox" name="shipping_enabled" value="1" data-toggle-target="#ship-methods" <?= (int) $p['shipping_enabled'] ? 'checked' : '' ?>> This product needs delivery</label>
      <p class="help">Untick for products that are not shipped (gift cards, services, digital items): no delivery fee is charged for them.</p>
      <div id="ship-methods" <?= (int) $p['shipping_enabled'] ? '' : 'hidden' ?>>
        <?php $allMethods = shipping_methods(false); $chosen = product_shipping_ids($p); ?>
        <?php if ($allMethods): ?>
          <p class="help" style="margin-top:0">Delivery options for this product (none ticked = all options):</p>
          <?php foreach ($allMethods as $m): ?>
            <label class="inline small"><input type="checkbox" name="shipping_methods[]" value="<?= (int) $m['id'] ?>" <?= in_array((int) $m['id'], $chosen, true) ? 'checked' : '' ?>> <?= e($m['name']) ?> (<?= (float) $m['cost'] > 0 ? money($m['cost']) : 'Free' ?>)<?= $m['active'] ? '' : ' - disabled' ?></label>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="help" style="margin-top:0">No delivery options yet. <a href="shipping.php">Add delivery options</a>.</p>
        <?php endif; ?>
      </div>

      <button class="btn btn-primary btn-block" type="submit">Save product</button>
    </div>
  </div>
</form>
<script>
// Stock per size/color grid: rebuilt when sizes or colors change, keeping the values already typed.
(function () {
  var body = document.getElementById('vs-body');
  if (!body) return;
  var toggle = document.getElementById('variant-stock-toggle'), grid = document.getElementById('variant-grid');
  var values = window.VARIANT_STOCK || {};
  var list = function (name) { return document.querySelector('input[name=' + name + ']').value.split(',').map(function (s) { return s.trim(); }).filter(Boolean); };
  var esc = function (s) { return String(s).replace(/[&<>"']/g, function (c) { return '&#' + c.charCodeAt(0) + ';'; }); };
  var build = function () {
    body.querySelectorAll('tr').forEach(function (tr) {
      values[tr.getAttribute('data-key')] = { stock: tr.querySelector('[data-f=stock]').value === '' ? -1 : parseInt(tr.querySelector('[data-f=stock]').value, 10), price: parseFloat(tr.querySelector('[data-f=price]').value) || 0 };
    });
    var sizes = list('sizes'), colors = list('colors'), html = '';
    (sizes.length ? sizes : ['']).forEach(function (s) {
      (colors.length ? colors : ['']).forEach(function (c) {
        var k = s + '|' + c, v = values[k] || { stock: -1, price: 0 };
        html += '<tr data-key="' + esc(k) + '"><td>' + esc(s || '-') + '</td><td>' + esc(c || '-') + '</td>'
          + '<td><input type="hidden" name="vs_key[]" value="' + esc(k) + '"><input data-f="stock" name="vs_stock[]" type="number" min="0" value="' + (v.stock >= 0 ? v.stock : '') + '" placeholder="∞"></td>'
          + '<td><input data-f="price" name="vs_price[]" type="number" step="0.01" min="0" value="' + (v.price > 0 ? v.price : '') + '" placeholder="Default"></td></tr>';
      });
    });
    body.innerHTML = html;
  };
  var sync = function () {
    grid.hidden = !toggle.checked;
    document.querySelectorAll('[data-hide-when-variant-stock]').forEach(function (el) { el.style.opacity = toggle.checked ? .4 : 1; el.title = toggle.checked ? 'Stock is set per size/color below' : ''; });
  };
  ['sizes', 'colors'].forEach(function (n) { document.querySelector('input[name=' + n + ']').addEventListener('input', build); });
  toggle.addEventListener('change', sync);
  build(); sync();
})();
</script>
<?php include __DIR__ . '/includes/editor.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
