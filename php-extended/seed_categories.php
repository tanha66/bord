<?php
/**
 * Bordkhan — درخت دسته‌بندی پیش‌فرض با برندها و مدل‌های شناخته‌شده.
 *
 * این فایل در install.php (نصب تازه) و migrate.php (ارتقای نصب‌های قدیمی)
 * استفاده می‌شود تا دسته‌بندی برندها همیشه کامل و یکسان باشد.
 * اجرای دوباره امن است (idempotent) — دسته‌های موجود دوباره ساخته نمی‌شوند.
 */

/** درخت دسته‌بندی: [نام والد, آیکون, [[برند, [مدل‌ها]], ...]] */
function bk_category_tree(): array {
    return [
        ['پاور', '⚡', [
            ['گرین', ['GP530', 'GP600', 'GP750', 'GP850']],
            ['کورسیر', ['VS', 'CV', 'RM', 'HX']],
            ['ترمالتیک', ['Smart', 'Toughpower']],
            ['کولر مستر', ['MWE', 'GM']],
            ['MSI', ['MAG', 'MPG']],
            ['ایسوس', ['ROG', 'TUF Gaming']],
            ['گیم مکس', ['GP', 'GT', 'VP']],
            ['متفرقه', []],
        ]],
        ['مادربرد', '🖥️', [
            ['گیگابایت', ['B450', 'B550', 'B650', 'H310', 'H510']],
            ['ایسوس', ['Prime', 'TUF Gaming', 'ROG Strix', 'ProArt']],
            ['MSI', ['MAG', 'Bazooka', 'Tomahawk', 'Mortar']],
            ['ASRock', ['Steel Legend', 'Phantom', 'Pro RS']],
            ['بایواستار', ['H510', 'B550', 'B760']],
            ['متفرقه', []],
        ]],
        ['لپ‌تاپ', '💻', [
            ['ایسوس', ['TUF Gaming', 'ROG', 'VivoBook', 'ZenBook']],
            ['لنوو', ['IdeaPad', 'Legion', 'ThinkPad', 'Yoga']],
            ['اچ‌پی', ['Pavilion', 'Victus', 'Omen', 'EliteBook']],
            ['دل', ['Inspiron', 'XPS', 'Latitude', 'G15']],
            ['ایسر', ['Aspire', 'Nitro', 'Swift']],
            ['مک بوک', ['Air M1', 'Air M2', 'Air M3', 'Pro 13', 'Pro 14', 'Pro 16']],
            ['مایکروسافت', ['Surface Pro', 'Surface Laptop']],
            ['متفرقه', []],
        ]],
        ['کارت گرافیک', '🎮', [
            ['انویدیا', ['GTX 1050/1060/1650', 'RTX 2060', 'RTX 3060', 'RTX 4060', 'RTX 4070']],
            ['AMD', ['RX 580', 'RX 6600', 'RX 6700XT', 'RX 7600']],
            ['ایسوس', ['TUF', 'ROG Strix']],
            ['MSI', ['Gaming', 'Ventus']],
            ['گیگابایت', ['Windforce', 'Gaming OC']],
            ['زوتاک', ['AMP', 'Twin Edge']],
            ['متفرقه', []],
        ]],
        ['مانیتور و تلویزیون', '📺', [
            ['سامسونگ', ['Odyssey', 'M5', 'UA50', 'Neo QLED']],
            ['ال‌جی', ['UltraGear', 'QLED', 'C2/C3']],
            ['سونی', ['Bravia X75', 'Bravia X80']],
            ['گنوتی', ['M1', 'M2']],
            ['شیائومی', ['Mi TV', 'TV A Pro']],
            ['هایسنس', ['A6', 'U6']],
            ['تی‌سی‌ال', ['P635', 'C645']],
            ['متفرقه', []],
        ]],
        ['موبایل و تبلت', '📱', [
            ['آیفون', ['11', '12', '13', '14', '15', '16']],
            ['سامسونگ', ['گلکسی A12', 'گلکسی A32', 'گلکسی A52', 'گلکسی A54', 'گلکسی S21', 'گلکسی S22', 'گلکسی S23', 'گلکسی S24', 'نوت ۲۰']],
            ['شیائومی', ['ردمی نوت 9', 'ردمی نوت 10', 'ردمی نوت 11', 'ردمی نوت 12', 'ردمی نوت 13', 'پوکو X3', 'پوکو X4', 'پوکو X6', 'می 11T']],
            ['هواوی', ['P30', 'P40', 'Nova 9', 'Mate 40']],
            ['آنر', ['X7', 'X8', 'X9', 'Honor 90', 'Magic 5']],
            ['آیپد', ['Air', 'Pro', 'Mini']],
            ['سامسونگ تبلت', ['Galaxy Tab A', 'Galaxy Tab S']],
            ['متفرقه', []],
        ]],
        ['بردهای صنعتی', '🏭', [
            ['اینورتر', ['زیمنس', 'دلتا', 'شنایدر', 'ABB']],
            ['PLC', ['زیمنس S7', 'میتسوبیشی FX', 'دلتا DVP']],
            ['درایو و CNC', ['فانوک', 'زیمنس', 'هایده‌نهایین']],
        ]],
        ['آداپتور و شارژر', '🔌', [
            ['لپ‌تاپ', ['ایسوس', 'لنوو', 'اچ‌پی', 'دل', '۹۰ وات', '۱۲۵ وات']],
            ['موبایل', ['شارژر آیفون', 'شارژر سامسونگ', 'شارژر شیائومی', 'شارژر هواوی']],
            ['صنعتی', ['۱۲ ولت', '۲۴ ولت', '۴۸ ولت']],
        ]],
        ['کنسول بازی', '🕹️', [
            ['پلی‌استیشن', ['PS4', 'PS4 Pro', 'PS5']],
            ['ایکس باکس', ['One', 'Series S', 'Series X']],
            ['نینتندو', ['Switch']],
        ]],
        ['سایر', '🔧', []],
    ];
}

