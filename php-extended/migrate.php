<?php
/**
 * Bordkhan PHP migration — idempotent MySQL/MariaDB installer با عیب‌یابی داخلی.
 *
 * اجرای مهاجرت:    /php-extended/migrate.php?key=INSTALL_KEY
 * حالت عیب‌یابی:    /php-extended/migrate.php?key=INSTALL_KEY&diag=1
 *
 * بعد از موفقیت، این فایل را از سرور حذف کنید.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
@set_time_limit(300);

/* ---------- نمایش صفحه نتیجه (به‌جای صفحه سفید 500) ---------- */
function bk_migrate_page(string $title, string $body, int $code = 200, bool $ok = true): void {
    static $shown = false;
    if ($shown) return;
    $shown = true;
    if (!headers_sent()) { http_response_code($code); header('Content-Type: text/html; charset=utf-8'); }
    $color = $ok ? '#0a7a4a' : '#b3261e';
    $bg    = $ok ? '#eafaf1' : '#fdecea';
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title></head>'
       . '<body style="font-family:Tahoma,sans-serif;background:#f4f6f8;padding:24px">'
       . '<div style="max-width:820px;margin:0 auto;background:#fff;border:1px solid #e3e8ee;border-radius:14px;padding:26px 28px">'
       . '<h2 style="margin:0 0 10px;color:' . $color . '">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>'
       . '<div style="background:' . $bg . ';border-radius:10px;padding:14px 16px;font-size:14px;line-height:2.1;color:#1c2733">' . $body . '</div>'
       . '<p style="font-size:11px;color:#8a94a0;margin-top:14px">بردخان · migrate.php — پس از اتمام کار، این فایل را از سرور حذف کنید.</p>'
       . '</div></body></html>';
    exit;
}

/* نمایش خطاهای مرگبار به‌جای صفحه 500 */
register_shutdown_function(function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        $msg = htmlspecialchars($e['message'], ENT_QUOTES, 'UTF-8') . ' (خط ' . (int)$e['line'] . ')';
        bk_migrate_page('خطای مرگبار PHP', 'خطای غیرمنتظره‌ای رخ داد:<br><b dir="ltr">' . $msg . '</b><br>اگر این پیام را می‌بینید، مشکل از همین بخش است — آن را برای پشتیبانی ارسال کنید.', 500, false);
    }
});

require dirname(__DIR__) . '/config.php';

$key  = (string)($_GET['key'] ?? $_POST['key'] ?? '');
$diag = !empty($_GET['diag']) || !empty($_POST['diag']);

if (!hash_equals((string)INSTALL_KEY, $key)) { http_response_code(403); exit('forbidden'); }

