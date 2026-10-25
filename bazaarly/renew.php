<?php
/**
 * One-click renewal from the "your ad expires soon" email. The link is signed, so it works
 * without signing in, and only for the ad and expiry date it was created for.
 */
require __DIR__ . '/includes/bootstrap.php';

$l = q_one('SELECT * FROM listings WHERE id = ?', [(int) input('id')]);
$ok = $l && hash_equals(renew_token($l), input('t')) && in_array($l['status'], ['active', 'expired'], true);
if ($ok) {
    renew_listing((int) $l['id']);
    if ($l['status'] === 'expired') {
        $status = setting('require_approval') === '1' ? 'pending' : 'active';
        q('UPDATE listings SET status = ? WHERE id = ?', [$status, $l['id']]);
    }
    $fresh = q_one('SELECT expires_at FROM listings WHERE id = ?', [$l['id']]);
    flash('success', $fresh['expires_at']
        ? __('Your ad has been renewed until {date}.', ['date' => format_date($fresh['expires_at'])])
        : __('Your ad has been renewed.'));
    header('Location: ' . listing_url($l));
    exit;
}
$pageTitle = __('Renew ad');
require __DIR__ . '/includes/header.php';
?>
<section class="container section-sm">
  <div class="card auth-single center">
    <span class="auth-icon"><?= icon('alert') ?></span>
    <h1><?= t('This link has expired') ?></h1>
    <p class="muted"><?= t('The ad was already renewed, changed or removed. You can renew your ads any time from your dashboard.') ?></p>
    <a class="btn btn-primary" href="<?= e(url('dashboard/listings.php')) ?>"><?= t('Go to my ads') ?></a>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php';
