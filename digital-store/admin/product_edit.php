<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin_layout.php';
require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$p = ['id' => 0, 'title' => '', 'description' => '', 'image' => '', 'file_name' => '',
      'file_label' => '', 'file_url' => '', 'active' => 1, 'category_id' => null, 'featured' => 0, 'badge' => '', 'views' => 0, 'downloads' => 0];
if ($id) {
    $q = db()->prepare('SELECT * FROM products WHERE id = ?');
    $q->execute([$id]);
    $p = $q->fetch();
    if (!$p) {
        redirect('admin/products.php');
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $p['title'] = trim($_POST['title'] ?? '');
    $p['description'] = trim($_POST['description'] ?? '');
    $p['file_url'] = trim($_POST['file_url'] ?? '');
    $p['active'] = isset($_POST['active']) ? 1 : 0;
    $p['featured'] = isset($_POST['featured']) ? 1 : 0;
    $p['category_id'] = (int)($_POST['category_id'] ?? 0) ?: null;
    $p['badge'] = mb_substr(trim((string)($_POST['badge'] ?? '')), 0, 20);

    if ($p['title'] === '') {
        $errors[] = 'Title is required.';
    }
    if ($p['file_url'] !== '' && !preg_match('#^https?://#i', $p['file_url'])) {
        $errors[] = 'The file link must start with http:// or https://';
    }

    if (!$errors) {
        $newImage = save_uploaded_image($_FILES['image'] ?? null, $errors);
        if ($newImage) {
            if ($p['image']) @unlink(UPLOADS_DIR . '/' . basename($p['image']));
            $p['image'] = $newImage;
        }
    }

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
            } else {
                $errors[] = 'Could not save the file. Check that data/files/ is writable.';
            }
        }
    }

    if (!$errors && $p['file_name'] === '' && $p['file_url'] === '') {
        $errors[] = 'Upload a file or paste a file link, so visitors have something to download.';
    }

    if (!$errors) {
        if ($p['id']) {
            db()->prepare('UPDATE products SET title=?, description=?, image=?, file_name=?, file_label=?, file_url=?, active=?, category_id=?, featured=?, badge=? WHERE id=?')
                ->execute([$p['title'], $p['description'], $p['image'], $p['file_name'], $p['file_label'], $p['file_url'], $p['active'], $p['category_id'], $p['featured'], $p['badge'], $p['id']]);
        } else {
            db()->prepare('INSERT INTO products (title, description, image, file_name, file_label, file_url, active, category_id, featured, badge, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$p['title'], $p['description'], $p['image'], $p['file_name'], $p['file_label'], $p['file_url'], $p['active'], $p['category_id'], $p['featured'], $p['badge'], time()]);
        }
        flash('Product saved.');
        redirect('admin/products.php');
    }
}

$categories = db()->query('SELECT id, name FROM categories ORDER BY sort_order, name')->fetchAll();
$featuredCount = (int)db()->query('SELECT COUNT(*) FROM products WHERE featured = 1 AND active = 1')->fetchColumn();

admin_header($p['id'] ? 'Edit product' : 'Add product',
    $p['id'] ? number_format((int)$p['views']) . ' views · ' . number_format((int)$p['downloads']) . ' downloads' : 'Fill in the details and upload the file visitors will get',
    $p['id'] ? '<a class="btn btn-light" href="../product.php?id=' . (int)$p['id'] . '" target="_blank">' . icon('eye', 16) . ' Preview</a>' : '');
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
      </div>
    </div>
    <div>
      <div class="card">
        <h2>Publishing</h2>
        <p class="card-sub">&nbsp;</p>
        <label class="switch"><input type="checkbox" name="active" <?= $p['active'] ? 'checked' : '' ?>><span></span> Visible in the store</label>
        <br><br>
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
</script>
<?php admin_footer();
