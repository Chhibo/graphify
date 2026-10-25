<?php
/**
 * "Plan my trip": visitors describe the custom trip they want and receive a
 * personalised proposal. Requests are managed in Admin > Trip Requests.
 */
require __DIR__ . '/includes/bootstrap.php';

$allDestinations = destinations();
$allStyles = categories();
$budgetSteps = array_values(array_filter(array_map('intval', explode(',', setting('budget_options', '1000,2000,3500')))));

/** Budget ranges built from the "Budget filter options" setting, labelled in $lang. */
function budget_ranges(array $steps, ?string $lang = null): array
{
    $out = [];
    foreach ($steps as $i => $amount) {
        $out['b' . $i] = $i === 0 ? sprintf(__('Under %s', $lang), money($amount)) : money($steps[$i - 1]) . ' - ' . money($amount);
    }
    if ($steps) {
        $out['b' . count($steps)] = sprintf(__('Over %s', $lang), money(end($steps)));
    }
    return $out;
}

function accommodation_levels(?string $lang = null): array
{
    return [
        'budget' => __('Budget / guesthouse', $lang), 'comfort' => __('Comfort (3 stars)', $lang),
        'superior' => __('Superior (4 stars)', $lang), 'luxury' => __('Luxury (5 stars)', $lang),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
    $fail = function (string $msg) use ($input) {
        remember_input($input);
        flash('error', $msg);
        redirect('plan-trip.php');
    };
    if (!verify_csrf()) {
        $fail(__('Your session expired. Please try again.'));
    }
    if ($err = spam_check('trip_request', 20)) {
        $fail($err);
    }
    $name = mb_substr(post('name'), 0, 150);
    $email = mb_substr(post('email'), 0, 190);
    $phone = mb_substr(post('phone'), 0, 60);
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fail(__('Please enter your name and a valid email address.'));
    }
    // Store destination and style names in the default language so your team can read them.
    $destNames = array_column(db_all('SELECT id, name FROM ' . tbl('destinations')), 'name', 'id');
    $styleNames = array_column(db_all('SELECT id, name FROM ' . tbl('categories')), 'name', 'id');
    $dests = [];
    foreach ((array) ($_POST['destinations'] ?? []) as $id) {
        if (isset($destNames[(int) $id])) {
            $dests[] = $destNames[(int) $id];
        }
    }
    if (post('other_destination') !== '') {
        $dests[] = mb_substr(post('other_destination'), 0, 150);
    }
    $styles = [];
    foreach ((array) ($_POST['styles'] ?? []) as $id) {
        if (isset($styleNames[(int) $id])) {
            $styles[] = $styleNames[(int) $id];
        }
    }
    $startDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', post('start_date')) ? post('start_date') : null;
    $budgets = budget_ranges($budgetSteps, default_lang());
    $levels = accommodation_levels(default_lang());
    do {
        $ref = 'TR-' . random_int(10000, 99999);
    } while (db_value('SELECT COUNT(*) FROM ' . tbl('trip_requests') . ' WHERE reference = ?', [$ref]));

    $request = [
        'reference' => $ref,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'destinations' => implode(', ', array_unique($dests)),
        'start_date' => $startDate,
        'flexible_dates' => post('flexible_dates') === '1' ? 1 : 0,
        'nights' => max(0, min(365, (int) post('nights'))),
        'adults' => max(1, min(99, (int) post('adults', 2))),
        'children' => max(0, min(99, (int) post('children'))),
        'budget' => $budgets[post('budget')] ?? '',
        'styles' => mb_substr(implode(', ', $styles), 0, 255),
        'accommodation' => $levels[post('accommodation')] ?? '',
        'message' => mb_substr(post('message'), 0, 5000),
        'status' => 'new',
        'lang' => current_lang(),
        'ip_address' => client_ip(),
        'created_at' => now(),
        'updated_at' => now(),
    ];
    $request['id'] = db_insert('trip_requests', $request);

    $lang = $request['lang'];
    send_template_email('request_guest', $email, request_vars($request, $lang), ['{request_details}' => request_details_html($request, $lang)], $lang);
    $adminEmail = setting('admin_notify_email', setting('contact_email'));
    if ($adminEmail) {
        send_template_email('request_admin', $adminEmail, request_vars($request, default_lang()), [
            '{request_details}' => request_details_html($request, default_lang()),
            '{admin_link}' => '<a href="' . e(url('admin/request.php?id=' . $request['id'])) . '">Open in the admin panel</a>',
        ], default_lang());
    }
    clear_input();
    $_SESSION['_trip_request'] = $ref;
    redirect('plan-trip.php?sent=1');
}

$sentRef = !empty($_GET['sent']) ? ($_SESSION['_trip_request'] ?? '') : '';
$preDest = (string) ($_GET['destination'] ?? '');
$oldDests = array_map('intval', (array) old('destinations', []));
$oldStyles = array_map('intval', (array) old('styles', []));

