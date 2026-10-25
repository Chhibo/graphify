<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

if (is_post()) {
    $r = q_one('SELECT v.*, u.name, u.email, u.username FROM verification_requests v JOIN users u ON u.id = v.user_id WHERE v.id = ?', [(int) input('id')]);
    if ($r && $r['status'] === 'pending' && in_array(input('action'), ['approve', 'reject'], true)) {
        $approve = input('action') === 'approve';
        $note = $approve ? '' : (input('reason') ?: 'The document was not readable or did not match your account.');
        q('UPDATE verification_requests SET status = ?, admin_note = ?, reviewed_at = ?, reviewed_by = ? WHERE id = ?',
            [$approve ? 'approved' : 'rejected', $note, now(), $admin['id'], $r['id']]);
        if ($approve) {
            q('UPDATE users SET verified = 1 WHERE id = ?', [$r['user_id']]);
            send_template('verification_approved', $r['email'], ['name' => $r['name'], 'action_url' => abs_url(profile_url((int) $r['user_id'], $r['username']))]);
        } else {
            send_template('verification_rejected', $r['email'], ['name' => $r['name'], 'reason' => $note, 'action_url' => full_url('dashboard/verify.php')]);
        }
        if (setting('delete_id_docs', '1') === '1') {
            foreach (['doc_front', 'doc_back', 'selfie'] as $f) {
                delete_upload($r[$f]);
            }
            q("UPDATE verification_requests SET doc_front = '', doc_back = '', selfie = '' WHERE id = ?", [$r['id']]);
        }
        log_activity('verification.' . input('action'), $r['name'] . ($note ? " ($note)" : ''), 'user', (int) $r['user_id']);
        flash('success', $approve ? 'Member verified.' : 'Request rejected.');
    }
    back('admin/verifications.php');
}

$status = in_array(input('status'), ['pending', 'approved', 'rejected'], true) ? input('status') : 'pending';
$rows = q_all('SELECT v.*, u.name, u.email, u.created_at AS joined, u.verified,
    (SELECT COUNT(*) FROM listings l WHERE l.user_id = v.user_id) AS ads
    FROM verification_requests v JOIN users u ON u.id = v.user_id WHERE v.status = ? ORDER BY v.created_at ' . ($status === 'pending' ? 'ASC' : 'DESC') . ' LIMIT 100', [$status]);
$counts = [];
foreach (q_all('SELECT status, COUNT(*) AS n FROM verification_requests GROUP BY status') as $c) {
    $counts[$c['status']] = (int) $c['n'];
}
$docLabels = ['national_id' => 'National ID', 'passport' => 'Passport', 'driving_licence' => 'Driving licence', 'business' => 'Business registration'];

$adminActive = 'verifications';
$pageTitle = 'ID verification';
require APP_ROOT . '/includes/admin-top.php';
?>
<nav class="tabs">
  <?php foreach (['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $lbl): ?>
    <a href="?status=<?= $k ?>" class="<?= $status === $k ? 'on' : '' ?>"><?= $lbl ?><span><?= $counts[$k] ?? 0 ?></span></a>
  <?php endforeach; ?>
</nav>
<?php if ($rows): ?>
  <div class="report-list">
    <?php foreach ($rows as $r): ?>
      <article class="card verif-item">
        <div class="verif-head">
          <div><h3><a href="<?= e(url('admin/user-edit.php?id=' . $r['user_id'])) ?>"><?= e($r['name']) ?></a></h3>
            <p class="muted small"><?= e($r['email']) ?> · joined <?= e(format_date($r['joined'])) ?> · <?= (int) $r['ads'] ?> ads · sent <?= e(time_ago($r['created_at'])) ?></p></div>
          <span class="badge"><?= icon('id-card') ?><?= e($docLabels[$r['doc_type']] ?? $r['doc_type']) ?></span>
        </div>
        <?php if ($r['note']): ?><p class="small">“<?= e($r['note']) ?>”</p><?php endif; ?>
        <div class="verif-docs">
          <?php foreach (['doc_front' => 'Front', 'doc_back' => 'Back', 'selfie' => 'Selfie'] as $f => $lbl): if ($r[$f] === '') continue; $src = url('admin/id-file.php?id=' . $r['id'] . '&f=' . $f); ?>
            <a href="<?= e($src) ?>" target="_blank" rel="noopener" class="verif-doc"><img src="<?= e($src) ?>" alt="<?= $lbl ?>" loading="lazy"><span><?= $lbl ?></span></a>
          <?php endforeach; ?>
          <?php if ($r['doc_front'] === '' && $r['status'] !== 'pending'): ?><p class="muted small">Documents were deleted after review.</p><?php endif; ?>
        </div>
        <?php if ($r['status'] === 'pending'): ?>
          <div class="btn-row">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-success" name="action" value="approve"><?= icon('shield') ?>Approve & verify</button></form>
            <form method="post" class="reject-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input name="reason" class="reason-input" placeholder="Reason (sent to the member)"><button class="btn btn-ghost" name="action" value="reject">Reject</button></form>
          </div>
        <?php else: ?>
          <p class="muted small"><?= $r['status'] === 'approved' ? 'Approved' : 'Rejected' ?> <?= e(time_ago((string) $r['reviewed_at'])) ?><?= $r['admin_note'] ? ' — ' . e($r['admin_note']) : '' ?></p>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="card empty-state"><?= icon('id-card') ?><h3>No requests</h3><p>Members can ask for a “Verified seller” badge from their dashboard.</p></div>
<?php endif; ?>
<?php require APP_ROOT . '/includes/admin-bottom.php';
