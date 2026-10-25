<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_login();
$uid = (int) $me['id'];

$convId = (int) input('c');
$conv = null;
if ($convId) {
    $conv = q_one('SELECT c.*, l.title, l.price, l.price_type, l.status AS listing_status
        FROM conversations c JOIN listings l ON l.id = c.listing_id
        WHERE c.id = ? AND (c.buyer_id = ? OR c.seller_id = ?)', [$convId, $uid, $uid]);
    if (!$conv) {
        flash('error', __('Conversation not found.'));
        redirect('dashboard/messages.php');
    }
}
$here = 'dashboard/messages.php?c=' . $convId;

if (is_post() && $conv) {
    $action = input('action');
    $otherId = (int) $conv['buyer_id'] === $uid ? (int) $conv['seller_id'] : (int) $conv['buyer_id'];
    if ($action === 'delete') {
        q('DELETE FROM messages WHERE conversation_id = ?', [$conv['id']]);
        q('DELETE FROM offers WHERE conversation_id = ?', [$conv['id']]);
        q('DELETE FROM conversations WHERE id = ?', [$conv['id']]);
        flash('success', __('Conversation deleted.'));
        redirect('dashboard/messages.php');
    }
    if ($action === 'block') {
        if (!is_blocked($uid, $otherId)) {
            db_insert('user_blocks', ['user_id' => $uid, 'blocked_id' => $otherId, 'created_at' => now()]);
        }
        flash('success', __('Member blocked. They can no longer message you.'));
        redirect($here);
    }
    if ($action === 'unblock') {
        q('DELETE FROM user_blocks WHERE user_id = ? AND blocked_id = ?', [$uid, $otherId]);
        flash('success', __('Member unblocked.'));
        redirect($here);
    }
    require_verified_email($here);
    if ($action === 'offer') {
        $offer = q_one('SELECT * FROM offers WHERE id = ? AND conversation_id = ?', [(int) input('offer_id'), $conv['id']]);
        $res = $offer ? respond_offer($offer, $me, input('do'), (float) str_replace([',', ' '], '', input('counter'))) : __('Offer not found.');
        if ($res !== true) {
            flash('error', $res);
        }
        redirect($here . '#bottom');
    }
    // normal message, optionally with a photo (sent with fetch() by app.js, or as a normal form post)
    $ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
    $image = null;
    $res = true;
    $file = normalize_files($_FILES['photo'] ?? null)[0] ?? null;
    if ($file && setting('chat_photos', '1') === '1') {
        $stored = store_image($file, 'chat', 1400, 360);
        is_string($stored) ? $res = $stored : $image = $stored;
    }
    if ($res === true) {
        $res = post_message((int) $conv['listing_id'], $me, (string) ($_POST['body'] ?? ''), $conv, $image);
        if (is_string($res) && $image) {
            delete_upload($image['path']);
            delete_upload($image['thumb']);
        }
    }
    if ($ajax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => !is_string($res), 'error' => is_string($res) ? $res : '']);
        exit;
    }
    if (is_string($res)) {
        flash('error', $res);
    }
    redirect($here . '#bottom');
}

if ($conv) {
    mark_conversation_read($conv, $uid);
}

