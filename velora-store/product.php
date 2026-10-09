<?php
require __DIR__ . '/includes/bootstrap.php';

$p = find_product((int) ($_GET['id'] ?? 0));
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
$soldOut = (int) $p['stock'] === 0;
$related = find_products(['category_id' => (int) $p['category_id'], 'exclude' => (int) $p['id'], 'limit' => 4]);

$pageTitle = $p['name'];
$metaDescription = excerpt((string) $p['description'], 155);
include __DIR__ . '/includes/header.php';
?>
<div class="container">
  <nav class="breadcrumb">
    <a href="<?= url() ?>">Home</a> <span>›</span> <a href="<?= url('shop.php') ?>">Shop</a>
    <?php if ($p['category_name']): ?><span>›</span> <a href="<?= url('shop.php?category=' . (int) $p['category_id']) ?>"><?= e($p['category_name']) ?></a><?php endif; ?>
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
      <div class="pc-rating"><?= stars((float) $p['rating']) ?> <span><?= e(number_format((float) $p['rating'], 1)) ?>/5 · <?= (int) $p['reviews_count'] ?> reviews</span></div>
      <div class="price big">
        <strong><?= money($p['price']) ?></strong>
        <?php if ($off > 0): ?><del><?= money($p['old_price']) ?></del><span class="off">-<?= $off ?>%</span><?php endif; ?>
      </div>
      <div class="pd-desc"><?= nl2br(e((string) $p['description'])) ?></div>

      <form action="<?= url('cart.php') ?>" method="post" class="add-form">
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

        <div class="buy-row">
          <div class="qty">
            <button type="button" data-qty="-1" aria-label="Decrease"><?= icon('minus', 16) ?></button>
            <input type="number" name="qty" value="1" min="1" max="<?= (int) $p['stock'] > 0 ? (int) $p['stock'] : 99 ?>">
            <button type="button" data-qty="1" aria-label="Increase"><?= icon('plus', 16) ?></button>
          </div>
          <?php if ($soldOut): ?>
            <button class="btn btn-primary grow" type="button" disabled>Sold out</button>
          <?php else: ?>
            <button class="btn btn-primary grow" type="submit">Add to Cart</button>
          <?php endif; ?>
        </div>
        <?php if (!$soldOut): ?>
          <button class="btn btn-outline btn-block" type="submit" name="buy_now" value="1">Buy it now</button>
        <?php endif; ?>
      </form>

      <ul class="perks">
        <li><?= icon('truck', 18) ?> <?= (float) setting('free_shipping_over') > 0 ? 'Free shipping on orders over ' . money(setting('free_shipping_over')) : 'Fast delivery to your door' ?></li>
        <?php if (setting_on('pay_cod_enabled')): ?><li><?= icon('package', 18) ?> Cash on delivery available</li><?php endif; ?>
        <li><?= icon('shield', 18) ?> Secure checkout</li>
      </ul>
    </div>
  </div>

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
