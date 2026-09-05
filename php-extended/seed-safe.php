<?php
/**
 * Bordkhan — سیدِ امن قدم‌به‌قدم (seed-safe.php)
 * ============================================================
 * همان کار seed-all.php را انجام می‌دهد ولی:
 *   • خروجی از خط اول و به‌صورت زنده (شکستن بافر هاست)
 *   • بدون وابستگی به image_map.php — نگاشت را خودش می‌سازد
 *   • هر مرحله جداگانه گزارش می‌شود؛ خطای fatal هم نمایش داده می‌شود
 *   • resume خودکار بر اساس عنوان قلق (بدون نیاز به جدول وضعیت)
 *
 *   https://bordkhan.ir/seed-safe.php?key=INSTALL_KEY            ← اجرای کامل
 *   https://bordkhan.ir/seed-safe.php?key=INSTALL_KEY&list=1     ← فقط پیش‌نمایش ۳۰۹ عنوان
 *   https://bordkhan.ir/seed-safe.php?key=INSTALL_KEY&skipcopy=1 ← رد شدن از کپی عکس‌ها
 *
 * ⚠️ بعد از اتمام، این فایل را از سرور حذف کنید.
 */

@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', 'off');
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
@error_reporting(E_ALL);
@set_time_limit(0);
while (ob_get_level() > 0) { @ob_end_flush(); }
http_response_code(200);
header('Content-Type: text/html; charset=utf-8');
header('X-Accel-Buffering: no');
echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>سید امن بردخان</title></head>';
echo '<body style="font-family:Tahoma;background:#0f1a2b;color:#e8edf4;padding:22px;line-height:2.2">';
echo '<div style="max-width:900px;margin:auto"><h1 style="color:#5eead4">🚀 سید امن قلق‌های بردخان (۳۰۹)</h1>';
echo str_pad(' ', 4096) . "\n";
@flush();

function bk_out(string $m): void { echo $m . "<br>\n"; while (ob_get_level() > 0) { @ob_flush(); } @flush(); }
function bk_fail(string $m): void {
    echo '<div dir="rtl" style="font-family:Tahoma;margin:18px 0;padding:14px;border:2px solid #d33;border-radius:12px"><b style="color:#fca5a5">⛔ توقف:</b><br>' . htmlspecialchars($m) . '</div>';
    while (ob_get_level() > 0) { @ob_flush(); } @flush();
    exit;
}
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        echo '<div style="margin-top:16px;padding:12px;border:2px solid #d33;border-radius:10px">';
        echo '<b style="color:#fca5a5">خطای fatal:</b><br>' . htmlspecialchars($e['message'])
           . '<br><small>خط ' . (int)$e['line'] . ' — ' . htmlspecialchars($e['file']) . '</small></div>';
    }
    echo '</div></body></html>';
});

/* نرمال‌سازی لینک &amp; */
foreach ($_GET as $gk => $gv) { if (str_starts_with((string)$gk, 'amp;')) { $_GET[substr((string)$gk, 4)] = $gv; } }

/* ---------- ۱) config ---------- */
bk_out('<b>مرحلهٔ ۰ — بارگذاری config</b>');
$cfg = '';
foreach ([__DIR__ . '/config.php', dirname(__DIR__) . '/config.php'] as $c) { if (is_file($c)) { $cfg = $c; break; } }
if ($cfg === '') bk_fail('config.php کنار این فایل نیست — فایل را در public_html بگذارید.');
try { require_once $cfg; bk_out('✅ config بار شد: ' . htmlspecialchars($cfg)); }
catch (Throwable $ex) { bk_fail('خطا در config: ' . $ex->getMessage()); }
if (!defined('INSTALL_KEY')) bk_fail('INSTALL_KEY در config تعریف نشده.');
$key = isset($_GET['key']) ? (string)$_GET['key'] : '';
if ($key === '' || !hash_equals((string)INSTALL_KEY, $key)) bk_fail('کلید نامعتبر — ?key=INSTALL_KEY (مقدار داخل config.php)');

/* ---------- ۲) دیتابیس ---------- */
bk_out('<b>مرحلهٔ ۱ — اتصال دیتابیس</b>');
try { $pdo = db(); bk_out('✅ اتصال برقرار شد — ' . DB_NAME); }
catch (Throwable $ex) { bk_fail('اتصال دیتابیس نشد: ' . $ex->getMessage()); }

/* ---------- ۳) نویسنده و دسته ---------- */
$AUTHOR_ID = 0;
foreach ($pdo->query("SELECT id, name FROM users WHERE role IN ('admin','superadmin') ORDER BY id ASC LIMIT 1")->fetchAll(PDO::FETCH_ASSOC) as $a) { $AUTHOR_ID = (int)$a['id']; }
if (!$AUTHOR_ID) bk_fail('کاربر ادمین پیدا نشد — اول install.php را اجرا کنید.');
bk_out('✅ نویسنده: #' . $AUTHOR_ID);
$CAT_ID = 0;
foreach ($pdo->query("SELECT id FROM categories WHERE name LIKE '%موبایل و تبلت%' AND status='active' ORDER BY parent_id IS NOT NULL ASC, id ASC LIMIT 1")->fetchAll(PDO::FETCH_ASSOC) as $c) { $CAT_ID = (int)$c['id']; }
if (!$CAT_ID) bk_fail('دستهٔ «موبایل و تبلت» پیدا نشد — از پنل ادمین بسازید.');
bk_out('✅ دسته: #' . $CAT_ID);

