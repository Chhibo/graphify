<?php
require __DIR__ . '/includes/bootstrap.php';

$p = isset($_GET['slug']) ? find_product_by_slug((string) $_GET['slug']) : find_product((int) ($_GET['id'] ?? 0));
if (!$p) {
    http_response_code(404);
    $pageTitle = 'Product not found';
    include __DIR__ . '/includes/header.php';
    echo '<div class="container empty"><h3>Product not found</h3><a class="btn btn-primary" href="' . url('shop.php') . '">Back to shop</a></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$images = product_images($p) ?: [''];
$sizes = str_list($p['sizes']);
$colors = str_list($p['colors']);
$off = discount_pct($p);
$allVariants = product_variants((int) $p['id']);
// Sold out: product stock 0, or (with stock per size/color) every combination is at 0.
$soldOut = $allVariants ? !array_filter($allVariants, fn($v) => (int) $v['stock'] !== 0) : (int) $p['stock'] === 0;
$variantData = array_map(fn($v) => [
    'size' => $v['size'],
    'color' => $v['color'],
    'price' => money(variant_price($v, $p)),
    'amount' => variant_price($v, $p),
    'stock' => (int) $v['stock'],
    'image' => $v['image'] !== '' ? img_url($v['image']) : '',
], $allVariants);
$extraOptions = product_extra_options($p);
$infoRows = product_additional_info($p);
$reviews = setting_on('reviews_enabled') ? product_reviews((int) $p['id']) : [];
$shortDesc = trim((string) ($p['short_description'] ?? ''));
$related = find_products(['category_id' => (int) $p['category_id'], 'exclude' => (int) $p['id'], 'limit' => 4]);

$pageTitle = $p['name'];
track_event('ViewContent', ['value' => (float) $p['price'], 'items' => [track_item($p, (float) $p['price'])]]);
// SEO & share previews
$seoTitle = trim((string) ($p['meta_title'] ?? '')) !== '' ? $p['meta_title'] : '';
$canonical = abs_url(product_url($p));
$ogType = 'product';
$ogImage = $images[0];
$jsonLd = [[
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $p['name'],
    'image' => array_map(fn($i) => abs_url(img_url($i)), $images),
    'description' => excerpt(strip_tags((string) ($shortDesc !== '' ? $shortDesc : $p['description'])), 300),
    'sku' => 'P' . (int) $p['id'],
    'brand' => ['@type' => 'Brand', 'name' => $p['brand'] !== '' ? $p['brand'] : setting('store_name')],
    'offers' => [
        '@type' => 'Offer',
        'url' => $canonical,
        'priceCurrency' => strtoupper(setting('currency_code', 'USD')),
        'price' => number_format((float) $p['price'], 2, '.', ''),
        'availability' => $soldOut ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
        'itemCondition' => 'https://schema.org/NewCondition',
    ],
]];
if ((int) $p['reviews_count'] > 0) {
    $jsonLd[0]['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => number_format((float) $p['rating'], 1), 'reviewCount' => (int) $p['reviews_count']];
}
$metaDescription = trim((string) ($p['meta_description'] ?? '')) !== '' ? $p['meta_description'] : excerpt($shortDesc !== '' ? $shortDesc : (string) $p['description'], 155);
include __DIR__ . '/includes/header.php';
?>
<div class="container">
  <nav class="breadcrumb">
    <a href="<?= url() ?>">Home</a> <span>›</span> <a href="<?= url('shop.php') ?>">Shop</a>
    <?php $cat = find_category((int) $p['category_id']); $parentCat = $cat && !empty($cat['parent_id']) ? find_category((int) $cat['parent_id']) : null; ?>
    <?php if ($parentCat): ?><span>›</span> <a href="<?= category_url($parentCat) ?>"><?= e($parentCat['name']) ?></a><?php endif; ?>
    <?php if ($cat): ?><span>›</span> <a href="<?= category_url($cat) ?>"><?= e($cat['name']) ?></a><?php endif; ?>
    <span>›</span> <span><?= e($p['name']) ?></span>
  </nav>

  <div class="product-detail">
    <div class="gallery">
      <?php if (count($images) > 1): ?>
        <div class="thumbs">
          <?php foreach ($images as $i => $img): ?>
            <button type="button" class="<?= $i === 0 ? 'active' : '' ?>" data-thumb="<?= e(img_url($img)) ?>"><img src="<?= e(img_url($img)) ?>" alt=""></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="main-img"><img id="main-img" src="<?= e(img_url($images[0])) ?>" alt="<?= e($p['name']) ?>"></div>
    </div>

    <div class="pd-info">
      <?php if ($p['brand']): ?><span class="pc-brand"><?= e($p['brand']) ?></span><?php endif; ?>
      <h1><?= e($p['name']) ?></h1>
      <a class="pc-rating" href="#tab-reviews" data-open-tab="reviews"><?= stars((float) $p['rating']) ?> <span><?= e(number_format((float) $p['rating'], 1)) ?>/5 · <?= (int) $p['reviews_count'] ?> <?= (int) $p['reviews_count'] === 1 ? 'review' : 'reviews' ?></span></a>
      <div class="price big">
        <strong data-price><?= money($p['price']) ?></strong>
        <?php if ($off > 0): ?><del><?= money($p['old_price']) ?></del><span class="off">-<?= $off ?>%</span><?php endif; ?>
      </div>
      <?php if ($shortDesc !== ''): ?>
        <div class="pd-desc"><?= rich_text($shortDesc) ?></div>
      <?php elseif (trim((string) $p['description']) !== ''): ?>
        <div class="pd-desc"><p><?= e(excerpt((string) $p['description'], 220)) ?></p></div>
      <?php endif; ?>

      <form action="<?= url('cart.php') ?>" method="post" class="add-form" data-base-price="<?= e((string) (float) $p['price']) ?>"<?= $variantData ? " data-variants='" . e(json_encode($variantData)) . "'" : '' ?>>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">

        <?php if ($colors): ?>
          <div class="opt-group">
            <h4>Select Color</h4>
            <div class="opts">
              <?php foreach ($colors as $i => $c): ?>
                <label class="opt"><input type="radio" name="color" value="<?= e($c) ?>" <?= $i === 0 ? 'checked' : '' ?>><span><?= e($c) ?></span></label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($sizes): ?>
          <div class="opt-group">
            <h4>Choose Size</h4>
            <div class="opts">
              <?php foreach ($sizes as $i => $s): ?>
                <label class="opt"><input type="radio" name="size" value="<?= e($s) ?>" <?= $i === 0 ? 'checked' : '' ?>><span><?= e($s) ?></span></label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php foreach ($extraOptions as $o): ?>
          <div class="opt-group">
            <h4><?= e($o['name']) ?></h4>
            <div class="opts">
              <?php foreach ($o['values'] as $i => $v): ?>
                <label class="opt"><input type="radio" name="opt[<?= e($o['name']) ?>]" value="<?= e($v['label']) ?>" data-extra="<?= e((string) $v['price']) ?>" <?= $i === 0 ? 'checked' : '' ?>><span><?= e($v['label']) ?><?= $v['price'] > 0 ? ' <small>+' . money($v['price']) . '</small>' : '' ?></span></label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="buy-row">
          <div class="qty">
            <button type="button" data-qty="-1" aria-label="Decrease"><?= icon('minus', 16) ?></button>
            <input type="number" name="qty" value="1" min="1" max="<?= (int) $p['stock'] > 0 ? (int) $p['stock'] : 99 ?>">
            <button type="button" data-qty="1" aria-label="Increase"><?= icon('plus', 16) ?></button>
          </div>
          <?php if ($soldOut): ?>
            <button class="btn btn-primary grow" type="button" disabled>Sold out</button>
          <?php else: ?>
            <button class="btn btn-primary grow" type="submit" data-add-btn>Add to Cart</button>
          <?php endif; ?>
        </div>
        <p class="variant-msg" data-variant-msg hidden>This combination is not available.</p>
        <?php if (!$soldOut): ?>
          <button class="btn btn-outline btn-block" type="submit" name="buy_now" value="1" data-add-btn>Buy it now</button>
        <?php endif; ?>
      </form>

      <ul class="perks">
        <li><?= icon('truck', 18) ?> <?= (float) setting('free_shipping_over') > 0 ? 'Free shipping on orders over ' . money(setting('free_shipping_over')) : 'Fast delivery to your door' ?></li>
        <?php if (setting_on('pay_cod_enabled')): ?><li><?= icon('package', 18) ?> Cash on delivery available</li><?php endif; ?>
        <li><?= icon('shield', 18) ?> Secure checkout</li>
      </ul>
    </div>
  </div>

  <?php
    $hasDesc = trim(strip_tags((string) $p['description'], '<img>')) !== '';
    $showInfo = $infoRows || $sizes || $colors || $extraOptions;
    $firstTab = $hasDesc ? 'description' : ($showInfo ? 'info' : 'reviews');
  ?>
  <section class="pd-tabs" id="product-tabs">
    <div class="tab-nav" role="tablist">
      <?php if ($hasDesc): ?><button type="button" role="tab" data-tab-btn="description" class="<?= $firstTab === 'description' ? 'active' : '' ?>">Description</button><?php endif; ?>
      <?php if ($showInfo): ?><button type="button" role="tab" data-tab-btn="info" class="<?= $firstTab === 'info' ? 'active' : '' ?>">Additional Information</button><?php endif; ?>
      <?php if (setting_on('reviews_enabled')): ?><button type="button" role="tab" data-tab-btn="reviews" id="tab-reviews" class="<?= $firstTab === 'reviews' ? 'active' : '' ?>">Reviews (<?= count($reviews) ?>)</button><?php endif; ?>
    </div>

    <?php if ($hasDesc): ?>
      <div class="tab-pane" data-tab-pane="description" <?= $firstTab === 'description' ? '' : 'hidden' ?>><?= rich_text((string) $p['description']) ?></div>
    <?php endif; ?>

    <?php if ($showInfo): ?>
      <div class="tab-pane" data-tab-pane="info" <?= $firstTab === 'info' ? '' : 'hidden' ?>>
        <table class="info-table">
          <?php if ($colors): ?><tr><th>Color</th><td><?= e(implode(', ', $colors)) ?></td></tr><?php endif; ?>
          <?php if ($sizes): ?><tr><th>Size</th><td><?= e(implode(', ', $sizes)) ?></td></tr><?php endif; ?>
          <?php foreach ($extraOptions as $o): ?><tr><th><?= e($o['name']) ?></th><td><?= e(implode(', ', array_column($o['values'], 'label'))) ?></td></tr><?php endforeach; ?>
          <?php foreach ($infoRows as [$k, $v]): ?><tr><th><?= e($k) ?></th><td><?= e($v) ?></td></tr><?php endforeach; ?>
        </table>
      </div>
    <?php endif; ?>

    <?php if (setting_on('reviews_enabled')): ?>
      <div class="tab-pane" data-tab-pane="reviews" <?= $firstTab === 'reviews' ? '' : 'hidden' ?>>
        <div class="reviews-grid">
          <div class="review-list">
            <?php foreach ($reviews as $r): ?>
              <div class="review-item">
                <span class="avatar"><?= e(initials($r['name'])) ?></span>
                <div class="review-body">
                  <div class="review-top">
                    <div><b><?= e($r['name']) ?></b><small><?= e(date('d F, Y', strtotime($r['created_at']))) ?></small></div>
                    <?= stars((float) $r['rating']) ?>
                  </div>
                  <p><?= nl2br(e($r['comment'])) ?></p>
                </div>
              </div>
            <?php endforeach; ?>
            <?php if (!$reviews): ?><p class="muted">There are no reviews yet. Be the first to review “<?= e($p['name']) ?>”.</p><?php endif; ?>
          </div>
          <form class="review-form" method="post" action="<?= url('review.php') ?>" id="review-form">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
            <h3>Review this product</h3>
            <p class="muted">Your email address will not be published. Required fields are marked *</p>
            <div class="rate-input">
              <span>Your rating * :</span>
              <span class="star-pick">
                <?php for ($i = 5; $i >= 1; $i--): ?><input type="radio" id="rate<?= $i ?>" name="rating" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>><label for="rate<?= $i ?>" title="<?= $i ?> stars">★</label><?php endfor; ?>
              </span>
            </div>
            <input name="name" placeholder="Your Name *" required maxlength="120" data-remember="name">
            <input name="email" type="email" placeholder="Your Email *" required maxlength="190" data-remember="email">
            <textarea name="comment" rows="5" placeholder="Comment *" required maxlength="2000"></textarea>
            <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
            <label class="check"><input type="checkbox" data-remember-me> Save my name and email in this browser for the next time I comment.</label>
            <button class="btn btn-primary" type="submit">Submit</button>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($related): ?>
    <section class="section">
      <div class="section-head"><h2>You Might Also Like</h2></div>
      <div class="product-grid">
        <?php foreach ($related as $p) { include __DIR__ . '/includes/product-card.php'; } ?>
      </div>
    </section>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
