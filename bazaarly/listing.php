<?php
require __DIR__ . '/includes/bootstrap.php';

$id = (int) input('id');
$l = q_one(listing_select() . ' WHERE l.id = ?', [$id]);
if (!$l) {
    not_found(__('This ad does not exist or has been removed.'));
}
$me = current_user();
$isOwner = $me && (int) $me['id'] === (int) $l['user_id'];
$canManage = $isOwner || is_admin();
$seller = q_one('SELECT * FROM users WHERE id = ?', [$l['user_id']]);
$isLive = $l['status'] === 'active' && (!$l['expires_at'] || $l['expires_at'] > now()) && ($seller['status'] ?? '') === 'active';
if (!$isLive && $l['status'] !== 'sold' && !$canManage) {
    not_found(__('This ad is no longer available.'));
}
$reportReasons = ['Scam or fraud', 'Wrong category', 'Prohibited item', 'Duplicate ad', 'Offensive content', 'Item already sold', 'Other'];

/* ----- actions ----- */
if (is_post()) {
    $action = input('action');
    if (!$me) {
        $_SESSION['intended'] = listing_url($l);
        flash('info', __('Please sign in first.'));
        redirect('login.php');
    }
    $back = ltrim(substr(listing_url($l), strlen(BASE_PATH)), '/');
    if ($action === 'message' && !$isOwner) {
        require_verified_email($back);
        $isNew = !q_val('SELECT id FROM conversations WHERE listing_id = ? AND buyer_id = ?', [$id, $me['id']]);
        $res = post_message($id, $me, (string) ($_POST['body'] ?? ''));
        if (is_string($res)) {
            flash('error', $res);
            header('Location: ' . listing_url($l) . '#contact');
            exit;
        }
        if ($isNew) {
            track_stat($id, 'messages');
        }
        flash('success', __('Message sent! You can follow the conversation in your inbox.'));
        redirect('dashboard/messages.php?c=' . $res);
    }
    if ($action === 'offer' && !$isOwner) {
        require_verified_email($back);
        $isNew = !q_val('SELECT id FROM conversations WHERE listing_id = ? AND buyer_id = ?', [$id, $me['id']]);
        $res = make_offer($l, $me, (float) str_replace([',', ' '], '', input('amount')), input('note'));
        if (is_string($res)) {
            flash('error', $res);
            header('Location: ' . listing_url($l));
            exit;
        }
        if ($isNew) {
            track_stat($id, 'messages');
        }
        flash('success', __('Your offer was sent. You\'ll get an email when the seller answers.'));
        redirect('dashboard/messages.php?c=' . $res);
    }
    if ($action === 'report') {
        $reason = in_array(input('reason'), $reportReasons, true) ? input('reason') : 'Other';
        db_insert('reports', ['listing_id' => $id, 'user_id' => $me['id'], 'reason' => $reason,
            'details' => mb_substr(input('details'), 0, 2000), 'status' => 'open', 'created_at' => now()]);
        flash('success', __('Thanks for letting us know. Our team will review this ad.'));
    }
    header('Location: ' . listing_url($l));
    exit;
}

remember_view($id);
if (!$canManage && empty($_SESSION['viewed'][$id])) {
    $_SESSION['viewed'][$id] = 1;
    q('UPDATE listings SET views = views + 1 WHERE id = ?', [$id]);
    track_stat($id, 'views');
    $l['views']++;
}

