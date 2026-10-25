<?php
require __DIR__ . '/includes/bootstrap.php';

$seller = input('username') !== ''
    ? q_one('SELECT * FROM users WHERE username = ?', [input('username')])
    : q_one('SELECT * FROM users WHERE id = ?', [(int) input('id')]);
$id = (int) ($seller['id'] ?? 0);
if (!$seller || ($seller['status'] !== 'active' && !is_admin())) {
    not_found(__('This member could not be found.'));
}
$me = current_user();
$reviewsOn = setting('enable_reviews') === '1';

$selfUrl = profile_url($id, $seller['username'] ?? null);
if (is_post() && $me && in_array(input('action'), ['block', 'unblock'], true) && (int) $me['id'] !== $id) {
    if (input('action') === 'block') {
        if (!is_blocked((int) $me['id'], $id)) {
            db_insert('user_blocks', ['user_id' => $me['id'], 'blocked_id' => $id, 'created_at' => now()]);
        }
        flash('success', $seller['name'] . ' is blocked and can no longer message you.');
    } else {
        q('DELETE FROM user_blocks WHERE user_id = ? AND blocked_id = ?', [$me['id'], $id]);
        flash('success', $seller['name'] . ' is unblocked.');
    }
    header('Location: ' . $selfUrl);
    exit;
}
if (is_post() && input('action') === 'review' && $reviewsOn) {
    if (!$me) {
        redirect('login.php');
    }
    require_verified_email();
    if (($bad = find_banned_word(input('comment'))) !== null && $me['role'] !== 'admin') {
        flash('error', __('Your review contains a word or phrase that is not allowed (“{word}”).', ['word' => $bad]));
        header('Location: ' . $selfUrl . '#reviews');
        exit;
    }
    if ((int) $me['id'] === $id) {
        flash('error', __('You cannot review yourself.'));
        header('Location: ' . $selfUrl);
        exit;
    }
    $rating = max(1, min(5, (int) input('rating')));
    $comment = mb_substr(input('comment'), 0, 1000);
    $existing = q_one('SELECT id FROM reviews WHERE seller_id = ? AND reviewer_id = ?', [$id, $me['id']]);
    if ($existing) {
        db_update('reviews', ['rating' => $rating, 'comment' => $comment, 'created_at' => now()], 'id = ?', [$existing['id']]);
        flash('success', __('Your review has been updated.'));
    } else {
        db_insert('reviews', ['seller_id' => $id, 'reviewer_id' => $me['id'], 'rating' => $rating, 'comment' => $comment, 'created_at' => now()]);
        flash('success', __('Thanks for your review!'));
    }
    header('Location: ' . $selfUrl . '#reviews');
    exit;
}

[$where, $params] = visible_listing_where();
$total = (int) q_val("SELECT COUNT(*) FROM listings l JOIN users u ON u.id = l.user_id WHERE $where AND l.user_id = ?", array_merge($params, [$id]));
[$page, $pages, $offset] = paginate($total, 12, (int) input('page', '1'));
$listings = q_all(listing_select() . " WHERE $where AND l.user_id = ? ORDER BY l.created_at DESC LIMIT 12 OFFSET $offset", array_merge($params, [$id]));
$reviews = q_all('SELECT r.*, u.name, u.avatar, u.id AS uid FROM reviews r JOIN users u ON u.id = r.reviewer_id WHERE r.seller_id = ? ORDER BY r.created_at DESC LIMIT 50', [$id]);
[$rating, $ratingCount] = seller_rating($id);
$myReview = $me ? q_one('SELECT * FROM reviews WHERE seller_id = ? AND reviewer_id = ?', [$id, $me['id']]) : null;
$sold = (int) q_val("SELECT COUNT(*) FROM listings WHERE user_id = ? AND status = 'sold'", [$id]);

