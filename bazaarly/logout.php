<?php
define('ALLOW_IN_MAINTENANCE', true);
require __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    logout_user();
    start_session();
    session_regenerate_id(true);
    flash('success', 'You have been signed out.');
}
redirect('index.php');
