<?php
/**
 * Database schema. Works for both MySQL and SQLite.
 */

function schema_statements(string $driver): array
{
    $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $fk = $driver === 'sqlite' ? 'INTEGER' : 'INT UNSIGNED';
    $tail = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    return array_merge([
        "CREATE TABLE IF NOT EXISTS settings (
            skey VARCHAR(100) NOT NULL PRIMARY KEY,
            svalue TEXT
        )$tail",

        "CREATE TABLE IF NOT EXISTS admins (
            id $pk,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            is_owner TINYINT NOT NULL DEFAULT 1,
            permissions VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL
        )$tail",

        "CREATE TABLE IF NOT EXISTS categories (
            id $pk,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(160) NOT NULL UNIQUE,
            image VARCHAR(255) NOT NULL DEFAULT '',
            sort_order INT NOT NULL DEFAULT 0,
            parent_id $fk NULL
        )$tail",

        "CREATE TABLE IF NOT EXISTS products (
            id $pk,
            category_id $fk NULL,
            name VARCHAR(200) NOT NULL,
            slug VARCHAR(220) NOT NULL UNIQUE,
            brand VARCHAR(120) NOT NULL DEFAULT '',
            description TEXT,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            old_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            image VARCHAR(500) NOT NULL DEFAULT '',
            gallery TEXT,
            sizes VARCHAR(255) NOT NULL DEFAULT '',
            colors VARCHAR(255) NOT NULL DEFAULT '',
            rating DECIMAL(2,1) NOT NULL DEFAULT 5.0,
            reviews_count INT NOT NULL DEFAULT 0,
            stock INT NOT NULL DEFAULT -1,
            is_new TINYINT NOT NULL DEFAULT 0,
            is_trending TINYINT NOT NULL DEFAULT 0,
            is_flash TINYINT NOT NULL DEFAULT 0,
            active TINYINT NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            printful_id VARCHAR(40) NOT NULL DEFAULT '',
            short_description TEXT,
            extra_options TEXT,
            additional_info TEXT,
            shipping_enabled TINYINT NOT NULL DEFAULT 1,
            shipping_methods VARCHAR(255) NOT NULL DEFAULT '',
            meta_title VARCHAR(200) NOT NULL DEFAULT '',
            meta_description VARCHAR(300) NOT NULL DEFAULT '',
            variant_stock TINYINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        )$tail",

        product_variants_sql($pk, $fk, $tail),

        "CREATE TABLE IF NOT EXISTS orders (
            id $pk,
            order_number VARCHAR(40) NOT NULL UNIQUE,
            customer_name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL DEFAULT '',
            phone VARCHAR(40) NOT NULL,
            address VARCHAR(255) NOT NULL,
            city VARCHAR(120) NOT NULL,
            state VARCHAR(120) NOT NULL DEFAULT '',
            zip VARCHAR(30) NOT NULL DEFAULT '',
            country VARCHAR(120) NOT NULL DEFAULT '',
            country_code VARCHAR(2) NOT NULL DEFAULT '',
            notes TEXT,
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
            shipping DECIMAL(10,2) NOT NULL DEFAULT 0,
            total DECIMAL(10,2) NOT NULL DEFAULT 0,
            payment_method VARCHAR(20) NOT NULL,
            payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
            payment_ref VARCHAR(190) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            whatsapp_sent TINYINT NOT NULL DEFAULT 0,
            stock_reduced TINYINT NOT NULL DEFAULT 0,
            printful_order_id VARCHAR(40) NOT NULL DEFAULT '',
            printful_status VARCHAR(40) NOT NULL DEFAULT '',
            tracking_url VARCHAR(255) NOT NULL DEFAULT '',
            coupon_code VARCHAR(40) NOT NULL DEFAULT '',
            discount DECIMAL(10,2) NOT NULL DEFAULT 0,
            shipping_method VARCHAR(120) NOT NULL DEFAULT '',
            customer_id $fk NULL,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )$tail",

        "CREATE TABLE IF NOT EXISTS order_items (
            id $pk,
            order_id $fk NOT NULL,
            product_id $fk NULL,
            name VARCHAR(200) NOT NULL,
            image VARCHAR(255) NOT NULL DEFAULT '',
            size VARCHAR(60) NOT NULL DEFAULT '',
            color VARCHAR(60) NOT NULL DEFAULT '',
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            qty INT NOT NULL DEFAULT 1,
            variant_id $fk NULL,
            printful_variant_id VARCHAR(40) NOT NULL DEFAULT '',
            options VARCHAR(500) NOT NULL DEFAULT '',
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
        )$tail",

        "CREATE TABLE IF NOT EXISTS testimonials (
            id $pk,
            name VARCHAR(120) NOT NULL,
            text TEXT NOT NULL,
            rating INT NOT NULL DEFAULT 5,
            active TINYINT NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0
        )$tail",

        "CREATE TABLE IF NOT EXISTS pages (
            id $pk,
            type VARCHAR(10) NOT NULL DEFAULT 'page',
            title VARCHAR(200) NOT NULL,
            slug VARCHAR(220) NOT NULL UNIQUE,
            content TEXT,
            image VARCHAR(500) NOT NULL DEFAULT '',
            active TINYINT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL
        )$tail",

        "CREATE TABLE IF NOT EXISTS subscribers (
            id $pk,
            email VARCHAR(190) NOT NULL UNIQUE,
            created_at DATETIME NOT NULL
        )$tail",
    ], v3_tables_sql($pk, $fk, $tail), [customers_sql($pk, $tail)], v5_tables_sql($pk, $fk, $tail));
}

