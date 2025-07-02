-- ROTZ Email Butler - Database Schema
-- Generated for MySQL 8.0+

SET FOREIGN_KEY_CHECKS = 0;

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `email` VARCHAR(255) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'user', 'viewer') DEFAULT 'user',
    `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_login` TIMESTAMP NULL,
    `two_factor_enabled` BOOLEAN DEFAULT FALSE,
    `two_factor_secret` VARCHAR(32) NULL,
    INDEX `idx_username` (`username`),
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email providers table
CREATE TABLE IF NOT EXISTS `email_providers` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `provider_type` VARCHAR(50) NOT NULL,
    `display_name` VARCHAR(100) NOT NULL,
    `email_address` VARCHAR(255) NOT NULL,
    `username` VARCHAR(255) NOT NULL,
    `password_encrypted` TEXT NOT NULL,
    `imap_server` VARCHAR(255) NULL,
    `imap_port` INT DEFAULT 993,
    `imap_encryption` ENUM('ssl', 'tls', 'none') DEFAULT 'ssl',
    `smtp_server` VARCHAR(255) NULL,
    `smtp_port` INT DEFAULT 587,
    `smtp_encryption` ENUM('ssl', 'tls', 'none') DEFAULT 'tls',
    `api_key_encrypted` TEXT NULL,
    `api_endpoint` VARCHAR(255) NULL,
    `oauth_token_encrypted` TEXT NULL,
    `oauth_refresh_token_encrypted` TEXT NULL,
    `status` ENUM('active', 'inactive', 'error') DEFAULT 'inactive',
    `last_sync` TIMESTAMP NULL,
    `total_emails` INT DEFAULT 0,
    `last_error` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_provider` (`user_id`, `provider_type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_last_sync` (`last_sync`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI providers table
CREATE TABLE IF NOT EXISTS `ai_providers` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `provider_name` VARCHAR(50) NOT NULL,
    `model_name` VARCHAR(100) NOT NULL,
    `api_key_encrypted` TEXT NOT NULL,
    `api_endpoint` VARCHAR(255) NULL,
    `is_enabled` BOOLEAN DEFAULT TRUE,
    `priority_weight` DECIMAL(3,2) DEFAULT 1.00,
    `max_tokens` INT DEFAULT 1000,
    `temperature` DECIMAL(3,2) DEFAULT 0.1,
    `status` ENUM('active', 'inactive', 'error') DEFAULT 'inactive',
    `requests_count` INT DEFAULT 0,
    `successful_requests` INT DEFAULT 0,
    `failed_requests` INT DEFAULT 0,
    `total_cost` DECIMAL(10,4) DEFAULT 0.0000,
    `average_response_time` DECIMAL(8,3) DEFAULT 0.000,
    `last_used` TIMESTAMP NULL,
    `last_error` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_provider` (`user_id`, `provider_name`),
    INDEX `idx_enabled` (`is_enabled`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Emails table
CREATE TABLE IF NOT EXISTS `emails` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `provider_id` INT NOT NULL,
    `message_id` VARCHAR(255) NOT NULL,
    `thread_id` VARCHAR(255) NULL,
    `sender_email` VARCHAR(255) NOT NULL,
    `sender_name` VARCHAR(255) NULL,
    `recipient_email` VARCHAR(255) NOT NULL,
    `subject` TEXT NULL,
    `body_text` LONGTEXT NULL,
    `body_html` LONGTEXT NULL,
    `received_at` TIMESTAMP NOT NULL,
    `is_read` BOOLEAN DEFAULT FALSE,
    `is_starred` BOOLEAN DEFAULT FALSE,
    `is_archived` BOOLEAN DEFAULT FALSE,
    `has_attachments` BOOLEAN DEFAULT FALSE,
    `attachment_count` INT DEFAULT 0,
    `category` VARCHAR(50) NULL,
    `priority` ENUM('high', 'medium', 'low') DEFAULT 'medium',
    `sentiment` ENUM('positive', 'neutral', 'negative') NULL,
    `ai_confidence` DECIMAL(4,3) DEFAULT 0.000,
    `ai_summary` TEXT NULL,
    `action_required` BOOLEAN DEFAULT FALSE,
    `follow_up_date` DATE NULL,
    `processing_status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    `ai_analysis_json` JSON NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`provider_id`) REFERENCES `email_providers`(`id`) ON DELETE CASCADE,
    INDEX `idx_provider_received` (`provider_id`, `received_at`),
    INDEX `idx_category` (`category`),
    INDEX `idx_priority` (`priority`),
    INDEX `idx_processing_status` (`processing_status`),
    INDEX `idx_message_id` (`message_id`),
    INDEX `idx_sender` (`sender_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI analysis results table
CREATE TABLE IF NOT EXISTS `ai_analysis_results` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `email_id` INT NOT NULL,
    `ai_provider_id` INT NOT NULL,
    `category` VARCHAR(50) NULL,
    `priority` ENUM('high', 'medium', 'low') NULL,
    `sentiment` ENUM('positive', 'neutral', 'negative') NULL,
    `confidence` DECIMAL(4,3) NOT NULL,
    `summary` TEXT NULL,
    `action_required` BOOLEAN DEFAULT FALSE,
    `suggested_actions` JSON NULL,
    `processing_time` DECIMAL(8,3) NOT NULL,
    `tokens_used` INT DEFAULT 0,
    `cost` DECIMAL(8,4) DEFAULT 0.0000,
    `raw_response` LONGTEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`email_id`) REFERENCES `emails`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`ai_provider_id`) REFERENCES `ai_providers`(`id`) ON DELETE CASCADE,
    INDEX `idx_email_provider` (`email_id`, `ai_provider_id`),
    INDEX `idx_category` (`category`),
    INDEX `idx_confidence` (`confidence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email rules table
CREATE TABLE IF NOT EXISTS `email_rules` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `is_enabled` BOOLEAN DEFAULT TRUE,
    `priority` INT DEFAULT 0,
    `conditions` JSON NOT NULL,
    `actions` JSON NOT NULL,
    `match_count` INT DEFAULT 0,
    `last_matched` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_enabled` (`user_id`, `is_enabled`),
    INDEX `idx_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email drafts table
CREATE TABLE IF NOT EXISTS `email_drafts` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `subject` VARCHAR(255) NULL,
    `body_text` LONGTEXT NULL,
    `body_html` LONGTEXT NULL,
    `to_email` VARCHAR(255) NULL,
    `cc_email` TEXT NULL,
    `bcc_email` TEXT NULL,
    `ai_generated` BOOLEAN DEFAULT FALSE,
    `ai_provider_id` INT NULL,
    `generation_context` JSON NULL,
    `is_sent` BOOLEAN DEFAULT FALSE,
    `sent_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`ai_provider_id`) REFERENCES `ai_providers`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_sent` (`user_id`, `is_sent`),
    INDEX `idx_ai_generated` (`ai_generated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System settings table
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `setting_key` VARCHAR(100) UNIQUE NOT NULL,
    `setting_value` TEXT NULL,
    `setting_type` ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    `description` TEXT NULL,
    `is_public` BOOLEAN DEFAULT FALSE,
    `updated_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_setting_key` (`setting_key`),
    INDEX `idx_public` (`is_public`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity logs table
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50) NULL,
    `entity_id` INT NULL,
    `details` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_action` (`user_id`, `action`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email attachments table
CREATE TABLE IF NOT EXISTS `email_attachments` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `email_id` INT NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `content_type` VARCHAR(100) NULL,
    `size` INT DEFAULT 0,
    `file_path` VARCHAR(500) NULL,
    `is_inline` BOOLEAN DEFAULT FALSE,
    `content_id` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`email_id`) REFERENCES `emails`(`id`) ON DELETE CASCADE,
    INDEX `idx_email_id` (`email_id`),
    INDEX `idx_filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Follow-ups table
CREATE TABLE IF NOT EXISTS `follow_ups` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `email_id` INT NOT NULL,
    `follow_up_date` DATE NOT NULL,
    `follow_up_time` TIME NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `status` ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    `reminder_sent` BOOLEAN DEFAULT FALSE,
    `completed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`email_id`) REFERENCES `emails`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_date` (`user_id`, `follow_up_date`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`) VALUES
('registration_enabled', 'false', 'boolean', 'Allow new user registration', true),
('app_name', 'ROTZ Email Butler', 'string', 'Application name', true),
('app_version', '1.0.0', 'string', 'Application version', true),
('max_email_providers_per_user', '10', 'integer', 'Maximum email providers per user', false),
('max_ai_providers_per_user', '20', 'integer', 'Maximum AI providers per user', false),
('email_processing_batch_size', '50', 'integer', 'Email processing batch size', false),
('ai_request_timeout', '30', 'integer', 'AI request timeout in seconds', false),
('maintenance_mode', 'false', 'boolean', 'Maintenance mode status', true);

SET FOREIGN_KEY_CHECKS = 1;

