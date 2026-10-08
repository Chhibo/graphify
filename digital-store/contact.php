<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_installed();

$errors = [];
$sent = !empty($_GET['sent']);
$form = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach ($form as $k => $_) {
        $form[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $ip = client_ip();

    // Bots fill in every field, including this hidden one.
    if (($_POST['website'] ?? '') !== '') {
        redirect('contact.php?sent=1');
    }
    if ($form['name'] === '' || mb_strlen($form['name']) > 100) {
        $errors[] = 'Please enter your name.';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (mb_strlen($form['message']) < 10 || mb_strlen($form['message']) > 5000) {
        $errors[] = 'Your message should be between 10 and 5000 characters.';
    }
    $recent = db()->prepare('SELECT COUNT(*) FROM messages WHERE ip = ? AND created_at > ?');
    $recent->execute([$ip, time() - 3600]);
    if ((int)$recent->fetchColumn() >= 5) {
        $errors[] = 'You have sent several messages already. Please wait an hour and try again.';
    }

    if (!$errors) {
        db()->prepare('INSERT INTO messages (name, email, subject, message, ip, created_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$form['name'], $form['email'], mb_substr($form['subject'], 0, 200), $form['message'], $ip, time()]);

        $to = setting('contact_email');
        if (setting('contact_notify') === '1' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $subject = '[' . setting('store_name') . '] ' . ($form['subject'] !== '' ? $form['subject'] : 'New contact message');
            $body = "Name: {$form['name']}\nEmail: {$form['email']}\n\n{$form['message']}\n";
            $host = preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
            $headers = 'From: ' . setting('store_name') . " <no-reply@$host>\r\nReply-To: {$form['email']}\r\nContent-Type: text/plain; charset=utf-8";
            @mail($to, str_replace(["\r", "\n"], ' ', $subject), $body, $headers);
        }
        redirect('contact.php?sent=1');
    }
}

page_header('Contact us', 'contact');
?>
<section class="card prose">
  <h1><?= e(setting_or('contact_title', 'Contact us')) ?></h1>
  <?php if ($sent): ?>
    <p class="ok">Thank you! Your message has been sent. We'll reply by email as soon as we can.</p>
    <p><a href="index.php">Back to the store</a></p>
  <?php else: ?>
    <?php if (setting('contact_intro') !== ''): ?><div><?= render_content(setting('contact_intro')) ?></div><?php endif; ?>
    <?php foreach ($errors as $err): ?><p class="bad"><?= e($err) ?></p><?php endforeach; ?>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <label>Your name <input name="name" required maxlength="100" value="<?= e($form['name']) ?>"></label>
      <label>Your email <input type="email" name="email" required value="<?= e($form['email']) ?>"></label>
      <label>Subject <input name="subject" maxlength="200" value="<?= e($form['subject']) ?>"></label>
      <label>Message <textarea name="message" rows="6" required minlength="10" maxlength="5000"><?= e($form['message']) ?></textarea></label>
      <label class="hp" aria-hidden="true">Website <input name="website" tabindex="-1" autocomplete="off"></label>
      <button class="btn">Send message</button>
    </form>
  <?php endif; ?>
</section>
<?php page_footer();
