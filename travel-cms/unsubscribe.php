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

$pageTitle = __('Unsubscribe');
$noindex = true;
$noPopup = true;
require APP_ROOT . '/includes/header.php';
page_banner(__('Newsletter Preferences'));
?>
<section class="py-14 max-w-md mx-auto px-4">
    <div class="bg-white rounded-3xl p-8 shadow-xl border border-slate-100 text-center space-y-4">
        <?php if (!$valid): ?>
            <i class="fa-solid fa-link-slash text-4xl text-slate-300"></i>
            <p class="text-slate-600 text-sm"><?= e(__('This unsubscribe link is invalid or incomplete. Please use the link from your latest email.')) ?></p>
        <?php elseif ($done): ?>
            <i class="fa-solid fa-circle-check text-4xl text-emerald-500"></i>
            <h2 class="font-serif text-2xl font-bold"><?= e(__("You're unsubscribed")) ?></h2>
            <p class="text-slate-600 text-sm"><?= e(__f('%s will no longer receive our newsletter.', $email)) ?></p>
        <?php else: ?>
            <i class="fa-solid fa-envelope-circle-check text-4xl text-brand-600"></i>
            <h2 class="font-serif text-2xl font-bold"><?= e(__('Unsubscribe?')) ?></h2>
            <p class="text-slate-600 text-sm"><?= e(__f('Stop sending newsletter emails to %s.', $email)) ?></p>
            <form method="post"><?= csrf_field() ?><button class="w-full py-3 bg-rose-500 hover:bg-rose-600 text-white font-bold rounded-xl text-sm"><?= e(__('Unsubscribe')) ?></button></form>
        <?php endif; ?>
        <a href="<?= e(url()) ?>" class="block text-xs text-slate-500 hover:text-brand-600"><?= e(__('Back to the website')) ?></a>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
