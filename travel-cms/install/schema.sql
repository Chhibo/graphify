-- WanderLuxe Travel CMS database schema (MySQL 5.7+ / MariaDB 10.3+)
-- The installer runs this file automatically. "{prefix}" is replaced with the table prefix.

CREATE TABLE IF NOT EXISTS `{prefix}users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'admin',
  `last_login` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}settings` (
  `name` VARCHAR(100) NOT NULL,
  `value` TEXT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trips (multi-day packages) and activities (day excursions) share one table.
CREATE TABLE IF NOT EXISTS `{prefix}tours` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(20) NOT NULL DEFAULT 'trip',
  `title` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(220) NOT NULL,
  `category_id` INT UNSIGNED NULL,
  `destination` VARCHAR(150) NOT NULL DEFAULT '',
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `duration` VARCHAR(80) NOT NULL DEFAULT '',
  `max_guests` INT NOT NULL DEFAULT 12,
  `icon` VARCHAR(60) NOT NULL DEFAULT 'fa-compass',
  `image` VARCHAR(500) NOT NULL DEFAULT '',
  `short_description` VARCHAR(500) NOT NULL DEFAULT '',
  `description` TEXT NULL,
  `inclusions` TEXT NULL,
  `itinerary` TEXT NULL,
  `rating` DECIMAL(2,1) NOT NULL DEFAULT 5.0,
  `reviews_count` INT NOT NULL DEFAULT 0,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 1,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slug` (`slug`),
  KEY `idx_type_status` (`type`, `status`),
  KEY `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}bookings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference` VARCHAR(20) NOT NULL,
  `access_token` VARCHAR(64) NOT NULL,
  `tour_id` INT UNSIGNED NULL,
  `tour_title` VARCHAR(200) NOT NULL,
  `travel_date` DATE NOT NULL,
  `guests` INT NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `customer_name` VARCHAR(150) NOT NULL,
  `customer_email` VARCHAR(190) NOT NULL,
  `customer_phone` VARCHAR(60) NOT NULL,
  `notes` TEXT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `admin_notes` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_reference` (`reference`),
  KEY `idx_status` (`status`),
  KEY `idx_travel_date` (`travel_date`),
  KEY `idx_email` (`customer_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}posts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(220) NOT NULL,
  `slug` VARCHAR(240) NOT NULL,
  `tag` VARCHAR(60) NOT NULL DEFAULT '',
  `author` VARCHAR(120) NOT NULL DEFAULT '',
  `image` VARCHAR(500) NOT NULL DEFAULT '',
  `excerpt` TEXT NULL,
  `content` MEDIUMTEXT NULL,
  `read_time` VARCHAR(30) NOT NULL DEFAULT '',
  `status` VARCHAR(20) NOT NULL DEFAULT 'published',
  `published_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slug` (`slug`),
  KEY `idx_status_pub` (`status`, `published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}reviews` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `trip` VARCHAR(200) NOT NULL DEFAULT '',
  `rating` TINYINT NOT NULL DEFAULT 5,
  `content` TEXT NOT NULL,
  `avatar` VARCHAR(500) NOT NULL DEFAULT '',
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}pages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(220) NOT NULL,
  `content` MEDIUMTEXT NULL,
  `show_in_footer` TINYINT(1) NOT NULL DEFAULT 1,
  `status` VARCHAR(20) NOT NULL DEFAULT 'published',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(60) NOT NULL DEFAULT '',
  `subject` VARCHAR(200) NOT NULL DEFAULT '',
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
