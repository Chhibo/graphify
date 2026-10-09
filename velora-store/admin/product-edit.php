<?php
require __DIR__ . '/includes/auth.php';
require_admin();

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
    ];
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
        $data['slug'] = unique_slug('products', $data['name'], (int) $p['id']);
        if ($p['id']) {
            db_update('products', (int) $p['id'], $data);
            flash('success', 'Product saved.');
            redirect('admin/product-edit.php?id=' . (int) $p['id']);
        }
        $data['created_at'] = now();
        $newId = db_insert('products', $data);
        flash('success', 'Product created.');
        redirect('admin/product-edit.php?id=' . $newId);
    }
    $p = array_merge($p, $data, ['image' => $data['image'] ?? $p['image']]);
}

$adminTitle = $p['id'] ? 'Edit product' : 'Add product';
include __DIR__ . '/includes/header.php';
?>
<p><a href="products.php">← All products</a><?php if ($p['id']): ?> · <a href="<?= e(product_url($p)) ?>" target="_blank">View in store</a><?php endif; ?></p>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="grid-main">
  <?= csrf_field() ?>
  <div>
    <div class="card">
      <label>Product name *<input name="name" value="<?= e($p['name']) ?>" required></label>
      <label>Description<textarea name="description" rows="6"><?= e($p['description']) ?></textarea></label>
      <div class="grid-3">
        <label>Price *<input name="price" type="number" step="0.01" min="0" value="<?= e($p['price']) ?>" required></label>
        <label>Old price <small class="muted">(for discount)</small><input name="old_price" type="number" step="0.01" min="0" value="<?= (float) $p['old_price'] > 0 ? e($p['old_price']) : '' ?>"></label>
        <label>Stock <small class="muted">(empty = unlimited)</small><input name="stock" type="number" min="0" value="<?= (int) $p['stock'] < 0 ? '' : (int) $p['stock'] ?>"></label>
      </div>
      <div class="grid-2">
        <label>Sizes <small class="muted">(comma separated)</small><input name="sizes" value="<?= e($p['sizes']) ?>" placeholder="S,M,L,XL"></label>
        <label>Colors <small class="muted">(comma separated)</small><input name="colors" value="<?= e($p['colors']) ?>" placeholder="Black,White"></label>
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
          <?php foreach (q_all('SELECT id, name FROM categories ORDER BY sort_order, name') as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) $p['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Brand<input name="brand" value="<?= e($p['brand']) ?>" list="brands"></label>
      <datalist id="brands"><?php foreach (brands() as $b): ?><option value="<?= e($b) ?>"><?php endforeach; ?></datalist>
      <h3 class="sub">Show on home page</h3>
      <label class="inline"><input type="checkbox" name="is_new" value="1" <?= $p['is_new'] ? 'checked' : '' ?>> New Arrivals</label>
      <label class="inline"><input type="checkbox" name="is_trending" value="1" <?= $p['is_trending'] ? 'checked' : '' ?>> Trending Now</label>
      <label class="inline"><input type="checkbox" name="is_flash" value="1" <?= $p['is_flash'] ? 'checked' : '' ?>> Flash Sale</label>
      <div class="grid-2">
        <label>Rating (0-5)<input name="rating" type="number" step="0.5" min="0" max="5" value="<?= e($p['rating']) ?>"></label>
        <label>Reviews<input name="reviews_count" type="number" min="0" value="<?= (int) $p['reviews_count'] ?>"></label>
      </div>
      <label>Sort order <small class="muted">(lower shows first)</small><input name="sort_order" type="number" value="<?= (int) $p['sort_order'] ?>"></label>
      <button class="btn btn-primary btn-block" type="submit">Save product</button>
    </div>
  </div>
</form>
<?php include __DIR__ . '/includes/footer.php'; ?>
