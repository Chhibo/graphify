<?php
require __DIR__ . '/includes/auth.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        q('DELETE FROM testimonials WHERE id = ?', [$id]);
        flash('success', 'Review deleted.');
        redirect('admin/testimonials.php');
    }
    $data = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'text' => trim((string) ($_POST['text'] ?? '')),
        'rating' => max(1, min(5, (int) ($_POST['rating'] ?? 5))),
        'active' => post_flag('active'),
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
    ];
    if ($data['name'] === '' || $data['text'] === '') {
        flash('error', 'Name and review text are required.');
    } else {
        $id ? db_update('testimonials', $id, $data) : db_insert('testimonials', $data);
        flash('success', 'Review saved.');
    }
    redirect('admin/testimonials.php');
}

$edit = isset($_GET['edit']) ? q_one('SELECT * FROM testimonials WHERE id = ?', [(int) $_GET['edit']]) : null;
$rows = q_all('SELECT * FROM testimonials ORDER BY sort_order, id');
$adminTitle = 'Customer reviews';
include __DIR__ . '/includes/header.php';
?>
<div class="grid-main">
  <div class="card">
    <p class="muted">Shown in “Our Happy Customers” on the home page.</p>
    <div class="table-wrap"><table>
      <thead><tr><th>Name</th><th>Review</th><th>Stars</th><th>Visible</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $t): ?>
        <tr>
          <td><b><?= e($t['name']) ?></b></td>
          <td><?= e(excerpt($t['text'], 90)) ?></td>
          <td><?= (int) $t['rating'] ?>★</td>
          <td><?= $t['active'] ? 'Yes' : 'No' ?></td>
          <td class="actions">
            <a class="btn btn-sm btn-light" href="?edit=<?= (int) $t['id'] ?>">Edit</a>
            <form method="post" data-confirm="Delete this review?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><button class="btn btn-sm btn-danger-light">Delete</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <div class="card-head"><h2><?= $edit ? 'Edit review' : 'Add review' ?></h2></div>
    <label>Customer name<input name="name" value="<?= e($edit['name'] ?? '') ?>" required></label>
    <label>Review<textarea name="text" rows="5" required><?= e($edit['text'] ?? '') ?></textarea></label>
    <div class="grid-2">
      <label>Stars<select name="rating"><?php for ($i = 5; $i >= 1; $i--): ?><option <?= (int) ($edit['rating'] ?? 5) === $i ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?></select></label>
      <label>Sort order<input name="sort_order" type="number" value="<?= (int) ($edit['sort_order'] ?? 0) ?>"></label>
    </div>
    <label class="inline"><input type="checkbox" name="active" value="1" <?= ($edit['active'] ?? 1) ? 'checked' : '' ?>> Visible</label>
    <button class="btn btn-primary btn-block" type="submit">Save</button>
    <?php if ($edit): ?><a class="btn btn-light btn-block" href="testimonials.php">Cancel</a><?php endif; ?>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
