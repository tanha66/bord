<?php
/**
 * Bordkhan — کپی تصاویر seed به پوشهٔ uploads/tips
 * اجرا از دو راه:
 *   ۱) SSH/CLI:  php copy_seed_media.php
 *   ۲) مرورگر:   https://site.com/copy_seed_media.php?key=INSTALL_KEY
 *
 * محل فایل: هم در ریشهٔ سایت کار می‌کند هم داخل پوشهٔ php-extended.
 * پوشهٔ uploads-seed باید یکی از این دو جا باشد: کنار این فایل، یا داخل php-extended.
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

/* ---------- یافتن پوشهٔ منبع و مقصد ---------- */
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

$bk_dst_candidates = [
    __DIR__ . '/../uploads/tips',   /* فایل داخل php-extended است */
    __DIR__ . '/uploads/tips',      /* فایل در ریشهٔ سایت است */
];
$DST = '';
foreach ($bk_dst_candidates as $bk_c) {
    $bk_parent = dirname($bk_c);
    if (is_dir($bk_parent) && is_writable($bk_parent)) { $DST = $bk_c; break; }
}
if ($DST === '') {
    /* اولین مسیر منطقی را بسازیم */
    $DST = $bk_dst_candidates[0];
}
if (!is_dir($DST) && !@mkdir($DST, 0755, true) && !is_dir($DST)) {
    bk_csm_fail('ساخت پوشهٔ مقصد ناموفق بود: ' . $DST . ' — مجوز نوشتن را بررسی کنید (معمولاً 755).');
}

bk_csm_out('منبع: ' . $SRC);
bk_csm_out('مقصد: ' . $DST);

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($SRC, FilesystemIterator::SKIP_DOTS));
$copied = 0; $skipped = 0; $failed = 0; $bytes = 0;
foreach ($it as $file) {
    if (!$file->isFile()) continue;
    $rel = substr($file->getPathname(), strlen($SRC) + 1);
    $target = $DST . '/' . $rel;
    $dir = dirname($target);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) { $failed++; continue; }
    if (is_file($target) && filesize($target) === $file->getSize()) { $skipped++; continue; }
    if (@copy($file->getPathname(), $target)) { $copied++; $bytes += $file->getSize(); }
    else { $failed++; bk_csm_out('کپی نشد: ' . $rel); }
}
bk_csm_out('کپی: ' . $copied . ' فایل (' . round($bytes / 1048576, 2) . ' MB) — موجود: ' . $skipped . ' — خطا: ' . $failed);
bk_csm_out('✅ تصاویر آماده‌اند. حالا seed_tips.php را اجرا کنید.');
bk_csm_out('⚠️ پس از اتمام، این فایل را از سرور حذف کنید.');
