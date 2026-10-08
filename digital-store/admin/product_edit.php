<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin_layout.php';
require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$p = ['id' => 0, 'title' => '', 'description' => '', 'image' => '', 'file_name' => '',
      'file_label' => '', 'file_url' => '', 'active' => 1];
if ($id) {
    $q = db()->prepare('SELECT * FROM products WHERE id = ?');
    $q->execute([$id]);
    $p = $q->fetch();
    if (!$p) {
        redirect('admin/products.php');
    }
}

$errors = [];

function upload_error(array $f): ?string
{
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        return 'The file is bigger than your hosting allows (' . ini_get('upload_max_filesize')
            . '). Upload it to Google Drive/Dropbox/MediaFire and use the "File link" field instead.';
    }
    return $f['error'] === UPLOAD_ERR_OK ? null : 'Upload failed (error code ' . $f['error'] . ').';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $p['title'] = trim($_POST['title'] ?? '');
    $p['description'] = trim($_POST['description'] ?? '');
    $p['file_url'] = trim($_POST['file_url'] ?? '');
    $p['active'] = isset($_POST['active']) ? 1 : 0;

    if ($p['title'] === '') {
        $errors[] = 'Title is required.';
    }
    if ($p['file_url'] !== '' && !preg_match('#^https?://#i', $p['file_url'])) {
        $errors[] = 'The file link must start with http:// or https://';
    }

    $img = $_FILES['image'] ?? null;
    if (!$errors && $img && $img['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($err = upload_error($img)) {
            $errors[] = $err;
        } else {
            $info = @getimagesize($img['tmp_name']);
            $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
            if (!$ext) {
                $errors[] = 'The image must be JPG, PNG, GIF or WEBP.';
            } else {
                $name = random_token(8) . '.' . $ext;
                if (move_uploaded_file($img['tmp_name'], UPLOADS_DIR . '/' . $name)) {
                    if ($p['image']) @unlink(UPLOADS_DIR . '/' . basename($p['image']));
                    $p['image'] = $name;
                } else {
                    $errors[] = 'Could not save the image. Check that uploads/ is writable.';
                }
            }
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
            db()->prepare('UPDATE products SET title=?, description=?, image=?, file_name=?, file_label=?, file_url=?, active=? WHERE id=?')
                ->execute([$p['title'], $p['description'], $p['image'], $p['file_name'], $p['file_label'], $p['file_url'], $p['active'], $p['id']]);
        } else {
            db()->prepare('INSERT INTO products (title, description, image, file_name, file_label, file_url, active, created_at) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$p['title'], $p['description'], $p['image'], $p['file_name'], $p['file_label'], $p['file_url'], $p['active'], time()]);
        }
        flash('Product saved.');
        redirect('admin/products.php');
    }
}

admin_header($p['id'] ? 'Edit product' : 'Add product');
?>
<?php foreach ($errors as $err): ?><p class="bad"><?= e($err) ?></p><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="card form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
  <label>Title <input name="title" required value="<?= e($p['title']) ?>"></label>
  <label>Description <textarea name="description" rows="6"><?= e($p['description']) ?></textarea></label>
  <label>Cover image (JPG/PNG/WEBP)
    <?php if ($p['image']): ?><img class="preview" src="../uploads/<?= e($p['image']) ?>" alt=""><?php endif; ?>
    <input type="file" name="image" accept="image/*">
  </label>
  <fieldset>
    <legend>The file visitors get</legend>
    <label>Upload a file (max <?= e(ini_get('upload_max_filesize')) ?> on your hosting)
      <?php if ($p['file_label']): ?><small>Current: <?= e($p['file_label']) ?></small><?php endif; ?>
      <input type="file" name="file">
    </label>
    <p class="muted">or</p>
    <label>File link (Google Drive, Dropbox, MediaFire…). Used instead of the upload when filled.
      <input name="file_url" placeholder="https://" value="<?= e($p['file_url']) ?>">
    </label>
  </fieldset>
  <label class="check"><input type="checkbox" name="active" <?= $p['active'] ? 'checked' : '' ?>> Visible in the store</label>
  <button class="btn">Save product</button>
  <a href="products.php">Cancel</a>
</form>
<?php admin_footer();
