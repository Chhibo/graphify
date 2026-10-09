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

    set_setting('db_version', (string) DB_VERSION);
    settings_all(true);
}
