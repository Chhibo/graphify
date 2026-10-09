<?php
/**
 * Database schema. Works for both MySQL and SQLite.
 */

function schema_statements(string $driver): array
{
    $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $fk = $driver === 'sqlite' ? 'INTEGER' : 'INT UNSIGNED';
    $tail = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    return [
        "CREATE TABLE IF NOT EXISTS settings (
            skey VARCHAR(100) NOT NULL PRIMARY KEY,
            svalue TEXT
        )$tail",

        "CREATE TABLE IF NOT EXISTS admins (
            id $pk,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL
        )$tail",

        "CREATE TABLE IF NOT EXISTS categories (
            id $pk,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(160) NOT NULL UNIQUE,
            image VARCHAR(255) NOT NULL DEFAULT '',
            sort_order INT NOT NULL DEFAULT 0
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
    ];
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
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )$tail";
}

/** Current database version. Bump it and add a step to run_migrations() when the schema changes. */
const DB_VERSION = 2;
