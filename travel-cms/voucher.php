<?php
require __DIR__ . '/includes/bootstrap.php';

$ref = (string) ($_GET['ref'] ?? '');
$token = (string) ($_GET['t'] ?? '');
$booking = db_one('SELECT * FROM ' . tbl('bookings') . ' WHERE reference = ?', [$ref]);
if (!$booking || !hash_equals($booking['access_token'], $token)) {
    flash('error', __('Booking not found. Please check your reference and email.'));
    redirect('my-booking.php');
}

$canCancel = setting('allow_guest_cancel', '1') === '1'
    && in_array($booking['status'], ['pending', 'confirmed'], true)
    && strtotime($booking['travel_date']) > strtotime('today +' . (int) setting('cancel_min_days', 1) . ' days');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'cancel') {
    if (!verify_csrf() || !$canCancel) {
        flash('error', __('This booking can no longer be cancelled online. Please contact us.'));
    } else {
        db_update('bookings', ['status' => 'cancelled', 'updated_at' => now()], (int) $booking['id']);
        $booking['status'] = 'cancelled';
        $adminEmail = setting('admin_notify_email', setting('contact_email'));
        if ($adminEmail) {
            send_booking_email('guest_cancelled_admin', $booking, $adminEmail, [], default_lang());
        }
        flash('success', __('Your booking has been cancelled.'));
    }
    redirect(voucher_url($booking));
}

$justBooked = ($_SESSION['_just_booked'] ?? '') === $booking['reference'];
unset($_SESSION['_just_booked']);
$tour = $booking['tour_id'] ? localize_row(db_one('SELECT id, title, slug, image, destination, duration FROM ' . tbl('tours') . ' WHERE id = ?', [$booking['tour_id']]), 'tour') : null;
$reviewed = (bool) db_value('SELECT COUNT(*) FROM ' . tbl('reviews') . ' WHERE booking_id = ?', [$booking['id']]);
$canReview = !$reviewed && booking_can_review($booking);

$pageTitle = __f('Voucher %s', $booking['reference']);
$noindex = true;
$noPopup = true;
require APP_ROOT . '/includes/header.php';
page_banner($justBooked ? __('Booking Confirmed!') : __('Your Reservation'), $justBooked ? __('Your reservation code has been registered. No payment was charged.') : __f('Reference %s', $booking['reference']), __('Pay On Arrival'));
?>