$convs = q_all('SELECT c.*, l.title, CASE WHEN c.buyer_id = ? THEN c.buyer_unread ELSE c.seller_unread END AS unread,
        u.name AS other_name, u.avatar AS other_avatar, u.id AS other_id,
        (SELECT body FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_body,
        (SELECT image FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_image
        FROM conversations c
        JOIN listings l ON l.id = c.listing_id
        JOIN users u ON u.id = CASE WHEN c.buyer_id = ? THEN c.seller_id ELSE c.buyer_id END
        WHERE c.buyer_id = ? OR c.seller_id = ? ORDER BY c.last_message_at DESC', [$uid, $uid, $uid, $uid]);

$messages = [];
$other = null;
$offer = null;
$iBlocked = $theyBlocked = false;
if ($conv) {
    $messages = q_all('SELECT * FROM messages WHERE conversation_id = ? ORDER BY id', [$conv['id']]);
    $other = q_one('SELECT id, name, username, avatar, verified FROM users WHERE id = ?', [(int) $conv['buyer_id'] === $uid ? $conv['seller_id'] : $conv['buyer_id']])
        ?? ['id' => 0, 'name' => __('Deleted member'), 'username' => null, 'avatar' => '', 'verified' => 0];
    $iBlocked = is_blocked($uid, (int) $other['id']);
    $theyBlocked = is_blocked((int) $other['id'], $uid);
    $offer = q_one('SELECT * FROM offers WHERE conversation_id = ? ORDER BY id DESC LIMIT 1', [$conv['id']]);
}
$isSeller = $conv && (int) $conv['seller_id'] === $uid;
$canReply = $conv && !$iBlocked && !$theyBlocked && $other['id'];

$dashActive = 'messages';
$pageTitle = __('Messages');
require APP_ROOT . '/includes/dash-top.php';
?>
<div class="dash-head"><div><h1><?= t('Messages') ?></h1><p class="muted"><?= t('Conversations with buyers and sellers.') ?></p></div></div>

<div class="card inbox<?= $conv ? ' has-thread' : '' ?>">
  <div class="inbox-list">
    <?php if ($convs): foreach ($convs as $c): ?>
      <a href="?c=<?= (int) $c['id'] ?>" class="conv<?= $conv && (int) $conv['id'] === (int) $c['id'] ? ' on' : '' ?><?= $c['unread'] ? ' unread' : '' ?>">
        <?= avatar_html(['id' => $c['other_id'], 'name' => $c['other_name'], 'avatar' => $c['other_avatar']]) ?>
        <span class="conv-body">
          <span class="conv-top"><strong><?= e($c['other_name']) ?></strong><small><?= e(time_ago($c['last_message_at'])) ?></small></span>
          <span class="conv-title"><?= e(excerpt($c['title'], 40)) ?></span>
          <span class="conv-last"><?= $c['last_body'] !== '' && $c['last_body'] !== null ? e(excerpt((string) $c['last_body'], 60)) : ($c['last_image'] ? icon('image') . t('Photo') : '') ?></span>
        </span>
        <?php if ($c['unread']): ?><span class="dot"></span><?php endif; ?>
      </a>
    <?php endforeach; else: ?>
      <div class="empty-state small"><?= icon('inbox') ?><p><?= t('No conversations yet. When someone messages you about an ad, it will appear here.') ?></p></div>
    <?php endif; ?>
  </div>

  <div class="thread">
    <?php if ($conv): ?>
      <div class="thread-head">
        <a class="icon-btn mobile-only" href="<?= e(url('dashboard/messages.php')) ?>" aria-label="<?= t('Back') ?>"><?= icon('chevron-left') ?></a>
        <?= avatar_html($other) ?>
        <div class="thread-who"><a href="<?= e(profile_url((int) $other['id'], $other['username'])) ?>"><strong><?= e($other['name']) ?></strong></a>
          <a class="small-link" href="<?= e(listing_url(['id' => $conv['listing_id'], 'title' => $conv['title']])) ?>"><?= e(excerpt($conv['title'], 50)) ?> · <?= e(listing_price($conv)) ?></a></div>
        <form method="post" <?= $iBlocked ? '' : 'data-confirm="' . t('Block this member? They won\'t be able to message you any more.') . '"' ?>><?= csrf_field() ?><input type="hidden" name="c" value="<?= (int) $conv['id'] ?>"><input type="hidden" name="action" value="<?= $iBlocked ? 'unblock' : 'block' ?>"><button class="icon-btn" type="submit" aria-label="<?= $iBlocked ? t('Unblock member') : t('Block member') ?>" title="<?= $iBlocked ? t('Unblock member') : t('Block member') ?>"><?= icon('ban') ?></button></form>
        <form method="post" data-confirm="<?= t('Delete this conversation for both participants?') ?>"><?= csrf_field() ?><input type="hidden" name="c" value="<?= (int) $conv['id'] ?>"><input type="hidden" name="action" value="delete"><button class="icon-btn" type="submit" aria-label="<?= t('Delete conversation') ?>"><?= icon('trash') ?></button></form>
      </div>

      <?php if ($offer): $open = in_array($offer['status'], ['pending', 'countered'], true); $shown = $offer['status'] === 'countered' ? $offer['counter_amount'] : $offer['amount']; ?>
        <div class="offer-panel status-<?= e($offer['status']) ?>">
          <div class="offer-info"><?= icon('hand-coins') ?>
            <div><strong><?= $offer['status'] === 'countered' ? t('Counter-offer: {amount}', ['amount' => money($shown)]) : t('Offer: {amount}', ['amount' => money($shown)]) ?></strong>
              <small><?= e(offer_status_label($offer['status'])) ?><?= $offer['status'] === 'countered' ? ' · ' . t('original offer {amount}', ['amount' => money($offer['amount'])]) : '' ?></small></div></div>
          <?php if ($open && $canReply): ?>
            <form method="post" class="offer-actions">
              <?= csrf_field() ?><input type="hidden" name="c" value="<?= (int) $conv['id'] ?>"><input type="hidden" name="action" value="offer"><input type="hidden" name="offer_id" value="<?= (int) $offer['id'] ?>">
              <?php if ($isSeller && $offer['status'] === 'pending'): ?>
                <button class="btn btn-success btn-sm" name="do" value="accept"><?= icon('check') ?><?= t('Accept') ?></button>
                <button class="btn btn-ghost btn-sm" name="do" value="decline"><?= t('Decline') ?></button>
                <span class="counter-box"><span class="input-prefix sm"><span><?= e(setting('currency_symbol')) ?></span><input name="counter" inputmode="decimal" placeholder="<?= t('Counter') ?>" aria-label="<?= t('Counter-offer amount') ?>"></span>
                <button class="btn btn-ghost btn-sm" name="do" value="counter"><?= t('Counter') ?></button></span>
              <?php elseif (!$isSeller && $offer['status'] === 'countered'): ?>
                <button class="btn btn-success btn-sm" name="do" value="accept_counter"><?= icon('check') ?><?= t('Accept {amount}', ['amount' => money($offer['counter_amount'])]) ?></button>
                <button class="btn btn-ghost btn-sm" name="do" value="decline_counter"><?= t('Decline') ?></button>
              <?php elseif (!$isSeller): ?>
                <button class="btn btn-ghost btn-sm" name="do" value="withdraw"><?= t('Withdraw offer') ?></button>
              <?php else: ?>
                <span class="muted small"><?= t('Waiting for the buyer') ?></span>
              <?php endif; ?>
            </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="thread-messages" data-scroll-bottom data-thread="<?= (int) $conv['id'] ?>" data-last="<?= (int) (end($messages)['id'] ?? 0) ?>" data-day="<?= e($messages ? format_date(end($messages)['created_at']) : '') ?>" data-endpoint="<?= e(url('ajax.php')) ?>">
        <?php $lastDay = ''; foreach ($messages as $m) { echo render_chat_message($m, $uid, $lastDay); } ?>
        <span id="bottom"></span>
      </div>
      <?php if (!$canReply): ?>
      <div class="thread-blocked"><?= icon('ban') ?><?= !$other['id'] ? t('This member has left.') : ($iBlocked ? t('You blocked this member. Unblock them to reply.') : t('This member is not accepting messages from you.')) ?></div>
      <?php else: ?>
      <?php if ($qr = quick_replies()): ?>
        <div class="quick-replies" data-quick-replies>
          <?php foreach ($qr as $q): ?><button type="button" class="pill"><?= t($q) ?></button><?php endforeach; ?>
        </div>
      <?php endif; ?>
      <form method="post" class="thread-reply" enctype="multipart/form-data" data-chat-form>
        <?= csrf_field() ?>
        <input type="hidden" name="c" value="<?= (int) $conv['id'] ?>">
        <?php if (setting('chat_photos', '1') === '1'): ?>
          <label class="icon-btn attach-btn" title="<?= t('Attach a photo') ?>"><?= icon('paperclip') ?><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-chat-photo></label>
        <?php endif; ?>
        <div class="reply-box">
          <div class="attach-preview" data-attach-preview hidden></div>
          <textarea name="body" rows="1" placeholder="<?= t('Write a message…') ?>" maxlength="4000" data-autogrow data-enter-submit></textarea>
        </div>
        <button class="btn btn-primary" type="submit" aria-label="<?= t('Send') ?>"><?= icon('send') ?></button>
      </form>
      <?php endif; ?>
    <?php else: ?>
      <div class="thread-empty"><?= icon('message') ?><p><?= t('Select a conversation to read it.') ?></p></div>
    <?php endif; ?>
  </div>
</div>
<?php require APP_ROOT . '/includes/dash-bottom.php';
