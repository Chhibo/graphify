<?php
require __DIR__ . '/includes/bootstrap.php';

$id = (int) input('id');
$l = q_one(listing_select() . ' WHERE l.id = ?', [$id]);
if (!$l) {
    not_found('This ad does not exist or has been removed.');
}
$me = current_user();
$isOwner = $me && (int) $me['id'] === (int) $l['user_id'];
$canManage = $isOwner || is_admin();
$seller = q_one('SELECT * FROM users WHERE id = ?', [$l['user_id']]);
$isLive = $l['status'] === 'active' && (!$l['expires_at'] || $l['expires_at'] > now()) && ($seller['status'] ?? '') === 'active';
if (!$isLive && $l['status'] !== 'sold' && !$canManage) {
    not_found('This ad is no longer available.');
}

/* ----- actions ----- */
if (is_post()) {
    $action = input('action');
    if (!$me) {
        $_SESSION['intended'] = url('listing.php?id=' . $id);
        flash('info', 'Please sign in first.');
        redirect('login.php');
    }
    if ($action === 'message' && !$isOwner) {
        $body = trim((string) ($_POST['body'] ?? ''));
        if (mb_strlen($body) < 2) {
            flash('error', 'Please write a message.');
            redirect('listing.php?id=' . $id . '#contact');
        }
        $conv = q_one('SELECT * FROM conversations WHERE listing_id = ? AND buyer_id = ?', [$id, $me['id']]);
        $convId = $conv ? (int) $conv['id'] : db_insert('conversations', [
            'listing_id' => $id, 'buyer_id' => $me['id'], 'seller_id' => $l['user_id'],
            'buyer_unread' => 0, 'seller_unread' => 0, 'last_message_at' => now(), 'created_at' => now(),
        ]);
        db_insert('messages', ['conversation_id' => $convId, 'sender_id' => $me['id'], 'body' => mb_substr($body, 0, 4000), 'created_at' => now()]);
        q('UPDATE conversations SET seller_unread = seller_unread + 1, last_message_at = ? WHERE id = ?', [now(), $convId]);
        if (setting('notify_new_message') === '1' && $seller) {
            send_mail($seller['email'], 'New message about "' . $l['title'] . '"',
                "Hi {$seller['name']},\n\n{$me['name']} sent you a message about your ad \"{$l['title']}\":\n\n$body\n\nReply here: " . full_url('dashboard/messages.php?c=' . $convId) . "\n\n— " . setting('site_name'));
        }
        flash('success', 'Message sent! You can follow the conversation in your inbox.');
        redirect('dashboard/messages.php?c=' . $convId);
    }
    if ($action === 'report') {
        $reason = input('reason');
        $reasons = ['Scam or fraud', 'Wrong category', 'Prohibited item', 'Duplicate ad', 'Offensive content', 'Item already sold', 'Other'];
        if (!in_array($reason, $reasons, true)) {
            $reason = 'Other';
        }
        db_insert('reports', ['listing_id' => $id, 'user_id' => $me['id'], 'reason' => $reason,
            'details' => mb_substr(input('details'), 0, 2000), 'status' => 'open', 'created_at' => now()]);
        flash('success', 'Thanks for letting us know. Our team will review this ad.');
        redirect('listing.php?id=' . $id);
    }
    redirect('listing.php?id=' . $id);
}

if (!$canManage && empty($_SESSION['viewed'][$id])) {
    $_SESSION['viewed'][$id] = 1;
    q('UPDATE listings SET views = views + 1 WHERE id = ?', [$id]);
    $l['views']++;
}

$images = q_all('SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id', [$id]);
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
$shareUrl = full_url('listing.php?id=' . $id);
$tips = array_filter(array_map('trim', explode("\n", setting('safety_tips'))));

