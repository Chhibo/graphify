<?php
require __DIR__ . '/includes/auth.php';
if (current_admin()) {
    log_activity('Logged out');
}
unset($_SESSION['admin_id']);
session_regenerate_id(true);
redirect('admin/index.php');
