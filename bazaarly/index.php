<?php
require __DIR__ . '/includes/bootstrap.php';

[$where, $params] = visible_listing_where();
$featured = q_all(listing_select() . " WHERE $where AND l.featured = 1 ORDER BY l.created_at DESC LIMIT 8", $params);
$latest = q_all(listing_select() . " WHERE $where ORDER BY l.created_at DESC LIMIT 12", $params);
$tree = category_tree();
$counts = category_counts();
$totalAds = (int) q_val("SELECT COUNT(*) FROM listings l JOIN users u ON u.id = l.user_id WHERE $where", $params);
$totalUsers = (int) q_val("SELECT COUNT(*) FROM users WHERE status = 'active'");

$pageTitle = '';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="container hero-inner">
    <div class="hero-copy">
      <span class="eyebrow"><?= icon('zap') ?><?= number_format($totalAds) ?> live ads near you</span>
      <h1><?= e(setting('hero_title')) ?></h1>
      <p class="lead"><?= e(setting('hero_subtitle')) ?></p>
    </div>
    <form class="search-panel" action="<?= e(url('listings.php')) ?>" method="get" role="search">
      <label class="sp-field sp-q"><?= icon('search') ?><input type="search" name="q" placeholder="What are you looking for?" aria-label="Keyword"></label>
      <label class="sp-field"><?= icon('map-pin') ?><input type="text" name="location" placeholder="City or area" aria-label="Location"></label>
      <label class="sp-field"><?= icon('folder') ?><select name="category" aria-label="Category"><option value="">All categories</option><?= category_options() ?></select></label>
      <button class="btn btn-primary btn-lg" type="submit"><?= icon('search') ?>Search</button>
    </form>
    <div class="hero-chips">
      <?php foreach (array_slice($tree, 0, 6, true) as $id => $c): ?>
        <a class="chip-link" href="<?= e(url('listings.php?category=' . $id)) ?>"><?= icon($c['icon']) ?><?= e($c['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?= ad_slot('header') ? '<div class="container">' . ad_slot('header') . '</div>' : '' ?>

<section class="container section">
  <div class="section-head">
    <div><h2>Browse categories</h2><p class="muted">Find exactly what you need, faster.</p></div>
    <a class="link-arrow" href="<?= e(url('categories.php')) ?>">All categories<?= icon('arrow-right') ?></a>
  </div>
  <div class="cat-grid">
    <?php foreach ($tree as $id => $c): ?>
      <a class="cat-tile" href="<?= e(url('listings.php?category=' . $id)) ?>">
        <span class="cat-icon"><?= icon($c['icon']) ?></span>
        <span class="cat-name"><?= e($c['name']) ?></span>
        <span class="cat-count"><?= ($n = $counts[$id] ?? 0) === 1 ? "1 ad" : number_format($n) . " ads" ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($featured): ?>
<section class="container section">
  <div class="section-head">
    <div><h2>Featured ads</h2><p class="muted">Hand-picked deals from trusted sellers.</p></div>
    <a class="link-arrow" href="<?= e(url('listings.php?featured=1')) ?>">See all<?= icon('arrow-right') ?></a>
  </div>
  <div class="listing-grid">
    <?php foreach ($featured as $l) { include __DIR__ . '/includes/partials/listing-card.php'; } ?>
  </div>
</section>
<?php endif; ?>

<section class="container section">
  <div class="section-head">
    <div><h2>Fresh recommendations</h2><p class="muted">The newest ads posted by our community.</p></div>
    <a class="link-arrow" href="<?= e(url('listings.php')) ?>">Browse all<?= icon('arrow-right') ?></a>
  </div>
  <?php if ($latest): ?>
    <div class="listing-grid">
      <?php foreach ($latest as $l) { include __DIR__ . '/includes/partials/listing-card.php'; } ?>
    </div>
  <?php else: ?>
    <div class="empty-state"><?= icon('package') ?><h3>No ads yet</h3><p>Be the first to post something!</p><a class="btn btn-primary" href="<?= e(url('dashboard/edit.php')) ?>">Post an ad</a></div>
  <?php endif; ?>
</section>

<section class="container section">
  <div class="steps">
    <div class="step"><span class="step-icon"><?= icon('user') ?></span><h3>Create an account</h3><p>Sign up for free in seconds. No credit card, no fuss.</p></div>
    <div class="step"><span class="step-icon"><?= icon('camera') ?></span><h3>Post your ad</h3><p>Add a few photos, a fair price and a short description.</p></div>
    <div class="step"><span class="step-icon"><?= icon('message') ?></span><h3>Chat & sell</h3><p>Buyers message you directly. Meet up, get paid, done.</p></div>
  </div>
</section>

<section class="container section">
  <div class="cta-band">
    <div>
      <h2>Have something to sell?</h2>
      <p>Join <?= number_format($totalUsers) ?> members already buying and selling on <?= e(setting('site_name')) ?>.</p>
    </div>
    <a class="btn btn-light btn-lg" href="<?= e(url('dashboard/edit.php')) ?>"><?= icon('plus') ?>Post a free ad</a>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php';
