<?php
require __DIR__ . '/includes/auth.php';
require_permission('content');

$id = (int) ($_GET['id'] ?? 0);
$tour = $id ? db_one('SELECT * FROM ' . tbl('tours') . ' WHERE id = ?', [$id]) : null;
if ($id && !$tour) {
    flash('error', 'Experience not found.');
    redirect('admin/tours.php');
}
$type = $tour['type'] ?? (($_GET['type'] ?? 'trip') === 'activity' ? 'activity' : 'trip');
$t = $tour ?: [
    'type' => $type, 'title' => '', 'slug' => '', 'category_id' => null, 'destination_id' => null, 'destination' => '',
    'price' => '', 'child_price' => null, 'infant_price' => null, 'allow_children' => 1, 'allow_infants' => 1, 'hide_price' => 0, 'duration' => '',
    'max_guests' => $type === 'trip' ? 12 : 10, 'icon' => $type === 'trip' ? 'fa-plane' : 'fa-compass', 'image' => '',
    'short_description' => '', 'description' => '', 'inclusions' => '', 'itinerary' => '', 'rating' => '5.0', 'reviews_count' => 0,
    'is_featured' => 1, 'status' => 'active', 'sort_order' => 0, 'seo_title' => '', 'meta_description' => '',
];
$self = 'admin/tour-edit.php?' . ($id ? 'id=' . $id : 'type=' . $type);

/** Optional price field: empty stays NULL (child = adult price, infant = free). */
function optional_price(string $key): ?float
{
    return post($key) === '' ? null : max(0, (float) post($key));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf($self);
    $data = [
        'type' => post('type') === 'activity' ? 'activity' : 'trip',
        'title' => mb_substr(post('title'), 0, 200),
        'category_id' => (int) post('category_id') ?: null,
        'destination_id' => (int) post('destination_id') ?: null,
        'destination' => mb_substr(post('destination'), 0, 150),
        'price' => max(0, (float) post('price')),
        'child_price' => optional_price('child_price'),
        'infant_price' => optional_price('infant_price'),
        'allow_children' => post('allow_children') === '1' ? 1 : 0,
        'allow_infants' => post('allow_infants') === '1' ? 1 : 0,
        'hide_price' => post('hide_price') === '1' ? 1 : 0,
        'duration' => mb_substr(post('duration'), 0, 80),
        'max_guests' => max(1, (int) post('max_guests', 12)),
        'icon' => preg_replace('/[^a-z0-9\- ]/', '', strtolower(post('icon'))) ?: 'fa-compass',
        'short_description' => mb_substr(post('short_description'), 0, 500),
        'description' => post('description'),
        'inclusions' => post('inclusions'),
        'itinerary' => post('itinerary'),
        'rating' => max(0, min(5, round((float) post('rating', 5), 1))),
        'reviews_count' => max(0, (int) post('reviews_count')),
        'is_featured' => post('is_featured') === '1' ? 1 : 0,
        'status' => post('status') === 'hidden' ? 'hidden' : 'active',
        'sort_order' => (int) post('sort_order'),
        'seo_title' => mb_substr(post('seo_title'), 0, 200),
        'meta_description' => mb_substr(post('meta_description'), 0, 320),
        'updated_at' => now(),
    ];
    if ($data['title'] === '') {
        flash('error', 'Title is required.');
        redirect($self);
    }
    if (!$data['hide_price'] && post('price') === '') {
        flash('error', 'Enter a price, or tick "Hide price" to show "' . setting('price_hidden_text', 'Price on request') . '" instead.');
        redirect($self);
    }
    // Fill the location label from the chosen destination when left empty.
    if ($data['destination'] === '' && $data['destination_id']) {
        $d = db_one('SELECT name, country FROM ' . tbl('destinations') . ' WHERE id = ?', [$data['destination_id']]);
        $data['destination'] = $d ? $d['name'] . ($d['country'] && $d['country'] !== $d['name'] ? ', ' . $d['country'] : '') : '';
    }
    $data['slug'] = unique_slug('tours', post('slug') !== '' ? post('slug') : $data['title'], $id);
    try {
        $data['image'] = resolve_image('image', $tour['image'] ?? '');
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
        redirect($self);
    }
    if ($tour) {
        db_update('tours', $data, $id);
    } else {
        $data['created_at'] = now();
        $id = db_insert('tours', $data);
    }

    // Gallery: captions / order / removals for existing photos, then new uploads and URLs.
    $existing = db_all('SELECT * FROM ' . tbl('tour_images') . ' WHERE tour_id = ?', [$id]);
    $remove = array_map('intval', (array) ($_POST['gallery_delete'] ?? []));
    foreach ($existing as $img) {
        if (in_array((int) $img['id'], $remove, true)) {
            db_exec('DELETE FROM ' . tbl('tour_images') . ' WHERE id = ?', [$img['id']]);
            delete_upload($img['image']);
            continue;
        }
        db_update('tour_images', [
            'caption' => mb_substr((string) ($_POST['gallery_caption'][$img['id']] ?? $img['caption']), 0, 200),
            'sort_order' => (int) ($_POST['gallery_sort'][$img['id']] ?? $img['sort_order']),
        ], (int) $img['id']);
    }
    $next = (int) db_value('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM ' . tbl('tour_images') . ' WHERE tour_id = ?', [$id]);
    $added = 0;
    try {
        foreach (upload_images('gallery_files') as $file) {
            db_insert('tour_images', ['tour_id' => $id, 'image' => $file, 'caption' => '', 'sort_order' => $next++]);
            $added++;
        }
    } catch (RuntimeException $ex) {
        flash('error', 'Some gallery photos were not uploaded: ' . $ex->getMessage());
    }
    foreach (lines(post('gallery_urls')) as $u) {
        if (preg_match('#^https?://#i', $u)) {
            db_insert('tour_images', ['tour_id' => $id, 'image' => mb_substr($u, 0, 500), 'caption' => '', 'sort_order' => $next++]);
            $added++;
        }
    }

    // FAQs of this trip: edit / remove existing ones, add new ones.
    $faqRows = is_array($_POST['faq'] ?? null) ? $_POST['faq'] : [];
    $order = 0;
    foreach ($faqRows as $row) {
        $fid = (int) ($row['id'] ?? 0);
        $q = trim((string) ($row['question'] ?? ''));
        $a = trim((string) ($row['answer'] ?? ''));
        $remove = !empty($row['delete']) || ($q === '' && $a === '');
        if ($fid) {
            if ($remove) {
                db_exec('DELETE FROM ' . tbl('faqs') . ' WHERE id = ? AND tour_id = ?', [$fid, $id]);
                delete_translations('faq', $fid);
            } else {
                db_exec('UPDATE ' . tbl('faqs') . ' SET question = ?, answer = ?, sort_order = ? WHERE id = ? AND tour_id = ?', [mb_substr($q, 0, 500), $a, $order++, $fid, $id]);
            }
        } elseif (!$remove && $q !== '') {
            db_insert('faqs', ['tour_id' => $id, 'category' => '', 'question' => mb_substr($q, 0, 500), 'answer' => $a, 'sort_order' => $order++, 'status' => 'active', 'created_at' => now()]);
        }
    }
    save_translations_from_post('tour', $id);

    flash('success', 'Saved successfully.' . ($added ? " $added photo(s) added to the gallery." : ''));
    redirect('admin/tour-edit.php?id=' . $id);
}

