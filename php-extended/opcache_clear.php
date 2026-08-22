<?php
/**
 * Bordkhan — پاک‌سازی OPcache بعد از آپلود فایل‌های جدید.
 *
 * وقتی سرور کد قدیمی PHP را در حافظه نگه داشته است (اپ‌کش)،
 * فایل‌های تازه‌آپلودشده اجرا نمی‌شوند و تغییرات به نظر «اعمال نشده» می‌رسند.
 *
 * اجرا: /php-extended/opcache_clear.php?key=INSTALL_KEY
 * بعد از موفقیت، این فایل را حذف کنید.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require dirname(__DIR__) . '/config.php';

$key = (string)($_GET['key'] ?? '');
if (!hash_equals((string)INSTALL_KEY, $key)) { http_response_code(403); exit('forbidden'); }

$html = '';
$html .= '<b>OPcache:</b> ' . (function_exists('opcache_reset') ? 'فعال' : 'نصب نیست') . '<br>';
if (function_exists('opcache_reset')) {
    $ok = @opcache_reset();
    $html .= 'نتیجهٔ پاک‌سازی: ' . ($ok ? '<b style="color:#0a7a4a">انجام شد ✓</b>' : '<b style="color:#b3261e">ناموفق (ممکن است مجوز نباشد)</b>') . '<br>';
} else {
    $html .= 'پاک‌سازی لازم نیست.<br>';
}
if (function_exists('apcu_clear_cache')) { @apcu_clear_cache(); $html .= 'APCu: پاک شد ✓<br>'; }
if (function_exists('litespeed_purge_all') || isset($_SERVER['X-LSCACHE'])) { $html .= 'LiteSpeed Cache: اگر افزونهٔ LSCache در هاست فعال است، آن را هم از پنل هاست Purge کنید.<br>'; }
$html .= '<br><b>قدم بعدی:</b> صفحهٔ سایت را با Ctrl+Shift+R رفرش کنید و سپس برای اطمینان از اجرای نسخهٔ جدید، <span dir="ltr">/diag-version</span> را باز کنید.<br>';

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>پاک‌سازی کش PHP</title></head>'
   . '<body style="font-family:Tahoma;background:#f4f6f8;padding:24px"><div style="max-width:640px;margin:auto;background:#fff;border-radius:14px;padding:24px;border:1px solid #e3e8ee;font-size:14px;line-height:2.2">'
   . '<h2 style="margin-top:0">🧹 پاک‌سازی کش PHP</h2>' . $html
   . '<p style="font-size:11px;color:#8a94a0">پس از اتمام کار، این فایل (opcache_clear.php) را از سرور حذف کنید.</p>'
   . '</div></body></html>';
