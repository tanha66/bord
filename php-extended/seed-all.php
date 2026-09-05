<?php
/**
 * Bordkhan — ابزار یک‌جا سید ۳۰۹ قلق تعمیر گوشی
 * ================================================
 * همه‌کاره: کپی ۱۴ عکس + اصلاح مسیر عکس‌های ثبت‌شده + درج/به‌روزرسانی قلق‌ها
 *
 * فقط یک فایل است — کنار index.php (در ریشهٔ public_html) بگذارید و در مرورگر باز کنید:
 *
 *   https://bordkhan.ir/seed-all.php?key=INSTALL_KEY          ← همه‌کاره: کپی عکس + fiximgs + سید
 *   https://bordkhan.ir/seed-all.php?key=INSTALL_KEY&fresh=1  ← حذف قلق‌های سید قبلی و نصب از نو
 *   https://bordkhan.ir/seed-all.php?key=INSTALL_KEY&list=1   ← فقط پیش‌نمایش ۳۰۹ عنوان
 *   https://bordkhan.ir/seed-all.php?key=INSTALL_KEY&skipcopy=1 ← اگر عکس‌ها قبلاً کپی شده (اجرای سریع‌تر)
 *
 * دیتاست: پوشهٔ seed-data باید کنار همین فایل باشد (یا داخل php-extended/seed-data).
 * عکس‌ها: پوشهٔ uploads-seed/tips باید کنار همین فایل باشد (یا داخل php-extended/uploads-seed/tips).
 * عکس‌ها به ریشهٔ uploads کپی می‌شوند (مثل uploads/tip-swollen-battery.jpg) چون
 * serve.php فقط فایل‌های مستقیم داخل uploads/ را سرو می‌کند.
 *
 * ⚠️ پس از اتمام، این فایل و پوشه‌های seed-data و uploads-seed را از سرور حذف کنید.
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
@ini_set('display_errors', '1');
@set_time_limit(0);

function bk_out(string $msg): void {
    echo $msg . "<br>\n";
    while (ob_get_level() > 0) { @ob_flush(); }
    @flush();
}
function bk_fail(string $msg): void {
    http_response_code(500);
    echo '<div dir="rtl" style="font-family:Tahoma;max-width:720px;margin:40px auto;padding:20px;border:2px solid #d33;border-radius:12px">';
    echo '<h2 style="color:#d33">خطا</h2><p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p></div>';
    exit;
}

/* ---------- ۱) بارگذاری config از مسیرهای ممکن ---------- */
$cfg_ok = false;
foreach ([__DIR__ . '/config.php', __DIR__ . '/php-extended/config.php'] as $cf) {
    if (is_file($cf)) { require_once $cf; $cfg_ok = true; break; }
}
if (!$cfg_ok || !defined('DB_NAME')) bk_fail('config.php کنار seed-all.php نبود. این فایل باید در ریشهٔ سایت (کنار index.php) باشد.');

if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

/* ---------- ۲) محافظت کلید ---------- */
$key = isset($_GET['key']) ? (string)$_GET['key'] : '';
if ($key === '' || !defined('INSTALL_KEY') || !hash_equals((string)INSTALL_KEY, $key)) {
    http_response_code(403);
    echo '<div dir="rtl" style="font-family:Tahoma;max-width:720px;margin:40px auto;padding:20px;border:2px solid #d93;border-radius:12px">';
    echo '<h2 style="color:#d93">دسترسی مجاز نیست</h2>';
    echo '<p>آدرس را به شکل زیر باز کنید:<br><code>seed-all.php?key=INSTALL_KEY</code></p>';
    echo '<p>INSTALL_KEY همان مقداری است که در <code>config.php</code> تعریف کرده‌اید.</p></div>';
    exit;
}
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>سید قلق‌های بردخان</title></head>';
echo '<body style="font-family:Tahoma;background:#0f1a2b;color:#e8edf4;padding:24px;line-height:2.1">';
echo '<div style="max-width:860px;margin:auto"><h1 style="color:#5eead4">📱 ابزار سید ۳۰۹ قلق تعمیر گوشی</h1>';
/* شکستن بافر خروجی هاست‌های اشتراکی تا progress از ثانیهٔ اول دیده شود */
echo str_pad(' ', 4096) . "\n";
while (ob_get_level() > 0) { @ob_flush(); }
@flush();
bk_out('⏳ این عملیات ممکن است چند دقیقه طول بکشد — صفحه را نبندید. اگر اتصال قطع شد، دوباره همین آدرس را باز کنید؛ از همان‌جا ادامه می‌دهد.');

