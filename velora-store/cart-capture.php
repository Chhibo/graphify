<?php
/** Checkout: remember the email typed by a visitor so an abandoned cart reminder can be sent. */
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/json');
if (!is_post() || !hash_equals(csrf_token(), (string) ($_POST['_csrf'] ?? ''))) {
    exit('{"ok":false}');
}
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['cart_contact'] = [
        'email' => $email,
        'name' => mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 150),
        'phone' => mb_substr(trim((string) ($_POST['phone'] ?? '')), 0, 40),
    ];
    cart_snapshot();
}
echo '{"ok":true}';
