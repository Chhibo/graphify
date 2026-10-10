<?php
/** Delivery options radio list (checkout). Also returned by shipping-options.php when the address changes. @var array $totals */
if ($totals['no_delivery']): ?>
  <div class="alert alert-error" style="margin:0">Sorry, we do not deliver to this address yet. Please check your country and city, or contact us.</div>
<?php endif;
foreach ($totals['shipping_methods'] as $m):
    $free = (float) $m['free_over'] > 0 && $totals['subtotal'] >= (float) $m['free_over'];
    $cost = $free || ($totals['coupon']['type'] ?? '') === 'free_shipping' ? 0.0 : (float) $m['cost'];
    if ((float) setting('free_shipping_over') > 0 && $totals['subtotal'] >= (float) setting('free_shipping_over')) {
        $cost = 0.0;
    } ?>
  <label class="pay-method">
    <input type="radio" name="shipping_method" value="<?= (int) $m['id'] ?>" data-cost="<?= e((string) $cost) ?>" <?= (int) $m['id'] === (int) ($totals['shipping_method']['id'] ?? 0) ? 'checked' : '' ?>>
    <span class="pm-body">
      <span class="pm-title"><?= e($m['name']) ?></span>
      <?php if ($m['description'] !== '' || (float) $m['free_over'] > 0): ?>
        <span class="pm-text"><?= e($m['description']) ?><?= (float) $m['free_over'] > 0 && !$free ? ($m['description'] !== '' ? ' · ' : '') . 'Free over ' . money($m['free_over']) : '' ?></span>
      <?php endif; ?>
    </span>
    <span class="pm-cost"><?= $cost > 0 ? money($cost) : 'Free' ?></span>
  </label>
<?php endforeach;
