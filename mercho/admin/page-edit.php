<?php
require __DIR__ . '/includes/auth.php';
require_admin('content');

$id = (int) ($_GET['id'] ?? 0);
$page = $id ? q_one('SELECT * FROM pages WHERE id = ?', [$id]) : null;
if ($id && !$page) {
    redirect('admin/pages.php');
}
$page = $page ?? ['id' => 0, 'type' => ($_GET['type'] ?? '') === 'post' ? 'post' : 'page', 'title' => '', 'slug' => '', 'content' => '', 'image' => '', 'active' => 1];
$errors = [];

if (is_post()) {
    verify_csrf();
    $data = [
        'type' => ($_POST['type'] ?? 'page') === 'post' ? 'post' : 'page',
        'title' => trim((string) ($_POST['title'] ?? '')),
        'content' => (string) ($_POST['content'] ?? ''),
        'active' => post_flag('active'),
    ];
    $slugInput = trim((string) ($_POST['slug'] ?? ''));
    if ($data['title'] === '') {
        $errors[] = 'Title is required.';
    }
    try {
        $img = upload_image($_FILES['image'] ?? null);
        if ($img) {
            $data['image'] = $img;
        }
    } catch (RuntimeException $ex) {
        $errors[] = $ex->getMessage();
    }
    if (!$errors) {
        $data['slug'] = unique_slug('pages', $slugInput !== '' ? $slugInput : $data['title'], (int) $page['id']);
        if ($page['id']) {
            db_update('pages', (int) $page['id'], $data);
            $newId = (int) $page['id'];
        } else {
            $newId = db_insert('pages', $data + ['image' => $data['image'] ?? '', 'created_at' => now()]);
        }
        flash('success', 'Saved.');
        redirect('admin/page-edit.php?id=' . $newId);
    }
    $page = array_merge($page, $data);
}

$adminTitle = $page['id'] ? 'Edit ' . ($page['type'] === 'post' ? 'blog post' : 'page') : 'New ' . ($page['type'] === 'post' ? 'blog post' : 'page');
include __DIR__ . '/includes/header.php';
?>
<p><a href="pages.php">← Pages & Blog</a><?php if ($page['id']): ?> · <a href="<?= url('page.php?slug=' . rawurlencode($page['slug'])) ?>" target="_blank">View</a><?php endif; ?></p>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="card">
  <?= csrf_field() ?>
  <input type="hidden" name="type" value="<?= e($page['type']) ?>">
  <div class="grid-2">
    <label>Title<input name="title" value="<?= e($page['title']) ?>" required></label>
    <label>Address (slug) <small class="muted">leave empty to create from title</small><input name="slug" value="<?= e($page['slug']) ?>"></label>
  </div>
  <label>Content <small class="muted">(use the toolbar for headings, colors, sizes, images, tables, videos… or the &lt;/&gt; button to edit HTML)</small><textarea name="content" rows="14" data-editor="full"><?= e($page['content']) ?></textarea></label>
  <?php if ($page['image'] !== ''): ?><img class="preview" src="<?= e(img_url($page['image'])) ?>" alt=""><?php endif; ?>
  <label>Cover image <small class="muted">(optional)</small><input type="file" name="image" accept="image/*"></label>
  <label class="inline"><input type="checkbox" name="active" value="1" <?= $page['active'] ? 'checked' : '' ?>> Visible</label>
  <button class="btn btn-primary" type="submit">Save</button>
</form>
<?php include __DIR__ . '/includes/editor.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
