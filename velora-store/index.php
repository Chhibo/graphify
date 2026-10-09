<?php
require __DIR__ . '/includes/bootstrap.php';

$newArrivals = find_products(['flag' => 'is_new', 'sort' => 'manual', 'limit' => 4]);
$trending = find_products(['flag' => 'is_trending', 'sort' => 'manual', 'limit' => 12]);
$flash = find_products(['flag' => 'is_flash', 'sort' => 'manual', 'limit' => 4]);
$testimonials = q_all('SELECT * FROM testimonials WHERE active = 1 ORDER BY sort_order, id');
$instagram = find_products(['sort' => 'new', 'limit' => 6]);
$cats = array_slice(categories(), 0, 4);

include __DIR__ . '/includes/header.php';
?>

<!-- HERO -->
<section class="hero">
  <div class="container hero-grid">
    <div class="hero-text">
      <h1><?= e(setting('hero_title')) ?></h1>
      <p><?= e(setting('hero_text')) ?></p>
      <a class="btn btn-primary" href="<?= url('shop.php') ?>"><?= e(setting('hero_button', 'Shop Now')) ?></a>
      <div class="hero-stats">
        <?php for ($i = 1; $i <= 3; $i++): if (setting("stat{$i}_value") !== ''): ?>
          <div><strong><?= e(setting("stat{$i}_value")) ?></strong><span><?= e(setting("stat{$i}_label")) ?></span></div>
        <?php endif; endfor; ?>
      </div>
    </div>
    <div class="hero-media">
      <span class="sparkle s1">✦</span>
      <img src="<?= e(img_url(setting('hero_image'))) ?>" alt="<?= e(setting('store_name')) ?>">
      <span class="sparkle s2">✦</span>
    </div>
  </div>
</section>

<!-- BROWSE BY DRESS STYLE -->
<?php if ($cats): ?>
<section class="section">
  <div class="container">
    <div class="section-head left">
      <div>
        <h2>Browse by Dress Style</h2>
        <p>Discover collections curated for every occasion</p>
      </div>
      <a class="link-more" href="<?= url('shop.php') ?>">View All <?= icon('arrow-right', 14) ?></a>
    </div>
    <div class="style-grid">
      <?php foreach ($cats as $c): ?>
        <a class="style-card" href="<?= url('shop.php?category=' . (int) $c['id']) ?>">
          <img src="<?= e(img_url($c['image'])) ?>" alt="<?= e($c['name']) ?>" loading="lazy">
          <div class="style-info"><strong><?= e($c['name']) ?></strong><span><?= (int) $c['product_count'] ?> Products</span></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- FLASH SALE -->
<?php if (setting_on('flash_enabled') && $flash): ?>
<section class="section pt-0">
  <div class="container">
    <div class="flash">
      <div class="flash-text">
        <span class="chip-red"><?= e(setting('flash_badge', 'Flash Sale')) ?></span>
        <h2><?= e(setting('flash_title')) ?></h2>
        <p><?= e(setting('flash_text')) ?></p>
        <div class="countdown" data-countdown="<?= e(setting('flash_ends_at')) ?>">
          <div><b data-d>00</b><span>Days</span></div>
          <div><b data-h>00</b><span>Hours</span></div>
          <div><b data-m>00</b><span>Mins</span></div>
          <div><b data-s>00</b><span>Secs</span></div>
        </div>
      </div>
      <div class="flash-products">
        <?php foreach ($flash as $p): ?>
          <a class="flash-item" href="<?= e(product_url($p)) ?>">
            <img src="<?= e(img_url($p['image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
            <span class="fi-name"><?= e($p['name']) ?></span>
            <span class="fi-price"><?= money($p['price']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- NEW ARRIVALS -->
<?php if ($newArrivals): ?>
<section class="section">
  <div class="container">
    <div class="section-head"><h2>New Arrivals</h2></div>
    <div class="product-grid">
      <?php foreach ($newArrivals as $p) { include __DIR__ . '/includes/product-card.php'; } ?>
    </div>
    <div class="center"><a class="btn btn-outline" href="<?= url('shop.php?sort=new') ?>">View All</a></div>
  </div>
</section>
<?php endif; ?>

<!-- TRENDING NOW -->
<?php if ($trending): ?>
<section class="section">
  <div class="container">
    <div class="section-head left">
      <h2>Trending Now</h2>
      <div class="slider-nav" data-slider-nav="#trending">
        <button type="button" data-dir="-1" aria-label="Previous"><?= icon('chevron-left', 16) ?></button>
        <button type="button" data-dir="1" aria-label="Next"><?= icon('chevron-right', 16) ?></button>
      </div>
    </div>
    <div class="slider" id="trending">
      <?php foreach ($trending as $p) { include __DIR__ . '/includes/product-card.php'; } ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- BANNER -->
<?php if (setting_on('banner_enabled')): ?>
<section class="section-alt">
  <div class="container">
    <a class="promo-banner" href="<?= url('shop.php') ?>" style="background-image:url('<?= e(img_url(setting('banner_image'))) ?>')">
      <div class="promo-text">
        <?php if (setting('banner_badge') !== ''): ?><span class="chip-green"><?= e(setting('banner_badge')) ?></span><?php endif; ?>
        <h2><?= e(setting('banner_title')) ?></h2>
        <p><?= e(setting('banner_text')) ?></p>
        <span class="btn btn-white btn-sm"><?= e(setting('banner_button', 'Explore Collection')) ?></span>
      </div>
    </a>
  </div>
</section>
<?php endif; ?>

<!-- TESTIMONIALS -->
<?php if ($testimonials): ?>
<section class="section">
  <div class="container">
    <div class="section-head left">
      <h2>Our Happy Customers</h2>
      <div class="slider-nav" data-slider-nav="#reviews">
        <button type="button" data-dir="-1" aria-label="Previous"><?= icon('chevron-left', 16) ?></button>
        <button type="button" data-dir="1" aria-label="Next"><?= icon('chevron-right', 16) ?></button>
      </div>
    </div>
    <div class="slider reviews" id="reviews">
      <?php foreach ($testimonials as $t): ?>
        <figure class="review-card">
          <?= stars((float) $t['rating']) ?>
          <blockquote>“<?= e($t['text']) ?>”</blockquote>
          <figcaption><?= e($t['name']) ?> <span class="verified"><?= icon('check', 11) ?></span></figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- INSTAGRAM -->
<?php if ($instagram && setting('instagram_handle') !== ''): ?>
<section class="section insta">
  <div class="container">
    <div class="section-head">
      <div>
        <h2><?= e(setting('instagram_handle')) ?></h2>
        <p>Follow us on Instagram for daily style inspiration</p>
      </div>
    </div>
    <div class="insta-grid">
      <?php foreach ($instagram as $p): ?>
        <a href="<?= e(setting('instagram_url', '#')) ?>" target="_blank" rel="noopener"><img src="<?= e(img_url($p['image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy"><span><?= icon('instagram', 22) ?></span></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
