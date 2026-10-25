<?php
/**
 * Database schema, portable between SQLite and MySQL/MariaDB.
 */
declare(strict_types=1);

function schema_tables(): array
{
    // column definitions use {ID}, {INT}, {TEXT} placeholders resolved per driver
    return [
        'users' => [
            'cols' => [
                'id {ID}',
                'name VARCHAR(100) NOT NULL',
                'username VARCHAR(50) NOT NULL',
                'email VARCHAR(191) NOT NULL',
                'password VARCHAR(255) NOT NULL',
                "phone VARCHAR(40) NOT NULL DEFAULT ''",
                "location VARCHAR(150) NOT NULL DEFAULT ''",
                'bio TEXT NULL',
                "avatar VARCHAR(255) NOT NULL DEFAULT ''",
                "role VARCHAR(20) NOT NULL DEFAULT 'user'",
                "status VARCHAR(20) NOT NULL DEFAULT 'active'",
                'verified {INT} NOT NULL DEFAULT 0',
                'created_at DATETIME NOT NULL',
                'last_login DATETIME NULL',
                'email_verified_at DATETIME NULL',
                "lang VARCHAR(10) NOT NULL DEFAULT ''",
                'newsletter {INT} NOT NULL DEFAULT 1',
                "totp_secret VARCHAR(64) NOT NULL DEFAULT ''",
                'totp_enabled {INT} NOT NULL DEFAULT 0',
                'recovery_codes TEXT NULL',
            ],
            'unique' => ['ux_users_email' => 'email', 'ux_users_username' => 'username'],
        ],
        'categories' => [
            'cols' => [
                'id {ID}',
                'parent_id {INT} NOT NULL DEFAULT 0',
                'name VARCHAR(100) NOT NULL',
                'slug VARCHAR(120) NOT NULL',
                "icon VARCHAR(40) NOT NULL DEFAULT 'tag'",
                "description VARCHAR(255) NOT NULL DEFAULT ''",
                'sort_order INT NOT NULL DEFAULT 0',
                'created_at DATETIME NOT NULL',
            ],
            'index' => ['ix_categories_parent' => 'parent_id'],
        ],
        'listings' => [
            'cols' => [
                'id {ID}',
                'user_id {INT} NOT NULL',
                'category_id {INT} NOT NULL DEFAULT 0',
                'title VARCHAR(150) NOT NULL',
                'description {TEXT} NULL',
                'price DECIMAL(14,2) NOT NULL DEFAULT 0',
                "price_type VARCHAR(20) NOT NULL DEFAULT 'fixed'",
                "item_condition VARCHAR(20) NOT NULL DEFAULT ''",
                "location VARCHAR(150) NOT NULL DEFAULT ''",
                'location_id {INT} NOT NULL DEFAULT 0',
                "phone VARCHAR(40) NOT NULL DEFAULT ''",
                'show_phone {INT} NOT NULL DEFAULT 1',
                "tags VARCHAR(255) NOT NULL DEFAULT ''",
                "status VARCHAR(20) NOT NULL DEFAULT 'pending'",
                "reject_reason VARCHAR(255) NOT NULL DEFAULT ''",
                'featured {INT} NOT NULL DEFAULT 0',
                'views {INT} NOT NULL DEFAULT 0',
                'created_at DATETIME NOT NULL',
                'updated_at DATETIME NOT NULL',
                'expires_at DATETIME NULL',
                "video_url VARCHAR(255) NOT NULL DEFAULT ''",
                'reminder_sent_at DATETIME NULL',
                'previous_price DECIMAL(14,2) NULL',
                'price_changed_at DATETIME NULL',
            ],
            'index' => [
                'ix_listings_status' => 'status, created_at',
                'ix_listings_user' => 'user_id',
                'ix_listings_category' => 'category_id',
                'ix_listings_location' => 'location_id',
            ],
        ],
        'listing_images' => [
            'cols' => [
                'id {ID}',
                'listing_id {INT} NOT NULL',
                'path VARCHAR(255) NOT NULL',
                'thumb VARCHAR(255) NOT NULL',
                'sort_order INT NOT NULL DEFAULT 0',
                "phash VARCHAR(16) NOT NULL DEFAULT ''",
            ],
            'index' => ['ix_images_listing' => 'listing_id'],
        ],
        'favorites' => [
            'cols' => [
                'id {ID}',
                'user_id {INT} NOT NULL',
                'listing_id {INT} NOT NULL',
                'created_at DATETIME NOT NULL',
            ],
            'unique' => ['ux_favorites' => 'user_id, listing_id'],
            'index' => ['ix_favorites_listing' => 'listing_id'],
        ],
        'conversations' => [
            'cols' => [
                'id {ID}',
                'listing_id {INT} NOT NULL',
                'buyer_id {INT} NOT NULL',
                'seller_id {INT} NOT NULL',
                'buyer_unread {INT} NOT NULL DEFAULT 0',
                'seller_unread {INT} NOT NULL DEFAULT 0',
                'last_message_at DATETIME NOT NULL',
                'created_at DATETIME NOT NULL',
            ],
            'index' => ['ix_conv_buyer' => 'buyer_id', 'ix_conv_seller' => 'seller_id', 'ix_conv_listing' => 'listing_id'],
        ],
        'messages' => [
            'cols' => [
                'id {ID}',
                'conversation_id {INT} NOT NULL',
                'sender_id {INT} NOT NULL',
                'body TEXT NOT NULL',
                "image VARCHAR(255) NOT NULL DEFAULT ''",
                "thumb VARCHAR(255) NOT NULL DEFAULT ''",
                'read_at DATETIME NULL',
                'offer_id {INT} NOT NULL DEFAULT 0',
                'created_at DATETIME NOT NULL',
            ],
            'index' => ['ix_messages_conv' => 'conversation_id'],
        ],
        'reviews' => [
            'cols' => [
                'id {ID}',
                'seller_id {INT} NOT NULL',
                'reviewer_id {INT} NOT NULL',
                'rating INT NOT NULL',
                'comment TEXT NULL',
                'created_at DATETIME NOT NULL',
            ],
            'index' => ['ix_reviews_seller' => 'seller_id'],
        ],
        'reports' => [
            'cols' => [
                'id {ID}',
                'listing_id {INT} NOT NULL',
                'user_id {INT} NOT NULL DEFAULT 0',
                'reason VARCHAR(60) NOT NULL',
                'details TEXT NULL',
                "status VARCHAR(20) NOT NULL DEFAULT 'open'",
                'created_at DATETIME NOT NULL',
            ],
            'index' => ['ix_reports_status' => 'status'],
        ],
        'pages' => [
            'cols' => [
                'id {ID}',
                'title VARCHAR(150) NOT NULL',
                'slug VARCHAR(120) NOT NULL',
                'content {TEXT} NULL',
                'in_header {INT} NOT NULL DEFAULT 0',
                'in_footer {INT} NOT NULL DEFAULT 1',
                'created_at DATETIME NOT NULL',
                'updated_at DATETIME NOT NULL',
            ],
            'unique' => ['ux_pages_slug' => 'slug'],
        ],
        'settings' => [
            'cols' => [
                'name VARCHAR(100) NOT NULL PRIMARY KEY',
                'value TEXT NULL',
            ],
        ],
        'contact_messages' => [
            'cols' => [
                'id {ID}',
                'name VARCHAR(100) NOT NULL',
                'email VARCHAR(191) NOT NULL',
                'subject VARCHAR(191) NOT NULL',
                'message TEXT NOT NULL',
                'is_read {INT} NOT NULL DEFAULT 0',
                'created_at DATETIME NOT NULL',
            ],
        ],
        'password_resets' => [
            'cols' => [
                'id {ID}',
                'user_id {INT} NOT NULL',
                'token_hash VARCHAR(64) NOT NULL',
                'expires_at DATETIME NOT NULL',
            ],
            'index' => ['ix_resets_token' => 'token_hash'],
        ],
        'category_fields' => [
            'cols' => [
                'id {ID}',
                'category_id {INT} NOT NULL',
                'label VARCHAR(100) NOT NULL',
                "type VARCHAR(20) NOT NULL DEFAULT 'text'",
                'options TEXT NULL',
                "unit VARCHAR(30) NOT NULL DEFAULT ''",
                'required {INT} NOT NULL DEFAULT 0',
                'filterable {INT} NOT NULL DEFAULT 1',
                'sort_order INT NOT NULL DEFAULT 0',
            ],
            'index' => ['ix_fields_category' => 'category_id'],
        ],
        'listing_field_values' => [
            'cols' => [
                'id {ID}',
                'listing_id {INT} NOT NULL',
                'field_id {INT} NOT NULL',
                'value VARCHAR(191) NOT NULL',
            ],
            'index' => ['ix_lfv_listing' => 'listing_id', 'ix_lfv_field' => 'field_id, value'],
        ],
        'locations' => [
            'cols' => [
                'id {ID}',
                'parent_id {INT} NOT NULL DEFAULT 0',
                'name VARCHAR(100) NOT NULL',
                'slug VARCHAR(120) NOT NULL',
                'sort_order INT NOT NULL DEFAULT 0',
            ],
            'index' => ['ix_locations_parent' => 'parent_id'],
        ],
        'saved_searches' => [
            'cols' => [
                'id {ID}',
                'user_id {INT} NOT NULL',
                'name VARCHAR(150) NOT NULL',
                'query TEXT NOT NULL',
                'alerts {INT} NOT NULL DEFAULT 1',
                'last_alert_at DATETIME NULL',
                'created_at DATETIME NOT NULL',
            ],
            'index' => ['ix_saved_user' => 'user_id'],
        ],
        'user_tokens' => [
            'cols' => [
                'id {ID}',
                'user_id {INT} NOT NULL',
                'type VARCHAR(20) NOT NULL',
                'token_hash VARCHAR(64) NOT NULL',
                'expires_at DATETIME NOT NULL',
            ],
            'index' => ['ix_tokens_hash' => 'token_hash'],
        ],
        'user_social' => [
            'cols' => [
                'id {ID}',
                'user_id {INT} NOT NULL',
                'provider VARCHAR(20) NOT NULL',
                'provider_uid VARCHAR(191) NOT NULL',
                'created_at DATETIME NOT NULL',
            ],
            'unique' => ['ux_social' => 'provider, provider_uid'],
            'index' => ['ix_social_user' => 'user_id'],
        ],
        'user_blocks' => [
            'cols' => [
                'id {ID}',
                'user_id {INT} NOT NULL',
                'blocked_id {INT} NOT NULL',
                'created_at DATETIME NOT NULL',
            ],
            'unique' => ['ux_blocks' => 'user_id, blocked_id'],
        ],
        'activity_log' => [
            'cols' => [
                'id {ID}',
                'user_id {INT} NOT NULL DEFAULT 0',
                'action VARCHAR(60) NOT NULL',
                "subject_type VARCHAR(30) NOT NULL DEFAULT ''",
                'subject_id {INT} NOT NULL DEFAULT 0',
                'details TEXT NULL',
                "ip VARCHAR(45) NOT NULL DEFAULT ''",
                'created_at DATETIME NOT NULL',
            ],
            'index' => ['ix_activity_created' => 'created_at', 'ix_activity_user' => 'user_id'],
        ],
        'languages' => [
            'cols' => [
                'code VARCHAR(10) NOT NULL PRIMARY KEY',
                'name VARCHAR(60) NOT NULL',
                "direction VARCHAR(3) NOT NULL DEFAULT 'ltr'",
                'enabled {INT} NOT NULL DEFAULT 1',
                'sort_order INT NOT NULL DEFAULT 0',
            ],
        ],
        'translations' => [
            'cols' => [
                'id {ID}',
                'lang VARCHAR(10) NOT NULL',
                'hash CHAR(40) NOT NULL',
                'msgid TEXT NOT NULL',
                'msgstr TEXT NOT NULL',
            ],
            'unique' => ['ux_translations' => 'lang, hash'],
        ],
        'shops' => [
            'cols' => [
                'id {ID}',
                'user_id {INT} NOT NULL',
                'name VARCHAR(120) NOT NULL',
                'slug VARCHAR(140) NOT NULL',
                "tagline VARCHAR(190) NOT NULL DEFAULT ''",
                'description TEXT NULL',
                "logo VARCHAR(255) NOT NULL DEFAULT ''",
                "banner VARCHAR(255) NOT NULL DEFAULT ''",
                "phone VARCHAR(40) NOT NULL DEFAULT ''",
                "email VARCHAR(191) NOT NULL DEFAULT ''",
                "website VARCHAR(255) NOT NULL DEFAULT ''",
                "address VARCHAR(255) NOT NULL DEFAULT ''",
                'hours TEXT NULL',
                "status VARCHAR(20) NOT NULL DEFAULT 'pending'",
                "reject_reason VARCHAR(255) NOT NULL DEFAULT ''",
                'created_at DATETIME NOT NULL',
                'updated_at DATETIME NOT NULL',
            ],
            'unique' => ['ux_shops_user' => 'user_id', 'ux_shops_slug' => 'slug'],
        ],
        'listing_stats' => [
            'cols' => [
                'id {ID}',
                'listing_id {INT} NOT NULL',
                'day DATE NOT NULL',
                'views {INT} NOT NULL DEFAULT 0',
                'saves {INT} NOT NULL DEFAULT 0',
                'messages {INT} NOT NULL DEFAULT 0',
                'offers {INT} NOT NULL DEFAULT 0',
                'phone_reveals {INT} NOT NULL DEFAULT 0',
            ],
            'unique' => ['ux_stats' => 'listing_id, day'],
        ],
        'offers' => [
            'cols' => [
                'id {ID}',
                'listing_id {INT} NOT NULL',
                'conversation_id {INT} NOT NULL',
                'buyer_id {INT} NOT NULL',
                'seller_id {INT} NOT NULL',
                'amount DECIMAL(14,2) NOT NULL',
                'counter_amount DECIMAL(14,2) NULL',
                "status VARCHAR(20) NOT NULL DEFAULT 'pending'",
                'created_at DATETIME NOT NULL',
                'updated_at DATETIME NOT NULL',
            ],
            'index' => ['ix_offers_listing' => 'listing_id', 'ix_offers_buyer' => 'buyer_id', 'ix_offers_seller' => 'seller_id'],
        ],
        'mail_queue' => [
            'cols' => [
                'id {ID}',
                'to_email VARCHAR(191) NOT NULL',
                'subject VARCHAR(255) NOT NULL',
                'body_text {TEXT} NOT NULL',
                'body_html {TEXT} NOT NULL',
                'newsletter_id {INT} NOT NULL DEFAULT 0',
                'attempts INT NOT NULL DEFAULT 0',
                "last_error VARCHAR(255) NOT NULL DEFAULT ''",
                'created_at DATETIME NOT NULL',
                'sent_at DATETIME NULL',
            ],
            'index' => ['ix_mail_pending' => 'sent_at, attempts', 'ix_mail_newsletter' => 'newsletter_id'],
        ],
        'newsletters' => [
            'cols' => [
                'id {ID}',
                'subject VARCHAR(255) NOT NULL',
                'body {TEXT} NOT NULL',
                'audience VARCHAR(30) NOT NULL',
                'recipients {INT} NOT NULL DEFAULT 0',
                'created_by {INT} NOT NULL DEFAULT 0',
                'created_at DATETIME NOT NULL',
            ],
        ],
        'listing_flags' => [
            'cols' => [
                'id {ID}',
                'listing_id {INT} NOT NULL',
                'type VARCHAR(30) NOT NULL',
                'related_id {INT} NOT NULL DEFAULT 0',
                "details VARCHAR(255) NOT NULL DEFAULT ''",
                'resolved {INT} NOT NULL DEFAULT 0',
                'created_at DATETIME NOT NULL',
            ],
            'index' => ['ix_flags_listing' => 'listing_id', 'ix_flags_open' => 'resolved'],
        ],
        'verification_requests' => [
            'cols' => [
                'id {ID}',
                'user_id {INT} NOT NULL',
                'doc_type VARCHAR(40) NOT NULL',
                "doc_front VARCHAR(255) NOT NULL DEFAULT ''",
                "doc_back VARCHAR(255) NOT NULL DEFAULT ''",
                "selfie VARCHAR(255) NOT NULL DEFAULT ''",
                'note TEXT NULL',
                "status VARCHAR(20) NOT NULL DEFAULT 'pending'",
                "admin_note VARCHAR(255) NOT NULL DEFAULT ''",
                'created_at DATETIME NOT NULL',
                'reviewed_at DATETIME NULL',
                'reviewed_by {INT} NOT NULL DEFAULT 0',
            ],
            'index' => ['ix_verif_user' => 'user_id', 'ix_verif_status' => 'status'],
        ],
        'login_attempts' => [
            'cols' => [
                'id {ID}',
                'ip VARCHAR(45) NOT NULL',
                'created_at DATETIME NOT NULL',
            ],
            'index' => ['ix_attempts_ip' => 'ip, created_at'],
        ],
    ];
}

