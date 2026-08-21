<?php
/**
 * Bordkhan PHP parity migration — idempotent MySQL/MariaDB installer.
 * Usage: open /php-extended/migrate.php?key=INSTALL_KEY once, then delete/protect this file.
 */
require dirname(__DIR__) . '/config.php';
$key = (string)($_GET['key'] ?? $_POST['key'] ?? '');
if (!hash_equals((string)INSTALL_KEY, $key)) { http_response_code(403); exit('forbidden'); }
$pdo = db();

function bk_col(PDO $pdo, string $table, string $column): bool {
    $q = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $q->execute([$table, $column]);
    return (bool)$q->fetchColumn();
}
function bk_add_col(PDO $pdo, string $table, string $column, string $definition): void {
    if (!bk_col($pdo, $table, $column)) {
        $pdo->exec('ALTER TABLE `' . preg_replace('/[^a-zA-Z0-9_]/', '', $table) . '` ADD COLUMN `' . preg_replace('/[^a-zA-Z0-9_]/', '', $column) . '` ' . $definition);
    }
}
function bk_table(PDO $pdo, string $table): bool {
    $q = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $q->execute([$table]);
    return (bool)$q->fetchColumn();
}

$columns = [
    ['users', 'address', 'TEXT NULL'], ['users', 'postal_code', 'VARCHAR(20) NULL'],
    ['users', 'landline', 'VARCHAR(30) NULL'], ['users', 'mobile', 'VARCHAR(30) NULL'],
    ['users', 'city', 'VARCHAR(100) NULL'], ['users', 'support_group', 'VARCHAR(80) NULL'],
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
];
foreach ($columns as [$table, $column, $definition]) bk_add_col($pdo, $table, $column, $definition);

$tables = [
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
    'bk_gateway_payments' => "CREATE TABLE bk_gateway_payments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL,
        amount BIGINT NOT NULL, gateway VARCHAR(30) NOT NULL, authority VARCHAR(190) NULL,
        reference VARCHAR(190) NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, verified_at DATETIME NULL,
        UNIQUE KEY uq_gateway_authority(gateway, authority), INDEX idx_gateway_user_status(user_id,status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
foreach ($tables as $name => $ddl) if (!bk_table($pdo, $name)) $pdo->exec($ddl);

header('Content-Type: text/html; charset=utf-8');
echo '<div dir="rtl" style="font:16px Tahoma;max-width:700px;margin:40px auto"><h2>مهاجرت PHP بردخان انجام شد</h2><p>ستون‌های آدرس/ارسال، تنظیمات درگاه، پرداخت‌ها، تیکت‌ها و گروه پشتیبانی آماده شدند.</p><b>این فایل را حذف یا محدود کنید.</b></div>';
