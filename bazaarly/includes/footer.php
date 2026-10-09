<?php
$footerPages = q_all('SELECT title, slug FROM pages WHERE in_footer = 1 ORDER BY id');
$footerCats = array_slice(category_tree(), 0, 6, true);
$socials = ['facebook', 'twitter', 'instagram', 'youtube', 'linkedin'];
?>
</main>
<?= ad_slot('footer') ? '<div class="container">' . ad_slot('footer') . '</div>' : '' ?>
<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <a class="logo logo-light" href="<?= e(url()) ?>">
        <?php if (setting('logo')): ?><img src="<?= e(upload_url(setting('logo'))) ?>" alt="<?= e(setting('site_name')) ?>">
        <?php else: ?><span class="logo-mark"><?= icon('store') ?></span><span class="logo-text"><?= e(setting('site_name')) ?></span><?php endif; ?>
      </a>
      <p><?= e(setting('footer_about')) ?></p>
      <ul class="footer-contact">
        <?php if (setting('contact_address')): ?><li><?= icon('map-pin') ?><?= e(setting('contact_address')) ?></li><?php endif; ?>
        <?php if (setting('contact_phone')): ?><li><?= icon('phone') ?><?= e(setting('contact_phone')) ?></li><?php endif; ?>
        <?php if (setting('contact_email')): ?><li><?= icon('mail') ?><a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></li><?php endif; ?>
      </ul>
      <div class="socials">
        <?php foreach ($socials as $s): if (setting('social_' . $s)): ?>
          <a href="<?= e(setting('social_' . $s)) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($s)) ?>"><?= icon($s) ?></a>
        <?php endif; endforeach; ?>
      </div>
    </div>
    <div>
      <h4>Categories</h4>
      <ul>
        <?php foreach ($footerCats as $id => $c): ?>
          <li><a href="<?= e(url('listings.php?category=' . $id)) ?>"><?= e($c['name']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div>
      <h4>Information</h4>
      <ul>
        <?php foreach ($footerPages as $p): ?>
          <li><a href="<?= e(url('page.php?slug=' . $p['slug'])) ?>"><?= e($p['title']) ?></a></li>
        <?php endforeach; ?>
        <li><a href="<?= e(url('contact.php')) ?>">Contact us</a></li>
      </ul>
    </div>
    <div>
      <h4>Your account</h4>
      <ul>
        <li><a href="<?= e(url('dashboard/edit.php')) ?>">Post a free ad</a></li>
        <li><a href="<?= e(url('dashboard/listings.php')) ?>">My ads</a></li>
        <li><a href="<?= e(url('dashboard/favorites.php')) ?>">Saved ads</a></li>
        <li><a href="<?= e(url('dashboard/messages.php')) ?>">Messages</a></li>
      </ul>
    </div>
  </div>
  <div class="container footer-bottom">
    <span><?= e(copyright_text()) ?></span>
    <a href="#main" class="to-top" aria-label="Back to top"><?= icon('chevron-down') ?></a>
  </div>
</footer>
<?php if (setting('cookie_notice') === '1'): ?>
<div class="cookie-bar" id="cookieBar" hidden>
  <p><?= e(setting('cookie_text')) ?> <a href="<?= e(url('page.php?slug=privacy')) ?>">Learn more</a></p>
  <button class="btn btn-primary btn-sm" type="button" data-cookie-accept>Got it</button>
</div>
<?php endif; ?>
<script>window.BZR={base:<?= json_encode(BASE_PATH) ?>,csrf:<?= json_encode(csrf_token()) ?>};</script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
