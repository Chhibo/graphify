<?php
/** @var array $p product row */
$off = discount_pct($p);
$isNew = (int) ($p['is_new'] ?? 0) === 1;
?>
<article class="product-card">
  <a class="pc-media" href="<?= e(product_url($p)) ?>">
    <img src="<?= e(img_url($p['image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
    <?php if ($off > 0): ?>
      <span class="pc-badge sale">-<?= $off ?>%</span>
    <?php elseif ($isNew): ?>
      <span class="pc-badge new">NEW</span>
    <?php endif; ?>
    <?php if ((int) $p['stock'] === 0): ?><span class="pc-out">Sold out</span><?php endif; ?>
  </a>
  <form class="pc-wish" action="<?= url('wishlist.php') ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
    <button type="submit" class="<?= in_wishlist((int) $p['id']) ? 'on' : '' ?>" aria-label="Add to wishlist"><?= icon('heart', 16) ?></button>
  </form>
  <div class="pc-body">
    <?php if (!empty($p['brand'])): ?><span class="pc-brand"><?= e($p['brand']) ?></span><?php endif; ?>
    <h3 class="pc-title"><a href="<?= e(product_url($p)) ?>"><?= e($p['name']) ?></a></h3>
    <div class="pc-rating"><?= stars((float) $p['rating']) ?> <span><?= e(number_format((float) $p['rating'], 1)) ?>/5</span></div>
    <div class="price">
      <strong><?= money($p['price']) ?></strong>
      <?php if ($off > 0): ?><del><?= money($p['old_price']) ?></del><span class="off">-<?= $off ?>%</span><?php endif; ?>
    </div>
  </div>
</article>
