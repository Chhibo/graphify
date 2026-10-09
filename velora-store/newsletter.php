<?php
require __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Please enter a valid email address.');
    } else {
        if ((int) q_val('SELECT COUNT(*) FROM subscribers WHERE email = ?', [$email]) === 0) {
            db_insert('subscribers', ['email' => $email, 'created_at' => now()]);
        }
        flash('success', 'Thank you for subscribing! You will be the first to hear about our offers.');
    }
}
redirect('');
