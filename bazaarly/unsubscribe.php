<?php
require __DIR__ . '/includes/bootstrap.php';

$uid = (int) input('u');
$valid = $uid > 0 && hash_equals(unsubscribe_token($uid), input('t')) && q_val('SELECT id FROM users WHERE id = ?', [$uid]);
if ($valid && is_post()) {
    $on = input('action') === 'resubscribe' ? 1 : 0;
    q('UPDATE users SET newsletter = ? WHERE id = ?', [$on, $uid]);
    flash('success', $on ? __('You are subscribed again. Welcome back!') : __('You have been unsubscribed.'));
    header('Location: ' . url('unsubscribe.php?u=' . $uid . '&t=' . input('t')));
    exit;
}
$subscribed = $valid ? (int) q_val('SELECT newsletter FROM users WHERE id = ?', [$uid]) === 1 : false;
if ($valid && !is_post() && $subscribed && input('confirm') !== '0') {
    // one click from the email unsubscribes straight away
    q('UPDATE users SET newsletter = 0 WHERE id = ?', [$uid]);
    $subscribed = false;
}
$pageTitle = __('Email preferences');
require __DIR__ . '/includes/header.php';
?>
<section class="container section-sm">
  <div class="card auth-single center">
    <?php if (!$valid): ?>
      <span class="auth-icon"><?= icon('alert') ?></span>
      <h1><?= t('Link not valid') ?></h1>
      <p class="muted"><?= t('You can change your email preferences any time in your account settings.') ?></p>
    <?php elseif (!$subscribed): ?>
      <span class="auth-icon success"><?= icon('check-circle') ?></span>
      <h1><?= t('You have been unsubscribed') ?></h1>
      <p class="muted"><?= t('You will no longer receive news and offers from {site}. Important emails about your account and ads are still sent.', ['site' => setting('site_name')]) ?></p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="u" value="<?= $uid ?>"><input type="hidden" name="t" value="<?= e(input('t')) ?>"><input type="hidden" name="action" value="resubscribe">
        <button class="btn btn-ghost" type="submit"><?= t('Oops, subscribe me again') ?></button></form>
    <?php else: ?>
      <span class="auth-icon"><?= icon('mail') ?></span>
      <h1><?= t('You are subscribed') ?></h1>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="u" value="<?= $uid ?>"><input type="hidden" name="t" value="<?= e(input('t')) ?>"><input type="hidden" name="action" value="unsubscribe">
        <button class="btn btn-primary" type="submit"><?= t('Unsubscribe') ?></button></form>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php';
