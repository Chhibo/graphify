<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin_layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        db()->prepare('DELETE FROM pages WHERE id = ?')->execute([$id]);
        flash('Page deleted.');
    } elseif ($action === 'toggle_header' || $action === 'toggle_footer') {
        $col = $action === 'toggle_header' ? 'show_in_header' : 'show_in_footer';
        db()->prepare("UPDATE pages SET $col = 1 - $col WHERE id = ?")->execute([$id]);
    }
    redirect('admin/pages.php');
}

$pages = db()->query('SELECT * FROM pages ORDER BY sort_order, title')->fetchAll();

admin_header('Pages');
?>
<p class="muted">Pages like Privacy Policy, Terms, About or FAQ. The <a href="../contact.php" target="_blank">Contact us</a> page is built in;
  change its text in <a href="settings.php#contact">Settings</a>.</p>
<p><a class="btn" href="page_edit.php">+ Add page</a></p>
<?php if (!$pages): ?>
  <p class="muted">No pages yet.</p>
<?php else: ?>
<table>
  <tr><th>Order</th><th>Title</th><th>Header menu</th><th>Footer</th><th></th></tr>
  <?php foreach ($pages as $pg): ?>
    <tr>
      <td><?= (int)$pg['sort_order'] ?></td>
      <td><a href="../page.php?slug=<?= e(rawurlencode($pg['slug'])) ?>" target="_blank"><?= e($pg['title']) ?></a></td>
      <?php foreach (['toggle_header' => 'show_in_header', 'toggle_footer' => 'show_in_footer'] as $act => $col): ?>
        <td><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$pg['id'] ?>">
          <button class="pill <?= $pg[$col] ? 'on' : '' ?>" name="action" value="<?= $act ?>"><?= $pg[$col] ? 'Shown' : 'Hidden' ?></button></form></td>
      <?php endforeach; ?>
      <td class="actions">
        <a class="btn btn-small" href="page_edit.php?id=<?= (int)$pg['id'] ?>">Edit</a>
        <form method="post" onsubmit="return confirm('Delete this page?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$pg['id'] ?>">
          <button class="btn btn-small btn-danger" name="action" value="delete">Delete</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php admin_footer();
