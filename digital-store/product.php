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
    echo empty_state('This product does not exist. <a href="index.php">Back to the store</a>');
    page_footer();
    exit;
}

// Count one view per visitor session (admins don't count).
if (!is_admin() && empty($_SESSION['viewed'][$p['id']])) {
    $_SESSION['viewed'][$p['id']] = 1;
    db()->prepare('UPDATE products SET views = views + 1 WHERE id = ?')->execute([$p['id']]);
}

// Related: same category first, then the newest others.
$related = db()->prepare(product_select() . ' WHERE p.active = 1 AND p.id != ?
    ORDER BY (p.category_id IS ? OR p.category_id = ?) DESC, p.featured DESC, p.id DESC LIMIT 4');
$related->execute([$p['id'], $p['category_id'], $p['category_id']]);
$related = $related->fetchAll();

$url = base_url('product.php?id=' . (int)$p['id']);
$shareText = rawurlencode($p['title'] . ' (free download)');
$showCount = setting('show_downloads', '1') === '1';

page_header($p['title'], $p['category_slug'] ? 'cat:' . $p['category_slug'] : '', [
    'description' => $p['description'] !== '' ? $p['description'] : null,
    'image' => $p['image'] ? base_url('uploads/' . $p['image']) : null,
    'type' => 'product',
]);
?>
<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="index.php"><?= icon('home', 14) ?> Home</a> <?= icon('chevron-right', 14) ?>
  <?php if ($p['category_name']): ?>
    <a href="category.php?slug=<?= e(rawurlencode($p['category_slug'])) ?>"><?= e($p['category_name']) ?></a> <?= icon('chevron-right', 14) ?>
  <?php endif; ?>
  <span><?= e($p['title']) ?></span>
</nav>

<article class="product">
  <div class="product-media">
    <?php if ($p['image']): ?><img src="uploads/<?= e($p['image']) ?>" alt="<?= e($p['title']) ?>">
    <?php else: ?><div class="thumb-empty big"><?= icon('package', 64) ?></div><?php endif; ?>
    <?php if ($p['badge'] !== ''): ?><span class="ribbon big"><?= e($p['badge']) ?></span><?php endif; ?>
  </div>
  <div class="product-body">
    <?php if ($p['category_name']): ?>
      <a class="card-cat" href="category.php?slug=<?= e(rawurlencode($p['category_slug'])) ?>"><?= e($p['category_name']) ?></a>
    <?php endif; ?>
    <h1><?= e($p['title']) ?></h1>
    <div class="product-meta">
      <span class="free-pill big"><?= icon('gift', 15) ?> Free</span>
      <?php if ($showCount && (int)$p['downloads'] > 0): ?><span><?= icon('download', 15) ?> <?= short_number((int)$p['downloads']) ?> downloads</span><?php endif; ?>
      <span><?= icon('clock', 15) ?> Added <?= e(date('M j, Y', (int)$p['created_at'])) ?></span>
    </div>
    <div class="description"><?= render_content($p['description']) ?></div>

    <button class="btn btn-big btn-glow" id="unlock-btn" data-product="<?= (int)$p['id'] ?>"><?= icon('download', 20) ?> <?= e(setting_or('download_button', 'Download for free')) ?></button>

    <ul class="trust">
      <li><?= icon('shield', 18) ?> Private download link</li>
      <li><?= icon('zap', 18) ?> Unlocks automatically</li>
      <li><?= icon('gift', 18) ?> Free to download</li>
    </ul>

    <div class="share">
      <span>Share:</span>
      <a href="https://www.facebook.com/sharer/sharer.php?u=<?= e(rawurlencode($url)) ?>" target="_blank" rel="noopener" aria-label="Share on Facebook"><?= icon('facebook', 16) ?></a>
      <a href="https://twitter.com/intent/tweet?url=<?= e(rawurlencode($url)) ?>&text=<?= e($shareText) ?>" target="_blank" rel="noopener" aria-label="Share on X"><?= icon('x-brand', 16) ?></a>
      <a href="https://wa.me/?text=<?= e($shareText . '%20' . rawurlencode($url)) ?>" target="_blank" rel="noopener" aria-label="Share on WhatsApp"><?= icon('whatsapp', 16) ?></a>
      <a href="https://t.me/share/url?url=<?= e(rawurlencode($url)) ?>&text=<?= e($shareText) ?>" target="_blank" rel="noopener" aria-label="Share on Telegram"><?= icon('telegram', 16) ?></a>
      <button type="button" class="copy-link" data-url="<?= e($url) ?>" aria-label="Copy link"><?= icon('link', 16) ?> <span>Copy link</span></button>
    </div>
  </div>
</article>

<?php if ($related): ?>
<section class="home-section related">
  <?= section_head('You may also like') ?>
  <?= product_grid($related) ?>
</section>
<?php endif; ?>

<div class="locker" id="locker" hidden>
  <div class="locker-box" role="dialog" aria-modal="true" aria-labelledby="locker-title">
    <button class="locker-close" id="locker-close" aria-label="Close"><?= icon('x', 20) ?></button>
    <div class="locker-head">
      <?php if ($p['image']): ?><img src="uploads/<?= e($p['image']) ?>" alt=""><?php endif; ?>
      <div>
        <span class="locker-product"><?= e($p['title']) ?></span>
        <h2 id="locker-title"><?= e(setting_or('locker_title', 'Complete one offer to unlock your free download')) ?></h2>
      </div>
    </div>
    <ol class="locker-steps" id="locker-steps">
      <li class="active" data-step="1"><span>1</span> Pick an offer</li>
      <li data-step="2"><span>2</span> Complete it</li>
      <li data-step="3"><span>3</span> Download</li>
    </ol>
    <div id="locker-offers" class="offers"><div class="offer-skeleton"></div><div class="offer-skeleton"></div><div class="offer-skeleton"></div></div>
    <p class="locker-status" id="locker-status" hidden>
      <span class="spinner"></span> Waiting for the offer to be confirmed. This can take a few minutes. Keep this page open.
    </p>
    <p class="locker-note"><?= icon('shield', 14) ?> Offers are provided by our advertising partners. Read each offer's terms before you start.</p>
    <?php if (is_admin()): ?>
      <p class="admin-test">Admin only: <button class="btn btn-small" id="simulate-btn" data-csrf="<?= e(csrf_token()) ?>">Simulate completed offer</button></p>
    <?php endif; ?>
  </div>
</div>

<script>window.STORE_BASE = <?= json_encode(base_url()) ?>;</script>
<script src="assets/locker.js?v=3"></script>
<?php page_footer();
