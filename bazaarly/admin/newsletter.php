<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_admin();
$audiences = newsletter_audiences();

/** Personalised email for one member. */
function newsletter_mail(string $subject, string $body, array $u): array
{
    $map = ['{name}' => $u['name'], '{first_name}' => strtok($u['name'], ' ') ?: $u['name'], '{site_name}' => setting('site_name'), '{site_url}' => full_url()];
    $s = strtr($subject, $map);
    $b = strtr($body, $map);
    $unsub = unsubscribe_url((int) $u['id']);
    $footer = e(__('You receive this email because you are a member of {site}.', ['site' => setting('site_name')])) . ' <a href="' . e($unsub) . '" style="color:#94a3b8">' . e(__('Unsubscribe')) . '</a>';
    return [$s, $b . "\n\n—\n" . __('Unsubscribe') . ': ' . $unsub, email_html($s, $b, '', '', $footer)];
}

// progress / batch sending (AJAX)
if (is_post() && input('action') === 'process') {
    $id = (int) input('id');
    $sent = process_mail_queue(15, $id);
    $left = (int) q_val('SELECT COUNT(*) FROM mail_queue WHERE newsletter_id = ? AND sent_at IS NULL AND attempts < 3', [$id]);
    $done = (int) q_val('SELECT COUNT(*) FROM mail_queue WHERE newsletter_id = ? AND sent_at IS NOT NULL', [$id]);
    $failed = (int) q_val('SELECT COUNT(*) FROM mail_queue WHERE newsletter_id = ? AND sent_at IS NULL AND attempts >= 3', [$id]);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'sent_now' => $sent, 'left' => $left, 'done' => $done, 'failed' => $failed]);
    exit;
}

$subject = input('subject');
$body = (string) ($_POST['body'] ?? '');
$audience = array_key_exists(input('audience'), $audiences) ? input('audience') : 'all';
if (is_post() && in_array(input('action'), ['test', 'send'], true)) {
    if (mb_strlen($subject) < 3 || mb_strlen(trim($body)) < 10) {
        flash('error', 'Write a subject and a message first.');
    } elseif (input('action') === 'test') {
        [$s, $txt, $html] = newsletter_mail($subject, $body, $me);
        send_mail($me['email'], '[TEST] ' . $s, $txt, $html)
            ? flash('success', 'Test sent to ' . $me['email'] . '.')
            : flash('error', 'Test failed: ' . mail_last_error());
    } else {
        $users = q_all('SELECT u.id, u.name, u.email FROM users u WHERE ' . newsletter_where($audience));
        if (!$users) {
            flash('error', 'No members match this audience.');
        } else {
            $nid = db_insert('newsletters', ['subject' => mb_substr($subject, 0, 250), 'body' => $body, 'audience' => $audience,
                'recipients' => count($users), 'created_by' => $me['id'], 'created_at' => now()]);
            db()->beginTransaction();
            foreach ($users as $u) {
                [$s, $txt, $html] = newsletter_mail($subject, $body, $u);
                queue_mail($u['email'], $s, $txt, $html, $nid);
            }
            db()->commit();
            log_activity('newsletter.send', '"' . $subject . '" to ' . count($users) . ' members (' . $audiences[$audience] . ')');
            flash('success', 'Newsletter queued for ' . count($users) . ' members. Sending now…');
            redirect('admin/newsletter.php?view=' . $nid);
        }
    }
}