/**
 * کاشت دسته‌بندی به‌صورت امن و قابل تکرار.
 * خروجی: ['added' => تعداد موارد جدید, 'skipped' => تعداد موارد از قبل موجود]
 */
function bk_seed_categories($pdo): array {
    $added = 0;
    $skipped = 0;
    // ۱) پاک‌سازی موارد تکراری واقعی (نام + والد یکسان) — اجرای مجدد امن است
    try {
        $pdo->exec('DELETE c1 FROM categories c1 INNER JOIN categories c2 ON c1.name=c2.name AND IFNULL(c1.parent_id,0)=IFNULL(c2.parent_id,0) AND c1.id>c2.id');
    } catch (Throwable $e) { /* جدول قدیمی یا بدون این ستون‌ها — نادیده بگیر */ }
    $find = $pdo->prepare('SELECT id FROM categories WHERE name=? AND parent_id IS NULL LIMIT 1');
    $ins = $pdo->prepare('INSERT INTO categories (parent_id,name,slug,icon) VALUES (?,?,?,?)');
    $childFind = $pdo->prepare('SELECT id FROM categories WHERE name=? AND parent_id=? LIMIT 1');
    foreach (bk_category_tree() as [$cname, $icon, $children]) {
        $find->execute([$cname]);
        $parentRow = $find->fetchColumn();
        if (!$parentRow) {
            $ins->execute([null, $cname, 'cat-' . md5($cname), $icon]);
            $parentRow = $pdo->lastInsertId();
            $added++;
        } else {
            $skipped++;
        }
        foreach ($children as $childEntry) {
            $childName = is_array($childEntry) ? $childEntry[0] : $childEntry;
            $grandchildren = is_array($childEntry) ? ($childEntry[1] ?? []) : [];
            $childFind->execute([$childName, $parentRow]);
            $childId = $childFind->fetchColumn();
            if (!$childId) {
                $ins->execute([$parentRow, $childName, 'cat-' . md5($cname . $childName), null]);
                $childId = $pdo->lastInsertId();
                $added++;
            } else {
                $skipped++;
            }
            foreach ($grandchildren as $grand) {
                $childFind->execute([$grand, $childId]);
                if (!$childFind->fetchColumn()) {
                    $ins->execute([$childId, $grand, 'cat-' . md5($cname . $childName . $grand), null]);
                    $added++;
                } else {
                    $skipped++;
                }
            }
        }
    }
    return ['added' => $added, 'skipped' => $skipped];
}
