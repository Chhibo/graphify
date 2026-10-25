<?php
/**
 * Verified reviews: guests open this page from the link in their email or voucher
 * after their trip. The review is linked to the booking and marked "Verified booking".
 */
require __DIR__ . '/includes/bootstrap.php';

$ref = (string) ($_GET['ref'] ?? '');
$token = (string) ($_GET['t'] ?? '');
$booking = db_one('SELECT * FROM ' . tbl('bookings') . ' WHERE reference = ?', [$ref]);
if (!$booking || !hash_equals($booking['access_token'], $token)) {
    flash('error', __('Booking not found. Please check your reference and email.'));
    redirect('my-booking.php');
}
$self = review_url($booking);
$existing = db_one('SELECT * FROM ' . tbl('reviews') . ' WHERE booking_id = ?', [$booking['id']]);
$allowed = booking_can_review($booking);
$tour = $booking['tour_id'] ? db_one('SELECT id, title, slug, image FROM ' . tbl('tours') . ' WHERE id = ?', [$booking['tour_id']]) : null;
$tourShown = $tour ? localize_row($tour, 'tour') : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allowed && !$existing) {
    $name = mb_substr(post('name'), 0, 150);
    $content = mb_substr(post('content'), 0, 3000);
    $rating = max(1, min(5, (int) post('rating', 5)));
    $error = null;
    if (!verify_csrf()) {
        $error = __('Your session expired. Please try again.');
    } elseif ($name === '' || mb_strlen($content) < 10) {
        $error = __('Please enter your name and a review of at least 10 characters.');
    }
    $photos = [];
    if (!$error) {
        try {
            $photos = array_slice(upload_images('photos'), 0, 3);
        } catch (RuntimeException $ex) {
            $error = __('One of the photos could not be uploaded. Use JPG, PNG or WEBP images up to 5 MB.');
        }
    }
    if ($error) {
        remember_input(['name' => $name, 'content' => $content, 'rating' => $rating]);
        flash('error', $error);
        redirect($self);
    }
    $auto = setting('verified_reviews_auto_approve', '1') === '1';
    db_insert('reviews', [
        'name' => $name,
        'trip' => $tour['title'] ?? $booking['tour_title'],
        'rating' => $rating,
        'content' => $content,
        'avatar' => '',
        'photos' => implode(',', $photos),
        'booking_id' => $booking['id'],
        'tour_id' => $booking['tour_id'],
        'verified' => 1,
        'lang' => current_lang(),
        'status' => $auto ? 'approved' : 'pending',
        'ip_address' => client_ip(),
        'created_at' => now(),
    ]);
    if ($booking['tour_id']) {
        recalc_tour_rating((int) $booking['tour_id']);
    }
    clear_input();
    flash('success', $auto ? __('Thank you! Your review has been published.') : __('Thank you! Your review will appear after moderation.'));
    redirect($self);
}

$pageTitle = __('Review your trip');
$noindex = true;
$noPopup = true;
require APP_ROOT . '/includes/header.php';
page_banner(__('How Was Your Trip?'), $tourShown['title'] ?? $booking['tour_title'], __('Verified Review'), $tour['image'] ?? '');
?>
<section class="py-14 max-w-2xl mx-auto px-4">
    <?php if ($existing): ?>
        <div class="bg-white rounded-3xl p-8 shadow-xl border border-slate-100 text-center space-y-3">
            <i class="fa-solid fa-circle-check text-5xl text-emerald-500"></i>
            <h2 class="font-serif text-2xl font-bold text-slate-900"><?= e(__('Thank you for your review!')) ?></h2>
            <div class="text-amber-400"><?= stars((int) $existing['rating']) ?></div>
            <p class="text-sm text-slate-600 italic">"<?= e($existing['content']) ?>"</p>
            <?php if ($existing['status'] !== 'approved'): ?><p class="text-xs text-slate-400"><?= e(__('It will be published after a quick check by our team.')) ?></p><?php endif; ?>
            <?php if ($tour): ?><a href="<?= e(url('tour.php?slug=' . urlencode($tour['slug']))) ?>" class="inline-block mt-2 text-sm font-bold text-brand-600 underline"><?= e(__('See the trip page')) ?></a><?php endif; ?>
        </div>
    <?php elseif (!$allowed): ?>
        <div class="bg-white rounded-3xl p-8 shadow-xl border border-slate-100 text-center space-y-3">
            <i class="fa-solid fa-hourglass-half text-5xl text-amber-400"></i>
            <h2 class="font-serif text-2xl font-bold text-slate-900"><?= e(__('Reviews open after your trip')) ?></h2>
            <p class="text-sm text-slate-600"><?= e(__f('You can review this booking after your travel date (%s).', format_date($booking['travel_date']))) ?></p>
            <a href="<?= e(voucher_url($booking)) ?>" class="inline-block mt-2 px-6 py-3 bg-brand-600 text-white rounded-xl font-bold text-sm"><?= e(__('Back to my voucher')) ?></a>
        </div>
    <?php else: ?>
        <form method="post" enctype="multipart/form-data" class="bg-white rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-100 space-y-5">
            <?= csrf_field() ?>
            <div class="flex items-center gap-3 bg-emerald-50 text-emerald-800 rounded-xl px-4 py-3 text-sm">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= e(__f('Your review will carry a "Verified booking" badge (booking %s).', $booking['reference'])) ?></span>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1"><?= e(__('Your rating')) ?></label>
                <div class="star-rating" role="radiogroup" aria-label="<?= e(__('Your rating')) ?>">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" <?= (int) old('rating', 5) === $i ? 'checked' : '' ?>>
                        <label for="star<?= $i ?>" title="<?= $i ?>/5"><i class="fa-solid fa-star"></i></label>
                    <?php endfor; ?>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1" for="revName"><?= e(__('Name shown with your review')) ?></label>
                <input id="revName" type="text" name="name" required maxlength="150" value="<?= e(old('name', $booking['customer_name'])) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1" for="revText"><?= e(__('Your review')) ?></label>
                <textarea id="revText" name="content" required rows="5" maxlength="3000" placeholder="<?= e(__('What did you enjoy most? Any tips for other travellers?')) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none"><?= e(old('content')) ?></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1"><?= e(__('Add up to 3 photos (optional)')) ?></label>
                <input type="file" name="photos[]" accept="image/*" multiple class="block w-full text-sm text-slate-500 file:me-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-brand-50 file:text-brand-700">
            </div>
            <button class="w-full bg-accent-500 hover:bg-accent-600 text-white font-bold py-3 rounded-xl shadow-lg shadow-accent-500/30 text-sm"><i class="fa-solid fa-paper-plane me-1"></i> <?= e(__('Publish my review')) ?></button>
        </form>
    <?php endif; ?>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
