-- Bordkhan PHP parity migration
-- Run once after schema.sql. For a repeatable migration use migrate.php.

ALTER TABLE users
  ADD COLUMN address TEXT NULL,
  ADD COLUMN postal_code VARCHAR(20) NULL,
  ADD COLUMN landline VARCHAR(30) NULL,
  ADD COLUMN mobile VARCHAR(30) NULL,
  ADD COLUMN city VARCHAR(100) NULL,
  ADD COLUMN support_group VARCHAR(80) NULL,
  ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE board_orders
  ADD COLUMN full_name VARCHAR(160) NULL,
  ADD COLUMN phone VARCHAR(30) NULL,
  ADD COLUMN address TEXT NULL,
  ADD COLUMN city VARCHAR(100) NULL,
  ADD COLUMN postal_code VARCHAR(20) NULL,
  ADD COLUMN carrier VARCHAR(40) NULL;

ALTER TABLE wallet_transactions
  ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'confirmed',
  ADD COLUMN method VARCHAR(30) NULL,
  ADD COLUMN gateway VARCHAR(40) NULL,
  ADD COLUMN receipt_url VARCHAR(500) NULL,
  ADD COLUMN bank_name VARCHAR(120) NULL,
  ADD COLUMN card_number VARCHAR(40) NULL,
  ADD COLUMN reference VARCHAR(160) NULL;

ALTER TABLE settings
  ADD COLUMN gateway_enabled TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN gateway_type VARCHAR(20) NOT NULL DEFAULT 'zarinpal',
  ADD COLUMN gateway_merchant_id VARCHAR(190) NULL,
  ADD COLUMN gateway_api_key VARCHAR(255) NULL,
  ADD COLUMN gateway_sandbox TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN gateway_min_charge BIGINT NOT NULL DEFAULT 100000,
  ADD COLUMN gateway_max_charge BIGINT NOT NULL DEFAULT 50000000,
  ADD COLUMN z2c_bank_name VARCHAR(120) NULL,
  ADD COLUMN z2c_account_name VARCHAR(160) NULL,
  ADD COLUMN z2c_card_number VARCHAR(40) NULL,
  ADD COLUMN actionbar_json TEXT NULL,
  ADD COLUMN privacy_text TEXT NULL,
  ADD COLUMN contact_form_enabled TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN contact_email VARCHAR(190) NULL,
  ADD COLUMN contact_phone VARCHAR(40) NULL,
  ADD COLUMN contact_telegram VARCHAR(190) NULL,
  ADD COLUMN contact_instagram VARCHAR(190) NULL,
  ADD COLUMN contact_address VARCHAR(300) NULL;

CREATE TABLE IF NOT EXISTS tickets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  destination VARCHAR(20) NOT NULL DEFAULT 'support',
  seller_id INT UNSIGNED NULL,
  order_id INT UNSIGNED NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'عمومی',
  priority VARCHAR(20) NOT NULL DEFAULT 'normal',
  title VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  assigned_to INT UNSIGNED NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tickets_user (user_id),
  INDEX idx_tickets_status (status),
  INDEX idx_tickets_assigned (assigned_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT UNSIGNED NOT NULL,
  sender_id INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ticket_messages_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(30) NULL,
  subject VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_contact_status (status),
  INDEX idx_contact_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bk_gateway_payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  amount BIGINT NOT NULL,
  gateway VARCHAR(30) NOT NULL,
  authority VARCHAR(190) NULL,
  order_id VARCHAR(190) NULL,
  reference VARCHAR(190) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  verified_at DATETIME NULL,
  UNIQUE KEY uq_gateway_authority (gateway, authority),
  INDEX idx_gateway_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- v5.12: Push notification subscriptions
CREATE TABLE IF NOT EXISTS push_subscriptions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  endpoint VARCHAR(500) NOT NULL,
  p256dh VARCHAR(200) NOT NULL DEFAULT '',
  auth VARCHAR(200) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_push_sub_user (user_id),
  UNIQUE KEY uq_push_endpoint (endpoint(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add VAPID key columns to settings if not exist
-- (Handled by PHP code in bk_vapid_keys)
