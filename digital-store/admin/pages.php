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

admin_header('Pages', 'Privacy Policy, Terms, About, FAQ…', '<a class="btn btn-grad" href="page_edit.php">' . icon('plus', 16) . ' Add page</a>');
?>
<p class="muted">Pages like Privacy Policy, Terms, About or FAQ. The <a href="../contact.php" target="_blank">Contact us</a> page is built in;
  change its text in <a href="settings.php#contact">Settings</a>.</p>
<?php if (!$pages): ?>
  <p class="muted">No pages yet.</p>
<?php else: ?>
<div class="table-wrap"><table>
  <tr><th>Order</th><th>Title</th><th>Header menu</th><th>Footer</th><th></th></tr>
  <?php foreach ($pages as $pg): ?>
    <tr>
      <td><?= (int)$pg['sort_order'] ?></td>
      <td><div class="prod-cell"><span class="mini-empty"><?= icon('file') ?></span><div><a href="page_edit.php?id=<?= (int)$pg['id'] ?>"><?= e($pg['title']) ?></a><br><small>page.php?slug=<?= e($pg['slug']) ?></small></div></div></td>
      <?php foreach (['toggle_header' => 'show_in_header', 'toggle_footer' => 'show_in_footer'] as $act => $col): ?>
        <td><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$pg['id'] ?>">
          <button class="pill <?= $pg[$col] ? 'on' : '' ?>" name="action" value="<?= $act ?>"><?= $pg[$col] ? 'Shown' : 'Hidden' ?></button></form></td>
      <?php endforeach; ?>
      <td class="actions">
        <a class="icon-btn" href="../page.php?slug=<?= e(rawurlencode($pg['slug'])) ?>" target="_blank" title="View"><?= icon('external', 16) ?></a>
        <a class="icon-btn" href="page_edit.php?id=<?= (int)$pg['id'] ?>" title="Edit"><?= icon('pencil', 16) ?></a>
        <form method="post" onsubmit="return confirm('Delete this page?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$pg['id'] ?>">
          <button class="icon-btn danger" name="action" value="delete" title="Delete"><?= icon('trash', 16) ?></button></form>
      </td>
    </tr>
  <?php endforeach; ?>
</table></div>
<?php endif; ?>
<?php admin_footer();
