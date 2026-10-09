<?php
/**
 * Starter content used by the installer.
 */
declare(strict_types=1);

function install_default_categories(PDO $pdo): array
{
    $cats = [
        ['Vehicles', 'car', ['Cars', 'Motorcycles', 'Trucks & Vans', 'Parts & Accessories']],
        ['Property', 'building', ['Apartments for Rent', 'Houses for Sale', 'Land', 'Commercial']],
        ['Mobiles', 'smartphone', ['Phones', 'Tablets', 'Accessories']],
        ['Electronics', 'laptop', ['Computers', 'TV & Audio', 'Cameras', 'Gaming']],
        ['Home & Furniture', 'sofa', ['Furniture', 'Appliances', 'Decor & Garden']],
        ['Fashion', 'shirt', ['Men', 'Women', 'Watches & Jewellery']],
        ['Jobs', 'briefcase', ['Full-time', 'Part-time', 'Freelance']],
        ['Services', 'wrench', ['Repairs', 'Lessons', 'Events', 'Moving']],
        ['Pets', 'paw', ['Dogs', 'Cats', 'Pet Supplies']],
        ['Sports & Hobbies', 'bike', ['Bicycles', 'Fitness', 'Musical Instruments']],
        ['Books & Media', 'book', []],
        ['Kids & Baby', 'baby', []],
    ];
    $ids = [];
    $ins = $pdo->prepare('INSERT INTO categories (parent_id, name, slug, icon, description, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $order = 0;
    foreach ($cats as [$name, $icon, $children]) {
        $ins->execute([0, $name, slugify($name), $icon, '', $order++, now()]);
        $pid = (int) $pdo->lastInsertId();
        $ids[$name] = $pid;
        $sub = 0;
        foreach ($children as $child) {
            $ins->execute([$pid, $child, slugify($child), $icon, '', $sub++, now()]);
            $ids[$child] = (int) $pdo->lastInsertId();
        }
    }
    return $ids;
}

function install_default_pages(PDO $pdo): void
{
    $site = setting('site_name');
    $pages = [
        ['About us', 'about', 1, "<p>$site is a free, friendly marketplace that helps people buy and sell locally. Whether you are clearing out the garage, looking for your next car or hiring help, you can post an ad in minutes and connect directly with people near you.</p><h2>Why people love us</h2><ul><li>Posting ads is free and fast</li><li>Chat with buyers and sellers securely</li><li>Verified sellers and community reviews</li></ul>"],
        ['Safety tips', 'safety', 0, '<p>Most people are honest, but it pays to be careful.</p><ul><li>Meet in a busy public place and bring a friend if you can.</li><li>Inspect the item before paying.</li><li>Never send money in advance or share banking passwords.</li><li>If a deal looks too good to be true, it probably is.</li><li>Use the <strong>Report</strong> button on any ad that looks suspicious.</li></ul>'],
        ['Terms & conditions', 'terms', 0, '<p>By using this website you agree to post only legal items, to describe them honestly, and to treat other members with respect. We may remove ads or accounts that break these rules.</p><p>Edit this page from <em>Admin → Pages</em>.</p>'],
        ['Privacy policy', 'privacy', 0, '<p>We only collect the information needed to run your account and your ads. We never sell your personal data. You can delete your account and all your ads at any time from your dashboard.</p><p>Edit this page from <em>Admin → Pages</em>.</p>'],
    ];
    $ins = $pdo->prepare('INSERT INTO pages (title, slug, content, in_header, in_footer, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?)');
    foreach ($pages as [$title, $slug, $header, $content]) {
        $ins->execute([$title, $slug, $content, $header, now(), now()]);
    }
}

function install_demo_content(PDO $pdo, array $cat): void
{
    $users = [
        ['Maya Thompson', 'maya', 'Brooklyn, NY', 1],
        ['Daniel Okafor', 'danielo', 'Austin, TX', 0],
        ['Sofia Rossi', 'sofiar', 'Chicago, IL', 1],
    ];
    $uids = [];
    $ins = $pdo->prepare('INSERT INTO users (name, username, email, password, phone, location, bio, role, status, verified, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($users as $i => [$name, $username, $loc, $verified]) {
        $ins->execute([$name, $username, $username . '@example.com', password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT),
            '+1 555 010' . $i, $loc, 'Demo seller account.', 'user', 'active', $verified, date('Y-m-d H:i:s', strtotime('-' . (90 + $i * 40) . ' days'))]);
        $uids[] = (int) $pdo->lastInsertId();
    }

    $items = [
        ['2019 Toyota Corolla LE – low mileage', 'Cars', 14500, 'negotiable', 'used', 'Austin, TX', 1, 1, 'One owner, full service history, 38k miles. New tyres fitted last spring, no accidents. Clean title in hand.'],
        ['iPhone 15 Pro 256GB – Natural Titanium', 'Phones', 820, 'fixed', 'like_new', 'Brooklyn, NY', 0, 1, 'Bought in March, always kept in a case with a screen protector. Battery health 98%. Comes with box and original cable.'],
        ['Sunny 2-bedroom apartment near the park', 'Apartments for Rent', 1850, 'fixed', '', 'Chicago, IL', 2, 1, 'Bright corner unit with hardwood floors, in-unit laundry and a balcony. Five minutes walk to the train. Available from next month.'],
        ['Mid-century walnut sofa', 'Furniture', 450, 'negotiable', 'used', 'Brooklyn, NY', 0, 0, 'Three-seater with original walnut legs and newly re-upholstered cushions. Pet-free and smoke-free home. Pick-up only.'],
        ['MacBook Air M2 13" – 16GB / 512GB', 'Computers', 950, 'fixed', 'like_new', 'Austin, TX', 1, 1, 'Midnight colour, 41 battery cycles. Includes the 35W dual charger. Perfect for students and remote work.'],
        ['Trek FX 3 hybrid bike', 'Bicycles', 520, 'negotiable', 'used', 'Chicago, IL', 2, 0, 'Size M carbon fork, hydraulic disc brakes. Recently tuned up. Lights and lock included.'],
        ['Professional home cleaning', 'Repairs', 0, 'contact', '', 'Brooklyn, NY', 0, 0, 'Reliable, insured cleaning service for apartments and houses. Weekly, bi-weekly or one-off deep cleans. Message me for a free quote.'],
        ['Golden Retriever puppies', 'Dogs', 900, 'fixed', '', 'Austin, TX', 1, 1, 'Healthy, playful puppies raised in a family home. Vaccinated, microchipped and vet checked. Ready for their new homes in two weeks.'],
        ['Sony A7 III camera body', 'Cameras', 1100, 'negotiable', 'used', 'Chicago, IL', 2, 0, 'Shutter count around 12k. Comes with two batteries, charger and strap. Sensor is spotless.'],
        ['Front-end developer (React) – remote', 'Freelance', 0, 'contact', '', 'Remote', 1, 0, 'Small product studio looking for a freelance React developer for a 3-month project. Send a short intro and portfolio link.'],
        ['Yamaha acoustic guitar F310', 'Musical Instruments', 120, 'fixed', 'used', 'Brooklyn, NY', 0, 0, 'Great beginner guitar, plays well and holds tune. Includes a soft gig bag and spare strings.'],
        ['Kids balance bike – free to good home', 'Kids & Baby', 0, 'free', 'used', 'Chicago, IL', 2, 0, 'My son has outgrown it. A few scratches but works perfectly. Collect any evening this week.'],
    ];
    $ins = $pdo->prepare('INSERT INTO listings (user_id, category_id, title, description, price, price_type, item_condition, location, phone, show_phone, tags, status, featured, views, created_at, updated_at, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($items as $i => [$title, $catName, $price, $ptype, $cond, $loc, $owner, $featured, $desc]) {
        $created = date('Y-m-d H:i:s', time() - ($i * 7 + 2) * 3600);
        $ins->execute([$uids[$owner], $cat[$catName] ?? 0, $title, $desc, $price, $ptype, $cond, $loc, '+1 555 010' . $owner,
            strtolower($catName), 'active', $featured, mt_rand(12, 400), $created, $created, new_expiry()]);
    }

    $rev = $pdo->prepare('INSERT INTO reviews (seller_id, reviewer_id, rating, comment, created_at) VALUES (?, ?, ?, ?, ?)');
    $rev->execute([$uids[0], $uids[1], 5, 'Item exactly as described and very friendly. Would buy again!', now()]);
    $rev->execute([$uids[1], $uids[2], 4, 'Quick replies and an easy pick-up.', now()]);
    $rev->execute([$uids[2], $uids[0], 5, 'Super honest seller, highly recommended.', now()]);
}
