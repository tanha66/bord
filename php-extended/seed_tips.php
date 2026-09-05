<?php
/**
 * Bordkhan — سید کردن ۲۰۰+ قلق تعمیر گوشی به زبان فارسی
 * =========================================================
 * منابع محتوا: دانش فنی گردآوری‌شده از منابع معتبر تعمیرات (iFixit، تعمیرکاران حرفه‌ای،
 * ویدئوهای آموزشی آپارات مانند امداد موبایل، Hellomobile، هارد ریست، DoctorMobile،
 * مجتمع آموزشی پل، شهر سخت‌افزار) + عکس‌های آزاد Pexels/Wikimedia + تصاویر تولیدشده.
 *
 * روش اجرا:
 *   php seed_tips.php                    → نصب/ادامه (resume) — امن برای اجرای مکرر
 *   php seed_tips.php --fresh            → حذف قلق‌های قبلی این سید و نصب از نو
 *   php seed_tips.php --list             → فقط شمارش و پیش‌نمایش، بدون نوشتن
 *
 * نکتهٔ امنیتی: پس از اجرا، این فایل را از سرور حذف کنید.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/seed-data/image_map.php';

/* ---------- پارامترها ---------- */
$FRESH = in_array('--fresh', $argv, true);
$LIST  = in_array('--list', $argv, true);

/* شناسهٔ کاربرِ نویسندهٔ قلق‌ها (ادمین) */
$AUTHOR_EMAIL = 'admin@bordkhan.ir';

/* دستهٔ «موبایل و تبلت» — قلق‌های موبایل زیر همین دسته ثبت می‌شوند */
$MOBILE_CATEGORY_NAME = 'موبایل و تبلت';

/* پیشوند شناسهٔ سید — برای idempotency و --fresh */
$SEED_MARKER_PREFIX = 'seed-mobile-';

/* ---------- اتصال دیتابیس ---------- */
$pdo = db();
echo "================ Bordkhan Mobile-Tips Seeder ================\n";
echo 'DB: ' . DB_NAME . ' @ ' . DB_HOST . "\n";

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
    fwrite(STDERR, "خطا: کاربر نویسنده ({$AUTHOR_EMAIL} یا ادمین) پیدا نشد. ابتدا install.php را اجرا کنید.\n");
    exit(1);
}
echo "نویسنده: #{$author['id']} — {$author['name']}\n";
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
    fwrite(STDERR, "خطا: هیچ دسته‌بندی فعالی پیدا نشد.\n");
    exit(1);
}
$CAT_ID = (int)$cat['id'];
echo "دسته: #{$CAT_ID}\n";

/* ---------- بارگذاری دیتاست ---------- */
$map = bk_seed_image_map();
$DATA_DIR = __DIR__ . '/seed-data';
$files = glob($DATA_DIR . '/part*.json');
sort($files, SORT_NATURAL);
if (!$files) {
    fwrite(STDERR, "خطا: هیچ فایل دیتاستی در {$DATA_DIR} پیدا نشد.\n");
    exit(1);
}

$tips = [];
foreach ($files as $f) {
    $raw = file_get_contents($f);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        fwrite(STDERR, "خطا: فایل {$f} جیسون معتبر نیست: " . json_last_error_msg() . "\n");
        exit(1);
    }
    foreach ($data as $i => $t) {
        $t['_src'] = basename($f) . '#' . ($i + 1);
        $tips[] = $t;
    }
}
echo 'دیتاست بارگذاری شد: ' . count($tips) . " قلق\n";

/* نگاشت تصاویر به مسیر واقعی /uploads */
$resolve_img = function (string $key) use ($map): ?string {
    if (isset($map[$key])) return '/uploads/tips/' . $map[$key]['file'];
    return null;
};

/* ---------- حالت‌ها ---------- */
if ($LIST) {
    foreach ($tips as $i => $t) {
        printf("%3d. [%s] %s\n", $i + 1, $t['cat'], $t['title']);
    }
    printf("\nجمع: %d قلق — حالت پیش‌نمایش (چیزی نوشته نشد)\n", count($tips));
    exit(0);
}

/* ---------- ساخت جدول کمکی وضعیت سید (idempotency + resume) ---------- */
$pdo->exec("CREATE TABLE IF NOT EXISTS bk_seed_state (
  seed_key VARCHAR(64) PRIMARY KEY,
  item_no INT NOT NULL,
  tip_id INT UNSIGNED NOT NULL,
  source VARCHAR(60) NULL,
  seeded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if ($FRESH) {
    /* حذف قلق‌های سید قبلی + پاک‌سازی وضعیت */
    $rows = $pdo->query('SELECT tip_id FROM bk_seed_state')->fetchAll(PDO::FETCH_COLUMN);
    if ($rows) {
        $in = implode(',', array_map('intval', $rows));
        $n = $pdo->exec("DELETE FROM tips WHERE id IN ($in)");
        echo "حالت --fresh: {$n} قلق قبلی حذف شد.\n";
    }
    $pdo->exec('DELETE FROM bk_seed_state');
}

/* آمار موجود برای resume */
$doneStmt = $pdo->query('SELECT item_no FROM bk_seed_state');
$doneSet = [];
foreach ($doneStmt->fetchAll(PDO::FETCH_COLUMN) as $no) $doneSet[(int)$no] = true;
$already = count($doneSet);
if ($already > 0) {
    echo "ادامه از رکورد " . ($already + 1) . " ({$already} قلق قبلاً ثبت شده)\n";
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
        fwrite(STDERR, "پرش از رکورد {$no}: داده ناقص\n");
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

    /* تاریخ انتشار تدریجی (شبیه‌سازی فعالیت طبیعی — هر قلق با فاصلهٔ چند ساعت) */
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
            echo "  ... {$ok} قلق ثبت شد\n";
        }
    } catch (Throwable $e) {
        fwrite(STDERR, "خطا در رکورد {$no} ({$title}): " . $e->getMessage() . "\n");
        $fail++;
    }
}
$pdo->commit();

$secs = time() - $startedAt;
echo "=============================================================\n";
echo "پایان: {$ok} ثبت جدید، {$skip} از قبل موجود، {$fail} خطا — {$secs} ثانیه\n";
$c = (int)$pdo->query("SELECT COUNT(*) FROM tips WHERE status='published'")->fetchColumn();
echo "جمع قلق‌های منتشرشده در سایت: {$c}\n";
echo "توجه: فایل‌های تصویر را از uploads-seed/tips/ به uploads/tips/ کپی کنید (یا اسکریپت copy_seed_media.php را اجرا کنید).\n";
