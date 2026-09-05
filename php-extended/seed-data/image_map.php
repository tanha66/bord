<?php
/**
 * Bordkhan — نگاشت کلیدهای تصویر دیتاست seed به فایل‌های واقعی در /uploads/tips/
 * منابع تصاویر: عکس‌های آزاد (Pexels/Wikimedia) + تصاویر تولیدشدهٔ هوش مصنوعی (بدون واترمارک و بدون کپی‌رایت ثالث)
 */

function bk_seed_image_map(): array {
    return [
        /* عکس‌های واقعی آزاد */
        'pexels-iphone-battery'   => ['file' => 'mobile/open-iphone-repair.jpg',   'alt' => 'آیفون بازشده روی میز تعمیر'],
        'pexels-iphone-battery-2' => ['file' => 'mobile/phone-circuit-repair.jpg', 'alt' => 'تعمیر برد گوشی با پیچ‌گوشتی'],
        'pexels-microscope'       => ['file' => 'mobile/board-under-microscope.jpg', 'alt' => 'برد گوشی زیر میکروسکوپ'],
        'pexels-microscope-2'     => ['file' => 'mobile/board-macro-afem.jpg',     'alt' => 'ماکروی تراشه روی برد آیفون'],

        /* تصاویر تولیدشده (AI) */
        'gen-swollen-battery'   => ['file' => 'gen/swollen-battery.jpg',   'alt' => 'باتری متورم گوشی'],
        'gen-battery-flex'      => ['file' => 'gen/disconnect-battery-flex.jpg', 'alt' => 'جدا کردن فلکس باتری'],
        'gen-heatgun'           => ['file' => 'gen/heat-gun-backcover.jpg', 'alt' => 'گرم دادن درب پشت گوشی'],
        'gen-wet-port'          => ['file' => 'gen/wet-usb-port.jpg',      'alt' => 'پورت شارژ خیس'],
        'gen-cracked-lens'      => ['file' => 'gen/cracked-camera-lens.jpg', 'alt' => 'لنز دوربین ترک‌خورده'],
        'gen-cracked-display'   => ['file' => 'gen/cracked-display.jpg',   'alt' => 'نمایشگر شکسته'],
        'gen-screws-mat'        => ['file' => 'gen/screws-organization-mat.jpg', 'alt' => 'سازماندهی پیچ‌ها روی مات'],
        'gen-rice'              => ['file' => 'gen/phone-in-rice.jpg',     'alt' => 'گوشی درون برنج'],
        'gen-speaker'           => ['file' => 'gen/speaker-mesh-macro.jpg', 'alt' => 'شبکهٔ اسپیکر گوشی'],
        'gen-charger'           => ['file' => 'gen/charger-and-cable.jpg', 'alt' => 'شارژر و کابل'],
        'gen-esd-workspace'     => ['file' => 'gen/esd-workspace.jpg',     'alt' => 'میز کار ضداستاتیک تعمیرگاه'],
    ];
}
