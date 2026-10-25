<?php
require __DIR__ . '/includes/auth.php';
require_permission('settings');

$pageOptions = ['' => '— No terms checkbox —'];
foreach (db_all('SELECT slug, title FROM ' . tbl('pages') . ' ORDER BY title') as $p) {
    $pageOptions[$p['slug']] = $p['title'];
}
$tzOptions = array_combine(timezone_identifiers_list(), timezone_identifiers_list());

// [type, label, default, help, options]
$groups = [
    'general' => ['General', 'fa-gear', [
        'site_name' => ['text', 'Website name', 'WanderLuxe'],
        'site_tagline' => ['text', 'Tagline (browser title on homepage)', 'Premium Travel Experiences & Adventures'],
        'logo_text_1' => ['text', 'Logo text – white part', 'Wander'],
        'logo_text_2' => ['text', 'Logo text – orange part', 'Luxe'],
        'logo_image' => ['image', 'Logo image (optional, replaces the compass icon)', ''],
        'favicon' => ['image', 'Favicon', ''],
        'meta_description' => ['textarea', 'SEO meta description', ''],
        'footer_about' => ['textarea', 'Footer about text', 'Creating memories that last a lifetime with personalized tours, local guides, and effortless online bookings.'],
        'copyright_text' => ['text', 'Copyright text (after © year)', '', 'Default: "<Site name> Travel Agency. All rights reserved."'],
        'currency_symbol' => ['text', 'Currency symbol', '$', 'e.g. $, €, £, AED, EGP'],
        'currency_code' => ['text', 'Currency code (ISO)', 'USD', 'Used by Google for prices, e.g. USD, EUR, GBP, AED, EGP.'],
        'currency_position' => ['select', 'Currency position', 'before', '', ['before' => 'Before amount ($100)', 'after' => 'After amount (100 €)']],
        'date_format' => ['select', 'Date format', 'M j, Y', '', ['M j, Y' => date('M j, Y'), 'd/m/Y' => date('d/m/Y'), 'm/d/Y' => date('m/d/Y'), 'Y-m-d' => date('Y-m-d'), 'j F Y' => date('j F Y')]],
        'timezone' => ['select', 'Timezone', 'UTC', '', $tzOptions],
        'dark_mode_toggle' => ['bool', 'Show the dark / light mode button in the header', '1'],
        'dark_mode_default' => ['select', 'Default theme for new visitors', 'light', 'Visitors can switch with the moon/sun button; their choice is remembered.', ['light' => 'Light', 'dark' => 'Dark', 'auto' => "Follow the visitor's device setting"]],
    ]],
    'home' => ['Homepage', 'fa-house', [
        'hero_image' => ['image', 'Hero background image', 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=2000&q=80'],
        'hero_badge' => ['text', 'Hero badge', 'Unforgettable Luxury & Adventure Tours'],
        'hero_title_1' => ['text', 'Hero title – line 1', "Explore the World's Most"],
        'hero_title_2' => ['text', 'Hero title – line 2 (gradient)', 'Extraordinary Places'],
        'hero_subtitle' => ['textarea', 'Hero subtitle', 'Handcrafted guided itineraries, thrilling outdoor activities, and hassle-free instant reservations with zero upfront fees.'],
        'trust_1' => ['text', 'Trust badge 1', 'Instant Voucher Code'],
        'trust_2' => ['text', 'Trust badge 2', 'Zero Upfront Payment'],
        'trust_3' => ['text', 'Trust badge 3', '', 'Default: "<average rating>/5 Average Rating"'],
        'budget_options' => ['text', 'Budget filter options', '1000,2000,3500', 'Comma separated amounts.'],
        'trips_kicker' => ['text', 'Trips section – small label', 'Curated Packages'],
        'trips_title' => ['text', 'Trips section – title', 'Featured Travel Destinations'],
        'home_trips_count' => ['number', 'Number of trips on homepage', '6'],
        'activities_kicker' => ['text', 'Activities section – small label', 'Day Excursions'],
        'activities_title' => ['text', 'Activities section – title', 'Popular Outdoor Activities'],
        'activities_subtitle' => ['text', 'Activities section – subtitle', 'Elevate your vacation with thrilling half-day and full-day immersive experiences led by local experts.'],
        'home_activities_count' => ['number', 'Number of activities on homepage', '4'],
        'home_show_destinations' => ['bool', 'Show the destinations section on the homepage', '1', 'Shows up to 5 destinations marked "Show on homepage".'],
        'destinations_kicker' => ['text', 'Destinations section – small label', 'Where To Next?'],
        'destinations_title' => ['text', 'Destinations section – title', 'Explore Destinations'],
        'destinations_subtitle' => ['text', 'Destinations page – subtitle', 'Discover our handpicked destinations around the world and the trips and activities available in each one.'],
        'blog_kicker' => ['text', 'Blog section – small label', 'Insiders Guide'],
        'blog_title' => ['text', 'Blog section – title', 'Latest Travel Stories & Advice'],
        'blog_subtitle' => ['text', 'Blog section – subtitle', 'Read expert tips, destination highlights, and travel itineraries written by world explorers.'],
        'average_rating' => ['text', 'Reviews – rating shown', '', 'Leave empty to calculate from approved reviews.'],
        'reviews_summary' => ['text', 'Reviews – summary line', '', 'e.g. "Based on 1,240+ verified bookings". Empty = automatic.'],
        'feature_1_icon' => ['text', 'Feature 1 – icon', 'fa-handshake'],
        'feature_1_title' => ['text', 'Feature 1 – title', 'No Online Payment Needed'],
        'feature_1_text' => ['textarea', 'Feature 1 – text', 'Reserve your slot instantly without entering credit card details. Pay directly to your tour guide upon arrival.'],
        'feature_2_icon' => ['text', 'Feature 2 – icon', 'fa-user-shield'],
        'feature_2_title' => ['text', 'Feature 2 – title', 'Verified Local Guides'],
        'feature_2_text' => ['textarea', 'Feature 2 – text', 'Every excursion is led by certified, multilingual experts committed to safety, authenticity, and fun.'],
        'feature_3_icon' => ['text', 'Feature 3 – icon', 'fa-headset'],
        'feature_3_title' => ['text', 'Feature 3 – title', '24/7 Dedicated Support'],
        'feature_3_text' => ['textarea', 'Feature 3 – text', 'Our concierge desk is available round-the-clock on WhatsApp & phone to assist with trip customisations.'],
        'plan_cta_title' => ['text', 'Custom trip banner – title', "Can't find the perfect trip?"],
        'plan_cta_text' => ['textarea', 'Custom trip banner – text', 'Tell us your dream destinations, dates and budget. Our travel experts will design a personalised itinerary for you, free of charge.'],
        'faq_title' => ['text', 'FAQ page – title', 'Frequently Asked Questions'],
        'faq_subtitle' => ['text', 'FAQ page – subtitle', 'Answers about booking, payment on arrival, cancellations and travelling with us.'],
        'banner_image' => ['image', 'Inner pages banner image', 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?auto=format&fit=crop&w=2000&q=80'],
    ]],
    'booking' => ['Booking', 'fa-calendar-check', [
        'bookings_enabled' => ['bool', 'Accept online bookings', '1'],
        'hide_all_prices' => ['bool', 'Hide all prices on the website (price on request)', '0', 'Every trip shows the text below instead of a price. Guests can still book; bookings show "To be confirmed" until you enter the agreed price.'],
        'auto_confirm' => ['bool', 'Auto-confirm new bookings', '0', 'Off: new bookings arrive as "Pending" so you can confirm them.'],
        'booking_prefix' => ['text', 'Booking reference prefix', 'WL', 'References look like WL-123456.'],
        'booking_min_days' => ['number', 'Minimum notice (days)', '1', '0 = same-day bookings allowed.'],
        'booking_max_months' => ['number', 'Book up to (months ahead)', '18'],
        'daily_capacity' => ['number', 'Max guests per experience per day', '0', '0 = unlimited.'],
        'blocked_dates' => ['textarea', 'Blocked dates (one YYYY-MM-DD per line)', '', 'No bookings are accepted on these dates.'],
        'allow_guest_cancel' => ['bool', 'Guests can cancel online from their voucher', '1'],
        'cancel_min_days' => ['number', 'Online cancellation allowed until (days before travel)', '1'],
        'terms_page' => ['select', 'Booking terms page (checkbox in booking form)', '', '', $pageOptions],
        'booking_modal_text' => ['text', 'Booking form subtitle', 'Reserve your spot now & pay cash or card directly to your guide on arrival.'],
        'price_hidden_text' => ['text', 'Text shown instead of a hidden price', 'Price on request'],
        'child_age_label' => ['text', 'Child age label', 'Ages 2-11', 'Shown next to the child price and in the booking form.'],
        'infant_age_label' => ['text', 'Infant age label', 'Under 2'],
        'voucher_note' => ['textarea', 'Voucher note', 'Please present this voucher to your guide on arrival.'],
        'reviews_auto_approve' => ['bool', 'Publish visitor reviews without moderation', '0', 'For reviews written in the homepage form (not linked to a booking).'],
        'booking_reviews' => ['bool', 'Verified reviews: let guests review their booking after the trip', '1', 'Guests get a review link on their voucher and by email. Their review shows a "Verified booking" badge.'],
        'review_request_on_complete' => ['bool', 'Email the review link automatically when a booking is marked "Completed"', '1'],
        'verified_reviews_auto_approve' => ['bool', 'Publish verified reviews without moderation', '1'],
        'auto_tour_rating' => ['bool', 'Calculate trip ratings from approved reviews', '0', 'When on, a trip\'s star rating and review count are updated from its linked reviews.'],
    ]],
    'email' => ['Email', 'fa-envelope', [
        'mail_enabled' => ['bool', 'Send email notifications', '1', 'Uses the PHP mail() function of your hosting.'],
        'mail_from' => ['email', 'Sender address (From)', '', 'Use an address on your own domain, e.g. booking@yourdomain.com'],
        'admin_notify_email' => ['email', 'Send new booking alerts to', ''],
        'smtp_host' => ['text', 'SMTP host (optional)', '', 'Leave empty to use PHP mail(). e.g. smtp.gmail.com, mail.yourdomain.com'],
        'smtp_port' => ['number', 'SMTP port', '587', '587 for TLS, 465 for SSL.'],
        'smtp_secure' => ['select', 'SMTP encryption', 'tls', '', ['tls' => 'STARTTLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None']],
        'smtp_user' => ['text', 'SMTP username', ''],
        'smtp_pass' => ['password', 'SMTP password', ''],
    ]],
    'contact' => ['Contact & Social', 'fa-address-book', [
        'contact_email' => ['email', 'Public email', ''],
        'contact_phone' => ['text', 'Phone', ''],
        'contact_whatsapp' => ['text', 'WhatsApp number (with country code)', '', 'e.g. +1 555 0100'],
        'whatsapp_button' => ['bool', 'Show floating WhatsApp button', '1'],
        'contact_address' => ['text', 'Address', ''],
        'map_embed_url' => ['text', 'Google Maps embed URL (contact page)', '', 'Google Maps → Share → Embed a map → copy the src="…" URL.'],
        'social_facebook' => ['text', 'Facebook URL', ''],
        'social_instagram' => ['text', 'Instagram URL', ''],
        'social_twitter' => ['text', 'X / Twitter URL', ''],
        'social_youtube' => ['text', 'YouTube URL', ''],
        'social_tiktok' => ['text', 'TikTok URL', ''],
    ]],
    'newsletter' => ['Newsletter', 'fa-paper-plane', [
        'newsletter_enabled' => ['bool', 'Show the newsletter sign-up box in the footer', '1'],
        'newsletter_title' => ['text', 'Footer box – title', 'Get Travel Deals & Stories'],
        'newsletter_text' => ['text', 'Footer box – text', 'Exclusive offers, new trips and travel tips, straight to your inbox. No spam, unsubscribe anytime.'],
        'newsletter_success' => ['text', 'Message after subscribing', 'Thanks for subscribing! Check your inbox soon.'],
        'newsletter_popup' => ['bool', 'Enable the subscribe popup window', '0', 'A popup invites visitors to subscribe. It is never shown to people who already subscribed.'],
        'newsletter_popup_delay' => ['number', 'Popup – show after (seconds)', '8'],
        'newsletter_popup_days' => ['number', 'Popup – after closing, show again after (days)', '7'],
        'newsletter_popup_title' => ['text', 'Popup – title', 'Unlock Exclusive Travel Deals'],
        'newsletter_popup_text' => ['textarea', 'Popup – text', 'Join our travel club for members-only offers, new destinations and insider tips. No spam, unsubscribe anytime.'],
        'newsletter_popup_button' => ['text', 'Popup – button text', 'Subscribe'],
        'newsletter_popup_image' => ['image', 'Popup – image (optional, shown on the left)', ''],
        'newsletter_welcome' => ['bool', 'Send a welcome email to new subscribers', '1', 'Edit its text in Message Templates > Newsletter welcome.'],
        'newsletter_notify' => ['bool', 'Email me when someone subscribes', '0'],
    ]],
    'seo' => ['SEO', 'fa-magnifying-glass-chart', [
        'search_engines' => ['bool', 'Allow search engines to index this website', '1', 'Turn off while you are still building the site.'],
        'og_image' => ['image', 'Default social sharing image', '', 'Shown when a page without its own image is shared on WhatsApp, Facebook or X. Recommended 1200×630.'],
        'google_verification' => ['text', 'Google Search Console verification code', '', 'Only the content="…" value of the HTML tag.'],
    ]],
    'advanced' => ['Advanced', 'fa-code', [
        'head_code' => ['code', 'Custom code before </head>', '', 'Google Analytics, Meta Pixel, verification tags…'],
        'footer_code' => ['code', 'Custom code before </body>', '', 'Live chat widgets, etc.'],
    ]],
];

$tab = isset($groups[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'general';
// Translation mode: ?lang=xx edits the texts of that language (stored as translations).
$lang = (isset(languages()[$_GET['lang'] ?? '']) && $_GET['lang'] !== default_lang()) ? $_GET['lang'] : '';
$notTranslatable = '/^(currency_|booking_prefix|budget_options|contact_(email|phone|whatsapp)|social_|map_embed_url|google_verification|mail_from|admin_notify_email|smtp_|feature_\d_icon|site_name|logo_text_)/';
$isTranslatable = fn (string $key, array $def) => in_array($def[0], ['text', 'textarea'], true) && !preg_match($notTranslatable, $key);
$selfUrl = 'admin/settings.php?tab=' . $tab . ($lang ? '&lang=' . $lang : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf($selfUrl);
    if ($lang) {
        $data = [];
        foreach ($groups[$tab][2] as $key => $def) {
            if ($isTranslatable($key, $def) && isset($_POST[$key])) {
                $data[$key] = post($key);
            }
        }
        save_translations('setting', 0, [$lang => $data], array_keys($data));
        flash('success', languages()[$lang]['name'] . ' texts saved.');
        redirect($selfUrl);
    }
    if (post('action') === 'test_mail') {
        $to = $admin['email'];
        $ok = send_mail($to, 'Test email from ' . setting('site_name'), '<p>Your email settings work!</p>');
        flash($ok ? 'success' : 'error', $ok ? "Test email sent to $to." : 'Sending failed. Make sure notifications are enabled and your server supports PHP mail().');
        redirect('admin/settings.php?tab=email');
    }
    $current = settings_all();
    try {
        foreach ($groups[$tab][2] as $key => $def) {
            $type = $def[0];
            if ($type === 'image') {
                $value = resolve_image($key, $current[$key] ?? '');
            } elseif ($type === 'bool') {
                $value = post($key) === '1' ? '1' : '0';
            } elseif ($type === 'number') {
                $value = (string) max(0, (int) post($key));
            } elseif ($type === 'select') {
                $value = array_key_exists(post($key), $def[4]) ? post($key) : $def[2];
            } elseif ($type === 'code') {
                $value = (string) ($_POST[$key] ?? '');
            } else {
                $value = post($key);
            }
            save_setting($key, $value);
        }
        flash('success', 'Settings saved.');
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('admin/settings.php?tab=' . $tab);
}

$values = settings_all();
$trValues = $lang ? (get_translations('setting', 0)[$lang] ?? []) : [];
admin_header('Settings', 'settings');
?>
<?php if (count(languages()) > 1): ?>
    <div class="flex flex-wrap items-center gap-2 mb-4 text-xs font-bold">
        <span class="text-slate-500"><i class="fa-solid fa-language"></i> Editing texts in:</span>
        <?php foreach (languages() as $l): $active = $l['code'] === ($lang ?: default_lang()); ?>
            <a href="?tab=<?= $tab ?><?= $l['code'] !== default_lang() ? '&lang=' . $l['code'] : '' ?>" class="px-3 py-2 rounded-xl <?= $active ? 'bg-slate-900 text-white' : 'bg-white text-slate-600' ?>"><?= e($l['name']) ?><?= $l['code'] === default_lang() ? ' (default)' : '' ?></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($lang): ?>
    <div class="mb-4 px-4 py-3 rounded-xl text-sm bg-amber-50 text-amber-800 border border-amber-200">Translating the texts of this tab into <b><?= e(languages()[$lang]['name']) ?></b>. Empty fields show the text in grey (default language, or the built-in translation).</div>
<?php endif; ?>
<div class="flex flex-wrap gap-2 mb-4 text-xs font-bold">
    <?php foreach ($groups as $key => [$label, $icon]):
        if ($lang && !array_filter($groups[$key][2], fn ($d, $k) => $isTranslatable($k, $d), ARRAY_FILTER_USE_BOTH)) continue; ?>
        <a href="?tab=<?= $key ?><?= $lang ? '&lang=' . $lang : '' ?>" class="px-4 py-2.5 rounded-xl <?= $tab === $key ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50' ?>"><i class="fa-solid <?= $icon ?> mr-1"></i><?= e($label) ?></a>
    <?php endforeach; ?>
</div>
<form method="post" enctype="multipart/form-data" class="card p-6">
    <?= csrf_field() ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <?php foreach ($groups[$tab][2] as $key => $def):
            [$type, $label, $default] = $def;
            $help = $def[3] ?? '';
            if ($lang) {
                if (!$isTranslatable($key, $def)) {
                    continue;
                }
                $shown = __(($values[$key] ?? '') !== '' ? $values[$key] : $default, $lang);
                echo '<div class="' . ($type === 'textarea' ? 'md:col-span-2' : '') . '"><label class="lbl">' . e($label) . '</label>';
                if ($type === 'textarea') {
                    echo '<textarea class="inp" dir="' . lang_dir($lang) . '" name="' . e($key) . '" rows="3" placeholder="' . e($shown) . '">' . e($trValues[$key] ?? '') . '</textarea>';
                } else {
                    echo '<input class="inp" dir="' . lang_dir($lang) . '" name="' . e($key) . '" value="' . e($trValues[$key] ?? '') . '" placeholder="' . e($shown) . '">';
                }
                echo '</div>';
                continue;
            }
            $val = array_key_exists($key, $values) ? $values[$key] : $default;
            $wide = in_array($type, ['textarea', 'code', 'image'], true);
            echo '<div class="' . ($wide ? 'md:col-span-2' : '') . '">';
            switch ($type) {
                case 'textarea': f_textarea($label, $key, $val, 3, $help); break;
                case 'code': f_textarea($label, $key, $val, 6, $help, 'style="font-family:monospace;font-size:12px"'); break;
                case 'select': f_select($label, $key, $def[4], $val, $help); break;
                case 'bool': f_check($label, $key, $val === '1', $help); break;
                case 'image': f_image($label, $key, (string) $val); break;
                case 'number': f_input($label, $key, $val, 'number', 'min="0"', $help); break;
                default: f_input($label, $key, $val, $type, '', $help);
            }
            echo '</div>';
        endforeach; ?>
    </div>
    <div class="flex justify-end mt-6 pt-6 border-t border-slate-100"><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save settings</button></div>
</form>
<?php if ($tab === 'seo'): ?>
    <div class="card p-6 mt-4 text-sm text-slate-600 space-y-2">
        <h2 class="font-bold text-slate-900">Sitemap & robots.txt</h2>
        <p>Submit your sitemap in <a href="https://search.google.com/search-console" target="_blank" rel="noopener" class="text-brand-600 underline">Google Search Console</a>: <a href="<?= e(url('sitemap.php')) ?>" target="_blank" class="font-mono text-brand-700 underline"><?= e(url('sitemap.php')) ?></a></p>
        <p>robots.txt: <a href="<?= e(url('robots.php')) ?>" target="_blank" class="font-mono text-brand-700 underline"><?= e(url('robots.php')) ?></a> (also available as /sitemap.xml and /robots.txt on Apache).</p>
        <p class="text-xs text-slate-400">Each trip, destination, blog post and page has its own SEO title and meta description fields. Trips also send ratings and prices to Google as structured data.</p>
    </div>
<?php endif; ?>
<?php if ($tab === 'email'): ?>
    <form method="post" class="card p-6 mt-4 flex items-center justify-between">
        <?= csrf_field() ?><input type="hidden" name="action" value="test_mail">
        <p class="text-sm text-slate-600">Send a test email to <b><?= e($admin['email']) ?></b> (save your settings first).</p>
        <button class="btn btn-light"><i class="fa-solid fa-paper-plane"></i> Send test</button>
    </form>
<?php endif; ?>
<?php admin_footer(); ?>
