<?php
require __DIR__ . '/includes/auth.php';
require_permission('bookings');

$id = (int) ($_GET['id'] ?? 0);
$r = db_one('SELECT * FROM ' . tbl('trip_requests') . ' WHERE id = ?', [$id]);
if (!$r) {
    flash('error', 'Trip request not found.');
    redirect('admin/requests.php');
}
$statuses = trip_request_statuses();
$self = 'admin/request.php?id=' . $id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf($self);
    if (post('action') === 'delete' && can('bookings_delete')) {
        db_exec('DELETE FROM ' . tbl('trip_requests') . ' WHERE id = ?', [$id]);
        flash('success', 'Request ' . $r['reference'] . ' deleted.');
        redirect('admin/requests.php');
    }
    db_update('trip_requests', [
        'status' => isset($statuses[post('status')]) ? post('status') : $r['status'],
        'admin_notes' => post('admin_notes'),
        'updated_at' => now(),
    ], $id);
    flash('success', 'Request updated.');
    redirect($self);
}

// Opening a new request marks it as seen.
if ($r['status'] === 'new') {
    db_update('trip_requests', ['status' => 'contacted', 'updated_at' => now()], $id);
    $r['status'] = 'contacted';
}
$lang = $r['lang'] ?: default_lang();
$vars = request_vars($r, $lang);
$bookings = db_all('SELECT id, reference, tour_title, travel_date, status FROM ' . tbl('bookings') . ' WHERE request_id = ? ORDER BY id', [$id]);

admin_header('Trip request ' . $r['reference'], 'requests');
?>
<a href="<?= e(url('admin/requests.php')) ?>" class="text-xs font-bold text-slate-500 hover:text-brand-600"><i class="fa-solid fa-arrow-left mr-1"></i> All trip requests</a>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-3">
    <div class="xl:col-span-2 space-y-6">
        <div class="card p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div>
                    <h2 class="font-bold text-lg"><?= e($r['name']) ?></h2>
                    <p class="text-sm text-slate-500"><a class="text-brand-600" href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a><?= $r['phone'] ? ' · ' . e($r['phone']) : '' ?> · <?= e(languages()[$lang]['name'] ?? $lang) ?></p>
                </div>
                <?= request_status_badge($r['status']) ?>
            </div>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <?php foreach ([
                    'Destinations' => $r['destinations'] ?: 'Not decided',
                    'Departure' => $r['start_date'] ? format_date($r['start_date']) . ($r['flexible_dates'] ? ' (flexible)' : '') : 'Flexible',
                    'Nights' => $r['nights'] ?: '—',
                    'Travellers' => $r['adults'] . ' adults' . ($r['children'] ? ', ' . $r['children'] . ' children' : ''),
                    'Budget per person' => $r['budget'] ?: '—',
                    'Accommodation' => $r['accommodation'] ?: '—',
                    'Travel style' => $r['styles'] ?: '—',
                    'Received' => format_date($r['created_at'], 'M j, Y H:i'),
                ] as $label => $value): ?>
                    <div class="bg-slate-50 rounded-xl p-3"><dt class="text-xs font-bold uppercase text-slate-400"><?= e($label) ?></dt><dd class="font-semibold mt-0.5"><?= e($value) ?></dd></div>
                <?php endforeach; ?>
            </dl>
            <?php if ($r['message']): ?>
                <div class="mt-4 bg-amber-50 rounded-xl p-4 text-sm"><p class="text-xs font-bold uppercase text-amber-700 mb-1">Message</p><?= nl2br(e($r['message'])) ?></div>
            <?php endif; ?>
        </div>

        <form method="post" class="card p-6 space-y-4">
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <?php f_select('Status', 'status', array_map(fn ($s) => $s[0], $statuses), $r['status']); ?>
                <div class="sm:col-span-2"><?php f_textarea('Internal notes', 'admin_notes', $r['admin_notes'], 2); ?></div>
            </div>
            <div class="flex justify-end"><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save</button></div>
        </form>

        <?php if ($bookings): ?>
            <div class="card p-6">
                <h2 class="font-bold mb-3">Bookings created from this request</h2>
                <?php foreach ($bookings as $b): ?>
                    <a href="<?= e(url('admin/booking.php?id=' . $b['id'])) ?>" class="flex justify-between items-center py-2 border-b border-slate-100 text-sm hover:text-brand-600"><span><b class="font-mono"><?= e($b['reference']) ?></b> · <?= e($b['tour_title']) ?> · <?= e(format_date($b['travel_date'])) ?></span><?= status_badge($b['status']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="space-y-6">
        <div class="card p-6 space-y-2 text-sm">
            <h2 class="font-bold mb-1">Reply to the guest</h2>
            <?php if ($r['phone']): ?>
                <?php $wa = whatsapp_text('request_reply', $vars, $lang); ?>
                <textarea id="waReply" class="inp text-xs" rows="5"><?= e($wa) ?></textarea>
                <div class="flex gap-2">
                    <a href="<?= e(whatsapp_link($r['phone'], $wa)) ?>" target="_blank" rel="noopener" class="btn flex-1 justify-center bg-emerald-500 text-white hover:bg-emerald-600"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
                    <button type="button" class="btn btn-light" data-copy="waReply"><i class="fa-solid fa-copy"></i></button>
                </div>
            <?php endif; ?>
            <a href="mailto:<?= e($r['email']) ?>?subject=<?= rawurlencode('Your trip proposal ' . $r['reference']) ?>" class="btn btn-light w-full justify-center"><i class="fa-solid fa-envelope"></i> Email</a>
            <?php if ($r['phone']): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $r['phone'])) ?>" class="btn btn-light w-full justify-center"><i class="fa-solid fa-phone"></i> Call</a><?php endif; ?>
            <p class="help">The guest wrote in <?= e(languages()[$lang]['name'] ?? $lang) ?>. Edit the WhatsApp text in <a class="underline" href="<?= e(url('admin/templates.php?tab=wa')) ?>">Message Templates</a>.</p>
        </div>
        <div class="card p-6 space-y-2">
            <h2 class="font-bold">Convert to a booking</h2>
            <p class="help">Opens a new booking filled with the guest's details and travellers. Choose the trip and prices there.</p>
            <a href="<?= e(url('admin/booking.php?request=' . $id)) ?>" class="btn btn-accent w-full justify-center"><i class="fa-solid fa-calendar-plus"></i> Create booking</a>
        </div>
        <?php if (can('bookings_delete')): ?>
            <form method="post" class="card p-6" data-confirm="Delete this trip request permanently?">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete">
                <button class="btn btn-danger w-full justify-center"><i class="fa-solid fa-trash"></i> Delete request</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php admin_footer(); ?>
