<?php
require __DIR__ . '/includes/bootstrap.php';

$me = current_user();
$errors = [];
$data = ['name' => $me['name'] ?? '', 'email' => $me['email'] ?? '', 'subject' => '', 'message' => ''];

if (is_post()) {
    foreach ($data as $k => $_) {
        $data[$k] = mb_substr(input($k), 0, $k === 'message' ? 5000 : 190);
    }
    if ($data['name'] === '') $errors[] = __('Please enter your name.');
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = __('Please enter a valid email address.');
    if ($data['subject'] === '') $errors[] = __('Please add a subject.');
    if (mb_strlen($data['message']) < 10) $errors[] = __('Your message is a little short.');
    if (input('website') !== '') $errors[] = __('Spam detected.'); // honeypot
    if (!$errors && !captcha_passed('contact')) $errors[] = __('Please complete the security check.');
    if (!$errors) {
        db_insert('contact_messages', $data + ['is_read' => 0, 'created_at' => now()]);
        if (setting('contact_email')) {
            send_template('contact_form', setting('contact_email'), [
                'sender_name' => $data['name'], 'sender_email' => $data['email'], 'subject' => $data['subject'],
                'message' => $data['message'], 'action_url' => full_url('admin/messages.php'),
            ]);
        }
        flash('success', __('Thanks! Your message has been sent. We will get back to you soon.'));
        redirect('contact.php');
    }
}

$pageTitle = __('Contact us');
require __DIR__ . '/includes/header.php';
?>
<section class="container section-sm">
  <nav class="breadcrumb"><a href="<?= e(url()) ?>"><?= t('Home') ?></a><?= icon('chevron-right') ?><span><?= t('Contact') ?></span></nav>
  <div class="contact-layout">
    <div class="contact-intro">
      <h1><?= t('Get in touch') ?></h1>
      <p class="lead muted"><?= t('Questions, feedback or a problem with an ad? Send us a message and our team will reply as soon as possible.') ?></p>
      <ul class="contact-points">
        <?php if (setting('contact_email')): ?><li><span class="cp-icon"><?= icon('mail') ?></span><div><strong><?= t('Email') ?></strong><a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></div></li><?php endif; ?>
        <?php if (setting('contact_phone')): ?><li><span class="cp-icon"><?= icon('phone') ?></span><div><strong><?= t('Phone') ?></strong><span><?= e(setting('contact_phone')) ?></span></div></li><?php endif; ?>
        <?php if (setting('contact_address')): ?><li><span class="cp-icon"><?= icon('map-pin') ?></span><div><strong><?= t('Address') ?></strong><span><?= e(setting('contact_address')) ?></span></div></li><?php endif; ?>
      </ul>
    </div>
    <form method="post" class="card form-card">
      <?= csrf_field() ?>
      <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= icon('alert') ?><span><?= e($err) ?></span></div><?php endforeach; ?>
      <div class="form-grid">
        <div class="field"><label for="c-name"><?= t('Your name') ?></label><input id="c-name" name="name" value="<?= e($data['name']) ?>" required></div>
        <div class="field"><label for="c-email"><?= t('Email') ?></label><input id="c-email" type="email" name="email" value="<?= e($data['email']) ?>" required></div>
      </div>
      <div class="field"><label for="c-subject"><?= t('Subject') ?></label><input id="c-subject" name="subject" value="<?= e($data['subject']) ?>" required></div>
      <div class="field"><label for="c-msg"><?= t('Message') ?></label><textarea id="c-msg" name="message" rows="6" required><?= e($data['message']) ?></textarea></div>
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
      <?= captcha_widget('contact') ?>
      <button class="btn btn-primary" type="submit"><?= icon('send') ?><?= t('Send message') ?></button>
    </form>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php';