$FRESH  = isset($_GET['fresh'])  && $_GET['fresh']  == '1';
$LIST   = isset($_GET['list'])   && $_GET['list']   == '1';
$FIX    = !$LIST; /* در حالت عادی، پس از سید، اصلاح سراسری عکس‌ها هم اجرا می‌شود */

/* ---------- ۳) مسیرها ---------- */
$ROOT = __DIR__;
$seedDataCandidates = [$ROOT . '/seed-data', $ROOT . '/php-extended/seed-data'];
$SEED_DATA = '';
foreach ($seedDataCandidates as $c) if (is_dir($c)) { $SEED_DATA = $c; break; }
if ($SEED_DATA === '') bk_fail('پوشهٔ seed-data (شامل part1.json…part14.json و image_map.php) کنار seed-all.php یا داخل php-extended نیست.');

$mediaCandidates = [$ROOT . '/uploads-seed/tips', $ROOT . '/php-extended/uploads-seed/tips'];
$MEDIA_SRC = '';
foreach ($mediaCandidates as $c) if (is_dir($c)) { $MEDIA_SRC = $c; break; }
if ($MEDIA_SRC === '') bk_fail('پوشهٔ uploads-seed/tips (۱۴ فایل tip-*.jpg) کنار seed-all.php یا داخل php-extended نیست.');

$UPLOADS = rtrim((string)UPLOAD_DIR, '/');   /* از config — همیشه public_html/uploads */
if (!is_dir($UPLOADS) && !@mkdir($UPLOADS, 0755, true) && !is_dir($UPLOADS)) {
    bk_fail('پوشهٔ uploads وجود ندارد و ساخته نشد: ' . htmlspecialchars($UPLOADS) . ' — دسترسی public_html را روی 755 بگذارید.');
}
if (!is_writable($UPLOADS)) bk_fail('پوشهٔ uploads قابل نوشتن نیست: ' . htmlspecialchars($UPLOADS) . ' — دسترسی آن را روی 755 بگذارید.');

bk_out('مسیر دیتاست: <code>' . htmlspecialchars($SEED_DATA) . '</code>');
bk_out('مسیر عکس‌ها: <code>' . htmlspecialchars($MEDIA_SRC) . '</code>');
bk_out('مقصد عکس‌ها (UPLOAD_DIR): <code>' . htmlspecialchars($UPLOADS) . '</code>');

/* ---------- ۴) اتصال دیتابیس ---------- */
$pdo = db();
bk_out('اتصال دیتابیس: ✔ (' . DB_NAME . ')');

/* ---------- ۵) نویسنده ---------- */
$st = $pdo->prepare('SELECT id, name FROM users WHERE email = ? LIMIT 1');
$st->execute(['admin@bordkhan.ir']);
$author = $st->fetch();
if (!$author) {
    $st = $pdo->prepare("SELECT id, name FROM users WHERE role IN ('admin','superadmin') ORDER BY id ASC LIMIT 1");
    $st->execute();
    $author = $st->fetch();
}
if (!$author) bk_fail('هیچ کاربر ادمینی در دیتابیس نیست. اول install.php را اجرا کنید.');
$AUTHOR_ID = (int)$author['id'];
bk_out('نویسنده: #' . $AUTHOR_ID . ' — ' . htmlspecialchars($author['name']));

/* ---------- ۶) دستهٔ موبایل ---------- */
$st = $pdo->prepare('SELECT id FROM categories WHERE name LIKE ? AND status = "active" ORDER BY parent_id IS NOT NULL ASC, id ASC LIMIT 1');
$st->execute(['%موبایل و تبلت%']);
$cat = $st->fetch();
if (!$cat) {
    $st = $pdo->prepare("SELECT id FROM categories WHERE status='active' ORDER BY id ASC LIMIT 1");
    $st->execute();
    $cat = $st->fetch();
}
if (!$cat) bk_fail('هیچ دستهٔ فعالی نیست.');
$CAT_ID = (int)$cat['id'];
bk_out('دسته: #' . $CAT_ID);

/* ---------- ۷) بارگذاری دیتاست ---------- */
if (!is_file($SEED_DATA . '/image_map.php')) bk_fail('image_map.php داخل seed-data نیست.');
require_once $SEED_DATA . '/image_map.php';
if (!function_exists('bk_seed_image_map')) bk_fail('تابع bk_seed_image_map در image_map.php نیست.');
$map = bk_seed_image_map();