/* ---------- ۴) دیتاست ---------- */
bk_out('<b>مرحلهٔ ۲ — بارگذاری دیتاست</b>');
$SD = '';
foreach ([__DIR__ . '/seed-data', __DIR__ . '/php-extended/seed-data'] as $c) { if (is_dir($c)) { $SD = $c; break; } }
if ($SD === '') bk_fail('پوشهٔ seed-data کنار این فایل نیست.');
$files = glob($SD . '/part*.json');
sort($files, SORT_NATURAL);
if (!$files) bk_fail('هیچ part*.json داخل seed-data نیست.');
$tips = [];
foreach ($files as $f) {
    $d = json_decode((string)file_get_contents($f), true);
    if (!is_array($d)) bk_fail('جیسون نامعتبر: ' . basename($f));
    foreach ($d as $t) $tips[] = $t;
    bk_out('… ' . basename($f) . ' ← ' . count($d) . ' قلق');
}
$TOTAL = count($tips);
bk_out("✅ مجموع: <b>{$TOTAL} قلق</b>");

$LIST = isset($_GET['list']) && $_GET['list'] == '1';
if ($LIST) {
    bk_out('<h2 style="color:#93c5fd">پیش‌نمایش عناوین (هیچ‌چیز در دیتابیس نوشته نشد)</h2>');
    foreach ($tips as $i => $t) bk_out(sprintf('%3d. [%s] %s', $i + 1, htmlspecialchars((string)$t['cat']), htmlspecialchars((string)$t['title'])));
    bk_out('✅ برای اجرای واقعی، همین آدرس را بدون list=1 باز کنید.');
    exit;
}

/* ---------- ۵) مقصد uploads ---------- */
if (!defined('UPLOAD_DIR')) bk_fail('UPLOAD_DIR در config تعریف نشده.');
$UPLOADS = rtrim((string)UPLOAD_DIR, '/');
if (!is_dir($UPLOADS)) bk_fail('پوشهٔ uploads نیست: ' . $UPLOADS);
bk_out('✅ مقصد عکس‌ها: ' . htmlspecialchars($UPLOADS));

/* ---------- ۶) کپی عکس‌ها ---------- */
$SKIPCOPY = isset($_GET['skipcopy']) && $_GET['skipcopy'] == '1';
$MD = '';
foreach ([__DIR__ . '/uploads-seed/tips', __DIR__ . '/php-extended/uploads-seed/tips'] as $c) { if (is_dir($c)) { $MD = $c; break; } }
if ($MD === '') bk_fail('پوشهٔ uploads-seed/tips کنار این فایل نیست.');
$copied = $skipped = $failed = 0; $bytes = 0; $uniqNames = []; $seen = 0;
if ($SKIPCOPY) {
    bk_out('<b>مرحلهٔ ۳ — کپی عکس‌ها (رد شد — skipcopy=1)</b>');
    foreach (glob($MD . '/tip-*.jpg') as $p) { $uniqNames[basename($p)] = true; }
    bk_out('فهرست: ' . count($uniqNames) . ' فایل tip-*.jpg');
} else {
    bk_out('<b>مرحلهٔ ۳ — کپی عکس‌های یکتا به uploads</b>');
    foreach (glob($MD . '/tip-*.jpg') as $p) {
        $name = basename($p);
        $uniqNames[$name] = true;
        $target = $UPLOADS . '/' . $name;
        if (is_file($target) && filesize($target) === filesize($p)) { $skipped++; }
        elseif (@copy($p, $target)) { $copied++; $bytes += (int)filesize($p); @chmod($target, 0644); }
        else { $failed++; bk_out('⚠️ کپی نشد: ' . $name); }
        $seen++;
        if ($seen % 25 === 0) bk_out("… {$seen} فایل (کپی {$copied}، موجود {$skipped})");
    }
    bk_out("✅ کپی {$copied} | از قبل بود {$skipped} | خطا {$failed} — مجموع مجاز: " . count($uniqNames));
    if ($failed > 0) bk_fail('دسترسی پوشهٔ uploads را 755 کنید و دوباره باز کنید.');
}

