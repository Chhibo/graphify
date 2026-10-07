<?php
require __DIR__ . '/includes/auth.php';

$id = (int) ($_GET['id'] ?? 0);
$tour = $id ? db_one('SELECT * FROM ' . tbl('tours') . ' WHERE id = ?', [$id]) : null;
if ($id && !$tour) {
    flash('error', 'Experience not found.');
    redirect('admin/tours.php');
}
$type = $tour['type'] ?? (($_GET['type'] ?? 'trip') === 'activity' ? 'activity' : 'trip');
$t = $tour ?: [
    'type' => $type, 'title' => '', 'slug' => '', 'category_id' => null, 'destination' => '', 'price' => '', 'hide_price' => 0, 'duration' => '',
    'max_guests' => $type === 'trip' ? 12 : 10, 'icon' => $type === 'trip' ? 'fa-plane' : 'fa-compass', 'image' => '',
    'short_description' => '', 'description' => '', 'inclusions' => '', 'itinerary' => '', 'rating' => '5.0', 'reviews_count' => 0,
    'is_featured' => 1, 'status' => 'active', 'sort_order' => 0,
];
$self = 'admin/tour-edit.php?' . ($id ? 'id=' . $id : 'type=' . $type);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf($self);
    $data = [
        'type' => post('type') === 'activity' ? 'activity' : 'trip',
        'title' => mb_substr(post('title'), 0, 200),
        'category_id' => (int) post('category_id') ?: null,
        'destination' => mb_substr(post('destination'), 0, 150),
        'price' => max(0, (float) post('price')),
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
    flash('success', 'Saved successfully.');
    redirect('admin/tour-edit.php?id=' . $id);
}

$catOptions = ['' => '— None —'];
foreach (categories() as $c) {
    $catOptions[$c['id']] = $c['name'];
}

admin_header($tour ? 'Edit: ' . $tour['title'] : 'New ' . ($type === 'trip' ? 'trip' : 'activity'), $type === 'trip' ? 'trips' : 'activities');
?>
<a href="<?= e(url('admin/tours.php?type=' . $type)) ?>" class="text-xs font-bold text-slate-500 hover:text-brand-600"><i class="fa-solid fa-arrow-left mr-1"></i> Back to list</a>
<form method="post" enctype="multipart/form-data" class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-3">
    <?= csrf_field() ?>
    <div class="xl:col-span-2 space-y-6">
        <div class="card p-6 space-y-4">
            <?php f_input('Title', 'title', $t['title'], 'text', 'required maxlength="200"'); ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?php f_input('Destination / location', 'destination', $t['destination'], 'text', 'placeholder="e.g., Bali, Indonesia"'); ?>
                <?php f_input('Duration', 'duration', $t['duration'], 'text', 'placeholder="' . ($type === 'trip' ? '7 Days / 6 Nights' : '5 Hours') . '"'); ?>
                <div class="space-y-2">
                    <?php f_input('Price per guest (' . setting('currency_symbol', '$') . ')', 'price', $t['price'], 'number', 'step="0.01" min="0"' . ($t['hide_price'] ? '' : ' required')); ?>
                    <?php f_check('Hide price', 'hide_price', (bool) $t['hide_price'], 'Shows "' . setting('price_hidden_text', 'Price on request') . '" and the price is not required. You confirm the price with the guest after booking.'); ?>
                </div>
                <?php f_input('Max guests per booking', 'max_guests', $t['max_guests'], 'number', 'min="1"'); ?>
            </div>
            <?php f_textarea('Short description (shown on cards)', 'short_description', $t['short_description'], 2, 'Max 500 characters.', 'maxlength="500"'); ?>
            <?php f_textarea('Full description', 'description', $t['description'], 8, 'Plain text (blank line = new paragraph) or basic HTML.'); ?>
        </div>
        <div class="card p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php f_textarea("What's included (one per line)", 'inclusions', $t['inclusions'], 7, 'e.g. Hotel pickup'); ?>
            <?php f_textarea('Itinerary (one step per line)', 'itinerary', $t['itinerary'], 7, 'Format: Day 1 - Arrival | Description of the day'); ?>
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
(function () {
    var box = document.querySelector('input[type=checkbox][name=hide_price]');
    var price = document.getElementById('f_price');
    function sync() {
        price.required = !box.checked;
        price.closest('div').style.opacity = box.checked ? '.5' : '1';
    }
    box.addEventListener('change', sync);
    sync();
})();
</script>
<?php admin_footer(); ?>
