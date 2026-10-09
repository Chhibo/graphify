<?php
require __DIR__ . '/includes/auth.php';
unset($_SESSION['admin_id']);
session_regenerate_id(true);
redirect('admin/index.php');