/** Columns added after v1 — [table, column, definition]. Used to upgrade existing installs. */
function schema_added_columns(): array
{
    return [
        ['users', 'email_verified_at', 'DATETIME NULL'],
        ['listings', 'location_id', '{INT} NOT NULL DEFAULT 0'],
        // v3
        ['users', 'lang', "VARCHAR(10) NOT NULL DEFAULT ''"],
        ['users', 'newsletter', '{INT} NOT NULL DEFAULT 1'],
        ['users', 'totp_secret', "VARCHAR(64) NOT NULL DEFAULT ''"],
        ['users', 'totp_enabled', '{INT} NOT NULL DEFAULT 0'],
        ['users', 'recovery_codes', 'TEXT NULL'],
        ['listings', 'video_url', "VARCHAR(255) NOT NULL DEFAULT ''"],
        ['listings', 'reminder_sent_at', 'DATETIME NULL'],
        ['listings', 'previous_price', 'DECIMAL(14,2) NULL'],
        ['listings', 'price_changed_at', 'DATETIME NULL'],
        ['listing_images', 'phash', "VARCHAR(16) NOT NULL DEFAULT ''"],
        ['messages', 'image', "VARCHAR(255) NOT NULL DEFAULT ''"],
        ['messages', 'thumb', "VARCHAR(255) NOT NULL DEFAULT ''"],
        ['messages', 'read_at', 'DATETIME NULL'],
        ['messages', 'offer_id', '{INT} NOT NULL DEFAULT 0'],
    ];
}

