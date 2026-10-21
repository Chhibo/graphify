<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_installed();

// Found by number (product.php?id=12) or by web address (/product/my-product).
if (isset($_GET['slug'])) {
    $stmt = db()->prepare(product_select() . ' WHERE p.slug = ? AND (p.active = 1 OR ?)');
    $stmt->execute([(string)$_GET['slug'], is_admin() ? 1 : 0]);
} else {
    $stmt = db()->prepare(product_select() . ' WHERE p.id = ? AND (p.active = 1 OR ?)');
    $stmt->execute([(int)($_GET['id'] ?? 0), is_admin() ? 1 : 0]);
}
$p = $stmt->fetch();
if (!$p) {
    http_response_code(404);
    page_header('Not found');
    echo empty_state('This product does not exist. <a href="' . e(base_url()) . '">Back to the store</a>');
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

$url = url_product($p);
$gallery = array_values(array_filter((array)json_decode($p['gallery'] ?: '[]', true), 'is_string'));
$images = array_merge($p['image'] ? [$p['image']] : [], $gallery);
$video = youtube_id($p['video_url']);
$details = array_filter([
    'File size' => $p['file_size'], 'Format' => $p['file_format'], 'Version' => $p['version'], 'Works with' => $p['requirements'],
]);
$liked = !empty($_SESSION['liked'][$p['id']]);
$shareText = rawurlencode($p['title'] . ' (free download)');
$showCount = setting('show_downloads', '1') === '1';

page_header($p['title'], $p['category_slug'] ? 'cat:' . $p['category_slug'] : '', [
    'description' => $p['description'] !== '' ? $p['description'] : null,
    'image' => $p['image'] ? base_url('uploads/' . $p['image']) : null,
    'type' => 'product',
]);
?>
<?php if (!$p['active']): ?><p class="alert alert-bad"><?= $p['publish_at'] ? 'Scheduled for ' . e(date('M j, Y H:i', (int)$p['publish_at'])) . '.' : 'Hidden.' ?> Only you can see this page.</p><?php endif; ?>
<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="<?= e(base_url()) ?>"><?= icon('home', 14) ?> Home</a> <?= icon('chevron-right', 14) ?>
  <?php if ($p['category_name']): ?>
    <a href="<?= e(url_category($p['category_slug'])) ?>"><?= e($p['category_name']) ?></a> <?= icon('chevron-right', 14) ?>
  <?php endif; ?>
  <span><?= e($p['title']) ?></span>
</nav>

<article class="product">
  <div class="product-media">
    <div class="media-main">
      <?php if ($images): ?><img id="main-img" src="<?= e(base_url('uploads/' . $images[0])) ?>" alt="<?= e($p['title']) ?>">
      <?php else: ?><div class="thumb-empty big"><?= icon('package', 64) ?></div><?php endif; ?>
      <?php if ($p['badge'] !== ''): ?><span class="ribbon big"><?= e($p['badge']) ?></span><?php endif; ?>
    </div>
    <?php if (count($images) > 1): ?>
      <div class="thumbs">
        <?php foreach ($images as $i => $img): ?>
          <button type="button" class="<?= $i ? '' : 'active' ?>" data-src="<?= e(base_url('uploads/' . $img)) ?>" aria-label="Picture <?= $i + 1 ?>">
            <img src="<?= e(base_url('uploads/' . $img)) ?>" alt="" loading="lazy"></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($video !== ''): ?>
      <div class="video"><iframe src="https://www.youtube-nocookie.com/embed/<?= e($video) ?>" title="Video preview" loading="lazy"
        allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>
    <?php endif; ?>
  </div>
  <div class="product-body">
    <?php if ($p['category_name']): ?>
      <a class="card-cat" href="<?= e(url_category($p['category_slug'])) ?>"><?= e($p['category_name']) ?></a>
    <?php endif; ?>
    <h1><?= e($p['title']) ?></h1>
    <div class="product-meta">
      <span class="free-pill big"><?= icon('gift', 15) ?> Free</span>
      <?php if ($showCount && (int)$p['downloads'] > 0): ?><span><?= icon('download', 15) ?> <?= short_number((int)$p['downloads']) ?> downloads</span><?php endif; ?>
      <span><?= icon('clock', 15) ?> Added <?= e(date('M j, Y', (int)$p['created_at'])) ?></span>
    </div>
    <div class="description"><?= render_content($p['description']) ?></div>
    <?php if ($details): ?>
      <dl class="details">
        <?php foreach ($details as $label => $value): ?><div><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div><?php endforeach; ?>
      </dl>
    <?php endif; ?>

    <button class="btn btn-big btn-glow" id="unlock-btn" data-product="<?= (int)$p['id'] ?>"><?= icon('download', 20) ?> <?= e(setting_or('download_button', 'Download for free')) ?></button>

    <ul class="trust">
      <li><?= icon('shield', 18) ?> Private download link</li>
      <li><?= icon('zap', 18) ?> Unlocks automatically</li>
      <li><?= icon('gift', 18) ?> Free to download</li>
    </ul>

    <div class="share">
      <button type="button" class="like-btn<?= $liked ? ' liked' : '' ?>" id="like-btn" data-id="<?= (int)$p['id'] ?>" data-csrf="<?= e(csrf_token()) ?>" aria-pressed="<?= $liked ? 'true' : 'false' ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
        <span><?= (int)$p['likes'] ?></span></button>
      <span>Share:</span>
      <a href="https://www.facebook.com/sharer/sharer.php?u=<?= e(rawurlencode($url)) ?>" target="_blank" rel="noopener" aria-label="Share on Facebook"><?= icon('facebook', 16) ?></a>
      <a href="https://twitter.com/intent/tweet?url=<?= e(rawurlencode($url)) ?>&text=<?= e($shareText) ?>" target="_blank" rel="noopener" aria-label="Share on X"><?= icon('x-brand', 16) ?></a>
      <a href="https://wa.me/?text=<?= e($shareText . '%20' . rawurlencode($url)) ?>" target="_blank" rel="noopener" aria-label="Share on WhatsApp"><?= icon('whatsapp', 16) ?></a>
      <a href="https://t.me/share/url?url=<?= e(rawurlencode($url)) ?>&text=<?= e($shareText) ?>" target="_blank" rel="noopener" aria-label="Share on Telegram"><?= icon('telegram', 16) ?></a>
      <button type="button" class="copy-link" data-url="<?= e($url) ?>" aria-label="Copy link"><?= icon('link', 16) ?> <span>Copy link</span></button>
    </div>
  </div>
</article>

<?= ad_slot('ad_product') ?>

<?php if ($related): ?>
<section class="home-section related">
  <?= section_head('You may also like') ?>
  <?= product_grid($related) ?>
</section>
<?php endif; ?>

<?= locker_modal($p['title'], $p['image'] ? base_url('uploads/' . $p['image']) : '') ?>
<?php page_footer();
