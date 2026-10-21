<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin_layout.php';
require_admin();

if (isset($_GET['export'])) {
    csv_download('subscribers-' . date('Y-m-d') . '.csv', ['Email', 'Signed up'],
        array_map(fn($r) => [$r['email'], date('Y-m-d H:i', (int)$r['created_at'])],
            db()->query('SELECT email, created_at FROM subscribers ORDER BY id')->fetchAll()));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    db()->prepare('DELETE FROM subscribers WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
    flash('Subscriber removed.');
    redirect('admin/subscribers.php');
}

$search = trim((string)($_GET['q'] ?? ''));
$stmt = db()->prepare('SELECT * FROM subscribers' . ($search !== '' ? ' WHERE email LIKE ?' : '') . ' ORDER BY id DESC LIMIT 500');
$stmt->execute($search !== '' ? ['%' . $search . '%'] : []);
$subs = $stmt->fetchAll();
$total = (int)db()->query('SELECT COUNT(*) FROM subscribers')->fetchColumn();
$week = (int)db()->query('SELECT COUNT(*) FROM subscribers WHERE created_at > ' . (time() - 7 * 86400))->fetchColumn();

admin_header('Subscribers', number_format($total) . ' on your email list · ' . $week . ' this week',
    $total ? '<a class="btn btn-light" href="?export=1">' . icon('download', 16) . ' Export CSV</a>' : '');
?>
<?php if (setting('newsletter_show') !== '1'): ?>
  <?= notice('The signup box is switched off, so nobody can subscribe right now.', 'growth.php#newsletter', 'Turn it on') ?>
<?php endif; ?>
<p class="muted">Export the list to Mailchimp, Brevo, MailerLite or any email tool to send updates.</p>
<form class="toolbar" method="get">
  <label class="search-box"><?= icon('search', 16) ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search emails…"></label>
</form>
<?php if (!$subs): ?>
  <div class="card empty"><?= icon('user', 40) ?><p><?= $total ? 'No emails match.' : 'No subscribers yet.' ?></p></div>
<?php else: ?>
<div class="table-wrap"><table>
  <thead><tr><th>Email</th><th>Signed up</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($subs as $s): ?>
    <tr>
      <td><div class="prod-cell"><span class="avatar"><?= e(mb_strtoupper(mb_substr($s['email'], 0, 1))) ?></span> <?= e($s['email']) ?></div></td>
      <td class="muted"><?= e(date('M j, Y H:i', (int)$s['created_at'])) ?></td>
      <td class="actions"><form method="post" onsubmit="return confirm('Remove this subscriber?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <button class="icon-btn danger" title="Remove"><?= icon('trash', 16) ?></button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
<?php admin_footer();
