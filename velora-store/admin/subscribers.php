<?php
require __DIR__ . '/includes/auth.php';
require_admin();

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscribers-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['email', 'subscribed_at']);
    foreach (q_all('SELECT email, created_at FROM subscribers ORDER BY id DESC') as $r) {
        fputcsv($out, [$r['email'], $r['created_at']]);
    }
    exit;
}
if (is_post()) {
    verify_csrf();
    q('DELETE FROM subscribers WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
    redirect('admin/subscribers.php');
}
$rows = q_all('SELECT * FROM subscribers ORDER BY id DESC');
$adminTitle = 'Newsletter subscribers';
include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <div class="toolbar"><p class="muted grow-form"><?= count($rows) ?> subscriber(s)</p><?php if ($rows): ?><a class="btn btn-light" href="?export=1">Download CSV</a><?php endif; ?></div>
  <?php if ($rows): ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Email</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= e($r['email']) ?></td><td><?= e($r['created_at']) ?></td>
        <td class="actions"><form method="post" data-confirm="Remove this subscriber?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-danger-light">Remove</button></form></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?><p class="muted">No subscribers yet.</p><?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