$pageTitle = __('Plan a Custom Trip');
$metaDescription = setting('plan_meta', __('Tell us where you want to go and our travel experts will design a personalised itinerary for you. Free, with no obligation.'));
$activeNav = 'plan';
$noindex = $sentRef !== '';
require APP_ROOT . '/includes/header.php';
page_banner(__('Plan Your Dream Trip'), __('Tell us where you want to go and our travel experts will design a personalised itinerary for you. Free, with no obligation.'), __('Tailor-Made Travel'));
$input = 'w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none';
$lbl = 'block text-xs font-bold text-slate-700 uppercase mb-1';
?>
<section class="py-14 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <?php if ($sentRef): ?>
        <div class="max-w-xl mx-auto bg-white rounded-3xl p-8 shadow-2xl border border-slate-100 text-center">
            <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto text-3xl mb-4"><i class="fa-solid fa-paper-plane"></i></div>
            <h2 class="font-serif text-2xl font-bold text-slate-900"><?= e(__('Thank you! Your request was sent.')) ?></h2>
            <p class="text-slate-600 text-sm mt-2"><?= e(__('Our travel experts are preparing a personalised proposal and will contact you within 24 hours.')) ?></p>
            <p class="mt-4 text-sm"><?= e(__('Your reference')) ?>: <b class="font-mono" dir="ltr"><?= e($sentRef) ?></b></p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center mt-6">
                <a href="<?= e(url('tours.php')) ?>" class="px-6 py-3 bg-slate-100 hover:bg-slate-200 rounded-xl font-bold text-sm"><?= e(__('Browse experiences')) ?></a>
                <?php if (setting('contact_whatsapp')): ?>
                    <a href="<?= e(whatsapp_link(setting('contact_whatsapp'), __('Hello! I just sent the trip request') . ' ' . $sentRef)) ?>" target="_blank" rel="noopener" class="px-6 py-3 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl font-bold text-sm"><i class="fa-brands fa-whatsapp me-1"></i> <?= e(__('Chat on WhatsApp')) ?></a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
        <form method="post" class="lg:col-span-2 bg-white rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-100 space-y-8">
            <?= csrf_field() ?>
            <input type="text" name="website" value="" class="hidden" tabindex="-1" autocomplete="off">

            <fieldset class="space-y-4">
                <legend class="font-serif text-xl font-bold text-slate-900 mb-2"><span class="inline-flex w-7 h-7 rounded-full bg-brand-600 text-white text-sm items-center justify-center me-2">1</span><?= e(__('Where would you like to go?')) ?></legend>
                <?php if ($allDestinations): ?>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($allDestinations as $d): $checked = in_array((int) $d['id'], $oldDests, true) || ($preDest !== '' && strcasecmp($preDest, $d['name']) === 0); ?>
                            <label class="cursor-pointer">
                                <input type="checkbox" name="destinations[]" value="<?= (int) $d['id'] ?>" class="peer sr-only" <?= $checked ? 'checked' : '' ?>>
                                <span class="inline-block px-4 py-2 rounded-full border border-slate-200 text-sm font-semibold text-slate-600 peer-checked:bg-brand-600 peer-checked:text-white peer-checked:border-brand-600 hover:border-brand-500 transition"><?= e($d['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div>
                    <label class="<?= $lbl ?>" for="otherDest"><?= e($allDestinations ? __('Somewhere else?') : __('Destinations')) ?></label>
                    <input id="otherDest" type="text" name="other_destination" maxlength="150" value="<?= e(old('other_destination', $preDest && !$allDestinations ? $preDest : '')) ?>" placeholder="<?= e(__('e.g., Morocco, Iceland, Maldives')) ?>" class="<?= $input ?>">
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="font-serif text-xl font-bold text-slate-900 mb-2"><span class="inline-flex w-7 h-7 rounded-full bg-brand-600 text-white text-sm items-center justify-center me-2">2</span><?= e(__('When and who?')) ?></legend>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="col-span-2">
                        <label class="<?= $lbl ?>" for="startDate"><?= e(__('Departure date')) ?></label>
                        <input id="startDate" type="date" name="start_date" min="<?= date('Y-m-d') ?>" value="<?= e(old('start_date')) ?>" class="<?= $input ?>">
                        <label class="flex items-center gap-2 text-xs text-slate-600 mt-2"><input type="checkbox" name="flexible_dates" value="1" <?= old('flexible_dates') ? 'checked' : '' ?>> <?= e(__('My dates are flexible')) ?></label>
                    </div>
                    <div class="col-span-2">
                        <label class="<?= $lbl ?>" for="nights"><?= e(__('Number of nights')) ?></label>
                        <input id="nights" type="number" name="nights" min="0" max="365" value="<?= e(old('nights', 7)) ?>" class="<?= $input ?>">
                    </div>
                    <div class="col-span-2">
                        <label class="<?= $lbl ?>" for="reqAdults"><?= e(__('Adults')) ?></label>
                        <input id="reqAdults" type="number" name="adults" min="1" max="99" value="<?= e(old('adults', 2)) ?>" class="<?= $input ?>">
                    </div>
                    <div class="col-span-2">
                        <label class="<?= $lbl ?>" for="reqChildren"><?= e(__('Children')) ?></label>
                        <input id="reqChildren" type="number" name="children" min="0" max="99" value="<?= e(old('children', 0)) ?>" class="<?= $input ?>">
                    </div>
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="font-serif text-xl font-bold text-slate-900 mb-2"><span class="inline-flex w-7 h-7 rounded-full bg-brand-600 text-white text-sm items-center justify-center me-2">3</span><?= e(__('Your travel style')) ?></legend>
                <?php if ($allStyles): ?>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($allStyles as $c): ?>
                            <label class="cursor-pointer">
                                <input type="checkbox" name="styles[]" value="<?= (int) $c['id'] ?>" class="peer sr-only" <?= in_array((int) $c['id'], $oldStyles, true) ? 'checked' : '' ?>>
                                <span class="inline-block px-4 py-2 rounded-full border border-slate-200 text-sm font-semibold text-slate-600 peer-checked:bg-accent-500 peer-checked:text-white peer-checked:border-accent-500 hover:border-accent-400 transition"><?= e($c['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="<?= $lbl ?>" for="budget"><?= e(__('Budget per person')) ?></label>
                        <select id="budget" name="budget" class="<?= $input ?>">
                            <option value=""><?= e(__('Not sure yet')) ?></option>
                            <?php foreach (budget_ranges($budgetSteps) as $k => $label): ?>
                                <option value="<?= e($k) ?>" <?= old('budget') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="<?= $lbl ?>" for="accommodation"><?= e(__('Accommodation')) ?></label>
                        <select id="accommodation" name="accommodation" class="<?= $input ?>">
                            <option value=""><?= e(__('No preference')) ?></option>
                            <?php foreach (accommodation_levels() as $k => $label): ?>
                                <option value="<?= e($k) ?>" <?= old('accommodation') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="<?= $lbl ?>" for="reqMessage"><?= e(__('Tell us more')) ?></label>
                    <textarea id="reqMessage" name="message" rows="4" maxlength="5000" placeholder="<?= e(__('Special occasions, must-see places, activities you love, dietary needs...')) ?>" class="<?= $input ?>"><?= e(old('message')) ?></textarea>
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="font-serif text-xl font-bold text-slate-900 mb-2"><span class="inline-flex w-7 h-7 rounded-full bg-brand-600 text-white text-sm items-center justify-center me-2">4</span><?= e(__('Your contact details')) ?></legend>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="<?= $lbl ?>" for="reqName"><?= e(__('Full Name')) ?></label>
                        <input id="reqName" type="text" name="name" required maxlength="150" value="<?= e(old('name')) ?>" class="<?= $input ?>">
                    </div>
                    <div>
                        <label class="<?= $lbl ?>" for="reqEmail"><?= e(__('Email Address')) ?></label>
                        <input id="reqEmail" type="email" name="email" required maxlength="190" value="<?= e(old('email')) ?>" class="<?= $input ?>">
                    </div>
                    <div>
                        <label class="<?= $lbl ?>" for="reqPhone"><?= e(__('Phone Number (WhatsApp)')) ?></label>
                        <input id="reqPhone" type="tel" name="phone" maxlength="60" value="<?= e(old('phone')) ?>" class="<?= $input ?>">
                    </div>
                </div>
            </fieldset>

            <button class="w-full bg-accent-500 hover:bg-accent-600 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-accent-500/30 text-sm"><i class="fa-solid fa-paper-plane me-1"></i> <?= e(__('Send My Trip Request')) ?></button>
            <p class="text-center text-xs text-slate-400"><?= e(__('Free and with no obligation. No payment details needed.')) ?></p>
        </form>

        <aside class="space-y-4">
            <?php foreach ([
                ['fa-user-tie', __('Expert travel planners'), __('Local specialists design every itinerary around you.')],
                ['fa-clock', __('Reply within 24 hours'), __('Receive a personalised proposal by email or WhatsApp.')],
                ['fa-hand-holding-dollar', __('Pay on arrival'), __('No online payment: reserve now, pay when you travel.')],
                ['fa-pen-ruler', __('Fully flexible'), __('Change anything until the itinerary is perfect.')],
            ] as [$icon, $title, $text]): ?>
                <div class="flex items-start gap-4 bg-white rounded-2xl p-5 shadow border border-slate-100">
                    <div class="w-12 h-12 rounded-xl bg-brand-100 text-brand-600 flex items-center justify-center text-xl shrink-0"><i class="fa-solid <?= $icon ?>"></i></div>
                    <div><p class="font-bold text-slate-900"><?= e($title) ?></p><p class="text-sm text-slate-500"><?= e($text) ?></p></div>
                </div>
            <?php endforeach; ?>
        </aside>
    </div>
    <?php endif; ?>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
