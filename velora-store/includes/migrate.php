<?php
/**
 * Upgrades the database of an existing store when a new version adds tables or columns.
 * Runs automatically (it only does work once per version).
 */
require_once APP_ROOT . '/includes/schema.php';

function run_migrations(): void
{
    $version = (int) setting('db_version', '1');
    if ($version >= DB_VERSION) {
        return;
    }
    $mysql = DB_DRIVER !== 'sqlite';
    $pk = $mysql ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $fk = $mysql ? 'INT UNSIGNED' : 'INTEGER';
    $tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    if ($version < 2) {
        // Version 2: Printful integration (variants, Printful ids, full shipping address).
        $steps = [
            "ALTER TABLE products ADD COLUMN printful_id VARCHAR(40) NOT NULL DEFAULT ''",
            "ALTER TABLE orders ADD COLUMN state VARCHAR(120) NOT NULL DEFAULT ''",
            "ALTER TABLE orders ADD COLUMN zip VARCHAR(30) NOT NULL DEFAULT ''",
            "ALTER TABLE orders ADD COLUMN country_code VARCHAR(2) NOT NULL DEFAULT ''",
            "ALTER TABLE orders ADD COLUMN printful_order_id VARCHAR(40) NOT NULL DEFAULT ''",
            "ALTER TABLE orders ADD COLUMN printful_status VARCHAR(40) NOT NULL DEFAULT ''",
            "ALTER TABLE orders ADD COLUMN tracking_url VARCHAR(255) NOT NULL DEFAULT ''",
            "ALTER TABLE order_items ADD COLUMN variant_id $fk NULL",
            "ALTER TABLE order_items ADD COLUMN printful_variant_id VARCHAR(40) NOT NULL DEFAULT ''",
            product_variants_sql($pk, $fk, $tail),
        ];
        if ($mysql) {
            $steps[] = "ALTER TABLE products MODIFY image VARCHAR(500) NOT NULL DEFAULT ''";
            $steps[] = "ALTER TABLE order_items MODIFY image VARCHAR(500) NOT NULL DEFAULT ''";
        }
        foreach ($steps as $sql) {
            try {
                db()->exec($sql);
            } catch (PDOException $ex) {
                // Column already exists (e.g. an interrupted earlier upgrade): safe to ignore.
                if (stripos($ex->getMessage(), 'duplicate') === false) {
                    throw $ex;
                }
            }
        }
        foreach (['printful_enabled' => '0', 'printful_auto_paid' => '1', 'printful_auto_cod' => '0', 'printful_confirm' => '0'] as $k => $v) {
            if (!array_key_exists($k, settings_all())) {
                set_setting($k, $v);
            }
        }
        if (setting('printful_webhook_key') === '') {
            set_setting('printful_webhook_key', bin2hex(random_bytes(16)));
        }
    }

    if ($version < 3) {
        // Version 3: subcategories, short description, custom options, product shipping,
        // coupons, product reviews, contact messages, shipping methods.
        $steps = array_merge([
            "ALTER TABLE categories ADD COLUMN parent_id $fk NULL",
            "ALTER TABLE products ADD COLUMN short_description TEXT",
            "ALTER TABLE products ADD COLUMN extra_options TEXT",
            "ALTER TABLE products ADD COLUMN additional_info TEXT",
            "ALTER TABLE products ADD COLUMN shipping_enabled TINYINT NOT NULL DEFAULT 1",
            "ALTER TABLE products ADD COLUMN shipping_methods VARCHAR(255) NOT NULL DEFAULT ''",
            "ALTER TABLE orders ADD COLUMN coupon_code VARCHAR(40) NOT NULL DEFAULT ''",
            "ALTER TABLE orders ADD COLUMN discount DECIMAL(10,2) NOT NULL DEFAULT 0",
            "ALTER TABLE orders ADD COLUMN shipping_method VARCHAR(120) NOT NULL DEFAULT ''",
            "ALTER TABLE order_items ADD COLUMN options VARCHAR(500) NOT NULL DEFAULT ''",
        ], v3_tables_sql($pk, $fk, $tail));
        foreach ($steps as $sql) {
            try {
                db()->exec($sql);
            } catch (PDOException $ex) {
                if (stripos($ex->getMessage(), 'duplicate') === false) {
                    throw $ex;
                }
            }
        }
        // Defaults for the new settings (the popup stays off until the owner turns it on).
        $defaults = [
            'reviews_enabled' => '1', 'reviews_moderate' => '1', 'popup_enabled' => '0',
            'popup_badge_value' => '10%', 'popup_badge_text' => 'OFF', 'popup_subtitle' => 'FIRST ORDER OFFER',
            'popup_title' => 'Take [10%] Off Your First Order', 'popup_link_text' => 'SHOP NOW', 'popup_link' => 'shop.php',
            'popup_text' => 'Use the code below at checkout, or send it to your inbox so it is there when you are ready.',
            'popup_delay' => '4', 'popup_days' => '7', 'popup_image' => 'assets/img/demo/popup.svg',
            'contact_title' => 'Get In Touch', 'contact_box_title' => setting('store_name'), 'contact_email' => setting('store_email'),
            'contact_image' => 'assets/img/demo/banner.svg', 'contact_hours_title' => 'Opening Hours',
            'contact_hours' => "Monday - Friday : 9am - 5pm\nWeekend Closed",
            'footer_copyright' => '{store} © {year}, All Rights Reserved',
        ];
        foreach ($defaults as $k => $v) {
            if (!array_key_exists($k, settings_all())) {
                set_setting($k, $v);
            }
        }
        // Keep the old flat delivery fee as the first shipping method.
        if ((int) q_val('SELECT COUNT(*) FROM shipping_methods') === 0) {
            db_insert('shipping_methods', ['name' => 'Standard Delivery', 'description' => '3-5 business days',
                'cost' => (float) setting('shipping_fee', '0'), 'active' => 1, 'sort_order' => 0]);
        }
    }

    if ($version < 4) {
        // Version 4: customer accounts + email (SMTP) settings.
        foreach (["ALTER TABLE orders ADD COLUMN customer_id $fk NULL", customers_sql($pk, $tail)] as $sql) {
            try {
                db()->exec($sql);
            } catch (PDOException $ex) {
                if (stripos($ex->getMessage(), 'duplicate') === false) {
                    throw $ex;
                }
            }
        }
        foreach (default_v4_settings() as $k => $v) {
            if (!array_key_exists($k, settings_all())) {
                set_setting($k, $v);
            }
        }
    }

    if ($version < 5) {
        foreach (array_merge(v5_alter_sql($fk), v5_tables_sql($pk, $fk, $tail)) as $sql) {
            try {
                db()->exec($sql);
            } catch (PDOException $ex) {
                if (stripos($ex->getMessage(), 'duplicate') === false) {
                    throw $ex;
                }
            }
        }
        foreach (default_v5_settings() as $k => $v) {
            if (!array_key_exists($k, settings_all())) {
                set_setting($k, $v);
            }
        }
    }

    set_setting('db_version', (string) DB_VERSION);
    settings_all(true);
}

