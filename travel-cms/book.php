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
    'guests' => (int) post('guests'),
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
    booking_fail('Your session expired. Please submit the form again.', $input, $back);
}
if (setting('bookings_enabled', '1') === '0') {
    booking_fail('Online bookings are temporarily closed. Please contact us directly.', $input, $back);
}
if ($err = spam_check('booking', 10)) {
    booking_fail($err, $input, $back);
}

$tour = db_one('SELECT * FROM ' . tbl('tours') . " WHERE id = ? AND status = 'active'", [$input['tour_id']]);
if (!$tour) {
    booking_fail('Please select a valid tour or activity.', $input, $back);
}

$date = DateTime::createFromFormat('Y-m-d', $input['travel_date']);
$minDate = new DateTime('today +' . max(0, (int) setting('booking_min_days', 1)) . ' days');
$maxDate = new DateTime('today +' . max(1, (int) setting('booking_max_months', 18)) . ' months');
if (!$date || $date->format('Y-m-d') !== $input['travel_date']) {
    booking_fail('Please choose a valid travel date.', $input, $back);
}
if ($date < $minDate) {
    booking_fail('The earliest available travel date is ' . format_date($minDate->format('Y-m-d')) . '.', $input, $back);
}
if ($date > $maxDate) {
    booking_fail('Bookings can only be made up to ' . (int) setting('booking_max_months', 18) . ' months ahead.', $input, $back);
}
$blocked = lines(setting('blocked_dates', ''));
if (in_array($input['travel_date'], $blocked, true)) {
    booking_fail('Sorry, we are fully booked on ' . format_date($input['travel_date']) . '. Please choose another date.', $input, $back);
}
if ($input['guests'] < 1 || $input['guests'] > (int) $tour['max_guests']) {
    booking_fail('This experience accepts between 1 and ' . (int) $tour['max_guests'] . ' guests per booking.', $input, $back);
}
if ($input['name'] === '' || !filter_var($input['email'], FILTER_VALIDATE_EMAIL) || !preg_match('/^[0-9 +().\-]{6,}$/', $input['phone'])) {
    booking_fail('Please enter your name, a valid email address and phone number.', $input, $back);
}

// Optional daily capacity per tour (0 = unlimited).
$capacity = (int) setting('daily_capacity', 0);
if ($capacity > 0) {
    $booked = (int) db_value('SELECT COALESCE(SUM(guests),0) FROM ' . tbl('bookings') . " WHERE tour_id = ? AND travel_date = ? AND status IN ('pending','confirmed')", [$tour['id'], $input['travel_date']]);
    if ($booked + $input['guests'] > $capacity) {
        $left = max(0, $capacity - $booked);
        booking_fail($left ? "Only {$left} spot(s) left on this date." : 'This date is fully booked. Please choose another date.', $input, $back);
    }
}

$autoConfirm = setting('auto_confirm', '0') === '1';
$booking = [
    'reference' => generate_booking_reference(),
    'access_token' => bin2hex(random_bytes(16)),
    'tour_id' => $tour['id'],
    'tour_title' => $tour['title'],
    'travel_date' => $input['travel_date'],
    'guests' => $input['guests'],
    'unit_price' => $tour['price'],
    'total' => round((float) $tour['price'] * $input['guests'], 2),
    'customer_name' => $input['name'],
    'customer_email' => $input['email'],
    'customer_phone' => $input['phone'],
    'notes' => $input['notes'],
    'status' => $autoConfirm ? 'confirmed' : 'pending',
    'ip_address' => client_ip(),
    'created_at' => now(),
    'updated_at' => now(),
];
db_insert('bookings', $booking);

// Notify the guest
$guestBody = '<p>Hi ' . e($booking['customer_name']) . ',</p>'
    . '<p>Thank you for your reservation! ' . ($autoConfirm ? 'Your booking is <b>confirmed</b>.' : 'We have received your booking request and our team will confirm it shortly.') . ' No payment has been charged &mdash; you pay on arrival.</p>'
    . booking_email_table($booking)
    . '<p><a href="' . e(voucher_url($booking)) . '" style="display:inline-block;background:#f97316;color:#fff;padding:10px 18px;border-radius:10px;text-decoration:none;font-weight:bold">View &amp; print your voucher</a></p>'
    . '<p style="color:#64748b;font-size:12px">' . e(setting('voucher_note', 'Please present this voucher to your guide on arrival.')) . '</p>';
send_mail($booking['customer_email'], 'Your reservation ' . $booking['reference'] . ' - ' . setting('site_name', 'WanderLuxe'), $guestBody);

// Notify the admin
$adminEmail = setting('admin_notify_email', setting('contact_email'));
if ($adminEmail) {
    $adminBody = '<p>A new booking request was received.</p>' . booking_email_table($booking)
        . '<p><b>Guest:</b> ' . e($booking['customer_name']) . '<br><b>Email:</b> ' . e($booking['customer_email']) . '<br><b>Phone:</b> ' . e($booking['customer_phone']) . '</p>'
        . ($booking['notes'] ? '<p><b>Notes:</b><br>' . nl2br(e($booking['notes'])) . '</p>' : '')
        . '<p><a href="' . e(url('admin/bookings.php')) . '">Open the admin panel</a></p>';
    send_mail($adminEmail, 'New booking ' . $booking['reference'] . ' - ' . $booking['tour_title'], $adminBody);
}

clear_input();
$_SESSION['_just_booked'] = $booking['reference'];
redirect(voucher_url($booking));
