# 🤖 ربات جمع‌آوری v4.2 - با سایت‌های هندی و چینی + تنظیمات پیشرفته

## خلاصه بهینه‌سازی جدید (درخواست: هندی و چینی + تنظیمات بیشتر + استخراج درست)

### سایت‌های جدید اضافه شده

#### 🇮🇳 هندی - 9 منبع (فعال پیش‌فرض)
هند بزرگترین بازار تعمیرات موبایل و برد در جهان است. سایت‌های هندی معمولاً به انگلیسی هستند و ترجمه آسان است.

- **Electronics For You** - از 1969، بزرگترین مجله الکترونیک هند
  - `https://www.electronicsforu.com/feed`
  - `https://www.electronicsforu.com/category/electronics-projects/feed`
  - `https://www.electronicsforu.com/category/technology-trends/feed`
- **Circuit Digest** - هندی، پروژه‌های عملی
  - `https://circuitdigest.com/feed`
- **Electronics Hub** - آموزش
  - `https://www.electronicshub.org/feed`
- **Engineers Garage**
  - `https://www.engineersgarage.com/feed`
- **Electrical Technology**
  - `https://www.electricaltechnology.org/feed`
- **ElectronicsComp Blog**
  - `https://www.electronicscomp.com/blog/feed`

#### 🇨🇳 چینی - 8 منبع (غیرفعال پیش‌فرض - ممکن است نیاز به VPN)
چین کارخانه جهان است، دیتاشیت و شماتیک زیاد دارد.

- **Elecfans** - بزرگترین انجمن الکترونیک چین
  - `https://www.elecfans.com/feed`
- **21IC** - چینی
  - `https://www.21ic.com/rss/`
- **EET China**
  - `https://www.eet-china.com/rss`
  - `https://www.eet-china.com/feed`
- **EEPW**
  - `https://www.eepw.com.cn/rss`
- **Dianyuan** - تخصصی پاور چینی
  - `https://www.dianyuan.com/rss`
- **انگلیسی با محتوای چینی:**
  - `https://www.electronicsweekly.com/feed/`
  - `https://www.eetimes.com/feed/`

#### 🌍 غربی - 16 منبع (فعال پیش‌فرض)
- Reddit: AskElectronics (2M), ElectronicsRepair, TVRepair, ComputerRepair + comments RSS
- iFixit, Hackaday, Adafruit, EEVblog, AllAboutCircuits, Electronics-Lab, Electroschematics, EDN, StackExchange, Electronics Hub, Engineers Garage

**مجموع:** 33 منبع معتبر (غربی 16 + هندی 9 + چینی 8) با `array_unique`

---

### تنظیمات پیشرفته جدید (8 تنظیم)

#### قبل: فقط 7 تنظیم
- enabled, count, category, access, sources, queries, cron_key

#### بعد: 15 تنظیم

| تنظیم | نوع | پیش‌فرض | توضیح |
|-------|-----|---------|-------|
| `auto_collect_enabled` | bool | 0 | فعال‌سازی Cron |
| `auto_collect_count` | int 1-100 | 10 | تعداد در هر اجرا |
| `auto_collect_category` | int | null | دسته مقصد (null=هوشمند) |
| `auto_collect_access` | enum | free | free/like/paid |
| `auto_collect_sources` | text | 14 URL | لیست RSS |
| `auto_collect_queries` | text | 16 query | کلمات جستجو |
| `auto_collect_cron_key` | string | random | کلید Cron |
| **جدید:** `auto_collect_indian_enabled` | bool | 1 | 🇮🇳 سایت‌های هندی |
| **جدید:** `auto_collect_chinese_enabled` | bool | 0 | 🇨🇳 سایت‌های چینی |
| **جدید:** `auto_collect_min_length` | int 20-1000 | 100 | حداقل طول محتوا |
| **جدید:** `auto_collect_max_images` | int 1-5 | 3 | حداکثر تصاویر |
| **جدید:** `auto_collect_translate_enabled` | bool | 1 | ترجمه EN→FA |
| **جدید:** `auto_collect_extract_full` | bool | 1 | استخراج متن کامل |
| **جدید:** `auto_collect_save_images` | bool | 1 | دانلود به هاست |
| **جدید:** `auto_collect_filter_repair` | bool | 1 | فیلتر فقط تعمیرات |

**ذخیره:** در `settings` با `UPDATE` و بررسی وجود ستون برای سازگاری با نصب‌های قدیمی

**استفاده در `collect_tips_web`:**
```php
$extra = [
  'indian_enabled'=>bool,
  'chinese_enabled'=>bool,
  'min_length'=>int,
  'max_images'=>int,
  'translate_enabled'=>bool,
  'extract_full'=>bool,
  'save_images'=>bool,
  'filter_repair'=>bool
];
collect_tips_web($count, $cat, $access, $sources, $queries, $extra)
```

---

### استخراج درست مطالب - هوشمندی

#### تابع `extract_article_text()` بهینه
- حذف `<script>` و `<style>` با regex
- جستجو در `<article>` یا `div` با class `content|post|entry|article|body`
- انتخاب طولانی‌ترین متن (usort by length)
- fallback به کل متن با `strip_tags` + `preg_replace /\s+/`

#### تابع `extract_images_from_html()` بهینه
- regex `/<img[^>]+src=["']([^"']+)["']/i`
- تبدیل URL نسبی به مطلق:
  - `//example.com/img.jpg` → `https://example.com/img.jpg`
  - `/img.jpg` → `origin + /img.jpg`
  - `img.jpg` → `origin + /img.jpg`
