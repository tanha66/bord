<?php
/**
 * Bordkhan — عیب‌یاب قدم‌به‌قدم (bk-diag.php)
 * ================================================
 * هر مرحله را جداگانه تست می‌کند و همان لحظه نتیجه را چاپ می‌کند.
 * حتی اگر خطای fatal رخ دهد، در پایین صفحه نمایش داده می‌شود.
 *
 *   https://bordkhan.ir/bk-diag.php          ← اجرای تست کامل
 *
 * ⚠️ بعد از رفع مشکل، این فایل را از سرور حذف کنید.
 */

/* جلوی بافر هاست بایستیم و خروجی از خط اول دیده شود */
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', 'off');
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
@error_reporting(E_ALL);
while (ob_get_level() > 0) { @ob_end_flush(); }
http_response_code(200);
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>عیب‌یاب بردخان</title></head>';
echo '<body style="font-family:Tahoma;background:#0f1a2b;color:#e8edf4;padding:20px;line-height:2.2">';
echo '<div style="max-width:900px;margin:auto">';
echo '<h1 style="color:#5eead4">🔍 عیب‌یاب بردخان</h1>';
echo str_pad(' ', 4096) . "\n";
@flush();

$STEP = 0;
function bk_step(string $title): void {
    global $STEP; $STEP++;
    echo '<div style="margin-top:14px;padding:8px 12px;background:#1c2b45;border-radius:10px"><b style="color:#93c5fd">' . $STEP . ') ' . htmlspecialchars($title) . '</b><br>';
    while (ob_get_level() > 0) { @ob_flush(); }
    @flush();
}
function bk_ok(string $msg): void { echo '✅ ' . htmlspecialchars($msg) . '<br>'; while (ob_get_level() > 0) { @ob_flush(); } @flush(); }
function bk_bad(string $msg): void { echo '❌ <span style="color:#fca5a5">' . htmlspecialchars($msg) . '</span><br>'; while (ob_get_level() > 0) { @ob_flush(); } @flush(); }
function bk_note(string $msg): void { echo 'ℹ️ ' . htmlspecialchars($msg) . '<br>'; while (ob_get_level() > 0) { @ob_flush(); } @flush(); }

/* نمایش هر خطای fatal که وسط کار رخ دهد */
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        echo '<div style="margin-top:16px;padding:12px;border:2px solid #d33;border-radius:10px">';
        echo '<b style="color:#fca5a5">خطای fatal:</b><br>';
        echo htmlspecialchars($e['message']) . '<br><small>خط ' . (int)$e['line'] . ' فایل ' . htmlspecialchars($e['file']) . '</small>';
        echo '</div>';
    }
    echo '</div></body></html>';
});

/* ---------- ۱) PHP ---------- */
bk_step('نسخهٔ PHP سرور');
bk_note('PHP ' . PHP_VERSION);
if (version_compare(PHP_VERSION, '7.4.0', '>=')) bk_ok('نسخهٔ PHP کافی است (۷.۴+)');
else bk_bad('نسخهٔ PHP قدیمی است — سید به PHP ۷.۴+ نیاز دارد');
foreach (['pdo', 'pdo_mysql', 'json', 'mbstring', 'fileinfo'] as $ext) {
    if (extension_loaded($ext)) bk_ok('افزونهٔ ' . $ext);
    else bk_bad('افزونهٔ ' . $ext . ' نصب نیست');
}
bk_note('محدودیت‌ها: max_execution_time=' . ini_get('max_execution_time') . ' | memory_limit=' . ini_get('memory_limit'));

/* ---------- ۲) محل فایل ---------- */
bk_step('محل فایل و پوشه‌ها');
bk_note('__DIR__ = ' . __DIR__);
$here = scandir(__DIR__);
if ($here === false) { bk_bad('نمی‌توان پوشهٔ فعلی را خواند!'); }
else {
    $need = ['index.php', 'config.php', 'serve.php'];
    foreach ($need as $n) {
        if (in_array($n, $here, true)) bk_ok($n . ' کنار همین فایل هست');
        else bk_bad($n . ' کنار همین فایل نیست — یعنی فایل در ریشهٔ public_html نیست');
    }
    $dirs = [];
    foreach ($here as $n) { if (is_dir(__DIR__ . '/' . $n) && $n !== '.' && $n !== '..') $dirs[] = $n; }
    bk_note('پوشه‌های همین محل: ' . implode('، ', array_slice($dirs, 0, 20)));
}

/* ---------- ۳) config ---------- */
bk_step('بارگذاری config.php');
$cfgTried = [__DIR__ . '/config.php', dirname(__DIR__) . '/config.php'];
$cfgLoaded = false;
foreach ($cfgTried as $cfg) {
    if (is_file($cfg)) {
        bk_note('پیدا شد: ' . $cfg);
        try { require_once $cfg; $cfgLoaded = true; bk_ok('config.php بدون خطا بار شد'); }
        catch (Throwable $ex) { bk_bad('خطا در بارگذاری config: ' . $ex->getMessage()); }
        break;
    }
}
if (!$cfgLoaded) bk_bad('config.php پیدا نشد — فایل diag باید کنار index.php در public_html باشد');
if (defined('INSTALL_KEY')) bk_ok('INSTALL_KEY تعریف است (طول: ' . strlen((string)INSTALL_KEY) . ' کاراکتر)');
else bk_bad('INSTALL_KEY در config تعریف نشده');
$key = isset($_GET['key']) ? (string)$_GET['key'] : '';
if ($key !== '' && defined('INSTALL_KEY')) {
    if (hash_equals((string)INSTALL_KEY, $key)) bk_ok('کلید ?key= با INSTALL_KEY می‌خواند');
    else bk_bad('کلید ?key= با INSTALL_KEY نمی‌خواند (همان کلیدی که در config.php است را بگذارید)');
}