/** Size/color combinations of a product (used by Printful products). */
function product_variants_sql(string $pk, string $fk, string $tail): string
{
    return "CREATE TABLE IF NOT EXISTS product_variants (
        id $pk,
        product_id $fk NOT NULL,
        printful_variant_id VARCHAR(40) NOT NULL DEFAULT '',
        size VARCHAR(60) NOT NULL DEFAULT '',
        color VARCHAR(60) NOT NULL DEFAULT '',
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        sku VARCHAR(120) NOT NULL DEFAULT '',
        image VARCHAR(500) NOT NULL DEFAULT '',
        active TINYINT NOT NULL DEFAULT 1,
        stock INT NOT NULL DEFAULT -1,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )$tail";
}

/** Current database version. Bump it and add a step to run_migrations() when the schema changes. */
const DB_VERSION = 5;

/** Version 5: COD block list and saved carts (abandoned cart reminders). */
function v5_tables_sql(string $pk, string $fk, string $tail): array
{
    return [
        "CREATE TABLE IF NOT EXISTS blocklist (
            id $pk,
            type VARCHAR(10) NOT NULL,
            value VARCHAR(190) NOT NULL,
            note VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL
        )$tail",
        "CREATE TABLE IF NOT EXISTS carts (
            id $pk,
            token VARCHAR(64) NOT NULL UNIQUE,
            customer_id $fk NULL,
            email VARCHAR(190) NOT NULL DEFAULT '',
            name VARCHAR(150) NOT NULL DEFAULT '',
            phone VARCHAR(40) NOT NULL DEFAULT '',
            items TEXT,
            total DECIMAL(10,2) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            reminded_at DATETIME NULL,
            recovered TINYINT NOT NULL DEFAULT 0
        )$tail",
    ];
}

/** Columns added in version 5 (for upgrades of older stores). */
function v5_alter_sql(string $fk): array
{
    return [
        "ALTER TABLE admins ADD COLUMN is_owner TINYINT NOT NULL DEFAULT 1",
        "ALTER TABLE admins ADD COLUMN permissions VARCHAR(255) NOT NULL DEFAULT ''",
        "ALTER TABLE products ADD COLUMN meta_title VARCHAR(200) NOT NULL DEFAULT ''",
        "ALTER TABLE products ADD COLUMN meta_description VARCHAR(300) NOT NULL DEFAULT ''",
        "ALTER TABLE products ADD COLUMN variant_stock TINYINT NOT NULL DEFAULT 0",
        "ALTER TABLE orders ADD COLUMN ip VARCHAR(45) NOT NULL DEFAULT ''",
        "ALTER TABLE product_variants ADD COLUMN stock INT NOT NULL DEFAULT -1",
        "ALTER TABLE shipping_methods ADD COLUMN countries VARCHAR(1000) NOT NULL DEFAULT ''",
        "ALTER TABLE shipping_methods ADD COLUMN cities TEXT",
        "ALTER TABLE shipping_methods ADD COLUMN free_over DECIMAL(10,2) NOT NULL DEFAULT 0",
    ];
}

