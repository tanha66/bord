<?php
/**
 * Bordkhan — نگاشت کلیدهای تصویر دیتاست seed
 * ============================================================
 * v3: هر قلق عکس «یکتای» خودش را دارد (سئو) — tip-001.jpg تا tip-209.jpg
 * این تصاویر با tools/make_unique_images.py ساخته شده‌اند:
 *   عکس موضوعی مرتبط (باتری/نمایشگر/دوربین/شارژ/آب/برد/عمومی)
 *   + عنوان فارسی همان قلق + چیپ برند و شمارهٔ قلق
 * فایل‌ها تخت در ریشهٔ uploads/ هستند (serve.php فقط ریشهٔ uploads را سرو می‌کند).
 *
 * کلیدهای gen-* و pexels-* (۱۴ عکس پایهٔ موضوعی) هم پشتیبانی می‌شوند تا
 * نسخه‌های قبلی دیتاست کار کنند، ولی برای سئو بهتر است تصویر یکتا باشد.
 */

function bk_seed_image_map(): array {
    /* ۱۴ عکس پایهٔ موضوعی (برای make_unique_images و سازگاری) */
    $bases = [
        'pexels-iphone-battery'   => 'tip-open-iphone-repair.jpg',
        'pexels-iphone-battery-2' => 'tip-phone-circuit-repair.jpg',
        'pexels-microscope'       => 'tip-board-under-microscope.jpg',
        'pexels-microscope-2'     => 'tip-board-macro-afem.jpg',
        'gen-swollen-battery'     => 'tip-swollen-battery.jpg',
        'gen-battery-flex'        => 'tip-disconnect-battery-flex.jpg',
        'gen-heatgun'             => 'tip-heat-gun-backcover.jpg',
        'gen-wet-port'            => 'tip-wet-usb-port.jpg',
        'gen-cracked-lens'        => 'tip-cracked-camera-lens.jpg',
        'gen-cracked-display'     => 'tip-cracked-display.jpg',
        'gen-screws-mat'          => 'tip-screws-organization-mat.jpg',
        'gen-rice'                => 'tip-phone-in-rice.jpg',
        'gen-speaker'             => 'tip-speaker-mesh-macro.jpg',
        'gen-charger'             => 'tip-charger-and-cable.jpg',
        'gen-esd-workspace'       => 'tip-esd-workspace.jpg',
    ];
    /* ۲۰۹ عکس یکتا: seed-img-1 → tip-001.jpg … seed-img-209 → tip-209.jpg */
    for ($i = 1; $i <= 209; $i++) {
        $bases['seed-img-' . $i] = 'tip-' . sprintf('%03d', $i) . '.jpg';
    }
    /* سازگاری: کلیدهای قدیمی gen-*/pexels-* به عکس یکتای «همان شماره» هدایت می‌شوند تا
       هر قلق حتی با دیتاست قدیمی هم تصویر یکتا بگیرد (پیش‌فرض مؤثرتر برای سئو) */
    for ($i = 1; $i <= 209; $i++) {
        $key = 'auto-' . $i;
        $bases[$key] = 'tip-' . sprintf('%03d', $i) . '.jpg';
    }
    return array_map(function ($f) { return ['file' => $f, 'alt' => '']; }, $bases);
}
