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
            ],
            'index' => [
                'ix_listings_status' => 'status, created_at',
                'ix_listings_user' => 'user_id',
                'ix_listings_category' => 'category_id',
            ],
        ],
        'listing_images' => [
            'cols' => [
                'id {ID}',
                'listing_id {INT} NOT NULL',
                'path VARCHAR(255) NOT NULL',
                'thumb VARCHAR(255) NOT NULL',
                'sort_order INT NOT NULL DEFAULT 0',
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
