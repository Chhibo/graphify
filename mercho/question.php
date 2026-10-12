<?php
/** Product page: "Ask a question". The owner answers in Admin > Product questions. */
require __DIR__ . '/includes/bootstrap.php';

if (!is_post() || !qa_on()) {
    redirect('shop.php');
}
verify_csrf();
$p = find_product((int) ($_POST['product_id'] ?? 0));
if (!$p) {
    redirect('shop.php');
}
$back = product_url($p) . '#tab-questions';

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$question = trim((string) ($_POST['question'] ?? ''));

// Spam protection: hidden "website" field must stay empty, and one question per minute per visitor.
if (($_POST['website'] ?? '') !== '' || (time() - (int) ($_SESSION['last_question'] ?? 0)) < 60) {
    flash('error', 'Please wait a moment before sending another question.');
    redirect($back);
}
if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($question) < 5) {
    flash('error', 'Please fill in your name, a valid email and your question.');
    redirect($back);
}
$id = db_insert('product_questions', [
    'product_id' => (int) $p['id'],
    'name' => mb_substr($name, 0, 120),
    'email' => mb_substr($email, 0, 190),
    'question' => mb_substr($question, 0, 1000),
    'published' => 1,
    'created_at' => now(),
]);
$_SESSION['last_question'] = time();
if (setting_on('notify_admin_question') && admin_email() !== '') {
    send_template(admin_email(), 'New question about ' . $p['name'], 'New customer question',
        '<p><b>' . e($name) . '</b> asked about <b>' . e($p['name']) . '</b>:</p><p>' . nl2br(e($question)) . '</p>',
        'Answer it', full_url('admin/questions.php?id=' . $id), $email);
}
flash('success', 'Thank you! We will answer your question by email soon.');
redirect($back);
