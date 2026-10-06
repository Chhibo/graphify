<?php
require __DIR__ . '/includes/bootstrap.php';

$ref = (string) ($_GET['ref'] ?? '');
$token = (string) ($_GET['t'] ?? '');
$booking = db_one('SELECT * FROM ' . tbl('bookings') . ' WHERE reference = ?', [$ref]);
if (!$booking || !hash_equals($booking['access_token'], $token)) {
    flash('error', 'Booking not found. Please check your reference and email.');
    redirect('my-booking.php');
}

$canCancel = setting('allow_guest_cancel', '1') === '1'
    && in_array($booking['status'], ['pending', 'confirmed'], true)
    && strtotime($booking['travel_date']) > strtotime('today +' . (int) setting('cancel_min_days', 1) . ' days');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'cancel') {
    if (!verify_csrf() || !$canCancel) {
        flash('error', 'This booking can no longer be cancelled online. Please contact us.');
    } else {
        db_update('bookings', ['status' => 'cancelled', 'updated_at' => now()], (int) $booking['id']);
        $booking['status'] = 'cancelled';
        $adminEmail = setting('admin_notify_email', setting('contact_email'));
        if ($adminEmail) {
            send_mail($adminEmail, 'Booking cancelled by guest: ' . $booking['reference'], '<p>The guest cancelled this booking.</p>' . booking_email_table($booking));
        }
        flash('success', 'Your booking has been cancelled.');
    }
    redirect(voucher_url($booking));
}

$justBooked = ($_SESSION['_just_booked'] ?? '') === $booking['reference'];
unset($_SESSION['_just_booked']);
$tour = $booking['tour_id'] ? db_one('SELECT slug, image, destination, duration FROM ' . tbl('tours') . ' WHERE id = ?', [$booking['tour_id']]) : null;

$pageTitle = 'Voucher ' . $booking['reference'];
require APP_ROOT . '/includes/header.php';
page_banner($justBooked ? 'Booking Confirmed!' : 'Your Reservation', $justBooked ? 'Your reservation code has been registered. No payment was charged.' : 'Reference ' . $booking['reference'], 'Pay On Arrival');
?>

<section class="py-14 max-w-2xl mx-auto px-4">
    <div id="printArea" class="bg-white rounded-3xl p-6 sm:p-8 shadow-2xl border border-slate-200 text-center relative">
        <div class="w-16 h-16 <?= $booking['status'] === 'cancelled' ? 'bg-rose-100 text-rose-600' : 'bg-emerald-100 text-emerald-600' ?> rounded-full flex items-center justify-center mx-auto text-3xl mb-4">
            <i class="fa-solid <?= $booking['status'] === 'cancelled' ? 'fa-circle-xmark' : 'fa-circle-check' ?>"></i>
        </div>
        <h2 class="font-serif text-2xl font-bold text-slate-900"><?= e(setting('site_name', 'WanderLuxe')) ?> Reservation</h2>
        <div class="mt-2"><?= status_badge($booking['status']) ?></div>
        <?php if ($booking['status'] === 'pending'): ?>
            <p class="text-xs text-slate-500 mt-2">Your request is awaiting confirmation from our team. We will contact you by email or WhatsApp.</p>
        <?php endif; ?>

        <div class="my-6 p-5 bg-slate-50 border-2 border-dashed border-brand-500 rounded-2xl text-left space-y-3">
            <div class="flex justify-between items-center border-b border-slate-200 pb-3">
                <span class="text-xs font-bold text-brand-600 uppercase tracking-widest">Reservation Voucher</span>
                <span class="text-xs font-mono font-bold bg-brand-100 text-brand-700 px-2 py-0.5 rounded">#<?= e($booking['reference']) ?></span>
            </div>
            <div>
                <span class="text-xs text-slate-400 block">Experience</span>
                <span class="font-bold text-sm text-slate-800"><?= e($booking['tour_title']) ?></span>
                <?php if ($tour): ?><span class="block text-xs text-slate-500"><i class="fa-solid fa-location-dot text-brand-500 mr-1"></i><?= e($tour['destination']) ?> · <?= e($tour['duration']) ?></span><?php endif; ?>
            </div>
            <div class="grid grid-cols-2 gap-3 text-xs">
                <div><span class="text-slate-400 block">Lead Guest</span><span class="font-semibold"><?= e($booking['customer_name']) ?></span></div>
                <div><span class="text-slate-400 block">Travel Date</span><span class="font-semibold"><?= e(format_date($booking['travel_date'], 'D, M j, Y')) ?></span></div>
                <div><span class="text-slate-400 block">Guests</span><span class="font-semibold"><?= (int) $booking['guests'] ?> × <?= e(money($booking['unit_price'])) ?></span></div>
                <div><span class="text-slate-400 block">Amount Due (On Arrival)</span><span class="font-bold text-emerald-600"><?= e(money($booking['total'])) ?></span></div>
                <div><span class="text-slate-400 block">Phone</span><span class="font-semibold"><?= e($booking['customer_phone']) ?></span></div>
                <div><span class="text-slate-400 block">Booked On</span><span class="font-semibold"><?= e(format_date($booking['created_at'])) ?></span></div>
            </div>
            <?php if ($booking['notes']): ?>
                <div class="text-xs"><span class="text-slate-400 block">Special Requests</span><?= nl2br(e($booking['notes'])) ?></div>
            <?php endif; ?>
        </div>
        <p class="text-xs text-slate-500 mb-6"><?= e(setting('voucher_note', 'Please present this voucher to your guide on arrival.')) ?></p>

        <div class="flex gap-3 no-print">
            <button type="button" onclick="window.print()" class="w-1/2 bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold py-2.5 rounded-xl text-xs transition">
                <i class="fa-solid fa-print mr-1"></i> Print Voucher
            </button>
            <a href="<?= e(url()) ?>" class="w-1/2 bg-brand-600 hover:bg-brand-500 text-white font-bold py-2.5 rounded-xl text-xs transition flex items-center justify-center">Done</a>
        </div>
    </div>

    <p class="text-center text-xs text-slate-400 mt-4 no-print">Bookmark this page to come back to your voucher anytime.</p>

    <?php if ($canCancel): ?>
        <form method="post" class="text-center mt-6 no-print" onsubmit="return confirm('Cancel this booking?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel">
            <button class="text-xs font-semibold text-rose-600 hover:underline"><i class="fa-solid fa-ban mr-1"></i> Cancel this booking</button>
        </form>
    <?php endif; ?>
</section>

<?php require APP_ROOT . '/includes/footer.php'; ?>