/* ---------- بررسی نسخه PHP ---------- */
if (PHP_VERSION_ID < 80100) {
    bk_migrate_page('نسخه PHP قدیمی است', 'بردخان به <b>PHP ۸.۱ یا بالاتر</b> نیاز دارد.<br>نسخه فعلی سرور: <b dir="ltr">' . htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') . '</b><br>از هاست بخواهید نسخه PHP را ارتقا دهد (cPanel ← Select PHP Version).', 500, false);
}

/* ---------- اتصال دیتابیس ---------- */
try {
    $pdo = db();
} catch (Throwable $e) {
    bk_migrate_page('اتصال به دیتابیس برقرار نشد', 'خطا: <b>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</b><br>مقادیر DB_HOST / DB_NAME / DB_USER / DB_PASS را در فایل config.php بررسی کنید و مطمئن شوید دیتابیس و کاربر آن در هاست ساخته شده‌اند.', 500, false);
}

/* ---------- ابزارهای کمکی ---------- */
function bk_col($pdo, string $table, string $column): bool {
    $q = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $q->execute([$table, $column]);
    return (bool)$q->fetchColumn();
}
function bk_table($pdo, string $table): bool {
    $q = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $q->execute([$table]);
    return (bool)$q->fetchColumn();
}

/* ---------- تعریف مهاجرت‌ها ---------- */
$columns = [
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

$requiredTables = ['users', 'settings', 'tips', 'boards', 'board_orders', 'wallet_transactions'];

/* ================= حالت عیب‌یابی ================= */
if ($diag) {
    $rows = '';
    $rows .= '<b>نسخه PHP:</b> <span dir="ltr">' . htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') . '</span> — '
           . (PHP_VERSION_ID >= 80100 ? '<b style="color:#0a7a4a">مناسب ✓</b>' : '<b style="color:#b3261e">قدیمی ✗ (به ۸.۱+ نیاز است)</b>') . '<br>';
    foreach (['pdo_mysql' => 'PDO MySQL', 'curl' => 'cURL', 'gd' => 'GD (واترمارک)', 'mbstring' => 'mbstring', 'fileinfo' => 'fileinfo'] as $ext => $label) {
        $rows .= 'افزونه ' . $label . ': ' . (extension_loaded($ext) ? '<b style="color:#0a7a4a">نصب است ✓</b>' : '<b style="color:#b3261e">نصب نیست ✗</b>') . '<br>';
    }
    $rows .= '<b>کلید نصب:</b> ' . ((string)INSTALL_KEY === 'CHANGE_THIS_INSTALL_KEY' ? '<b style="color:#b3261e">هنوز پیش‌فرض است — install.php را اجرا نکرده‌اید</b>' : '<b style="color:#0a7a4a">تنظیم شده ✓</b>') . '<br><br>';
    $rows .= '<b>جدول‌های پایه (ساخته‌شده توسط install.php):</b><br>';
    foreach ($requiredTables as $t) {
        try { $exists = bk_table($pdo, $t); } catch (Throwable $e) { $exists = false; }
        $rows .= '• ' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . ': ' . ($exists ? '<b style="color:#0a7a4a">موجود ✓</b>' : '<b style="color:#b3261e">ناموجود ✗</b>') . '<br>';
    }
    $rows .= '<br><b>جدول‌های این ماژول:</b><br>';
    foreach (array_keys($tables) as $t) {
        try { $exists = bk_table($pdo, $t); } catch (Throwable $e) { $exists = false; }
        $rows .= '• ' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . ': ' . ($exists ? '<b style="color:#0a7a4a">موجود ✓</b>' : 'ناموجود') . '<br>';
    }
    $missingCols = 0;
    foreach ($columns as [$t, $c, $d]) {
        try { if (!bk_col($pdo, $t, $c)) $missingCols++; } catch (Throwable $e) {}
    }
    $rows .= '<br><b>ستون‌های این ماژول:</b> ' . count($columns) . ' ستون بررسی شد — '
           . ($missingCols === 0 ? '<b style="color:#0a7a4a">همه موجودند ✓ (اگر جدول‌های پایه موجود بودند)</b>' : '<b>' . $missingCols . ' ستون هنوز اضافه نشده است</b>') . '<br>';
    $rows .= '<br><b>نتیجه:</b> ' . (bk_table($pdo, 'users') && bk_table($pdo, 'settings') ? 'محیط آماده اجرای مهاجرت است؛ همین آدرس را بدون diag باز کنید.' : 'ابتدا <b>install.php</b> را اجرا کنید تا جدول‌های پایه ساخته شوند.') ;
    bk_migrate_page('عیب‌یابی نصب بردخان', $rows, 200, true);
}

/* ================= پیش‌نیازها ================= */
$missing = [];
foreach ($requiredTables as $t) {
    if (!bk_table($pdo, $t)) $missing[] = $t;
}
if ($missing) {
    bk_migrate_page('جدول‌های پایه وجود ندارند', 'این جدول‌ها پیدا نشدند: <b>' . htmlspecialchars(implode('، ', $missing), ENT_QUOTES, 'UTF-8') . '</b><br><br>یعنی <b>install.php</b> هنوز با موفقیت اجرا نشده است.<br>۱) اول <span dir="ltr">install.php</span> را باز کنید و مراحل نصب را کامل کنید.<br>۲) بعد دوباره همین صفحه را اجرا کنید.<br><br>اگر install.php را اجرا کرده‌اید اما این جدول‌ها نیستند، نام دیتابیس در config.php اشتباه است (DB_NAME).', 500, false);
}

/* ================= اجرای مهاجرت ================= */
$report = [];
$errors = [];

foreach ($columns as [$table, $column, $definition]) {
    $label = $table . '.' . $column;
    try {
        if (bk_col($pdo, $table, $column)) {
            $report[] = [$label, 'skipped', 'از قبل موجود بود'];
        } else {
            $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
            $report[] = [$label, 'added', 'اضافه شد'];
        }
    } catch (Throwable $e) {
        $report[] = [$label, 'error', $e->getMessage()];
        $errors[] = $label . ': ' . $e->getMessage();
    }
}

foreach ($tables as $name => $ddl) {
    try {
        if (bk_table($pdo, $name)) {
            $report[] = [$name, 'skipped', 'جدول از قبل موجود بود'];
        } else {
            $pdo->exec($ddl);
            $report[] = [$name, 'added', 'جدول ساخته شد'];
        }
    } catch (Throwable $e) {
        $report[] = [$name, 'error', $e->getMessage()];
        $errors[] = $name . ': ' . $e->getMessage();
    }
}

/* ================= نتیجه ================= */
$okCount = $skipCount = 0;
foreach ($report as $r) { if ($r[1] === 'added') $okCount++; elseif ($r[1] === 'skipped') $skipCount++; }

$body = '';
if (!$errors) {
    $body = '<b style="color:#0a7a4a">مهاجرت با موفقیت انجام شد.</b><br>'
          . $okCount . ' مورد اضافه شد · ' . $skipCount . ' مورد از قبل موجود بود.<br><br>'
          . 'حالا: <b>این فایل (migrate.php) را از سرور حذف کنید.</b>';
} else {
    $body = '<b style="color:#b3261e">' . count($errors) . ' خطا در حین مهاجرت رخ داد</b> (بقیه موارد با موفقیت انجام شد).<br><br>'
          . '<b>جزئیات خطاها:</b><br>' . implode('<br>', array_map(fn($e) => '• ' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8'), $errors)) . '<br><br>'
          . '<b>دلایل رایج:</b><br>'
          . '۱) کاربر دیتابیس مجوز ALTER/CREATE ندارد — از هاست خود بخواهید دسترسی کامل دیتابیس بدهد.<br>'
          . '۲) جدول‌های پایه کامل نیستند — ابتدا install.php را اجرا کنید.<br>'
          . '۳) نسخه MySQL/MariaDB خیلی قدیمی است.<br>'
          . '۴) وقفهٔ زمانی سرور (timeout) — دوباره امتحان کنید؛ مهاجرت ایمن است و قابل تکرار.';
}

$tableHtml = '<table style="width:100%;border-collapse:collapse;font-size:12px;margin-top:14px"><tr style="background:#f0f4f8">'
           . '<th style="padding:7px;text-align:right;border:1px solid #e3e8ee">مورد</th>'
           . '<th style="padding:7px;border:1px solid #e3e8ee">وضعیت</th>'
           . '<th style="padding:7px;text-align:right;border:1px solid #e3e8ee">جزئیات</th></tr>';
foreach ($report as $r) {
    $badge = $r[1] === 'added' ? '<b style="color:#0a7a4a">اضافه شد ✓</b>' : ($r[1] === 'skipped' ? '<span style="color:#7a8794">موجود بود</span>' : '<b style="color:#b3261e">خطا ✗</b>');
    $tableHtml .= '<tr><td style="padding:6px;border:1px solid #e3e8ee" dir="ltr">' . htmlspecialchars($r[0], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:6px;border:1px solid #e3e8ee">' . $badge . '</td>'
                . '<td style="padding:6px;border:1px solid #e3e8ee;font-size:11px">' . htmlspecialchars((string)$r[2], ENT_QUOTES, 'UTF-8') . '</td></tr>';
}
$tableHtml .= '</table>';

bk_migrate_page($errors ? 'مهاجرت با خطا مواجه شد' : 'مهاجرت بردخان انجام شد', $body . $tableHtml, $errors ? 500 : 200, !$errors);
