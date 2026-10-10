<?php
require __DIR__ . '/includes/auth.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $r = q_one('SELECT * FROM reviews WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
    if ($r) {
        $action = $_POST['action'] ?? '';
        if ($action === 'approve') {
            q('UPDATE reviews SET approved = 1 WHERE id = ?', [$r['id']]);
        } elseif ($action === 'hide') {
            q('UPDATE reviews SET approved = 0 WHERE id = ?', [$r['id']]);
        } elseif ($action === 'delete') {
            q('DELETE FROM reviews WHERE id = ?', [$r['id']]);
        }
        refresh_product_rating((int) $r['product_id']);
        flash('success', 'Review updated.');
    }
    redirect('admin/reviews.php' . (isset($_GET['all']) ? '?all=1' : ''));
}
$showAll = isset($_GET['all']);
$rows = q_all('SELECT r.*, p.name AS product_name FROM reviews r LEFT JOIN products p ON p.id = r.product_id'
    . ($showAll ? '' : ' WHERE r.approved = 0') . ' ORDER BY r.id DESC LIMIT 300');
$adminTitle = 'Product reviews';
include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <div class="toolbar">
    <p class="muted grow-form"><?= $showAll ? 'All reviews' : 'Reviews waiting for approval' ?> ·
      <a href="?<?= $showAll ? '' : 'all=1' ?>"><?= $showAll ? 'Show only waiting' : 'Show all reviews' ?></a> ·
      <a href="settings.php">Review settings</a></p>
  </div>
  <?php if ($rows): ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Product</th><th>Customer</th><th>Rating</th><th>Review</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="<?= e(url('product.php?id=' . (int) $r['product_id'])) ?>" target="_blank"><?= e($r['product_name'] ?? '-') ?></a></td>
        <td><b><?= e($r['name']) ?></b><br><small class="muted"><?= e($r['email']) ?></small></td>
        <td><?= (int) $r['rating'] ?>★</td>
        <td style="max-width:360px"><?= e(excerpt($r['comment'], 220)) ?><br><small class="muted"><?= e(date('M j, Y', strtotime($r['created_at']))) ?></small></td>
        <td><?= (int) $r['approved'] ? '<span class="status st-delivered">Published</span>' : '<span class="status st-pending">Waiting</span>' ?></td>
        <td class="actions">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <?php if ((int) $r['approved']): ?>
              <button class="btn btn-sm btn-light" name="action" value="hide">Hide</button>
            <?php else: ?>
              <button class="btn btn-sm btn-primary" name="action" value="approve">Approve</button>
            <?php endif; ?>
          </form>
          <form method="post" data-confirm="Delete this review?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-danger-light" name="action" value="delete">Delete</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?><p class="muted"><?= $showAll ? 'No reviews yet.' : 'No reviews waiting. 🎉' ?></p><?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
