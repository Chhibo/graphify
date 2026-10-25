<?php
require __DIR__ . '/includes/auth.php';
require_permission('bookings');

$id = (int) ($_GET['id'] ?? 0);
$booking = $id ? db_one('SELECT * FROM ' . tbl('bookings') . ' WHERE id = ?', [$id]) : null;
if ($id && !$booking) {
    flash('error', 'Booking not found.');
    redirect('admin/bookings.php');
}
$isNew = !$booking;
$statuses = booking_statuses();
$tours = db_all('SELECT id, type, title, price, child_price, infant_price, hide_price, status FROM ' . tbl('tours') . ' ORDER BY type DESC, title');
$fromRequest = !$booking && !empty($_GET['request']) ? db_one('SELECT * FROM ' . tbl('trip_requests') . ' WHERE id = ?', [(int) $_GET['request']]) : null;
$self = 'admin/booking.php' . ($id ? '?id=' . $id : ($fromRequest ? '?request=' . $fromRequest['id'] : ''));

/** Email the guest asking for a verified review, and remember when it was sent. */
function send_review_request(array $b): bool
{
    $sent = send_booking_email('review_request', $b);
    db_update('bookings', ['review_requested_at' => now()], (int) $b['id']);
    return $sent;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf($self);

    if (post('action') === 'delete' && $booking && can('bookings_delete')) {
        db_exec('DELETE FROM ' . tbl('bookings') . ' WHERE id = ?', [$id]);
        flash('success', 'Booking ' . $booking['reference'] . ' deleted.');
        redirect('admin/bookings.php');
    }
    if (post('action') === 'review_request' && $booking) {
        $sent = send_review_request($booking);
        flash($sent ? 'success' : 'error', $sent ? 'Review request emailed to the guest.' : 'Email could not be sent (check Settings > Email).');
        redirect($self);
    }

    $tour = db_one('SELECT * FROM ' . tbl('tours') . ' WHERE id = ?', [(int) post('tour_id')]);
    $data = [
        'travel_date' => post('travel_date'),
        'adults' => max(1, (int) post('adults', 1)),
        'children' => max(0, (int) post('children')),
        'infants' => max(0, (int) post('infants')),
        'customer_name' => mb_substr(post('customer_name'), 0, 150),
        'customer_email' => mb_substr(post('customer_email'), 0, 190),
        'customer_phone' => mb_substr(post('customer_phone'), 0, 60),
        'notes' => post('notes'),
        'status' => isset($statuses[post('status')]) ? post('status') : 'pending',
        'admin_notes' => post('admin_notes'),
        'updated_at' => now(),
    ];
    $errors = [];
    if (!$tour) {
        $errors[] = 'Choose an experience.';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['travel_date'])) {
        $errors[] = 'Enter a valid travel date.';
    }
    if ($data['customer_name'] === '' || !filter_var($data['customer_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Guest name and a valid email are required.';
    }
    if ($errors) {
        flash('error', implode(' ', $errors));
        redirect($self);
    }

    $data['guests'] = $data['adults'] + $data['children'] + $data['infants'];
    $onRequest = post('price_on_request') === '1';
    $tourChanged = $booking && (int) $booking['tour_id'] !== (int) $tour['id'];
    $tourPrices = tour_prices($tour);
    $inputs = [];
    foreach (['unit_price' => 'adult', 'child_price' => 'child', 'infant_price' => 'infant'] as $field => $cat) {
        $value = post($field);
        // Experience changed and the old price was left untouched: use the new experience's price.
        if ($tourChanged && $value !== '' && $value === price_field($booking, $field)) {
            $value = '';
        }
        $inputs[$field] = $value;
        $data[$field] = $onRequest ? 0 : ($value !== '' ? max(0, (float) $value) : $tourPrices[$cat]);
    }
    if (!$onRequest && prices_hidden($tour) && $inputs['unit_price'] === '') {
        flash('error', 'This experience has no public price: enter the agreed adult price, or keep "Price to be confirmed" ticked.');
        redirect($self);
    }
    $data['tour_id'] = $tour['id'];
    $data['tour_title'] = $tour['title'];
    $data['total'] = $onRequest ? 0 : round($data['adults'] * $data['unit_price'] + $data['children'] * $data['child_price'] + $data['infants'] * $data['infant_price'], 2);
    $data['price_on_request'] = $onRequest ? 1 : 0;

    if ($isNew) {
        $data += [
            'reference' => generate_booking_reference(),
            'access_token' => bin2hex(random_bytes(16)),
            'lang' => isset(languages()[post('lang')]) ? post('lang') : default_lang(),
            'request_id' => $fromRequest['id'] ?? null,
            'ip_address' => client_ip(),
            'created_at' => now(),
        ];
        $id = db_insert('bookings', $data);
        if ($fromRequest) {
            db_update('trip_requests', ['status' => 'booked', 'updated_at' => now()], (int) $fromRequest['id']);
        }
        flash('success', 'Booking ' . $data['reference'] . ' created.');
    } else {
        if (isset(languages()[post('lang')])) {
            $data['lang'] = post('lang');
        }
        db_update('bookings', $data, $id);
        flash('success', 'Booking updated.');
    }

    $fresh = db_one('SELECT * FROM ' . tbl('bookings') . ' WHERE id = ?', [$id]);
    if (post('notify') === '1') {
        // Uses the editable "Status: ..." templates (Admin > Message Templates), in the guest's language.
        $sent = send_booking_email('status_' . $fresh['status'], $fresh, null, ['{message}' => post('message')]);
        if ($sent && $fresh['status'] === 'completed') {
            db_update('bookings', ['review_requested_at' => now()], $id); // the "completed" email contains the review link
        }
        flash($sent ? 'success' : 'error', $sent ? 'Email sent to the guest.' : 'Email could not be sent (check mail settings / server mail function).');
    }
    // Ask for a verified review automatically when a trip is marked completed.
    $justCompleted = $fresh['status'] === 'completed' && ($booking['status'] ?? '') !== 'completed';
    if ($justCompleted && !$fresh['review_requested_at'] && setting('review_request_on_complete', '1') === '1' && setting('booking_reviews', '1') === '1'
        && !(post('notify') === '1')) {
        send_review_request($fresh);
        flash('success', 'A review request was emailed to the guest.');
    }
    redirect('admin/booking.php?id=' . $id);
}

/** Value shown in a price field: empty while the price is still on request. */
function price_field(?array $b, string $field): string
{
    return (!$b || $b[$field] === '' || !empty($b['price_on_request'])) ? '' : (string) (float) $b[$field];
}

$b = $booking ?: [
    'tour_id' => (int) ($_GET['tour'] ?? 0), 'travel_date' => date('Y-m-d', strtotime('+1 day')), 'adults' => 2, 'children' => 0, 'infants' => 0,
    'unit_price' => '', 'child_price' => '', 'infant_price' => '', 'price_on_request' => 0, 'lang' => default_lang(),
    'customer_name' => '', 'customer_email' => '', 'customer_phone' => '', 'notes' => '', 'status' => 'confirmed', 'admin_notes' => '',
];
if ($fromRequest) {
    $b = array_merge($b, [
        'customer_name' => $fromRequest['name'], 'customer_email' => $fromRequest['email'], 'customer_phone' => $fromRequest['phone'],
        'adults' => $fromRequest['adults'], 'children' => $fromRequest['children'], 'lang' => $fromRequest['lang'] ?: default_lang(),
        'travel_date' => $fromRequest['start_date'] ?: $b['travel_date'],
        'admin_notes' => 'From trip request ' . $fromRequest['reference'] . ($fromRequest['destinations'] ? ' (' . $fromRequest['destinations'] . ')' : ''),
        'notes' => (string) $fromRequest['message'],
    ]);
}
$bookingLang = $b['lang'] ?: default_lang();
$waTemplates = [];
if ($booking && $booking['customer_phone']) {
    $vars = booking_vars($booking, $bookingLang);
    foreach (whatsapp_templates() as $key => [$name, , $group]) {
        if ($group === 'booking') {
            $waTemplates[$key] = [$name, whatsapp_text($key, $vars, $bookingLang)];
        }
    }
}
$review = $booking ? db_one('SELECT id, rating, status FROM ' . tbl('reviews') . ' WHERE booking_id = ?', [$booking['id']]) : null;

admin_header($isNew ? 'New booking' : 'Booking ' . $booking['reference'], 'bookings');
?>
<a href="<?= e(url('admin/bookings.php')) ?>" class="text-xs font-bold text-slate-500 hover:text-brand-600"><i class="fa-solid fa-arrow-left mr-1"></i> All bookings</a>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-3">
    <form method="post" class="card p-6 xl:col-span-2 space-y-6">
        <?= csrf_field() ?>
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-lg"><?= $isNew ? ($fromRequest ? 'Create a booking from trip request ' . e($fromRequest['reference']) : 'Create a booking (phone / walk-in)') : 'Booking details' ?></h2>
            <?php if (!$isNew): ?><?= status_badge($booking['status']) ?><?php endif; ?>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="lbl">Experience</label>
                <select class="inp" name="tour_id" required>
                    <option value="">— Choose —</option>
                    <?php foreach ($tours as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" data-hidden="<?= prices_hidden($t) ? 1 : 0 ?>" <?= (int) $b['tour_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e(($t['type'] === 'trip' ? 'Trip: ' : 'Activity: ') . $t['title'] . ' (' . tour_price($t) . ')' . ($t['status'] !== 'active' ? ' [hidden]' : '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php f_input('Travel date', 'travel_date', $b['travel_date'], 'date', 'required'); ?>
            <?php f_select('Status', 'status', array_map(fn ($s) => $s[0], $statuses), $b['status']); ?>
        </div>
        <div class="grid grid-cols-3 gap-4">
            <?php f_input('Adults', 'adults', $b['adults'], 'number', 'min="1" required'); ?>
            <?php f_input('Children', 'children', $b['children'], 'number', 'min="0"', setting('child_age_label', 'Ages 2-11')); ?>
            <?php f_input('Infants', 'infants', $b['infants'], 'number', 'min="0"', setting('infant_age_label', 'Under 2')); ?>
        </div>
        <div class="rounded-xl border <?= $b['price_on_request'] ? 'border-amber-300 bg-amber-50' : 'border-slate-200' ?> p-4 space-y-3" id="priceBox">
            <?php f_check('Price to be confirmed (price on request)', 'price_on_request', (bool) $b['price_on_request'], 'The guest sees "To be confirmed". Untick it and enter the agreed prices once you have confirmed them with the guest.'); ?>
            <div class="grid grid-cols-3 gap-4" data-booking-prices>
                <?php f_input('Adult price', 'unit_price', price_field($booking, 'unit_price'), 'number', 'step="0.01" min="0"'); ?>
                <?php f_input('Child price', 'child_price', price_field($booking, 'child_price'), 'number', 'step="0.01" min="0"'); ?>
                <?php f_input('Infant price', 'infant_price', price_field($booking, 'infant_price'), 'number', 'step="0.01" min="0"'); ?>
            </div>
            <p class="help">Leave a price empty to use the experience's current price.</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <?php f_input('Guest name', 'customer_name', $b['customer_name'], 'text', 'required'); ?>
            <?php f_input('Email', 'customer_email', $b['customer_email'], 'email', 'required'); ?>
            <?php f_input('Phone / WhatsApp', 'customer_phone', $b['customer_phone']); ?>
        </div>
        <?php if (count(languages()) > 1): ?>
            <div class="sm:w-1/3"><?php f_select('Guest language (emails & WhatsApp)', 'lang', array_map(fn ($l) => $l['name'], languages()), $bookingLang); ?></div>
        <?php endif; ?>
        <?php f_textarea('Guest special requests', 'notes', $b['notes'], 3); ?>
        <?php f_textarea('Internal admin notes (never shown to the guest)', 'admin_notes', $b['admin_notes'], 3); ?>
        <div class="bg-slate-50 rounded-xl p-4 space-y-3">
            <?php f_check('Email the guest about this update', 'notify', false, 'Sends the current status, booking details and voucher link.'); ?>
            <?php f_textarea('Optional message to include in the email', 'message', '', 2); ?>
        </div>
        <div class="flex justify-end"><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?= $isNew ? 'Create booking' : 'Save changes' ?></button></div>
    </form>

    <?php if (!$isNew): ?>
    <div class="space-y-6">
        <div class="card p-6 space-y-3 text-sm">
            <h2 class="font-bold">Summary</h2>
            <?php if (count(languages()) > 1): ?><div class="flex justify-between"><span class="text-slate-500">Language</span><span><?= e(languages()[$bookingLang]['name'] ?? $bookingLang) ?></span></div><?php endif; ?>
            <div class="flex justify-between"><span class="text-slate-500">Reference</span><b class="font-mono"><?= e($booking['reference']) ?></b></div>
            <div class="flex justify-between"><span class="text-slate-500">Guests</span><span><?= e(guests_summary($booking)) ?></span></div>
            <?php foreach (booking_lines($booking) as [$label, $amount]): ?>
                <div class="flex justify-between text-xs"><span class="text-slate-400"><?= e($label) ?></span><span><?= e($amount) ?></span></div>
            <?php endforeach; ?>
            <div class="flex justify-between text-base"><span class="text-slate-500">Due on arrival</span><b class="text-emerald-600"><?= e(booking_total($booking)) ?></b></div>
            <div class="flex justify-between"><span class="text-slate-500">Booked</span><span><?= e(format_date($booking['created_at'], 'M j, Y H:i')) ?></span></div>
            <div class="flex justify-between"><span class="text-slate-500">Last update</span><span><?= e(time_ago($booking['updated_at'])) ?></span></div>
            <?php if ($booking['ip_address']): ?><div class="flex justify-between"><span class="text-slate-500">IP</span><span class="text-xs"><?= e($booking['ip_address']) ?></span></div><?php endif; ?>
            <a href="<?= e(voucher_url($booking)) ?>" target="_blank" class="btn btn-light w-full justify-center"><i class="fa-solid fa-ticket"></i> Open guest voucher</a>
        </div>
        <?php if ($waTemplates): ?>
        <div class="card p-6 space-y-3 text-sm">
            <div class="flex items-center justify-between">
                <h2 class="font-bold"><i class="fa-brands fa-whatsapp text-emerald-500"></i> WhatsApp messages</h2>
                <?php if (can('settings')): ?><a href="<?= e(url('admin/templates.php?tab=wa')) ?>" class="text-xs text-brand-600 font-semibold">Edit templates</a><?php endif; ?>
            </div>
            <p class="help -mt-1">Ready-made messages in <?= e(languages()[$bookingLang]['name'] ?? $bookingLang) ?>, filled with this booking. Click to open WhatsApp, edit before sending if needed.</p>
            <?php foreach ($waTemplates as $key => [$name, $text]): ?>
                <details class="border border-slate-200 rounded-xl">
                    <summary class="cursor-pointer px-3 py-2 font-semibold flex items-center justify-between"><span><?= e($name) ?></span><i class="fa-solid fa-chevron-down text-xs text-slate-400"></i></summary>
                    <div class="p-3 pt-0 space-y-2">
                        <textarea id="wa_<?= e($key) ?>" class="inp text-xs" rows="5" dir="<?= lang_dir($bookingLang) ?>"><?= e($text) ?></textarea>
                        <div class="flex gap-2">
                            <a href="<?= e(whatsapp_link($booking['customer_phone'], $text)) ?>" data-wa-link="wa_<?= e($key) ?>" target="_blank" rel="noopener" class="btn flex-1 justify-center bg-emerald-500 text-white hover:bg-emerald-600"><i class="fa-brands fa-whatsapp"></i> Send</a>
                            <button type="button" class="btn btn-light" data-copy="wa_<?= e($key) ?>" title="Copy"><i class="fa-solid fa-copy"></i></button>
                        </div>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="card p-6 space-y-2 text-sm">
            <h2 class="font-bold mb-1">Contact guest</h2>
            <a href="mailto:<?= e($booking['customer_email']) ?>?subject=<?= rawurlencode('Your booking ' . $booking['reference']) ?>" class="btn btn-light w-full justify-center"><i class="fa-solid fa-envelope"></i> Email</a>
            <?php if ($booking['customer_phone']): ?>
                <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $booking['customer_phone'])) ?>" class="btn btn-light w-full justify-center"><i class="fa-solid fa-phone"></i> Call</a>
            <?php endif; ?>
            <?php if ($booking['request_id']): ?>
                <a href="<?= e(url('admin/request.php?id=' . $booking['request_id'])) ?>" class="btn btn-light w-full justify-center"><i class="fa-solid fa-route"></i> Original trip request</a>
            <?php endif; ?>
        </div>
        <div class="card p-6 space-y-2 text-sm">
            <h2 class="font-bold mb-1"><i class="fa-solid fa-star text-amber-400"></i> Verified review</h2>
            <?php if ($review): ?>
                <p>The guest left a <b><?= (int) $review['rating'] ?>/5</b> review (<?= e($review['status']) ?>).</p>
                <?php if (can('reviews')): ?><a href="<?= e(url('admin/reviews.php?edit=' . $review['id'])) ?>" class="btn btn-light w-full justify-center">Open review</a><?php endif; ?>
            <?php else: ?>
                <p class="help"><?= $booking['review_requested_at'] ? 'Review requested ' . e(time_ago($booking['review_requested_at'])) . '.' : 'Ask the guest to review the trip. Their review gets a "Verified booking" badge.' ?></p>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="review_request"><button class="btn btn-light w-full justify-center"><i class="fa-solid fa-envelope"></i> Email review request</button></form>
                <input type="hidden" id="reviewLink" value="<?= e(review_url($booking)) ?>">
                <button type="button" class="btn btn-light w-full justify-center" data-copy="reviewLink"><i class="fa-solid fa-link"></i> Copy review link</button>
            <?php endif; ?>
        </div>
        <?php if (can('bookings_delete')): ?>
        <form method="post" class="card p-6" data-confirm="Delete this booking permanently?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <button class="btn btn-danger w-full justify-center"><i class="fa-solid fa-trash"></i> Delete booking</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<script>
// Keep the WhatsApp "Send" links in sync with edits made in the text boxes.
document.querySelectorAll('[data-wa-link]').forEach(function (a) {
    var box = document.getElementById(a.getAttribute('data-wa-link'));
    box.addEventListener('input', function () { a.href = a.href.split('?')[0] + '?text=' + encodeURIComponent(box.value); });
});
(function () {
    var box = document.querySelector('input[type=checkbox][name=price_on_request]');
    var prices = document.querySelector('[data-booking-prices]');
    var tour = document.querySelector('select[name=tour_id]');
    function sync() { prices.style.opacity = box.checked ? '.4' : '1'; }
    box.addEventListener('change', sync);
    <?php if ($isNew): ?>
    tour.addEventListener('change', function () {
        var opt = tour.options[tour.selectedIndex];
        box.checked = opt && opt.getAttribute('data-hidden') === '1';
        sync();
    });
    <?php endif; ?>
    sync();
})();
</script>
<?php admin_footer(); ?>
