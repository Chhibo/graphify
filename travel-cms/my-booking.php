<?php
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', __('Your session expired. Please try again.'));
        redirect('my-booking.php');
    }
    if ($err = spam_check('lookup', 3)) {
        flash('error', $err);
        redirect('my-booking.php');
    }
    $booking = db_one('SELECT * FROM ' . tbl('bookings') . ' WHERE reference = ? AND customer_email = ?', [strtoupper(post('reference')), post('email')]);
    if ($booking) {
        redirect(voucher_url($booking));
    }
    flash('error', __('No booking matches that reference and email address.'));
    redirect('my-booking.php');
}

$pageTitle = __('Manage My Booking');
$noindex = true;
require APP_ROOT . '/includes/header.php';
page_banner(__('Manage My Booking'), __('Find your reservation voucher using your booking reference and email.'), __('Booking Lookup'));
?>
<section class="py-14 max-w-md mx-auto px-4">
    <form method="post" class="bg-white rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-100 space-y-4">
        <?= csrf_field() ?>
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-1"><?= e(__('Booking Reference')) ?></label>
            <input type="text" name="reference" required placeholder="<?= e(__('e.g.,')) ?> WL-123456" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm uppercase focus:ring-2 focus:ring-brand-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-1"><?= e(__('Email Address')) ?></label>
            <input type="email" name="email" required placeholder="you@example.com" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
        </div>
        <button class="w-full bg-brand-600 hover:bg-brand-500 text-white font-bold py-3 rounded-xl text-sm"><i class="fa-solid fa-magnifying-glass me-1"></i> <?= e(__('Find My Booking')) ?></button>
    </form>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
