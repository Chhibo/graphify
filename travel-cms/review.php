<?php
/** Stores a visitor review. Reviews are held for moderation unless auto-approve is enabled. */
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php#reviews');
}
if (!verify_csrf()) {
    flash('error', 'Your session expired. Please submit your review again.');
    redirect('index.php#reviews');
}
if ($err = spam_check('review', 30)) {
    flash('error', $err);
    redirect('index.php#reviews');
}

$name = mb_substr(post('name'), 0, 150);
$trip = mb_substr(post('trip'), 0, 200);
$content = mb_substr(post('content'), 0, 2000);
$rating = max(1, min(5, (int) post('rating', 5)));

if ($name === '' || $trip === '' || mb_strlen($content) < 10) {
    flash('error', 'Please fill in all fields (review must be at least 10 characters).');
    redirect('index.php#reviews');
}

$auto = setting('reviews_auto_approve', '0') === '1';
db_insert('reviews', [
    'name' => $name,
    'trip' => $trip,
    'rating' => $rating,
    'content' => $content,
    'avatar' => '',
    'status' => $auto ? 'approved' : 'pending',
    'ip_address' => client_ip(),
    'created_at' => now(),
]);

flash('success', $auto ? 'Thank you! Your review has been published.' : 'Thank you! Your review will appear after moderation.');
redirect('index.php#reviews');
