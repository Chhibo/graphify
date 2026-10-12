<?php
require __DIR__ . '/includes/auth.php';
require_admin('products');

if (is_post()) {
    verify_csrf();
    $qa = q_one('SELECT q.*, p.name AS product_name, p.slug FROM product_questions q JOIN products p ON p.id = q.product_id WHERE q.id = ?', [(int) ($_POST['id'] ?? 0)]);
    if ($qa) {
        $action = $_POST['action'] ?? '';
        if ($action === 'answer') {
            $answer = trim((string) ($_POST['answer'] ?? ''));
            $question = trim((string) ($_POST['question'] ?? '')) ?: $qa['question'];
            if ($answer === '') {
                flash('error', 'Please write an answer.');
                redirect('admin/questions.php?id=' . (int) $qa['id']);
            }
            $first = trim((string) $qa['answer']) === '';
            q('UPDATE product_questions SET question = ?, answer = ?, published = ?, answered_at = ? WHERE id = ?',
                [mb_substr($question, 0, 1000), mb_substr($answer, 0, 3000), post_flag('published'), now(), $qa['id']]);
            $msg = 'Answer saved.';
            if ($first && !empty($_POST['email_customer']) && filter_var($qa['email'], FILTER_VALIDATE_EMAIL)) {
                $sent = send_template($qa['email'], 'Answer to your question about ' . $qa['product_name'], 'We answered your question',
                    '<p>Hello ' . e(explode(' ', $qa['name'])[0]) . ',</p><p>You asked about <b>' . e($qa['product_name']) . '</b>:</p>'
                    . '<p style="color:#555;border-left:3px solid #ddd;padding-left:10px">' . nl2br(e($question)) . '</p><p><b>Our answer:</b><br>' . nl2br(e($answer)) . '</p>',
                    'View the product', abs_url(product_url(['id' => $qa['product_id'], 'slug' => $qa['slug']])));
                $msg .= $sent ? ' The customer was emailed.' : ' (The email could not be sent: ' . mail_last_error() . ')';
            }
            activity('Answered a question about “' . $qa['product_name'] . '”', excerpt($question, 120));
            flash('success', $msg);
        } elseif ($action === 'toggle') {
            q('UPDATE product_questions SET published = 1 - published WHERE id = ?', [$qa['id']]);
            flash('success', 'Question updated.');
        } elseif ($action === 'delete') {
            q('DELETE FROM product_questions WHERE id = ?', [$qa['id']]);
            flash('success', 'Question deleted.');
        }
    }
    redirect('admin/questions.php' . (isset($_GET['all']) ? '?all=1' : ''));
}

$showAll = isset($_GET['all']);
$openId = (int) ($_GET['id'] ?? 0);
$rows = q_all("SELECT q.*, p.name AS product_name FROM product_questions q LEFT JOIN products p ON p.id = q.product_id"
    . ($showAll || $openId ? '' : " WHERE q.answer IS NULL OR q.answer = ''") . ' ORDER BY q.id DESC LIMIT 300');
$adminTitle = 'Product questions';
include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <div class="toolbar">
    <p class="muted grow-form"><?= $showAll ? 'All questions' : 'Questions waiting for an answer' ?> ·
      <a href="?<?= $showAll ? '' : 'all=1' ?>"><?= $showAll ? 'Show only waiting' : 'Show all questions' ?></a> ·
      <a href="settings.php?tab=sales">Settings</a></p>
  </div>
  <?php if (!qa_on()): ?><div class="alert alert-info">Questions are turned off on product pages. Turn them on in <a href="settings.php?tab=sales">Settings → Sales tools</a>.</div><?php endif; ?>
  <?php foreach ($rows as $r): $answered = trim((string) $r['answer']) !== ''; ?>
    <details class="qa-admin" <?= $openId === (int) $r['id'] || (!$answered && count($rows) <= 5) ? 'open' : '' ?>>
      <summary>
        <span class="grow"><b><?= e(excerpt($r['question'], 110)) ?></b><br><small class="muted"><?= e($r['name']) ?> · <?= e($r['email']) ?> · <?= e($r['product_name'] ?? '-') ?> · <?= e(date('M j, Y', strtotime($r['created_at']))) ?></small></span>
        <?= $answered ? ((int) $r['published'] ? '<span class="status st-delivered">Answered · shown</span>' : '<span class="status st-cancelled">Answered · hidden</span>') : '<span class="status st-pending">Waiting</span>' ?>
      </summary>
      <form method="post" class="qa-form">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="answer">
        <label>Question <small class="muted">(you can fix typos)</small><textarea name="question" rows="2"><?= e($r['question']) ?></textarea></label>
        <label>Your answer<textarea name="answer" rows="4" required><?= e((string) $r['answer']) ?></textarea></label>
        <label class="inline"><input type="checkbox" name="published" value="1" <?= (int) $r['published'] ? 'checked' : '' ?>> Show on the product page</label>
        <?php if (!$answered): ?><label class="inline"><input type="checkbox" name="email_customer" value="1" checked <?= $r['email'] === '' ? 'disabled' : '' ?>> Email the answer to the customer</label><?php endif; ?>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit"><?= $answered ? 'Save' : 'Send answer' ?></button>
          <button class="btn btn-danger-light" type="submit" name="action" value="delete" formnovalidate onclick="return confirm('Delete this question?')">Delete</button>
        </div>
      </form>
    </details>
  <?php endforeach; ?>
  <?php if (!$rows): ?><p class="muted"><?= $showAll ? 'No questions yet.' : 'No questions waiting. 🎉' ?></p><?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
