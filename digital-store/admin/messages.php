<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin_layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    switch ($_POST['action'] ?? '') {
        case 'delete':
            db()->prepare('DELETE FROM messages WHERE id = ?')->execute([$id]);
            flash('Message deleted.');
            break;
        case 'toggle_read':
            db()->prepare('UPDATE messages SET is_read = 1 - is_read WHERE id = ?')->execute([$id]);
            break;
        case 'read_all':
            db()->exec('UPDATE messages SET is_read = 1');
            break;
    }
    redirect('admin/messages.php');
}

$messages = db()->query('SELECT * FROM messages ORDER BY id DESC LIMIT 200')->fetchAll();

admin_header('Messages');
?>
<p class="muted">Messages sent from the <a href="../contact.php" target="_blank">Contact us</a> page.
  <?= setting('contact_notify') === '1' && setting('contact_email') !== '' ? 'Copies are emailed to ' . e(setting('contact_email')) . '.' : 'Turn on email copies in <a href="settings.php#contact">Settings</a>.' ?></p>
<?php if (unread_messages()): ?>
  <form method="post"><?= csrf_field() ?><button class="btn btn-small btn-light" name="action" value="read_all">Mark all as read</button></form>
<?php endif; ?>
<?php if (!$messages): ?>
  <p class="muted">No messages yet.</p>
<?php else: ?>
  <?php foreach ($messages as $m): ?>
    <div class="card message <?= $m['is_read'] ? '' : 'unread' ?>">
      <div class="message-head">
        <div>
          <strong><?= e($m['name']) ?></strong>
          &lt;<a href="mailto:<?= e($m['email']) ?>?subject=<?= e(rawurlencode('Re: ' . $m['subject'])) ?>"><?= e($m['email']) ?></a>&gt;
          <?php if (!$m['is_read']): ?><span class="badge">NEW</span><?php endif; ?>
          <br><small class="muted"><?= e(date('Y-m-d H:i', (int)$m['created_at'])) ?> · IP <?= e($m['ip']) ?></small>
        </div>
        <div class="actions">
          <a class="btn btn-small" href="mailto:<?= e($m['email']) ?>?subject=<?= e(rawurlencode('Re: ' . $m['subject'])) ?>">Reply</a>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <button class="btn btn-small btn-light" name="action" value="toggle_read"><?= $m['is_read'] ? 'Mark unread' : 'Mark read' ?></button></form>
          <form method="post" onsubmit="return confirm('Delete this message?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <button class="btn btn-small btn-danger" name="action" value="delete">Delete</button></form>
        </div>
      </div>
      <?php if ($m['subject'] !== ''): ?><p><b><?= e($m['subject']) ?></b></p><?php endif; ?>
      <p class="message-body"><?= nl2br(e($m['message'])) ?></p>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
<?php admin_footer();
