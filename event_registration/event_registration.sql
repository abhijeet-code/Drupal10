-- Event Registration Module - Database Schema
-- Run this SQL after enabling the module if tables were not auto-created.

CREATE TABLE IF NOT EXISTS `event_registration_config` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_name` VARCHAR(255) NOT NULL,
  `event_category` VARCHAR(100) NOT NULL,
  `registration_start_date` VARCHAR(20) NOT NULL,
  `registration_end_date` VARCHAR(20) NOT NULL,
  `event_date` VARCHAR(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `event_registration_data` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` INT UNSIGNED NOT NULL,
  `full_name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `college_name` VARCHAR(255) NOT NULL,
  `department` VARCHAR(255) NOT NULL,
  `event_category` VARCHAR(100) NOT NULL,
  `created` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `email_event` (`email`, `event_id`),
  CONSTRAINT `event_fk` FOREIGN KEY (`event_id`) REFERENCES `event_registration_config` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