$counts = [];
foreach ($audiences as $k => $_) {
    $counts[$k] = (int) q_val('SELECT COUNT(*) FROM users u WHERE ' . newsletter_where($k));
}
$optedOut = (int) q_val("SELECT COUNT(*) FROM users WHERE newsletter = 0 AND status = 'active'");
$view = input('view') ? q_one('SELECT * FROM newsletters WHERE id = ?', [(int) input('view')]) : null;
$history = q_all('SELECT n.*, (SELECT COUNT(*) FROM mail_queue m WHERE m.newsletter_id = n.id AND m.sent_at IS NOT NULL) AS sent,
    (SELECT COUNT(*) FROM mail_queue m WHERE m.newsletter_id = n.id AND m.sent_at IS NULL AND m.attempts >= 3) AS failed
    FROM newsletters n ORDER BY n.id DESC LIMIT 20');

$adminActive = 'newsletter';
$pageTitle = 'Newsletter';
require APP_ROOT . '/includes/admin-top.php';
?>
<?php if ($view): $sent = (int) q_val('SELECT COUNT(*) FROM mail_queue WHERE newsletter_id = ? AND sent_at IS NOT NULL', [$view['id']]); ?>
  <section class="card form-section" data-newsletter-progress data-id="<?= (int) $view['id'] ?>" data-endpoint="<?= e(url('admin/newsletter.php')) ?>" data-total="<?= (int) $view['recipients'] ?>">
    <h2><?= icon('send') ?>Sending “<?= e($view['subject']) ?>”</h2>
    <div class="progress"><span data-progress-bar style="width:<?= $view['recipients'] ? round($sent / $view['recipients'] * 100) : 0 ?>%"></span></div>
    <p class="muted small"><strong data-progress-done><?= $sent ?></strong> of <?= (int) $view['recipients'] ?> sent · <span data-progress-failed>0</span> failed · <span data-progress-status>Keep this page open to send faster. If you close it, the rest is sent automatically in the background.</span></p>
    <div class="btn-row"><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/newsletter.php')) ?>"><?= icon('chevron-left') ?>Back</a></div>
  </section>
<?php endif; ?>

<div class="admin-grid wide-left">
  <form method="post" class="card form-section">
    <?= csrf_field() ?>
    <h2><?= icon('newspaper') ?>New newsletter</h2>
    <div class="field"><label for="n-aud">Send to</label>
      <select id="n-aud" name="audience"><?php foreach ($audiences as $k => $lbl): ?><option value="<?= $k ?>" <?= $audience === $k ? 'selected' : '' ?>><?= e($lbl) ?> (<?= $counts[$k] ?>)</option><?php endforeach; ?></select>
      <small class="help">Only members who agreed to receive news (<?= $optedOut ?> opted out)<?= setting('require_email_verification') === '1' ? ' and confirmed their email' : '' ?>.</small></div>
    <div class="field"><label for="n-sub">Subject</label><input id="n-sub" name="subject" value="<?= e($subject) ?>" maxlength="200" required placeholder="This week's best deals on {site_name}"></div>
    <div class="field"><label for="n-body">Message</label><textarea id="n-body" name="body" rows="12" required placeholder="Hi {first_name},&#10;&#10;…"><?= e($body) ?></textarea>
      <small class="help">Placeholders: <code>{name}</code> <code>{first_name}</code> <code>{site_name}</code> <code>{site_url}</code>. Links are clickable automatically. An unsubscribe link is added to every email.</small></div>
    <div class="btn-row">
      <button class="btn btn-ghost" name="action" value="test"><?= icon('mail') ?>Send me a test</button>
      <button class="btn btn-primary" name="action" value="send" data-confirm-click="Send this newsletter now? This cannot be undone."><?= icon('send') ?>Send newsletter</button>
    </div>
  </form>
  <section class="card table-card">
    <div class="card-head"><h2>Sent newsletters</h2></div>
    <?php if ($history): ?>
      <ul class="mini-list">
        <?php foreach ($history as $h): ?>
          <li><span class="stat-icon sm"><?= icon('newspaper') ?></span>
            <div class="mini-body"><a href="?view=<?= (int) $h['id'] ?>"><?= e($h['subject']) ?></a><small><?= e($audiences[$h['audience']] ?? $h['audience']) ?> · <?= e(time_ago($h['created_at'])) ?></small></div>
            <span class="badge <?= (int) $h['sent'] >= (int) $h['recipients'] ? 'badge-success' : 'badge-warning' ?>"><?= (int) $h['sent'] ?>/<?= (int) $h['recipients'] ?></span></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="empty-state small"><?= icon('newspaper') ?><p>No newsletters sent yet.</p></div>
    <?php endif; ?>
  </section>
</div>
<?php require APP_ROOT . '/includes/admin-bottom.php';