/* ---------- ۷) درج/به‌روزرسانی قلق‌ها (resume بر اساس عنوان) ---------- */
bk_out('<b>مرحلهٔ ۴ — درج/به‌روزرسانی ' . $TOTAL . ' قلق</b>');
$uniqImg = function (int $no) use ($UPLOADS): array {
    $u = 'tip-' . sprintf('%03d', $no) . '.jpg';
    if (is_file($UPLOADS . '/' . $u)) return ['/uploads/' . $u];
    return [];
};
$findId = $pdo->prepare('SELECT id FROM tips WHERE title = ? ORDER BY id ASC LIMIT 1');
$updTip = $pdo->prepare('UPDATE tips SET images_json=?, video_url=?, solution_json=?, tools=?, difficulty=?, fault_type=?, device_name=?, tags=?, featured=?, short_description=?, description=? WHERE id=?');
$insTip = $pdo->prepare('INSERT INTO tips(author_id,category_id,title,short_description,description,device_name,brand,model,board_number,fault_type,difficulty,solution_json,tools,images_json,video_url,attachments_json,access_type,price,visibility,status,tags,version,versions_json,featured,views,likes_count,purchases_count,rating_sum,rating_count,duplicate_of,rejection_reason,source_url,source_name,published_at)
 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NULL,?,0,0,0,0,0,NULL,NULL,NULL,NULL,?)');

$ok = $upd = $skip = $fail = 0;
$pdo->beginTransaction();
foreach ($tips as $idx => $t) {
    $no = $idx + 1;
    $title = trim((string)$t['title']); $short = trim((string)$t['short']); $desc = trim((string)$t['desc']);
    if (mb_strlen($title) < 5 || mb_strlen($short) < 5 || mb_strlen($desc) < 10 || empty($t['steps'])) { $fail++; continue; }
    $steps = [];
    foreach ($t['steps'] as $s) $steps[] = ['title' => (string)$s[0], 'body' => (string)$s[1]];
    $solutionJson = json_encode($steps, JSON_UNESCAPED_UNICODE);
    $imgs = $uniqImg($no);
    $imagesJson = json_encode($imgs ?: ['/uploads/tip-' . sprintf('%03d', $no) . '.jpg'], JSON_UNESCAPED_UNICODE);
    $tools = trim((string)($t['tools'] ?? ''));
    $videoUrl = !empty($t['vid']) ? 'https://www.aparat.com/v/' . $t['vid'] : '';
    $diff = in_array(($t['diff'] ?? ''), ['easy', 'medium', 'hard'], true) ? $t['diff'] : 'medium';
    $access = 'free'; $price = 0;
    if ($no % 4 === 0) $access = 'like';
    elseif ($no % 9 === 0) { $access = 'paid'; $price = 25000 + ($no % 5) * 10000; }
    $featured = (int)($no % 3 === 0);
    $publishedAt = date('Y-m-d H:i:s', strtotime('-' . (($TOTAL - $no) * 3 + rand(0, 2)) . ' hours'));
    try {
        $findId->execute([$title]);
        $tid = (int)$findId->fetchColumn();
        if ($tid > 0) {
            $updTip->execute([$imagesJson, $videoUrl, $solutionJson, $tools, $diff, (string)$t['fault'], (string)$t['device'], (string)$t['tags'], $featured, $short, $desc, $tid]);
            $upd++;
        } else {
            $insTip->execute([$AUTHOR_ID, $CAT_ID, $title, $short, $desc, (string)$t['device'], (string)$t['brand'], (string)($t['model'] ?? ''), '', (string)$t['fault'], $diff, $solutionJson, $tools, $imagesJson, $videoUrl, '[]', $access, $price, 'public', 'published', (string)$t['tags'], $featured, $publishedAt]);
            $ok++;
        }
    } catch (Throwable $ex) {
        $fail++;
        bk_out('⚠️ رکورد ' . $no . ' (' . htmlspecialchars($title) . '): ' . htmlspecialchars($ex->getMessage()));
    }
    if ($no % 20 === 0) { $pdo->commit(); $pdo->beginTransaction(); bk_out("… {$no} از {$TOTAL} پردازش شد (جدید {$ok} | به‌روزرسانی {$upd})"); }
}
$pdo->commit();
bk_out("<b>خلاصه: {$ok} درج جدید | {$upd} به‌روزرسانی | {$fail} خطا</b>");

/* ---------- ۸) آمار نهایی ---------- */
$c = (int)$pdo->query("SELECT COUNT(*) FROM tips WHERE status='published'")->fetchColumn();
$wimg = (int)$pdo->query("SELECT COUNT(*) FROM tips WHERE status='published' AND images_json IS NOT NULL AND images_json NOT IN ('','[]')")->fetchColumn();
$wvid = (int)$pdo->query("SELECT COUNT(*) FROM tips WHERE status='published' AND video_url IS NOT NULL AND video_url != ''")->fetchColumn();
bk_out("کل منتشرشده: <b>{$c}</b> | دارای عکس: <b>{$wimg}</b> | دارای ویدیو: <b>{$wvid}</b>");
bk_out('✅ <b>تمام شد!</b> حالا سایت را با Ctrl+F5 باز کنید — ویدیوی هر قلق باید در صفحهٔ خودش پخش شود (برای پخش، فایل‌های index.php و assets/style.css جدید هم باید آپلود شده باشند).');
bk_out('⚠️ <b>امنیت:</b> seed-safe.php و پوشه‌های seed-data و uploads-seed را از سرور حذف کنید.');
