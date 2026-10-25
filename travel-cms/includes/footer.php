<?php
if (!defined('APP_ROOT')) {
    exit;
}
$footerDestinations = array_slice(destinations(true), 0, 5);
$footerTrips = $footerDestinations ? [] : localize_rows(db_all('SELECT id, title, slug FROM ' . tbl('tours') . " WHERE status = 'active' AND type = 'trip' ORDER BY sort_order, id LIMIT 4"), 'tour');
$newsletterOn = setting('newsletter_enabled', '1') === '1';
$popupOn = $newsletterOn && setting('newsletter_popup', '0') === '1' && empty($noPopup);
$footerPages = localize_rows(db_all('SELECT id, title, slug FROM ' . tbl('pages') . " WHERE status = 'published' AND show_in_footer = 1 ORDER BY title"), 'page');
$bookOptions = bookable_tours();
$minNotice = max(0, (int) setting('booking_min_days', 1));
$minDate = date('Y-m-d', strtotime('+' . $minNotice . ' days'));
$socials = ['facebook' => 'fa-facebook-f', 'instagram' => 'fa-instagram', 'twitter' => 'fa-x-twitter', 'youtube' => 'fa-youtube', 'tiktok' => 'fa-tiktok'];
$hasFaq = (bool) db_value('SELECT COUNT(*) FROM ' . tbl('faqs') . " WHERE tour_id IS NULL AND status = 'active'");
?>
<footer class="bg-slate-900 border-t border-slate-800 text-slate-400 py-12">
    <?php if ($newsletterOn): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-12">
        <div class="rounded-3xl bg-gradient-to-r from-brand-700 to-brand-500 p-6 sm:p-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6 shadow-2xl">
            <div class="text-white max-w-xl">
                <span class="text-xs font-bold uppercase tracking-widest text-accent-400"><i class="fa-solid fa-paper-plane me-1"></i> <?= e(__('Newsletter')) ?></span>
                <h3 class="font-serif text-2xl sm:text-3xl font-bold mt-1"><?= e(setting('newsletter_title', __('Get Travel Deals & Stories'))) ?></h3>
                <p class="text-sm text-brand-100 mt-1"><?= e(setting('newsletter_text', __('Exclusive offers, new trips and travel tips, straight to your inbox. No spam, unsubscribe anytime.'))) ?></p>
            </div>
            <form method="post" action="<?= e(url('subscribe.php')) ?>" data-subscribe class="w-full lg:w-auto flex flex-col sm:flex-row gap-3">
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="footer">
                <input type="text" name="website" value="" class="hidden" tabindex="-1" autocomplete="off">
                <input type="email" name="email" required placeholder="<?= e(__('Your email address')) ?>" aria-label="<?= e(__('Email address')) ?>" class="w-full sm:w-72 bg-white rounded-xl px-4 py-3 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-accent-400">
                <button class="px-6 py-3 bg-accent-500 hover:bg-accent-600 text-white font-bold rounded-xl text-sm shadow-lg whitespace-nowrap"><?= e(__('Subscribe')) ?></button>
                <p data-subscribe-msg class="hidden text-sm font-semibold text-white sm:self-center"></p>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
        <div class="space-y-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-brand-600 to-accent-500 flex items-center justify-center text-white font-bold">
                    <i class="fa-solid fa-compass"></i>
                </div>
                <span class="font-serif font-bold text-xl text-white" dir="ltr"><?= e(setting('logo_text_1', 'Wander')) ?><span class="text-accent-400"><?= e(setting('logo_text_2', 'Luxe')) ?></span></span>
            </div>
            <p class="text-xs leading-relaxed"><?= e(setting('footer_about', __('Creating memories that last a lifetime with personalized tours, local guides, and effortless online bookings.'))) ?></p>
            <div class="flex gap-2">
                <?php foreach ($socials as $key => $icon): if (setting('social_' . $key)): ?>
                    <a href="<?= e(setting('social_' . $key)) ?>" target="_blank" rel="noopener" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-brand-600 hover:text-white flex items-center justify-center transition"><i class="fa-brands <?= $icon ?> text-xs"></i></a>
                <?php endif; endforeach; ?>
            </div>
            <?php if (is_multilingual()): ?>
                <div class="flex flex-wrap gap-2 text-xs">
                    <i class="fa-solid fa-globe mt-0.5"></i>
                    <?php foreach (enabled_languages() as $l): ?>
                        <a href="<?= e(lang_switch_url($thisUrl ?? url(), $l['code'])) ?>" lang="<?= e($l['code']) ?>" class="hover:text-white <?= $l['code'] === current_lang() ? 'text-white font-bold' : '' ?>"><?= e($l['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div>
            <h4 class="text-white font-bold text-sm mb-4"><?= e(__('Quick Links')) ?></h4>
            <ul class="space-y-2 text-xs">
                <li><a href="<?= e(url('tours.php?type=trip')) ?>" class="hover:text-white transition"><?= e(__('Featured Trips')) ?></a></li>
                <li><a href="<?= e(url('tours.php?type=activity')) ?>" class="hover:text-white transition"><?= e(__('Day Excursions')) ?></a></li>
                <li><a href="<?= e(url('plan-trip.php')) ?>" class="hover:text-white transition"><?= e(__('Plan a Custom Trip')) ?></a></li>
                <li><a href="<?= e(url('blog.php')) ?>" class="hover:text-white transition"><?= e(__('Travel Blog')) ?></a></li>
                <li><a href="<?= e(url('index.php#reviews')) ?>" class="hover:text-white transition"><?= e(__('Customer Reviews')) ?></a></li>
                <?php if ($hasFaq): ?><li><a href="<?= e(url('faq.php')) ?>" class="hover:text-white transition"><?= e(__('FAQ')) ?></a></li><?php endif; ?>
                <li><a href="<?= e(url('wishlist.php')) ?>" class="hover:text-white transition"><?= e(__('Saved Trips')) ?></a></li>
                <li><a href="<?= e(url('my-booking.php')) ?>" class="hover:text-white transition"><?= e(__('Manage My Booking')) ?></a></li>
                <?php foreach ($footerPages as $p): ?>
                    <li><a href="<?= e(url('page.php?slug=' . urlencode($p['slug']))) ?>" class="hover:text-white transition"><?= e($p['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div>
            <h4 class="text-white font-bold text-sm mb-4"><?= e(__('Popular Destinations')) ?></h4>
            <ul class="space-y-2 text-xs">
                <?php foreach ($footerDestinations as $d): ?>
                    <li><a href="<?= e(url('destination.php?slug=' . urlencode($d['slug']))) ?>" class="hover:text-white transition"><?= e($d['name']) ?><?= $d['country'] && $d['country'] !== $d['name'] ? __(', ') . e($d['country']) : '' ?></a></li>
                <?php endforeach; ?>
                <?php foreach ($footerTrips as $t): ?>
                    <li><a href="<?= e(url('tour.php?slug=' . urlencode($t['slug']))) ?>" class="hover:text-white transition"><?= e($t['title']) ?></a></li>
                <?php endforeach; ?>
                <li><a href="<?= e(url('destinations.php')) ?>" class="hover:text-white transition font-semibold"><?= e(__('All destinations')) ?> <i class="fa-solid fa-arrow-right text-[9px] ms-1"></i></a></li>
            </ul>
        </div>
        <div>
            <h4 class="text-white font-bold text-sm mb-4"><?= e(__('Contact Desk')) ?></h4>
            <?php if (setting('contact_email')): ?><p class="text-xs mb-2"><i class="fa-solid fa-envelope me-2 text-brand-400"></i> <a href="mailto:<?= e(setting('contact_email')) ?>" class="hover:text-white"><?= e(setting('contact_email')) ?></a></p><?php endif; ?>
            <?php if (setting('contact_phone')): ?><p class="text-xs mb-2"><i class="fa-solid fa-phone me-2 text-brand-400"></i> <span dir="ltr"><?= e(setting('contact_phone')) ?></span></p><?php endif; ?>
            <?php if (setting('contact_whatsapp')): ?><p class="text-xs mb-2"><i class="fa-brands fa-whatsapp me-2 text-brand-400"></i> <a href="https://wa.me/<?= e(preg_replace('/\D/', '', setting('contact_whatsapp'))) ?>" target="_blank" rel="noopener" class="hover:text-white" dir="ltr"><?= e(setting('contact_whatsapp')) ?></a></p><?php endif; ?>
            <?php if (setting('contact_address')): ?><p class="text-xs"><i class="fa-solid fa-location-dot me-2 text-brand-400"></i> <?= e(setting('contact_address')) ?></p><?php endif; ?>
        </div>
    </div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 border-t border-slate-800 pt-6 flex flex-col sm:flex-row justify-between items-center text-xs">
        <p>&copy; <?= date('Y') ?> <?= e(setting('copyright_text', setting('site_name', 'WanderLuxe') . ' ' . __('Travel Agency. All rights reserved.'))) ?></p>
        <p class="mt-2 sm:mt-0 text-slate-500"><?= e(__('Pay-On-Arrival Reservation System')) ?></p>
    </div>
</footer>

<!-- BOOKING FORM MODAL (NO ONLINE PAYMENT) -->
<div id="bookingModal" class="fixed inset-0 z-50 hidden p-4 bg-slate-900/80 backdrop-blur-sm overflow-y-auto" data-modal>
    <div class="bg-white rounded-3xl max-w-xl w-full overflow-hidden shadow-2xl border border-slate-200 m-auto">
        <div class="bg-gradient-to-r from-brand-700 to-brand-500 p-6 text-white relative">
            <button type="button" onclick="closeModal('bookingModal')" class="absolute top-4 end-4 text-white/80 hover:text-white text-xl" aria-label="<?= e(__('Close')) ?>">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <span class="text-xs bg-white/20 px-3 py-1 rounded-full uppercase tracking-wider font-semibold"><?= e(__('Zero Payment Required')) ?></span>
            <h3 class="font-serif text-2xl font-bold mt-2"><?= e(__('Instant Booking Request')) ?></h3>
            <p class="text-xs text-brand-100 mt-1"><?= e(setting('booking_modal_text', __('Reserve your spot now & pay cash or card directly to your guide on arrival.'))) ?></p>
        </div>

        <?php if (!$bookOptions): ?>
            <div class="p-6 text-sm text-slate-500"><?= e(__('No tours are available for booking at the moment.')) ?></div>
        <?php else: ?>
        <form id="bookingForm" method="post" action="<?= e(url('book.php')) ?>" class="p-6 space-y-4">
            <?= csrf_field() ?>
            <input type="text" name="website" value="" class="hidden" tabindex="-1" autocomplete="off">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1" for="bookItemSelect"><?= e(__('Select Tour or Activity')) ?></label>
                <select id="bookItemSelect" name="tour_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    <?php foreach (['trip' => __('Multi-Day Trips'), 'activity' => __('Day Activities')] as $type => $label): ?>
                        <optgroup label="<?= e($label) ?>">
                            <?php foreach ($bookOptions as $o): if ($o['type'] !== $type) continue; ?>
                                <?php $hidden = prices_hidden($o); $pr = tour_prices($o); ?>
                                <option value="<?= (int) $o['id'] ?>" data-price="<?= $hidden ? '' : e($pr['adult']) ?>" data-child="<?= $hidden ? '' : e($pr['child']) ?>" data-infant="<?= $hidden ? '' : e($pr['infant']) ?>" data-children="<?= (int) $o['allow_children'] ?>" data-infants="<?= (int) $o['allow_infants'] ?>" data-max="<?= (int) $o['max_guests'] ?>"><?= e($o['title']) ?><?= $hidden ? '' : ' (' . e(money($o['price'])) . e(__('/person')) . ')' ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1" for="bookDate"><?= e(__('Preferred Date')) ?></label>
                <input type="date" id="bookDate" name="travel_date" min="<?= e($minDate) ?>" value="<?= e(old('travel_date')) ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1" for="bookAdults"><?= e(__('Adults')) ?></label>
                    <input type="number" id="bookAdults" name="adults" min="1" max="50" value="<?= e(old('adults', 2)) ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    <span class="block text-[10px] text-slate-400 mt-0.5" data-price-label="adult">&nbsp;</span>
                </div>
                <div data-guest-field="children">
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1" for="bookChildren"><?= e(__('Children')) ?></label>
                    <input type="number" id="bookChildren" name="children" min="0" max="50" value="<?= e(old('children', 0)) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    <span class="block text-[10px] text-slate-400 mt-0.5"><?= e(setting('child_age_label', __('Ages 2-11'))) ?> <span data-price-label="child"></span></span>
                </div>
                <div data-guest-field="infants">
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1" for="bookInfants"><?= e(__('Infants')) ?></label>
                    <input type="number" id="bookInfants" name="infants" min="0" max="20" value="<?= e(old('infants', 0)) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    <span class="block text-[10px] text-slate-400 mt-0.5"><?= e(setting('infant_age_label', __('Under 2'))) ?> <span data-price-label="infant"></span></span>
                </div>
            </div>
            <p id="bookGuestsError" class="hidden text-xs font-semibold text-rose-600"></p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1" for="bookName"><?= e(__('Full Name')) ?></label>
                    <input type="text" id="bookName" name="name" maxlength="150" value="<?= e(old('name')) ?>" placeholder="<?= e(__('e.g., Alex Johnson')) ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1" for="bookEmail"><?= e(__('Email Address')) ?></label>
                    <input type="email" id="bookEmail" name="email" maxlength="190" value="<?= e(old('email')) ?>" placeholder="alex@example.com" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1" for="bookPhone"><?= e(__('Phone Number (WhatsApp)')) ?></label>
                <input type="tel" id="bookPhone" name="phone" maxlength="60" value="<?= e(old('phone')) ?>" placeholder="+1 555-0199" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1" for="bookNotes"><?= e(__('Special Requests / Dietary Notes')) ?></label>
                <textarea id="bookNotes" name="notes" rows="2" maxlength="2000" placeholder="<?= e(__('Airport pickup, hotel location, or dietary requirements...')) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none"><?= e(old('notes')) ?></textarea>
            </div>

            <div class="flex items-center justify-between bg-brand-50 rounded-xl px-4 py-3 text-sm">
                <span class="text-slate-600"><?= e(__('Estimated total')) ?> <span class="text-xs text-slate-400">(<?= e(__('pay on arrival')) ?>)</span></span>
                <span class="font-extrabold text-brand-700" id="bookEstimate" data-symbol="<?= e(setting('currency_symbol', '$')) ?>" data-position="<?= e(setting('currency_position', 'before')) ?>" data-hidden-text="<?= e(setting('price_hidden_text', __('Price on request'))) ?>">—</span>
            </div>

            <?php if (setting('terms_page')): ?>
                <label class="flex items-start gap-2 text-xs text-slate-500">
                    <input type="checkbox" required class="mt-0.5 rounded">
                    <span><?= e(__('I agree to the')) ?> <a href="<?= e(url('page.php?slug=' . urlencode(setting('terms_page')))) ?>" target="_blank" class="text-brand-600 underline"><?= e(__('booking terms')) ?></a>.</span>
                </label>
            <?php endif; ?>

            <div class="pt-2">
                <button type="submit" class="w-full bg-accent-500 hover:bg-accent-600 text-white font-bold py-3 rounded-xl shadow-lg shadow-accent-500/30 transition text-sm flex items-center justify-center gap-2">
                    <i class="fa-solid fa-ticket"></i>
                    <span><?= e(__('Confirm Reservation & Generate Voucher')) ?></span>
                </button>
            </div>
            <p class="text-center text-xs text-slate-400"><?= e(__('Not sure yet?')) ?> <a href="<?= e(url('plan-trip.php')) ?>" class="text-brand-600 font-semibold underline"><?= e(__('Ask us to plan a custom trip')) ?></a></p>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if (setting('contact_whatsapp') && setting('whatsapp_button', '1')): ?>
<a href="https://wa.me/<?= e(preg_replace('/\D/', '', setting('contact_whatsapp'))) ?>" target="_blank" rel="noopener" class="fixed bottom-5 start-5 z-40 w-12 h-12 rounded-full bg-emerald-500 hover:bg-emerald-600 text-white shadow-xl flex items-center justify-center text-2xl" aria-label="WhatsApp">
    <i class="fa-brands fa-whatsapp"></i>
</a>
<?php endif; ?>

<?php if ($popupOn): ?>
<!-- NEWSLETTER POPUP (Settings > Newsletter) -->
<div id="newsletterPopup" class="fixed inset-0 z-50 hidden p-4 bg-slate-900/70 backdrop-blur-sm overflow-y-auto" data-modal data-delay="<?= (int) setting('newsletter_popup_delay', '8') ?>" data-days="<?= (int) setting('newsletter_popup_days', '7') ?>">
    <div class="bg-white rounded-3xl max-w-2xl w-full overflow-hidden shadow-2xl m-auto grid grid-cols-1 <?= setting('newsletter_popup_image') ? 'sm:grid-cols-2' : '' ?> relative">
        <button type="button" onclick="closeNewsletterPopup()" class="absolute top-3 end-3 z-10 w-9 h-9 rounded-full bg-white/90 text-slate-500 hover:text-slate-900 shadow flex items-center justify-center" aria-label="<?= e(__('Close')) ?>"><i class="fa-solid fa-xmark"></i></button>
        <?php if (setting('newsletter_popup_image')): ?>
            <img src="<?= e(img_url(setting('newsletter_popup_image'))) ?>" alt="" class="hidden sm:block w-full h-full object-cover min-h-[320px]">
        <?php endif; ?>
        <div class="p-8 flex flex-col justify-center">
            <div class="w-12 h-12 rounded-2xl bg-accent-100 text-accent-500 flex items-center justify-center text-xl mb-4"><i class="fa-solid fa-envelope-open-text"></i></div>
            <h3 class="font-serif text-2xl font-bold text-slate-900"><?= e(setting('newsletter_popup_title', __('Unlock Exclusive Travel Deals'))) ?></h3>
            <p class="text-sm text-slate-600 mt-2 mb-5"><?= e(setting('newsletter_popup_text', __('Join our travel club for members-only offers, new destinations and insider tips. No spam, unsubscribe anytime.'))) ?></p>
            <form method="post" action="<?= e(url('subscribe.php')) ?>" data-subscribe class="space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="popup">
                <input type="text" name="website" value="" class="hidden" tabindex="-1" autocomplete="off">
                <input type="text" name="name" maxlength="150" placeholder="<?= e(__('First name (optional)')) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                <input type="email" name="email" required placeholder="<?= e(__('Your email address')) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                <button class="w-full py-3 bg-accent-500 hover:bg-accent-600 text-white font-bold rounded-xl text-sm shadow-lg shadow-accent-500/30"><?= e(setting('newsletter_popup_button', __('Subscribe'))) ?></button>
                <p data-subscribe-msg class="hidden text-sm font-semibold text-emerald-600 text-center"></p>
            </form>
            <button type="button" onclick="closeNewsletterPopup()" class="mt-3 text-xs text-slate-400 hover:text-slate-600"><?= e(__('No thanks')) ?></button>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="toast" class="hidden fixed bottom-5 end-5 z-[60] bg-slate-900 text-white px-5 py-3 rounded-2xl shadow-2xl border border-slate-700 items-center gap-3 max-w-sm">
    <i class="fa-solid fa-circle-check text-emerald-400 text-lg"></i><span class="text-sm font-medium" data-toast-text></span>
</div>

<script>window.TCMS = <?= json_encode([
    'base' => parse_url(base_url(), PHP_URL_PATH) ?: '/',
    'lang' => current_lang(),
    'i18n' => [
        'each' => __('each'), 'free' => __('Free'), 'saved' => __('Saved'), 'save' => __('Save'),
        'savedToast' => __('Saved to your wishlist'), 'removedToast' => __('Removed from your wishlist'),
        'copied' => __('Link copied to clipboard'), 'copyPrompt' => __('Copy this link:'),
        'maxGuests' => __('Maximum %d guests per booking for this experience.'),
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script src="<?= e(url('assets/js/app.js')) ?>?v=<?= APP_VERSION ?>"></script>
<?php if (!empty($_SESSION['_open_booking'])): unset($_SESSION['_open_booking']); ?>
<script>document.addEventListener('DOMContentLoaded', function () { openBookingModal(<?= (int) old('tour_id', 0) ?>); });</script>
<?php endif; clear_input(); ?>
<?= setting('footer_code') ?>
</body>
</html>
