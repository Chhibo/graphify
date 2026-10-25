<?php
require __DIR__ . '/includes/bootstrap.php';

$shop = shops_enabled() ? q_one("SELECT s.*, u.name AS owner_name, u.username, u.verified, u.status AS owner_status, u.created_at AS member_since
    FROM shops s JOIN users u ON u.id = s.user_id WHERE s.slug = ?", [input('slug')]) : null;
$isOwner = $shop && user_id() === (int) $shop['user_id'];
if (!$shop || (($shop['status'] !== 'active' || $shop['owner_status'] !== 'active') && !$isOwner && !is_admin())) {
    not_found(__('This shop could not be found.'));
}
$uid = (int) $shop['user_id'];
$q = mb_substr(input('q'), 0, 80);
[$where, $params] = visible_listing_where();
$where .= ' AND l.user_id = ?';
$params[] = $uid;
if ($q !== '') {
    $where .= ' AND (l.title LIKE ? OR l.description LIKE ?)';
    array_push($params, "%$q%", "%$q%");
}
$total = (int) q_val("SELECT COUNT(*) FROM listings l JOIN users u ON u.id = l.user_id WHERE $where", $params);
[$page, $pages, $offset] = paginate($total, 12, (int) input('page', '1'));
$listings = q_all(listing_select() . " WHERE $where ORDER BY l.featured DESC, l.created_at DESC LIMIT 12 OFFSET $offset", $params);
[$rating, $ratingCount] = seller_rating($uid);
$hours = shop_hours($shop);
$hasHours = (bool) array_filter($hours, fn($h) => $h['closed'] || $h['open'] !== '');
$open = shop_open_now($shop);
$todayKey = strtolower(date('D'));

$pageTitle = $shop['name'];
$metaDesc = $shop['tagline'] ?: excerpt((string) $shop['description'], 160);
$canonical = abs_url(shop_url($shop));
$ogImage = $shop['banner'] ? abs_url(url($shop['banner'])) : ($shop['logo'] ? abs_url(url($shop['logo'])) : null);
$jsonLd = ['@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => $shop['name'], 'url' => $canonical,
    'description' => $metaDesc, 'telephone' => $shop['phone'] ?: null, 'address' => $shop['address'] ?: null,
    'image' => $ogImage, 'logo' => $shop['logo'] ? abs_url(url($shop['logo'])) : null];
require __DIR__ . '/includes/header.php';
?>
<section class="shop-hero"<?= $shop['banner'] ? ' style="--banner:url(\'' . e(upload_url($shop['banner'])) . '\')"' : '' ?>>
  <div class="container shop-hero-inner">
    <?php if ($shop['logo']): ?><img class="shop-logo" src="<?= e(upload_url($shop['logo'])) ?>" alt="<?= e($shop['name']) ?>"><?php else: ?><span class="shop-logo shop-logo-ph"><?= icon('shop') ?></span><?php endif; ?>
    <div class="shop-title">
      <h1><?= e($shop['name']) ?></h1>
      <?php if ($shop['tagline']): ?><p><?= e($shop['tagline']) ?></p><?php endif; ?>
      <div class="shop-badges">
        <span class="badge badge-brand"><?= icon('shop') ?><?= t('Business') ?></span>
        <?php if ($shop['verified']): ?><span class="badge badge-success"><?= icon('shield') ?><?= t('Verified') ?></span><?php endif; ?>
        <?php if ($open !== null): ?><span class="badge <?= $open ? 'badge-success' : '' ?>"><?= icon('clock') ?><?= $open ? t('Open now') : t('Closed now') ?></span><?php endif; ?>
        <?php if ($ratingCount): ?><span class="badge"><?= stars($rating, 'sm') ?> <?= $rating ?> (<?= $ratingCount ?>)</span><?php endif; ?>
      </div>
    </div>
  </div>
</section>

<section class="container section-sm">
  <?php if ($shop['status'] !== 'active'): ?><div class="alert alert-warning"><?= icon('info') ?><span><?= t('Only you can see this page until the shop is approved.') ?></span></div><?php endif; ?>
  <div class="profile-layout shop-layout">
    <div>
      <div class="results-bar">
        <h2 class="h-md"><?= e(n_('{n} ad', '{n} ads', $total)) ?></h2>
        <form method="get" class="shop-search"><?php if (!clean_urls()): ?><input type="hidden" name="slug" value="<?= e($shop['slug']) ?>"><?php endif; ?>
          <div class="input-icon"><?= icon('search') ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= t('Search this shop') ?>"></div></form>
      </div>
      <?php if ($listings): ?>
        <div class="listing-grid cols-3">
          <?php foreach ($listings as $l) { include __DIR__ . '/includes/partials/listing-card.php'; } ?>
        </div>
        <?= pagination_links($page, $pages) ?>
      <?php else: ?>
        <div class="empty-state"><?= icon('package') ?><h3><?= t('No ads found') ?></h3></div>
      <?php endif; ?>
    </div>
    <aside>
      <?php if ($shop['description']): ?>
        <div class="card side-card"><h3><?= t('About') ?></h3><p class="small"><?= nl2br(e((string) $shop['description'])) ?></p></div>
      <?php endif; ?>
      <div class="card side-card">
        <h3><?= t('Contact') ?></h3>
        <ul class="contact-list">
          <?php if ($shop['phone']): ?><li><?= icon('phone') ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $shop['phone'])) ?>"><?= e($shop['phone']) ?></a></li><?php endif; ?>
          <?php if ($shop['email']): ?><li><?= icon('mail') ?><a href="mailto:<?= e($shop['email']) ?>"><?= e($shop['email']) ?></a></li><?php endif; ?>
          <?php if ($shop['website']): ?><li><?= icon('globe') ?><a href="<?= e($shop['website']) ?>" target="_blank" rel="noopener nofollow"><?= e(preg_replace('~^https?://(www\.)?~', '', rtrim($shop['website'], '/'))) ?></a></li><?php endif; ?>
          <?php if ($shop['address']): ?><li><?= icon('map-pin') ?><span><?= e($shop['address']) ?></span></li><?php endif; ?>
          <li><?= icon('user') ?><a href="<?= e(profile_url($uid, $shop['username'])) ?>"><?= t('Seller profile & reviews') ?></a></li>
        </ul>
        <?php if ($shop['address'] && setting('enable_map') === '1'): ?>
          <div class="map-embed small-map" data-map="<?= e($shop['address']) ?>"><button class="btn btn-ghost btn-sm" type="button" data-load-map><?= icon('globe') ?><?= t('Show on map') ?></button></div>
        <?php endif; ?>
      </div>
      <?php if ($hasHours): ?>
        <div class="card side-card">
          <h3><?= icon('clock') ?><?= t('Opening hours') ?></h3>
          <table class="hours-table">
            <?php foreach (WEEK_DAYS as $k => $dayName): $h = $hours[$k]; ?>
              <tr class="<?= $k === $todayKey ? 'today' : '' ?>"><th><?= t($dayName) ?></th>
                <td><?= $h['closed'] || $h['open'] === '' ? t('Closed') : e($h['open'] . ' – ' . $h['close']) ?></td></tr>
            <?php endforeach; ?>
          </table>
        </div>
      <?php endif; ?>
      <p class="muted small center"><?= t('Member since {date}', ['date' => format_month($shop['member_since'])]) ?></p>
    </aside>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php';
