<?php
/**
 * Default settings and optional demo content inserted by the installer.
 * Expects $pdo (PDO) and $prefix (string) in scope.
 */
if (!isset($pdo, $prefix)) {
    exit;
}

function seed_insert(PDO $pdo, string $prefix, string $table, array $row): int
{
    $cols = array_keys($row);
    $sql = 'INSERT INTO `' . $prefix . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    $pdo->prepare($sql)->execute(array_values($row));
    return (int) $pdo->lastInsertId();
}

function seed_default_settings(PDO $pdo, string $prefix, array $site): void
{
    $settings = [
        'site_name' => $site['site_name'],
        'site_tagline' => 'Premium Travel Experiences & Adventures',
        'meta_description' => 'Handcrafted guided itineraries, outdoor activities and instant pay-on-arrival reservations.',
        'logo_text_1' => $site['logo_text_1'],
        'logo_text_2' => $site['logo_text_2'],
        'contact_email' => $site['email'],
        'admin_notify_email' => $site['email'],
        'contact_phone' => '+1 (800) 555-0100',
        'contact_whatsapp' => '',
        'contact_address' => '100 Adventure Way, Suite 400',
        'currency_symbol' => '$',
        'currency_position' => 'before',
        'date_format' => 'M j, Y',
        'timezone' => $site['timezone'],
        'booking_prefix' => 'WL',
        'booking_min_days' => '1',
        'booking_max_months' => '18',
        'bookings_enabled' => '1',
        'auto_confirm' => '0',
        'allow_guest_cancel' => '1',
        'cancel_min_days' => '1',
        'daily_capacity' => '0',
        'mail_enabled' => '1',
        'reviews_auto_approve' => '0',
        'whatsapp_button' => '1',
        'voucher_note' => 'Please present this voucher (printed or on your phone) to your guide on arrival. Payment is made directly on the day.',
        'home_trips_count' => '6',
        'home_activities_count' => '4',
        'budget_options' => '1000,2000,3500',
        'price_hidden_text' => 'Price on request',
        'db_version' => (string) SCHEMA_VERSION,
        'hide_all_prices' => '0',
        'child_age_label' => 'Ages 2-11',
        'infant_age_label' => 'Under 2',
        'newsletter_enabled' => '1',
        'newsletter_popup' => '0',
        'newsletter_popup_delay' => '8',
        'newsletter_popup_days' => '7',
        'languages' => json_encode([
            ['code' => 'en', 'name' => 'English', 'dir' => 'ltr', 'enabled' => true],
            ['code' => 'ar', 'name' => 'العربية', 'dir' => 'rtl', 'enabled' => false],
        ], JSON_UNESCAPED_UNICODE),
        'default_language' => 'en',
        'detect_browser_language' => '0',
        'dark_mode_toggle' => '1',
        'dark_mode_default' => 'light',
        'booking_reviews' => '1',
        'review_request_on_complete' => '1',
        'verified_reviews_auto_approve' => '1',
        'auto_tour_rating' => '0',
    ];
    $st = $pdo->prepare('INSERT INTO `' . $prefix . 'settings` (name, value) VALUES (?, ?)');
    foreach ($settings as $k => $v) {
        $st->execute([$k, $v]);
    }
}

