<?php
require __DIR__ . '/includes/auth.php';
require_permission('settings');

$tab = ($_GET['tab'] ?? 'email') === 'wa' ? 'wa' : 'email';
$lang = isset(languages()[$_GET['lang'] ?? '']) ? $_GET['lang'] : default_lang();
$self = 'admin/templates.php?tab=' . $tab . '&lang=' . $lang;
$emails = email_templates();
$was = whatsapp_templates();

/** Sample values used for previews and test emails. */
function sample_booking(): array
{
    $b = db_one('SELECT * FROM ' . tbl('bookings') . ' ORDER BY id DESC LIMIT 1');
    return $b ?: [
        'id' => 0, 'reference' => 'WL-123456', 'access_token' => 'sample', 'tour_id' => null, 'tour_title' => 'Bali Luxury Ocean & Temple Tour',
        'travel_date' => date('Y-m-d', strtotime('+14 days')), 'guests' => 3, 'adults' => 2, 'children' => 1, 'infants' => 0,
        'unit_price' => 1899, 'child_price' => 1329, 'infant_price' => 0, 'total' => 5127, 'price_on_request' => 0, 'lang' => '',
        'customer_name' => 'Alex Johnson', 'customer_email' => 'alex@example.com', 'customer_phone' => '+1 555 0199', 'notes' => 'Airport pickup please',
        'status' => 'confirmed',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf($self);
    if (post('action') === 'test') {
        $key = post('key');
        $b = sample_booking();
        $b['lang'] = $lang;
        $group = $emails[$key][4] ?? 'booking';
        if ($group === 'booking') {
            $ok = send_booking_email($key, $b, $admin['email'], ['{status_message}' => __('Your booking is confirmed.', $lang), '{voucher_note}' => setting('voucher_note'), '{message}' => '(Your optional message appears here.)'], $lang);
        } else {
            $vars = ['{name}' => 'Alex Johnson', '{reference}' => 'TR-12345', '{guest_email}' => 'alex@example.com', '{guest_phone}' => '+1 555 0199', '{subject}' => 'Question about Bali',
                '{message}' => 'Sample message text.', '{site_name}' => setting('site_name'), '{site_phone}' => setting('contact_phone'), '{site_email}' => setting('contact_email')];
            $html = ['{request_details}' => email_table(['Destinations' => 'Bali, Kyoto', 'Travellers' => '2 adults']), '{admin_link}' => '<a href="#">Open in the admin panel</a>',
                '{unsubscribe_link}' => '<a href="#">' . e(__('Unsubscribe', $lang)) . '</a>'];
            $ok = send_template_email($key, $admin['email'], $vars, $html, $lang);
        }
        flash($ok ? 'success' : 'error', $ok ? 'Test email sent to ' . $admin['email'] . '.' : 'Sending failed. Check Settings > Email.');
        redirect($self . '#tpl-' . $key);
    }
    if (post('action') === 'reset') {
        $key = post('key');
        foreach ($tab === 'email' ? ['subject', 'body'] : [''] as $field) {
            save_template_text($tab, $key, $field, '', $lang);
        }
        flash('success', 'Template reset to the default text.');
        redirect($self . '#tpl-' . $key);
    }
    $saved = 0;
    foreach ($tab === 'email' ? $emails : $was as $key => $def) {
        foreach ($tab === 'email' ? ['subject', 'body'] : [''] as $field) {
            $name = $key . ($field ? '_' . $field : '');
            if (!isset($_POST['tpl'][$name])) {
                continue;
            }
            $value = str_replace("\r\n", "\n", (string) $_POST['tpl'][$name]);
            $default = template_default($tab, $key, $field, $lang);
            // Store nothing when the text equals the default, so future improvements of the default still apply.
            save_template_text($tab, $key, $field, trim($value) === trim($default) ? '' : $value, $lang);
            $saved++;
        }
    }
    flash('success', 'Templates saved.');
    redirect($self);
}

admin_header('Message templates', 'templates');
$langName = languages()[$lang]['name'];
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div class="flex gap-2 text-xs font-bold">
        <a href="?tab=email&lang=<?= e($lang) ?>" class="px-4 py-2.5 rounded-xl <?= $tab === 'email' ? 'bg-brand-600 text-white' : 'bg-white text-slate-600' ?>"><i class="fa-solid fa-envelope mr-1"></i> Emails</a>
        <a href="?tab=wa&lang=<?= e($lang) ?>" class="px-4 py-2.5 rounded-xl <?= $tab === 'wa' ? 'bg-brand-600 text-white' : 'bg-white text-slate-600' ?>"><i class="fa-brands fa-whatsapp mr-1"></i> WhatsApp</a>
    </div>
    <?php if (count(languages()) > 1): ?>
        <div class="flex items-center gap-2 text-xs font-bold">
            <span class="text-slate-500">Language:</span>
            <?php foreach (languages() as $l): ?>
                <a href="?tab=<?= $tab ?>&lang=<?= e($l['code']) ?>" class="px-3 py-2 rounded-xl <?= $l['code'] === $lang ? 'bg-slate-900 text-white' : 'bg-white text-slate-600' ?>"><?= e($l['name']) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card p-5 mb-6 text-sm text-slate-600">
    <?php if ($tab === 'email'): ?>
        Edit the emails sent by the website. Write plain text; a blank line starts a new paragraph. Words in <code class="text-brand-700">{curly brackets}</code> are replaced automatically.
        Guests receive emails in the language they used on the website<?= count(languages()) > 1 ? ' — you are editing the <b>' . e($langName) . '</b> version' : '' ?>.
    <?php else: ?>
        Ready-made WhatsApp messages. On each booking (and trip request) page, click a template to open WhatsApp with the message filled in for that guest. You can still edit it before sending.
        <?= count(languages()) > 1 ? 'You are editing the <b>' . e($langName) . '</b> version, used for guests who booked in that language.' : '' ?>
    <?php endif; ?>
</div>

<form method="post" class="space-y-4">
    <?= csrf_field() ?>
    <?php if ($tab === 'email'): foreach ($emails as $key => [$name, $desc, , , $group, $blocks]):
        $ph = $group === 'request' ? request_placeholders() : ($group === 'booking' ? booking_placeholders() : ['{site_name}' => 'Website name']);
        if ($key === 'contact_admin') { $ph = ['{name}' => 'Sender', '{guest_email}' => 'Sender email', '{guest_phone}' => 'Sender phone', '{subject}' => 'Subject', '{message}' => 'Message']; }
        if (in_array($key, ['booking_admin', 'request_admin'], true)) { $ph += ['{guest_email}' => 'Guest email', '{guest_phone}' => 'Guest phone', '{notes}' => 'Guest notes']; }
        if ($key === 'booking_guest') { $ph += ['{status_message}' => '"Your booking is confirmed" or "…will confirm it shortly"', '{voucher_note}' => 'Voucher note (Settings)']; } ?>
        <div class="card p-6 space-y-3 scroll-mt-20" id="tpl-<?= e($key) ?>">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div><h2 class="font-bold"><?= e($name) ?></h2><p class="help"><?= e($desc) ?></p></div>
                <div class="flex gap-2">
                    <button form="test-<?= e($key) ?>" class="btn btn-light !py-1.5"><i class="fa-solid fa-paper-plane"></i> Send test</button>
                    <button form="reset-<?= e($key) ?>" class="btn btn-light !py-1.5" onclick="return confirm('Reset this template to the default text?')"><i class="fa-solid fa-rotate-left"></i> Default</button>
                </div>
            </div>
            <div><label class="lbl">Subject</label><input class="inp" dir="<?= lang_dir($lang) ?>" name="tpl[<?= e($key) ?>_subject]" value="<?= e(template_text('email', $key, 'subject', $lang)) ?>"></div>
            <div><label class="lbl">Message</label><textarea class="inp font-mono text-xs" dir="<?= lang_dir($lang) ?>" rows="9" name="tpl[<?= e($key) ?>_body]"><?= e(template_text('email', $key, 'body', $lang)) ?></textarea></div>
            <div class="flex flex-wrap gap-1.5 text-[11px]">
                <?php foreach ($ph as $code => $label): ?><span class="px-2 py-1 rounded-lg bg-slate-100 text-slate-600" title="<?= e($label) ?>"><code class="text-brand-700"><?= e($code) ?></code> <?= e($label) ?></span><?php endforeach; ?>
                <?php foreach ($blocks as $code): ?><span class="px-2 py-1 rounded-lg bg-brand-50 text-brand-700" title="Inserted as a block"><code><?= e($code) ?></code> <?= e(['{booking_details}' => 'Booking summary table', '{voucher_button}' => 'Voucher button', '{review_button}' => 'Review button', '{request_details}' => 'Request summary', '{admin_link}' => 'Admin link', '{unsubscribe_link}' => 'Unsubscribe link'][$code] ?? '') ?></span><?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; else: foreach ($was as $key => [$name, , $group]): $ph = $group === 'request' ? request_placeholders() : booking_placeholders(); ?>
        <div class="card p-6 space-y-3 scroll-mt-20" id="tpl-<?= e($key) ?>">
            <div class="flex items-center justify-between gap-2">
                <h2 class="font-bold"><i class="fa-brands fa-whatsapp text-emerald-500"></i> <?= e($name) ?> <span class="text-xs text-slate-400 font-normal">(<?= $group === 'request' ? 'trip requests' : 'bookings' ?>)</span></h2>
                <button form="reset-<?= e($key) ?>" class="btn btn-light !py-1.5" onclick="return confirm('Reset this template to the default text?')"><i class="fa-solid fa-rotate-left"></i> Default</button>
            </div>
            <textarea class="inp text-sm" dir="<?= lang_dir($lang) ?>" rows="5" name="tpl[<?= e($key) ?>]"><?= e(template_text('wa', $key, '', $lang)) ?></textarea>
            <div class="flex flex-wrap gap-1.5 text-[11px]">
                <?php foreach ($ph as $code => $label): ?><span class="px-2 py-1 rounded-lg bg-slate-100 text-slate-600"><code class="text-brand-700"><?= e($code) ?></code> <?= e($label) ?></span><?php endforeach; ?>
                <span class="px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700">*bold* · _italic_ · emojis work in WhatsApp</span>
            </div>
        </div>
    <?php endforeach; endif; ?>
    <div class="sticky bottom-4 flex justify-end"><button class="btn btn-primary shadow-lg"><i class="fa-solid fa-floppy-disk"></i> Save all templates</button></div>
</form>
<?php foreach ($tab === 'email' ? array_keys($emails) : array_keys($was) as $key): ?>
    <form method="post" id="reset-<?= e($key) ?>" class="hidden"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="key" value="<?= e($key) ?>"></form>
    <form method="post" id="test-<?= e($key) ?>" class="hidden"><?= csrf_field() ?><input type="hidden" name="action" value="test"><input type="hidden" name="key" value="<?= e($key) ?>"></form>
<?php endforeach; ?>
<?php admin_footer(); ?>
