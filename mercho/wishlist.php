<?php
require __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    verify_csrf();
    $id = (int) ($_POST['product_id'] ?? 0);
    $list = wishlist_ids();
    if (in_array($id, $list, true)) {
        $list = array_values(array_diff($list, [$id]));
    } elseif (find_product($id)) {
        $list[] = $id;
    }
    $_SESSION['wishlist'] = $list;
    $back = $_SERVER['HTTP_REFERER'] ?? '';
    // Only redirect back to pages of this site.
    if ($back !== '' && parse_url($back, PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? '')) {
        header('Location: ' . $back);
        exit;
    }
    redirect('wishlist.php');
}

$ids = wishlist_ids();
$products = [];
if ($ids) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $products = q_all("SELECT * FROM products WHERE active = 1 AND id IN ($ph)", $ids);
}
$pageTitle = 'Wishlist';
include __DIR__ . '/includes/header.php';
?>
<div class="container">
  <nav class="breadcrumb"><a href="<?= url() ?>">Home</a> <span>›</span> <span>Wishlist</span></nav>
  <h1 class="page-title">Your Wishlist</h1>
  <?php if ($products): ?>
    <div class="product-grid">
      <?php foreach ($products as $p) { include __DIR__ . '/includes/product-card.php'; } ?>
    </div>
  <?php else: ?>
    <div class="empty"><?= icon('heart', 44) ?><h3>Your wishlist is empty</h3><p>Tap the heart on any product to save it here.</p><a class="btn btn-primary" href="<?= url('shop.php') ?>">Browse products</a></div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
