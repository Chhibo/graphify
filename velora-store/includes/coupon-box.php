<?php
/**
 * Coupon field for the order summary. Uses its own form (linked with the form="" attribute)
 * so it also works inside the checkout form. Set $couponBack = 'checkout' on the checkout page.
 * @var array $totals
 */
$couponBack = $couponBack ?? 'cart';
?>
<?php if (empty($couponFormPrinted)): // the checkout page prints this form itself, outside its own form ?>
<form method="post" action="<?= url('cart.php') ?>" id="coupon-form"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($couponBack) ?>"></form>
<?php endif; ?>
<div class="coupon-box">
  <?php if ($totals['coupon']): ?>
    <div class="coupon-applied">
      <span><?= icon('check', 14) ?> <b><?= e($totals['coupon']['code']) ?></b> · <?= e(coupon_label($totals['coupon'])) ?></span>
      <button type="submit" form="coupon-form" name="action" value="coupon_remove" class="link-btn">Remove</button>
    </div>
  <?php else: ?>
    <?php if ($totals['coupon_error'] !== '' && !empty($_SESSION['coupon'])): ?><p class="coupon-error"><?= e($totals['coupon_error']) ?></p><?php endif; ?>
    <div class="coupon-row">
      <input type="text" name="coupon" form="coupon-form" placeholder="Coupon code" autocomplete="off" aria-label="Coupon code">
      <button class="btn btn-outline btn-sm" type="submit" form="coupon-form" name="action" value="coupon">Apply</button>
    </div>
  <?php endif; ?>
</div>
