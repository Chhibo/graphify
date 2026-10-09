<?php
/**
 * Default settings and demo content inserted by the installer.
 * Functions here only use a raw PDO handle because config.php does not exist yet during install.
 */

function default_settings(array $store): array
{
    return [
        // General
        'store_name' => $store['store_name'],
        'store_email' => $store['store_email'],
        'store_phone' => $store['whatsapp'],
        'store_address' => '',
        'store_tagline' => 'We have clothes that suits your style and which you\'re proud to wear. From women to men.',
        'logo' => '',
        'currency_code' => $store['currency_code'],
        'currency_symbol' => $store['currency_symbol'],
        'currency_position' => 'before',
        'currency_decimals' => '2',
        'hide_zero_decimals' => '1',
        'shipping_fee' => '0',
        'free_shipping_over' => '0',
        'order_prefix' => 'VL',
        'country_default' => '',

        // Top bar & hero
        'announcement_enabled' => '1',
        'announcement_text' => 'Sign up and get 20% off to your first order.',
        'announcement_link_text' => 'Sign Up Now',
        'hero_title' => 'FIND CLOTHES THAT MATCHES YOUR STYLE',
        'hero_text' => 'Browse through our diverse range of meticulously crafted garments, designed to bring out your individuality and cater to your sense of style.',
        'hero_button' => 'Shop Now',
        'hero_image' => 'assets/img/demo/hero.svg',
        'stat1_value' => '200+', 'stat1_label' => 'International Brands',
        'stat2_value' => '2,000+', 'stat2_label' => 'High-Quality Products',
        'stat3_value' => '30,000+', 'stat3_label' => 'Happy Customers',

        // Home sections
        'flash_enabled' => '1',
        'flash_badge' => 'Flash Sale',
        'flash_title' => 'Up to 50% Off Limited Time Only',
        'flash_text' => 'Don\'t miss out on our biggest sale of the season. Grab your favorites before they\'re gone!',
        'flash_ends_at' => date('Y-m-d\TH:i', strtotime('+3 days')),
        'banner_enabled' => '1',
        'banner_badge' => 'New Collection',
        'banner_title' => 'Elevate Your Wardrobe',
        'banner_text' => 'Discover premium essentials designed for the modern lifestyle.',
        'banner_button' => 'Explore Collection',
        'banner_image' => 'assets/img/demo/banner.svg',
        'instagram_handle' => '@velora',
        'instagram_url' => 'https://instagram.com/',
        'newsletter_title' => 'STAY UP TO DATE ABOUT OUR LATEST OFFERS',

        // Social
        'social_facebook' => '#', 'social_twitter' => '#', 'social_instagram' => '#', 'social_github' => '',

        // Payments
        'pay_cod_enabled' => '1',
        'pay_cod_title' => 'Cash on Delivery',
        'pay_cod_text' => 'Pay with cash when your order is delivered.',
        'pay_paypal_enabled' => '0',
        'pay_paypal_title' => 'PayPal',
        'paypal_mode' => 'sandbox',
        'paypal_client_id' => '',
        'paypal_secret' => '',
        'pay_stripe_enabled' => '0',
        'pay_stripe_title' => 'Credit / Debit Card',
        'stripe_publishable_key' => '',
        'stripe_secret_key' => '',

        // WhatsApp
        'whatsapp_number' => $store['whatsapp'],
        'whatsapp_mode' => 'link',
        'whatsapp_float' => '1',
        'whatsapp_notify_online' => '0',
        'callmebot_apikey' => '',
        'wa_cloud_token' => '',
        'wa_cloud_phone_id' => '',
    ];
}