$pageTitle = $seller['name'];
require __DIR__ . '/includes/header.php';
?>
<section class="container section-sm">
  <nav class="breadcrumb"><a href="<?= e(url()) ?>"><?= t('Home') ?></a><?= icon('chevron-right') ?><span><?= t('Seller profile') ?></span></nav>

  <div class="card profile-hero">
    <?= avatar_html($seller, 'xl') ?>
    <div class="profile-info">
      <h1><?= e($seller['name']) ?><?php if ($seller['verified']): ?><span class="badge badge-success"><?= icon('shield') ?><?= t('Verified') ?></span><?php endif; ?></h1>
      <p class="muted">@<?= e($seller['username']) ?> · <?= t('Member since {date}', ['date' => format_month($seller['created_at'])]) ?></p>
      <?php if ($seller['bio']): ?><p><?= nl2br(e($seller['bio'])) ?></p><?php endif; ?>
      <div class="meta-row">
        <?php if ($seller['location']): ?><span><?= icon('map-pin') ?><?= e($seller['location']) ?></span><?php endif; ?>
        <?php if ($ratingCount): ?><span><?= stars($rating) ?><?= $rating ?> · <?= e(n_('{n} review', '{n} reviews', $ratingCount)) ?></span><?php endif; ?>
        <?php if (shops_enabled() && ($pShop = user_shop($id))): ?><a href="<?= e(shop_url($pShop)) ?>"><?= icon('shop') ?><?= t('Visit {shop}', ['shop' => $pShop['name']]) ?></a><?php endif; ?>
      </div>
    </div>
    <?php if ($me && (int) $me['id'] !== $id): $iBlocked = is_blocked((int) $me['id'], $id); ?>
      <form method="post" class="profile-block" <?= $iBlocked ? '' : 'data-confirm="' . t('Block this member? They won\'t be able to message you.') . '"' ?>>
        <?= csrf_field() ?><input type="hidden" name="action" value="<?= $iBlocked ? 'unblock' : 'block' ?>">
        <button class="btn btn-ghost btn-sm<?= $iBlocked ? '' : ' danger' ?>" type="submit"><?= icon('ban') ?><?= $iBlocked ? t('Unblock') : t('Block') ?></button>
      </form>
    <?php endif; ?>
    <div class="profile-stats">
      <div><strong><?= $total ?></strong><span><?= t('Active ads') ?></span></div>
      <div><strong><?= $sold ?></strong><span><?= t('Sold') ?></span></div>
      <div><strong><?= $ratingCount ?></strong><span><?= t('Reviews') ?></span></div>
    </div>
  </div>

  <div class="profile-layout">
    <div>
      <h2 class="h-md"><?= t('Ads by {name}', ['name' => strtok($seller['name'], ' ')]) ?></h2>
      <?php if ($listings): ?>
        <div class="listing-grid cols-3">
          <?php foreach ($listings as $l) { include __DIR__ . '/includes/partials/listing-card.php'; } ?>
        </div>
        <?= pagination_links($page, $pages) ?>
      <?php else: ?>
        <div class="empty-state"><?= icon('package') ?><h3><?= t('No active ads') ?></h3><p><?= t('This seller has nothing listed right now.') ?></p></div>
      <?php endif; ?>
    </div>

    <?php if ($reviewsOn): ?>
    <aside id="reviews">
      <div class="card side-card">
        <h3><?= t('Reviews') ?></h3>
        <?php if ($me && (int) $me['id'] !== $id): ?>
          <form method="post" class="review-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="review">
            <div class="star-input" role="radiogroup" aria-label="<?= t('Rating') ?>">
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" <?= (int) ($myReview['rating'] ?? 5) === $i ? 'checked' : '' ?>><label for="star<?= $i ?>" title="<?= $i ?> stars"><?= icon('star') ?></label>
              <?php endfor; ?>
            </div>
            <textarea name="comment" rows="3" maxlength="1000" placeholder="<?= t('How was your experience with this seller?') ?>"><?= e($myReview['comment'] ?? '') ?></textarea>
            <button class="btn btn-primary btn-sm" type="submit"><?= $myReview ? t('Update review') : t('Post review') ?></button>
          </form>
        <?php elseif (!$me): ?>
          <p class="muted small"><a href="<?= e(url('login.php')) ?>"><?= t('Sign in') ?></a> <?= t('to leave a review.') ?></p>
        <?php endif; ?>

        <?php if ($reviews): ?>
          <ul class="review-list">
            <?php foreach ($reviews as $r): ?>
              <li>
                <div class="review-head"><?= avatar_html(['id' => $r['uid'], 'name' => $r['name'], 'avatar' => $r['avatar']], 'sm') ?>
                  <div><strong><?= e($r['name']) ?></strong><?= stars((float) $r['rating'], 'sm') ?></div>
                  <small class="muted"><?= e(time_ago($r['created_at'])) ?></small></div>
                <?php if ($r['comment']): ?><p><?= nl2br(e($r['comment'])) ?></p><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="muted small"><?= t('No reviews yet.') ?></p>
        <?php endif; ?>
      </div>
    </aside>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php';
