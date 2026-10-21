<?php
// Demo content: sample categories, products and pages so a new store
// doesn't look empty. Everything added is remembered so it can be removed
// in one click (Admin -> Settings -> Demo content).
declare(strict_types=1);

function demo_installed(): bool
{
    return setting('demo_data') !== '';
}

function demo_products(): array
{
    $desc = function (string $intro, array $bullets, string $outro): string {
        return '<p>' . $intro . "</p>\n<ul>\n<li>" . implode("</li>\n<li>", $bullets) . "</li>\n</ul>\n<p>" . $outro . '</p>';
    };
    // [cover image, category slug, title, badge, featured, description]
    return [
        ['tpl-social-planner', 'templates', 'Ultimate Social Media Content Planner', 'BESTSELLER', 1,
            $desc('Plan a whole month of posts in one afternoon. A clean, ready-to-use planner for Instagram, TikTok, Facebook and LinkedIn.',
                ['12 monthly calendars + weekly planning pages', '100 content ideas and caption starters', 'Hashtag tracker and analytics log', 'Works in Notion, Google Sheets and as a printable PDF'],
                'Perfect for creators, small businesses and social media managers.')],
        ['preset-golden-hour', 'presets', 'Golden Hour Lightroom Presets', 'HOT', 1,
            $desc('Warm, glowing tones that make every photo look like it was shot at sunset.',
                ['15 presets for Lightroom Desktop and Mobile', 'Works on portraits, travel and lifestyle photos', 'Includes a quick install guide (DNG + XMP files)'],
                'One click, and your feed looks consistent and professional.')],
        ['ebook-passive-income', 'ebooks', 'The Beginner\'s Guide to Passive Income', 'NEW', 1,
            $desc('A practical, no-hype guide to building your first income streams that keep paying you.',
                ['68 pages, easy to read in a weekend', '12 passive income ideas with real examples', 'Step-by-step plan for your first 90 days', 'Bonus: income tracker spreadsheet'],
                'Written for complete beginners. No experience needed.')],
        ['gfx-story-templates', 'graphics', 'Instagram Story Templates Bundle', 'NEW', 0,
            $desc('Eye-catching story templates you can edit in minutes.',
                ['60 editable story templates', 'Canva and Photoshop versions included', 'Matching highlight covers'],
                'Change colors, fonts and photos to match your brand.')],
        ['preset-cinematic-luts', 'presets', 'Cinematic LUTs for Video Creators', '', 0,
            $desc('Give your videos a film look in seconds.',
                ['20 .cube LUTs', 'Premiere Pro, Final Cut, DaVinci Resolve and CapCut', 'Works with phone and camera footage'],
                'Great for YouTube, Reels and travel films.')],
        ['tpl-resume', 'templates', 'Minimal Resume & Cover Letter Kit', '', 0,
            $desc('Stand out with a clean, modern resume that recruiters can read in seconds.',
                ['3 resume layouts + matching cover letter', 'Microsoft Word, Google Docs and Pages', 'A4 and US Letter sizes', 'Writing tips for every section'],
                'Just add your details and export to PDF.')],
        ['gfx-icon-set', 'graphics', 'Hand-Drawn Icon Set: 200+ Icons', 'UPDATED', 0,
            $desc('Friendly, hand-drawn icons for websites, presentations and social posts.',
                ['200+ icons in SVG and PNG', 'Easy to recolor', 'Business, tech, lifestyle and nature themes'],
                'Free for personal and commercial projects.')],
        ['ebook-productivity', 'ebooks', 'Productivity Playbook: 30-Day Reset', '', 0,
            $desc('Get more done in less time with a simple daily system.',
                ['30 short daily lessons', 'Printable habit tracker and weekly review', 'Focus techniques that actually work'],
                'Build habits that stick in just one month.')],
        ['tpl-invoice', 'templates', 'Freelancer Invoice & Contract Pack', '', 0,
            $desc('Look professional and get paid on time.',
                ['5 invoice designs', 'Simple freelance contract template', 'Quote and receipt templates', 'Excel, Google Sheets and Word'],
                'Everything a freelancer needs to bill clients.')],
        ['preset-moody-film', 'presets', 'Moody Film Presets Collection', '', 0,
            $desc('Rich, moody tones inspired by classic film stock.',
                ['12 presets for Lightroom', 'Desktop and mobile versions', 'Ideal for street, urban and night photos'],
                'Add atmosphere to any photo.')],
        ['ebook-freelancing', 'ebooks', 'Freelancing 101: Land Your First Client', '', 0,
            $desc('Go from zero to your first paying client.',
                ['Choosing a service people pay for', 'Pricing, proposals and follow-ups', 'Email templates you can copy'],
                'A short, practical guide for new freelancers.')],
        ['gfx-gradients', 'graphics', 'Abstract Gradient Backgrounds', '', 0,
            $desc('Beautiful high-resolution gradient backgrounds.',
                ['50 backgrounds in 4K', 'Use for wallpapers, slides and social posts', 'JPG and PNG formats'],
                'Instantly make any design look modern.')],
    ];
}

