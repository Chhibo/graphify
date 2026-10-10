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
        flash('error', 'Conversation not found.');
        redirect('dashboard/messages.php');
    }
}

if (is_post() && $conv) {
    if (input('action') === 'delete') {
        q('DELETE FROM messages WHERE conversation_id = ?', [$conv['id']]);
        q('DELETE FROM conversations WHERE id = ?', [$conv['id']]);
        flash('success', 'Conversation deleted.');
        redirect('dashboard/messages.php');
    }
    $otherId = (int) $conv['buyer_id'] === $uid ? (int) $conv['seller_id'] : (int) $conv['buyer_id'];
    if (input('action') === 'block') {
        if (!is_blocked($uid, $otherId)) {
            db_insert('user_blocks', ['user_id' => $uid, 'blocked_id' => $otherId, 'created_at' => now()]);
        }
        flash('success', 'Member blocked. They can no longer message you.');
        redirect('dashboard/messages.php?c=' . $conv['id']);
    }
    if (input('action') === 'unblock') {
        q('DELETE FROM user_blocks WHERE user_id = ? AND blocked_id = ?', [$uid, $otherId]);
        flash('success', 'Member unblocked.');
        redirect('dashboard/messages.php?c=' . $conv['id']);
    }
    require_verified_email('dashboard/messages.php?c=' . $conv['id']);
    $res = post_message((int) $conv['listing_id'], $me, (string) ($_POST['body'] ?? ''), $conv);
    if (is_string($res)) {
        flash('error', $res);
    }
    redirect('dashboard/messages.php?c=' . $conv['id'] . '#bottom');
}

if ($conv) {
    $col = (int) $conv['buyer_id'] === $uid ? 'buyer_unread' : 'seller_unread';
    q("UPDATE conversations SET $col = 0 WHERE id = ?", [$conv['id']]);
}