$files = glob($SEED_DATA . '/part*.json');
sort($files, SORT_NATURAL);
if (!$files) bk_fail('هیچ part*.json داخل seed-data نیست.');
$tips = [];
foreach ($files as $f) {
    $data = json_decode((string)file_get_contents($f), true);
    if (!is_array($data)) bk_fail('جیسون نامعتبر: ' . basename($f));
    foreach ($data as $i => $t) { $t['_src'] = basename($f) . '#' . ($i + 1); $tips[] = $t; }
}
bk_out('دیتاست: <b>' . count($tips) . ' قلق</b> بارگذاری شد.');

$TOTAL = count($tips);
$imgs_of = function (array $t, int $no) use ($map, $UPLOADS): array {
    /* اولویت سئو: عکس یکتای همین قلق tip-{no}.jpg اگر روی دیسک باشد */
    $u = 'tip-' . sprintf('%03d', $no) . '.jpg';
    if (is_file($UPLOADS . '/' . $u)) return ['/uploads/' . $u];
    $out = [];
    foreach (($t['imgs'] ?? []) as $k) if (isset($map[$k])) $out[] = '/uploads/' . $map[$k]['file'];
    if (!$out) $out = ['/uploads/' . $u];
    return $out;
};

/* ---------- حالت پیش‌نمایش ---------- */
if ($LIST) {
    echo '<ol style="line-height:2.4">';
    foreach ($tips as $t) echo '<li>[' . htmlspecialchars($t['cat']) . '] ' . htmlspecialchars($t['title']) . '</li>';
    echo '</ol><p><b>جمع: ' . $TOTAL . ' قلق — پیش‌نمایش (چیزی نوشته نشد)</b></p></div></body></html>';
    exit;
}

/* ---------- ۸) کپی عکس‌ها (تخت، فقط tip-*) ---------- */
$SKIPCOPY = isset($_GET['skipcopy']) && $_GET['skipcopy'] == '1';
$copied = $skipped = $failed = 0; $bytes = 0; $media_names = []; $seen = 0;
if ($SKIPCOPY) {
    bk_out('<h2 style="color:#93c5fd">مرحلهٔ ۱ — کپی عکس‌ها (رد شد با skipcopy=1)</h2>');
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($MEDIA_SRC, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $name = basename($f->getPathname());
        if (strpos($name, 'tip-') === 0) $media_names[$name] = true;
    }
    bk_out('فهرست فایل‌های مجاز خوانده شد: ' . count($media_names) . ' فایل (کپی انجام نشد)');
} else {
bk_out('<h2 style="color:#93c5fd">مرحلهٔ ۱ — کپی عکس‌های یکتا به ریشهٔ uploads (۳۲۴ فایل)</h2>');
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($MEDIA_SRC, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile()) continue;
    $name = basename($f->getPathname());
    if (strpos($name, 'tip-') !== 0) continue;
    $media_names[$name] = true;   /* همهٔ tip-* شامل ۳۰۹ عکس یکتا ثبت می‌شوند */
    $target = $UPLOADS . '/' . $name;
    if (is_file($target) && filesize($target) === $f->getSize()) { $skipped++; }
    else {
        if (@copy($f->getPathname(), $target)) { $copied++; $bytes += $f->getSize(); @chmod($target, 0644); }
        else $failed++;
    }
    $seen++;
    if ($seen % 25 === 0) bk_out("… {$seen} فایل بررسی شد (کپی {$copied}، از قبل بود {$skipped})");
}
bk_out("نتیجه: کپی {$copied} | از قبل بود {$skipped} | خطا {$failed} (" . round($bytes/1048576, 2) . " MB) — مجموع فایل‌های مجاز: " . count($media_names));
}
if ($failed > 0) bk_fail("{$failed} عکس کپی نشد. دسترسی پوشهٔ uploads را روی 755 بگذارید و دوباره همین صفحه را باز کنید.");

