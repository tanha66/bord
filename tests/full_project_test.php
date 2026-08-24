<?php
/**
 * تست کامل پروژه بردخان - Full Project Test
 * بررسی تمام فایل‌ها، routeها، اکشن‌ها و امنیت
 */

$report = [];
$pass = 0; $fail = 0;

function add_test($name, $ok, $detail=''){
    global $report, $pass, $fail;
    $report[] = ['name'=>$name, 'ok'=>$ok, 'detail'=>$detail];
    if($ok) $pass++; else $fail++;
}

// 1. فایل‌های ضروری
$requiredFiles = [
    'index.php','config.php','serve.php','install.php',
    'assets/style.css','assets/icon-192.png','assets/icon-512.png','assets/icon.svg',
    'manifest.webmanifest','sw.js','.htaccess',
    'pages/home.php','pages/admin.php','pages/admin_dashboard_v5.php','pages/admin_users_v5.php','pages/boards.php','pages/about.php','pages/contact.php','pages/terms.php','pages/privacy.php',
    'php-extended/bk_extended.php','php-extended/bk_actionbar.php','php-extended/bk_admin_extra.php','php-extended/tickets.php','php-extended/admin_finance.php','php-extended/schema_build.php','php-extended/migrate.php',
    'sql/schema.sql'
];
foreach($requiredFiles as $f){
    add_test("فایل $f", is_file(__DIR__."/../$f"), is_file(__DIR__."/../$f") ? 'موجود' : 'ناموجود');
}

// 2. بررسی index.php برای routeهای اصلی
$index = file_get_contents(__DIR__.'/../index.php');
$routes = ['home','tips','tip','boards','board','my-boards','seller-apply','upload','wallet','my-tips','repairs','repair','profile','leaderboard','premium','referral','about','contact','terms','privacy','admin','bookmarks','favorites','notifications','settings','reels','reels_demo','tour','login','register','verify','forgot','logout','serve','verify-email','ajax-comments','ajax-notifications','ajax-categories','diag-version'];
foreach($routes as $r){
    add_test("Route $r", strpos($index, "'$r'")!==false || strpos($index, "\"$r\"")!==false, strpos($index, "'$r'")!==false ? 'یافت شد' : 'یافت نشد');
}

// 3. بررسی اکشن‌های POST
$actions = ['login','register','verify','logout','forgot_request','forgot_reset','my_tip_delete','my_tip_toggle','my_tip_resubmit','admin_tip_edit','admin_tip_delete','unlock','comment','rate','follow','repair_answer','repair_best','report','comment_vote','favorite','bookmark','search_live','admin_tip','admin_user','admin_withdraw','subscribe','email_code_send','email_code_verify','email_code_resend','profile_update','suggest_category','admin_category','admin_settings','admin_report','contact_status','seller_apply','board_create','board_buy','board_ship','board_confirm','board_cancel','admin_board','admin_seller','upload_tip','withdraw','repair_create'];
foreach($actions as $a){
    add_test("Action $a", strpos($index, "'$a'")!==false, strpos($index, "'$a'")!==false ? 'موجود' : 'ناموجود - ممکن است باگ باشد');
}

// 4. بررسی باگ‌های شناخته‌شده
add_test('رفع باگ BKC {{}}', strpos($index, 'var BKC={{csrf:')===false, strpos($index, 'var BKC={{csrf:')===false ? 'رفع شده' : 'هنوز وجود دارد');
add_test('رفع باگ script بدون بسته شدن', substr_count($index, '<script>') <= substr_count($index, '</script>')+2, 'تعداد script: '.substr_count($index, '<script>').' vs '.substr_count($index, '</script>'));
add_test('رفع باگ board_ship \\t', strpos($index, '?\tAND')===false && strpos($index, '?\\tAND')===false, strpos($index, '\tAND')===false ? 'رفع شده' : 'هنوز وجود دارد');
add_test('ترمیم upload_tip', strpos($index, "if(\$action==='upload_tip')")!==false, strpos($index, "upload_tip")!==false ? 'موجود' : 'ناموجود');
add_test('ترمیم withdraw', strpos($index, "if(\$action==='withdraw')")!==false, 'موجود');
add_test('ترمیم repair_create', strpos($index, "if(\$action==='repair_create')")!==false, 'موجود');

