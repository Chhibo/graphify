<?php
require __DIR__ . '/includes/auth.php';
require_admin('content');

if (is_post()) {
    verify_csrf();
    q('DELETE FROM pages WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
    flash('success', 'Deleted.');
    redirect('admin/pages.php');
}
$rows = q_all('SELECT * FROM pages ORDER BY type, title');
$adminTitle = 'Pages & Blog';
include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <div class="toolbar">
    <p class="muted grow-form">Footer links use these page addresses: about, contact, faq, customer-support, delivery-details, terms, privacy.</p>
    <a class="btn btn-light" href="page-edit.php?type=page"><?= icon('plus', 16) ?> New page</a>
    <a class="btn btn-primary" href="page-edit.php?type=post"><?= icon('plus', 16) ?> New blog post</a>
  </div>
  <div class="table-wrap"><table>
    <thead><tr><th>Title</th><th>Type</th><th>Address</th><th>Visible</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="page-edit.php?id=<?= (int) $r['id'] ?>"><b><?= e($r['title']) ?></b></a></td>
        <td><span class="tag"><?= $r['type'] === 'post' ? 'Blog post' : 'Page' ?></span></td>
        <td><a href="<?= url('page.php?slug=' . rawurlencode($r['slug'])) ?>" target="_blank"><?= e($r['slug']) ?></a></td>
        <td><?= $r['active'] ? 'Yes' : 'No' ?></td>
        <td class="actions">
          <a class="btn btn-sm btn-light" href="page-edit.php?id=<?= (int) $r['id'] ?>">Edit</a>
          <form method="post" data-confirm="Delete “<?= e($r['title']) ?>”?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-danger-light">Delete</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
