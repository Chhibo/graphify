<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

if (is_post()) {
    if (input('action') === 'recheck') {
        $r = license_recheck(true);
        flash('info', 'Checked: ' . $r . '.');
        redirect('admin/license.php');
    }
    $code = input('purchase_code');
    $result = license_activate($code);
    if ($result['ok']) {
        license_store($code, $result);
        log_activity('settings.license', 'Purchase code saved: ' . license_mask($code));
        flash('success', 'Purchase code saved. Thank you!');
    } else {
        flash('error', $result['error']);
    }
    redirect('admin/license.php');
}

$code = setting('license_code');
$status = $code !== '' && license_known($code) ? setting('license_status') : '';
$labels = [
    'active' => ['badge-success', 'Registered', 'This website is registered with your purchase code.'],
    'local' => ['badge-info', 'Test install', 'Local and test addresses (localhost, *.test…) do not use up your purchase code. Enter it again after you move the site to its real domain.'],
    'pending' => ['badge-warning', 'Waiting', 'The license server could not be reached yet. The website works normally and registration is retried automatically.'],
    'offline' => ['badge-success', 'Valid', 'Your purchase code is valid.'],
    'used' => ['badge-danger', 'Used elsewhere', setting('license_message') ?: 'This purchase code is already registered on another website.'],
    'blocked' => ['badge-danger', 'Disabled', setting('license_message') ?: 'This purchase code has been disabled by the seller.'],
];
[$badgeClass, $badgeText, $note] = $labels[$status] ?? ['badge-muted', 'Missing', 'No purchase code is saved for this website yet.'];

$adminActive = 'license';
$pageTitle = 'Purchase code';
require APP_ROOT . '/includes/admin-top.php';
?>
<div class="admin-grid">
  <section class="card form-section">
    <h2><?= icon('key') ?>Your purchase code</h2>
    <p><span class="badge <?= $badgeClass ?>"><?= e($badgeText) ?></span></p>
    <p class="muted"><?= e($note) ?></p>
    <?php if ($code !== ''): ?>
      <ul class="check-list">
        <li><?= icon('key') ?>Code: <code><?= e(license_mask($code)) ?></code></li>
        <li><?= icon('globe') ?>Website: <strong><?= e(setting('license_domain') ?: license_domain()) ?></strong></li>
        <?php if (setting('license_checked_at')): ?><li><?= icon('clock') ?>Last checked <?= e(time_ago(date('Y-m-d H:i:s', (int) setting('license_checked_at')))) ?></li><?php endif; ?>
      </ul>
      <?php if (LICENSE_SERVER !== '' && $status !== 'local'): ?>
        <form method="post" style="margin:14px 0"><?= csrf_field() ?><input type="hidden" name="action" value="recheck"><button class="btn btn-ghost btn-sm" type="submit"><?= icon('refresh') ?>Check now</button></form>
      <?php endif; ?>
    <?php endif; ?>
  </section>
  <form method="post" class="card form-section">
    <?= csrf_field() ?>
    <h2><?= $code === '' ? 'Enter your purchase code' : 'Change purchase code' ?></h2>
    <p class="muted small">Moving to a new domain? Enter your code again here from the new address. If it says the code is used elsewhere, ask the seller to reset it.</p>
    <div class="field"><label for="pc">Purchase code</label><input id="pc" name="purchase_code" class="mono" placeholder="BZR-XXXX-XXXX-XXXX-XXXX" required autocomplete="off" spellcheck="false" maxlength="40"></div>
    <button class="btn btn-primary" type="submit"><?= icon('check') ?>Save code</button>
  </form>
</div>
<?php require APP_ROOT . '/includes/admin-bottom.php';