$images = q_all('SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id', [$id]);
$video = $l['video_url'] !== '' && setting('enable_video', '1') === '1' ? parse_video_url($l['video_url']) : null;
$slides = count($images) + ($video ? 1 : 0);
[$rating, $ratingCount] = seller_rating((int) $l['user_id']);
$sellerAds = (int) q_val("SELECT COUNT(*) FROM listings WHERE user_id = ? AND status = 'active'", [$l['user_id']]);
[$vw, $vp] = visible_listing_where();
$related = q_all(listing_select() . " WHERE $vw AND l.category_id = ? AND l.id <> ? ORDER BY l.featured DESC, l.created_at DESC LIMIT 4", array_merge($vp, [$l['category_id'], $id]));
$cat = categories_all()[(int) $l['category_id']] ?? null;
$parentCat = $cat && $cat['parent_id'] ? (categories_all()[(int) $cat['parent_id']] ?? null) : null;
$favs = favorite_ids();
$isFav = isset($favs[$id]);
$showPhone = $l['show_phone'] && $l['phone'] !== '' && ($me || setting('show_phone_to_guests') === '1');
$tags = array_filter(array_map('trim', explode(',', (string) $l['tags'])));
$shareUrl = abs_url(listing_url($l));
$fieldValues = listing_field_values($id);
$specFields = [];
foreach (fields_for_category((int) $l['category_id']) as $fid => $f) {
    if (isset($fieldValues[$fid]) && $fieldValues[$fid] !== '') {
        $specFields[] = [__($f['label']), format_field_value($f, $fieldValues[$fid])];
    }
}
$blockedByMe = $me && is_blocked((int) $me['id'], (int) $l['user_id']);
$blockedMe = $me && is_blocked((int) $l['user_id'], (int) $me['id']);
$tips = array_filter(array_map('trim', explode("\n", setting('safety_tips'))));
$shop = shops_enabled() ? user_shop((int) $l['user_id']) : null;
$canOffer = !$isOwner && listing_accepts_offers($l) && !$blockedByMe && !$blockedMe;
$myOffer = $me && $canOffer ? q_one("SELECT * FROM offers WHERE listing_id = ? AND buyer_id = ? AND status IN ('pending','countered','accepted') ORDER BY id DESC LIMIT 1", [$id, $me['id']]) : null;
$dupFlags = is_admin() ? listing_duplicate_flags($id) : [];