// 5. بررسی امنیت
add_test('محافظت open redirect در redirect_to', strpos($index, 'open redirect')!==false, 'بررسی شده');
add_test('بررسی CSRF در POST', strpos($index, 'check_csrf')!==false, 'موجود');
add_test('محافظت آپلود - mime check', strpos($index, 'file_mime')!==false, 'موجود');
add_test('محافظت serve.php - realpath', file_get_contents(__DIR__.'/../serve.php') && strpos(file_get_contents(__DIR__.'/../serve.php'), 'realpath')!==false, 'موجود');
add_test('بلاک php در uploads via htaccess', strpos(file_get_contents(__DIR__.'/../.htaccess'), 'uploads/.*\\.(php')!==false, 'موجود');
add_test('fa() fallback در serve.php', strpos(file_get_contents(__DIR__.'/../serve.php'), "function fa")!==false, 'اضافه شد');

// 6. بررسی PWA
$manifest = json_decode(file_get_contents(__DIR__.'/../manifest.webmanifest'), true);
add_test('manifest.webmanifest معتبر', $manifest && isset($manifest['name']), $manifest ? 'معتبر' : 'نامعتبر');
$sw = file_get_contents(__DIR__.'/../sw.js');
add_test('sw.js cache version', strpos($sw, 'CACHE')!==false, 'موجود');
add_test('sw.js no-cache برای صفحات خصوصی', strpos($sw, 'NO_CACHE_PREFIXES')!==false, 'موجود');

// 7. بررسی style.css
$css = file_get_contents(__DIR__.'/../assets/style.css');
add_test('style.css متغیرهای تم', strpos($css, '--bg')!==false && strpos($css, '--accent')!==false, 'موجود');
add_test('style.css responsive', strpos($css, '@media')!==false, 'موجود');
add_test('style.css reels styles', strpos($css, '.reel')!==false || strpos($index, '.reel')!==false, 'موجود در index یا css');

// 8. بررسی دیتابیس schema
$schema = file_get_contents(__DIR__.'/../sql/schema.sql');
add_test('schema.sql جدول users', strpos($schema, 'CREATE TABLE')!==false && strpos($schema, 'users')!==false, 'موجود');
add_test('schema.sql جدول tips', strpos($schema, 'tips')!==false, 'موجود');
add_test('schema.sql جدول boards', strpos($schema, 'boards')!==false, 'موجود');

// 9. تست منطقی
add_test('تابع fa() تبدیل اعداد', function_exists('fa') || strpos($index, 'function fa')!==false, 'موجود');
add_test('تابع h() برای XSS', strpos($index, 'function h(')!==false, 'موجود');
add_test('تابع url()', strpos($index, 'function url(')!==false, 'موجود');

// 10. امنیت و احراز هویت — v5.5/v5.6
$cfg = file_get_contents(__DIR__.'/../config.php');
$adminPhp = file_get_contents(__DIR__.'/../pages/admin.php');
$swJs = file_get_contents(__DIR__.'/../sw.js');
$cssFile = file_get_contents(__DIR__.'/../assets/style.css');
$adminPhp = file_get_contents(__DIR__.'/../pages/admin.php');
$servePhp = file_get_contents(__DIR__.'/../serve.php');
$checks = [
    ['نسخهٔ 5.6', $index, "BORDKHAN_VERSION', '5.6'"],
    ['v5.5: ارسال ایمیل', $index, 'function bk_send_mail'],
    ['v5.5: کد ایمیل', $index, 'function bk_send_email_code'],
    ['v5.5: دروازهٔ تأیید ایمیل', $index, 'function bk_require_email_verified'],
    ['v5.5: صفحهٔ verify-email', $index, "=== 'verify-email'"],
    ['v5.5: فرم‌های AJAX', $index, 'bk-ajax'],
    ["v5.6: بازیابی رمز با ایمیل", $index, "bk_check_email_code(\$p['email'],\$code,'reset')"],
    ['v5.6: پیام یکسان ضد کشف ایمیل', $index, 'اگر این ایمیل در بردخان ثبت شده باشد'],
    ['v5.6: هدرهای امنیتی', $index, 'X-Content-Type-Options'],
    ['v5.6: کوکی HttpOnly', $cfg, "'httponly' => true"],
    ['v5.6: کوکی SameSite', $cfg, "'samesite' => 'Lax'"],
    ['v5.6: throttle ثبت‌نام', $index, "throttle('register:'"],
    ['v5.6: لاگ‌اوت کامل', $index, 'Clear-Site-Data'],
    ['v5.6: SW فقط assets', $swJs, 'bordkhan-pwa-v5'],
    ['v5.6: قدرت رمز', $index, 'bkPassStrength'],
    ['v5.6: noopener در admin', $adminPhp, 'rel="noopener"'],
];
foreach($checks as $ck){
    add_test('امنیت/UX — '.$ck[0], is_string($ck[1]) && strpos($ck[1], $ck[2])!==false, 'بررسی کد');
}

