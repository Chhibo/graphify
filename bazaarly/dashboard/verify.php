<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_login();
$uid = (int) $me['id'];
if (setting('id_verification', '1') !== '1') {
    flash('info', __('Seller verification is not available on this site.'));
    redirect('dashboard/');
}
$docTypes = ['national_id' => __('National ID card'), 'passport' => __('Passport'), 'driving_licence' => __('Driving licence'), 'business' => __('Business registration')];
$latest = q_one('SELECT * FROM verification_requests WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$uid]);
$errors = [];

if (is_post() && !$me['verified'] && ($latest['status'] ?? '') !== 'pending') {
    require_verified_email('dashboard/verify.php');
    $type = array_key_exists(input('doc_type'), $docTypes) ? input('doc_type') : 'national_id';
    $stored = [];
    foreach (['doc_front' => true, 'doc_back' => false, 'selfie' => false] as $field => $required) {
        $file = normalize_files($_FILES[$field] ?? null)[0] ?? null;
        if (!$file) {
            if ($required) {
                $errors[$field] = __('Please add a photo of the front of your document.');
            }
            continue;
        }
        // private folder: never reachable from the web, only admins can view the files
        $res = store_image($file, 'data/private/ids', 1800, 480);
        if (is_string($res)) {
            $errors[$field] = $res;
        } else {
            $stored[$field] = $res['path'];
            if ($res['thumb'] !== $res['path']) {
                delete_upload($res['thumb']);
            }
        }
    }
    if (empty($_POST['consent'])) {
        $errors['consent'] = __('Please confirm that the document is yours.');
    }
    if ($errors) {
        foreach ($stored as $p) {
            delete_upload($p);
        }
    } else {
        db_insert('verification_requests', [
            'user_id' => $uid, 'doc_type' => $type, 'doc_front' => $stored['doc_front'], 'doc_back' => $stored['doc_back'] ?? '',
            'selfie' => $stored['selfie'] ?? '', 'note' => mb_substr(input('note'), 0, 1000), 'status' => 'pending', 'admin_note' => '', 'created_at' => now(),
        ]);
        flash('success', __('Thanks! We received your documents and will review them soon.'));
        redirect('dashboard/verify.php');
    }
}
$err = fn(string $k) => isset($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : '';

$dashActive = 'verify';
$pageTitle = __('Get verified');
require APP_ROOT . '/includes/dash-top.php';
?>
<div class="dash-head"><div><h1><?= t('Get verified') ?></h1><p class="muted"><?= t('Verified sellers get a badge on their profile and ads, so buyers know they are dealing with a real person.') ?></p></div></div>

<?php if ($me['verified']): ?>
  <div class="card verify-state good"><span class="auth-icon success"><?= icon('shield') ?></span><div><h2><?= t('You are a verified seller') ?></h2><p class="muted"><?= t('The “Verified” badge is shown on your profile and ads.') ?></p></div></div>
<?php elseif (($latest['status'] ?? '') === 'pending'): ?>
  <div class="card verify-state"><span class="auth-icon"><?= icon('clock') ?></span><div><h2><?= t('We are checking your documents') ?></h2><p class="muted"><?= t('Sent {when}. This usually takes 1–2 working days. We will email you the result.', ['when' => time_ago($latest['created_at'])]) ?></p></div></div>
<?php else: ?>
  <?php if (($latest['status'] ?? '') === 'rejected'): ?>
    <div class="alert alert-error"><?= icon('alert') ?><span><?= t('Your last request was not approved.') ?> <?= e($latest['admin_note']) ?> <?= t('You can send a new one below.') ?></span></div>
  <?php endif; ?>
  <div class="admin-grid wide-left">
    <form method="post" enctype="multipart/form-data" class="card form-section">
      <?= csrf_field() ?>
      <h2><?= icon('id-card') ?><?= t('Send your documents') ?></h2>
      <div class="field"><label for="v-type"><?= t('Document type') ?></label>
        <select id="v-type" name="doc_type"><?php foreach ($docTypes as $k => $lbl): ?><option value="<?= $k ?>"><?= e($lbl) ?></option><?php endforeach; ?></select></div>
      <div class="form-grid">
        <div class="field<?= isset($errors['doc_front']) ? ' has-error' : '' ?>"><label for="v-front"><?= t('Front of the document') ?></label><input id="v-front" type="file" name="doc_front" accept="image/jpeg,image/png,image/webp" required><?= $err('doc_front') ?></div>
        <div class="field<?= isset($errors['doc_back']) ? ' has-error' : '' ?>"><label for="v-back"><?= t('Back of the document') ?> <small class="muted"><?= t('optional') ?></small></label><input id="v-back" type="file" name="doc_back" accept="image/jpeg,image/png,image/webp"><?= $err('doc_back') ?></div>
      </div>
      <div class="field<?= isset($errors['selfie']) ? ' has-error' : '' ?>"><label for="v-selfie"><?= t('A selfie holding the document') ?> <small class="muted"><?= t('optional, speeds up approval') ?></small></label><input id="v-selfie" type="file" name="selfie" accept="image/jpeg,image/png,image/webp"><?= $err('selfie') ?></div>
      <div class="field"><label for="v-note"><?= t('Note for our team') ?> <small class="muted"><?= t('optional') ?></small></label><textarea id="v-note" name="note" rows="2" maxlength="1000"></textarea></div>
      <label class="check<?= isset($errors['consent']) ? ' has-error' : '' ?>"><input type="checkbox" name="consent" value="1"><span><?= t('This is my own document, and I agree that the site team may look at it to verify my identity.') ?></span></label>
      <?= $err('consent') ?>
      <div class="btn-row"><button class="btn btn-primary" type="submit"><?= icon('upload') ?><?= t('Send for review') ?></button></div>
    </form>
    <section class="card side-card">
      <h3><?= icon('lock') ?><?= t('Your privacy') ?></h3>
      <ul class="check-list">
        <li><?= icon('check') ?><?= t('Documents are stored privately and are never shown to other members.') ?></li>
        <li><?= icon('check') ?><?= t('Only site administrators can view them.') ?></li>
        <?php if (setting('delete_id_docs', '1') === '1'): ?><li><?= icon('check') ?><?= t('They are deleted as soon as your request has been reviewed.') ?></li><?php endif; ?>
        <li><?= icon('check') ?><?= t('Make sure your name and photo are clearly readable.') ?></li>
      </ul>
    </section>
  </div>
<?php endif; ?>
<?php require APP_ROOT . '/includes/dash-bottom.php';
