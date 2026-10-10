<?php
require __DIR__ . '/includes/bootstrap.php';

if (!accounts_enabled()) {
    redirect('track.php');
}
$acct = require_customer('account.php');
$tab = in_array($_GET['tab'] ?? '', ['orders', 'profile', 'address', 'password'], true) ? $_GET['tab'] : 'dashboard';

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'profile') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter your name and a valid email.');
        } elseif ($email !== $acct['email'] && (int) q_val('SELECT COUNT(*) FROM customers WHERE email = ? AND id <> ?', [$email, $acct['id']]) > 0) {
            flash('error', 'Another account already uses this email.');
        } elseif ($email !== $acct['email'] && !password_verify((string) ($_POST['current_password'] ?? ''), $acct['password'])) {
            flash('error', 'To change your email, please enter your current password.');
        } else {
            db_update('customers', (int) $acct['id'], ['name' => mb_substr($name, 0, 150), 'email' => $email, 'phone' => mb_substr($phone, 0, 40)]);
            flash('success', 'Your details have been saved.');
        }
        redirect('account.php?tab=profile');
    }
    if ($action === 'address') {
        db_update('customers', (int) $acct['id'], [
            'address' => mb_substr(trim((string) ($_POST['address'] ?? '')), 0, 255),
            'city' => mb_substr(trim((string) ($_POST['city'] ?? '')), 0, 120),
            'state' => mb_substr(trim((string) ($_POST['state'] ?? '')), 0, 120),
            'zip' => mb_substr(trim((string) ($_POST['zip'] ?? '')), 0, 30),
            'country_code' => country_code((string) ($_POST['country'] ?? '')),
        ]);
        flash('success', 'Your address has been saved. It will be filled in automatically at checkout.');
        redirect('account.php?tab=address');
    }
    if ($action === 'password') {
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $acct['password'])) {
            flash('error', 'Your current password is not correct.');
        } elseif (strlen($new) < 8) {
            flash('error', 'Your new password must be at least 8 characters.');
        } elseif ($new !== (string) ($_POST['new_password2'] ?? '')) {
            flash('error', 'The two new passwords do not match.');
        } else {
            db_update('customers', (int) $acct['id'], ['password' => password_hash($new, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            flash('success', 'Your password has been changed.');
        }
        redirect('account.php?tab=password');
    }
    redirect('account.php');
}

$acct = current_customer(true);
$orders = q_all("SELECT * FROM orders WHERE customer_id = ? AND payment_status <> 'unpaid' ORDER BY id DESC", [$acct['id']]);
$order = null;
if (isset($_GET['order'])) {
    $order = q_one('SELECT * FROM orders WHERE order_number = ? AND customer_id = ?', [(string) $_GET['order'], $acct['id']]);
    $tab = 'orders';
}
$spent = array_sum(array_map(fn($o) => $o['status'] !== 'cancelled' ? (float) $o['total'] : 0, $orders));
$statusSteps = ['pending' => 'Order placed', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];

$pageTitle = 'My account';
include __DIR__ . '/includes/header.php';
?>
<div class="container">
  <nav class="breadcrumb"><a href="<?= url() ?>">Home</a> <span>›</span> <span>My account</span></nav>
  <h1 class="page-title">My account</h1>
  <div class="account-layout">
    <aside class="account-nav">
      <div class="account-user"><span class="avatar"><?= e(initials($acct['name'])) ?></span><div><b><?= e($acct['name']) ?></b><small><?= e($acct['email']) ?></small></div></div>
      <?php foreach (['dashboard' => ['Dashboard', 'user'], 'orders' => ['My orders', 'package'], 'profile' => ['Account details', 'user'], 'address' => ['Address', 'map-pin'], 'password' => ['Password', 'shield']] as $k => [$label, $ico]): ?>
        <a href="<?= url('account.php' . ($k === 'dashboard' ? '' : '?tab=' . $k)) ?>" class="<?= $tab === $k ? 'active' : '' ?>"><?= icon($ico, 17) ?> <?= e($label) ?></a>
      <?php endforeach; ?>
      <a href="<?= url('wishlist.php') ?>"><?= icon('heart', 17) ?> Wishlist</a>
      <form method="post" action="<?= url('logout.php') ?>"><?= csrf_field() ?><button type="submit"><?= icon('close', 17) ?> Log out</button></form>
    </aside>

    <section class="account-main">
      <?php if ($tab === 'dashboard'): ?>
        <p>Hello <b><?= e(explode(' ', $acct['name'])[0]) ?></b>! From your account you can see your orders, manage your address and change your password.</p>
        <div class="account-stats">
          <div><strong><?= count($orders) ?></strong><span>Orders</span></div>
          <div><strong><?= money($spent) ?></strong><span>Total spent</span></div>
          <div><strong><?= count(wishlist_ids()) ?></strong><span>Wishlist</span></div>
        </div>
        <?php if ($orders): ?>
          <h3>Latest order</h3>
          <?php $o = $orders[0]; ?>
          <a class="order-row" href="<?= url('account.php?order=' . rawurlencode($o['order_number'])) ?>">
            <b><?= e($o['order_number']) ?></b><span><?= e(date('M j, Y', strtotime($o['created_at']))) ?></span>
            <span class="status-pill st-<?= e($o['status']) ?>"><?= e(order_status_labels()[$o['status']] ?? $o['status']) ?></span><b><?= money($o['total']) ?></b>
          </a>
        <?php else: ?>
          <div class="empty small-empty"><p>You have not placed any orders yet.</p><a class="btn btn-primary" href="<?= url('shop.php') ?>">Start shopping</a></div>
        <?php endif; ?>

      <?php elseif ($tab === 'orders' && $order): ?>
        <p><a href="<?= url('account.php?tab=orders') ?>">← All orders</a></p>
        <h2 class="acc-title">Order <?= e($order['order_number']) ?></h2>
        <p class="muted">Placed on <?= e(date('F j, Y', strtotime($order['created_at']))) ?> · <?= e(payment_method_label($order['payment_method'])) ?></p>
        <?php if ($order['status'] === 'cancelled'): ?>
          <div class="alert alert-error">This order was cancelled.</div>
        <?php else: $reached = true; ?>
          <ol class="timeline">
            <?php foreach ($statusSteps as $key => $label): ?>
              <li class="<?= $reached ? 'done' : '' ?>"><?= e($label) ?></li>
              <?php if ($key === $order['status']) { $reached = false; } ?>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
        <?php if ($order['tracking_url'] !== ''): ?><a class="btn btn-primary" href="<?= e($order['tracking_url']) ?>" target="_blank" rel="noopener"><?= icon('truck', 18) ?> Track your package</a><?php endif; ?>
        <div class="order-box">
          <?php foreach (order_items((int) $order['id']) as $it): ?>
            <div class="sum-item">
              <span class="si-img"><img src="<?= e(img_url($it['image'])) ?>" alt=""><b><?= (int) $it['qty'] ?></b></span>
              <span class="si-name"><?= e($it['name']) ?><small><?= e(implode(' / ', array_filter([$it['size'], $it['color'], $it['options']]))) ?></small></span>
              <b><?= money($it['price'] * $it['qty']) ?></b>
            </div>
          <?php endforeach; ?>
          <div class="sum-row"><span>Subtotal</span><b><?= money($order['subtotal']) ?></b></div>
          <?php if ((float) $order['discount'] > 0): ?><div class="sum-row discount"><span>Discount (<?= e($order['coupon_code']) ?>)</span><b>-<?= money($order['discount']) ?></b></div><?php endif; ?>
          <div class="sum-row"><span>Delivery<?= $order['shipping_method'] !== '' ? ' (' . e($order['shipping_method']) . ')' : '' ?></span><b><?= (float) $order['shipping'] > 0 ? money($order['shipping']) : 'Free' ?></b></div>
          <div class="sum-row total"><span>Total</span><b><?= money($order['total']) ?></b></div>
          <div class="sum-row"><span>Deliver to</span><b><?= e(implode(', ', array_filter([$order['address'], $order['city'], $order['zip'], $order['country']], 'strlen'))) ?></b></div>
        </div>

      <?php elseif ($tab === 'orders'): ?>
        <h2 class="acc-title">My orders</h2>
        <?php if ($orders): ?>
          <div class="order-list">
            <?php foreach ($orders as $o): ?>
              <a class="order-row" href="<?= url('account.php?order=' . rawurlencode($o['order_number'])) ?>">
                <b><?= e($o['order_number']) ?></b><span><?= e(date('M j, Y', strtotime($o['created_at']))) ?></span>
                <span class="status-pill st-<?= e($o['status']) ?>"><?= e(order_status_labels()[$o['status']] ?? $o['status']) ?></span><b><?= money($o['total']) ?></b>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty small-empty"><p>You have not placed any orders yet.</p><a class="btn btn-primary" href="<?= url('shop.php') ?>">Start shopping</a></div>
        <?php endif; ?>

      <?php elseif ($tab === 'profile'): ?>
        <h2 class="acc-title">Account details</h2>
        <form method="post" class="acc-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="profile">
          <label>Full name<input name="name" value="<?= e($acct['name']) ?>" required></label>
          <div class="grid-2">
            <label>Email<input type="email" name="email" value="<?= e($acct['email']) ?>" required></label>
            <label>Phone (WhatsApp)<input type="tel" name="phone" value="<?= e($acct['phone']) ?>"></label>
          </div>
          <label>Current password <small class="muted">(only needed to change your email)</small><input type="password" name="current_password" autocomplete="current-password"></label>
          <button class="btn btn-primary" type="submit">Save changes</button>
        </form>

      <?php elseif ($tab === 'address'): ?>
        <h2 class="acc-title">Delivery address</h2>
        <form method="post" class="acc-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="address">
          <label>Address<input name="address" value="<?= e($acct['address']) ?>" placeholder="Street, building, apartment" autocomplete="street-address"></label>
          <div class="grid-2">
            <label>City<input name="city" value="<?= e($acct['city']) ?>" autocomplete="address-level2"></label>
            <label>State / Region<input name="state" value="<?= e($acct['state']) ?>" autocomplete="address-level1"></label>
          </div>
          <div class="grid-2">
            <label>Postal / ZIP code<input name="zip" value="<?= e($acct['zip']) ?>" autocomplete="postal-code"></label>
            <label>Country
              <select name="country"><option value="">Choose your country</option>
                <?php foreach (countries() as $code => $name): ?><option value="<?= e($code) ?>" <?= $acct['country_code'] === $code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
              </select>
            </label>
          </div>
          <button class="btn btn-primary" type="submit">Save address</button>
        </form>

      <?php else: ?>
        <h2 class="acc-title">Change password</h2>
        <form method="post" class="acc-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="password">
          <label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
          <div class="grid-2">
            <label>New password <small class="muted">(min. 8)</small><input type="password" name="new_password" required minlength="8" autocomplete="new-password"></label>
            <label>Repeat new password<input type="password" name="new_password2" required minlength="8" autocomplete="new-password"></label>
          </div>
          <button class="btn btn-primary" type="submit">Change password</button>
        </form>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
