<?php
/**
 * Bordkhan shared-host configuration.
 * Edit these values before opening install.php.
 */

define('DB_HOST', getenv('BORDKHAN_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('BORDKHAN_DB_NAME') ?: 'cpuser_bordkhan');
define('DB_USER', getenv('BORDKHAN_DB_USER') ?: 'cpuser_bordkhan_user');
define('DB_PASS', getenv('BORDKHAN_DB_PASS') ?: 'CHANGE_THIS_PASSWORD');
define('SITE_URL', rtrim(getenv('BORDKHAN_SITE_URL') ?: 'https://bordkhan.ir', '/'));
define('SITE_NAME', 'بردخان');
define('INSTALL_KEY', getenv('BORDKHAN_INSTALL_KEY') ?: 'CHANGE_THIS_INSTALL_KEY');
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('UPLOAD_URL', SITE_URL . '/uploads');

date_default_timezone_set('Asia/Tehran');
if (session_status() !== PHP_SESSION_ACTIVE) {
    /* v5.6: کوکی نشست امن — HttpOnly + SameSite + Secure در HTTPS */
    @session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_name('bordkhan_session');
    session_start();
}

/* ---------- helper های مقاوم برای هاست‌های اشتراکی ---------- */

/* اگر افزونه mbstring نصب نباشد، fallback ساده (جلوگیری از خطای مرگبار) */
if (!function_exists('mb_strlen')) {
    function mb_strlen($s, $enc = null): int { return strlen((string)$s); }
    function mb_substr($s, $start, $len = null, $enc = null): string { $s = (string)$s; return $len === null ? substr($s, $start) : substr($s, $start, $len); }
}

/* تشخیص نوع MIME بدون وابستگی به fileinfo (finfo → mime_content_type → getimagesize) */
function file_mime(string $path): string {
    if (class_exists('finfo')) {
        try { $f = new finfo(FILEINFO_MIME_TYPE); $m = $f->file($path); if (is_string($m) && $m !== '') return $m; } catch (Throwable $e) {}
    }
    if (function_exists('mime_content_type')) { $m = @mime_content_type($path); if (is_string($m) && $m !== '') return $m; }
    $m = @getimagesize($path);
    if (is_array($m) && !empty($m['mime'])) return (string)$m['mime'];
    return '';
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        die('<div dir="rtl" style="font-family:Tahoma;padding:40px;max-width:700px;margin:auto"><h2>اتصال به دیتابیس برقرار نشد</h2><p>اطلاعات دیتابیس را در فایل config.php بررسی کنید.</p></div>');
    }
    return $pdo;
}
