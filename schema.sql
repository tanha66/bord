-- ============================================================
-- Bordyar database schema (MySQL/MariaDB)
-- NOTE: installer drops existing tables first for a clean,
-- consistent schema. Run install.php to create everything.
-- ============================================================

DROP TABLE IF EXISTS `notifications`, `badges`, `reports`, `media_access`, `bookmarks`, `favorites`, `comment_votes`, `comments`, `ratings`, `tip_accesses`, `wallet_transactions`, `withdrawals`, `repair_answers`, `repair_requests`, `tips`, `categories`, `users`, `settings`;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  phone VARCHAR(30) NOT NULL UNIQUE,
  email VARCHAR(190) NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(120) NOT NULL,
  bio TEXT NULL,
  avatar VARCHAR(500) NULL,
  specialties TEXT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'member',
  points INT NOT NULL DEFAULT 0,
  balance BIGINT NOT NULL DEFAULT 0,
  referral_code VARCHAR(30) NULL UNIQUE,
  referred_by INT UNSIGNED NULL,
  premium_until DATETIME NULL,
  likes_used_today INT NOT NULL DEFAULT 0,
  likes_used_date DATE NULL,
  phone_verified TINYINT(1) NOT NULL DEFAULT 0,
  verified TINYINT(1) NOT NULL DEFAULT 0,
  is_banned TINYINT(1) NOT NULL DEFAULT 0,
  referred_rewarded TINYINT(1) NOT NULL DEFAULT 0,
  seller_status VARCHAR(20) NOT NULL DEFAULT 'none',
  seller_note TEXT NULL,
  seller_applied_at DATETIME NULL,
  national_id VARCHAR(20) NULL,
  shaba VARCHAR(40) NULL,
  card_number VARCHAR(30) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login DATETIME NULL,
  INDEX idx_users_role (role),
  INDEX idx_users_referred (referred_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  icon VARCHAR(20) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_categories_parent (parent_id),
  INDEX idx_categories_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tips (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  author_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  short_description TEXT NOT NULL,
  description MEDIUMTEXT NOT NULL,
  device_name VARCHAR(180) NOT NULL,
  brand VARCHAR(120) NOT NULL,
  model VARCHAR(120) NULL,
  board_number VARCHAR(120) NULL,
  fault_type VARCHAR(160) NOT NULL,
  difficulty VARCHAR(20) NOT NULL DEFAULT 'medium',
  solution_json MEDIUMTEXT NOT NULL,
  tools TEXT NULL,
  images_json MEDIUMTEXT NULL,
  video_url VARCHAR(500) NULL,
  attachments_json MEDIUMTEXT NULL,
  access_type VARCHAR(20) NOT NULL DEFAULT 'free',
  price BIGINT NOT NULL DEFAULT 0,
  visibility VARCHAR(20) NOT NULL DEFAULT 'public',
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  tags TEXT NULL,
  version INT NOT NULL DEFAULT 1,
  versions_json MEDIUMTEXT NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  views INT NOT NULL DEFAULT 0,
  likes_count INT NOT NULL DEFAULT 0,
  purchases_count INT NOT NULL DEFAULT 0,
  rating_sum INT NOT NULL DEFAULT 0,
  rating_count INT NOT NULL DEFAULT 0,
  duplicate_of INT UNSIGNED NULL,
  rejection_reason VARCHAR(500) NULL,
  source_url VARCHAR(500) NULL,
  source_name VARCHAR(200) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  published_at DATETIME NULL,
  INDEX idx_tips_status (status),
  INDEX idx_tips_category (category_id),
  INDEX idx_tips_author (author_id),
  INDEX idx_tips_published (published_at),
  FULLTEXT KEY ft_tips (title, short_description, description, device_name, brand, fault_type, tags)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tip_accesses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tip_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  access_type VARCHAR(30) NOT NULL,
  price_paid BIGINT NOT NULL DEFAULT 0,
  ip VARCHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tip_access (tip_id, user_id),
  INDEX idx_access_tip (tip_id),
  INDEX idx_access_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ratings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tip_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  stars TINYINT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rating (tip_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tip_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  parent_id INT UNSIGNED NULL,
  body TEXT NOT NULL,
  like_count INT NOT NULL DEFAULT 0,
  dislike_count INT NOT NULL DEFAULT 0,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  edited_at DATETIME NULL,
  INDEX idx_comments_tip (tip_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comment_votes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  comment_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  value TINYINT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_comment_vote (comment_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS follows (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  follower_id INT UNSIGNED NOT NULL,
  following_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_follow (follower_id, following_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookmarks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  tip_id INT UNSIGNED NOT NULL,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bookmark (user_id, tip_id),
  INDEX idx_bookmarks_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS favorites (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  tip_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_favorite (user_id, tip_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media_access (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  media_type VARCHAR(10) NOT NULL,
  path VARCHAR(500) NOT NULL,
  nonce VARCHAR(64) NOT NULL,
  ip VARCHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_media_access (user_id, media_type, path),
  INDEX idx_media_nonce (nonce)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type VARCHAR(40) NOT NULL,
  amount BIGINT NOT NULL,
  balance_after BIGINT NOT NULL,
  tip_id INT UNSIGNED NULL,
  request_id INT UNSIGNED NULL,
  note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tx_user (user_id),
  INDEX idx_tx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS withdrawals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  amount BIGINT NOT NULL,
  shaba VARCHAR(40) NOT NULL,
  card_number VARCHAR(30) NULL,
  national_id VARCHAR(20) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  INDEX idx_withdraw_status (status),
  INDEX idx_withdraw_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS repair_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  device_name VARCHAR(180) NOT NULL,
  brand VARCHAR(120) NULL,
  model VARCHAR(120) NULL,
  images_json TEXT NULL,
  reward_type VARCHAR(20) NOT NULL DEFAULT 'money',
  reward_amount BIGINT NOT NULL DEFAULT 0,
  deadline_days INT NOT NULL DEFAULT 7,
  status VARCHAR(20) NOT NULL DEFAULT 'open',
  best_answer_id INT UNSIGNED NULL,
  answer_count INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_repairs_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS repair_answers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  images_json TEXT NULL,
  is_best TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_answers_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type VARCHAR(40) NOT NULL,
  title VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  link VARCHAR(500) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notifications_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS badges (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  badge_type VARCHAR(80) NOT NULL,
  label VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_badge (user_id, badge_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reports (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reporter_id INT UNSIGNED NOT NULL,
  target_type VARCHAR(20) NOT NULL,
  target_id INT UNSIGNED NOT NULL,
  reason VARCHAR(200) NOT NULL,
  detail TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  INDEX idx_reports_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS boards (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seller_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  description MEDIUMTEXT NOT NULL,
  brand VARCHAR(120) NULL,
  model VARCHAR(120) NULL,
  condition_status VARCHAR(20) NOT NULL DEFAULT 'used',
  price BIGINT NOT NULL DEFAULT 0,
  stock INT NOT NULL DEFAULT 1,
  images_json MEDIUMTEXT NULL,
  video_url VARCHAR(500) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  views INT NOT NULL DEFAULT 0,
  sold_count INT NOT NULL DEFAULT 0,
  rejection_reason VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  approved_at DATETIME NULL,
  INDEX idx_boards_status (status),
  INDEX idx_boards_cat (category_id),
  INDEX idx_boards_seller (seller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS board_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  board_id INT UNSIGNED NOT NULL,
  buyer_id INT UNSIGNED NOT NULL,
  seller_id INT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NULL,
  amount BIGINT NOT NULL,
  commission_percent INT NOT NULL DEFAULT 10,
  commission_amount BIGINT NOT NULL DEFAULT 0,
  net_amount BIGINT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'paid',
  tracking_code VARCHAR(120) NULL,
  buyer_note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  shipped_at DATETIME NULL,
  completed_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  INDEX idx_orders_status (status),
  INDEX idx_orders_buyer (buyer_id),
  INDEX idx_orders_seller (seller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  id TINYINT UNSIGNED PRIMARY KEY,
   site_title VARCHAR(255) NOT NULL DEFAULT 'بردخان',
  hero_title VARCHAR(500) NOT NULL DEFAULT 'بازار تخصصی قلق‌های تعمیراتی بردهای الکترونیکی',
  hero_subtitle TEXT NOT NULL,
  announcement VARCHAR(500) NULL,
  upload_reward BIGINT NOT NULL DEFAULT 50000,
  like_points_reward INT NOT NULL DEFAULT 5,
  like_wallet_reward BIGINT NOT NULL DEFAULT 0,
  referral_reward BIGINT NOT NULL DEFAULT 20000,
  invitee_credit BIGINT NOT NULL DEFAULT 10000,
  commission_percent INT NOT NULL DEFAULT 20,
  min_withdrawal BIGINT NOT NULL DEFAULT 200000,
  daily_like_limit INT NOT NULL DEFAULT 5,
  repair_deadline_days INT NOT NULL DEFAULT 7,
  daily_free_tip_id INT UNSIGNED NULL,
  premium_1 BIGINT NOT NULL DEFAULT 149000,
  premium_3 BIGINT NOT NULL DEFAULT 399000,
  premium_12 BIGINT NOT NULL DEFAULT 1299000,
  board_commission_percent INT NOT NULL DEFAULT 10,
  auto_collect_enabled TINYINT(1) NOT NULL DEFAULT 0,
  auto_collect_count INT NOT NULL DEFAULT 10,
  auto_collect_category INT UNSIGNED NULL,
  auto_collect_access VARCHAR(20) NOT NULL DEFAULT 'free',
  auto_collect_sources TEXT NULL,
  auto_collect_queries TEXT NULL,
  auto_collect_cron_key VARCHAR(64) NULL,
  terms_text TEXT NULL,
  about_text TEXT NULL,
  contact_text TEXT NULL,
  meta_description TEXT NULL,
  meta_keywords TEXT NULL,
  og_image VARCHAR(500) NULL,
  google_analytics VARCHAR(100) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
