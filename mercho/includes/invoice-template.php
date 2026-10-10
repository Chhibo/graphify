<?php
/**
 * Printable invoice / packing slip (admin/invoice.php and invoice.php for customers).
 * @var array $order
 * @var string $docType 'invoice' or 'slip'
 */
$items = order_items((int) $order['id']);
$isSlip = $docType === 'slip';
$title = ($isSlip ? 'Packing slip' : 'Invoice') . ' ' . $order['order_number'];
$color = preg_match('/^#[0-9a-fA-F]{6}$/', setting('theme_color')) ? setting('theme_color') : '#e03a3e';
$addr = implode('<br>', array_map('e', array_filter([$order['address'], trim($order['zip'] . ' ' . $order['city']), $order['state'], $order['country']], 'strlen')));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?></title>
<style>
  * { box-sizing: border-box; }
  body { margin: 0; background: #f2f2f2; font: 14px/1.5 Arial, Helvetica, sans-serif; color: #222; }
  .doc { max-width: 820px; margin: 24px auto; background: #fff; padding: 44px; box-shadow: 0 6px 24px rgba(0,0,0,.08); }
  .top { display: flex; justify-content: space-between; gap: 20px; border-bottom: 3px solid <?= $color ?>; padding-bottom: 18px; margin-bottom: 26px; }
  .brand { font-size: 26px; font-weight: bold; } .brand img { max-height: 50px; }
  .muted { color: #777; } h1 { margin: 0 0 4px; font-size: 24px; color: <?= $color ?>; text-transform: uppercase; letter-spacing: 1px; }
  .cols { display: flex; gap: 30px; margin-bottom: 26px; } .cols > div { flex: 1; }
  .cols h3 { margin: 0 0 6px; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #777; }
  table { width: 100%; border-collapse: collapse; } th { text-align: left; font-size: 12px; text-transform: uppercase; color: #777; border-bottom: 2px solid #222; padding: 8px 6px; }
  td { padding: 10px 6px; border-bottom: 1px solid #e5e5e5; vertical-align: top; } .r { text-align: right; }
  .totals { margin: 16px 0 0 auto; width: 300px; } .totals div { display: flex; justify-content: space-between; padding: 5px 0; }
  .totals .grand { border-top: 2px solid #222; margin-top: 6px; padding-top: 10px; font-size: 18px; font-weight: bold; }
  .check { width: 18px; height: 18px; border: 2px solid #222; display: inline-block; }
  .note { margin-top: 26px; padding: 12px 14px; background: #fafafa; border-left: 3px solid <?= $color ?>; }
  .foot { margin-top: 34px; text-align: center; color: #888; font-size: 12px; }
  .bar { max-width: 820px; margin: 16px auto 0; display: flex; gap: 8px; justify-content: flex-end; }
  .bar button, .bar a { background: <?= $color ?>; color: #fff; border: 0; padding: 10px 18px; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 14px; }
  .bar a { background: #555; }
  @media print { body { background: #fff; } .doc { box-shadow: none; margin: 0; padding: 0; max-width: none; } .bar { display: none; } }
  @media (max-width: 600px) { .doc { padding: 20px; } .cols, .top { flex-direction: column; } .totals { width: 100%; } }
</style>
</head>
<body>
<div class="bar">
  <?php if (!empty($otherDocUrl)): ?><a href="<?= e($otherDocUrl) ?>"><?= $isSlip ? 'Invoice' : 'Packing slip' ?></a><?php endif; ?>
  <button type="button" onclick="window.print()">Print / Save as PDF</button>
</div>
<div class="doc">
  <div class="top">
    <div>
      <div class="brand"><?= setting('logo') !== '' ? '<img src="' . e(img_url(setting('logo'))) . '" alt="">' : e(setting('store_name')) ?></div>
      <div class="muted"><?= nl2br(e(setting('contact_address'))) ?><?= setting('contact_phone') !== '' ? '<br>' . e(setting('contact_phone')) : '' ?><?= setting('store_email') !== '' ? '<br>' . e(setting('store_email')) : '' ?></div>
    </div>
    <div class="r">
      <h1><?= $isSlip ? 'Packing slip' : 'Invoice' ?></h1>
      <div><b><?= e($order['order_number']) ?></b></div>
      <div class="muted">Date: <?= e(date('F j, Y', strtotime($order['created_at']))) ?></div>
      <?php if (!$isSlip): ?><div class="muted">Payment: <?= e(payment_method_label($order['payment_method'])) ?> - <?= e($order['payment_status'] === 'paid' ? 'Paid' : ($order['payment_status'] === 'cod' ? 'To pay on delivery' : ucfirst($order['payment_status']))) ?></div><?php endif; ?>
    </div>
  </div>
  <div class="cols">
    <div><h3><?= $isSlip ? 'Ship to' : 'Bill to' ?></h3><b><?= e($order['customer_name']) ?></b><br><?= $addr ?><br><?= e($order['phone']) ?><?= $order['email'] !== '' ? '<br>' . e($order['email']) : '' ?></div>
    <div><h3>Delivery</h3><?= e($order['shipping_method'] !== '' ? $order['shipping_method'] : 'Standard') ?><?= $order['tracking_url'] !== '' ? '<br><small class="muted">' . e($order['tracking_url']) . '</small>' : '' ?></div>
  </div>
  <table>
    <thead><tr><?php if ($isSlip): ?><th style="width:40px"></th><?php endif; ?><th>Product</th><th class="r">Qty</th><?php if (!$isSlip): ?><th class="r">Price</th><th class="r">Total</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($items as $it): ?>
      <tr>
        <?php if ($isSlip): ?><td><span class="check"></span></td><?php endif; ?>
        <td><b><?= e($it['name']) ?></b><?php $v = implode(' / ', array_filter([$it['size'], $it['color'], $it['options']])); ?><?= $v !== '' ? '<br><span class="muted">' . e($v) . '</span>' : '' ?></td>
        <td class="r"><?= (int) $it['qty'] ?></td>
        <?php if (!$isSlip): ?><td class="r"><?= money($it['price']) ?></td><td class="r"><?= money($it['price'] * $it['qty']) ?></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!$isSlip): ?>
    <div class="totals">
      <div><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
      <?php if ((float) $order['discount'] > 0): ?><div><span>Discount (<?= e($order['coupon_code']) ?>)</span><span>-<?= money($order['discount']) ?></span></div><?php endif; ?>
      <div><span>Delivery</span><span><?= (float) $order['shipping'] > 0 ? money($order['shipping']) : 'Free' ?></span></div>
      <div class="grand"><span>Total</span><span><?= money($order['total']) ?></span></div>
    </div>
  <?php endif; ?>
  <?php if (trim((string) $order['notes']) !== ''): ?><div class="note"><b>Notes:</b> <?= nl2br(e($order['notes'])) ?></div><?php endif; ?>
  <div class="foot">Thank you for shopping at <?= e(setting('store_name')) ?>! · <?= e(full_url()) ?></div>
</div>
</body>
</html>
