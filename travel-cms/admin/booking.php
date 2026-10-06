<?php
require __DIR__ . '/includes/auth.php';

$id = (int) ($_GET['id'] ?? 0);
$booking = $id ? db_one('SELECT * FROM ' . tbl('bookings') . ' WHERE id = ?', [$id]) : null;
if ($id && !$booking) {
    flash('error', 'Booking not found.');
    redirect('admin/bookings.php');
}
$isNew = !$booking;
$statuses = booking_statuses();
$tours = db_all('SELECT id, type, title, price, status FROM ' . tbl('tours') . ' ORDER BY type DESC, title');
$self = 'admin/booking.php' . ($id ? '?id=' . $id : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf($self);

    if (post('action') === 'delete' && $booking) {
        db_exec('DELETE FROM ' . tbl('bookings') . ' WHERE id = ?', [$id]);
        flash('success', 'Booking ' . $booking['reference'] . ' deleted.');
        redirect('admin/bookings.php');
    }

    $tour = db_one('SELECT * FROM ' . tbl('tours') . ' WHERE id = ?', [(int) post('tour_id')]);
    $data = [
        'travel_date' => post('travel_date'),
        'guests' => max(1, (int) post('guests', 1)),
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

    $unit = post('unit_price') !== '' ? (float) post('unit_price') : (float) $tour['price'];
    if ($booking && (int) $booking['tour_id'] !== (int) $tour['id'] && post('unit_price') === (string) (float) $booking['unit_price']) {
        $unit = (float) $tour['price']; // experience changed: take the new price
    }
    $data['tour_id'] = $tour['id'];
    $data['tour_title'] = $tour['title'];
    $data['unit_price'] = $unit;
    $data['total'] = round($unit * $data['guests'], 2);

    if ($isNew) {
        $data += [
            'reference' => generate_booking_reference(),
            'access_token' => bin2hex(random_bytes(16)),
            'ip_address' => client_ip(),
            'created_at' => now(),
        ];
        $id = db_insert('bookings', $data);
        flash('success', 'Booking ' . $data['reference'] . ' created.');
    } else {
        db_update('bookings', $data, $id);
        flash('success', 'Booking updated.');
    }

    $fresh = db_one('SELECT * FROM ' . tbl('bookings') . ' WHERE id = ?', [$id]);
    if (post('notify') === '1') {
        $messages = [
            'pending' => 'We have received your booking request and will confirm it shortly.',
            'confirmed' => 'Great news! Your booking is <b>confirmed</b>. We look forward to welcoming you.',
            'completed' => 'Thank you for travelling with us! We hope you had an amazing time. We would love a review on our website.',
            'cancelled' => 'Your booking has been <b>cancelled</b>. If this is unexpected, please contact us.',
        ];
        $body = '<p>Hi ' . e($fresh['customer_name']) . ',</p><p>' . $messages[$fresh['status']] . '</p>'
            . booking_email_table($fresh)
            . (post('message') !== '' ? '<p>' . nl2br(e(post('message'))) . '</p>' : '')
            . '<p><a href="' . e(voucher_url($fresh)) . '" style="display:inline-block;background:#f97316;color:#fff;padding:10px 18px;border-radius:10px;text-decoration:none;font-weight:bold">View your voucher</a></p>';
        $sent = send_mail($fresh['customer_email'], 'Booking ' . $fresh['reference'] . ' – ' . ucfirst($fresh['status']), $body);
        flash($sent ? 'success' : 'error', $sent ? 'Email sent to the guest.' : 'Email could not be sent (check mail settings / server mail function).');
    }
    redirect('admin/booking.php?id=' . $id);
}

$b = $booking ?: [
    'tour_id' => (int) ($_GET['tour'] ?? 0), 'travel_date' => date('Y-m-d', strtotime('+1 day')), 'guests' => 2, 'unit_price' => '',
    'customer_name' => '', 'customer_email' => '', 'customer_phone' => '', 'notes' => '', 'status' => 'confirmed', 'admin_notes' => '',
];

admin_header($isNew ? 'New booking' : 'Booking ' . $booking['reference'], 'bookings');
?>
<a href="<?= e(url('admin/bookings.php')) ?>" class="text-xs font-bold text-slate-500 hover:text-brand-600"><i class="fa-solid fa-arrow-left mr-1"></i> All bookings</a>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-3">
    <form method="post" class="card p-6 xl:col-span-2 space-y-6">
        <?= csrf_field() ?>
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-lg"><?= $isNew ? 'Create a booking (phone / walk-in)' : 'Booking details' ?></h2>
            <?php if (!$isNew): ?><?= status_badge($booking['status']) ?><?php endif; ?>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="lbl">Experience</label>
                <select class="inp" name="tour_id" required>
                    <option value="">— Choose —</option>
                    <?php foreach ($tours as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= (int) $b['tour_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e(($t['type'] === 'trip' ? 'Trip: ' : 'Activity: ') . $t['title'] . ' (' . money($t['price']) . ')' . ($t['status'] !== 'active' ? ' [hidden]' : '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php f_input('Travel date', 'travel_date', $b['travel_date'], 'date', 'required'); ?>
            <?php f_input('Guests', 'guests', $b['guests'], 'number', 'min="1" required'); ?>
            <?php f_input('Price per guest', 'unit_price', $b['unit_price'] === '' ? '' : (string) (float) $b['unit_price'], 'number', 'step="0.01" min="0"', 'Leave empty to use the experience price.'); ?>
            <?php f_select('Status', 'status', array_map(fn ($s) => $s[0], $statuses), $b['status']); ?>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <?php f_input('Guest name', 'customer_name', $b['customer_name'], 'text', 'required'); ?>
            <?php f_input('Email', 'customer_email', $b['customer_email'], 'email', 'required'); ?>
            <?php f_input('Phone / WhatsApp', 'customer_phone', $b['customer_phone']); ?>
        </div>
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
            <div class="flex justify-between"><span class="text-slate-500">Reference</span><b class="font-mono"><?= e($booking['reference']) ?></b></div>
            <div class="flex justify-between"><span class="text-slate-500">Guests × price</span><span><?= (int) $booking['guests'] ?> × <?= e(money($booking['unit_price'])) ?></span></div>
            <div class="flex justify-between text-base"><span class="text-slate-500">Due on arrival</span><b class="text-emerald-600"><?= e(money($booking['total'])) ?></b></div>
            <div class="flex justify-between"><span class="text-slate-500">Booked</span><span><?= e(format_date($booking['created_at'], 'M j, Y H:i')) ?></span></div>
            <div class="flex justify-between"><span class="text-slate-500">Last update</span><span><?= e(time_ago($booking['updated_at'])) ?></span></div>
            <?php if ($booking['ip_address']): ?><div class="flex justify-between"><span class="text-slate-500">IP</span><span class="text-xs"><?= e($booking['ip_address']) ?></span></div><?php endif; ?>
            <a href="<?= e(voucher_url($booking)) ?>" target="_blank" class="btn btn-light w-full justify-center"><i class="fa-solid fa-ticket"></i> Open guest voucher</a>
        </div>
        <div class="card p-6 space-y-2 text-sm">
            <h2 class="font-bold mb-1">Contact guest</h2>
            <a href="mailto:<?= e($booking['customer_email']) ?>?subject=<?= rawurlencode('Your booking ' . $booking['reference']) ?>" class="btn btn-light w-full justify-center"><i class="fa-solid fa-envelope"></i> Email</a>
            <?php if ($booking['customer_phone']): ?>
                <a href="https://wa.me/<?= e(preg_replace('/\D/', '', $booking['customer_phone'])) ?>?text=<?= rawurlencode('Hello ' . $booking['customer_name'] . ', regarding your booking ' . $booking['reference'] . ' (' . $booking['tour_title'] . ' on ' . format_date($booking['travel_date']) . ')') ?>" target="_blank" rel="noopener" class="btn w-full justify-center bg-emerald-500 text-white hover:bg-emerald-600"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
                <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $booking['customer_phone'])) ?>" class="btn btn-light w-full justify-center"><i class="fa-solid fa-phone"></i> Call</a>
            <?php endif; ?>
        </div>
        <form method="post" class="card p-6" data-confirm="Delete this booking permanently?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <button class="btn btn-danger w-full justify-center"><i class="fa-solid fa-trash"></i> Delete booking</button>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php admin_footer(); ?>
