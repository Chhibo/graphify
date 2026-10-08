<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_installed();

page_header('Privacy policy');
?>
<article class="card prose">
  <h1>Privacy policy</h1>
  <?php if (setting('privacy_text') !== ''): ?>
    <?= nl2br(e(setting('privacy_text'))) ?>
  <?php else: ?>
    <p>To unlock free downloads we show offers from advertising partners (CPA networks such as OGAds and AdBlueMedia).</p>
    <p>When you open an offer list, your IP address and browser type are sent to the partner so it can show offers that work in your country and on your device. A random tracking code is attached to each offer so we can confirm completion and unlock your download. We do not ask for your name or email.</p>
    <p>Offers are run by third parties under their own terms and privacy policies. We use a session cookie only to remember your unlock progress.</p>
    <p>Contact: <?= e(setting('contact_email', 'the site owner')) ?></p>
  <?php endif; ?>
</article>
<?php page_footer();
