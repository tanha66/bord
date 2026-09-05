<?php
/**
 * Bordkhan — کپی تصاویر seed به ریشهٔ uploads
 * مقصد از ثابت UPLOAD_DIR در config.php خوانده می‌شود (همیشه public_html/uploads است — حتی روی هاست ایران)
 *
 * اجرا از دو راه:
 *   ۱) SSH/CLI:  php copy_seed_media.php
 *   ۲) مرورگر:   https://site.com/copy_seed_media.php?key=INSTALL_KEY
 *
 * اجرای مکرر امن است. فقط فایل‌های با پیشوند tip- کپی می‌شوند (فایل‌های قدیمی/اکسذو نادیده گرفته می‌شوند).
 */

function bk_csm_out(string $msg): void {
    echo $msg . "\n";
    if (PHP_SAPI !== 'cli') { @flush(); @ob_flush(); }
}

function bk_csm_fail(string $msg): void {
    if (PHP_SAPI === 'cli') { fwrite(STDERR, "خطا: " . $msg . "\n"); exit(1); }
    http_response_code(500);
    @header('Content-Type: text/plain; charset=utf-8');
    echo "خطا: " . $msg . "\n";
    exit(1);
}

/* ---------- بارگذاری config ---------- */
$bk_cfg_candidates = [
    __DIR__ . '/../config.php',   /* فایل داخل php-extended است */
    __DIR__ . '/config.php',      /* فایل در ریشهٔ سایت است */
];
$bk_cfg_loaded = false;
foreach ($bk_cfg_candidates as $bk_cfg) {
    if (is_file($bk_cfg)) { require_once $bk_cfg; $bk_cfg_loaded = true; break; }
}
if (!$bk_cfg_loaded || !defined('INSTALL_KEY') || !defined('UPLOAD_DIR')) {
    bk_csm_fail('فایل config.php (با INSTALL_KEY و UPLOAD_DIR) پیدا نشد. فایل باید در ریشهٔ سایت یا داخل php-extended باشد.');
}
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

/* ---------- محافظت اجرای مرورگری ---------- */
$bk_is_cli = (PHP_SAPI === 'cli');
if (!$bk_is_cli) {
    $bk_key = isset($_GET['key']) ? (string)$_GET['key'] : '';
    if ($bk_key === '' || !hash_equals((string)INSTALL_KEY, $bk_key)) {
        http_response_code(403);
        @header('Content-Type: text/plain; charset=utf-8');
        echo "دسترسی مجاز نیست.\nآدرس را به شکل زیر باز کنید:\ncopy_seed_media.php?key=INSTALL_KEY\n";
        exit;
    }
    @set_time_limit(0);
    @header('Content-Type: text/plain; charset=utf-8');
}

/* ---------- یافتن پوشهٔ منبع ---------- */
$bk_src_candidates = [
    __DIR__ . '/uploads-seed/tips',
    __DIR__ . '/php-extended/uploads-seed/tips',
];
$SRC = '';
foreach ($bk_src_candidates as $bk_c) {
    if (is_dir($bk_c)) { $SRC = $bk_c; break; }
}
if ($SRC === '') {
    bk_csm_fail('پوشهٔ uploads-seed/tips پیدا نشد. آن را کنار این فایل یا داخل php-extended قرار دهید.');
}

/* ---------- مقصد: ثابت UPLOAD_DIR (همیشه درست است) ---------- */
$DST = rtrim((string)UPLOAD_DIR, '/');
if (!is_dir($DST)) {
    if (!@mkdir($DST, 0755, true) && !is_dir($DST)) {
        bk_csm_fail('پوشهٔ uploads ساخته نشد: ' . $DST . ' — مجوز نوشتن را بررسی کنید.');
    }
}
if (!is_writable($DST)) {
    bk_csm_fail('پوشهٔ uploads قابل نوشتن نیست: ' . $DST . ' — دسترسی آن را روی 755 بگذارید.');
}

bk_csm_out('منبع: ' . $SRC);
bk_csm_out('مقصد (UPLOAD_DIR از config): ' . $DST);

/* ---------- کپی تخت فقط برای فایل‌های tip-* ---------- */
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($SRC, FilesystemIterator::SKIP_DOTS));
$copied = 0; $skipped = 0; $failed = 0; $bytes = 0; $ignored = 0;
foreach ($it as $file) {
    if (!$file->isFile()) continue;
    $name = basename($file->getPathname());
    if (strpos($name, 'tip-') !== 0) { $ignored++; continue; } /* فقط فایل‌های استاندارد این بسته */
    $target = $DST . '/' . $name;
    if (is_file($target) && filesize($target) === $file->getSize()) { $skipped++; continue; }
    if (@copy($file->getPathname(), $target)) {
        $copied++; $bytes += $file->getSize();
        @chmod($target, 0644);
    } else {
        $failed++;
        bk_csm_out('کپی نشد: ' . $name . ' (دسترسی پوشهٔ uploads را روی 755 بگذارید)');
    }
}
bk_csm_out('نتیجه — کپی: ' . $copied . ' فایل (' . round($bytes / 1048576, 2) . ' MB) | از قبل موجود: ' . $skipped . ' | خطا: ' . $failed . ' | نادیده (فایل قدیمی/متفرقه): ' . $ignored);

if ($failed === 0) {
    bk_csm_out('✅ تصاویر در جای درست‌اند.');
    bk_csm_out('حالا مسیر عکسِ قلق‌های ثبت‌شده را اصلاح کنید — این آدرس را باز کنید:');
    bk_csm_out('   seed_tips.php?key=INSTALL_KEY همراه با پارامتر fiximgs=1');
    bk_csm_out('   (یا اگر می‌خواهید همه از نو ثبت شوند: پارامتر fresh=1)');
}
bk_csm_out('⚠️ پس از اتمام، این فایل را از سرور حذف کنید.');