/* ---------- ۹) اصلاح سراسری مسیر عکس‌ها ---------- */
if ($FIX) {
    bk_out('<h2 style="color:#93c5fd">مرحلهٔ ۲ — اصلاح مسیر عکس‌های قلق‌های سیدشده</h2>');
    $rows = $pdo->query('SELECT id, images_json FROM tips WHERE images_json IS NOT NULL AND images_json != "" AND images_json != "[]"')->fetchAll(PDO::FETCH_ASSOC);
    $u = $pdo->prepare('UPDATE tips SET images_json = ? WHERE id = ?');
    $fT = 0; $fI = 0;
    foreach ($rows as $r) {
        $imgs = json_decode((string)$r['images_json'], true);
        if (!is_array($imgs) || !$imgs) continue;
        $new = []; $changed = false;
        foreach ($imgs as $p) {
            $base = basename((string)$p);
            $np = (isset($media_names['tip-' . $base]) || strpos($base, 'tip-') === 0) ? '/uploads/' . $base : ('/uploads/tip-' . $base);
            if ($np !== $p) { $changed = true; $fI++; }
            $new[] = $np;
        }
        if ($changed) { $u->execute([json_encode($new, JSON_UNESCAPED_UNICODE), (int)$r['id']]); $fT++; }
    }
    bk_out("اصلاح: {$fT} قلق و {$fI} مسیر عکس به‌روزرسانی شد.");
}

/* ---------- ۹ب) مهاجرت سئو: هر قلقِ سیدشده عکس یکتای خودش (tip-{شماره}) ---------- */
bk_out('<h2 style="color:#93c5fd">مرحلهٔ ۲ب — انتساب عکس یکتا به هر قلق (سئو)</h2>');
$mig = $pdo->query('SELECT s.item_no, s.tip_id FROM bk_seed_state s')->fetchAll(PDO::FETCH_ASSOC);
$mU = $pdo->prepare('UPDATE tips SET images_json = ? WHERE id = ?');
$mT = 0;
foreach ($mig as $row) {
    $u = '["/uploads/tip-' . sprintf('%03d', (int)$row['item_no']) . '.jpg"]';
    $mU->execute([$u, (int)$row['tip_id']]);
    $mT++;
}
bk_out("{$mT} قلق به عکس یکتای خودش (tip-001 … tip-" . sprintf('%03d', count($mig)) . ") متصل شد.");

