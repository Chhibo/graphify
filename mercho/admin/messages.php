<?php
require __DIR__ . '/includes/auth.php';
require_admin('customers');

if (is_post()) {
    verify_csrf();
    q('DELETE FROM messages WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
    flash('success', 'Message deleted.');
    redirect('admin/messages.php');
}
$view = isset($_GET['id']) ? q_one('SELECT * FROM messages WHERE id = ?', [(int) $_GET['id']]) : null;
if ($view && !(int) $view['is_read']) {
    q('UPDATE messages SET is_read = 1 WHERE id = ?', [$view['id']]);
}
$rows = q_all('SELECT * FROM messages ORDER BY id DESC LIMIT 300');
$adminTitle = 'Messages';
include __DIR__ . '/includes/header.php';
?>
<div class="grid-main">
  <div class="card">
    <p class="muted">Messages sent from the <a href="<?= url('contact.php') ?>" target="_blank">Contact page</a>. <a href="settings.php?tab=contact">Edit the contact page</a>.</p>
    <?php if ($rows): ?>
    <div class="table-wrap"><table>
      <thead><tr><th>From</th><th>Subject</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $m): ?>
        <tr class="clickable" onclick="location='?id=<?= (int) $m['id'] ?>'">
          <td><?= (int) $m['is_read'] ? '' : '<span class="badge-new"></span>' ?><b><?= e($m['name']) ?></b><br><small class="muted"><?= e($m['email']) ?></small></td>
          <td><?= e($m['subject'] !== '' ? $m['subject'] : excerpt($m['message'], 60)) ?></td>
          <td><small><?= e(date('M j, Y H:i', strtotime($m['created_at']))) ?></small></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php else: ?><p class="muted">No messages yet.</p><?php endif; ?>
  </div>
  <div class="card">
    <?php if ($view): ?>
      <div class="card-head"><h2><?= e($view['subject'] !== '' ? $view['subject'] : 'Message') ?></h2></div>
      <div class="kv"><span>From</span><b><?= e($view['name']) ?></b></div>
      <div class="kv"><span>Email</span><b><a href="mailto:<?= e($view['email']) ?>"><?= e($view['email']) ?></a></b></div>
      <div class="kv"><span>Date</span><b><?= e(date('M j, Y H:i', strtotime($view['created_at']))) ?></b></div>
      <div class="msg-body"><?= e($view['message']) ?></div>
      <a class="btn btn-primary btn-block" href="mailto:<?= e($view['email']) ?>?subject=<?= rawurlencode('Re: ' . ($view['subject'] !== '' ? $view['subject'] : setting('store_name'))) ?>">Reply by email</a>
      <form method="post" data-confirm="Delete this message?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $view['id'] ?>"><button class="btn btn-danger-light btn-block" type="submit">Delete</button></form>
    <?php else: ?>
      <p class="muted">Click a message to read it.</p>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
