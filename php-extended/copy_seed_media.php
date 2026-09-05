<?php
/**
 * Bordkhan — کپی تصاویر seed به پوشهٔ uploads (ریشه)
 * ⚠️ عمداً فایل‌ها را «تخت» در ریشهٔ uploads کپی می‌کنیم چون serve.php
 * فقط فایل‌های مستقیم داخل uploads/ را سرو می‌کند (basename).
 *
 * اجرا از دو راه:
 *   ۱) SSH/CLI:  php copy_seed_media.php
 *   ۲) مرورگر:   https://site.com/copy_seed_media.php?key=INSTALL_KEY
 *
 * محل فایل: هم در ریشهٔ سایت کار می‌کند هم داخل پوشهٔ php-extended.
 * اجرای مکرر امن است (فقط فایل‌های ناموجود کپی می‌شوند).
 */

function bk_csm_out(string $msg): void {
    echo $msg . "\n";
    if (PHP_SAPI !== 'cli') { @flush(); @ob_flush(); }
}

function bk_csm_fail(string $msg): void {
    if (PHP_SAPI === 'cli') { fwrite(STDERR, "خطا: " . $msg . "\n"); exit(1); }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "خطا: " . $msg . "\n";
    exit(1);
}

/* ---------- بارگذاری config (برای INSTALL_KEY) ---------- */
$bk_cfg_candidates = [
    __DIR__ . '/../config.php',
    __DIR__ . '/config.php',
];
$bk_cfg_loaded = false;
foreach ($bk_cfg_candidates as $bk_cfg) {
    if (is_file($bk_cfg)) { require_once $bk_cfg; $bk_cfg_loaded = true; break; }
}
if (!$bk_cfg_loaded || !defined('INSTALL_KEY')) {
    bk_csm_fail('فایل config.php پیدا نشد.');
}
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

/* ---------- محافظت اجرای مرورگری ---------- */
$bk_is_cli = (PHP_SAPI === 'cli');
if (!$bk_is_cli) {
    $bk_key = isset($_GET['key']) ? (string)$_GET['key'] : '';
    if ($bk_key === '' || !hash_equals((string)INSTALL_KEY, $bk_key)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "دسترسی مجاز نیست.\nآدرس را به شکل زیر باز کنید:\ncopy_seed_media.php?key=INSTALL_KEY\n";
        exit;
    }
    @set_time_limit(0);
    header('Content-Type: text/plain; charset=utf-8');
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

/* ---------- یافتن/ساخت پوشهٔ مقصد = ریشهٔ uploads ---------- */
$bk_dst_candidates = [
    __DIR__ . '/../uploads',   /* فایل داخل php-extended است */
    __DIR__ . '/uploads',      /* فایل در ریشهٔ سایت است */
];
$DST = '';
foreach ($bk_dst_candidates as $bk_c) {
    $bk_parent = dirname($bk_c);
    if (is_dir($bk_c) && is_writable($bk_c)) { $DST = $bk_c; break; }
    if (!is_dir($bk_c) && is_dir($bk_parent) && is_writable($bk_parent)) { $DST = $bk_c; break; }
}
if ($DST === '') {
    bk_csm_fail('پوشهٔ uploads پیدا نشد یا قابل نوشتن نیست. این فایل باید در ریشهٔ سایت یا داخل php-extended باشد.');
}

bk_csm_out('منبع: ' . $SRC);
bk_csm_out('مقصد (ریشهٔ uploads): ' . $DST);

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($SRC, FilesystemIterator::SKIP_DOTS));
$copied = 0; $skipped = 0; $failed = 0; $bytes = 0;
foreach ($it as $file) {
    if (!$file->isFile()) continue;
    /* کپی تخت: فقط نام فایل، مستقیم در ریشهٔ uploads */
    $target = $DST . '/' . basename($file->getPathname());
    if (is_file($target) && filesize($target) === $file->getSize()) { $skipped++; continue; }
    if (@copy($file->getPathname(), $target)) { $copied++; $bytes += $file->getSize(); }
    else { $failed++; bk_csm_out('کپی نشد: ' . basename($file->getPathname())); }
}
bk_csm_out('کپی: ' . $copied . ' فایل (' . round($bytes / 1048576, 2) . ' MB) — موجود: ' . $skipped . ' — خطا: ' . $failed);
if ($skipped > 0 && $copied === 0) {
    bk_csm_out('ℹ️ همهٔ فایل‌ها از قبل در uploads بودند — مشکلی نیست.');
}
bk_csm_out('✅ تصاویر آماده‌اند.');
bk_csm_out('اگر قبلاً قلق‌ها را با نسخهٔ قدیمی ثبت کرده‌اید، برای اصلاح مسیر عکس‌ها این آدرس را باز کنید:');
bk_csm_out('   seed_tips.php?key=INSTALL_KEY&fresh=1');
bk_csm_out('⚠️ پس از اتمام، این فایل را از سرور حذف کنید.');
