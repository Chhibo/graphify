<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/listing-form.php';
$me = require_login();
require_verified_email();

$id = (int) input('id');
$listing = null;
if ($id) {
    $listing = q_one('SELECT * FROM listings WHERE id = ? AND user_id = ?', [$id, $me['id']]);
    if (!$listing) {
        flash('error', __('Ad not found.'));
        redirect('dashboard/listings.php');
    }
}
if (!categories_all()) {
    flash('error', __('No categories exist yet.') . ' ' . ($me['role'] === 'admin' ? __('Add some in Admin → Categories first.') : __('Please try again later.')));
    redirect($me['role'] === 'admin' ? 'admin/categories.php' : 'dashboard/');
}

$errors = [];
$d = $listing ?? listing_form_defaults($me);
if (is_post()) {
    $errors = listing_form_save($listing, false, 'dashboard/listings.php');
    $d = array_merge($d, array_intersect_key($_POST, $d));
}
$fieldValues = is_post() ? array_filter((array) ($_POST['cf'] ?? []), 'is_string') : ($listing ? listing_field_values((int) $listing['id']) : []);
$images = $listing ? q_all('SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id', [$listing['id']]) : [];

$dashActive = $listing ? 'listings' : 'new';
$pageTitle = $listing ? __('Edit ad') : __('Post a new ad');
require APP_ROOT . '/includes/dash-top.php';
?>
<div class="dash-head">
  <div><h1><?= e($pageTitle) ?></h1><p class="muted"><?= $listing ? t('Update the details of your ad.') : t('Great ads sell faster. It only takes a couple of minutes.') ?></p></div>
  <?php if ($listing): ?><a class="btn btn-ghost" href="<?= e(listing_url($listing)) ?>"><?= icon('eye') ?><?= t('View ad') ?></a><?php endif; ?>
</div>
<?php render_listing_form($d, $images, $errors, false, url('dashboard/listings.php'), $fieldValues); ?>
<?php require APP_ROOT . '/includes/dash-bottom.php';
