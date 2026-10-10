<?php
require __DIR__ . '/includes/bootstrap.php';
if (is_post()) {
    verify_csrf();
    customer_logout();
    flash('success', 'You have been logged out.');
}
redirect('');
