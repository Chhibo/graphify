<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_installed();

$stmt = db()->prepare(product_select() . ' WHERE p.id = ? AND p.active = 1');
$stmt->execute([(int)($_GET['id'] ?? 0)]);
$p = $stmt->fetch();
if (!$p) {
    http_response_code(404);
    page_header('Not found');
    echo '<p class="empty">This product does not exist. <a href="index.php">Back to the store</a></p>';
    page_footer();
    exit;
}

page_header($p['title']);
?>
<article class="product">
  <div class="product-media">
    <?php if ($p['image']): ?><img src="uploads/<?= e($p['image']) ?>" alt="<?= e($p['title']) ?>"><?php endif; ?>
  </div>
  <div class="product-body">
    <?php if ($p['category_name']): ?>
      <a class="card-cat" href="category.php?slug=<?= e(rawurlencode($p['category_slug'])) ?>"><?= e($p['category_name']) ?></a>
    <?php endif; ?>
    <h1><?= e($p['title']) ?></h1>
    <div class="description"><?= nl2br(e($p['description'])) ?></div>
    <button class="btn btn-big" id="unlock-btn" data-product="<?= (int)$p['id'] ?>">Download for free</button>
  </div>
</article>

<div class="locker" id="locker" hidden>
  <div class="locker-box" role="dialog" aria-modal="true" aria-labelledby="locker-title">
    <button class="locker-close" id="locker-close" aria-label="Close">&times;</button>
    <h2 id="locker-title"><?= e(setting_or('locker_title', 'Complete one offer to unlock your free download')) ?></h2>
    <p class="locker-sub">Pick any offer below and complete it. Your download unlocks automatically when it's confirmed.</p>
    <div id="locker-offers" class="offers"><p class="loading">Loading offers…</p></div>
    <p class="locker-status" id="locker-status" hidden>
      <span class="spinner"></span> Waiting for the offer to be confirmed. This can take a few minutes. Keep this page open.
    </p>
    <?php if (is_admin()): ?>
      <p class="admin-test">Admin only: <button class="btn btn-small" id="simulate-btn" data-csrf="<?= e(csrf_token()) ?>">Simulate completed offer</button></p>
    <?php endif; ?>
  </div>
</div>

<script>window.STORE_BASE = <?= json_encode(base_url()) ?>;</script>
<script src="assets/locker.js"></script>
<?php page_footer();