/* ---------- ۴) دیتابیس ---------- */
bk_step('اتصال دیتابیس');
$pdo = null;
try {
    $pdo = db();
    bk_ok('اتصال برقرار شد — دیتابیس: ' . DB_NAME);
} catch (Throwable $ex) {
    bk_bad('اتصال دیتابیس نشد: ' . $ex->getMessage());
}
if ($pdo) {
    try {
        $c = (int)$pdo->query('SELECT COUNT(*) FROM tips')->fetchColumn();
        bk_ok('جدول tips هست — تعداد رکورد فعلی: ' . $c);
        $rows = $pdo->query("SELECT access_type, COUNT(*) c FROM tips WHERE status='published' GROUP BY access_type")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) bk_note('منتشرشدهٔ ' . $r['access_type'] . ': ' . $r['c']);
        $t = $pdo->query("SELECT COUNT(*) FROM tips WHERE video_url IS NOT NULL AND video_url != ''")->fetchColumn();
        bk_note('قلق‌های دارای ویدیو در دیتابیس: ' . (int)$t);
        try { $s = (int)$pdo->query('SELECT state_value FROM bk_seed_state LIMIT 1')->fetchColumn(); bk_note('bk_seed_state: ' . $s); }
        catch (Throwable $ex) { bk_note('bk_seed_state هنوز ساخته نشده (طبیعی است اگر سید جدید هنوز اجرا نشده)'); }
    } catch (Throwable $ex) { bk_bad('خواندن جدول tips: ' . $ex->getMessage()); }
}

/* ---------- ۵) دیتاست ---------- */
bk_step('پوشهٔ seed-data و دیتاست ۳۰۹ قلق');
$sd = '';
foreach ([__DIR__ . '/seed-data', __DIR__ . '/php-extended/seed-data'] as $c) {
    if (is_dir($c)) { $sd = $c; break; }
}
if ($sd === '') bk_bad('پوشهٔ seed-data پیدا نشد — باید seed-data کنار bk-diag.php باشد یا داخل php-extended/');
else {
    bk_ok('مسیر دیتاست: ' . $sd);
    $parts = glob($sd . '/part*.json');
    bk_note('تعداد فایل‌های part: ' . (is_array($parts) ? count($parts) : 0));
    $total = 0; $badFiles = [];
    foreach ((array)$parts as $p) {
        $j = json_decode((string)file_get_contents($p), true);
        if (!is_array($j)) { $badFiles[] = basename($p); continue; }
        $total += count($j);
    }
    if ($badFiles) bk_bad('فایل‌های JSON خراب: ' . implode('، ', $badFiles));
    else bk_ok('همهٔ partها سالم — مجموع قلق‌ها: ' . $total);
    if (is_file($sd . '/image_map.php')) bk_ok('image_map.php هست');
    else bk_bad('image_map.php داخل seed-data نیست');
    /* نمایش ۳ عنوان اول و آخر (شبیه‌ساز list=1) */
    $all = [];
    foreach ((array)$parts as $p) { $j = json_decode((string)file_get_contents($p), true); if (is_array($j)) foreach ($j as $t) $all[] = $t['title'] ?? '?'; }
    if ($all) {
        bk_note('نمونهٔ ۳ عنوان اول: ' . implode(' | ', array_slice($all, 0, 3)));
        bk_note('نمونهٔ ۳ عنوان آخر: ' . implode(' | ', array_slice($all, -3)));
    }
}

/* ---------- ۶) عکس‌ها ---------- */
bk_step('پوشهٔ عکس‌های سید و مقصد uploads');
$md = '';
foreach ([__DIR__ . '/uploads-seed/tips', __DIR__ . '/php-extended/uploads-seed/tips'] as $c) {
    if (is_dir($c)) { $md = $c; break; }
}
if ($md === '') bk_bad('پوشهٔ uploads-seed/tips پیدا نشد');
else {
    $tipFiles = glob($md . '/tip-*.jpg');
    bk_ok('مسیر: ' . $md . ' — تعداد tip-*.jpg: ' . (is_array($tipFiles) ? count($tipFiles) : 0));
}
$up = '';
if (defined('UPLOAD_DIR')) {
    $up = (string)UPLOAD_DIR;
    bk_note('UPLOAD_DIR از config: ' . $up);
    if (is_dir($up)) {
        bk_ok('پوشهٔ uploads وجود دارد');
        if (is_writable($up)) bk_ok('پوشهٔ uploads قابل نوشتن است');
        else bk_bad('پوشهٔ uploads قابل نوشتن نیست — دسترسی را روی 755 بگذارید');
        $existing = glob($up . '/tip-*.jpg');
        bk_note('عکس‌های tip-* که الان داخل uploads هستند: ' . (is_array($existing) ? count($existing) : 0));
    } else bk_bad('پوشهٔ uploads وجود ندارد: ' . $up);
} else bk_bad('UPLOAD_DIR در config تعریف نشده');

/* ---------- ۷) نتیجه ---------- */
bk_step('جمع‌بندی');
bk_note('اگر همهٔ مراحل بالا ✅ دارند، seed-all.php باید کار کند. در این صورت مشکل از محل آپلود فایل است: seed-all.php باید دقیقاً در public_html کنار index.php باشد (نه داخل زیرپوشهٔ بستهٔ ZIP).');
bk_note('اگر مرحله‌ای ❌ دارد، همان مرحله را برای من ارسال کنید (عکس صفحه کافی است).');
bk_note('⚠️ بعد از رفع مشکل، bk-diag.php را از سرور حذف کنید.');
