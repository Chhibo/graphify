<?php
require __DIR__ . '/includes/bootstrap.php';

$form = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
$errors = [];
if (is_post()) {
    verify_csrf();
    foreach ($form as $k => $v) {
        $form[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    // Spam protection: hidden field must stay empty, and one message per minute per visitor.
    $spam = ($_POST['website'] ?? '') !== '' || (time() - (int) ($_SESSION['last_contact'] ?? 0)) < 60;
    if (mb_strlen($form['name']) < 2) {
        $errors[] = 'Please enter your name.';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (mb_strlen($form['message']) < 5) {
        $errors[] = 'Please write your message.';
    }
    if (!$errors && $spam) {
        $errors[] = 'Please wait a moment before sending another message.';
    }
    if (!$errors) {
        db_insert('messages', [
            'name' => mb_substr($form['name'], 0, 120),
            'email' => mb_substr($form['email'], 0, 190),
            'subject' => mb_substr($form['subject'], 0, 200),
            'message' => mb_substr($form['message'], 0, 5000),
            'is_read' => 0,
            'created_at' => now(),
        ]);
        $_SESSION['last_contact'] = time();
        $to = setting('contact_email') !== '' ? setting('contact_email') : setting('store_email');
        send_mail($to, 'New message: ' . ($form['subject'] !== '' ? $form['subject'] : 'Contact form'),
            "From: {$form['name']} <{$form['email']}>\n\n{$form['message']}", $form['email']);
        flash('success', 'Thank you! Your message has been sent. We will get back to you soon.');
        redirect('contact.php');
    }
}

$pageTitle = setting('contact_title', 'Get In Touch');
include __DIR__ . '/includes/header.php';
?>
<div class="container">
  <nav class="breadcrumb"><a href="<?= url() ?>">Home</a> <span>›</span> <span>Contact</span></nav>
</div>
<section class="contact-section">
  <div class="container contact-grid">
    <div class="contact-main">
      <h1><?= e(setting('contact_title', 'Get In Touch')) ?></h1>
      <?php if (setting('contact_text') !== ''): ?><p class="muted"><?= nl2br(e(setting('contact_text'))) ?></p><?php endif; ?>
      <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
      <form method="post" class="contact-form">
        <?= csrf_field() ?>
        <div class="grid-2">
          <label>Your name<input name="name" value="<?= e($form['name']) ?>" required maxlength="120" autocomplete="name"></label>
          <label>Email address<input name="email" type="email" value="<?= e($form['email']) ?>" required maxlength="190" autocomplete="email"></label>
        </div>
        <label>Subject<input name="subject" value="<?= e($form['subject']) ?>" maxlength="200"></label>
        <label>Write your message<textarea name="message" rows="6" required maxlength="5000"><?= e($form['message']) ?></textarea></label>
        <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
        <button class="btn btn-primary btn-square" type="submit">Submit Message</button>
      </form>
    </div>
    <aside class="contact-card">
      <?php if (setting('contact_image') !== ''): ?><img src="<?= e(img_url(setting('contact_image'))) ?>" alt="" class="contact-img"><?php endif; ?>
      <h3><?= e(setting('contact_box_title', setting('store_name'))) ?></h3>
      <?php if (setting('contact_address') !== ''): ?><p><?= nl2br(e(setting('contact_address'))) ?></p><?php endif; ?>
      <?php if (setting('contact_phone') !== ''): ?><p>Phone: <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('contact_phone'))) ?>"><?= e(setting('contact_phone')) ?></a></p><?php endif; ?>
      <?php if (setting('contact_email') !== ''): ?><p>Email: <a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></p><?php endif; ?>
      <?php if (setting('whatsapp_number') !== ''): ?><p>WhatsApp: <a href="https://wa.me/<?= e(preg_replace('/\D+/', '', setting('whatsapp_number'))) ?>" target="_blank" rel="noopener">Chat with us</a></p><?php endif; ?>
      <?php if (setting('contact_hours') !== ''): ?>
        <hr>
        <h3><?= e(setting('contact_hours_title', 'Opening Hours')) ?></h3>
        <p><?= nl2br(e(setting('contact_hours'))) ?></p>
      <?php endif; ?>
    </aside>
  </div>
  <?php $map = setting('contact_map'); if (preg_match('#^https://(www\.)?google\.[a-z.]+/maps/embed\?#', $map)): ?>
    <div class="container"><iframe class="contact-map" src="<?= e($map) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map"></iframe></div>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
