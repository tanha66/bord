<?php
/**
 * Bordkhan — سید کردن ۲۰۰+ قلق تعمیر گوشی به زبان فارسی
 * =========================================================
 * اجرا از دو راه:
 *   ۱) SSH/CLI:   php seed_tips.php            (نصب/ادامه — امن برای اجرای مکرر)
 *                 php seed_tips.php --fresh    (حذف قبلی‌ها و نصب از نو)
 *                 php seed_tips.php --list     (فقط پیش‌نمایش)
 *   ۲) مرورگر:    https://site.com/seed_tips.php?key=INSTALL_KEY
 *                 https://site.com/seed_tips.php?key=INSTALL_KEY&fresh=1
 *                 https://site.com/seed_tips.php?key=INSTALL_KEY&list=1
 *
 * محل فایل: هم در ریشهٔ سایت کار می‌کند هم داخل پوشهٔ php-extended.
 * دیتاست (پوشهٔ seed-data) باید یکی از این دو جا باشد: کنار این فایل، یا داخل php-extended.
 *
 * ⚠️ امنیت: اجرای مرورگری فقط با کلید INSTALL_KEY تعریف‌شده در config.php ممکن است.
 * پس از نصب موفق، این فایل را از سرور حذف کنید.
 */

/* ---------- کمکی‌های سازگار با CLI و وب ---------- */
$BK_IS_CLI = (PHP_SAPI === 'cli');

function bk_seed_out(string $msg): void {
    echo $msg . "\n";
    if (PHP_SAPI !== 'cli') { @flush(); @ob_flush(); }
}

function bk_seed_fail(string $msg): void {
    if (PHP_SAPI === 'cli') { fwrite(STDERR, "خطا: " . $msg . "\n"); exit(1); }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "خطا: " . $msg . "\n";
    exit(1);
}

/* ---------- بارگذاری config از مسیرهای ممکن ---------- */
$bk_cfg_candidates = [
    __DIR__ . '/../config.php',              /* فایل داخل php-extended است */
    __DIR__ . '/config.php',                 /* فایل در ریشهٔ سایت است */
    dirname(__DIR__, 2) . '/config.php',     /* یک سطح عمیق‌تر (احتیاط) */
];
$bk_cfg_loaded = false;
foreach ($bk_cfg_candidates as $bk_cfg) {
    if (is_file($bk_cfg)) { require_once $bk_cfg; $bk_cfg_loaded = true; break; }
}
if (!$bk_cfg_loaded || !defined('DB_NAME')) {
    bk_seed_fail('فایل config.php پیدا نشد. این فایل را در ریشهٔ سایت یا داخل پوشهٔ php-extended قرار دهید.');
}

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

/* ---------- محافظت اجرای مرورگری با کلید نصب ---------- */
if (!$BK_IS_CLI) {
    $bk_key = isset($_GET['key']) ? (string)$_GET['key'] : '';
    if ($bk_key === '' || !defined('INSTALL_KEY') || !hash_equals((string)INSTALL_KEY, $bk_key)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "دسترسی مجاز نیست.\nآدرس را به شکل زیر باز کنید:\n";
        echo "seed_tips.php?key=INSTALL_KEY\n";
        echo "(INSTALL_KEY همان کلیدی است که در config.php تعریف کرده‌اید)\n";
        exit;
    }
    /* مهلت اجرای بلندتر برای هاست اشتراکی — در صورت بلاک بودن، نادیده گرفته می‌شود */
    @set_time_limit(0);
    @ignore_user_abort(false);
    header('Content-Type: text/plain; charset=utf-8');
}

/* ---------- پارامترها (CLI و وب) ---------- */
$FRESH = ($BK_IS_CLI && isset($argv) && in_array('--fresh', $argv, true)) || (!$BK_IS_CLI && isset($_GET['fresh']) && $_GET['fresh'] == '1');
$LIST  = ($BK_IS_CLI && isset($argv) && in_array('--list', $argv, true))  || (!$BK_IS_CLI && isset($_GET['list'])  && $_GET['list']  == '1');

