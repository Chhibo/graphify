<?php
require __DIR__ . '/includes/bootstrap.php';

$email = strtolower(trim((string) ($_GET['e'] ?? '')));
$token = (string) ($_GET['t'] ?? '');
$valid = $email !== '' && valid_unsubscribe_token($email, $token);
$done = false;
if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    db_exec('UPDATE ' . tbl('subscribers') . " SET status = 'unsubscribed', unsubscribed_at = ? WHERE email = ?", [now(), $email]);
    $done = true;
}

$pageTitle = 'Unsubscribe';
$noindex = true;
$noPopup = true;
require APP_ROOT . '/includes/header.php';
page_banner('Newsletter Preferences');
?>
<section class="py-14 max-w-md mx-auto px-4">
    <div class="bg-white rounded-3xl p-8 shadow-xl border border-slate-100 text-center space-y-4">
        <?php if (!$valid): ?>
            <i class="fa-solid fa-link-slash text-4xl text-slate-300"></i>
            <p class="text-slate-600 text-sm">This unsubscribe link is invalid or incomplete. Please use the link from your latest email.</p>
        <?php elseif ($done): ?>
            <i class="fa-solid fa-circle-check text-4xl text-emerald-500"></i>
            <h2 class="font-serif text-2xl font-bold">You're unsubscribed</h2>
            <p class="text-slate-600 text-sm"><b><?= e($email) ?></b> will no longer receive our newsletter.</p>
        <?php else: ?>
            <i class="fa-solid fa-envelope-circle-check text-4xl text-brand-600"></i>
            <h2 class="font-serif text-2xl font-bold">Unsubscribe?</h2>
            <p class="text-slate-600 text-sm">Stop sending newsletter emails to <b><?= e($email) ?></b>.</p>
            <form method="post"><?= csrf_field() ?><button class="w-full py-3 bg-rose-500 hover:bg-rose-600 text-white font-bold rounded-xl text-sm">Unsubscribe</button></form>
        <?php endif; ?>
        <a href="<?= e(url()) ?>" class="block text-xs text-slate-500 hover:text-brand-600">&larr; Back to the website</a>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
