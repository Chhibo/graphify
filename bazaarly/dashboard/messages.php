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
    $body = trim((string) ($_POST['body'] ?? ''));
    if ($body !== '') {
        db_insert('messages', ['conversation_id' => $conv['id'], 'sender_id' => $uid, 'body' => mb_substr($body, 0, 4000), 'created_at' => now()]);
        $col = (int) $conv['buyer_id'] === $uid ? 'seller_unread' : 'buyer_unread';
        q("UPDATE conversations SET $col = $col + 1, last_message_at = ? WHERE id = ?", [now(), $conv['id']]);
        $otherId = (int) $conv['buyer_id'] === $uid ? $conv['seller_id'] : $conv['buyer_id'];
        $other = q_one('SELECT name, email FROM users WHERE id = ?', [$otherId]);
        if ($other && setting('notify_new_message') === '1') {
            send_mail($other['email'], 'New reply about "' . $conv['title'] . '"',
                "Hi {$other['name']},\n\n{$me['name']} replied:\n\n$body\n\nOpen the conversation: " . full_url('dashboard/messages.php?c=' . $conv['id']) . "\n\n— " . setting('site_name'));
        }
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
if ($conv) {
    $messages = q_all('SELECT * FROM messages WHERE conversation_id = ? ORDER BY id', [$conv['id']]);
    $other = q_one('SELECT id, name, avatar, verified FROM users WHERE id = ?', [(int) $conv['buyer_id'] === $uid ? $conv['seller_id'] : $conv['buyer_id']]);
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
        <div class="thread-who"><a href="<?= e(url('profile.php?id=' . $other['id'])) ?>"><strong><?= e($other['name']) ?></strong></a>
          <a class="small-link" href="<?= e(url('listing.php?id=' . $conv['listing_id'])) ?>"><?= e(excerpt($conv['title'], 50)) ?> · <?= e(listing_price($conv)) ?></a></div>
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
      <form method="post" class="thread-reply">
        <?= csrf_field() ?>
        <input type="hidden" name="c" value="<?= (int) $conv['id'] ?>">
        <textarea name="body" rows="1" placeholder="Write a message…" required maxlength="4000" data-autogrow data-enter-submit></textarea>
        <button class="btn btn-primary" type="submit" aria-label="Send"><?= icon('send') ?></button>
      </form>
    <?php else: ?>
      <div class="thread-empty"><?= icon('message') ?><p>Select a conversation to read it.</p></div>
    <?php endif; ?>
  </div>
</div>
<?php require APP_ROOT . '/includes/dash-bottom.php';
