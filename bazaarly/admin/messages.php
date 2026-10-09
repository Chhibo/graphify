<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

if (is_post()) {
    $id = (int) input('id');
    if (input('action') === 'delete') {
        q('DELETE FROM contact_messages WHERE id = ?', [$id]);
        flash('success', 'Message deleted.');
    } elseif (input('action') === 'unread') {
        q('UPDATE contact_messages SET is_read = 0 WHERE id = ?', [$id]);
    }
    redirect('admin/messages.php');
}

$view = input('id') ? q_one('SELECT * FROM contact_messages WHERE id = ?', [(int) input('id')]) : null;
if ($view && !$view['is_read']) {
    q('UPDATE contact_messages SET is_read = 1 WHERE id = ?', [$view['id']]);
}
$total = (int) q_val('SELECT COUNT(*) FROM contact_messages');
[$page, $pages, $offset] = paginate($total, 25, (int) input('page', '1'));
$rows = q_all("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 25 OFFSET $offset");

$adminActive = 'messages';
$pageTitle = 'Contact inbox';
require APP_ROOT . '/includes/admin-top.php';
?>
<?php if ($view): ?>
  <article class="card form-section">
    <div class="card-head"><h2><?= e($view['subject']) ?></h2><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/messages.php')) ?>"><?= icon('chevron-left') ?>Back</a></div>
    <p class="muted small">From <strong><?= e($view['name']) ?></strong> &lt;<?= e($view['email']) ?>&gt; · <?= e(format_date($view['created_at'])) ?> <?= e(date('H:i', strtotime($view['created_at']))) ?></p>
    <div class="message-body"><?= nl2br(e($view['message'])) ?></div>
    <div class="btn-row">
      <a class="btn btn-primary" href="mailto:<?= e($view['email']) ?>?subject=<?= rawurlencode('Re: ' . $view['subject']) ?>"><?= icon('mail') ?>Reply by email</a>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $view['id'] ?>"><input type="hidden" name="action" value="unread"><button class="btn btn-ghost" type="submit">Mark unread</button></form>
      <form method="post" data-confirm="Delete this message?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $view['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-ghost danger" type="submit"><?= icon('trash') ?>Delete</button></form>
    </div>
  </article>
<?php endif; ?>

<div class="card table-card">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>From</th><th>Subject</th><th>Received</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $m): ?>
        <tr class="<?= $m['is_read'] ? '' : 'row-unread' ?>">
          <td><strong><?= e($m['name']) ?></strong><small class="muted block"><?= e($m['email']) ?></small></td>
          <td><a href="?id=<?= (int) $m['id'] ?>"><?= e($m['subject']) ?></a><small class="muted block"><?= e(excerpt($m['message'], 90)) ?></small></td>
          <td class="nowrap muted small"><?= e(time_ago($m['created_at'])) ?></td>
          <td><form method="post" data-confirm="Delete this message?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="action" value="delete"><button class="icon-btn sm danger" type="submit" aria-label="Delete"><?= icon('trash') ?></button></form></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="4" class="center muted">No messages yet. Messages sent from the Contact page appear here.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?= pagination_links($page, $pages) ?>
<?php require APP_ROOT . '/includes/admin-bottom.php';