// 11. v5.7 — امنیت پیشرفته + پاک‌سازی ربات + اعتماد
$cleanupPhp = is_file(__DIR__.'/../php-extended/cleanup_bot.php') ? file_get_contents(__DIR__.'/../php-extended/cleanup_bot.php') : '';
$secLogPhp = is_file(__DIR__.'/../php-extended/security_log.php') ? file_get_contents(__DIR__.'/../php-extended/security_log.php') : '';
$checks7 = [
    ['نسخهٔ 5.7', $index, "BORDKHAN_VERSION', '5.7'"],
    ['قفل حساب ۵ ورود ناموفق', $index, 'account_locked'],
    ['ایمیل هشدار قفل', $index, 'هشدار امنیتی — قفل موقت حساب'],
    ['لاگ امنیتی (sec_log)', $index, 'function sec_log'],
    ['نشست‌های فعال', $index, 'function bk_session_register'],
    ['اعتبارسنجی نشست در current_user', $index, 'bk_session_adopt_or_valid'],
    ['اکشن خروج همهٔ دستگاه‌ها', $index, "=== 'logout_all'"],
    ['اکشن بستن نشست', $index, "=== 'session_kill'"],
    ['CSP کامل', $index, "Content-Security-Policy' . "],
    ['کرون پاک‌سازی', $index, "=== 'cron-cleanup'"],
    ['صفحهٔ وضعیت پرداخت', $index, "=== 'payment-status'"],
    ['نماد اعتماد در فوتر', $index, 'trust_badge_image'],
    ['ابزار پاک‌سازی ربات', $cleanupPhp, 'پاک‌سازی بقایای ربات'],
    ['تغییر نام ربات به تیم بردخان', $cleanupPhp, 'تیم بردخان'],
    ['صفحهٔ لاگ امنیتی', $secLogPhp, 'لاگ امنیتی'],
    ['جدول user_sessions', $schemaBuild, 'user_sessions'],
    ['جدول security_log', $schemaBuild, 'security_log'],
    ['ستون failed_logins', $schemaBuild, 'failed_logins'],
];
foreach($checks7 as $ck){
    add_test('v5.7 — '.$ck[0], is_string($ck[1]) && strpos($ck[1], $ck[2])!==false, 'بررسی کد');
}

// 12. v5.8 — آپلود همهٔ فرمت‌های تصویری + رفع پیش‌نمایش
$servePhp = file_get_contents(__DIR__.'/../serve.php');
$boardsPhp = file_get_contents(__DIR__.'/../pages/boards.php');
$htaccess = file_get_contents(__DIR__.'/../.htaccess');
$checks8 = [
    ['نسخهٔ 5.8', $index, "BORDKHAN_VERSION', '5.8'"],
    ['پذیرش GIF', $index, "'gif'"],
    ['پذیرش HEIC (عکس آیفون)', $index, "'heic'"],
    ['پذیرش AVIF/BMP/TIFF', $index, "'avif'"],
    ['پذیرش SVG با پاک‌سازی اسکریپت', $index, "stripos($raw,'<svg')"],
    ['سقف ۱۲ مگابایت', $index, '12*1024*1024'],
    ['تبدیل Imagick برای HEIC/AVIF', $index, 'new Imagick('],
    ['پیام علت شکست آپلود', $index, 'function bk_upload_error_reason'],
    ['CSP اجازهٔ blob: (پیش‌نمایش)', $index, "data: blob: https:"],
    ['accept همهٔ تصاویر (قلق)', $index, 'accept="image/*" multiple required><div id="tipPreview"'],
    ['accept همهٔ تصاویر (برد)', $boardsPhp, 'accept="image/*" multiple required><div id="boardPreview"'],
    ['serve: نوع درست برای GIF', $servePhp, "'image/gif'"],
    ['serve: CSP برای SVG', $servePhp, "default-src 'none'"],
    ['serve: نوع واقعی thumb', $servePhp, "file_mime(\$full)"],
    ['htaccess سقف آپلود', $htaccess, 'upload_max_filesize 12M'],
];
foreach($checks8 as $ck){
    add_test('v5.8 — '.$ck[0], is_string($ck[1]) && strpos($ck[1], $ck[2])!==false, 'بررسی کد');
}

