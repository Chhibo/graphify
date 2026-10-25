<?php
/**
 * Handles the booking form (POST). No payment is taken: a booking request is
 * stored, emails are sent and the guest is redirected to their voucher.
 */
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$back = $_SERVER['HTTP_REFERER'] ?? url('index.php');
if (strpos($back, base_url()) !== 0 && strpos($back, '/') !== 0) {
    $back = url('index.php');
}

$input = [
    'tour_id' => (int) post('tour_id'),
    'travel_date' => post('travel_date'),
    // Older cached forms only sent "guests": treat them as adults.
    'adults' => post('adults') !== '' ? (int) post('adults') : (int) post('guests'),
    'children' => max(0, (int) post('children')),
    'infants' => max(0, (int) post('infants')),
    'name' => mb_substr(post('name'), 0, 150),
    'email' => mb_substr(post('email'), 0, 190),
    'phone' => mb_substr(post('phone'), 0, 60),
    'notes' => mb_substr(post('notes'), 0, 2000),
];

function booking_fail(string $msg, array $input, string $back): void
{
    remember_input($input);
    $_SESSION['_open_booking'] = true;
    flash('error', $msg);
    redirect($back);
}

if (!verify_csrf()) {
    booking_fail(__('Your session expired. Please submit the form again.'), $input, $back);
}
if (setting('bookings_enabled', '1') === '0') {
    booking_fail(__('Online bookings are temporarily closed. Please contact us directly.'), $input, $back);
}
if ($err = spam_check('booking', 10)) {
    booking_fail($err, $input, $back);
}

$tour = db_one('SELECT * FROM ' . tbl('tours') . " WHERE id = ? AND status = 'active'", [$input['tour_id']]);
if (!$tour) {
    booking_fail(__('Please select a valid tour or activity.'), $input, $back);
}

$date = DateTime::createFromFormat('Y-m-d', $input['travel_date']);
$minDate = new DateTime('today +' . max(0, (int) setting('booking_min_days', 1)) . ' days');
$maxDate = new DateTime('today +' . max(1, (int) setting('booking_max_months', 18)) . ' months');
if (!$date || $date->format('Y-m-d') !== $input['travel_date']) {
    booking_fail(__('Please choose a valid travel date.'), $input, $back);
}
if ($date < $minDate) {
    booking_fail(__f('The earliest available travel date is %s.', format_date($minDate->format('Y-m-d'))), $input, $back);
}
if ($date > $maxDate) {
    booking_fail(__f('Bookings can only be made up to %d months ahead.', (int) setting('booking_max_months', 18)), $input, $back);
}
$blocked = lines(setting('blocked_dates', ''));
if (in_array($input['travel_date'], $blocked, true)) {
    booking_fail(__f('Sorry, we are fully booked on %s. Please choose another date.', format_date($input['travel_date'])), $input, $back);
}
if (!$tour['allow_children']) {
    $input['children'] = 0;
}
if (!$tour['allow_infants']) {
    $input['infants'] = 0;
}
$guests = $input['adults'] + $input['children'] + $input['infants'];
if ($input['adults'] < 1) {
    booking_fail(__('At least one adult is required per booking.'), $input, $back);
}
if ($guests > (int) $tour['max_guests']) {
    booking_fail(__f('This experience accepts up to %d guests per booking.', (int) $tour['max_guests']), $input, $back);
}
if ($input['name'] === '' || !filter_var($input['email'], FILTER_VALIDATE_EMAIL) || !preg_match('/^[0-9 +().\-]{6,}$/', $input['phone'])) {
    booking_fail(__('Please enter your name, a valid email address and phone number.'), $input, $back);
}

// Optional daily capacity per tour (0 = unlimited).
$capacity = (int) setting('daily_capacity', 0);
if ($capacity > 0) {
    $booked = (int) db_value('SELECT COALESCE(SUM(guests),0) FROM ' . tbl('bookings') . " WHERE tour_id = ? AND travel_date = ? AND status IN ('pending','confirmed')", [$tour['id'], $input['travel_date']]);
    if ($booked + $guests > $capacity) {
        $left = max(0, $capacity - $booked);
        booking_fail($left ? __f('Only %d spot(s) left on this date.', $left) : __('This date is fully booked. Please choose another date.'), $input, $back);
    }
}

$autoConfirm = setting('auto_confirm', '0') === '1';
$booking = [
    'reference' => generate_booking_reference(),
    'access_token' => bin2hex(random_bytes(16)),
    'tour_id' => $tour['id'],
    'tour_title' => $tour['title'],
    'travel_date' => $input['travel_date'],
    'guests' => $guests,
    'adults' => $input['adults'],
    'children' => $input['children'],
    'infants' => $input['infants'],
    'customer_name' => $input['name'],
    'customer_email' => $input['email'],
    'customer_phone' => $input['phone'],
    'notes' => $input['notes'],
    'status' => $autoConfirm ? 'confirmed' : 'pending',
    'lang' => current_lang(),
    'ip_address' => client_ip(),
    'created_at' => now(),
    'updated_at' => now(),
];
$booking += booking_quote($tour, $input['adults'], $input['children'], $input['infants']);
$booking['id'] = db_insert('bookings', $booking);

// Notify the guest (in the language they booked in) and the admin, using the editable templates.
$lang = $booking['lang'];
send_booking_email('booking_guest', $booking, null, [
    '{status_message}' => $autoConfirm ? __('Your booking is confirmed.', $lang) : __('We have received your booking request and our team will confirm it shortly.', $lang),
    '{voucher_note}' => setting('voucher_note', __('Please present this voucher to your guide on arrival.', $lang)),
]);
$adminEmail = setting('admin_notify_email', setting('contact_email'));
if ($adminEmail) {
    send_booking_email('booking_admin', $booking, $adminEmail, [], default_lang());
}

clear_input();
$_SESSION['_just_booked'] = $booking['reference'];
redirect(voucher_url($booking));