function seed_demo(PDO $pdo): void
{
    $now = date('Y-m-d H:i:s');

    $cats = [
        ['Casual', 'casual', 'assets/img/demo/cat-casual.svg', 1],
        ['Formal', 'formal', 'assets/img/demo/cat-formal.svg', 2],
        ['Party', 'party', 'assets/img/demo/cat-party.svg', 3],
        ['Gym', 'gym', 'assets/img/demo/cat-gym.svg', 4],
    ];
    $catIds = [];
    $st = $pdo->prepare('INSERT INTO categories (name, slug, image, sort_order) VALUES (?,?,?,?)');
    foreach ($cats as $c) {
        $st->execute($c);
        $catIds[$c[1]] = (int) $pdo->lastInsertId();
    }

    $desc = 'This piece is perfect for any occasion. Crafted from a soft and breathable fabric, it offers superior comfort and style. Easy to match with jeans, shorts or chinos for a look you will love.';
    // name, brand, category, price, old price, image, sizes, colors, rating, reviews, new, trending, flash
    $products = [
        ['T-shirt with Tape Details', 'H&M', 'casual', 120, 0, 'p1', 'S,M,L,XL', 'White,Black,Olive', 4.5, 128, 1, 1, 1],
        ['Skinny Fit Jeans', 'Zara', 'casual', 240, 260, 'p2', '28,30,32,34,36', 'Black,Grey', 3.5, 84, 1, 1, 1],
        ['Checkered Shirt', 'Gucci', 'formal', 180, 0, 'p3', 'S,M,L,XL', 'Blue,Red', 4.5, 63, 1, 1, 1],
        ['Sleeve Striped T-shirt', 'Prada', 'casual', 130, 160, 'p4', 'S,M,L,XL', 'Beige,Blue', 4.5, 97, 1, 1, 1],
        ['Vertical Striped Shirt', 'Calvin Klein', 'formal', 212, 232, 'p5', 'S,M,L,XL', 'Light Blue,White', 5.0, 45, 0, 1, 0],
        ['Courage Graphic T-shirt', 'Nike', 'casual', 145, 0, 'p6', 'S,M,L,XL', 'White,Black', 4.0, 71, 0, 1, 0],
        ['Loose Fit Bermuda Shorts', 'Adidas', 'gym', 80, 0, 'p7', 'S,M,L,XL', 'Navy,Grey', 3.0, 38, 0, 0, 0],
        ['Faded Skinny Jeans', 'Zara', 'casual', 210, 0, 'p8', '28,30,32,34', 'Light Blue', 4.5, 52, 0, 0, 0],
        ['Polo with Contrast Trims', 'Calvin Klein', 'formal', 212, 242, 'p9', 'S,M,L,XL', 'Green,White', 4.0, 66, 0, 0, 0],
        ['Gradient Graphic T-shirt', 'H&M', 'party', 145, 0, 'p10', 'S,M,L,XL', 'Orange,Pink', 3.5, 29, 0, 0, 0],
        ['Polo with Tipping Details', 'Gucci', 'party', 180, 0, 'p11', 'S,M,L,XL', 'Purple,Black', 4.5, 41, 0, 0, 0],
        ['Classic Two-Piece Suit', 'Versace', 'formal', 390, 450, 'p12', '46,48,50,52', 'Navy,Black', 5.0, 18, 0, 0, 0],
    ];
    $st = $pdo->prepare('INSERT INTO products (category_id, name, slug, brand, description, price, old_price, image, gallery, sizes, colors, rating, reviews_count, stock, is_new, is_trending, is_flash, active, sort_order, created_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?)');
    foreach ($products as $i => $p) {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $p[0]), '-'));
        $st->execute([
            $catIds[$p[2]], $p[0], $slug, $p[1], $desc, $p[3], $p[4],
            'assets/img/demo/' . $p[5] . '.svg', '[]', $p[6], $p[7], $p[8], $p[9], -1,
            $p[10], $p[11], $p[12], $i, $now,
        ]);
    }

    $testimonials = [
        ['Sarah M.', 'I\'m blown away by the quality and style of the clothes I received from this store. From casual wear to elegant dresses, every piece I\'ve bought has exceeded my expectations.'],
        ['Alex K.', 'Finding clothes that align with my personal style used to be a challenge until I discovered this store. The range of options they offer is truly remarkable, catering to a variety of tastes and occasions.'],
        ['James L.', 'As someone who\'s always on the lookout for unique fashion pieces, I\'m thrilled to have stumbled upon this store. The selection of clothes is not only diverse but also on-point with the latest trends.'],
        ['Mooen', 'The checkout was quick, delivery was on time and the fabric feels amazing. Customer service was also very helpful when I needed to change my size.'],
    ];
    $st = $pdo->prepare('INSERT INTO testimonials (name, text, rating, active, sort_order) VALUES (?,?,5,1,?)');
    foreach ($testimonials as $i => $t) {
        $st->execute([$t[0], $t[1], $i]);
    }

    $pages = [
        ['page', 'About', 'about', '<p>We are a fashion store bringing you carefully selected clothes that suit your style and that you are proud to wear.</p>'],
        ['page', 'Contact', 'contact', '<p>Have a question? Contact us by WhatsApp or email and we will be happy to help you.</p>'],
        ['page', 'FAQ', 'faq', '<h3>How long does delivery take?</h3><p>Orders are usually delivered within 2-5 working days.</p><h3>Can I pay on delivery?</h3><p>Yes, choose Cash on Delivery at checkout when it is available.</p>'],
        ['page', 'Customer Support', 'customer-support', '<p>Our support team is available every day. Reach us on WhatsApp for the fastest answer.</p>'],
        ['page', 'Delivery Details', 'delivery-details', '<p>We deliver to your door. Shipping fees, if any, are shown at checkout before you place your order.</p>'],
        ['page', 'Terms & Conditions', 'terms', '<p>By placing an order you agree to our terms of sale. Please edit this page from the admin panel.</p>'],
        ['page', 'Privacy Policy', 'privacy', '<p>We only use your personal details to process and deliver your orders. Please edit this page from the admin panel.</p>'],
        ['post', 'How to Build a Capsule Wardrobe', 'capsule-wardrobe', '<p>A capsule wardrobe is a small collection of versatile pieces that work together. Start with neutral basics: a white tee, dark jeans, a checkered shirt and a classic jacket.</p>'],
        ['post', '5 Ways to Style a Checkered Shirt', 'style-checkered-shirt', '<p>The checkered shirt is a timeless staple. Wear it buttoned with chinos for the office, open over a tee for the weekend, or tied at the waist for a relaxed look.</p>'],
    ];
    $st = $pdo->prepare('INSERT INTO pages (type, title, slug, content, image, active, created_at) VALUES (?,?,?,?,?,1,?)');
    foreach ($pages as $p) {
        $st->execute([$p[0], $p[1], $p[2], $p[3], $p[0] === 'post' ? 'assets/img/demo/banner.svg' : '', $now]);
    }
}
