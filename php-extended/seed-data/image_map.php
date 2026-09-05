<?php
/**
 * Bordkhan — نگاشت عکس‌های سید قلق‌ها — v5 (۳۰۹ قلق، همه با عکس یکتا)
 * ============================================================
 * seed-all.php و seed_tips.php با فراخوانی bk_seed_image_map() این نگاشت را می‌خوانند.
 * ساختار هر مقدار: ['file' => 'نام‌فایل.jpg'] — فایل‌ها تخت در ریشهٔ uploads/.
 *
 * ⚠️ این فایل باید کنار part*.json داخل پوشهٔ seed-data باشد.
 */
function bk_seed_image_map(): array {
    static $m = null;
    if ($m !== null) return $m;
    $m = [];
    /* عکس یکتای هر قلق: seed-img-N و auto-N ← tip-NNN.jpg (سئو) */
    for ($i = 1; $i <= 309; $i++) {
        $f = ['file' => 'tip-' . sprintf('%03d', $i) . '.jpg'];
        $m['seed-img-' . $i] = $f;
        $m['auto-' . $i]     = $f;
    }
    /* عکس‌های پایهٔ موضوعی (fallback — معمولاً استفاده نمی‌شوند) */
    foreach ([
        'base-water'      => 'tip-wet-usb-port.jpg',
        'base-battery'    => 'tip-swollen-battery.jpg',
        'base-display'    => 'tip-cracked-display.jpg',
        'base-camera'     => 'tip-cracked-camera-lens.jpg',
        'base-sound'      => 'tip-speaker-mesh-macro.jpg',
        'base-network'    => 'tip-board-macro-afem.jpg',
        'base-software'   => 'tip-esd-workspace.jpg',
        'base-charging'   => 'tip-charger-and-cable.jpg',
        'base-board'      => 'tip-board-under-microscope.jpg',
        'base-heat'       => 'tip-heat-gun-backcover.jpg',
        'base-open'       => 'tip-open-iphone-repair.jpg',
        'base-flex'       => 'tip-disconnect-battery-flex.jpg',
        'base-circuit'    => 'tip-phone-circuit-repair.jpg',
        'base-rice'       => 'tip-phone-in-rice.jpg',
        'base-screws'     => 'tip-screws-organization-mat.jpg',
        'gen-1'  => 'tip-wet-usb-port.jpg',
        'gen-2'  => 'tip-swollen-battery.jpg',
        'gen-3'  => 'tip-cracked-display.jpg',
        'gen-4'  => 'tip-cracked-camera-lens.jpg',
        'gen-5'  => 'tip-speaker-mesh-macro.jpg',
        'gen-6'  => 'tip-board-macro-afem.jpg',
        'gen-7'  => 'tip-esd-workspace.jpg',
        'gen-8'  => 'tip-charger-and-cable.jpg',
        'gen-9'  => 'tip-board-under-microscope.jpg',
        'pexels-1' => 'tip-wet-usb-port.jpg',
        'pexels-2' => 'tip-swollen-battery.jpg',
        'pexels-3' => 'tip-cracked-display.jpg',
        'pexels-4' => 'tip-cracked-camera-lens.jpg',
        'pexels-5' => 'tip-speaker-mesh-macro.jpg',
        'pexels-6' => 'tip-board-macro-afem.jpg',
        'pexels-7' => 'tip-esd-workspace.jpg',
        'pexels-8' => 'tip-charger-and-cable.jpg',
        'pexels-9' => 'tip-board-under-microscope.jpg',
    ] as $k => $v) { $m[$k] = ['file' => $v]; }
    return $m;
}
