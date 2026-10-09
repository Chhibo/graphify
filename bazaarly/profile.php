<?php
require __DIR__ . '/includes/bootstrap.php';

$id = (int) input('id');
$seller = q_one("SELECT * FROM users WHERE id = ?", [$id]);
if (!$seller || ($seller['status'] !== 'active' && !is_admin())) {
    not_found('This member could not be found.');
}
$me = current_user();
$reviewsOn = setting('enable_reviews') === '1';

if (is_post() && input('action') === 'review' && $reviewsOn) {
    if (!$me) {
        redirect('login.php');
    }
    if ((int) $me['id'] === $id) {
        flash('error', 'You cannot review yourself.');
        redirect('profile.php?id=' . $id);
    }
    $rating = max(1, min(5, (int) input('rating')));
    $comment = mb_substr(input('comment'), 0, 1000);
    $existing = q_one('SELECT id FROM reviews WHERE seller_id = ? AND reviewer_id = ?', [$id, $me['id']]);
    if ($existing) {
        db_update('reviews', ['rating' => $rating, 'comment' => $comment, 'created_at' => now()], 'id = ?', [$existing['id']]);
        flash('success', 'Your review has been updated.');
    } else {
        db_insert('reviews', ['seller_id' => $id, 'reviewer_id' => $me['id'], 'rating' => $rating, 'comment' => $comment, 'created_at' => now()]);
        flash('success', 'Thanks for your review!');
    }
    redirect('profile.php?id=' . $id . '#reviews');
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
  <nav class="breadcrumb"><a href="<?= e(url()) ?>">Home</a><?= icon('chevron-right') ?><span>Seller profile</span></nav>

  <div class="card profile-hero">
    <?= avatar_html($seller, 'xl') ?>
    <div class="profile-info">
      <h1><?= e($seller['name']) ?><?php if ($seller['verified']): ?><span class="badge badge-success"><?= icon('shield') ?>Verified</span><?php endif; ?></h1>
      <p class="muted">@<?= e($seller['username']) ?> · Member since <?= e(date('F Y', strtotime($seller['created_at']))) ?></p>
      <?php if ($seller['bio']): ?><p><?= nl2br(e($seller['bio'])) ?></p><?php endif; ?>
      <div class="meta-row">
        <?php if ($seller['location']): ?><span><?= icon('map-pin') ?><?= e($seller['location']) ?></span><?php endif; ?>
        <?php if ($ratingCount): ?><span><?= stars($rating) ?><?= $rating ?> · <?= $ratingCount ?> review<?= $ratingCount > 1 ? 's' : '' ?></span><?php endif; ?>
      </div>
    </div>
    <div class="profile-stats">
      <div><strong><?= $total ?></strong><span>Active ads</span></div>
      <div><strong><?= $sold ?></strong><span>Sold</span></div>
      <div><strong><?= $ratingCount ?></strong><span>Reviews</span></div>
    </div>
  </div>

  <div class="profile-layout">
    <div>
      <h2 class="h-md">Ads by <?= e(strtok($seller['name'], ' ')) ?></h2>
      <?php if ($listings): ?>
        <div class="listing-grid cols-3">
          <?php foreach ($listings as $l) { include __DIR__ . '/includes/partials/listing-card.php'; } ?>
        </div>
        <?= pagination_links($page, $pages) ?>
      <?php else: ?>
        <div class="empty-state"><?= icon('package') ?><h3>No active ads</h3><p>This seller has nothing listed right now.</p></div>
      <?php endif; ?>
    </div>

    <?php if ($reviewsOn): ?>
    <aside id="reviews">
      <div class="card side-card">
        <h3>Reviews</h3>
        <?php if ($me && (int) $me['id'] !== $id): ?>
          <form method="post" class="review-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="review">
            <div class="star-input" role="radiogroup" aria-label="Rating">
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" <?= (int) ($myReview['rating'] ?? 5) === $i ? 'checked' : '' ?>><label for="star<?= $i ?>" title="<?= $i ?> stars"><?= icon('star') ?></label>
              <?php endfor; ?>
            </div>
            <textarea name="comment" rows="3" maxlength="1000" placeholder="How was your experience with this seller?"><?= e($myReview['comment'] ?? '') ?></textarea>
            <button class="btn btn-primary btn-sm" type="submit"><?= $myReview ? 'Update review' : 'Post review' ?></button>
          </form>
        <?php elseif (!$me): ?>
          <p class="muted small"><a href="<?= e(url('login.php')) ?>">Sign in</a> to leave a review.</p>
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
          <p class="muted small">No reviews yet.</p>
        <?php endif; ?>
      </div>
    </aside>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php';