/* ---------- ۱۰) جدول وضعیت سید ---------- */
$pdo->exec("CREATE TABLE IF NOT EXISTS bk_seed_state (
  seed_key VARCHAR(64) PRIMARY KEY,
  item_no INT NOT NULL,
  tip_id INT UNSIGNED NOT NULL,
  source VARCHAR(60) NULL,
  seeded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if ($FRESH) {
    bk_out('<h2 style="color:#fbbf24">حالت fresh — حذف قلق‌های سید قبلی</h2>');
    $ids = $pdo->query('SELECT tip_id FROM bk_seed_state')->fetchAll(PDO::FETCH_COLUMN);
    if ($ids) {
        $in = implode(',', array_map('intval', $ids));
        $n = $pdo->exec("DELETE FROM tips WHERE id IN ($in)");
        bk_out("{$n} قلق قبلی حذف شد.");
    }
    $pdo->exec('DELETE FROM bk_seed_state');
}

$doneSet = [];
foreach ($pdo->query('SELECT item_no FROM bk_seed_state')->fetchAll(PDO::FETCH_COLUMN) as $n) $doneSet[(int)$n] = true;
if ($doneSet) bk_out('ادامه: ' . count($doneSet) . ' قلق قبلاً ثبت شده — بقیه از همین‌جا ادامه می‌یابد.');

/* ---------- ۱۱) درج/به‌روزرسانی قلق‌ها ---------- */
bk_out('<h2 style="color:#93c5fd">مرحلهٔ ۳ — درج قلق‌ها</h2>');
$insTip = $pdo->prepare(
    'INSERT INTO tips(author_id,category_id,title,short_description,description,device_name,brand,model,board_number,fault_type,difficulty,solution_json,tools,images_json,video_url,attachments_json,access_type,price,visibility,status,tags,version,versions_json,featured,views,likes_count,purchases_count,rating_sum,rating_count,duplicate_of,rejection_reason,source_url,source_name,published_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NULL,?,0,0,0,0,0,NULL,NULL,NULL,NULL,?)'
);
$insState = $pdo->prepare('INSERT IGNORE INTO bk_seed_state (seed_key,item_no,tip_id,source) VALUES (?,?,?,?)');
/* به‌روزرسانی برای قلق‌های از-قبل-ثبت‌شده (تا عکس/ویدیو نسخهٔ جدید اعمال شود) */
$updTip = $pdo->prepare('UPDATE tips SET images_json=?, video_url=?, solution_json=?, tools=?, difficulty=?, fault_type=?, device_name=?, tags=?, featured=?, short_description=?, description=? WHERE id=?');

$ok = $skip = $upd = $fail = 0;
$pdo->beginTransaction();
foreach ($tips as $idx => $t) {
    $no = $idx + 1;
    $title = trim((string)$t['title']);
    $short = trim((string)$t['short']);
    $desc  = trim((string)$t['desc']);
    if (mb_strlen($title) < 5 || mb_strlen($short) < 5 || mb_strlen($desc) < 10 || empty($t['steps'])) { $fail++; continue; }

    $steps = [];
    foreach ($t['steps'] as $s) $steps[] = ['title' => (string)$s[0], 'body' => (string)$s[1]];
    $solutionJson = json_encode($steps, JSON_UNESCAPED_UNICODE);
    $imagesJson = json_encode($imgs_of($t, $no), JSON_UNESCAPED_UNICODE);
    $tools = trim((string)($t['tools'] ?? ''));
    $videoUrl = !empty($t['vid']) ? 'https://www.aparat.com/v/' . $t['vid'] : '';
    $diff = in_array($t['diff'] ?? '', ['easy','medium','hard'], true) ? $t['diff'] : 'medium';
    $access = 'free'; $price = 0;
    if ($no % 4 === 0) $access = 'like';
    elseif ($no % 9 === 0) { $access = 'paid'; $price = 25000 + ($no % 5) * 10000; }
    $featured = (int)($no % 3 === 0);
    $publishedAt = date('Y-m-d H:i:s', strtotime('-' . (($TOTAL - $no) * 3 + rand(0, 2)) . ' hours'));

    try {
        if (isset($doneSet[$no])) {
            /* به‌روزرسانی ساکت قلق موجود (بدون تغییر author و آمار) */
            $qid = $pdo->prepare('SELECT id FROM tips WHERE id = (SELECT tip_id FROM bk_seed_state WHERE item_no = ? LIMIT 1) LIMIT 1');
            $qid->execute([$no]);
            $tid = (int)$qid->fetchColumn();
            if ($tid > 0) {
                $updTip->execute([$imagesJson, $videoUrl, $solutionJson, $tools, $diff, (string)$t['fault'], (string)$t['device'], (string)$t['tags'], $featured, $short, $desc, $tid]);
                $upd++;
            }
            $skip++;
            continue;
        }
        $insTip->execute([
            $AUTHOR_ID, $CAT_ID, $title, $short, $desc,
            (string)$t['device'], (string)$t['brand'], (string)($t['model'] ?? ''), '',
            (string)$t['fault'], $diff, $solutionJson, $tools, $imagesJson, $videoUrl, '[]',
            $access, $price, 'public', 'published', (string)$t['tags'], $featured, $publishedAt,
        ]);
        $tipId = (int)$pdo->lastInsertId();
        $insState->execute(['seed-mobile-' . $no, $no, $tipId, $t['_src']]);
        $ok++;
        if ($ok % 20 === 0) { $pdo->commit(); $pdo->beginTransaction(); bk_out("… {$ok} قلق ثبت شد"); }
    } catch (Throwable $e) {
        bk_out('⚠️ رکورد ' . $no . ' (' . htmlspecialchars($title) . '): ' . htmlspecialchars($e->getMessage()));
        $fail++;
    }
}
$pdo->commit();

/* ---------- ۱۲) خلاصه ---------- */
$c = (int)$pdo->query("SELECT COUNT(*) FROM tips WHERE status='published'")->fetchColumn();
$wimg = (int)$pdo->query("SELECT COUNT(*) FROM tips WHERE status='published' AND images_json IS NOT NULL AND images_json NOT IN ('', '[]')")->fetchColumn();
$wvid = (int)$pdo->query("SELECT COUNT(*) FROM tips WHERE status='published' AND video_url != '' AND video_url IS NOT NULL")->fetchColumn();
echo '<div style="background:#123055;border:1px solid #2c4a77;border-radius:12px;padding:18px;margin-top:18px">';
bk_out("<b>خلاصه:</b> {$ok} درج جدید | {$upd} به‌روزرسانی | {$skip} موجود | {$fail} خطا");
bk_out("کل قلق‌های منتشرشدهٔ سایت: <b>{$c}</b> | دارای عکس: <b>{$wimg}</b> | دارای ویدیو: <b>{$wvid}</b>");
bk_out('✅ تمام شد. حالا سایت را باز کنید (Ctrl+F5) — قلق‌ها با عکس و ویدیو باید نمایش داده شوند.');
bk_out('⚠️ <b>امنیت:</b> این فایل و پوشه‌های seed-data و uploads-seed را از سرور حذف کنید.');
echo '</div></div></body></html>';
