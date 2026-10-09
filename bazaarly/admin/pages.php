<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$errors = [];
$editId = (int) input('id');
$editing = input('edit') !== '' || $editId;
$page = $editId ? q_one('SELECT * FROM pages WHERE id = ?', [$editId]) : null;
$d = $page ?? ['title' => '', 'slug' => '', 'content' => '', 'in_header' => 0, 'in_footer' => 1];

if (is_post()) {
    if (input('action') === 'delete') {
        q('DELETE FROM pages WHERE id = ?', [$editId]);
        flash('success', 'Page deleted.');
        redirect('admin/pages.php');
    }
    $d = [
        'title' => mb_substr(input('title'), 0, 150),
        'slug' => slugify(input('slug') ?: input('title')),
        'content' => (string) ($_POST['content'] ?? ''),
        'in_header' => empty($_POST['in_header']) ? 0 : 1,
        'in_footer' => empty($_POST['in_footer']) ? 0 : 1,
    ];
    if ($d['title'] === '') $errors[] = 'Title is required.';
    if (q_val('SELECT id FROM pages WHERE slug = ? AND id <> ?', [$d['slug'], $editId])) $errors[] = 'Another page already uses this URL slug.';
    if (!$errors) {
        $d['updated_at'] = now();
        if ($page) {
            db_update('pages', $d, 'id = ?', [$editId]);
        } else {
            $editId = db_insert('pages', $d + ['created_at' => now()]);
        }
        flash('success', 'Page saved.');
        redirect('admin/pages.php?id=' . $editId);
    }
    $editing = true;
}

$rows = q_all('SELECT * FROM pages ORDER BY id');
$adminActive = 'pages';
$pageTitle = $editing ? ($page ? 'Edit page' : 'New page') : 'Pages';
require APP_ROOT . '/includes/admin-top.php';
?>
<?php if ($editing): ?>
  <div class="page-actions">
    <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/pages.php')) ?>"><?= icon('chevron-left') ?>All pages</a>
    <?php if ($page): ?><span class="push"></span><a class="btn btn-ghost btn-sm" href="<?= e(url('page.php?slug=' . $page['slug'])) ?>" target="_blank"><?= icon('eye') ?>View</a>
      <form method="post" data-confirm="Delete this page?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $editId ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm" type="submit"><?= icon('trash') ?>Delete</button></form><?php endif; ?>
  </div>
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= icon('alert') ?><span><?= e($er) ?></span></div><?php endforeach; ?>
  <form method="post" class="card form-section">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $editId ?>">
    <div class="form-grid">
      <div class="field"><label for="p-title">Title</label><input id="p-title" name="title" value="<?= e($d['title']) ?>" required></div>
      <div class="field"><label for="p-slug">URL slug</label><div class="input-prefix"><span>page.php?slug=</span><input id="p-slug" name="slug" value="<?= e($d['slug']) ?>" placeholder="auto"></div></div>
    </div>
    <div class="field">
      <label for="p-content">Content <small class="muted">(HTML allowed: &lt;p&gt;, &lt;h2&gt;, &lt;ul&gt;, &lt;a&gt;, &lt;img&gt;…)</small></label>
      <div class="editor-bar" data-editor-for="p-content">
        <button type="button" data-wrap="h2">H2</button><button type="button" data-wrap="p">¶</button><button type="button" data-wrap="strong"><b>B</b></button><button type="button" data-wrap="em"><i>I</i></button><button type="button" data-wrap="ul">• List</button><button type="button" data-wrap="a">Link</button>
      </div>
      <textarea id="p-content" name="content" rows="16" class="mono"><?= e((string) $d['content']) ?></textarea>
    </div>
    <div class="switch-row">
      <label class="switch"><input type="checkbox" name="in_header" value="1" <?= $d['in_header'] ? 'checked' : '' ?>><span class="switch-ui"></span><span>Show in header menu</span></label>
      <label class="switch"><input type="checkbox" name="in_footer" value="1" <?= $d['in_footer'] ? 'checked' : '' ?>><span class="switch-ui"></span><span>Show in footer</span></label>
    </div>
    <button class="btn btn-primary" type="submit">Save page</button>
  </form>
<?php else: ?>
  <div class="page-actions"><span class="muted small">Static pages like About, Terms and Privacy.</span><span class="push"></span><a class="btn btn-primary" href="?edit=new"><?= icon('plus') ?>New page</a></div>
  <div class="card table-card">
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Title</th><th>URL</th><th>Header</th><th>Footer</th><th>Updated</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $p): ?>
          <tr>
            <td><a href="?id=<?= (int) $p['id'] ?>"><strong><?= e($p['title']) ?></strong></a></td>
            <td class="muted small">page.php?slug=<?= e($p['slug']) ?></td>
            <td><?= $p['in_header'] ? icon('check', 'text-success') : '' ?></td>
            <td><?= $p['in_footer'] ? icon('check', 'text-success') : '' ?></td>
            <td class="muted small nowrap"><?= e(format_date($p['updated_at'])) ?></td>
            <td class="row-actions nowrap"><a class="icon-btn sm" href="?id=<?= (int) $p['id'] ?>" aria-label="Edit"><?= icon('edit') ?></a><a class="icon-btn sm" href="<?= e(url('page.php?slug=' . $p['slug'])) ?>" target="_blank" aria-label="View"><?= icon('eye') ?></a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="center muted">No pages yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
<?php require APP_ROOT . '/includes/admin-bottom.php';