function install_demo_data(): void
{
    if (demo_installed()) {
        return;
    }
    $made = ['categories' => [], 'products' => [], 'pages' => []];
    $pdo = db();
    $pdo->beginTransaction();

    $catIds = [];
    $order = 1;
    foreach (['ebooks' => 'Ebooks', 'templates' => 'Templates', 'presets' => 'Presets', 'graphics' => 'Graphics'] as $slug => $name) {
        $q = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
        $q->execute([$slug]);
        if ($id = $q->fetchColumn()) {
            $catIds[$slug] = (int)$id; // keep the owner's own category
        } else {
            $pdo->prepare('INSERT INTO categories (name, slug, sort_order, show_on_home, show_in_header) VALUES (?, ?, ?, 1, 1)')
                ->execute([$name, $slug, $order]);
            $catIds[$slug] = $made['categories'][] = (int)$pdo->lastInsertId();
        }
        $order++;
    }

    $products = array_reverse(demo_products()); // newest first on the home page
    $now = time();
    $insert = $pdo->prepare('INSERT INTO products (title, description, image, file_name, file_label, file_url, active, category_id,
        featured, badge, downloads, views, created_at) VALUES (?, ?, ?, ?, ?, \'\', 1, ?, ?, ?, ?, ?, ?)');
    foreach ($products as $i => [$cover, $cat, $title, $badge, $featured, $description]) {
        $image = '';
        $src = APP_ROOT . '/assets/demo/' . $cover . '.jpg';
        if (is_file($src)) {
            $image = 'demo-' . random_token(6) . '.jpg';
            copy($src, UPLOADS_DIR . '/' . $image);
        }
        $file = random_token(16);
        file_put_contents(FILES_DIR . '/' . $file, "This is a demo download from your new store.\r\n\r\n"
            . "Replace this product with your real one in Admin -> Products, or remove all demo content in Admin -> Settings.\r\n");
        $downloads = 120 + crc32($title) % 2400;
        $insert->execute([$title, $description, $image, $file, slugify($title) . '.txt', $catIds[$cat], $featured, $badge,
            $downloads, $downloads * 3 + 40, $now - (count($products) - $i) * 86400 * 2]);
        $made['products'][] = (int)$pdo->lastInsertId();
    }

    $pages = [
        ['About Us', 'about-us', "<p>Welcome to " . e(setting('store_name')) . "! We collect and create useful digital products (ebooks, templates, presets and graphics) and share them for free.</p>\n<h2>How it works</h2>\n<p>Pick any product, complete one short offer from our partners, and your download unlocks right away. The offers keep this site running and the downloads free.</p>\n<h2>Questions?</h2>\n<p>We're happy to help. Send us a message on the <a href=\"contact.php\">contact page</a>.</p>", 0],
        ['FAQ', 'faq', "<h2>Are the downloads really free?</h2>\n<p>Yes. You don't pay us anything. You unlock a download by completing one offer from our advertising partners.</p>\n<h2>What kind of offers are there?</h2>\n<p>Usually short surveys, app installs or sign-ups. Pick the one you like and read its terms before you start.</p>\n<h2>I completed an offer but the download didn't unlock.</h2>\n<p>Some offers take a few minutes to confirm. Keep the page open. If it still doesn't unlock after 15 minutes, try another offer or <a href=\"contact.php\">contact us</a>.</p>\n<h2>How long does my download link work?</h2>\n<p>Your link stays valid for " . (int)setting('download_hours', '24') . " hours after unlocking.</p>", 1],
    ];
    foreach ($pages as [$title, $slug, $content, $header]) {
        $q = $pdo->prepare('SELECT 1 FROM pages WHERE slug = ?');
        $q->execute([$slug]);
        if ($q->fetchColumn()) {
            continue;
        }
        $pdo->prepare('INSERT INTO pages (title, slug, content, sort_order, show_in_header, show_in_footer, updated_at) VALUES (?, ?, ?, 0, ?, 1, ?)')
            ->execute([$title, $slug, $content, $header, $now]);
        $made['pages'][] = (int)$pdo->lastInsertId();
    }

    $pdo->commit();
    save_setting('demo_data', json_encode($made));
}

function remove_demo_data(): void
{
    $made = json_decode(setting('demo_data'), true);
    if (!is_array($made)) {
        save_setting('demo_data', '');
        return;
    }
    $pdo = db();
    foreach ($made['products'] ?? [] as $id) {
        $q = $pdo->prepare('SELECT image, file_name FROM products WHERE id = ?');
        $q->execute([$id]);
        if ($p = $q->fetch()) {
            if ($p['image']) @unlink(UPLOADS_DIR . '/' . basename($p['image']));
            if ($p['file_name']) @unlink(FILES_DIR . '/' . basename($p['file_name']));
            $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        }
    }
    foreach ($made['pages'] ?? [] as $id) {
        $pdo->prepare('DELETE FROM pages WHERE id = ?')->execute([$id]);
    }
    // Keep a demo category if the owner has since put their own products in it.
    foreach ($made['categories'] ?? [] as $id) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $q->execute([$id]);
        if (!(int)$q->fetchColumn()) {
            $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        }
    }
    save_setting('demo_data', '');
}
