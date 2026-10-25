<?php
/** @var array $l  listing row from listing_select() */
$favs = favorite_ids();
$isFav = isset($favs[(int) $l['id']]);
?>
<article class="listing-card<?= !empty($l['featured']) ? ' is-featured' : '' ?>">
  <a class="lc-media" href="<?= e(listing_url($l)) ?>" tabindex="-1" aria-hidden="true">
    <?php if (!empty($l['cover'])): ?>
      <img src="<?= e(upload_url($l['cover'])) ?>" alt="" loading="lazy" decoding="async">
    <?php else: ?>
      <span class="lc-placeholder"><?= icon($l['category_icon'] ?: 'image') ?></span>
    <?php endif; ?>
    <?php if (!empty($l['featured'])): ?><span class="chip chip-featured"><?= icon('zap') ?><?= t('Featured') ?></span><?php endif; ?>
    <?php if (($l['status'] ?? '') === 'sold'): ?><span class="chip chip-sold"><?= t('Sold') ?></span><?php elseif (has_price_drop($l)): ?><span class="chip chip-drop"><?= icon('trending-down') ?><?= t('Price dropped') ?></span><?php endif; ?>
    <?php if (!empty($l['video_url'])): ?><span class="chip chip-video" title="<?= t('Has a video') ?>"><?= icon('play') ?></span><?php endif; ?>
  </a>
  <button class="fav-btn<?= $isFav ? ' on' : '' ?>" type="button" data-fav="<?= (int) $l['id'] ?>" aria-pressed="<?= $isFav ? 'true' : 'false' ?>" aria-label="<?= t('Save ad') ?>"><?= icon('heart') ?></button>
  <div class="lc-body">
    <div class="lc-price"><?= e(listing_price($l)) ?><?php if (has_price_drop($l)): ?><s class="was-price"><?= e(money($l['previous_price'])) ?></s><?php endif; ?><?php if (($l['price_type'] ?? '') === 'negotiable'): ?><small><?= t('Negotiable') ?></small><?php endif; ?></div>
    <h3 class="lc-title"><a href="<?= e(listing_url($l)) ?>"><?= e($l['title']) ?></a></h3>
    <p class="lc-excerpt"><?= e(excerpt((string) $l['description'], 110)) ?></p>
    <div class="lc-meta">
      <span><?= icon('map-pin') ?><?= e($l['location'] ?: '—') ?></span>
      <span><?= icon('clock') ?><?= e(time_ago($l['created_at'])) ?></span>
    </div>
    <?php if (!empty($l['shop_name'])): ?><div class="lc-shop"><?= icon('shop') ?><span><?= e($l['shop_name']) ?></span></div><?php endif; ?>
  </div>
</article>