<section class="py-14 max-w-2xl mx-auto px-4">
    <?php if ($canReview): ?>
        <a href="<?= e(review_url($booking)) ?>" class="no-print mb-6 flex items-center justify-between gap-4 bg-gradient-to-r from-amber-400 to-accent-500 text-white rounded-2xl p-5 shadow-lg hover:shadow-xl transition">
            <span><span class="block font-bold"><i class="fa-solid fa-star me-1"></i> <?= e(__('How was your trip?')) ?></span><span class="text-sm text-white/90"><?= e(__('Share your experience and help other travellers.')) ?></span></span>
            <span class="shrink-0 px-4 py-2 bg-white text-accent-600 rounded-xl text-sm font-bold"><?= e(__('Write a review')) ?></span>
        </a>
    <?php elseif ($reviewed): ?>
        <p class="no-print mb-6 text-center text-sm text-emerald-700 bg-emerald-50 rounded-xl py-3"><i class="fa-solid fa-circle-check me-1"></i> <?= e(__('Thank you for reviewing this trip!')) ?></p>
    <?php endif; ?>
    <div id="printArea" class="bg-white rounded-3xl p-6 sm:p-8 shadow-2xl border border-slate-200 text-center relative">
        <div class="w-16 h-16 <?= $booking['status'] === 'cancelled' ? 'bg-rose-100 text-rose-600' : 'bg-emerald-100 text-emerald-600' ?> rounded-full flex items-center justify-center mx-auto text-3xl mb-4">
            <i class="fa-solid <?= $booking['status'] === 'cancelled' ? 'fa-circle-xmark' : 'fa-circle-check' ?>"></i>
        </div>
        <h2 class="font-serif text-2xl font-bold text-slate-900"><?= e(__f('%s Reservation', setting('site_name', 'WanderLuxe'))) ?></h2>
        <div class="mt-2"><?= status_badge($booking['status']) ?></div>
        <?php if ($booking['status'] === 'pending'): ?>
            <p class="text-xs text-slate-500 mt-2"><?= e(__('Your request is awaiting confirmation from our team. We will contact you by email or WhatsApp.')) ?></p>
        <?php endif; ?>

        <div class="my-6 p-5 bg-slate-50 border-2 border-dashed border-brand-500 rounded-2xl text-start space-y-3">
            <div class="flex justify-between items-center border-b border-slate-200 pb-3">
                <span class="text-xs font-bold text-brand-600 uppercase tracking-widest"><?= e(__('Reservation Voucher')) ?></span>
                <span class="text-xs font-mono font-bold bg-brand-100 text-brand-700 px-2 py-0.5 rounded" dir="ltr">#<?= e($booking['reference']) ?></span>
            </div>
            <div>
                <span class="text-xs text-slate-400 block"><?= e(__('Experience')) ?></span>
                <span class="font-bold text-sm text-slate-800"><?= e($tour['title'] ?? $booking['tour_title']) ?></span>
                <?php if ($tour): ?><span class="block text-xs text-slate-500"><i class="fa-solid fa-location-dot text-brand-500 me-1"></i><?= e($tour['destination']) ?> · <?= e($tour['duration']) ?></span><?php endif; ?>
            </div>
            <div class="grid grid-cols-2 gap-3 text-xs">
                <div><span class="text-slate-400 block"><?= e(__('Lead Guest')) ?></span><span class="font-semibold"><?= e($booking['customer_name']) ?></span></div>
                <div><span class="text-slate-400 block"><?= e(__('Travel Date')) ?></span><span class="font-semibold"><?= e(format_date($booking['travel_date'], 'D, M j, Y')) ?></span></div>
                <div><span class="text-slate-400 block"><?= e(__('Guests')) ?></span><span class="font-semibold"><?= e(guests_summary($booking)) ?></span></div>
                <div><span class="text-slate-400 block"><?= e(__('Amount Due (On Arrival)')) ?></span><span class="font-bold text-emerald-600"><?= e(booking_total($booking)) ?></span></div>
                <div><span class="text-slate-400 block"><?= e(__('Phone')) ?></span><span class="font-semibold" dir="ltr"><?= e($booking['customer_phone']) ?></span></div>
                <div><span class="text-slate-400 block"><?= e(__('Booked On')) ?></span><span class="font-semibold"><?= e(format_date($booking['created_at'])) ?></span></div>
            </div>
            <?php if ($lines = booking_lines($booking)): ?>
                <div class="text-xs border-t border-slate-200 pt-3 space-y-1">
                    <?php foreach ($lines as [$label, $amount]): ?>
                        <div class="flex justify-between"><span class="text-slate-500"><?= e($label) ?></span><span class="font-semibold"><?= e($amount) ?></span></div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($booking['price_on_request']): ?>
                <p class="text-xs text-amber-700 bg-amber-50 rounded-lg px-3 py-2"><i class="fa-solid fa-circle-info me-1"></i> <?= e(__('The price will be confirmed with you by our team before your trip.')) ?></p>
            <?php endif; ?>
            <?php if ($booking['notes']): ?>
                <div class="text-xs"><span class="text-slate-400 block"><?= e(__('Special Requests')) ?></span><?= nl2br(e($booking['notes'])) ?></div>
            <?php endif; ?>
        </div>
        <p class="text-xs text-slate-500 mb-6"><?= e(setting('voucher_note', __('Please present this voucher to your guide on arrival.'))) ?></p>

        <div class="flex gap-3 no-print">
            <button type="button" onclick="window.print()" class="w-1/2 bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold py-2.5 rounded-xl text-xs transition">
                <i class="fa-solid fa-print me-1"></i> <?= e(__('Print Voucher')) ?>
            </button>
            <a href="<?= e(url()) ?>" class="w-1/2 bg-brand-600 hover:bg-brand-500 text-white font-bold py-2.5 rounded-xl text-xs transition flex items-center justify-center"><?= e(__('Done')) ?></a>
        </div>
    </div>

    <p class="text-center text-xs text-slate-400 mt-4 no-print"><?= e(__('Bookmark this page to come back to your voucher anytime.')) ?></p>

    <?php if ($canCancel): ?>
        <form method="post" class="text-center mt-6 no-print" onsubmit="return confirm(<?= e(json_encode(__('Cancel this booking?'), JSON_UNESCAPED_UNICODE)) ?>);">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel">
            <button class="text-xs font-semibold text-rose-600 hover:underline"><i class="fa-solid fa-ban me-1"></i> <?= e(__('Cancel this booking')) ?></button>
        </form>
    <?php endif; ?>
</section>

<?php require APP_ROOT . '/includes/footer.php'; ?>
