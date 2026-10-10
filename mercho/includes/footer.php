<?php
$storeName = setting('store_name', 'MERCHO');
?>
</main>

<?php if (setting_on('newsletter_enabled')): ?>
<section class="newsletter-wrap" id="newsletter">
  <div class="container">
    <div class="newsletter">
      <h2><?= e(setting('newsletter_title', 'STAY UP TO DATE ABOUT OUR LATEST OFFERS')) ?></h2>
      <form action="<?= url('newsletter.php') ?>" method="post" class="newsletter-form">
        <?= csrf_field() ?>
        <label class="input-pill"><?= icon('mail', 18) ?><input type="email" name="email" placeholder="Enter your email address" required></label>
        <button class="btn btn-white" type="submit">Subscribe to Newsletter</button>
      </form>
    </div>
  </div>
</section>
<?php endif; ?>

<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <a class="logo" href="<?= url() ?>"><?= e($storeName) ?></a>
      <p><?= e(setting('store_tagline')) ?></p>
      <div class="socials">
        <?php foreach (['twitter', 'facebook', 'instagram', 'github'] as $s): if (setting('social_' . $s) !== ''): ?>
          <a href="<?= e(setting('social_' . $s)) ?>" target="_blank" rel="noopener" aria-label="<?= e($s) ?>"><?= icon($s, 15) ?></a>
        <?php endif; endforeach; ?>
      </div>
    </div>
    <?php foreach (menu('footer') as $col): ?>
      <div>
        <h4><?= e($col['title'] ?? '') ?></h4>
        <?php foreach ((array) ($col['links'] ?? []) as $l): if (trim((string) ($l['label'] ?? '')) === '') { continue; } ?>
          <a href="<?= e(menu_url((string) ($l['url'] ?? ''))) ?>"><?= e($l['label']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="container footer-bottom">
    <p><?= e(strtr(setting('footer_copyright', '{store} © {year}, All Rights Reserved'), ['{store}' => $storeName, '{year}' => date('Y')])) ?></p>
    <div class="pay-badges">
      <?php $methods = enabled_payment_methods(); ?>
      <?php if (isset($methods['stripe'])): ?><span>VISA</span><span>Mastercard</span><?php endif; ?>
      <?php if (isset($methods['paypal'])): ?><span>PayPal</span><?php endif; ?>
      <?php if (isset($methods['cod'])): ?><span>Cash on Delivery</span><?php endif; ?>
    </div>
  </div>
</footer>

<?php if (setting_on('whatsapp_float') && setting('whatsapp_number') !== ''): ?>
<a class="wa-float" href="https://wa.me/<?= e(preg_replace('/\D+/', '', setting('whatsapp_number'))) ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp"><?= icon('whatsapp', 28) ?></a>
<?php endif; ?>

<button class="to-top" id="to-top" type="button" aria-label="Back to top"><?= icon('chevron-up', 20) ?></button>

<?php include __DIR__ . '/popup.php'; ?>

<?= tracking_footer() ?>

<script>window.STORE = {base: <?= json_encode(BASE_PATH) ?>, csrf: <?= json_encode(csrf_token()) ?>,
  money: <?= json_encode(['symbol' => setting('currency_symbol', '$'), 'after' => setting('currency_position') === 'after', 'dec' => (int) setting('currency_decimals', '2'), 'trim' => setting('hide_zero_decimals', '1') === '1']) ?>};</script>
<script src="<?= asset('js/app.js') ?>?v=<?= APP_VERSION ?>"></script>
</body>
</html>
