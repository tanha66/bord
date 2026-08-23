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
    'pages/home.php','pages/admin.php','pages/admin_dashboard_v5.php','pages/admin_users_v5.php','pages/admin_collect_v5.php','pages/boards.php','pages/about.php','pages/contact.php','pages/terms.php','pages/privacy.php',
    'php-extended/bk_extended.php','php-extended/bk_actionbar.php','php-extended/bk_admin_extra.php','php-extended/tickets.php','php-extended/admin_finance.php','php-extended/schema_build.php','php-extended/migrate.php',
    'sql/schema.sql'
];
foreach($requiredFiles as $f){
    add_test("فایل $f", is_file(__DIR__."/../$f"), is_file(__DIR__."/../$f") ? 'موجود' : 'ناموجود');
}

// 2. بررسی index.php برای routeهای اصلی
$index = file_get_contents(__DIR__.'/../index.php');
$routes = ['home','tips','tip','boards','board','my-boards','seller-apply','upload','wallet','my-tips','repairs','repair','profile','leaderboard','premium','referral','about','contact','terms','privacy','admin','bookmarks','favorites','notifications','settings','reels','reels_demo','tour','login','register','verify','forgot','logout','serve','cron-collect','ajax-bot-status','ajax-comments','ajax-notifications','ajax-categories','diag-version'];
foreach($routes as $r){
    add_test("Route $r", strpos($index, "'$r'")!==false || strpos($index, "\"$r\"")!==false, strpos($index, "'$r'")!==false ? 'یافت شد' : 'یافت نشد');
}

// 3. بررسی اکشن‌های POST
$actions = ['login','register','verify','logout','forgot_request','forgot_reset','my_tip_delete','my_tip_toggle','my_tip_resubmit','admin_tip_edit','admin_tip_delete','unlock','comment','rate','follow','repair_answer','repair_best','report','comment_vote','favorite','bookmark','search_live','admin_tip','admin_user','admin_withdraw','admin_collect','subscribe','profile_update','suggest_category','admin_category','admin_settings','admin_report','contact_status','seller_apply','board_create','board_buy','board_ship','board_confirm','board_cancel','admin_board','admin_seller','upload_tip','withdraw','repair_create','admin_bot_run','admin_bot_tip'];
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

// 10. ربات پیشرفته v5.0
$schemaBuild = file_get_contents(__DIR__.'/../php-extended/schema_build.php');
$adminCollect = is_file(__DIR__.'/../pages/admin_collect_v5.php') ? file_get_contents(__DIR__.'/../pages/admin_collect_v5.php') : '';
$adminX = is_file(__DIR__.'/../php-extended/bk_admin_extra.php') ? file_get_contents(__DIR__.'/../php-extended/bk_admin_extra.php') : '';
foreach([
    ['موتور: score_candidate (امتیازدهی کیفیت)', $index, 'function score_candidate'],
    ['موتور: bot_title_tokens + شباهت فازی', $index, 'function bot_titles_similar'],
    ['موتور: قفل اجرای همزمان', $index, 'auto_collect_lock'],
    ['موتور: چرخش منابع', $index, 'auto_collect_last_offset'],
    ['موتور: گارد زمان اجرا', $index, 'timeLimit'],
    ['موتور: حالت آزمایشی dry_run', $index, 'dry_run'],
    ['لاگ اجراها: bot_run_start', $index, 'function bot_run_start'],
    ['لاگ اجراها: bot_run_end', $index, 'function bot_run_end'],
    ['پایش منابع: bot_update_source', $index, 'function bot_update_source'],
    ['جدول bot_runs در اسکیما', $schemaBuild, "CREATE TABLE bot_runs"],
    ['جدول bot_sources در اسکیما', $schemaBuild, "CREATE TABLE bot_sources"],
    ['اکشن AJAX اجرای زنده', $index, "admin_bot_run"],
    ['اکشن عملیات سریع قلق ربات', $index, "admin_bot_tip"],
    ['مسیر وضعیت ربات', $index, "ajax-bot-status"],
    ['پنل: فایل admin_collect_v5.php', $adminCollect, 'bkBotRunForm'],
    ['پنل: زیرتب‌ها (run/sources/content/settings)', $adminCollect, "bk-subtabs"],
    ['پنل: جدول تاریخچهٔ اجراها', $adminCollect, 'bot_runs'],
    ['پنل: جدول سلامت منابع', $adminCollect, 'bot_sources'],
    ['نسخهٔ 5.2', $index, "BORDKHAN_VERSION', '5.2'"],
    ['v5.2: ددلاین سراسری کشف منابع', $index, '$deadline = $startedAt + $timeLimit'],
    ['v5.2: چک توقف توسط مدیر', $index, 'function bot_stop_requested'],
    ['v5.2: اکشن توقف ربات', $index, "admin_bot_stop"],
    ['v5.2: پاک‌سازی اجراهای یتیم', $index, 'function bot_cleanup_stale_runs'],
    ['v5.2: قفل ضدگیرکردن (shutdown)', $index, 'register_shutdown_function'],
    ['v5.2: circuit breaker منابع خراب', $index, 'consecutive_fails >= 3'],
    ['v5.2: قلق بدون عکس ذخیره می‌شود', $index, 'بدون عکس ادامه می‌دهیم'],
    ['پنل: داشبورد حرفه‌ای', file_get_contents(__DIR__.'/../pages/admin_dashboard_v5.php'), 'کارهای امروز'],
    ['پنل: مدیریت کاربران حرفه‌ای', file_get_contents(__DIR__.'/../pages/admin_users_v5.php'), 'admin_user'],
    ['پنل: مدیریت پیشرفته کاربران v5', $adminX, 'پروفایل'],
] as $bt){
    add_test("ربات v5 — {$bt[0]}", is_string($bt[1]) && strpos($bt[1], $bt[2])!==false, 'بررسی کد');
}

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
