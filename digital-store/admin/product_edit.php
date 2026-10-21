<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin_layout.php';
require_admin();

const GALLERY_MAX = 8;

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$p = ['id' => 0, 'title' => '', 'slug' => '', 'description' => '', 'image' => '', 'file_name' => '', 'file_label' => '',
      'file_url' => '', 'active' => 1, 'category_id' => null, 'featured' => 0, 'badge' => '', 'views' => 0, 'downloads' => 0,
      'gallery' => '', 'video_url' => '', 'file_size' => '', 'file_format' => '', 'version' => '', 'requirements' => '',
      'publish_at' => 0, 'likes' => 0];
if ($id) {
    $q = db()->prepare('SELECT * FROM products WHERE id = ?');
    $q->execute([$id]);
    $p = $q->fetch();
    if (!$p) {
        redirect('admin/products.php');
    }
}
$gallery = array_values(array_filter((array)json_decode($p['gallery'] ?: '[]', true), 'is_string'));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $p['title'] = trim((string)($_POST['title'] ?? ''));
    $p['description'] = trim((string)($_POST['description'] ?? ''));
    $p['file_url'] = trim((string)($_POST['file_url'] ?? ''));
    $p['featured'] = isset($_POST['featured']) ? 1 : 0;
    $p['category_id'] = (int)($_POST['category_id'] ?? 0) ?: null;
    $p['badge'] = mb_substr(trim((string)($_POST['badge'] ?? '')), 0, 20);
    $p['video_url'] = trim((string)($_POST['video_url'] ?? ''));
    foreach (['file_size', 'file_format', 'version'] as $k) {
        $p[$k] = mb_substr(trim((string)($_POST[$k] ?? '')), 0, 60);
    }
    $p['requirements'] = mb_substr(trim((string)($_POST['requirements'] ?? '')), 0, 300);

    // Visible now, hidden, or scheduled for later.
    $status = (string)($_POST['status'] ?? 'visible');
    $p['active'] = $status === 'visible' ? 1 : 0;
    $p['publish_at'] = 0;
    if ($status === 'scheduled') {
        $when = strtotime((string)($_POST['publish_at'] ?? ''));
        if (!$when || $when <= time()) {
            $errors[] = 'Pick a publish date and time in the future.';
        } else {
            $p['publish_at'] = $when;
        }
    }

    if ($p['title'] === '') {
        $errors[] = 'Title is required.';
    }
    if ($p['file_url'] !== '' && !preg_match('#^https?://#i', $p['file_url'])) {
        $errors[] = 'The file link must start with http:// or https://';
    }
    if ($p['video_url'] !== '' && youtube_id($p['video_url']) === '') {
        $errors[] = 'The video link must be a YouTube link (youtube.com/watch?v=… or youtu.be/…).';
    }
    $slugInput = trim((string)($_POST['slug'] ?? ''));
    $p['slug'] = unique_slug('products', slugify($slugInput !== '' ? $slugInput : $p['title']), (int)$p['id']);

    if (!$errors) {
        $newImage = save_uploaded_image($_FILES['image'] ?? null, $errors);
        if ($newImage) {
            if ($p['image']) @unlink(UPLOADS_DIR . '/' . basename($p['image']));
            $p['image'] = $newImage;
        }
    }

    // Gallery: remove ticked pictures, then add new uploads.
    $remove = array_map('strval', (array)($_POST['remove_gallery'] ?? []));
    foreach ($gallery as $i => $g) {
        if (in_array($g, $remove, true)) {
            @unlink(UPLOADS_DIR . '/' . basename($g));
            unset($gallery[$i]);
        }
    }
    $gallery = array_values($gallery);
    if (!$errors && !empty($_FILES['gallery']['name'][0])) {
        foreach (array_keys($_FILES['gallery']['name']) as $i) {
            if (count($gallery) >= GALLERY_MAX) {
                $errors[] = 'A product can have up to ' . GALLERY_MAX . ' extra pictures.';
                break;
            }
            $one = ['name' => $_FILES['gallery']['name'][$i], 'tmp_name' => $_FILES['gallery']['tmp_name'][$i], 'error' => $_FILES['gallery']['error'][$i]];
            if ($name = save_uploaded_image($one, $errors)) {
                $gallery[] = $name;
            }
        }
    }
    $p['gallery'] = $gallery ? json_encode($gallery) : '';

    $file = $_FILES['file'] ?? null;
    if (!$errors && $file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($err = upload_error($file)) {
            $errors[] = $err;
        } else {
            // Stored under a random name with no extension so it can never run as a script.
            $name = random_token(16);
            if (move_uploaded_file($file['tmp_name'], FILES_DIR . '/' . $name)) {
                if ($p['file_name']) @unlink(FILES_DIR . '/' . basename($p['file_name']));
                $p['file_name'] = $name;
                $p['file_label'] = basename($file['name']);
                $p['file_size'] = human_size((int)filesize(FILES_DIR . '/' . $name));
                if ($p['file_format'] === '' && ($ext = pathinfo($file['name'], PATHINFO_EXTENSION))) {
                    $p['file_format'] = strtoupper($ext);
                }
            } else {
                $errors[] = 'Could not save the file. Check that data/files/ is writable.';
            }
        }
    }

    if (!$errors && $p['file_name'] === '' && $p['file_url'] === '') {
        $errors[] = 'Upload a file or paste a file link, so visitors have something to download.';
    }

    if (!$errors) {
        $cols = ['title', 'slug', 'description', 'image', 'file_name', 'file_label', 'file_url', 'active', 'category_id', 'featured',
                 'badge', 'gallery', 'video_url', 'file_size', 'file_format', 'version', 'requirements', 'publish_at'];
        $vals = array_map(fn($c) => $p[$c], $cols);
        if ($p['id']) {
            db()->prepare('UPDATE products SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?')
                ->execute(array_merge($vals, [$p['id']]));
        } else {
            db()->prepare('INSERT INTO products (' . implode(', ', $cols) . ', created_at) VALUES (' . str_repeat('?, ', count($cols)) . '?)')
                ->execute(array_merge($vals, [time()]));
        }
        flash($p['publish_at'] ? 'Product scheduled for ' . date('M j, Y H:i', $p['publish_at']) . '.' : 'Product saved.');
        redirect('admin/products.php');
    }
}

