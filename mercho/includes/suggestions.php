<?php
/**
 * "You may also like" box for the cart and checkout (Admin > Settings > Sales tools).
 * @var array $suggestions products, @var string $suggestBack 'cart' or 'checkout'
 */
if (!$suggestions) {
    return;
}
?>
<section class="upsell-box">
  <h3><?= $suggestBack === 'checkout' ? 'Add to your order' : 'You may also like' ?></h3>
  <div class="upsell-list">
    <?php foreach ($suggestions as $sp): ?>
      <div class="upsell-item">
        <a href="<?= e(product_url($sp)) ?>" class="ui-img"><img src="<?= e(img_url($sp['image'])) ?>" alt="" loading="lazy"></a>
        <div class="ui-info">
          <a href="<?= e(product_url($sp)) ?>"><?= e($sp['name']) ?></a>
          <b><?= money($sp['price']) ?></b>
          <?php if (deal_label($sp) !== ''): ?><small class="deal-tag"><?= e(deal_label($sp)) ?></small><?php endif; ?>
        </div>
        <?php if (product_quick_add($sp)): ?>
          <form method="post" action="<?= url('cart.php') ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= (int) $sp['id'] ?>">
            <input type="hidden" name="back" value="<?= e($suggestBack) ?>">
            <button class="btn btn-outline btn-sm" type="submit"><?= icon('plus', 14) ?> Add</button>
          </form>
        <?php else: ?>
          <a class="btn btn-outline btn-sm" href="<?= e(product_url($sp)) ?>">Choose</a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>
