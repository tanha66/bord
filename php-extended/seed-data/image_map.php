<?php
/**
 * Bordkhan — نگاشت کلیدهای تصویر دیتاست seed به فایل‌های واقعی
 * ⚠️ نکتهٔ مهم: موتور نمایش سایت (serve.php) فقط فایل‌های «مستقیم داخل uploads/»
 * را سرو می‌کند (basename می‌گیرد). پس نام فایل‌ها باید تخت و در ریشهٔ uploads باشند.
 * منابع تصاویر: عکس‌های آزاد (Pexels/Wikimedia) + تصاویر تولیدشدهٔ هوش مصنوعی (بدون واترمارک)
 */

function bk_seed_image_map(): array {
    return [
        /* عکس‌های واقعی آزاد */
        'pexels-iphone-battery'   => ['file' => 'tip-open-iphone-repair.jpg',      'alt' => 'آیفون بازشده روی میز تعمیر'],
        'pexels-iphone-battery-2' => ['file' => 'tip-phone-circuit-repair.jpg',    'alt' => 'تعمیر برد گوشی با پیچ‌گوشتی'],
        'pexels-microscope'       => ['file' => 'tip-board-under-microscope.jpg',  'alt' => 'برد گوشی زیر میکروسکوپ'],
        'pexels-microscope-2'     => ['file' => 'tip-board-macro-afem.jpg',        'alt' => 'ماکروی تراشه روی برد آیفون'],

        /* تصاویر تولیدشده (AI) */
        'gen-swollen-battery'   => ['file' => 'tip-swollen-battery.jpg',           'alt' => 'باتری متورم گوشی'],
        'gen-battery-flex'      => ['file' => 'tip-disconnect-battery-flex.jpg',   'alt' => 'جدا کردن فلکس باتری'],
        'gen-heatgun'           => ['file' => 'tip-heat-gun-backcover.jpg',        'alt' => 'گرم دادن درب پشت گوشی'],
        'gen-wet-port'          => ['file' => 'tip-wet-usb-port.jpg',              'alt' => 'پورت شارژ خیس'],
        'gen-cracked-lens'      => ['file' => 'tip-cracked-camera-lens.jpg',       'alt' => 'لنز دوربین ترک‌خورده'],
        'gen-cracked-display'   => ['file' => 'tip-cracked-display.jpg',           'alt' => 'نمایشگر شکسته'],
        'gen-screws-mat'        => ['file' => 'tip-screws-organization-mat.jpg',   'alt' => 'سازماندهی پیچ‌ها روی مات'],
        'gen-rice'              => ['file' => 'tip-phone-in-rice.jpg',             'alt' => 'گوشی درون برنج'],
        'gen-speaker'           => ['file' => 'tip-speaker-mesh-macro.jpg',        'alt' => 'شبکهٔ اسپیکر گوشی'],
        'gen-charger'           => ['file' => 'tip-charger-and-cable.jpg',         'alt' => 'شارژر و کابل'],
        'gen-esd-workspace'     => ['file' => 'tip-esd-workspace.jpg',             'alt' => 'میز کار ضداستاتیک تعمیرگاه'],
    ];
}
