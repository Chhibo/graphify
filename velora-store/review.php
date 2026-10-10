<?php
require __DIR__ . '/includes/bootstrap.php';

if (!is_post() || !setting_on('reviews_enabled')) {
    redirect('shop.php');
}
verify_csrf();
$p = find_product((int) ($_POST['product_id'] ?? 0));
if (!$p) {
    redirect('shop.php');
}
$back = product_url($p) . '#tab-reviews';

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$comment = trim((string) ($_POST['comment'] ?? ''));
$rating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));

// Spam protection: hidden "website" field must stay empty, and one review per minute per visitor.
if (($_POST['website'] ?? '') !== '' || (time() - (int) ($_SESSION['last_review'] ?? 0)) < 60) {
    flash('error', 'Please wait a moment before sending another review.');
    redirect($back);
}
if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($comment) < 3) {
    flash('error', 'Please fill in your name, a valid email and your review.');
    redirect($back);
}

$approved = setting_on('reviews_moderate') ? 0 : 1;
db_insert('reviews', [
    'product_id' => (int) $p['id'],
    'name' => mb_substr($name, 0, 120),
    'email' => mb_substr($email, 0, 190),
    'rating' => $rating,
    'comment' => mb_substr($comment, 0, 2000),
    'approved' => $approved,
    'created_at' => now(),
]);
$_SESSION['last_review'] = time();
if ($approved) {
    refresh_product_rating((int) $p['id']);
}
if (setting_on('notify_admin_review') && admin_email() !== '') {
    send_template(admin_email(), 'New review for ' . $p['name'], 'New ' . $rating . '★ review',
        '<p><b>' . e($name) . '</b> reviewed <b>' . e($p['name']) . '</b>:</p><p>' . nl2br(e($comment)) . '</p>'
        . ($approved ? '' : '<p>It is waiting for your approval.</p>'), 'Open reviews', full_url('admin/reviews.php'));
}
flash('success', $approved ? 'Thank you! Your review has been published.' : 'Thank you! Your review will appear after it is approved.');
redirect($back);
