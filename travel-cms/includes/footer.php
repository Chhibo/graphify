<?php
if (!defined('APP_ROOT')) {
    exit;
}
$footerTrips = db_all('SELECT title, slug FROM ' . tbl('tours') . " WHERE status = 'active' AND type = 'trip' ORDER BY sort_order, id LIMIT 4");
$footerPages = db_all('SELECT title, slug FROM ' . tbl('pages') . " WHERE status = 'published' AND show_in_footer = 1 ORDER BY title");
$bookOptions = bookable_tours();
$minNotice = max(0, (int) setting('booking_min_days', 1));
$minDate = date('Y-m-d', strtotime('+' . $minNotice . ' days'));
$socials = ['facebook' => 'fa-facebook-f', 'instagram' => 'fa-instagram', 'twitter' => 'fa-x-twitter', 'youtube' => 'fa-youtube', 'tiktok' => 'fa-tiktok'];
?>
<footer class="bg-slate-900 border-t border-slate-800 text-slate-400 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
        <div class="space-y-4">
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-brand-600 to-accent-500 flex items-center justify-center text-white font-bold">
                    <i class="fa-solid fa-compass"></i>
                </div>
                <span class="font-serif font-bold text-xl text-white"><?= e(setting('logo_text_1', 'Wander')) ?><span class="text-accent-400"><?= e(setting('logo_text_2', 'Luxe')) ?></span></span>
            </div>
            <p class="text-xs leading-relaxed"><?= e(setting('footer_about', 'Creating memories that last a lifetime with personalized tours, local guides, and effortless online bookings.')) ?></p>
            <div class="flex space-x-2">
                <?php foreach ($socials as $key => $icon): if (setting('social_' . $key)): ?>
                    <a href="<?= e(setting('social_' . $key)) ?>" target="_blank" rel="noopener" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-brand-600 hover:text-white flex items-center justify-center transition"><i class="fa-brands <?= $icon ?> text-xs"></i></a>
                <?php endif; endforeach; ?>
            </div>
        </div>
        <div>
            <h4 class="text-white font-bold text-sm mb-4">Quick Links</h4>
            <ul class="space-y-2 text-xs">
                <li><a href="<?= e(url('tours.php?type=trip')) ?>" class="hover:text-white transition">Featured Trips</a></li>
                <li><a href="<?= e(url('tours.php?type=activity')) ?>" class="hover:text-white transition">Day Excursions</a></li>
                <li><a href="<?= e(url('blog.php')) ?>" class="hover:text-white transition">Travel Blog</a></li>
                <li><a href="<?= e(url('index.php#reviews')) ?>" class="hover:text-white transition">Customer Reviews</a></li>
                <li><a href="<?= e(url('my-booking.php')) ?>" class="hover:text-white transition">Manage My Booking</a></li>
                <?php foreach ($footerPages as $p): ?>
                    <li><a href="<?= e(url('page.php?slug=' . urlencode($p['slug']))) ?>" class="hover:text-white transition"><?= e($p['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div>
            <h4 class="text-white font-bold text-sm mb-4">Popular Destinations</h4>
            <ul class="space-y-2 text-xs">
                <?php foreach ($footerTrips as $t): ?>
                    <li><a href="<?= e(url('tour.php?slug=' . urlencode($t['slug']))) ?>" class="hover:text-white transition"><?= e($t['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div>
            <h4 class="text-white font-bold text-sm mb-4">Contact Desk</h4>
            <?php if (setting('contact_email')): ?><p class="text-xs mb-2"><i class="fa-solid fa-envelope mr-2 text-brand-400"></i> <a href="mailto:<?= e(setting('contact_email')) ?>" class="hover:text-white"><?= e(setting('contact_email')) ?></a></p><?php endif; ?>
            <?php if (setting('contact_phone')): ?><p class="text-xs mb-2"><i class="fa-solid fa-phone mr-2 text-brand-400"></i> <?= e(setting('contact_phone')) ?></p><?php endif; ?>
            <?php if (setting('contact_whatsapp')): ?><p class="text-xs mb-2"><i class="fa-brands fa-whatsapp mr-2 text-brand-400"></i> <a href="https://wa.me/<?= e(preg_replace('/\D/', '', setting('contact_whatsapp'))) ?>" target="_blank" rel="noopener" class="hover:text-white"><?= e(setting('contact_whatsapp')) ?></a></p><?php endif; ?>
            <?php if (setting('contact_address')): ?><p class="text-xs"><i class="fa-solid fa-location-dot mr-2 text-brand-400"></i> <?= e(setting('contact_address')) ?></p><?php endif; ?>
        </div>
    </div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 border-t border-slate-800 pt-6 flex flex-col sm:flex-row justify-between items-center text-xs">
        <p>&copy; <?= date('Y') ?> <?= e(setting('copyright_text', setting('site_name', 'WanderLuxe') . ' Travel Agency. All rights reserved.')) ?></p>
        <p class="mt-2 sm:mt-0 text-slate-500">Pay-On-Arrival Reservation System</p>
    </div>
</footer>

<!-- BOOKING FORM MODAL (NO ONLINE PAYMENT) -->
<div id="bookingModal" class="fixed inset-0 z-50 hidden p-4 bg-slate-900/80 backdrop-blur-sm overflow-y-auto" data-modal>
    <div class="bg-white rounded-3xl max-w-xl w-full overflow-hidden shadow-2xl border border-slate-200 m-auto">
        <div class="bg-gradient-to-r from-brand-700 to-brand-500 p-6 text-white relative">
            <button type="button" onclick="closeModal('bookingModal')" class="absolute top-4 right-4 text-white/80 hover:text-white text-xl" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <span class="text-xs bg-white/20 px-3 py-1 rounded-full uppercase tracking-wider font-semibold">Zero Payment Required</span>
            <h3 class="font-serif text-2xl font-bold mt-2">Instant Booking Request</h3>
            <p class="text-xs text-brand-100 mt-1"><?= e(setting('booking_modal_text', 'Reserve your spot now & pay cash or card directly to your guide on arrival.')) ?></p>
        </div>

        <?php if (!$bookOptions): ?>
            <div class="p-6 text-sm text-slate-500">No tours are available for booking at the moment.</div>
        <?php else: ?>
        <form id="bookingForm" method="post" action="<?= e(url('book.php')) ?>" class="p-6 space-y-4">
            <?= csrf_field() ?>
            <input type="text" name="website" value="" class="hidden" tabindex="-1" autocomplete="off">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1" for="bookItemSelect">Select Tour or Activity</label>
                <select id="bookItemSelect" name="tour_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    <?php foreach (['trip' => 'Multi-Day Trips', 'activity' => 'Day Activities'] as $type => $label): ?>
                        <optgroup label="<?= e($label) ?>">
                            <?php foreach ($bookOptions as $o): if ($o['type'] !== $type) continue; ?>
                                <option value="<?= (int) $o['id'] ?>" data-price="<?= $o['hide_price'] ? '' : e($o['price']) ?>" data-max="<?= (int) $o['max_guests'] ?>"><?= e($o['title']) ?><?= $o['hide_price'] ? '' : ' (' . e(money($o['price'])) . '/person)' ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1" for="bookDate">Preferred Date</label>
                    <input type="date" id="bookDate" name="travel_date" min="<?= e($minDate) ?>" value="<?= e(old('travel_date')) ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1" for="bookGuests">Number of Guests</label>
                    <input type="number" id="bookGuests" name="guests" min="1" max="50" value="<?= e(old('guests', 2)) ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1" for="bookName">Full Name</label>
                    <input type="text" id="bookName" name="name" maxlength="150" value="<?= e(old('name')) ?>" placeholder="e.g., Alex Johnson" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1" for="bookEmail">Email Address</label>
                    <input type="email" id="bookEmail" name="email" maxlength="190" value="<?= e(old('email')) ?>" placeholder="alex@example.com" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1" for="bookPhone">Phone Number (WhatsApp)</label>
                <input type="tel" id="bookPhone" name="phone" maxlength="60" value="<?= e(old('phone')) ?>" placeholder="+1 555-0199" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1" for="bookNotes">Special Requests / Dietary Notes</label>
                <textarea id="bookNotes" name="notes" rows="2" maxlength="2000" placeholder="Airport pickup, hotel location, or dietary requirements..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none"><?= e(old('notes')) ?></textarea>
            </div>

            <div class="flex items-center justify-between bg-brand-50 rounded-xl px-4 py-3 text-sm">
                <span class="text-slate-600">Estimated total <span class="text-xs text-slate-400">(pay on arrival)</span></span>
                <span class="font-extrabold text-brand-700" id="bookEstimate" data-symbol="<?= e(setting('currency_symbol', '$')) ?>" data-position="<?= e(setting('currency_position', 'before')) ?>" data-hidden-text="<?= e(setting('price_hidden_text', 'Price on request')) ?>">—</span>
            </div>

            <?php if (setting('terms_page')): ?>
                <label class="flex items-start space-x-2 text-xs text-slate-500">
                    <input type="checkbox" required class="mt-0.5 rounded">
                    <span>I agree to the <a href="<?= e(url('page.php?slug=' . urlencode(setting('terms_page')))) ?>" target="_blank" class="text-brand-600 underline">booking terms</a>.</span>
                </label>
            <?php endif; ?>

            <div class="pt-2">
                <button type="submit" class="w-full bg-accent-500 hover:bg-accent-600 text-white font-bold py-3 rounded-xl shadow-lg shadow-accent-500/30 transition text-sm flex items-center justify-center space-x-2">
                    <i class="fa-solid fa-ticket"></i>
                    <span>Confirm Reservation &amp; Generate Voucher</span>
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if (setting('contact_whatsapp') && setting('whatsapp_button', '1')): ?>
<a href="https://wa.me/<?= e(preg_replace('/\D/', '', setting('contact_whatsapp'))) ?>" target="_blank" rel="noopener" class="fixed bottom-5 left-5 z-40 w-12 h-12 rounded-full bg-emerald-500 hover:bg-emerald-600 text-white shadow-xl flex items-center justify-center text-2xl" aria-label="WhatsApp">
    <i class="fa-brands fa-whatsapp"></i>
</a>
<?php endif; ?>

<script src="<?= e(url('assets/js/app.js')) ?>?v=<?= APP_VERSION ?>"></script>
<?php if (!empty($_SESSION['_open_booking'])): unset($_SESSION['_open_booking']); ?>
<script>document.addEventListener('DOMContentLoaded', function () { openBookingModal(<?= (int) old('tour_id', 0) ?>); });</script>
<?php endif; clear_input(); ?>
<?= setting('footer_code') ?>
</body>
</html>