$categories = db()->query('SELECT id, name FROM categories ORDER BY sort_order, name')->fetchAll();
$featuredCount = (int)db()->query('SELECT COUNT(*) FROM products WHERE featured = 1 AND active = 1')->fetchColumn();
$status = $p['publish_at'] ? 'scheduled' : ($p['active'] ? 'visible' : 'hidden');

admin_header($p['id'] ? 'Edit product' : 'Add product',
    $p['id'] ? number_format((int)$p['views']) . ' views · ' . number_format((int)$p['downloads']) . ' downloads · ' . number_format((int)$p['likes']) . ' likes' : 'Fill in the details and upload the file visitors will get',
    $p['id'] ? '<a class="btn btn-light" href="' . e(url_product($p)) . '" target="_blank">' . icon('eye', 16) . ' Preview</a>' : '');
?>
<?php foreach ($errors as $err): ?><?= notice(e($err), '', '', 'bad', 'x') ?><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
  <div class="grid-2">
    <div>
      <div class="card">
        <h2>Details</h2>
        <p class="card-sub">What visitors see on the product page.</p>
        <label>Title <input name="title" required maxlength="150" value="<?= e($p['title']) ?>" placeholder="e.g. 100 Instagram Post Templates"></label>
        <label>Description
          <textarea name="description" rows="10" placeholder="What's inside, who it's for, file format…"><?= e($p['description']) ?></textarea>
          <small>Plain text (an empty line starts a new paragraph) or HTML such as &lt;b&gt;, &lt;ul&gt;&lt;li&gt;.</small></label>
        <label>Web address <input name="slug" maxlength="120" value="<?= e($p['slug']) ?>" placeholder="made from the title if empty">
          <small>Used in readable links: <?= e(base_url('product/')) ?><b><?= e($p['slug'] !== '' ? $p['slug'] : 'your-product') ?></b></small></label>
      </div>

      <div class="card">
        <h2>Download file</h2>
        <p class="card-sub">The file visitors get after completing an offer. It is never shown publicly.</p>
        <label>Upload a file <span class="muted">(max <?= e(ini_get('upload_max_filesize')) ?> on your hosting)</span>
          <?php if ($p['file_label']): ?><small><?= icon('file', 14) ?> Current file: <b><?= e($p['file_label']) ?></b></small><?php endif; ?>
          <input type="file" name="file">
        </label>
        <label>…or a file link <input name="file_url" placeholder="https://drive.google.com/…" value="<?= e($p['file_url']) ?>">
          <small>Google Drive, Dropbox, MediaFire… Use this for big files. When filled, it is used instead of the upload.</small>
        </label>
        <div class="form-row">
          <label>File size <input name="file_size" value="<?= e($p['file_size']) ?>" placeholder="filled in automatically on upload"></label>
          <label>Format <input name="file_format" value="<?= e($p['file_format']) ?>" placeholder="e.g. PDF, ZIP, Canva"></label>
          <label>Version <input name="version" value="<?= e($p['version']) ?>" placeholder="e.g. 2.1"></label>
          <label>Works with <input name="requirements" value="<?= e($p['requirements']) ?>" placeholder="e.g. Lightroom Mobile, Photoshop CC"></label>
        </div>
        <small>These details show in a small table on the product page. Leave any of them empty to hide it.</small>
      </div>

      <div class="card">
        <h2>Gallery &amp; video</h2>
        <p class="card-sub">Extra pictures and a YouTube preview make people more likely to download.</p>
        <?php if ($gallery): ?>
          <div class="gallery-edit">
            <?php foreach ($gallery as $g): ?>
              <label class="gallery-item"><img src="../uploads/<?= e($g) ?>" alt=""><span><input type="checkbox" name="remove_gallery[]" value="<?= e($g) ?>"> Remove</span></label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <label>Add pictures (up to <?= GALLERY_MAX ?>) <input type="file" name="gallery[]" multiple accept="image/png,image/jpeg,image/webp,image/gif"></label>
        <label>YouTube video (optional) <input name="video_url" value="<?= e($p['video_url']) ?>" placeholder="https://www.youtube.com/watch?v=…"></label>
      </div>
    </div>

    <div>
      <div class="card">
        <h2>Publishing</h2>
        <label class="check"><input type="radio" name="status" value="visible" <?= $status === 'visible' ? 'checked' : '' ?>> Visible in the store</label>
        <label class="check"><input type="radio" name="status" value="hidden" <?= $status === 'hidden' ? 'checked' : '' ?>> Hidden</label>
        <label class="check"><input type="radio" name="status" value="scheduled" <?= $status === 'scheduled' ? 'checked' : '' ?>> Publish later</label>
        <label id="publish-at" <?= $status === 'scheduled' ? '' : 'hidden' ?>>Publish on
          <input type="datetime-local" name="publish_at" value="<?= $p['publish_at'] ? e(date('Y-m-d\TH:i', (int)$p['publish_at'])) : '' ?>">
          <small>Your server time is <?= e(date('M j, H:i')) ?>.</small></label>
        <label class="switch"><input type="checkbox" name="featured" <?= $p['featured'] ? 'checked' : '' ?>><span></span> ★ Featured on home page</label>
        <small><?= $featuredCount ?> featured now · the newest <?= (int)setting('featured_count', '3') ?> are shown</small>
        <br>
        <label>Category
          <select name="category_id">
            <option value="">No category</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (int)$p['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <small><a href="categories.php">Manage categories</a></small>
        </label>
        <label>Badge (optional) <input name="badge" maxlength="20" list="badge-ideas" value="<?= e($p['badge']) ?>" placeholder="e.g. NEW, HOT, BESTSELLER">
          <datalist id="badge-ideas"><option value="NEW"><option value="HOT"><option value="POPULAR"><option value="BESTSELLER"><option value="UPDATED"><option value="LIMITED"></datalist>
          <small>Shown as a colored label on the product image.</small></label>
      </div>
      <div class="card">
        <h2>Cover image</h2>
        <p class="card-sub">JPG, PNG or WEBP. 4:3 or 16:10 looks best.</p>
        <div class="img-drop">
          <?php if ($p['image']): ?><img id="img-preview" src="../uploads/<?= e($p['image']) ?>" alt="">
          <?php else: ?><img id="img-preview" alt="" hidden><div class="ph" id="img-ph"><?= icon('package', 32) ?> No image yet</div><?php endif; ?>
          <input type="file" name="image" accept="image/png,image/jpeg,image/webp,image/gif" id="img-input">
        </div>
      </div>
    </div>
  </div>
  <div class="form-actions">
    <button class="btn btn-grad"><?= icon('save', 16) ?> Save product</button>
    <a class="btn btn-light" href="products.php">Cancel</a>
  </div>
</form>
<script>
document.getElementById('img-input').addEventListener('change', function () {
  var f = this.files[0], img = document.getElementById('img-preview'), ph = document.getElementById('img-ph');
  if (!f) return;
  img.src = URL.createObjectURL(f); img.hidden = false; if (ph) ph.hidden = true;
});
document.querySelectorAll('input[name=status]').forEach(function (r) {
  r.addEventListener('change', function () { document.getElementById('publish-at').hidden = r.value !== 'scheduled' || !r.checked; });
});
</script>
<?php admin_footer();