/* شناسهٔ کاربرِ نویسندهٔ قلق‌ها (ادمین) */
$AUTHOR_EMAIL = 'admin@bordkhan.ir';

/* دستهٔ «موبایل و تبلت» — قلق‌های موبایل زیر همین دسته ثبت می‌شوند */
$MOBILE_CATEGORY_NAME = 'موبایل و تبلت';

/* پیشوند شناسهٔ سید — برای idempotency و --fresh */
$SEED_MARKER_PREFIX = 'seed-mobile-';

/* ---------- اتصال دیتابیس ---------- */
$pdo = db();
bk_seed_out("================ Bordkhan Mobile-Tips Seeder ================");
bk_seed_out('DB: ' . DB_NAME . ' @ ' . DB_HOST . ($BK_IS_CLI ? '' : ' (web mode)'));

/* ---------- یافتن کاربر نویسنده ---------- */
$st = $pdo->prepare('SELECT id, name FROM users WHERE email = ? LIMIT 1');
$st->execute([$AUTHOR_EMAIL]);
$author = $st->fetch();
if (!$author) {
    $st = $pdo->prepare("SELECT id, name FROM users WHERE role IN ('admin','superadmin') ORDER BY id ASC LIMIT 1");
    $st->execute();
    $author = $st->fetch();
}
if (!$author) {
    bk_seed_fail('کاربر نویسنده (' . $AUTHOR_EMAIL . ' یا ادمین) پیدا نشد. ابتدا install.php را اجرا کنید.');
}
bk_seed_out('نویسنده: #' . $author['id'] . ' — ' . $author['name']);
$AUTHOR_ID = (int)$author['id'];

/* ---------- یافتن دستهٔ موبایل (والد یا فرزند) ---------- */
$st = $pdo->prepare('SELECT id FROM categories WHERE name LIKE ? AND status = "active" ORDER BY parent_id IS NOT NULL ASC, id ASC LIMIT 1');
$st->execute(['%' . $MOBILE_CATEGORY_NAME . '%']);
$cat = $st->fetch();
if (!$cat) {
    $st = $pdo->prepare("SELECT id FROM categories WHERE status='active' ORDER BY id ASC LIMIT 1");
    $st->execute();
    $cat = $st->fetch();
}
if (!$cat) {
    bk_seed_fail('هیچ دسته‌بندی فعالی پیدا نشد.');
}
$CAT_ID = (int)$cat['id'];
bk_seed_out('دسته: #' . $CAT_ID);

/* ---------- بارگذاری نگاشت تصاویر ---------- */
$bk_map_candidates = [
    __DIR__ . '/seed-data/image_map.php',
    __DIR__ . '/php-extended/seed-data/image_map.php',
];
$bk_map_loaded = false;
foreach ($bk_map_candidates as $bk_map_file) {
    if (is_file($bk_map_file)) { require_once $bk_map_file; $bk_map_loaded = true; break; }
}
if (!$bk_map_loaded || !function_exists('bk_seed_image_map')) {
    bk_seed_fail('فایل seed-data/image_map.php پیدا نشد. پوشهٔ seed-data را کنار این فایل یا داخل php-extended قرار دهید.');
}
$map = bk_seed_image_map();

/* ---------- بارگذاری دیتاست ---------- */
$bk_data_candidates = [
    __DIR__ . '/seed-data',
    __DIR__ . '/php-extended/seed-data',
];
$DATA_DIR = '';
foreach ($bk_data_candidates as $bk_dir) {
    if (is_dir($bk_dir) && glob($bk_dir . '/part*.json')) { $DATA_DIR = $bk_dir; break; }
}
if ($DATA_DIR === '') {
    bk_seed_fail('پوشهٔ seed-data (شامل part1.json تا part10.json) پیدا نشد. آن را کنار این فایل یا داخل php-extended بگذارید.');
}