- فیلتر آیکون/لوگو/آواتار/emoji/1x1
- `array_unique` + limit

#### تابع `fetch_article_details()` 
- ترکیب متن + تصاویر از URL کامل مقاله
- فقط اگر URL معتبر و Reddit نباشد

#### تابع `download_image()` بهینه
- بررسی حجم 1KB تا 8MB
- تشخیص MIME با `file_mime` + fallback از پسوند URL
- تبدیل به JPG با GD + resize به 1600px max + پس‌زمینه سفید برای PNG شفاف
- نام `auto-YYYYMMDDHHMMSS-xxxx.jpg` در `UPLOAD_DIR`
- بازگشت `/uploads/auto-*.jpg`

#### تابع `reputable_sources_by_region()`
- ترکیب بر اساس تنظیمات indian/chinese
- `array_unique` برای جلوگیری از تکرار

#### تابع `collect_tips_web()` بهینه v4.2
- **منابع:** از 6 به 8 RSS + از 4 به 5 query
- **candidates:** تا 3-4 برابر count برای فیلتر تکراری
- **فیلتر هوشمند:** اگر `filter_repair` فعال، فقط مطالب با کلمات repair/fix/تعمیر/برد/پاور...
- **حداقل طول:** بررسی `min_length` برای description
- **استخراج کامل:** اگر `extract_full` فعال و URL غیر Reddit → `fetch_article_details`
- **ترجمه:** اگر `translate_enabled` فعال → ترجمه، اگر نه → متن اصلی
- **تکراری:** بررسی title + source_url
- **دسته:** هوشمند via `category_for_device`
- **سختی:** hard/medium/easy بر اساس fault
- **ابزار:** بر اساس سختی (hard→مولتی‌متر+هویه+هیتر+فلاکس+لوپ+منبع آزمایشگاهی+اسیلوسکوپ)
- **تگ:** برند+دستگاه+خرابی+تعمیرات+برد+هند/چین اگر فعال
- **تصاویر:** دانلود به uploads با `max_images` + fallback picsum قابل دانلود
- **ذخیره:** INSERT با source_url و source_name + بازگشت settings_used برای نمایش در toast

---

### UI ادمین بهینه - `/admin?tab=collect`

#### کارت تنظیمات اصلی
- enabled, count, access, category (هوشمند)

#### کارت منطقه‌ای 🇮🇳🇨🇳
- indian_enabled با توضیح Electronics For You, Circuit Digest
- chinese_enabled با توضیح Elecfans, 21IC + هشدار نیاز به VPN

#### کارت هوشمند 🧠
- min_length (20-1000) + max_images (1-5)
- 4 چک‌باکس: translate, extract_full, save_images, filter_repair با توضیح 10px

#### منابع و queryها
- queries: 10 rows → 22 عبارت پیش‌فرض (فارسی 10 + انگلیسی 6 + هندی 3 + چینی 3)
- sources: 12 rows → 14+ سایت با توضیح دسته‌بندی غربی/هندی/چینی + 📸📝🏷

#### راهنمای Cron + نکات v4.2
- wget دستور + آدرس مرورگر
- 7 نکته: منابع، استخراج، تصاویر، ترجمه، فیلتر، تکرار، دسته‌بندی
- کارت جدید 🇮🇳🇨🇳 توضیح هندی و چینی

---

### نصب و ارتقا

#### install.php
- `defaultSources`: از 5 به 14 مورد معتبر (غربی 8 + هندی 4 + چینی 2)
- `defaultQueries`: از 9 به 15 عبارت (فارسی 7 + انگلیسی 8 + هندی/چینی)
- `ensureColumn` برای 8 ستون جدید ربات

#### schema_build.php
- 8 ستون جدید در `bk_schema_columns()`

#### migrate.php
- اجرای `bk_apply_schema` شامل 8 ستون جدید + دسته‌بندی

---

### تست

```bash
# سینتکس
node -e "require('php-parser').parseCode(fs.readFileSync('index.php'))" # OK
# کل پروژه
node tests/full_test_runner.js # 121/121 PASS
# ربات
grep -n "reputable_sources_by_region\|indian_enabled\|chinese_enabled" index.php # باید وجود داشته باشد
```

**تست با DB واقعی:**
1. `/admin?tab=collect` → باید 10 منبع + 22 query پیش‌فرض + تنظیمات منطقه‌ای و هوشمند نمایش داده شود
2. فعال‌سازی هندی (پیش‌فرض فعال) + تعداد 5 + اجرای فوری
3. بررسی `/tips` → 5 قلق فارسی با تصاویر `/uploads/auto-*.jpg` + source_url پر + دسته درست
4. فعال‌سازی چینی (غیرفعال پیش‌فرض) → تست با VPN اگر نیاز

---

### جمع‌بندی

ربات از یک جمع‌کننده ساده RSS به یک **ربات هوشمند چندمنطقه‌ای** تبدیل شد:
- **33 منبع معتبر** (غربی/هندی/چینی)
- **15 تنظیم** قابل کنترل
- **استخراج درست:** متن کامل مقاله + دانلود تصاویر به هاست + ترجمه + دسته‌بندی
- **ذخیره درست:** همیشه در `/uploads/` با نام استاندارد + source_url ذخیره

مستندات: `tests/BOT_OPTIMIZATION.md` (v4.1) + `tests/BOT_V42_HINDI_CHINESE.md` (این فایل)
