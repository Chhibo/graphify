<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/listing-form.php';
require_admin();

$listing = q_one('SELECT * FROM listings WHERE id = ?', [(int) input('id')]);
if (!$listing) {
    flash('error', 'Ad not found.');
    redirect('admin/listings.php');
}
if (is_post() && input('action') === 'delete') {
    delete_listing((int) $listing['id']);
    flash('success', 'Ad deleted.');
    redirect('admin/listings.php');
}
$errors = [];
$d = $listing;
if (is_post()) {
    $errors = listing_form_save($listing, true, 'admin/listing-edit.php?id={id}');
    $d = array_merge($d, array_intersect_key($_POST, $d));
}
$images = q_all('SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id', [$listing['id']]);
$owner = q_one('SELECT * FROM users WHERE id = ?', [$listing['user_id']]);

$adminActive = 'listings';
$pageTitle = 'Edit ad #' . $listing['id'];
require APP_ROOT . '/includes/admin-top.php';
?>
<div class="page-actions">
  <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/listings.php')) ?>"><?= icon('chevron-left') ?>All ads</a>
  <span class="muted small">Posted by <a href="<?= e(url('admin/user-edit.php?id=' . $listing['user_id'])) ?>"><?= e($owner['name'] ?? 'unknown') ?></a> · <?= e(format_date($listing['created_at'])) ?> · <?= number_format((int) $listing['views']) ?> views</span>
  <span class="push"></span>
  <a class="btn btn-ghost btn-sm" href="<?= e(listing_url($listing)) ?>" target="_blank"><?= icon('eye') ?>View</a>
  <form method="post" data-confirm="Delete this ad permanently?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-danger" type="submit"><?= icon('trash') ?>Delete</button></form>
</div>
<?php render_listing_form($d, $images, $errors, true, url('admin/listings.php')); ?>
<?php require APP_ROOT . '/includes/admin-bottom.php';