$files = glob($DATA_DIR . '/part*.json');
sort($files, SORT_NATURAL);

$tips = [];
foreach ($files as $f) {
    $raw = file_get_contents($f);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        bk_seed_fail('فایل ' . basename($f) . ' جیسون معتبر نیست: ' . json_last_error_msg());
    }
    foreach ($data as $i => $t) {
        $t['_src'] = basename($f) . '#' . ($i + 1);
        $tips[] = $t;
    }
}
bk_seed_out('دیتاست بارگذاری شد: ' . count($tips) . " قلق");

/* نگاشت تصاویر به مسیر واقعی /uploads */
$resolve_img = function (string $key) use ($map): ?string {
    if (isset($map[$key])) return '/uploads/tips/' . $map[$key]['file'];
    return null;
};

/* ---------- حالت پیش‌نمایش ---------- */
if ($LIST) {
    foreach ($tips as $i => $t) {
        bk_seed_out(sprintf("%3d. [%s] %s", $i + 1, $t['cat'], $t['title']));
    }
    bk_seed_out("\nجمع: " . count($tips) . " قلق — حالت پیش‌نمایش (چیزی نوشته نشد)");
    exit;
}

/* ---------- جدول کمکی وضعیت سید (idempotency + resume) ---------- */
$pdo->exec("CREATE TABLE IF NOT EXISTS bk_seed_state (
  seed_key VARCHAR(64) PRIMARY KEY,
  item_no INT NOT NULL,
  tip_id INT UNSIGNED NOT NULL,
  source VARCHAR(60) NULL,
  seeded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if ($FRESH) {
    $rows = $pdo->query('SELECT tip_id FROM bk_seed_state')->fetchAll(PDO::FETCH_COLUMN);
    if ($rows) {
        $in = implode(',', array_map('intval', $rows));
        $n = $pdo->exec("DELETE FROM tips WHERE id IN ($in)");
        bk_seed_out("حالت fresh: {$n} قلق قبلی حذف شد.");
    }
    $pdo->exec('DELETE FROM bk_seed_state');
}

/* آمار موجود برای resume */
$doneStmt = $pdo->query('SELECT item_no FROM bk_seed_state');
$doneSet = [];
foreach ($doneStmt->fetchAll(PDO::FETCH_COLUMN) as $no) $doneSet[(int)$no] = true;
$already = count($doneSet);
if ($already > 0) {
    bk_seed_out('ادامه از رکورد ' . ($already + 1) . " ({$already} قلق قبلاً ثبت شده)");
}

/* ---------- آماده‌سازی statementها ---------- */
$insTip = $pdo->prepare(
    'INSERT INTO tips(author_id,category_id,title,short_description,description,device_name,brand,model,board_number,fault_type,difficulty,solution_json,tools,images_json,video_url,attachments_json,access_type,price,visibility,status,tags,version,versions_json,featured,views,likes_count,purchases_count,rating_sum,rating_count,duplicate_of,rejection_reason,source_url,source_name,published_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NULL,?,0,0,0,0,0,NULL,NULL,NULL,NULL,?)'
);
$insState = $pdo->prepare('INSERT IGNORE INTO bk_seed_state (seed_key,item_no,tip_id,source) VALUES (?,?,?,?)');

/* ---------- حلقهٔ اصلی ---------- */
$ok = 0; $skip = 0; $fail = 0;
$startedAt = time();
$total = count($tips);

$pdo->beginTransaction();
foreach ($tips as $idx => $t) {
    $no = $idx + 1;
    if (isset($doneSet[$no])) { $skip++; continue; }

    $title = trim((string)$t['title']);
    $short = trim((string)$t['short']);
    $desc  = trim((string)$t['desc']);
    $key   = $SEED_MARKER_PREFIX . $no;

    /* اعتبارسنجی حداقلی */
    if (mb_strlen($title) < 5 || mb_strlen($short) < 5 || mb_strlen($desc) < 10 || empty($t['steps'])) {
        bk_seed_out("هشدار: رکورد {$no} ناقص بود و رد شد.");
        $fail++;
        continue;
    }

    /* مراحل راه‌حل */
    $steps = [];
    foreach ($t['steps'] as $s) {
        $steps[] = ['title' => (string)$s[0], 'body' => (string)$s[1]];
    }
    $solutionJson = json_encode($steps, JSON_UNESCAPED_UNICODE);

    /* تصاویر */
    $images = [];
    foreach (($t['imgs'] ?? []) as $imgKey) {
        $p = $resolve_img((string)$imgKey);
        if ($p !== null) $images[] = $p;
    }
    $imagesJson = json_encode($images, JSON_UNESCAPED_UNICODE);

    /* ابزارها — با «،» جدا می‌شوند (فرمت نمایش سایت) */
    $tools = trim((string)($t['tools'] ?? ''));

    /* ویدیو: آپارات → https://www.aparat.com/v/{code} */
    $videoUrl = '';
    if (!empty($t['vid'])) {
        $videoUrl = 'https://www.aparat.com/v/' . $t['vid'];
    }

    /* سطح سختی */
    $diff = in_array($t['diff'] ?? '', ['easy', 'medium', 'hard'], true) ? $t['diff'] : 'medium';

    /* دسترسی: اکثر رایگان، بخشی با لایک (الگوی توزیع واقعی سایت) */
    $access = 'free';
    $price = 0;
    if ($no % 4 === 0) { $access = 'like'; }
    elseif ($no % 9 === 0) { $access = 'paid'; $price = 25000 + ($no % 5) * 10000; }

    /* تاریخ انتشار تدریجی (شبیه‌سازی فعالیت طبیعی) */
    $publishedAt = date('Y-m-d H:i:s', strtotime("-" . (($total - $no) * 3 + rand(0, 2)) . " hours"));

    try {
        $insTip->execute([
            $AUTHOR_ID,
            $CAT_ID,
            $title,
            $short,
            $desc,
            (string)$t['device'],
            (string)$t['brand'],
            (string)($t['model'] ?? ''),
            '', /* board_number */
            (string)$t['fault'],
            $diff,
            $solutionJson,
            $tools,
            $imagesJson,
            $videoUrl,
            '[]',
            $access,
            $price,
            'public',
            'published',
            (string)$t['tags'],
            (int)($no % 3 === 0), /* featured: هر سومین قلق منتخب */
            $publishedAt,
        ]);
        $tipId = (int)$pdo->lastInsertId();
        $insState->execute([$key, $no, $tipId, $t['_src']]);
        $ok++;

        /* commit دوره‌ای برای resume سریع */
        if ($ok % 50 === 0) {
            $pdo->commit();
            $pdo->beginTransaction();
            bk_seed_out("  ... {$ok} قلق ثبت شد");
        }
    } catch (Throwable $e) {
        bk_seed_out("خطا در رکورد {$no} ({$title}): " . $e->getMessage());
        $fail++;
    }
}
$pdo->commit();

$secs = time() - $startedAt;
bk_seed_out("=============================================================");
bk_seed_out("پایان: {$ok} ثبت جدید، {$skip} از قبل موجود، {$fail} خطا — {$secs} ثانیه");
$c = (int)$pdo->query("SELECT COUNT(*) FROM tips WHERE status='published'")->fetchColumn();
bk_seed_out("جمع قلق‌های منتشرشده در سایت: {$c}");
bk_seed_out("مرحلهٔ بعد: تصاویر را با copy_seed_media.php کپی کنید (همین روش اجرا).");
bk_seed_out("⚠️ پس از اتمام، این فایل را از سرور حذف کنید.");
