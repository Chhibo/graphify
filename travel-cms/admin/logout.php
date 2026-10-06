<?php
define('ADMIN_PUBLIC_PAGE', true);
require __DIR__ . '/includes/auth.php';
unset($_SESSION['admin_id'], $_SESSION['admin_seen']);
session_regenerate_id(true);
flash('success', 'You have been logged out.');
redirect('admin/login.php');
