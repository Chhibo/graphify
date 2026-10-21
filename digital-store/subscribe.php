<?php
// Footer email signup.
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require_installed();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('');
}
check_csrf();
// Only send people back to a page on this site.
$back = (string)($_POST['back'] ?? '/');
if (!preg_match('#^/[^/\\\\]#', $back) && $back !== '/') {
    $back = '/';
}
$back = preg_replace('/([?&])subscribed=[^&#]*&?/', '$1', $back);
$back = rtrim(preg_replace('/#.*$/', '', $back), '?&');
$sep = strpos($back, '?') === false ? '?' : '&';
$go = function (string $state) use ($back, $sep): void {
    $origin = preg_replace('#^(https?://[^/]+).*$#', '$1', base_url());
    header('Location: ' . $origin . $back . $sep . 'subscribed=' . $state . '#newsletter');
    exit;
};

if (($_POST['website'] ?? '') !== '') {
    $go('1'); // bot
}
$email = strtolower(trim((string)($_POST['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    $go('bad');
}
$ip = client_ip();
$recent = db()->prepare('SELECT COUNT(*) FROM subscribers WHERE ip = ? AND created_at > ?');
$recent->execute([$ip, time() - 3600]);
if ((int)$recent->fetchColumn() < 5) {
    $ins = db()->prepare('INSERT OR IGNORE INTO subscribers (email, ip, created_at) VALUES (?, ?, ?)');
    $ins->execute([$email, $ip, time()]);
    if ($ins->rowCount() && setting('tg_on_subscribe') === '1' && telegram_ready()) {
        telegram_send('📧 New subscriber: ' . $email);
    }
}
$go('1');