$pageTitle = $l['title'];
$metaDesc = excerpt((string) $l['description'], 160);
$canonical = $shareUrl;
$ogType = 'product';
if (setting('share_cards') === '1' && function_exists('imagecreatetruecolor')) {
    $ogImage = abs_url(clean_urls() ? url('ad/' . $id . '/card.jpg') : url('share-card.php?id=' . $id));
    $ogImageSize = [1200, 630];
} else {
    $ogImage = $images ? abs_url(url($images[0]['path'])) : null;
}
$jsonLd = [
    '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $l['title'],
    'description' => excerpt((string) $l['description'], 300), 'url' => $shareUrl,
    'image' => array_map(fn($i) => abs_url(url($i['path'])), array_slice($images, 0, 5)),
    'category' => $cat['name'] ?? null,
];
if (in_array($l['price_type'], ['fixed', 'negotiable'], true)) {
    $jsonLd['offers'] = ['@type' => 'Offer', 'price' => number_format((float) $l['price'], 2, '.', ''), 'priceCurrency' => setting('currency_code', 'USD'),
        'availability' => $l['status'] === 'sold' ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock', 'url' => $shareUrl];
    $ogPrice = number_format((float) $l['price'], 2, '.', '');
}
require __DIR__ . '/includes/header.php';
?>
<section class="container section-sm">
  <nav class="breadcrumb">
    <a href="<?= e(url()) ?>"><?= t('Home') ?></a><?= icon('chevron-right') ?>
    <?php if ($parentCat): ?><a href="<?= e(category_url((int) $parentCat['id'])) ?>"><?= t($parentCat['name']) ?></a><?= icon('chevron-right') ?><?php endif; ?>
    <?php if ($cat): ?><a href="<?= e(category_url((int) $cat['id'])) ?>"><?= e($cat['name']) ?></a><?= icon('chevron-right') ?><?php endif; ?>
    <span><?= e(excerpt($l['title'], 40)) ?></span>
  </nav>

  <?php if ($canManage && !$isLive): ?>
    <div class="alert alert-warning"><?= icon('info') ?><span><?= t('This ad is {status} and is not visible to the public.', ['status' => strip_tags(status_badge($l['status']))]) ?><?= $l['reject_reason'] ? ' ' . t('Reason:') . ' ' . e($l['reject_reason']) : '' ?></span></div>
  <?php endif; ?>
  <?php foreach ($dupFlags as $fl): ?>
    <div class="alert alert-warning"><?= icon('copy') ?><span><?= e($fl['details']) ?> — possible duplicate of <a href="<?= e(listing_url(['id' => $fl['related_id'], 'title' => $fl['related_title'] ?? ''])) ?>">#<?= (int) $fl['related_id'] ?> <?= e($fl['related_title'] ?? 'deleted ad') ?></a><?= $fl['related_owner'] ? ' (' . e($fl['related_owner']) . ')' : '' ?>. <a href="<?= e(url('admin/listing-edit.php?id=' . $id)) ?>">Review in admin</a></span></div>
  <?php endforeach; ?>

  <div class="detail-layout">
    <div class="detail-main">
      <div class="gallery card" data-gallery>
        <div class="gallery-stage">
          <?php if ($slides): ?>
            <?php foreach ($images as $i => $img): ?>
              <img src="<?= e(upload_url($img['path'])) ?>" alt="<?= e($l['title']) ?> – <?= t('photo {n}', ['n' => $i + 1]) ?>" class="g-slide<?= $i === 0 ? ' on' : '' ?>" <?= $i ? 'loading="lazy"' : 'fetchpriority="high"' ?> data-index="<?= $i ?>">
            <?php endforeach; ?>
            <?php if ($video): $vthumb = video_thumb($video); ?>
              <div class="g-slide g-video<?= $images ? '' : ' on' ?>" data-index="<?= count($images) ?>" data-embed="<?= e(video_embed_url($video)) ?>">
                <?php if ($vthumb): ?><img src="<?= e($vthumb) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?>
                <button type="button" class="g-play" data-play-video aria-label="<?= t('Play video') ?>"><?= icon('play') ?></button>
              </div>
            <?php endif; ?>
            <?php if ($slides > 1): ?>
              <button class="g-nav prev" type="button" data-g-prev aria-label="<?= t('Previous photo') ?>"><?= icon('chevron-left') ?></button>
              <button class="g-nav next" type="button" data-g-next aria-label="<?= t('Next photo') ?>"><?= icon('chevron-right') ?></button>
              <span class="g-count"><span data-g-current>1</span> / <?= $slides ?></span>
            <?php endif; ?>
          <?php else: ?>
            <div class="gallery-empty"><?= icon($l['category_icon'] ?: 'image') ?><span><?= t('No photos') ?></span></div>
          <?php endif; ?>
          <?php if ($l['featured']): ?><span class="chip chip-featured"><?= icon('zap') ?><?= t('Featured') ?></span><?php endif; ?>
          <?php if ($l['status'] === 'sold'): ?><span class="chip chip-sold"><?= t('Sold') ?></span><?php endif; ?>
        </div>
        <?php if ($slides > 1): ?>
          <div class="gallery-thumbs">
            <?php foreach ($images as $i => $img): ?>
              <button type="button" class="<?= $i === 0 ? 'on' : '' ?>" data-g-thumb="<?= $i ?>" aria-label="<?= t('Show photo {n}', ['n' => $i + 1]) ?>"><img src="<?= e(upload_url($img['thumb'])) ?>" alt="" loading="lazy"></button>
            <?php endforeach; ?>
            <?php if ($video): ?>
              <button type="button" class="thumb-video<?= $images ? '' : ' on' ?>" data-g-thumb="<?= count($images) ?>" aria-label="<?= t('Play video') ?>"><?php if (video_thumb($video)): ?><img src="<?= e(video_thumb($video)) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?><?= icon('play') ?></button>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="card detail-card">
        <div class="detail-title-row">
          <div>
            <h1 class="detail-title"><?= e($l['title']) ?></h1>
            <div class="meta-row">
              <span><?= icon('map-pin') ?><?= e($l['location'] ?: __('Location not specified')) ?></span>
              <span><?= icon('clock') ?><?= t('Posted {when}', ['when' => time_ago($l['created_at'])]) ?></span>
              <span><?= icon('eye') ?><?= e(n_('{n} view', '{n} views', (int) $l['views'])) ?></span>
            </div>
          </div>
          <div class="detail-price">
            <?php if (has_price_drop($l)): ?><span class="was-price"><?= e(money($l['previous_price'])) ?></span><?php endif; ?>
            <strong><?= e(listing_price($l)) ?></strong>
            <?php if (has_price_drop($l)): ?><span class="badge badge-success"><?= icon('trending-down') ?><?= t('Price dropped') ?></span><?php endif; ?>
            <?php if ($l['price_type'] === 'negotiable'): ?><span class="badge badge-success"><?= t('Negotiable') ?></span><?php endif; ?>
          </div>
        </div>

        <dl class="spec-grid">
          <?php if ($cat): ?><div><dt><?= t('Category') ?></dt><dd><?= e($cat['name']) ?></dd></div><?php endif; ?>
          <?php if ($l['item_condition']): ?><div><dt><?= t('Condition') ?></dt><dd><?= e(condition_label($l['item_condition'])) ?></dd></div><?php endif; ?>
          <?php foreach ($specFields as [$lbl, $val]): ?><div><dt><?= e($lbl) ?></dt><dd><?= e($val) ?></dd></div><?php endforeach; ?>
          <div><dt><?= t('Posted on') ?></dt><dd><?= e(format_date($l['created_at'])) ?></dd></div>
          <div><dt><?= t('Ad ID') ?></dt><dd>#<?= (int) $l['id'] ?></dd></div>
        </dl>

        <h2 class="h-sm"><?= t('Description') ?></h2>
        <div class="description" data-collapsible><?= nl2br(e((string) $l['description'])) ?></div>

        <?php if ($tags): ?>
          <div class="tag-row">
            <?php foreach ($tags as $tg): ?><a class="tag" href="<?= e(search_url(['q' => $tg])) ?>">#<?= e($tg) ?></a><?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="action-row">
          <button class="btn btn-ghost fav-toggle<?= $isFav ? ' on' : '' ?>" type="button" data-fav="<?= $id ?>" aria-pressed="<?= $isFav ? 'true' : 'false' ?>" data-label-on="<?= t('Saved') ?>" data-label-off="<?= t('Save') ?>"><?= icon('heart') ?><span data-fav-label><?= $isFav ? t('Saved') : t('Save') ?></span></button>
          <button class="btn btn-ghost" type="button" data-share data-url="<?= e($shareUrl) ?>" data-title="<?= e($l['title']) ?>" data-copied="<?= t('Link copied!') ?>"><?= icon('share') ?><?= t('Share') ?></button>
          <?php if (!$isOwner): ?><button class="btn btn-ghost" type="button" data-modal-open="reportModal"><?= icon('flag') ?><?= t('Report') ?></button><?php endif; ?>
          <span class="share-links">
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($shareUrl) ?>" target="_blank" rel="noopener" aria-label="Facebook"><?= icon('facebook') ?></a>
            <a href="https://twitter.com/intent/tweet?url=<?= urlencode($shareUrl) ?>&text=<?= urlencode($l['title']) ?>" target="_blank" rel="noopener" aria-label="X"><?= icon('twitter') ?></a>
            <a href="https://wa.me/?text=<?= rawurlencode($l['title'] . ' – ' . listing_price($l) . "\n" . $shareUrl) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><?= icon('whatsapp') ?></a>
            <a href="https://t.me/share/url?url=<?= rawurlencode($shareUrl) ?>&text=<?= rawurlencode($l['title']) ?>" target="_blank" rel="noopener" aria-label="Telegram"><?= icon('send') ?></a>
          </span>
        </div>
      </div>

      <?= ad_slot('listing') ?>

      <?php if (setting('enable_map') === '1' && $l['location'] !== '' && strtolower($l['location']) !== 'remote'): ?>
        <div class="card detail-card">
          <h2 class="h-sm"><?= icon('map-pin') ?><?= t('Location') ?></h2>
          <p class="muted"><?= e($l['location']) ?></p>
          <div class="map-embed" data-map="<?= e($l['location']) ?>">
            <button class="btn btn-ghost" type="button" data-load-map><?= icon('globe') ?><?= t('Show on map') ?></button>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <aside class="detail-side">
      <?php if ($canManage): ?>
        <div class="card side-card owner-card">
          <h3><?= $isOwner ? t('This is your ad') : 'Admin tools' ?></h3>
          <div class="btn-stack">
            <a class="btn btn-primary btn-block" href="<?= e(url(($isOwner ? 'dashboard/edit.php' : 'admin/listing-edit.php') . '?id=' . $id)) ?>"><?= icon('edit') ?><?= t('Edit ad') ?></a>
            <?php if ($isOwner): ?><a class="btn btn-ghost btn-block" href="<?= e(url('dashboard/stats.php?ad=' . $id)) ?>"><?= icon('chart') ?><?= t('See statistics') ?></a><?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="card side-card seller-card">
        <?php if ($shop): ?>
          <a class="shop-strip" href="<?= e(shop_url($shop)) ?>">
            <?php if ($shop['logo']): ?><img src="<?= e(upload_url($shop['logo'])) ?>" alt=""><?php else: ?><span class="shop-logo-ph"><?= icon('shop') ?></span><?php endif; ?>
            <span><strong><?= e($shop['name']) ?></strong><small><span class="badge badge-brand"><?= icon('shop') ?><?= t('Business') ?></span><?php $open = shop_open_now($shop); if ($open !== null): ?> <span class="<?= $open ? 'text-success' : 'muted' ?>"><?= $open ? t('Open now') : t('Closed now') ?></span><?php endif; ?></small></span>
          </a>
        <?php endif; ?>
        <a class="seller-head" href="<?= e(profile_url((int) $l['user_id'], $seller['username'] ?? null)) ?>">
          <?= avatar_html($seller, 'lg') ?>
          <span>
            <strong><?= e($seller['name'] ?? __('Unknown')) ?><?php if (!empty($seller['verified'])): ?><span class="verified" title="<?= t('Verified seller') ?>"><?= icon('shield') ?></span><?php endif; ?></strong>
            <small><?= t('Member since {date}', ['date' => date('M Y', strtotime($seller['created_at'] ?? 'now'))]) ?></small>
            <?php if ($ratingCount): ?><span class="rating-line"><?= stars($rating) ?><small><?= $rating ?> (<?= $ratingCount ?>)</small></span><?php endif; ?>
          </span>
        </a>
        <div class="seller-stats"><span><?= e(n_('{n} active ad', '{n} active ads', $sellerAds)) ?></span><a href="<?= e($shop ? shop_url($shop) : profile_url((int) $l['user_id'], $seller['username'] ?? null)) ?>"><?= $shop ? t('Visit shop') : t('View profile') ?><?= icon('arrow-right') ?></a></div>

        <?php if ($l['show_phone'] && $l['phone'] !== ''): ?>
          <?php if ($showPhone): ?>
            <button class="btn btn-dark btn-block phone-reveal" type="button" data-phone="<?= e($l['phone']) ?>" data-listing="<?= $id ?>">
              <?= icon('phone') ?><span data-phone-text><?= e(mb_substr($l['phone'], 0, max(3, mb_strlen($l['phone']) - 6))) ?> ••• ••</span><small><?= t('Show') ?></small>
            </button>
          <?php else: ?>
            <a class="btn btn-dark btn-block" href="<?= e(url('login.php')) ?>"><?= icon('phone') ?><?= t('Sign in to see the phone number') ?></a>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($canOffer): ?>
          <?php if ($myOffer): ?>
            <a class="offer-status" href="<?= e(url('dashboard/messages.php?c=' . $myOffer['conversation_id'])) ?>"><?= icon('hand-coins') ?><span><?= t('Your offer: {amount}', ['amount' => money($myOffer['status'] === 'countered' ? $myOffer['counter_amount'] : $myOffer['amount'])]) ?><small><?= e(offer_status_label($myOffer['status'])) ?></small></span><?= icon('chevron-right') ?></a>
          <?php else: ?>
            <?php if ($me): ?><button class="btn btn-ghost btn-block offer-btn" type="button" data-modal-open="offerModal"><?= icon('hand-coins') ?><?= t('Make an offer') ?></button><?php else: ?><a class="btn btn-ghost btn-block offer-btn" href="<?= e(url('login.php?next=' . urlencode(listing_url($l)))) ?>"><?= icon('hand-coins') ?><?= t('Make an offer') ?></a><?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>

        <?php if (!$isOwner && ($blockedByMe || $blockedMe)): ?>
          <p class="muted small blocked-note" id="contact"><?= icon('ban') ?><?= $blockedByMe ? t('You blocked this seller. Unblock them from your account settings to send messages.') : t('This seller is not accepting messages from you.') ?></p>
        <?php elseif (!$isOwner): ?>
          <form method="post" class="contact-form" id="contact">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="message">
            <label for="msg-body" class="label"><?= t('Message the seller') ?></label>
            <textarea id="msg-body" name="body" rows="4" required maxlength="4000"><?= t('Hi! Is "{title}" still available?', ['title' => $l['title']]) ?></textarea>
            <?php if ($me): ?>
              <button class="btn btn-primary btn-block" type="submit"><?= icon('send') ?><?= t('Send message') ?></button>
            <?php else: ?>
              <a class="btn btn-primary btn-block" href="<?= e(url('login.php')) ?>"><?= icon('log-in') ?><?= t('Sign in to send a message') ?></a>
            <?php endif; ?>
          </form>
        <?php endif; ?>
      </div>

      <?php if ($tips): ?>
        <div class="card side-card tips-card">
          <h3><?= icon('shield') ?><?= t('Stay safe') ?></h3>
          <ul class="check-list">
            <?php foreach ($tips as $tip): ?><li><?= icon('check') ?><?= t($tip) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <?= ad_slot('sidebar') ?>
    </aside>
  </div>

  <?php if ($related): ?>
    <div class="section-head mt-xl"><div><h2><?= t('Similar ads') ?></h2></div>
      <?php if ($cat): ?><a class="link-arrow" href="<?= e(category_url((int) $cat['id'])) ?>"><?= t('More in {category}', ['category' => $cat['name']]) ?><?= icon('arrow-right') ?></a><?php endif; ?></div>
    <div class="listing-grid">
      <?php foreach ($related as $l) { include __DIR__ . '/includes/partials/listing-card.php'; } ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($canOffer && $me && !$myOffer): ?>
<dialog class="modal" id="offerModal">
  <form method="post" class="modal-body">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="offer">
    <div class="modal-head"><h3><?= icon('hand-coins') ?><?= t('Make an offer') ?></h3><button class="icon-btn" type="button" data-modal-close aria-label="<?= t('Close') ?>"><?= icon('x') ?></button></div>
    <p class="muted small"><?= t('Asking price: {price}', ['price' => listing_price($l)]) ?></p>
    <div class="field"><label for="o-amount"><?= t('Your offer') ?></label>
      <div class="input-prefix"><span><?= e(setting('currency_symbol')) ?></span><input id="o-amount" name="amount" inputmode="decimal" required placeholder="0"></div></div>
    <div class="offer-quick" data-offer-quick data-price="<?= e((string) (float) $l['price']) ?>">
      <?php foreach ([95, 90, 85] as $pct): ?><button type="button" class="pill" data-pct="<?= $pct ?>">-<?= 100 - $pct ?>%</button><?php endforeach; ?>
    </div>
    <div class="field"><label for="o-note"><?= t('Message (optional)') ?></label><textarea id="o-note" name="note" rows="2" maxlength="1000" placeholder="<?= t('e.g. I can pick it up today.') ?>"></textarea></div>
    <div class="modal-foot"><button class="btn btn-ghost" type="button" data-modal-close><?= t('Cancel') ?></button><button class="btn btn-primary" type="submit"><?= icon('send') ?><?= t('Send offer') ?></button></div>
  </form>
</dialog>
<?php endif; ?>

<?php if (!$isOwner): ?>
<dialog class="modal" id="reportModal">
  <form method="post" class="modal-body">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="report">
    <div class="modal-head"><h3><?= icon('flag') ?><?= t('Report this ad') ?></h3><button class="icon-btn" type="button" data-modal-close aria-label="<?= t('Close') ?>"><?= icon('x') ?></button></div>
    <?php if ($me): ?>
      <div class="field"><label for="r-reason"><?= t('Reason') ?></label>
        <select id="r-reason" name="reason">
          <?php foreach ($reportReasons as $r): ?><option value="<?= e($r) ?>"><?= t($r) ?></option><?php endforeach; ?>
        </select></div>
      <div class="field"><label for="r-details"><?= t('Details (optional)') ?></label><textarea id="r-details" name="details" rows="3" maxlength="2000"></textarea></div>
      <div class="modal-foot"><button class="btn btn-ghost" type="button" data-modal-close><?= t('Cancel') ?></button><button class="btn btn-danger" type="submit"><?= t('Send report') ?></button></div>
    <?php else: ?>
      <p><a href="<?= e(url('login.php')) ?>"><?= t('Sign in') ?></a> <?= t('to report an ad.') ?></p>
    <?php endif; ?>
  </form>
</dialog>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php';
