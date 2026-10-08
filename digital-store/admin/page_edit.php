<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin_layout.php';
require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$pg = ['id' => 0, 'title' => '', 'slug' => '', 'content' => '', 'sort_order' => 0, 'show_in_header' => 0, 'show_in_footer' => 1];
if ($id) {
    $q = db()->prepare('SELECT * FROM pages WHERE id = ?');
    $q->execute([$id]);
    $pg = $q->fetch();
    if (!$pg) {
        redirect('admin/pages.php');
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $pg['title'] = trim((string)($_POST['title'] ?? ''));
    $pg['slug'] = trim((string)($_POST['slug'] ?? ''));
    $pg['content'] = (string)($_POST['content'] ?? '');
    $pg['sort_order'] = (int)($_POST['sort_order'] ?? 0);
    $pg['show_in_header'] = isset($_POST['show_in_header']) ? 1 : 0;
    $pg['show_in_footer'] = isset($_POST['show_in_footer']) ? 1 : 0;
    if ($pg['title'] === '') {
        $errors[] = 'Enter a page title.';
    } else {
        $slug = unique_slug('pages', slugify($pg['slug'] !== '' ? $pg['slug'] : $pg['title']), (int)$pg['id']);
        $vals = [$pg['title'], $slug, $pg['content'], $pg['sort_order'], $pg['show_in_header'], $pg['show_in_footer'], time()];
        if ($pg['id']) {
            db()->prepare('UPDATE pages SET title=?, slug=?, content=?, sort_order=?, show_in_header=?, show_in_footer=?, updated_at=? WHERE id=?')
                ->execute(array_merge($vals, [$pg['id']]));
        } else {
            db()->prepare('INSERT INTO pages (title, slug, content, sort_order, show_in_header, show_in_footer, updated_at) VALUES (?,?,?,?,?,?,?)')
                ->execute($vals);
        }
        flash('Page saved.');
        redirect('admin/pages.php');
    }
}

admin_header($pg['id'] ? 'Edit page' : 'Add page');
?>
<?php foreach ($errors as $err): ?><p class="bad"><?= e($err) ?></p><?php endforeach; ?>
<form method="post" class="card form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$pg['id'] ?>">
  <label>Title <input name="title" required value="<?= e($pg['title']) ?>" placeholder="e.g. About us"></label>
  <label>Web address (optional) <input name="slug" value="<?= e($pg['slug']) ?>" placeholder="made from the title if empty">
    <small>Used in the link: page.php?slug=<b>about-us</b></small></label>
  <label>Content
    <textarea name="content" rows="16"><?= e($pg['content']) ?></textarea>
    <small>Write plain text (empty lines start a new paragraph), or use HTML such as &lt;h2&gt;, &lt;p&gt;, &lt;a href="…"&gt;, &lt;ul&gt;&lt;li&gt;.</small>
  </label>
  <label>Order <input type="number" name="sort_order" value="<?= (int)$pg['sort_order'] ?>" class="short"></label>
  <label class="check"><input type="checkbox" name="show_in_header" <?= $pg['show_in_header'] ? 'checked' : '' ?>> Show in the header menu</label>
  <label class="check"><input type="checkbox" name="show_in_footer" <?= $pg['show_in_footer'] ? 'checked' : '' ?>> Show in the footer</label>
  <button class="btn">Save page</button>
  <a href="pages.php">Cancel</a>
</form>
<?php admin_footer();
