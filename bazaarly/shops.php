<?php
require __DIR__ . '/includes/bootstrap.php';
if (!shops_enabled()) {
    not_found();
}
$q = mb_substr(input('q'), 0, 80);
$where = "s.status = 'active' AND u.status = 'active'";
$params = [];
if ($q !== '') {
    $where .= ' AND (s.name LIKE ? OR s.tagline LIKE ? OR s.address LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
$total = (int) q_val("SELECT COUNT(*) FROM shops s JOIN users u ON u.id = s.user_id WHERE $where", $params);
[$page, $pages, $offset] = paginate($total, 24, (int) input('page', '1'));
$shops = q_all("SELECT s.*, u.verified,
        (SELECT COUNT(*) FROM listings l WHERE l.user_id = s.user_id AND l.status = 'active') AS ads
    FROM shops s JOIN users u ON u.id = s.user_id WHERE $where ORDER BY ads DESC, s.name LIMIT 24 OFFSET $offset", $params);

$pageTitle = __('Shops');
require __DIR__ . '/includes/header.php';
?>
<section class="container section-sm">
  <nav class="breadcrumb"><a href="<?= e(url()) ?>"><?= t('Home') ?></a><?= icon('chevron-right') ?><span><?= t('Shops') ?></span></nav>
  <div class="results-bar">
    <div><h1 class="results-title"><?= t('Shops') ?></h1><p class="muted"><?= t('Businesses selling on {site}.', ['site' => setting('site_name')]) ?></p></div>
    <form method="get" class="shop-search"><div class="input-icon"><?= icon('search') ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= t('Find a shop') ?>"></div></form>
  </div>
  <?php if ($shops): ?>
    <div class="shop-grid">
      <?php foreach ($shops as $s): $open = shop_open_now($s); ?>
        <a class="card shop-card" href="<?= e(shop_url($s)) ?>">
          <span class="shop-card-banner"<?= $s['banner'] ? ' style="background-image:url(\'' . e(upload_url($s['banner'])) . '\')"' : '' ?>></span>
          <?php if ($s['logo']): ?><img class="shop-logo sm" src="<?= e(upload_url($s['logo'])) ?>" alt="" loading="lazy"><?php else: ?><span class="shop-logo sm shop-logo-ph"><?= icon('shop') ?></span><?php endif; ?>
          <strong><?= e($s['name']) ?><?php if ($s['verified']): ?><span class="verified"><?= icon('shield') ?></span><?php endif; ?></strong>
          <?php if ($s['tagline']): ?><small class="muted"><?= e(excerpt($s['tagline'], 80)) ?></small><?php endif; ?>
          <span class="shop-card-meta"><span><?= e(n_('{n} ad', '{n} ads', (int) $s['ads'])) ?></span><?php if ($open !== null): ?><span class="<?= $open ? 'text-success' : 'muted' ?>"><?= $open ? t('Open now') : t('Closed now') ?></span><?php endif; ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <?= pagination_links($page, $pages) ?>
  <?php else: ?>
    <div class="empty-state"><?= icon('shop') ?><h3><?= t('No shops yet') ?></h3><p><?= t('Run a business? Create a free shop page from your dashboard.') ?></p><a class="btn btn-primary" href="<?= e(url('dashboard/shop.php')) ?>"><?= t('Create a shop') ?></a></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php';