$pageTitle = $l['title'];
$metaDesc = excerpt((string) $l['description'], 160);
$ogImage = $images ? full_url($images[0]['path']) : null;
require __DIR__ . '/includes/header.php';
?>
<section class="container section-sm">
  <nav class="breadcrumb">
    <a href="<?= e(url()) ?>">Home</a><?= icon('chevron-right') ?>
    <?php if ($parentCat): ?><a href="<?= e(url('listings.php?category=' . $parentCat['id'])) ?>"><?= e($parentCat['name']) ?></a><?= icon('chevron-right') ?><?php endif; ?>
    <?php if ($cat): ?><a href="<?= e(url('listings.php?category=' . $cat['id'])) ?>"><?= e($cat['name']) ?></a><?= icon('chevron-right') ?><?php endif; ?>
    <span><?= e(excerpt($l['title'], 40)) ?></span>
  </nav>

  <?php if ($canManage && !$isLive): ?>
    <div class="alert alert-warning"><?= icon('info') ?><span>This ad is <strong><?= e($l['status']) ?></strong> and is not visible to the public.<?= $l['reject_reason'] ? ' Reason: ' . e($l['reject_reason']) : '' ?></span></div>
  <?php endif; ?>

  <div class="detail-layout">
    <div class="detail-main">
      <div class="gallery card" data-gallery>
        <div class="gallery-stage">
          <?php if ($images): ?>
            <?php foreach ($images as $i => $img): ?>
              <img src="<?= e(upload_url($img['path'])) ?>" alt="<?= e($l['title']) ?> – photo <?= $i + 1 ?>" class="<?= $i === 0 ? 'on' : '' ?>" <?= $i ? 'loading="lazy"' : 'fetchpriority="high"' ?> data-index="<?= $i ?>">
            <?php endforeach; ?>
            <?php if (count($images) > 1): ?>
              <button class="g-nav prev" type="button" data-g-prev aria-label="Previous photo"><?= icon('chevron-left') ?></button>
              <button class="g-nav next" type="button" data-g-next aria-label="Next photo"><?= icon('chevron-right') ?></button>
              <span class="g-count"><span data-g-current>1</span> / <?= count($images) ?></span>
            <?php endif; ?>
          <?php else: ?>
            <div class="gallery-empty"><?= icon($l['category_icon'] ?: 'image') ?><span>No photos</span></div>
          <?php endif; ?>
          <?php if ($l['featured']): ?><span class="chip chip-featured"><?= icon('zap') ?>Featured</span><?php endif; ?>
          <?php if ($l['status'] === 'sold'): ?><span class="chip chip-sold">Sold</span><?php endif; ?>
        </div>
        <?php if (count($images) > 1): ?>
          <div class="gallery-thumbs">
            <?php foreach ($images as $i => $img): ?>
              <button type="button" class="<?= $i === 0 ? 'on' : '' ?>" data-g-thumb="<?= $i ?>" aria-label="Show photo <?= $i + 1 ?>"><img src="<?= e(upload_url($img['thumb'])) ?>" alt="" loading="lazy"></button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="card detail-card">
        <div class="detail-title-row">
          <div>
            <h1 class="detail-title"><?= e($l['title']) ?></h1>
            <div class="meta-row">
              <span><?= icon('map-pin') ?><?= e($l['location'] ?: 'Location not specified') ?></span>
              <span><?= icon('clock') ?>Posted <?= e(time_ago($l['created_at'])) ?></span>
              <span><?= icon('eye') ?><?= number_format((int) $l['views']) ?> views</span>
            </div>
          </div>
          <div class="detail-price">
            <strong><?= e(listing_price($l)) ?></strong>
            <?php if ($l['price_type'] === 'negotiable'): ?><span class="badge badge-success">Negotiable</span><?php endif; ?>
          </div>
        </div>

        <dl class="spec-grid">
          <?php if ($cat): ?><div><dt>Category</dt><dd><?= e($cat['name']) ?></dd></div><?php endif; ?>
          <?php if ($l['item_condition']): ?><div><dt>Condition</dt><dd><?= e(condition_label($l['item_condition'])) ?></dd></div><?php endif; ?>
          <div><dt>Posted on</dt><dd><?= e(format_date($l['created_at'])) ?></dd></div>
          <div><dt>Ad ID</dt><dd>#<?= (int) $l['id'] ?></dd></div>
        </dl>

        <h2 class="h-sm">Description</h2>
        <div class="description" data-collapsible><?= nl2br(e((string) $l['description'])) ?></div>

        <?php if ($tags): ?>
          <div class="tag-row">
            <?php foreach ($tags as $t): ?><a class="tag" href="<?= e(url('listings.php?q=' . urlencode($t))) ?>">#<?= e($t) ?></a><?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="action-row">
          <button class="btn btn-ghost fav-toggle<?= $isFav ? ' on' : '' ?>" type="button" data-fav="<?= $id ?>" aria-pressed="<?= $isFav ? 'true' : 'false' ?>"><?= icon('heart') ?><span data-fav-label><?= $isFav ? 'Saved' : 'Save' ?></span></button>
          <button class="btn btn-ghost" type="button" data-share data-url="<?= e($shareUrl) ?>" data-title="<?= e($l['title']) ?>"><?= icon('share') ?>Share</button>
          <?php if (!$isOwner): ?><button class="btn btn-ghost" type="button" data-modal-open="reportModal"><?= icon('flag') ?>Report</button><?php endif; ?>
          <span class="share-links">
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($shareUrl) ?>" target="_blank" rel="noopener" aria-label="Share on Facebook"><?= icon('facebook') ?></a>
            <a href="https://twitter.com/intent/tweet?url=<?= urlencode($shareUrl) ?>&text=<?= urlencode($l['title']) ?>" target="_blank" rel="noopener" aria-label="Share on X"><?= icon('twitter') ?></a>
            <a href="https://wa.me/?text=<?= urlencode($l['title'] . ' ' . $shareUrl) ?>" target="_blank" rel="noopener" aria-label="Share on WhatsApp"><?= icon('whatsapp') ?></a>
          </span>
        </div>
      </div>

      <?= ad_slot('listing') ?>

      <?php if (setting('enable_map') === '1' && $l['location'] !== '' && strtolower($l['location']) !== 'remote'): ?>
        <div class="card detail-card">
          <h2 class="h-sm"><?= icon('map-pin') ?>Location</h2>
          <p class="muted"><?= e($l['location']) ?></p>
          <div class="map-embed" data-map="<?= e($l['location']) ?>">
            <button class="btn btn-ghost" type="button" data-load-map><?= icon('globe') ?>Show on map</button>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <aside class="detail-side">
      <?php if ($canManage): ?>
        <div class="card side-card owner-card">
          <h3><?= $isOwner ? 'This is your ad' : 'Admin tools' ?></h3>
          <div class="btn-stack">
            <a class="btn btn-primary btn-block" href="<?= e(url(($isOwner ? 'dashboard/edit.php' : 'admin/listing-edit.php') . '?id=' . $id)) ?>"><?= icon('edit') ?>Edit ad</a>
            <?php if ($isOwner): ?><a class="btn btn-ghost btn-block" href="<?= e(url('dashboard/listings.php')) ?>"><?= icon('package') ?>Manage my ads</a><?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="card side-card seller-card">
        <a class="seller-head" href="<?= e(url('profile.php?id=' . $l['user_id'])) ?>">
          <?= avatar_html($seller, 'lg') ?>
          <span>
            <strong><?= e($seller['name'] ?? 'Unknown') ?><?php if (!empty($seller['verified'])): ?><span class="verified" title="Verified seller"><?= icon('shield') ?></span><?php endif; ?></strong>
            <small>Member since <?= e(date('M Y', strtotime($seller['created_at'] ?? 'now'))) ?></small>
            <?php if ($ratingCount): ?><span class="rating-line"><?= stars($rating) ?><small><?= $rating ?> (<?= $ratingCount ?>)</small></span><?php endif; ?>
          </span>
        </a>
        <div class="seller-stats"><span><strong><?= $sellerAds ?></strong> active ads</span><a href="<?= e(url('profile.php?id=' . $l['user_id'])) ?>">View profile<?= icon('arrow-right') ?></a></div>

        <?php if ($l['show_phone'] && $l['phone'] !== ''): ?>
          <?php if ($showPhone): ?>
            <button class="btn btn-dark btn-block phone-reveal" type="button" data-phone="<?= e($l['phone']) ?>">
              <?= icon('phone') ?><span data-phone-text><?= e(mb_substr($l['phone'], 0, max(3, mb_strlen($l['phone']) - 6))) ?> ••• ••</span><small>Show</small>
            </button>
          <?php else: ?>
            <a class="btn btn-dark btn-block" href="<?= e(url('login.php')) ?>"><?= icon('phone') ?>Sign in to see the phone number</a>
          <?php endif; ?>
        <?php endif; ?>

        <?php if (!$isOwner): ?>
          <form method="post" class="contact-form" id="contact">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="message">
            <label for="msg-body" class="label">Message the seller</label>
            <textarea id="msg-body" name="body" rows="4" required maxlength="4000"><?= e('Hi! Is "' . $l['title'] . '" still available?') ?></textarea>
            <?php if ($me): ?>
              <button class="btn btn-primary btn-block" type="submit"><?= icon('send') ?>Send message</button>
            <?php else: ?>
              <a class="btn btn-primary btn-block" href="<?= e(url('login.php')) ?>"><?= icon('log-in') ?>Sign in to send a message</a>
            <?php endif; ?>
          </form>
        <?php endif; ?>
      </div>

      <?php if ($tips): ?>
        <div class="card side-card tips-card">
          <h3><?= icon('shield') ?>Stay safe</h3>
          <ul class="check-list">
            <?php foreach ($tips as $t): ?><li><?= icon('check') ?><?= e($t) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <?= ad_slot('sidebar') ?>
    </aside>
  </div>

  <?php if ($related): ?>
    <div class="section-head mt-xl"><div><h2>Similar ads</h2></div>
      <?php if ($cat): ?><a class="link-arrow" href="<?= e(url('listings.php?category=' . $cat['id'])) ?>">More in <?= e($cat['name']) ?><?= icon('arrow-right') ?></a><?php endif; ?></div>
    <div class="listing-grid">
      <?php foreach ($related as $l) { include __DIR__ . '/includes/partials/listing-card.php'; } ?>
    </div>
  <?php endif; ?>
</section>

<?php if (!$isOwner): ?>
<dialog class="modal" id="reportModal">
  <form method="post" class="modal-body">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="report">
    <div class="modal-head"><h3><?= icon('flag') ?>Report this ad</h3><button class="icon-btn" type="button" data-modal-close aria-label="Close"><?= icon('x') ?></button></div>
    <?php if ($me): ?>
      <div class="field"><label for="r-reason">Reason</label>
        <select id="r-reason" name="reason">
          <?php foreach (['Scam or fraud', 'Wrong category', 'Prohibited item', 'Duplicate ad', 'Offensive content', 'Item already sold', 'Other'] as $r): ?><option><?= e($r) ?></option><?php endforeach; ?>
        </select></div>
      <div class="field"><label for="r-details">Details (optional)</label><textarea id="r-details" name="details" rows="3" maxlength="2000"></textarea></div>
      <div class="modal-foot"><button class="btn btn-ghost" type="button" data-modal-close>Cancel</button><button class="btn btn-danger" type="submit">Send report</button></div>
    <?php else: ?>
      <p>Please <a href="<?= e(url('login.php')) ?>">sign in</a> to report an ad.</p>
    <?php endif; ?>
  </form>
</dialog>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php';
