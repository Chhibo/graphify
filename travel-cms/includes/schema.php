<?php
/**
 * Database schema (MySQL 5.7+ / MariaDB 10.3+), shared by the installer and the
 * automatic upgrader. To add a column or table in a future version, add it here
 * and bump SCHEMA_VERSION: existing sites are upgraded on their next page view.
 */

const SCHEMA_VERSION = 4;

function schema_tables(): array
{
    $id = ['id' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT'];
    $seo = [
        'seo_title' => "VARCHAR(200) NOT NULL DEFAULT ''",
        'meta_description' => "VARCHAR(320) NOT NULL DEFAULT ''",
    ];

    return [
        'users' => [
            'columns' => $id + [
                'name' => 'VARCHAR(120) NOT NULL',
                'email' => 'VARCHAR(190) NOT NULL',
                'password' => 'VARCHAR(255) NOT NULL',
                'role' => "VARCHAR(20) NOT NULL DEFAULT 'admin'",
                'last_login' => 'DATETIME NULL',
                'created_at' => 'DATETIME NOT NULL',
            ],
            'keys' => ['PRIMARY KEY (`id`)', 'UNIQUE KEY `uniq_email` (`email`)'],
        ],
        'settings' => [
            'columns' => [
                'name' => 'VARCHAR(100) NOT NULL',
                'value' => 'TEXT NULL',
            ],
            'keys' => ['PRIMARY KEY (`name`)'],
        ],
        'categories' => [
            'columns' => $id + [
                'name' => 'VARCHAR(100) NOT NULL',
                'slug' => 'VARCHAR(120) NOT NULL',
                'sort_order' => 'INT NOT NULL DEFAULT 0',
            ],
            'keys' => ['PRIMARY KEY (`id`)', 'UNIQUE KEY `uniq_slug` (`slug`)'],
        ],
        'destinations' => [
            'columns' => $id + [
                'name' => 'VARCHAR(150) NOT NULL',
                'slug' => 'VARCHAR(170) NOT NULL',
                'country' => "VARCHAR(100) NOT NULL DEFAULT ''",
                'image' => "VARCHAR(500) NOT NULL DEFAULT ''",
                'short_description' => "VARCHAR(500) NOT NULL DEFAULT ''",
                'description' => 'TEXT NULL',
                'is_featured' => 'TINYINT(1) NOT NULL DEFAULT 1',
                'status' => "VARCHAR(20) NOT NULL DEFAULT 'active'",
                'sort_order' => 'INT NOT NULL DEFAULT 0',
            ] + $seo + [
                'created_at' => 'DATETIME NOT NULL',
                'updated_at' => 'DATETIME NOT NULL',
            ],
            'keys' => ['PRIMARY KEY (`id`)', 'UNIQUE KEY `uniq_slug` (`slug`)'],
        ],
        // Trips (multi-day packages) and activities (day excursions) share one table.
        'tours' => [
            'columns' => $id + [
                'type' => "VARCHAR(20) NOT NULL DEFAULT 'trip'",
                'title' => 'VARCHAR(200) NOT NULL',
                'slug' => 'VARCHAR(220) NOT NULL',
                'category_id' => 'INT UNSIGNED NULL',
                'destination_id' => 'INT UNSIGNED NULL',
                'destination' => "VARCHAR(150) NOT NULL DEFAULT ''",
                'price' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
                'child_price' => 'DECIMAL(10,2) NULL',
                'infant_price' => 'DECIMAL(10,2) NULL',
                'allow_children' => 'TINYINT(1) NOT NULL DEFAULT 1',
                'allow_infants' => 'TINYINT(1) NOT NULL DEFAULT 1',
                'hide_price' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'duration' => "VARCHAR(80) NOT NULL DEFAULT ''",
                'max_guests' => 'INT NOT NULL DEFAULT 12',
                'icon' => "VARCHAR(60) NOT NULL DEFAULT 'fa-compass'",
                'image' => "VARCHAR(500) NOT NULL DEFAULT ''",
                'short_description' => "VARCHAR(500) NOT NULL DEFAULT ''",
                'description' => 'TEXT NULL',
                'inclusions' => 'TEXT NULL',
                'itinerary' => 'TEXT NULL',
                'rating' => 'DECIMAL(2,1) NOT NULL DEFAULT 5.0',
                'reviews_count' => 'INT NOT NULL DEFAULT 0',
                'is_featured' => 'TINYINT(1) NOT NULL DEFAULT 1',
                'status' => "VARCHAR(20) NOT NULL DEFAULT 'active'",
                'sort_order' => 'INT NOT NULL DEFAULT 0',
            ] + $seo + [
                'created_at' => 'DATETIME NOT NULL',
                'updated_at' => 'DATETIME NOT NULL',
            ],
            'keys' => [
                'PRIMARY KEY (`id`)', 'UNIQUE KEY `uniq_slug` (`slug`)',
                'KEY `idx_type_status` (`type`, `status`)', 'KEY `idx_category` (`category_id`)', 'KEY `idx_destination` (`destination_id`)',
            ],
        ],
        'tour_images' => [
            'columns' => $id + [
                'tour_id' => 'INT UNSIGNED NOT NULL',
                'image' => 'VARCHAR(500) NOT NULL',
                'caption' => "VARCHAR(200) NOT NULL DEFAULT ''",
                'sort_order' => 'INT NOT NULL DEFAULT 0',
            ],
            'keys' => ['PRIMARY KEY (`id`)', 'KEY `idx_tour` (`tour_id`)'],
        ],
        'bookings' => [
            'columns' => $id + [
                'reference' => 'VARCHAR(20) NOT NULL',
                'access_token' => 'VARCHAR(64) NOT NULL',
                'tour_id' => 'INT UNSIGNED NULL',
                'tour_title' => 'VARCHAR(200) NOT NULL',
                'travel_date' => 'DATE NOT NULL',
                'guests' => 'INT NOT NULL DEFAULT 1',
                'adults' => 'INT NOT NULL DEFAULT 1',
                'children' => 'INT NOT NULL DEFAULT 0',
                'infants' => 'INT NOT NULL DEFAULT 0',
                'unit_price' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
                'child_price' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
                'infant_price' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
                'total' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
                'price_on_request' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'lang' => "VARCHAR(10) NOT NULL DEFAULT ''",
                'request_id' => 'INT UNSIGNED NULL',
                'review_requested_at' => 'DATETIME NULL',
                'customer_name' => 'VARCHAR(150) NOT NULL',
                'customer_email' => 'VARCHAR(190) NOT NULL',
                'customer_phone' => 'VARCHAR(60) NOT NULL',
                'notes' => 'TEXT NULL',
                'status' => "VARCHAR(20) NOT NULL DEFAULT 'pending'",
                'admin_notes' => 'TEXT NULL',
                'ip_address' => 'VARCHAR(45) NULL',
                'created_at' => 'DATETIME NOT NULL',
                'updated_at' => 'DATETIME NOT NULL',
            ],
            'keys' => [
                'PRIMARY KEY (`id`)', 'UNIQUE KEY `uniq_reference` (`reference`)',
                'KEY `idx_status` (`status`)', 'KEY `idx_travel_date` (`travel_date`)', 'KEY `idx_email` (`customer_email`)',
            ],
        ],
        'posts' => [
            'columns' => $id + [
                'title' => 'VARCHAR(220) NOT NULL',
                'slug' => 'VARCHAR(240) NOT NULL',
                'tag' => "VARCHAR(60) NOT NULL DEFAULT ''",
                'author' => "VARCHAR(120) NOT NULL DEFAULT ''",
                'image' => "VARCHAR(500) NOT NULL DEFAULT ''",
                'excerpt' => 'TEXT NULL',
                'content' => 'MEDIUMTEXT NULL',
                'read_time' => "VARCHAR(30) NOT NULL DEFAULT ''",
                'status' => "VARCHAR(20) NOT NULL DEFAULT 'published'",
                'published_at' => 'DATETIME NOT NULL',
            ] + $seo + [
                'created_at' => 'DATETIME NOT NULL',
            ],
            'keys' => ['PRIMARY KEY (`id`)', 'UNIQUE KEY `uniq_slug` (`slug`)', 'KEY `idx_status_pub` (`status`, `published_at`)'],
        ],
        'reviews' => [
            'columns' => $id + [
                'name' => 'VARCHAR(150) NOT NULL',
                'trip' => "VARCHAR(200) NOT NULL DEFAULT ''",
                'rating' => 'TINYINT NOT NULL DEFAULT 5',
                'content' => 'TEXT NOT NULL',
                'avatar' => "VARCHAR(500) NOT NULL DEFAULT ''",
                'photos' => 'TEXT NULL',
                'booking_id' => 'INT UNSIGNED NULL',
                'tour_id' => 'INT UNSIGNED NULL',
                'verified' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'lang' => "VARCHAR(10) NOT NULL DEFAULT ''",
                'status' => "VARCHAR(20) NOT NULL DEFAULT 'pending'",
                'ip_address' => 'VARCHAR(45) NULL',
                'created_at' => 'DATETIME NOT NULL',
            ],
            'keys' => ['PRIMARY KEY (`id`)', 'KEY `idx_status` (`status`)', 'KEY `idx_tour` (`tour_id`)', 'KEY `idx_booking` (`booking_id`)'],
        ],
        'pages' => [
            'columns' => $id + [
                'title' => 'VARCHAR(200) NOT NULL',
                'slug' => 'VARCHAR(220) NOT NULL',
                'content' => 'MEDIUMTEXT NULL',
                'show_in_footer' => 'TINYINT(1) NOT NULL DEFAULT 1',
                'status' => "VARCHAR(20) NOT NULL DEFAULT 'published'",
            ] + $seo + [
                'created_at' => 'DATETIME NOT NULL',
                'updated_at' => 'DATETIME NOT NULL',
            ],
            'keys' => ['PRIMARY KEY (`id`)', 'UNIQUE KEY `uniq_slug` (`slug`)'],
        ],
        'messages' => [
            'columns' => $id + [
                'name' => 'VARCHAR(150) NOT NULL',
                'email' => 'VARCHAR(190) NOT NULL',
                'phone' => "VARCHAR(60) NOT NULL DEFAULT ''",
                'subject' => "VARCHAR(200) NOT NULL DEFAULT ''",
                'message' => 'TEXT NOT NULL',
                'is_read' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'ip_address' => 'VARCHAR(45) NULL',
                'created_at' => 'DATETIME NOT NULL',
            ],
            'keys' => ['PRIMARY KEY (`id`)'],
        ],
        'subscribers' => [
            'columns' => $id + [
                'email' => 'VARCHAR(190) NOT NULL',
                'name' => "VARCHAR(150) NOT NULL DEFAULT ''",
                'status' => "VARCHAR(20) NOT NULL DEFAULT 'active'",
                'source' => "VARCHAR(30) NOT NULL DEFAULT 'footer'",
                'lang' => "VARCHAR(10) NOT NULL DEFAULT ''",
                'ip_address' => 'VARCHAR(45) NULL',
                'created_at' => 'DATETIME NOT NULL',
                'unsubscribed_at' => 'DATETIME NULL',
            ],
            'keys' => ['PRIMARY KEY (`id`)', 'UNIQUE KEY `uniq_email` (`email`)', 'KEY `idx_status` (`status`)'],
        ],
        // "Plan my trip" custom requests.
        'trip_requests' => [
            'columns' => $id + [
                'reference' => 'VARCHAR(20) NOT NULL',
                'name' => 'VARCHAR(150) NOT NULL',
                'email' => 'VARCHAR(190) NOT NULL',
                'phone' => "VARCHAR(60) NOT NULL DEFAULT ''",
                'destinations' => 'TEXT NULL',
                'start_date' => 'DATE NULL',
                'flexible_dates' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'nights' => 'INT NOT NULL DEFAULT 0',
                'adults' => 'INT NOT NULL DEFAULT 1',
                'children' => 'INT NOT NULL DEFAULT 0',
                'budget' => "VARCHAR(60) NOT NULL DEFAULT ''",
                'styles' => "VARCHAR(255) NOT NULL DEFAULT ''",
                'accommodation' => "VARCHAR(60) NOT NULL DEFAULT ''",
                'message' => 'TEXT NULL',
                'status' => "VARCHAR(20) NOT NULL DEFAULT 'new'",
                'admin_notes' => 'TEXT NULL',
                'lang' => "VARCHAR(10) NOT NULL DEFAULT ''",
                'ip_address' => 'VARCHAR(45) NULL',
                'created_at' => 'DATETIME NOT NULL',
                'updated_at' => 'DATETIME NOT NULL',
            ],
            'keys' => ['PRIMARY KEY (`id`)', 'UNIQUE KEY `uniq_reference` (`reference`)', 'KEY `idx_status` (`status`)'],
        ],
        // FAQs: tour_id NULL = general FAQ page, otherwise shown on that trip.
        'faqs' => [
            'columns' => $id + [
                'tour_id' => 'INT UNSIGNED NULL',
                'category' => "VARCHAR(100) NOT NULL DEFAULT ''",
                'question' => 'VARCHAR(500) NOT NULL',
                'answer' => 'TEXT NOT NULL',
                'sort_order' => 'INT NOT NULL DEFAULT 0',
                'status' => "VARCHAR(20) NOT NULL DEFAULT 'active'",
                'created_at' => 'DATETIME NOT NULL',
            ],
            'keys' => ['PRIMARY KEY (`id`)', 'KEY `idx_tour` (`tour_id`)'],
        ],
        // Translations of content (trips, pages, settings...) and interface texts.
        'translations' => [
            'columns' => $id + [
                'lang' => 'VARCHAR(10) NOT NULL',
                'type' => 'VARCHAR(30) NOT NULL',
                'item_id' => 'INT UNSIGNED NOT NULL DEFAULT 0',
                'field' => 'VARCHAR(100) NOT NULL',
                'value' => 'MEDIUMTEXT NULL',
            ],
            'keys' => ['PRIMARY KEY (`id`)', 'UNIQUE KEY `uniq_tr` (`lang`, `type`, `item_id`, `field`)', 'KEY `idx_type_item` (`type`, `item_id`)'],
        ],
    ];
}

/**
 * Create missing tables and add missing columns. Safe to run repeatedly:
 * existing data is never changed or removed.
 */
function schema_sync(PDO $pdo, string $prefix): void
{
    foreach (schema_tables() as $name => $def) {
        $table = '`' . $prefix . $name . '`';
        $exists = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote(str_replace('_', '\_', $prefix . $name)))->fetchColumn();
        if (!$exists) {
            $lines = [];
            foreach ($def['columns'] as $col => $ddl) {
                $lines[] = '`' . $col . '` ' . $ddl;
            }
            $lines = array_merge($lines, $def['keys']);
            $pdo->exec("CREATE TABLE $table (\n  " . implode(",\n  ", $lines) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            continue;
        }
        $have = array_column($pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_ASSOC), 'Field');
        $previous = null;
        foreach ($def['columns'] as $col => $ddl) {
            if (!in_array($col, $have, true)) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN `$col` $ddl" . ($previous ? " AFTER `$previous`" : ''));
            }
            $previous = $col;
        }
    }
}
