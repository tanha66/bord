<?php
/**
 * Bordkhan — کپی تصاویر seed به پوشهٔ uploads/tips
 * پس از نصب، تصاویر قلق‌ها از uploads-seed به uploads منتقل می‌شوند.
 * اجرای مکرر امن است (فقط فایل‌های ناموجود کپی می‌شوند).
 */

$SRC = __DIR__ . '/uploads-seed/tips';
$DST = __DIR__ . '/../uploads/tips';

if (!is_dir($SRC)) {
    fwrite(STDERR, "پوشهٔ منبع پیدا نشد: {$SRC}\n");
    exit(1);
}
if (!is_dir($DST) && !@mkdir($DST, 0755, true) && !is_dir($DST)) {
    fwrite(STDERR, "ساخت پوشهٔ مقصد ناموفق بود: {$DST} — مجوز نوشتن را بررسی کنید.\n");
    exit(1);
}

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
    else { $failed++; fwrite(STDERR, "کپی نشد: {$rel}\n"); }
}
echo "کپی: {$copied} فایل (" . round($bytes / 1048576, 2) . " MB) — موجود: {$skipped} — خطا: {$failed}\n";
echo "مسیر مقصد: {$DST}\n";
