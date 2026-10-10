<?php
/**
 * Subscribe / first-order popup. Admin > Settings > Popup.
 * Shown after a few seconds; after a visitor closes it, it stays hidden for the chosen number of days.
 * Add ?popup=1 to any store address to preview it.
 */
$popupPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$popupForce = isset($_GET['popup']);
if ((setting_on('popup_enabled') || $popupForce) && !in_array($popupPage, ['checkout.php', 'order-success.php', 'pay.php'], true)):
    $popupCoupon = null;
    if ((int) setting('popup_coupon_id') > 0) {
        $popupCoupon = q_one('SELECT * FROM coupons WHERE id = ?', [(int) setting('popup_coupon_id')]);
        if ($popupCoupon && coupon_error($popupCoupon, PHP_INT_MAX) !== '') {
            $popupCoupon = null; // expired, disabled or used up: hide the code
        }
    }
    // [text] in the title is shown in the main color, e.g. "Take [10%] Off Your First Order"
    $popupTitle = preg_replace('/\[(.+?)\]/', '<em>$1</em>', e(setting('popup_title', 'Take [10%] Off Your First Order')));
    $popupVersion = substr(md5(setting('popup_title') . setting('popup_text') . setting('popup_coupon_id') . setting('popup_image')), 0, 8);
    $popupLink = setting('popup_link', 'shop.php');
?>
<div class="popup-overlay" id="promo-popup" role="dialog" aria-modal="true" aria-label="<?= e(strip_tags(setting('popup_title'))) ?>"
     data-delay="<?= $popupForce ? 0 : e(setting('popup_delay', '4')) ?>" data-days="<?= e(setting('popup_days', '7')) ?>" data-version="<?= e($popupVersion) ?>"<?= $popupForce ? ' data-force' : '' ?>>
  <div class="popup">
    <div class="popup-media" style="background-image:url('<?= e(img_url(setting('popup_image'), 'assets/img/demo/popup.svg')) ?>')">
      <?php if (setting('popup_badge_value') !== ''): ?>
        <span class="popup-badge"><b><?= e(setting('popup_badge_value')) ?></b><small><?= e(setting('popup_badge_text', 'OFF')) ?></small></span>
      <?php endif; ?>
    </div>
    <div class="popup-body">
      <button class="popup-close" type="button" data-popup-close aria-label="Close"><?= icon('close', 18) ?></button>
      <?php if (setting('popup_subtitle') !== ''): ?><span class="popup-sub"><?= e(setting('popup_subtitle')) ?></span><?php endif; ?>
      <h2><?= $popupTitle ?></h2>
      <?php if (setting('popup_text') !== ''): ?><p><?= nl2br(e(setting('popup_text'))) ?></p><?php endif; ?>
      <?php if ($popupCoupon): ?>
        <div class="popup-code">
          <b><?= e($popupCoupon['code']) ?></b>
          <button type="button" data-copy="<?= e($popupCoupon['code']) ?>"><?= icon('copy', 14) ?> <span>COPY CODE</span></button>
        </div>
      <?php endif; ?>
      <form class="popup-form" method="post" action="<?= url('newsletter.php') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="from_popup" value="1">
        <label class="popup-input"><?= icon('mail', 16) ?><input type="email" name="email" placeholder="Email Address" required aria-label="Email address"></label>
        <button class="btn btn-primary" type="submit">SEND IT <?= icon('arrow-right', 16) ?></button>
      </form>
      <div class="popup-foot">
        <?php if (setting('popup_link_text') !== ''): ?>
          <a class="popup-link" href="<?= e(menu_url($popupLink)) ?>"><?= e(setting('popup_link_text')) ?> <?= icon('arrow-right', 14) ?></a>
        <?php endif; ?>
        <button type="button" class="link-btn" data-popup-close>No thanks, maybe later</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