function seed_demo_content(PDO $pdo, string $prefix): void
{
    $now = date('Y-m-d H:i:s');
    $cats = [];
    foreach (['Beach' => 'beach', 'Adventure' => 'adventure', 'Cultural' => 'cultural', 'Luxury' => 'luxury'] as $i => $slug) {
        $cats[$slug] = seed_insert($pdo, $prefix, 'categories', ['name' => $i, 'slug' => $slug, 'sort_order' => count($cats)]);
    }

    $u = 'https://images.unsplash.com/';
    $dests = [];
    $destRows = [
        ['Bali', 'Indonesia', 'photo-1537996194471-e657df975ab4', 'Temples, rice terraces, surf beaches and the turquoise reefs of Nusa Penida.'],
        ['Switzerland', 'Switzerland', 'photo-1530122037265-a5f1f91d3b99', 'Glaciers, alpine lakes and storybook villages, best explored on foot and by rail.'],
        ['Egypt', 'Egypt', 'photo-1503177119275-0aa32b3a9368', 'Pyramids, pharaohs and the timeless Nile, from Cairo to Luxor and Aswan.'],
        ['Santorini', 'Greece', 'photo-1570077188670-e3a8d69ac5ff', 'Whitewashed cliffs, volcanic beaches and the most famous sunsets of the Aegean.'],
        ['Costa Rica', 'Costa Rica', 'photo-1518259102261-b40117eabbc9', 'Rainforests, volcanoes and wildlife on both the Caribbean and Pacific coasts.'],
        ['Kyoto', 'Japan', 'photo-1493976040374-85c8e12f0c0e', 'Ancient temples, tea houses and bamboo groves in the heart of traditional Japan.'],
        ['Dubai', 'United Arab Emirates', 'photo-1451187580459-43490279c0fa', 'Desert safaris, record-breaking skylines and luxury beach resorts.'],
        ['Chiang Mai', 'Thailand', 'photo-1556910103-1c02745aae4d', 'Mountain temples, night markets and the best street food in northern Thailand.'],
        ['Cappadocia', 'Turkey', 'photo-1507608616759-54f48f0af0ee', 'Fairy chimneys, cave hotels and unforgettable hot air balloon sunrises.'],
    ];
    foreach ($destRows as $i => $d) {
        $dests[$d[0]] = seed_insert($pdo, $prefix, 'destinations', [
            'name' => $d[0], 'slug' => slug_for_seed($d[0]), 'country' => $d[1],
            'image' => $u . $d[2] . '?auto=format&fit=crop&w=1200&q=80', 'short_description' => $d[3],
            'description' => $d[3] . "\n\nBrowse our handpicked trips and day activities below and reserve your spot today. No online payment is needed: you pay on arrival.",
            'is_featured' => $i < 6 ? 1 : 0, 'status' => 'active', 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
    $tripDest = ['Bali', 'Switzerland', 'Egypt', 'Santorini', 'Costa Rica', 'Kyoto'];
    $actDest = ['Bali', 'Dubai', 'Chiang Mai', 'Cappadocia'];
    $galleries = [
        0 => ['photo-1537996194471-e657df975ab4', 'photo-1544551763-46a013bb70d5', 'photo-1528127269322-539801943592', 'photo-1507525428034-b723cf961d3e'],
        1 => ['photo-1530122037265-a5f1f91d3b99', 'photo-1502784444187-359ac186c5bb', 'photo-1488646953014-85cb44e25828'],
    ];
    $trips = [
        ['Bali Luxury Ocean & Temple Tour', 'beach', 'Bali, Indonesia', 1899, '7 Days / 6 Nights', 'photo-1537996194471-e657df975ab4', 4.9, 142,
            "5-Star Beach Resort\nDaily Breakfast & Dinner\nPrivate Airport Transfers\nUbud Monkey Forest Entry",
            'Experience the magic of Bali with luxury oceanfront accommodations, guided temple visits, private speedboats to Nusa Penida, and authentic Balinese spa treatments.'],
        ['Swiss Alps Glacier & Peak Trekking', 'adventure', 'Interlaken, Switzerland', 2450, '6 Days / 5 Nights', 'photo-1530122037265-a5f1f91d3b99', 5.0, 98,
            "Mountain Lodge Stay\nSwiss Rail Pass\nCertified Alpine Guide\nCable Car Tickets",
            'Immerse yourself in breathtaking mountain vistas, pristine alpine lakes, and historic Swiss villages with world-class guided hiking trails.'],
        ['Egyptian Pyramids & Nile Cruise', 'cultural', 'Cairo & Luxor, Egypt', 1550, '8 Days / 7 Nights', 'photo-1503177119275-0aa32b3a9368', 4.8, 210,
            "5-Star Nile River Cruise\nEgyptologist Private Guide\nPyramid & Tomb Entry\nDomestic Flights",
            'Uncover thousands of years of ancient history with private guided tours of the Giza Pyramids, Sphinx, Valley of the Kings, and a luxury Nile river voyage.'],
        ['Santorini Sunset & Wine Tasting', 'beach', 'Santorini, Greece', 2100, '5 Days / 4 Nights', 'photo-1570077188670-e3a8d69ac5ff', 4.9, 175,
            "Caldera View Cave Villa\nSunset Catamaran Cruise\nVolcanic Wine Tasting\nDaily Buffet Breakfast",
            'Indulge in iconic white-and-blue Aegean views, cliffside luxury suites, volcanic vineyard tours, and romantic catamaran sailing at sunset.'],
        ['Costa Rica Rainforest & Volcano Safari', 'adventure', 'Arenal, Costa Rica', 1350, '6 Days / 5 Nights', 'photo-1518259102261-b40117eabbc9', 4.9, 114,
            "Eco-Lodge Thermal Stay\nZipline Canopy Tour\nHot Springs Pass\nNight Jungle Walk",
            'Discover rich biodiversity, wildlife safaris, thermal hot springs under Arenal Volcano, and thrilling canopy ziplining in pristine cloud forests.'],
        ['Kyoto Ancient Temples & Tea Culture', 'cultural', 'Kyoto, Japan', 2800, '7 Days / 6 Nights', 'photo-1493976040374-85c8e12f0c0e', 5.0, 89,
            "Traditional Ryokan Stay\nTea Ceremony Masterclass\nBullet Train Pass\nKimono Experience",
            "Step back in time to Japan's cultural capital. Enjoy authentic Kaiseki dining, bamboo groves, serene zen gardens, and private geisha heritage walks."],
    ];
    $tripIds = [];
    foreach ($trips as $i => $t) {
        $tripIds[$i] = $tourId = seed_insert($pdo, $prefix, 'tours', [
            'type' => 'trip', 'title' => $t[0], 'slug' => slug_for_seed($t[0]), 'category_id' => $cats[$t[1]],
            'destination_id' => $dests[$tripDest[$i]], 'destination' => $t[2], 'price' => $t[3], 'child_price' => round($t[3] * 0.7), 'duration' => $t[4], 'max_guests' => 12, 'icon' => 'fa-plane',
            'image' => $u . $t[5] . '?auto=format&fit=crop&w=1200&q=80',
            'short_description' => $t[9],
            'description' => $t[9] . "\n\nOur local experts take care of every detail so you can simply enjoy the journey. Reserve today with zero upfront payment and settle the balance directly with your guide on arrival.",
            'inclusions' => $t[8],
            'itinerary' => "Day 1 - Arrival | Private transfer to your hotel and welcome dinner.\nDay 2 - Guided highlights | Full-day guided tour of the region's most iconic sights.\nDay 3 - Free day | Relax or choose one of our optional day activities.\nFinal day - Departure | Breakfast and private transfer to the airport.",
            'rating' => $t[6], 'reviews_count' => $t[7], 'is_featured' => 1, 'status' => 'active', 'sort_order' => $i,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        foreach ($galleries[$i] ?? [] as $n => $photo) {
            seed_insert($pdo, $prefix, 'tour_images', ['tour_id' => $tourId, 'image' => $u . $photo . '?auto=format&fit=crop&w=1400&q=80', 'sort_order' => $n]);
        }
    }

    $acts = [
        ['Nusa Penida Coral Reef Scuba Diving', 'Bali, Indonesia', 120, '5 Hours', 'fa-water', 'photo-1544551763-46a013bb70d5', 'Dive into turquoise waters with manta rays, sea turtles, and vibrant coral reefs alongside certified PADI instructors.', 'adventure'],
        ['Dubai Desert Dune Bashing & BBQ Night', 'Dubai, UAE', 85, '6 Hours', 'fa-sun', 'photo-1451187580459-43490279c0fa', 'Thrilling 4x4 dune bashing, camel riding, sunset photo stops, and a traditional Bedouin dinner show under the stars.', 'adventure'],
        ['Traditional Thai Cooking Class & Market Tour', 'Chiang Mai, Thailand', 55, '4 Hours', 'fa-utensils', 'photo-1556910103-1c02745aae4d', 'Visit local organic markets and learn to cook 5 authentic Thai dishes from scratch with local master chefs.', 'cultural'],
        ['Cappadocia Sunrise Hot Air Balloon', 'Cappadocia, Turkey', 190, '3 Hours', 'fa-cloud-sun', 'photo-1507608616759-54f48f0af0ee', 'Float gracefully over fairytale chimneys and unique rock formations while sipping champagne at sunrise.', 'luxury'],
    ];
    $actIds = [];
    foreach ($acts as $i => $a) {
        $actIds[$i] = seed_insert($pdo, $prefix, 'tours', [
            'type' => 'activity', 'title' => $a[0], 'slug' => slug_for_seed($a[0]), 'category_id' => $cats[$a[7]],
            'destination_id' => $dests[$actDest[$i]], 'destination' => $a[1], 'price' => $a[2], 'child_price' => round($a[2] * 0.6),
            'allow_infants' => in_array($i, [0, 3], true) ? 0 : 1, 'duration' => $a[3], 'max_guests' => 10, 'icon' => $a[4],
            'image' => $u . $a[5] . '?auto=format&fit=crop&w=900&q=80',
            'short_description' => $a[6], 'description' => $a[6], 'inclusions' => "Hotel pickup & drop-off\nProfessional local guide\nAll equipment\nBottled water",
            'itinerary' => '', 'rating' => 4.9, 'reviews_count' => 40 + $i * 13, 'is_featured' => 1, 'status' => 'active', 'sort_order' => $i,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    $posts = [
        ['10 Essential Tips for First-Time Solo Travelers in Southeast Asia', 'Elena Rostova', '-4 days', '6 min read', 'Travel Tips', 'photo-1528127269322-539801943592',
            'Navigating Southeast Asia solo is one of the most rewarding adventures a traveler can undertake. From staying connected with local eSIMs to booking pay-on-arrival guided tours, here is how you can stay safe and maximize your journey across Thailand, Vietnam, and Indonesia.'],
        ['Why Autumn is the Absolute Best Season to Visit the Swiss Alps', 'Marcus Vance', '-8 days', '4 min read', 'Destinations', 'photo-1502784444187-359ac186c5bb',
            'Fewer crowds, crisp mountain air, and golden larch trees turning the alpine valleys into a glowing paradise. Here is why planning your Swiss trekking trip during September and October gives you the ultimate value and views.'],
        ['How to Book Authentic Local Experiences Without Prepaying Online', 'Sophia Chen', '-21 days', '5 min read', 'Smart Booking', 'photo-1488646953014-85cb44e25828',
            'Many travelers prefer flexibility when planning international trips. Learn how our instant voucher system allows you to lock in spots for scuba diving, desert safaris, and food tours without risking upfront payments.'],
    ];
    $postIds = [];
    foreach ($posts as $p) {
        $postIds[] = seed_insert($pdo, $prefix, 'posts', [
            'title' => $p[0], 'slug' => slug_for_seed($p[0]), 'tag' => $p[4], 'author' => $p[1],
            'image' => $u . $p[5] . '?auto=format&fit=crop&w=1200&q=80', 'excerpt' => $p[6],
            'content' => $p[6] . "\n\nPlanning ahead is the secret to a stress-free trip. Research the season, pack light and keep digital copies of your documents.\n\nMost importantly, leave room for spontaneity. Some of the best travel memories happen when you follow a local recommendation or say yes to an unexpected invitation.",
            'read_time' => $p[3], 'status' => 'published', 'published_at' => date('Y-m-d H:i:s', strtotime($p[2])), 'created_at' => $now,
        ]);
    }

    $reviews = [
        ['Samantha & David Wright', 'Bali Luxury Ocean & Temple Tour', 'Absolutely seamless! We reserved our Bali tour online without paying anything beforehand. Our guide Nyoman was fantastic and met us right at our hotel entrance.', 'photo-1534528741775-53994a69daeb', '-3 days'],
        ['Liam Gallagher', 'Swiss Alps Glacier & Peak Trekking', 'The glacier views were unbelievable. Having the voucher code ready made check-in at the base lodge super fast. Will definitely book again for my next vacation!', 'photo-1507003211169-0a1dd7228f2d', '-7 days'],
        ['Noah Williams', 'Egyptian Pyramids & Nile Cruise', 'Our Egyptologist guide brought history to life. Paying on arrival gave us total peace of mind. Highly recommended.', 'photo-1535713875002-d1d0cf377fde', '-14 days'],
    ];
    foreach ($reviews as $i => $r) {
        seed_insert($pdo, $prefix, 'reviews', [
            'name' => $r[0], 'trip' => $r[1], 'tour_id' => $tripIds[$i] ?? null, 'rating' => 5, 'content' => $r[2],
            'avatar' => $u . $r[3] . '?auto=format&fit=crop&w=150&q=80', 'status' => 'approved',
            'created_at' => date('Y-m-d H:i:s', strtotime($r[4])),
        ]);
    }

    $pages = [
        ['About Us', "We are a team of passionate travelers and local guides creating unforgettable journeys around the world.\n\nEvery itinerary is handcrafted, every guide is verified, and every booking is free to reserve: you only pay when you arrive."],
        ['Booking Terms', "1. Reservations are free. No payment is taken online.\n\n2. Payment is due on arrival, in cash or by card, directly to your guide.\n\n3. You can cancel online for free up to 24 hours before your travel date.\n\n4. Prices are per person unless stated otherwise."],
        ['Privacy Policy', "We only collect the information needed to manage your booking (name, email, phone and travel details). We never sell your data to third parties.\n\nContact us at any time to request deletion of your personal information."],
    ];
    $pageIds = [];
    foreach ($pages as $p) {
        $pageIds[] = seed_insert($pdo, $prefix, 'pages', [
            'title' => $p[0], 'slug' => slug_for_seed($p[0]), 'content' => $p[1], 'show_in_footer' => 1,
            'status' => 'published', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
    $pdo->prepare('INSERT INTO `' . $prefix . 'settings` (name, value) VALUES (?, ?)')->execute(['terms_page', 'booking-terms']);

    seed_demo_faqs($pdo, $prefix, $tripIds, $actIds);
    seed_demo_arabic($pdo, $prefix, $cats, $dests, $tripIds, $actIds, $postIds, $pageIds);
}

/** General FAQ page (grouped) and a few questions per trip, in English and Arabic. */
function seed_demo_faqs(PDO $pdo, string $prefix, array $tripIds, array $actIds): void
{
    $now = date('Y-m-d H:i:s');
    $general = [
        ['Booking', 'العربية' => 'الحجز', 'Do I need to pay online to book?', 'No. Booking is free and no card is needed. You pay on arrival, in cash or by card, directly to your guide.',
            'هل أحتاج إلى الدفع عبر الإنترنت للحجز؟', 'لا. الحجز مجاني ولا حاجة لبطاقة. تدفع عند الوصول نقداً أو بالبطاقة مباشرة لمرشدك.'],
        ['Booking', 'العربية' => 'الحجز', 'How do I know my booking is confirmed?', 'You receive a voucher by email right after booking. Our team confirms every booking by email or WhatsApp, usually within a few hours.',
            'كيف أعرف أن حجزي مؤكد؟', 'تصلك قسيمة عبر البريد الإلكتروني فور الحجز، ويؤكد فريقنا كل حجز عبر البريد أو واتساب عادة خلال ساعات قليلة.'],
        ['Booking', 'العربية' => 'الحجز', 'Can you plan a trip just for us?', 'Yes! Use the "Plan a Trip" form, tell us your destinations, dates and budget, and we will send you a personalised proposal for free.',
            'هل يمكنكم تخطيط رحلة خاصة بنا؟', 'نعم! استخدم نموذج "خطط لرحلتك" وأخبرنا بوجهاتك وتواريخك وميزانيتك، وسنرسل لك عرضاً مخصصاً مجاناً.'],
        ['Payment', 'العربية' => 'الدفع', 'Which payment methods do you accept on arrival?', 'Cash (local currency, USD or EUR) and all major credit cards.',
            'ما طرق الدفع المقبولة عند الوصول؟', 'النقد (العملة المحلية أو الدولار أو اليورو) وجميع بطاقات الائتمان الرئيسية.'],
        ['Payment', 'العربية' => 'الدفع', 'Why does a trip show "Price on request"?', 'Some prices depend on the season and group size. Book as usual and we will confirm the exact price with you before you travel.',
            'لماذا تظهر عبارة "السعر عند الطلب" على بعض الرحلات؟', 'بعض الأسعار تعتمد على الموسم وحجم المجموعة. احجز كالمعتاد وسنؤكد معك السعر الدقيق قبل سفرك.'],
        ['Cancellations', 'العربية' => 'الإلغاء', 'Can I cancel my booking?', 'Yes, you can cancel for free from your voucher page up to 24 hours before your travel date.',
            'هل يمكنني إلغاء حجزي؟', 'نعم، يمكنك الإلغاء مجاناً من صفحة القسيمة حتى 24 ساعة قبل تاريخ السفر.'],
        ['Cancellations', 'العربية' => 'الإلغاء', 'What happens if the weather is bad?', 'If an activity is cancelled for safety reasons, we offer a new date or another experience at no extra cost.',
            'ماذا يحدث إذا كان الطقس سيئاً؟', 'إذا أُلغي نشاط لأسباب تتعلق بالسلامة، نقدم لك موعداً جديداً أو تجربة أخرى دون أي تكلفة إضافية.'],
    ];
    $tr = $pdo->prepare('INSERT INTO `' . $prefix . 'translations` (lang, type, item_id, field, value) VALUES (?, ?, ?, ?, ?)');
    foreach ($general as $i => $f) {
        $id = seed_insert($pdo, $prefix, 'faqs', ['tour_id' => null, 'category' => $f[0], 'question' => $f[1], 'answer' => $f[2], 'sort_order' => $i, 'status' => 'active', 'created_at' => $now]);
        foreach (['category' => $f['العربية'], 'question' => $f[3], 'answer' => $f[4]] as $field => $value) {
            $tr->execute(['ar', 'faq', $id, $field, $value]);
        }
    }
    $perTrip = [
        ['Is this trip suitable for children?', 'Yes. Children aged 2 to 11 get a reduced price, and the itinerary is relaxed enough for families.',
            'هل هذه الرحلة مناسبة للأطفال؟', 'نعم. يحصل الأطفال من 2 إلى 11 سنة على سعر مخفض، والبرنامج مريح ومناسب للعائلات.'],
        ['Are international flights included?', 'No, the price covers everything listed under "What\'s included". We are happy to help you find flights.',
            'هل تشمل الرحلة الطيران الدولي؟', 'لا، يشمل السعر كل ما هو مذكور في "ما يشمله السعر". ويسعدنا مساعدتك في إيجاد رحلات الطيران.'],
        ['Can the itinerary be customised?', 'Of course. Tell us your wishes in the booking notes or contact us on WhatsApp.',
            'هل يمكن تعديل برنامج الرحلة؟', 'بالتأكيد. أخبرنا برغباتك في ملاحظات الحجز أو تواصل معنا عبر واتساب.'],
    ];
    foreach (array_merge(array_slice($tripIds, 0, 3), array_slice($actIds, 0, 1)) as $tourId) {
        foreach ($perTrip as $i => $f) {
            $id = seed_insert($pdo, $prefix, 'faqs', ['tour_id' => $tourId, 'category' => '', 'question' => $f[0], 'answer' => $f[1], 'sort_order' => $i, 'status' => 'active', 'created_at' => $now]);
            $tr->execute(['ar', 'faq', $id, 'question', $f[2]]);
            $tr->execute(['ar', 'faq', $id, 'answer', $f[3]]);
        }
    }
}

/** Arabic version of the demo content, so the right-to-left website can be previewed right away. */
function seed_demo_arabic(PDO $pdo, string $prefix, array $cats, array $dests, array $tripIds, array $actIds, array $postIds, array $pageIds): void
{
    $rows = [];
    foreach (['beach' => 'شاطئ', 'adventure' => 'مغامرة', 'cultural' => 'ثقافي', 'luxury' => 'فاخر'] as $slug => $name) {
        $rows[] = ['category', $cats[$slug], 'name', $name];
    }
    $destAr = [
        'Bali' => ['بالي', 'إندونيسيا', 'معابد ومدرجات أرز وشواطئ لركوب الأمواج وشعاب نوسا بينيدا الفيروزية.'],
        'Switzerland' => ['سويسرا', 'سويسرا', 'أنهار جليدية وبحيرات جبلية وقرى ساحرة، أفضل طريقة لاستكشافها سيراً على الأقدام وبالقطار.'],
        'Egypt' => ['مصر', 'مصر', 'الأهرامات والفراعنة ونهر النيل الخالد، من القاهرة إلى الأقصر وأسوان.'],
        'Santorini' => ['سانتوريني', 'اليونان', 'منحدرات بيضاء وشواطئ بركانية وأشهر غروب للشمس في بحر إيجه.'],
        'Costa Rica' => ['كوستاريكا', 'كوستاريكا', 'غابات مطيرة وبراكين وحياة برية على سواحل الكاريبي والمحيط الهادئ.'],
        'Kyoto' => ['كيوتو', 'اليابان', 'معابد عريقة وبيوت شاي وغابات خيزران في قلب اليابان التقليدية.'],
        'Dubai' => ['دبي', 'الإمارات العربية المتحدة', 'رحلات سفاري صحراوية وناطحات سحاب قياسية ومنتجعات شاطئية فاخرة.'],
        'Chiang Mai' => ['شيانغ ماي', 'تايلاند', 'معابد جبلية وأسواق ليلية وأشهى أطعمة الشارع في شمال تايلاند.'],
        'Cappadocia' => ['كابادوكيا', 'تركيا', 'مداخن الجن وفنادق الكهوف وشروق لا يُنسى على متن المناطيد.'],
    ];
    foreach ($destAr as $en => [$name, $country, $short]) {
        if (isset($dests[$en])) {
            $rows[] = ['destination', $dests[$en], 'name', $name];
            $rows[] = ['destination', $dests[$en], 'country', $country];
            $rows[] = ['destination', $dests[$en], 'short_description', $short];
            $rows[] = ['destination', $dests[$en], 'description', $short . "\n\nتصفح رحلاتنا وأنشطتنا اليومية المختارة أدناه واحجز مكانك اليوم. لا حاجة للدفع عبر الإنترنت: الدفع عند الوصول."];
        }
    }
    $itinerary = "اليوم 1 - الوصول | انتقال خاص إلى الفندق وعشاء ترحيبي.\nاليوم 2 - أبرز المعالم | جولة كاملة بصحبة مرشد لأشهر معالم المنطقة.\nاليوم 3 - يوم حر | استرخِ أو اختر أحد أنشطتنا اليومية الاختيارية.\nاليوم الأخير - المغادرة | الإفطار وانتقال خاص إلى المطار.";
    $tripsAr = [
        ['جولة بالي الفاخرة بين المحيط والمعابد', 'بالي، إندونيسيا', '7 أيام / 6 ليالٍ', "منتجع شاطئي 5 نجوم\nإفطار وعشاء يومي\nانتقالات خاصة من وإلى المطار\nدخول غابة القرود في أوبود",
            'استمتع بسحر بالي مع إقامة فاخرة على المحيط وزيارات للمعابد بصحبة مرشد وقوارب سريعة خاصة إلى نوسا بينيدا وجلسات سبا بالية أصيلة.'],
        ['رحلة جبال الألب السويسرية والأنهار الجليدية', 'إنترلاكن، سويسرا', '6 أيام / 5 ليالٍ', "إقامة في نزل جبلي\nتذكرة القطارات السويسرية\nمرشد جبلي معتمد\nتذاكر التلفريك",
            'انغمس في مناظر جبلية خلابة وبحيرات ألبية صافية وقرى سويسرية تاريخية مع أفضل مسارات المشي بصحبة مرشدين.'],
        ['أهرامات مصر ورحلة نيلية', 'القاهرة والأقصر، مصر', '8 أيام / 7 ليالٍ', "رحلة نيلية 5 نجوم\nمرشد خاص متخصص في علم المصريات\nدخول الأهرامات والمقابر\nرحلات طيران داخلية",
            'اكتشف آلاف السنين من التاريخ مع جولات خاصة في أهرامات الجيزة وأبو الهول ووادي الملوك ورحلة فاخرة في نهر النيل.'],
        ['غروب سانتوريني وتذوق النبيذ', 'سانتوريني، اليونان', '5 أيام / 4 ليالٍ', "فيلا كهفية مطلة على الكالديرا\nرحلة كاتاماران عند الغروب\nجولة في الكروم البركانية\nإفطار يومي مفتوح",
            'استمتع بإطلالات بحر إيجه البيضاء والزرقاء الشهيرة وأجنحة فاخرة على المنحدرات ورحلات إبحار رومانسية عند الغروب.'],
        ['سفاري الغابات المطيرة والبراكين في كوستاريكا', 'أرينال، كوستاريكا', '6 أيام / 5 ليالٍ', "إقامة في نزل بيئي\nجولة الحبال المعلقة\nتذكرة الينابيع الحارة\nجولة ليلية في الغابة",
            'اكتشف التنوع البيولوجي الغني ورحلات السفاري والينابيع الحارة تحت بركان أرينال والتزلج على الحبال في غابات السحاب.'],
        ['معابد كيوتو العريقة وثقافة الشاي', 'كيوتو، اليابان', '7 أيام / 6 ليالٍ', "إقامة في نُزل ريوكان تقليدي\nدرس في حفل الشاي\nتذكرة القطار السريع\nتجربة ارتداء الكيمونو",
            'عُد بالزمن إلى العاصمة الثقافية لليابان واستمتع بمأكولات الكايسيكي الأصيلة وغابات الخيزران وحدائق الزن الهادئة.'],
    ];
    foreach ($tripsAr as $i => [$title, $dest, $duration, $inclusions, $short]) {
        if (isset($tripIds[$i])) {
            foreach (['title' => $title, 'destination' => $dest, 'duration' => $duration, 'inclusions' => $inclusions, 'short_description' => $short,
                'description' => $short . "\n\nيهتم خبراؤنا المحليون بكل التفاصيل لتستمتع برحلتك. احجز اليوم دون أي دفعة مقدمة وادفع مباشرة لمرشدك عند الوصول.", 'itinerary' => $itinerary] as $field => $value) {
                $rows[] = ['tour', $tripIds[$i], $field, $value];
            }
        }
    }
    $actsAr = [
        ['الغوص في الشعاب المرجانية في نوسا بينيدا', 'بالي، إندونيسيا', '5 ساعات', 'اغطس في المياه الفيروزية مع أسماك المانتا والسلاحف البحرية والشعاب المرجانية الملونة بصحبة مدربين معتمدين.'],
        ['سفاري الكثبان في صحراء دبي وعشاء شواء', 'دبي، الإمارات', '6 ساعات', 'تطعيس مثير بسيارات الدفع الرباعي وركوب الجمال وتصوير الغروب وعشاء بدوي تقليدي مع عرض تحت النجوم.'],
        ['درس طبخ تايلاندي وجولة في السوق', 'شيانغ ماي، تايلاند', '4 ساعات', 'زر الأسواق المحلية العضوية وتعلّم طهي 5 أطباق تايلاندية أصيلة مع طهاة محليين محترفين.'],
        ['رحلة منطاد عند الشروق في كابادوكيا', 'كابادوكيا، تركيا', '3 ساعات', 'حلّق فوق مداخن الجن والتكوينات الصخرية الفريدة عند شروق الشمس.'],
    ];
    foreach ($actsAr as $i => [$title, $dest, $duration, $short]) {
        if (isset($actIds[$i])) {
            foreach (['title' => $title, 'destination' => $dest, 'duration' => $duration, 'short_description' => $short, 'description' => $short,
                'inclusions' => "التوصيل من وإلى الفندق\nمرشد محلي محترف\nجميع المعدات\nمياه معبأة"] as $field => $value) {
                $rows[] = ['tour', $actIds[$i], $field, $value];
            }
        }
    }
    $postsAr = [
        ['10 نصائح أساسية للمسافرين بمفردهم لأول مرة في جنوب شرق آسيا', 'نصائح السفر', '6 دقائق قراءة', 'السفر منفرداً في جنوب شرق آسيا من أكثر المغامرات متعة. من البقاء على اتصال بشرائح eSIM المحلية إلى حجز جولات بالدفع عند الوصول، إليك كيف تبقى آمناً وتستفيد من رحلتك إلى أقصى حد.'],
        ['لماذا يعد الخريف أفضل موسم لزيارة جبال الألب السويسرية', 'وجهات', '4 دقائق قراءة', 'زحام أقل وهواء جبلي منعش وأشجار ذهبية تحوّل الوديان إلى جنة متوهجة. إليك لماذا يمنحك التخطيط لرحلتك في سبتمبر وأكتوبر أفضل قيمة ومناظر.'],
        ['كيف تحجز تجارب محلية أصيلة دون الدفع المسبق عبر الإنترنت', 'حجز ذكي', '5 دقائق قراءة', 'يفضل كثير من المسافرين المرونة عند التخطيط لرحلاتهم. تعرّف كيف يتيح لك نظام القسائم الفورية حجز الغوص ورحلات السفاري وجولات الطعام دون أي دفعات مسبقة.'],
    ];
    foreach ($postsAr as $i => [$title, $tag, $read, $excerpt]) {
        if (isset($postIds[$i])) {
            foreach (['title' => $title, 'tag' => $tag, 'read_time' => $read, 'excerpt' => $excerpt,
                'content' => $excerpt . "\n\nالتخطيط المسبق هو سر الرحلة الخالية من التوتر. ابحث عن الموسم المناسب واحزم أمتعة خفيفة واحتفظ بنسخ رقمية من مستنداتك.\n\nوالأهم من ذلك، اترك مجالاً للعفوية، فأجمل ذكريات السفر تحدث عندما تتبع توصية محلية أو تقبل دعوة غير متوقعة."] as $field => $value) {
                $rows[] = ['post', $postIds[$i], $field, $value];
            }
        }
    }
    $pagesAr = [
        ['من نحن', "نحن فريق من المسافرين الشغوفين والمرشدين المحليين نصنع رحلات لا تُنسى حول العالم.\n\nكل برنامج مصمم بعناية، وكل مرشد معتمد، وكل حجز مجاني: تدفع فقط عند وصولك."],
        ['شروط الحجز', "1. الحجز مجاني ولا يتم أي دفع عبر الإنترنت.\n\n2. يتم الدفع عند الوصول نقداً أو بالبطاقة مباشرة لمرشدك.\n\n3. يمكنك الإلغاء مجاناً عبر الإنترنت حتى 24 ساعة قبل تاريخ السفر.\n\n4. الأسعار للشخص الواحد ما لم يُذكر خلاف ذلك."],
        ['سياسة الخصوصية', "نجمع فقط المعلومات اللازمة لإدارة حجزك (الاسم والبريد الإلكتروني والهاتف وتفاصيل السفر). لا نبيع بياناتك لأي طرف ثالث أبداً.\n\nتواصل معنا في أي وقت لطلب حذف معلوماتك الشخصية."],
    ];
    foreach ($pagesAr as $i => [$title, $content]) {
        if (isset($pageIds[$i])) {
            $rows[] = ['page', $pageIds[$i], 'title', $title];
            $rows[] = ['page', $pageIds[$i], 'content', $content];
        }
    }
    foreach (['contact_address' => '100 طريق المغامرة، جناح 400'] as $key => $value) {
        $rows[] = ['setting', 0, $key, $value];
    }
    $st = $pdo->prepare('INSERT INTO `' . $prefix . 'translations` (lang, type, item_id, field, value) VALUES (?, ?, ?, ?, ?)');
    foreach ($rows as [$type, $id, $field, $value]) {
        $st->execute(['ar', $type, $id, $field, $value]);
    }
    // Show the Arabic version in the demo so the language menu is visible.
    $pdo->prepare('UPDATE `' . $prefix . 'settings` SET value = ? WHERE name = ?')->execute([json_encode([
        ['code' => 'en', 'name' => 'English', 'dir' => 'ltr', 'enabled' => true],
        ['code' => 'ar', 'name' => 'العربية', 'dir' => 'rtl', 'enabled' => true],
    ], JSON_UNESCAPED_UNICODE), 'languages']);
}

function slug_for_seed(string $s): string
{
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');
}
