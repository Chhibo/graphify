<?php
$storeName = setting('store_name', 'VELORA');
$pageLink = fn(string $slug) => url('page.php?slug=' . $slug);
?>
</main>

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
    <div>
      <h4>Company</h4>
      <a href="<?= $pageLink('about') ?>">About</a>
      <a href="<?= $pageLink('contact') ?>">Contact</a>
      <a href="<?= $pageLink('faq') ?>">FAQ</a>
      <a href="<?= url('blog.php') ?>">Blog</a>
    </div>
    <div>
      <h4>Help</h4>
      <a href="<?= $pageLink('customer-support') ?>">Customer Support</a>
      <a href="<?= $pageLink('delivery-details') ?>">Delivery Details</a>
      <a href="<?= $pageLink('terms') ?>">Terms &amp; Conditions</a>
      <a href="<?= $pageLink('privacy') ?>">Privacy Policy</a>
    </div>
    <div>
      <h4>FAQ</h4>
      <a href="<?= url('track.php') ?>">Track Order</a>
      <a href="<?= url('cart.php') ?>">My Cart</a>
      <a href="<?= url('wishlist.php') ?>">Wishlist</a>
      <a href="<?= url('checkout.php') ?>">Payments</a>
    </div>
    <div>
      <h4>Resources</h4>
      <a href="<?= url('shop.php?sort=new') ?>">New Arrivals</a>
      <a href="<?= url('shop.php?sale=1') ?>">On Sale</a>
      <a href="<?= url('blog.php') ?>">Style Tips</a>
      <a href="<?= url('shop.php') ?>">All Products</a>
    </div>
  </div>
  <div class="container footer-bottom">
    <p><?= e($storeName) ?> © <?= date('Y') ?>, All Rights Reserved</p>
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

<script>window.STORE = {base: <?= json_encode(BASE_PATH) ?>, csrf: <?= json_encode(csrf_token()) ?>};</script>
<script src="<?= asset('js/app.js') ?>?v=<?= APP_VERSION ?>"></script>
</body>
</html>