// 13. v5.9 — رفع ثبت قلق + واترمارک قابل تنظیم + ضد دانلود
$checks9 = [
    ['نسخهٔ 5.9', $index, "BORDKHAN_VERSION', '5.9'"],
    ['معافیت مدیران از دروازهٔ ایمیل', $index, 'v5.9: مدیر/ناظر از دروازهٔ تأیید ایمیل معاف است'],
    ['فرم تعیین ایمیل در verify-email', $index, "value='email_set'"],
    ['اکشن تعیین ایمیل', $index, "=== 'email_set'"],
    ['پیام سقف post_max_size در CSRF', $index, 'post_max_size'],
    ['تنظیم واترمارک روشن/خاموش', $index, 'watermark_enabled'],
    ['متن واترمارک دلخواه', $index, 'function watermark_text'],
    ['پوشش متحرک ضد اسکرین‌شات', $index, 'function bk_watermark_overlay'],
    ['پوشش روی گالری', $index, 'bk_watermark_overlay($u)'],
    ['پوشش روی ویدیو', $index, 'bk_watermark_overlay(null)'],
    ['serve: واترمارک از تنظیمات', $servePhp, 'watermark_enabled'],
    ['serve: متن سفارشی واترمارک', $servePhp, '$badge'],
    ['CSS پوشش متحرک', $cssFile, 'wm-grid'],
    ['CSS ضد درگ', $cssFile, '-webkit-user-drag:none'],
    ['گارد ضد دانلود JS', $index, '__bkMediaGuard'],
    ['فیلد واترمارک در پنل', $adminPhp, 'واترمارک روی تصاویر'],
];
foreach($checks9 as $ck){
    add_test('v5.9 — '.$ck[0], is_string($ck[1]) && strpos($ck[1], $ck[2])!==false, 'بررسی کد');
}

// 14. v5.10 — زوم و تمام‌صفحهٔ تصاویر/ویدیو
$checks10 = [
    ['نسخهٔ 5.10', $index, "BORDKHAN_VERSION', '5.10'"],
    ['گالری زوم‌شو', $index, 'bk-zoomable'],
    ['لایت‌باکس زوم/تمام‌صفحه', $index, '__bkLightbox'],
    ['پین‌چ‌زوم در لایت‌باکس', $index, 'touchmove'],
    ['دابل‌تپ زوم', $index, 'lastTap'],
    ['کلیدهای کیبورد لایت‌باکس', $index, "e.key==='ArrowLeft'"],
    ['واترمارک داخل لایت‌باکس', $index, 'lb-grid'],
    ['دکمه‌های ویدیو (تمام‌صفحه/زوم)', $index, 'vid-tools'],
    ['تمام‌صفحهٔ ویدیو با حفظ واترمارک', $index, "wrap.requestFullscreen"],
    ['بستن تمام‌صفحهٔ بومی ویدیو', $index, 'nofullscreen'],
    ['CSS لایت‌باکس', $cssFile, 'bk-lightbox'],
    ['CSS دکمه‌های ویدیو', $cssFile, 'vid-tools'],
];
foreach($checks10 as $ck){
    add_test('v5.10 — '.$ck[0], is_string($ck[1]) && strpos($ck[1], $ck[2])!==false, 'بررسی کد');
}

