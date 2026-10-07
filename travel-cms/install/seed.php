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
        'db_version' => '2',
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
    foreach ($trips as $i => $t) {
        seed_insert($pdo, $prefix, 'tours', [
            'type' => 'trip', 'title' => $t[0], 'slug' => slug_for_seed($t[0]), 'category_id' => $cats[$t[1]],
            'destination' => $t[2], 'price' => $t[3], 'duration' => $t[4], 'max_guests' => 12, 'icon' => 'fa-plane',
            'image' => $u . $t[5] . '?auto=format&fit=crop&w=1200&q=80',
            'short_description' => $t[9],
            'description' => $t[9] . "\n\nOur local experts take care of every detail so you can simply enjoy the journey. Reserve today with zero upfront payment and settle the balance directly with your guide on arrival.",
            'inclusions' => $t[8],
            'itinerary' => "Day 1 - Arrival | Private transfer to your hotel and welcome dinner.\nDay 2 - Guided highlights | Full-day guided tour of the region's most iconic sights.\nDay 3 - Free day | Relax or choose one of our optional day activities.\nFinal day - Departure | Breakfast and private transfer to the airport.",
            'rating' => $t[6], 'reviews_count' => $t[7], 'is_featured' => 1, 'status' => 'active', 'sort_order' => $i,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    $acts = [
        ['Nusa Penida Coral Reef Scuba Diving', 'Bali, Indonesia', 120, '5 Hours', 'fa-water', 'photo-1544551763-46a013bb70d5', 'Dive into turquoise waters with manta rays, sea turtles, and vibrant coral reefs alongside certified PADI instructors.', 'adventure'],
        ['Dubai Desert Dune Bashing & BBQ Night', 'Dubai, UAE', 85, '6 Hours', 'fa-sun', 'photo-1451187580459-43490279c0fa', 'Thrilling 4x4 dune bashing, camel riding, sunset photo stops, and a traditional Bedouin dinner show under the stars.', 'adventure'],
        ['Traditional Thai Cooking Class & Market Tour', 'Chiang Mai, Thailand', 55, '4 Hours', 'fa-utensils', 'photo-1556910103-1c02745aae4d', 'Visit local organic markets and learn to cook 5 authentic Thai dishes from scratch with local master chefs.', 'cultural'],
        ['Cappadocia Sunrise Hot Air Balloon', 'Cappadocia, Turkey', 190, '3 Hours', 'fa-cloud-sun', 'photo-1507608616759-54f48f0af0ee', 'Float gracefully over fairytale chimneys and unique rock formations while sipping champagne at sunrise.', 'luxury'],
    ];
    foreach ($acts as $i => $a) {
        seed_insert($pdo, $prefix, 'tours', [
            'type' => 'activity', 'title' => $a[0], 'slug' => slug_for_seed($a[0]), 'category_id' => $cats[$a[7]],
            'destination' => $a[1], 'price' => $a[2], 'duration' => $a[3], 'max_guests' => 10, 'icon' => $a[4],
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
    foreach ($posts as $p) {
        seed_insert($pdo, $prefix, 'posts', [
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
    foreach ($reviews as $r) {
        seed_insert($pdo, $prefix, 'reviews', [
            'name' => $r[0], 'trip' => $r[1], 'rating' => 5, 'content' => $r[2],
            'avatar' => $u . $r[3] . '?auto=format&fit=crop&w=150&q=80', 'status' => 'approved',
            'created_at' => date('Y-m-d H:i:s', strtotime($r[4])),
        ]);
    }

    $pages = [
        ['About Us', "We are a team of passionate travelers and local guides creating unforgettable journeys around the world.\n\nEvery itinerary is handcrafted, every guide is verified, and every booking is free to reserve: you only pay when you arrive."],
        ['Booking Terms', "1. Reservations are free. No payment is taken online.\n\n2. Payment is due on arrival, in cash or by card, directly to your guide.\n\n3. You can cancel online for free up to 24 hours before your travel date.\n\n4. Prices are per person unless stated otherwise."],
        ['Privacy Policy', "We only collect the information needed to manage your booking (name, email, phone and travel details). We never sell your data to third parties.\n\nContact us at any time to request deletion of your personal information."],
    ];
    foreach ($pages as $p) {
        seed_insert($pdo, $prefix, 'pages', [
            'title' => $p[0], 'slug' => slug_for_seed($p[0]), 'content' => $p[1], 'show_in_footer' => 1,
            'status' => 'published', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
    $pdo->prepare('INSERT INTO `' . $prefix . 'settings` (name, value) VALUES (?, ?)')->execute(['terms_page', 'booking-terms']);
}

function slug_for_seed(string $s): string
{
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');
}