$convs = q_all('SELECT c.*, l.title, CASE WHEN c.buyer_id = ? THEN c.buyer_unread ELSE c.seller_unread END AS unread,
        u.name AS other_name, u.avatar AS other_avatar, u.id AS other_id,
        (SELECT body FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_body,
        (SELECT thumb FROM listing_images i WHERE i.listing_id = c.listing_id ORDER BY i.sort_order, i.id LIMIT 1) AS cover
        FROM conversations c
        JOIN listings l ON l.id = c.listing_id
        JOIN users u ON u.id = CASE WHEN c.buyer_id = ? THEN c.seller_id ELSE c.buyer_id END
        WHERE c.buyer_id = ? OR c.seller_id = ? ORDER BY c.last_message_at DESC', [$uid, $uid, $uid, $uid]);

$messages = [];
$other = null;
$iBlocked = $theyBlocked = false;
if ($conv) {
    $messages = q_all('SELECT * FROM messages WHERE conversation_id = ? ORDER BY id', [$conv['id']]);
    $other = q_one('SELECT id, name, username, avatar, verified FROM users WHERE id = ?', [(int) $conv['buyer_id'] === $uid ? $conv['seller_id'] : $conv['buyer_id']])
        ?? ['id' => 0, 'name' => 'Deleted member', 'username' => null, 'avatar' => '', 'verified' => 0];
    $iBlocked = is_blocked($uid, (int) $other['id']);
    $theyBlocked = is_blocked((int) $other['id'], $uid);
}

$dashActive = 'messages';
$pageTitle = 'Messages';
require APP_ROOT . '/includes/dash-top.php';
?>
<div class="dash-head"><div><h1>Messages</h1><p class="muted">Conversations with buyers and sellers.</p></div></div>

<div class="card inbox<?= $conv ? ' has-thread' : '' ?>">
  <div class="inbox-list">
    <?php if ($convs): foreach ($convs as $c): ?>
      <a href="?c=<?= (int) $c['id'] ?>" class="conv<?= $conv && (int) $conv['id'] === (int) $c['id'] ? ' on' : '' ?><?= $c['unread'] ? ' unread' : '' ?>">
        <?= avatar_html(['id' => $c['other_id'], 'name' => $c['other_name'], 'avatar' => $c['other_avatar']]) ?>
        <span class="conv-body">
          <span class="conv-top"><strong><?= e($c['other_name']) ?></strong><small><?= e(time_ago($c['last_message_at'])) ?></small></span>
          <span class="conv-title"><?= e(excerpt($c['title'], 40)) ?></span>
          <span class="conv-last"><?= e(excerpt((string) $c['last_body'], 60)) ?></span>
        </span>
        <?php if ($c['unread']): ?><span class="dot"></span><?php endif; ?>
      </a>
    <?php endforeach; else: ?>
      <div class="empty-state small"><?= icon('inbox') ?><p>No conversations yet. When someone messages you about an ad, it will appear here.</p></div>
    <?php endif; ?>
  </div>

  <div class="thread">
    <?php if ($conv): ?>
      <div class="thread-head">
        <a class="icon-btn mobile-only" href="<?= e(url('dashboard/messages.php')) ?>" aria-label="Back"><?= icon('chevron-left') ?></a>
        <?= avatar_html($other) ?>
        <div class="thread-who"><a href="<?= e(profile_url((int) $other['id'], $other['username'])) ?>"><strong><?= e($other['name']) ?></strong></a>
          <a class="small-link" href="<?= e(listing_url(['id' => $conv['listing_id'], 'title' => $conv['title']])) ?>"><?= e(excerpt($conv['title'], 50)) ?> · <?= e(listing_price($conv)) ?></a></div>
        <form method="post" <?= $iBlocked ? '' : 'data-confirm="Block this member? They won\'t be able to message you any more."' ?>><?= csrf_field() ?><input type="hidden" name="c" value="<?= (int) $conv['id'] ?>"><input type="hidden" name="action" value="<?= $iBlocked ? 'unblock' : 'block' ?>"><button class="icon-btn" type="submit" aria-label="<?= $iBlocked ? 'Unblock member' : 'Block member' ?>" title="<?= $iBlocked ? 'Unblock member' : 'Block member' ?>"><?= icon('ban') ?></button></form>
        <form method="post" data-confirm="Delete this conversation for both participants?"><?= csrf_field() ?><input type="hidden" name="c" value="<?= (int) $conv['id'] ?>"><input type="hidden" name="action" value="delete"><button class="icon-btn" type="submit" aria-label="Delete conversation"><?= icon('trash') ?></button></form>
      </div>
      <div class="thread-messages" data-scroll-bottom>
        <?php $lastDay = ''; foreach ($messages as $m): $day = format_date($m['created_at']); ?>
          <?php if ($day !== $lastDay): $lastDay = $day; ?><div class="day-sep"><span><?= e($day) ?></span></div><?php endif; ?>
          <div class="bubble <?= (int) $m['sender_id'] === $uid ? 'mine' : 'theirs' ?>">
            <p><?= nl2br(e($m['body'])) ?></p>
            <time><?= e(date('H:i', strtotime($m['created_at']))) ?></time>
          </div>
        <?php endforeach; ?>
        <span id="bottom"></span>
      </div>
      <?php if ($iBlocked || $theyBlocked || !$other['id']): ?>
      <div class="thread-blocked"><?= icon('ban') ?><?= !$other['id'] ? 'This member has left.' : ($iBlocked ? 'You blocked this member. Unblock them to reply.' : 'This member is not accepting messages from you.') ?></div>
      <?php else: ?>
      <form method="post" class="thread-reply">
        <?= csrf_field() ?>
        <input type="hidden" name="c" value="<?= (int) $conv['id'] ?>">
        <textarea name="body" rows="1" placeholder="Write a message…" required maxlength="4000" data-autogrow data-enter-submit></textarea>
        <button class="btn btn-primary" type="submit" aria-label="Send"><?= icon('send') ?></button>
      </form>
      <?php endif; ?>
    <?php else: ?>
      <div class="thread-empty"><?= icon('message') ?><p>Select a conversation to read it.</p></div>
    <?php endif; ?>
  </div>
</div>
<?php require APP_ROOT . '/includes/dash-bottom.php';
