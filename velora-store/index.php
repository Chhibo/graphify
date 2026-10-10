<?php
require __DIR__ . '/includes/bootstrap.php';

$newArrivals = find_products(['flag' => 'is_new', 'sort' => 'manual', 'limit' => 4]);
$trending = find_products(['flag' => 'is_trending', 'sort' => 'manual', 'limit' => 12]);
$deals = find_products(['flag' => 'is_flash', 'sort' => 'manual', 'limit' => 2]);
$totalProducts = find_products([], true);
$testimonials = q_all('SELECT * FROM testimonials WHERE active = 1 ORDER BY sort_order, id');
$instagram = find_products(['sort' => 'new', 'limit' => 6]);
$cats = category_tree();

$canonical = abs_url(url());
$jsonLd = [
    ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => setting('store_name'), 'url' => abs_url(url()),
        'logo' => setting('logo') !== '' ? abs_url(img_url(setting('logo'))) : null,
        'sameAs' => array_values(array_filter([setting('social_facebook'), setting('social_instagram'), setting('social_twitter')], fn($u) => preg_match('#^https?://#', $u)))],
    ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => setting('store_name'), 'url' => abs_url(url()),
        'potentialAction' => ['@type' => 'SearchAction', 'target' => abs_url(url('shop.php')) . '?q={search_term_string}', 'query-input' => 'required name=search_term_string']],
];
include __DIR__ . '/includes/header.php';
?>

<!-- HERO -->
<section class="hero">
  <div class="container hero-grid">
    <div class="hero-text">
      <?php if (setting('hero_subtitle') !== ''): ?><span class="hero-sub"><?= e(setting('hero_subtitle')) ?></span><?php endif; ?>
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

<!-- BEST FOR YOUR CATEGORIES -->
<?php if (setting_on('categories_enabled') && $cats): ?>
<section class="section">
  <div class="container">
    <div class="section-head left with-line">
      <div>
        <h2><?= e(setting('categories_title', 'Best For Your Categories')) ?></h2>
        <p><?= count($cats) ?> categories belonging to a total <?= number_format($totalProducts) ?> products</p>
      </div>
      <div class="slider-nav round" data-slider-nav="#cat-slider">
        <button type="button" data-dir="-1" aria-label="Previous"><?= icon('chevron-left', 18) ?></button>
        <button type="button" data-dir="1" aria-label="Next"><?= icon('chevron-right', 18) ?></button>
      </div>
    </div>
    <div class="slider cat-slider" id="cat-slider" style="--cols: <?= max(4, min(6, count($cats))) ?>">
      <?php foreach ($cats as $c): ?>
        <a class="cat-card" href="<?= category_url($c) ?>">
          <span class="cat-img"><img src="<?= e(img_url($c['image'])) ?>" alt="<?= e($c['name']) ?>" loading="lazy"></span>
          <strong><?= e($c['name']) ?></strong>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- DEAL OF THE DAYS -->
<?php if (setting_on('flash_enabled') && $deals): ?>
<section class="section pt-0">
  <div class="container">
    <div class="deal">
      <svg class="deal-curve" viewBox="0 0 600 500" preserveAspectRatio="none" aria-hidden="true"><path d="M-20 140 C 200 160, 260 260, 230 520 M -20 520 C 200 420, 420 380, 640 300" fill="none" stroke="currentColor" stroke-width="34"/></svg>
      <div class="deal-text">
        <h2><?= e(setting('deal_title', 'Deal of the Days')) ?></h2>
        <p><?= e(setting('deal_text')) ?></p>
        <?php $dealEnd = strtotime(setting('flash_ends_at')); ?>
        <?php if ($dealEnd): ?>
          <div class="deal-expire">
            <span class="deal-icon"><?= icon('package', 22) ?></span>
            <span>Limited time offer. The deal will expire on <b><?= e(date('F j, Y', $dealEnd)) ?></b>
              <span class="deal-countdown" data-countdown="<?= e(setting('flash_ends_at')) ?>"><b data-d>00</b>d <b data-h>00</b>h <b data-m>00</b>m <b data-s>00</b>s</span></span>
          </div>
        <?php endif; ?>
        <a class="btn btn-outline-primary" href="<?= url('shop.php?sale=1') ?>"><?= e(setting('deal_button', 'View All Collections')) ?></a>
      </div>
      <div class="deal-products">
        <?php foreach ($deals as $p): $off = discount_pct($p); ?>
          <a class="deal-card" href="<?= e(product_url($p)) ?>">
            <span class="deal-media">
              <img src="<?= e(img_url($p['image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
              <span class="tag-square"><?= $off > 0 ? '-' . $off . '%' : 'New' ?></span>
            </span>
            <span class="deal-body">
              <?php if ($p['brand']): ?><small><?= e($p['brand']) ?></small><?php endif; ?>
              <strong><?= e($p['name']) ?></strong>
              <span class="pc-rating red"><?= stars((float) $p['rating']) ?> <span>(<?= (int) $p['reviews_count'] ?> Reviews)</span></span>
              <span class="deal-price"><?php if ($off > 0): ?><del><?= money($p['old_price']) ?></del><?php endif; ?> <b><?= money($p['price']) ?></b></span>
            </span>
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