$catOptions = ['' => '— None —'];
foreach (categories() as $c) {
    $catOptions[$c['id']] = $c['name'];
}
$destOptions = ['' => '— None —'];
foreach (db_all('SELECT id, name, country FROM ' . tbl('destinations') . ' ORDER BY name') as $d) {
    $destOptions[$d['id']] = $d['name'] . ($d['country'] && $d['country'] !== $d['name'] ? ' (' . $d['country'] . ')' : '');
}
$tourFaqs = $tour ? db_all('SELECT * FROM ' . tbl('faqs') . ' WHERE tour_id = ? ORDER BY sort_order, id', [$id]) : [];
$gallery = $tour ? db_all('SELECT * FROM ' . tbl('tour_images') . ' WHERE tour_id = ? ORDER BY sort_order, id', [$id]) : [];
$cur = setting('currency_symbol', '$');
$priceOrEmpty = fn ($v) => $v === null ? '' : (string) (float) $v;

admin_header($tour ? 'Edit: ' . $tour['title'] : 'New ' . ($type === 'trip' ? 'trip' : 'activity'), $type === 'trip' ? 'trips' : 'activities');
?>
<a href="<?= e(url('admin/tours.php?type=' . $type)) ?>" class="text-xs font-bold text-slate-500 hover:text-brand-600"><i class="fa-solid fa-arrow-left mr-1"></i> Back to list</a>
<form method="post" enctype="multipart/form-data" class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-3">
    <?= csrf_field() ?>
    <div class="xl:col-span-2 space-y-6">
        <div class="card p-6 space-y-4">
            <?php f_input('Title', 'title', $t['title'], 'text', 'required maxlength="200"'); ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?php f_select('Destination', 'destination_id', $destOptions, $t['destination_id'], 'Links the trip to a destination page. Manage them under Destinations.'); ?>
                <?php f_input('Location label', 'destination', $t['destination'], 'text', 'placeholder="e.g., Ubud, Bali"', 'Shown on cards. Leave empty to use the destination name.'); ?>
                <?php f_input('Duration', 'duration', $t['duration'], 'text', 'placeholder="' . ($type === 'trip' ? '7 Days / 6 Nights' : '5 Hours') . '"'); ?>
                <?php f_input('Max guests per booking', 'max_guests', $t['max_guests'], 'number', 'min="1"', 'Adults + children + infants.'); ?>
            </div>
            <?php f_textarea('Short description (shown on cards)', 'short_description', $t['short_description'], 2, 'Max 500 characters.', 'maxlength="500"'); ?>
            <?php f_textarea('Full description', 'description', $t['description'], 8, 'Plain text (blank line = new paragraph) or basic HTML.'); ?>
        </div>

        <div class="card p-6 space-y-4" id="pricing">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-bold">Prices</h2>
                <?php if (setting('hide_all_prices', '0') === '1'): ?>
                    <span class="text-xs font-bold text-amber-700 bg-amber-50 px-3 py-1 rounded-full"><i class="fa-solid fa-eye-slash mr-1"></i> All prices are hidden site-wide (Settings &rarr; Booking)</span>
                <?php endif; ?>
            </div>
            <?php f_check('Hide price (price on request)', 'hide_price', (bool) $t['hide_price'], 'Guests see "' . setting('price_hidden_text', 'Price on request') . '" and can still book. You confirm the price with them afterwards. The prices below are then optional.'); ?>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4" data-price-fields>
                <?php f_input('Adult price (' . $cur . ')', 'price', $t['price'] === '' ? '' : (string) (float) $t['price'], 'number', 'step="0.01" min="0"' . ($t['hide_price'] ? '' : ' required')); ?>
                <?php f_input('Child price (' . $cur . ')', 'child_price', $priceOrEmpty($t['child_price']), 'number', 'step="0.01" min="0" placeholder="Same as adult"', setting('child_age_label', 'Ages 2-11') . '. Empty = adult price.'); ?>
                <?php f_input('Infant price (' . $cur . ')', 'infant_price', $priceOrEmpty($t['infant_price']), 'number', 'step="0.01" min="0" placeholder="Free"', setting('infant_age_label', 'Under 2') . '. Empty = free.'); ?>
            </div>
            <div class="flex flex-wrap gap-6">
                <?php f_check('Children allowed', 'allow_children', (bool) $t['allow_children']); ?>
                <?php f_check('Infants allowed', 'allow_infants', (bool) $t['allow_infants']); ?>
            </div>
        </div>

        <div class="card p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php f_textarea("What's included (one per line)", 'inclusions', $t['inclusions'], 7, 'e.g. Hotel pickup'); ?>
            <?php f_textarea('Itinerary (one step per line)', 'itinerary', $t['itinerary'], 7, 'Format: Day 1 - Arrival | Description of the day'); ?>
        </div>

        <div class="card p-6 space-y-4" id="gallery">
            <div class="flex items-center justify-between">
                <h2 class="font-bold">Photo gallery <span class="text-slate-400 font-normal text-sm">(<?= count($gallery) ?> photos + main image)</span></h2>
            </div>
            <?php if ($gallery): ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <?php foreach ($gallery as $g): ?>
                        <div class="border border-slate-200 rounded-xl overflow-hidden bg-slate-50">
                            <img src="<?= e(img_url($g['image'])) ?>" alt="" class="w-full h-28 object-cover">
                            <div class="p-2 space-y-1.5">
                                <input class="inp !py-1 !text-xs" name="gallery_caption[<?= (int) $g['id'] ?>]" value="<?= e($g['caption']) ?>" placeholder="Caption">
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] text-slate-400 uppercase font-bold">Order</span>
                                    <input class="inp !py-1 !text-xs !w-14" type="number" name="gallery_sort[<?= (int) $g['id'] ?>]" value="<?= (int) $g['sort_order'] ?>">
                                </div>
                                <label class="text-xs text-rose-600 font-semibold flex items-center gap-1 cursor-pointer"><input type="checkbox" name="gallery_delete[]" value="<?= (int) $g['id'] ?>"> Remove photo</label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="lbl">Upload photos</label>
                    <input type="file" name="gallery_files[]" accept="image/*" multiple class="block w-full text-sm text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                    <p class="help">Select several images at once. JPG, PNG, WEBP or GIF up to 5 MB each.</p>
                </div>
                <?php f_textarea('…or add image URLs (one per line)', 'gallery_urls', '', 3); ?>
            </div>
        </div>

        <div class="card p-6 space-y-4" id="faq">
            <div class="flex items-center justify-between">
                <h2 class="font-bold">FAQ for this <?= $type === 'trip' ? 'trip' : 'activity' ?></h2>
                <button type="button" class="btn btn-light !py-1.5" onclick="addFaqRow()"><i class="fa-solid fa-plus"></i> Add question</button>
            </div>
            <p class="help">Shown as an accordion on the trip page and sent to Google as FAQ data. Translate them in <a class="text-brand-600 underline" href="<?= e(url('admin/faqs.php' . ($tour ? '?tour=' . $id : ''))) ?>">FAQs</a>.</p>
            <div id="faqRows" class="space-y-3">
                <?php $rows = $tourFaqs ?: [['id' => 0, 'question' => '', 'answer' => '']];
                foreach ($rows as $i => $f): ?>
                    <div class="border border-slate-200 rounded-xl p-3 space-y-2" data-faq-row>
                        <input type="hidden" name="faq[<?= $i ?>][id]" value="<?= (int) $f['id'] ?>">
                        <input class="inp" name="faq[<?= $i ?>][question]" value="<?= e($f['question']) ?>" placeholder="Question, e.g. Is hotel pickup included?">
                        <textarea class="inp" name="faq[<?= $i ?>][answer]" rows="2" placeholder="Answer"><?= e($f['answer']) ?></textarea>
                        <?php if ($f['id']): ?><label class="text-xs text-rose-600 font-semibold flex items-center gap-1"><input type="checkbox" name="faq[<?= $i ?>][delete]" value="1"> Remove this question</label><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php translations_card('tour', (int) ($tour['id'] ?? 0)); ?>

        <div class="card p-6 space-y-4" id="seo">
            <h2 class="font-bold">Search engines (SEO)</h2>
            <?php f_input('SEO title', 'seo_title', $t['seo_title'], 'text', 'maxlength="200" placeholder="' . e($t['title'] ? $t['title'] . ' | ' . setting('site_name') : '') . '"', 'Shown in Google results and the browser tab. Leave empty for "Title | Site name". Ideal: up to 60 characters.'); ?>
            <?php f_textarea('Meta description', 'meta_description', $t['meta_description'], 2, 'Shown under the title in Google. Leave empty to use the short description. Ideal: 120-160 characters.', 'maxlength="320"'); ?>
        </div>
    </div>
    <div class="space-y-6">
        <div class="card p-6 space-y-4">
            <?php f_select('Status', 'status', ['active' => 'Published', 'hidden' => 'Hidden (draft)'], $t['status']); ?>
            <?php f_check('Show on homepage', 'is_featured', (bool) $t['is_featured']); ?>
            <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-floppy-disk"></i> Save</button>
            <?php if ($tour): ?><a href="<?= e(url('tour.php?slug=' . urlencode($tour['slug']))) ?>" target="_blank" class="btn btn-light w-full justify-center"><i class="fa-solid fa-eye"></i> View on site</a><?php endif; ?>
        </div>
        <div class="card p-6 space-y-4">
            <?php f_image('Main image', 'image', $t['image']); ?>
        </div>
        <div class="card p-6 space-y-4">
            <?php f_select('Type', 'type', ['trip' => 'Multi-day trip', 'activity' => 'Day activity'], $t['type']); ?>
            <?php f_select('Category / travel style', 'category_id', $catOptions, $t['category_id']); ?>
            <?php f_input('URL slug', 'slug', $t['slug'], 'text', '', 'Leave empty to generate from the title.'); ?>
            <?php f_input('Icon (Font Awesome)', 'icon', $t['icon'], 'text', '', 'e.g. fa-water, fa-utensils, fa-mountain — see fontawesome.com/icons'); ?>
            <div class="grid grid-cols-2 gap-4">
                <?php f_input('Rating (0-5)', 'rating', $t['rating'], 'number', 'step="0.1" min="0" max="5"'); ?>
                <?php f_input('Review count', 'reviews_count', $t['reviews_count'], 'number', 'min="0"'); ?>
            </div>
            <?php f_input('Sort order', 'sort_order', $t['sort_order'], 'number', '', 'Lower numbers are shown first.'); ?>
        </div>
    </div>
</form>
<script>
function addFaqRow() {
    var wrap = document.getElementById('faqRows');
    var i = Date.now();
    var div = document.createElement('div');
    div.className = 'border border-slate-200 rounded-xl p-3 space-y-2';
    div.innerHTML = '<input type="hidden" name="faq[' + i + '][id]" value="0">'
        + '<input class="inp" name="faq[' + i + '][question]" placeholder="Question">'
        + '<textarea class="inp" name="faq[' + i + '][answer]" rows="2" placeholder="Answer"></textarea>';
    wrap.appendChild(div);
    div.querySelector('input.inp').focus();
}
(function () {
    var box = document.querySelector('input[type=checkbox][name=hide_price]');
    var price = document.getElementById('f_price');
    var fields = document.querySelector('[data-price-fields]');
    function sync() {
        price.required = !box.checked;
        fields.style.opacity = box.checked ? '.5' : '1';
    }
    box.addEventListener('change', sync);
    sync();
})();
</script>
<?php admin_footer(); ?>
