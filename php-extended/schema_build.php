<?php
/**
 * Bordkhan — تعریف مشترک اسکیمای ماژول‌های تکمیلی.
 *
 * این فایل هم در install.php (نصب تازه: همه‌چیز با هم نصب می‌شود)
 * و هم در migrate.php (ارتقای نصب‌های قدیمی) استفاده می‌شود،
 * بنابراین تعریف ستون‌ها و جدول‌ها فقط یک‌جا نگهداری می‌شود.
 */

/** ستون‌هایی که باید به جدول‌های پایه اضافه شوند: [جدول، ستون، تعریف] */
function bk_schema_columns(): array {
    return [
        ['users', 'address', 'TEXT NULL'], ['users', 'postal_code', 'VARCHAR(20) NULL'],
        ['users', 'landline', 'VARCHAR(30) NULL'], ['users', 'mobile', 'VARCHAR(30) NULL'],
        ['users', 'city', 'VARCHAR(100) NULL'], ['users', 'support_group', 'VARCHAR(80) NULL'],
        ['users', 'is_deleted', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ['board_orders', 'full_name', 'VARCHAR(160) NULL'], ['board_orders', 'phone', 'VARCHAR(30) NULL'],
        ['board_orders', 'address', 'TEXT NULL'], ['board_orders', 'city', 'VARCHAR(100) NULL'],
        ['board_orders', 'postal_code', 'VARCHAR(20) NULL'], ['board_orders', 'carrier', 'VARCHAR(40) NULL'],
        ['wallet_transactions', 'status', "VARCHAR(20) NOT NULL DEFAULT 'confirmed'"],
        ['wallet_transactions', 'method', 'VARCHAR(30) NULL'], ['wallet_transactions', 'gateway', 'VARCHAR(40) NULL'],
        ['wallet_transactions', 'receipt_url', 'VARCHAR(500) NULL'], ['wallet_transactions', 'bank_name', 'VARCHAR(120) NULL'],
        ['wallet_transactions', 'card_number', 'VARCHAR(40) NULL'], ['wallet_transactions', 'reference', 'VARCHAR(160) NULL'],
        ['settings', 'gateway_enabled', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ['settings', 'gateway_type', "VARCHAR(20) NOT NULL DEFAULT 'zarinpal'"],
        ['settings', 'gateway_merchant_id', 'VARCHAR(190) NULL'], ['settings', 'gateway_api_key', 'VARCHAR(255) NULL'],
        ['settings', 'gateway_sandbox', 'TINYINT(1) NOT NULL DEFAULT 1'],
        ['settings', 'gateway_min_charge', 'BIGINT NOT NULL DEFAULT 100000'],
        ['settings', 'gateway_max_charge', 'BIGINT NOT NULL DEFAULT 50000000'],
        ['settings', 'z2c_bank_name', 'VARCHAR(120) NULL'], ['settings', 'z2c_account_name', 'VARCHAR(160) NULL'],
        ['settings', 'z2c_card_number', 'VARCHAR(40) NULL'],
        ['settings', 'actionbar_json', 'TEXT NULL'],
        ['settings', 'privacy_text', 'TEXT NULL'],
        ['settings', 'contact_form_enabled', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ['settings', 'contact_email', 'VARCHAR(190) NULL'], ['settings', 'contact_phone', 'VARCHAR(40) NULL'],
        ['settings', 'contact_telegram', 'VARCHAR(190) NULL'], ['settings', 'contact_instagram', 'VARCHAR(190) NULL'],
        ['settings', 'contact_address', 'VARCHAR(300) NULL'],
        ['bk_gateway_payments', 'order_id', 'VARCHAR(190) NULL'],
    ];
}

/** جدول‌های ماژول‌ها: [نام جدول => DDL] */
function bk_schema_tables(): array {
    return [
        'tickets' => "CREATE TABLE tickets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL,
            destination VARCHAR(20) NOT NULL DEFAULT 'support', seller_id INT UNSIGNED NULL,
            order_id INT UNSIGNED NULL, category VARCHAR(80) NOT NULL DEFAULT 'عمومی',
            priority VARCHAR(20) NOT NULL DEFAULT 'normal', title VARCHAR(255) NOT NULL,
            body TEXT NOT NULL, assigned_to INT UNSIGNED NULL, status VARCHAR(20) NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tickets_user(user_id), INDEX idx_tickets_status(status), INDEX idx_tickets_assigned(assigned_to)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'ticket_messages' => "CREATE TABLE ticket_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_id INT UNSIGNED NOT NULL,
            sender_id INT UNSIGNED NOT NULL, body TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ticket_messages_ticket(ticket_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'contact_messages' => "CREATE TABLE contact_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NULL,
            name VARCHAR(160) NOT NULL, email VARCHAR(190) NULL, phone VARCHAR(30) NULL,
            subject VARCHAR(255) NOT NULL, body TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'new',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_contact_status(status), INDEX idx_contact_user(user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'bk_gateway_payments' => "CREATE TABLE bk_gateway_payments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL,
            amount BIGINT NOT NULL, gateway VARCHAR(30) NOT NULL, authority VARCHAR(190) NULL,
            order_id VARCHAR(190) NULL, reference VARCHAR(190) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, verified_at DATETIME NULL,
            UNIQUE KEY uq_gateway_authority(gateway, authority), INDEX idx_gateway_user_status(user_id,status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
}

/** آیا ستونی در جدول وجود دارد؟ */
function bk_col_exists($pdo, string $table, string $column): bool {
    $q = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $q->execute([$table, $column]);
    return (bool)$q->fetchColumn();
}

/** آیا جدولی وجود دارد؟ */
function bk_table_exists($pdo, string $table): bool {
    $q = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $q->execute([$table]);
    return (bool)$q->fetchColumn();
}

/**
 * اعمال کامل اسکیمای ماژول‌ها (ستون‌ها + جدول‌ها) به‌صورت امن و قابل تکرار.
 * هر آیتم جداگانه بررسی و اعمال می‌شود؛ خطای یک آیتم بقیه را متوقف نمی‌کند.
 *
 * خروجی: آرایه‌ای از گزارش‌ها: [مورد, وضعیت(added|skipped|error), جزئیات]
 */
function bk_apply_schema($pdo): array {
    $report = [];

    foreach (bk_schema_columns() as $def) {
        [$table, $column, $definition] = $def;
        $label = $table . '.' . $column;
        try {
            if (bk_col_exists($pdo, $table, $column)) {
                $report[] = [$label, 'skipped', 'از قبل موجود بود'];
            } else {
                $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
                $report[] = [$label, 'added', 'اضافه شد'];
            }
        } catch (Throwable $e) {
            $report[] = [$label, 'error', $e->getMessage()];
        }
    }

    foreach (bk_schema_tables() as $name => $ddl) {
        try {
            if (bk_table_exists($pdo, $name)) {
                $report[] = [$name, 'skipped', 'جدول از قبل موجود بود'];
            } else {
                $pdo->exec($ddl);
                $report[] = [$name, 'added', 'جدول ساخته شد'];
            }
        } catch (Throwable $e) {
            $report[] = [$name, 'error', $e->getMessage()];
        }
    }

    return $report;
}

/** خلاصهٔ آماری گزارش bk_apply_schema */
function bk_schema_summary(array $report): array {
    $added = $skipped = $errors = 0;
    foreach ($report as $r) {
        if ($r[1] === 'added') $added++;
        elseif ($r[1] === 'skipped') $skipped++;
        elseif ($r[1] === 'error') $errors++;
    }
    return [$added, $skipped, $errors];
}
