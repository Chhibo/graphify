<?php
/** Newsletter sign-up (footer form and popup). Answers JSON to fetch() requests. */
require __DIR__ . '/includes/bootstrap.php';

$ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
function subscribe_reply(bool $ok, string $message, bool $ajax): void
{
    if ($ajax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => $ok, 'message' => $message]);
        exit;
    }
    flash($ok ? 'success' : 'error', $message);
    $back = $_SERVER['HTTP_REFERER'] ?? '';
    redirect($back && strpos($back, base_url()) === 0 ? $back : 'index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || setting('newsletter_enabled', '1') !== '1') {
    redirect('index.php');
}
if (!verify_csrf()) {
    subscribe_reply(false, __('Your session expired. Please reload the page and try again.'), $ajax);
}
if ($err = spam_check('subscribe', 5)) {
    subscribe_reply(false, $err, $ajax);
}
$email = mb_substr(post('email'), 0, 190);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    subscribe_reply(false, __('Please enter a valid email address.'), $ajax);
}
$source = in_array(post('source'), ['footer', 'popup'], true) ? post('source') : 'footer';
$result = subscribe_email($email, post('name'), $source, current_lang());

if ($result !== 'exists' && setting('newsletter_notify', '0') === '1') {
    $adminEmail = setting('admin_notify_email', setting('contact_email'));
    if ($adminEmail) {
        send_mail($adminEmail, 'New newsletter subscriber', '<p><b>' . e($email) . '</b> subscribed from the ' . e($source) . ' (language: ' . e(current_lang()) . ').</p>', default_lang());
    }
}
if ($result === 'new' && setting('newsletter_welcome', '1') === '1') {
    send_template_email('newsletter_welcome', $email, [
        '{site_name}' => setting('site_name', 'WanderLuxe'), '{site_phone}' => setting('contact_phone'), '{site_email}' => setting('contact_email'),
    ], ['{unsubscribe_link}' => '<a href="' . e(unsubscribe_url($email)) . '">' . e(__('Unsubscribe')) . '</a>'], current_lang());
}
subscribe_reply(true, $result === 'exists' ? __("You're already subscribed. Thank you!") : setting('newsletter_success', __('Thanks for subscribing! Check your inbox soon.')), $ajax);
