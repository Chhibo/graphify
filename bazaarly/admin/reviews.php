<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

if (is_post() && input('action') === 'delete') {
    q('DELETE FROM reviews WHERE id = ?', [(int) input('id')]);
    flash('success', 'Review deleted.');
    back('admin/reviews.php');
}

$total = (int) q_val('SELECT COUNT(*) FROM reviews');
[$page, $pages, $offset] = paginate($total, 25, (int) input('page', '1'));
$rows = q_all("SELECT r.*, s.name AS seller, w.name AS reviewer FROM reviews r
    LEFT JOIN users s ON s.id = r.seller_id LEFT JOIN users w ON w.id = r.reviewer_id
    ORDER BY r.created_at DESC LIMIT 25 OFFSET $offset");

$adminActive = 'reviews';
$pageTitle = 'Reviews';
require APP_ROOT . '/includes/admin-top.php';
?>
<div class="card table-card">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Rating</th><th>Review</th><th>Seller</th><th>By</th><th>Date</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="nowrap"><?= stars((float) $r['rating'], 'sm') ?></td>
          <td><?= e(excerpt((string) $r['comment'], 140)) ?: '<span class="muted">—</span>' ?></td>
          <td><a href="<?= e(url('admin/user-edit.php?id=' . $r['seller_id'])) ?>"><?= e($r['seller'] ?? '—') ?></a></td>
          <td><a href="<?= e(url('admin/user-edit.php?id=' . $r['reviewer_id'])) ?>"><?= e($r['reviewer'] ?? '—') ?></a></td>
          <td class="nowrap muted small"><?= e(format_date($r['created_at'])) ?></td>
          <td><form method="post" data-confirm="Delete this review?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="delete"><button class="icon-btn sm danger" type="submit" aria-label="Delete"><?= icon('trash') ?></button></form></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="center muted">No reviews yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?= pagination_links($page, $pages) ?>
<?php require APP_ROOT . '/includes/admin-bottom.php';