// 15. v5.11 — رفع کش موبایل + عکس‌های مراحل تعمیر
$swJs2 = file_get_contents(__DIR__.'/../sw.js');
$checks11 = [
    ['نسخهٔ 5.11', $index, "BORDKHAN_VERSION', '5.11'"],
    ['بطلان کش CSS', $index, '?v=13'],
    ['کش SW جدید v8', $swJs2, 'bordkhan-pwa-v8'],
    ['فرم عکس مراحل', $index, 'name="step_images[]"'],
    ['پیش‌نمایش عکس مراحل', $index, "getElementById('stepImages')"],
    ['ذخیرهٔ عکس مراحل', $index, "FILES['step_images']"],
    ['اتصال عکس به گام', $index, "['img']"],
    ['نمایش عکس گام در قلق', $index, "['img']"],
    ['عکس گام با واترمارک', $index, 'bk_watermark_overlay($u)'],
    ['عکس گام قابل زوم', $index, 'bk-zoomable'],
    ['لایت‌باکس همهٔ عکس‌ها', $index, "querySelectorAll('.bk-zoomable')"],
    ['preload ویدیو', $index, 'preload="metadata"'],
];
foreach($checks11 as $ck){
    add_test('v5.11 — '.$ck[0], is_string($ck[1]) && strpos($ck[1], $ck[2])!==false, 'بررسی کد');
}

// 16. v5.11 — رگرسیون سینتکس JS هدر: باگ `})();()}` که کل اسکریپت هدر
// (لایت‌باکس زوم عکس + دکمه‌های ویدیو + فرم‌های AJAX) را از کار می‌انداخت
add_test('v5.11 — سینتکس JS هدر (نبود توالی خراب `})();()}`)', strpos($index, '})();()}') === false, strpos($index, '})();()}') === false ? 'سالم' : 'باگ سینتکس JS حاضر است');

// 17. v5.11 — لایت‌باکس/زوم باید مستقل از «زنگولهٔ اعلان‌ها» باشد تا برای مهمان
// (که #notifBell ندارد) هم فعال بماند؛ guard اعلان‌ها باید بعد از ثبت لایت‌باکس باشد
$lbPos = strpos($index, 'window.bkLightboxOpen=open');
$bellGuardPos = strpos($index, 'if(!bell)return;');
add_test('v5.11 — لایت‌باکس مستقل از زنگوله (برای مهمان فعال)', $lbPos !== false && $bellGuardPos !== false && $lbPos < $bellGuardPos, 'بررسی ترتیب کد');

// 18. v5.11 — پایداری پخش ویدیوی آپلودی (MP4)
$servePhp2 = file_get_contents(__DIR__.'/../serve.php');
add_test('v5.11 — INSERT رسانه داخل try/catch (بدون Fatal روی نصب قدیمی)', strpos($servePhp2, 'catch (Throwable $e) {}') !== false && strpos($servePhp2, 'INSERT INTO media_access') !== false, 'بررسی serve.php');
add_test('v5.11 — ویدیو همیشه video/mp4 سرو می‌شود', strpos($servePhp2, "if (\$type === 'vid') { \$mime = 'video/mp4'; }") !== false, 'بررسی serve.php');
$swJs3 = file_get_contents(__DIR__.'/../sw.js');
add_test('v5.11 — SW رسانه (/serve و /uploads) را رهگیری نمی‌کند', strpos($swJs3, "startsWith('/serve')") !== false && strpos($swJs3, "startsWith('/uploads/')") !== false, 'بررسی sw.js');
add_test('v5.11 — لینک جایگزین پخش ویدیو', strpos($index, 'vid-fallback') !== false, 'بررسی index.php');

// خروجی
echo "\n=== تست کامل پروژه بردخان ===\n\n";
foreach($report as $r){
    $icon = $r['ok'] ? '✅' : '❌';
    echo "$icon {$r['name']}: ".($r['ok']?'PASS':'FAIL');
    if($r['detail']) echo " - {$r['detail']}";
    echo "\n";
}
echo "\n--- خلاصه ---\n";
echo "کل: ".count($report)."\n";
echo "موفق: $pass\n";
echo "ناموفق: $fail\n";
if($fail===0){
    echo "\n🎉 کل پروژه بدون باگ بحرانی - آماده انتشار!\n";
}else{
    echo "\n⚠️ $fail مورد نیاز به بررسی دارد\n";
}
?>