function column_definition(string $def, string $driver): string
{
    return strtr($def, $driver === 'mysql' ? ['{INT}' => 'INT UNSIGNED'] : ['{INT}' => 'INTEGER']);
}

function schema_statements(string $driver): array
{
    $mysql = $driver === 'mysql';
    $map = $mysql
        ? ['{ID}' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', '{INT}' => 'INT UNSIGNED', '{TEXT}' => 'MEDIUMTEXT']
        : ['{ID}' => 'INTEGER PRIMARY KEY AUTOINCREMENT', '{INT}' => 'INTEGER', '{TEXT}' => 'TEXT'];
    $sql = [];
    foreach (schema_tables() as $table => $def) {
        $cols = array_map(fn($c) => strtr($c, $map), $def['cols']);
        if ($mysql) {
            foreach ($def['unique'] ?? [] as $name => $c) {
                $cols[] = "UNIQUE KEY $name ($c)";
            }
            foreach ($def['index'] ?? [] as $name => $c) {
                $cols[] = "KEY $name ($c)";
            }
        }
        $sql[] = "CREATE TABLE IF NOT EXISTS $table (\n  " . implode(",\n  ", $cols) . "\n)"
            . ($mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '');
        if (!$mysql) {
            foreach ($def['unique'] ?? [] as $name => $c) {
                $sql[] = "CREATE UNIQUE INDEX IF NOT EXISTS $name ON $table ($c)";
            }
            foreach ($def['index'] ?? [] as $name => $c) {
                $sql[] = "CREATE INDEX IF NOT EXISTS $name ON $table ($c)";
            }
        }
    }
    return $sql;
}