/** Settings added in version 5. */
function default_v5_settings(): array
{
    return [
        // COD protection
        'cod_max_total' => '0',
        'cod_max_per_day' => '3',
        'cod_require_confirmation' => '0',
        // Tracking pixels
        'fb_pixel_id' => '', 'tiktok_pixel_id' => '', 'ga4_id' => '', 'head_code' => '',
        // Cookie notice
        'cookie_enabled' => '0',
        'cookie_text' => 'We use cookies to improve your experience and to measure our ads. You can accept or decline.',
        'cookie_link_text' => 'Privacy policy',
        'cookie_link' => 'page.php?slug=privacy',
        // SEO
        'pretty_urls' => '0',
        'seo_title' => '',
        'seo_description' => '',
        'og_image' => '',
        // Abandoned carts
        'abandoned_enabled' => '1',
        'abandoned_delay_hours' => '3',
        'abandoned_coupon_id' => '',
        'cron_key' => bin2hex(random_bytes(12)),
        // Speed
        'image_max_width' => '1600',
        'image_quality' => '82',
    ];
}

/** Version 4: customer accounts. */
function customers_sql(string $pk, string $tail): string
{
    return "CREATE TABLE IF NOT EXISTS customers (
        id $pk,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        phone VARCHAR(40) NOT NULL DEFAULT '',
        password VARCHAR(255) NOT NULL,
        address VARCHAR(255) NOT NULL DEFAULT '',
        city VARCHAR(120) NOT NULL DEFAULT '',
        state VARCHAR(120) NOT NULL DEFAULT '',
        zip VARCHAR(30) NOT NULL DEFAULT '',
        country_code VARCHAR(2) NOT NULL DEFAULT '',
        active TINYINT NOT NULL DEFAULT 1,
        reset_token VARCHAR(100) NOT NULL DEFAULT '',
        reset_expires DATETIME NULL,
        last_login DATETIME NULL,
        created_at DATETIME NOT NULL
    )$tail";
}

/** Tables added in version 3: coupons, product reviews, contact messages, shipping methods. */
function v3_tables_sql(string $pk, string $fk, string $tail): array
{
    return [
        "CREATE TABLE IF NOT EXISTS coupons (
            id $pk,
            code VARCHAR(40) NOT NULL UNIQUE,
            type VARCHAR(20) NOT NULL DEFAULT 'percent',
            value DECIMAL(10,2) NOT NULL DEFAULT 0,
            min_order DECIMAL(10,2) NOT NULL DEFAULT 0,
            max_uses INT NOT NULL DEFAULT 0,
            used_count INT NOT NULL DEFAULT 0,
            starts_at DATETIME NULL,
            expires_at DATETIME NULL,
            active TINYINT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL
        )$tail",
        "CREATE TABLE IF NOT EXISTS reviews (
            id $pk,
            product_id $fk NOT NULL,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL DEFAULT '',
            rating INT NOT NULL DEFAULT 5,
            comment TEXT NOT NULL,
            approved TINYINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )$tail",
        "CREATE TABLE IF NOT EXISTS messages (
            id $pk,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL,
            subject VARCHAR(200) NOT NULL DEFAULT '',
            message TEXT NOT NULL,
            is_read TINYINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL
        )$tail",
        "CREATE TABLE IF NOT EXISTS shipping_methods (
            id $pk,
            name VARCHAR(120) NOT NULL,
            description VARCHAR(255) NOT NULL DEFAULT '',
            cost DECIMAL(10,2) NOT NULL DEFAULT 0,
            countries VARCHAR(1000) NOT NULL DEFAULT '',
            cities TEXT,
            free_over DECIMAL(10,2) NOT NULL DEFAULT 0,
            active TINYINT NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0
        )$tail",
    ];
}

/** Settings added in version 4 (also used by the installer). */
function default_v4_settings(): array
{
    return [
        'accounts_enabled' => '1',
        'guest_checkout' => '1',
        'mail_driver' => 'mail',
        'smtp_host' => '',
        'smtp_port' => '465',
        'smtp_encryption' => 'ssl',
        'smtp_username' => '',
        'smtp_password' => '',
        'mail_from_email' => '',
        'mail_from_name' => '',
        'admin_notify_email' => '',
        'notify_admin_order' => '1',
        'notify_admin_message' => '1',
        'notify_admin_review' => '1',
        'notify_customer_order' => '1',
        'notify_customer_status' => '1',
        'notify_customer_welcome' => '1',
    ];
}
