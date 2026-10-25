<?php
require __DIR__ . '/includes/bootstrap.php';

[$where, $params] = visible_listing_where();
$featured = q_all(listing_select() . " WHERE $where AND l.featured = 1 ORDER BY l.created_at DESC LIMIT 8", $params);
$latest = q_all(listing_select() . " WHERE $where ORDER BY l.created_at DESC LIMIT 12", $params);
$tree = category_tree();
$counts = category_counts();
$totalAds = (int) q_val("SELECT COUNT(*) FROM listings l JOIN users u ON u.id = l.user_id WHERE $where", $params);
$totalUsers = (int) q_val("SELECT COUNT(*) FROM users WHERE status = 'active'");
$recent = recently_viewed(4);

$pageTitle = '';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="container hero-inner">
    <div class="hero-copy">
      <span class="eyebrow"><?= icon('zap') ?><?= e(n_('{n} live ad near you', '{n} live ads near you', $totalAds)) ?></span>
      <h1><?= t(setting('hero_title')) ?></h1>
      <p class="lead"><?= t(setting('hero_subtitle')) ?></p>
    </div>
    <form class="search-panel" action="<?= e(search_url([])) ?>" method="get" role="search">
      <label class="sp-field sp-q suggest-wrap"><?= icon('search') ?><input type="search" name="q" placeholder="<?= t('What are you looking for?') ?>" aria-label="<?= t('Keyword') ?>" autocomplete="off" data-suggest></label>
      <?php if (location_list_mode()): ?>
        <label class="sp-field"><?= icon('map-pin') ?><select name="loc" aria-label="<?= t('Location') ?>"><option value=""><?= t('Everywhere') ?></option><?= location_options() ?></select></label>
      <?php else: ?>
        <label class="sp-field"><?= icon('map-pin') ?><input type="text" name="location" placeholder="<?= t('City or area') ?>" aria-label="<?= t('Location') ?>"></label>
      <?php endif; ?>
      <label class="sp-field"><?= icon('folder') ?><select name="category" aria-label="<?= t('Category') ?>"><option value=""><?= t('All categories') ?></option><?= category_options() ?></select></label>
      <button class="btn btn-primary btn-lg" type="submit"><?= icon('search') ?><?= t('Search') ?></button>
    </form>
    <div class="hero-chips">
      <?php foreach (array_slice($tree, 0, 6, true) as $id => $c): ?>
        <a class="chip-link" href="<?= e(category_url((int) $id)) ?>"><?= icon($c['icon']) ?><?= e($c['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?= ad_slot('header') ? '<div class="container">' . ad_slot('header') . '</div>' : '' ?>

<?php if ($recent): ?>
<section class="container section">
  <div class="section-head"><div><h2><?= t('Recently viewed') ?></h2><p class="muted"><?= t('Pick up where you left off.') ?></p></div></div>
  <div class="listing-grid">
    <?php foreach ($recent as $l) { include __DIR__ . '/includes/partials/listing-card.php'; } ?>
  </div>
</section>
<?php endif; ?>

<section class="container section">
  <div class="section-head">
    <div><h2><?= t('Browse categories') ?></h2><p class="muted"><?= t('Find exactly what you need, faster.') ?></p></div>
    <a class="link-arrow" href="<?= e(url('categories.php')) ?>"><?= t('All categories') ?><?= icon('arrow-right') ?></a>
  </div>
  <div class="cat-grid">
    <?php foreach ($tree as $id => $c): ?>
      <a class="cat-tile" href="<?= e(category_url((int) $id)) ?>">
        <span class="cat-icon"><?= icon($c['icon']) ?></span>
        <span class="cat-name"><?= e($c['name']) ?></span>
        <span class="cat-count"><?= e(n_('{n} ad', '{n} ads', $counts[$id] ?? 0)) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($featured): ?>
<section class="container section">
  <div class="section-head">
    <div><h2><?= t('Featured ads') ?></h2><p class="muted"><?= t('Hand-picked deals from trusted sellers.') ?></p></div>
    <a class="link-arrow" href="<?= e(search_url(['featured' => 1])) ?>"><?= t('See all') ?><?= icon('arrow-right') ?></a>
  </div>
  <div class="listing-grid">
    <?php foreach ($featured as $l) { include __DIR__ . '/includes/partials/listing-card.php'; } ?>
  </div>
</section>
<?php endif; ?>

<section class="container section">
  <div class="section-head">
    <div><h2><?= t('Fresh recommendations') ?></h2><p class="muted"><?= t('The newest ads posted by our community.') ?></p></div>
    <a class="link-arrow" href="<?= e(search_url([])) ?>"><?= t('Browse all') ?><?= icon('arrow-right') ?></a>
  </div>
  <?php if ($latest): ?>
    <div class="listing-grid">
      <?php foreach ($latest as $l) { include __DIR__ . '/includes/partials/listing-card.php'; } ?>
    </div>
  <?php else: ?>
    <div class="empty-state"><?= icon('package') ?><h3><?= t('No ads yet') ?></h3><p><?= t('Be the first to post something!') ?></p><a class="btn btn-primary" href="<?= e(url('dashboard/edit.php')) ?>"><?= t('Post an ad') ?></a></div>
  <?php endif; ?>
</section>

<section class="container section">
  <div class="steps">
    <div class="step"><span class="step-icon"><?= icon('user') ?></span><h3><?= t('Create an account') ?></h3><p><?= t('Sign up for free in seconds. No credit card, no fuss.') ?></p></div>
    <div class="step"><span class="step-icon"><?= icon('camera') ?></span><h3><?= t('Post your ad') ?></h3><p><?= t('Add a few photos, a fair price and a short description.') ?></p></div>
    <div class="step"><span class="step-icon"><?= icon('message') ?></span><h3><?= t('Chat & sell') ?></h3><p><?= t('Buyers message you directly. Meet up, get paid, done.') ?></p></div>
  </div>
</section>

<section class="container section">
  <div class="cta-band">
    <div>
      <h2><?= t('Have something to sell?') ?></h2>
      <p><?= t('Join {n} members already buying and selling on {site}.', ['n' => number_format($totalUsers), 'site' => setting('site_name')]) ?></p>
    </div>
    <a class="btn btn-light btn-lg" href="<?= e(url('dashboard/edit.php')) ?>"><?= icon('plus') ?><?= t('Post a free ad') ?></a>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php';
