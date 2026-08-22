# 🤖 بهینه‌سازی ربات جمع‌آوری خودکار - Bordkhan

**تاریخ:** 2026-08-22  
**نسخه:** 4.1 - هوشمند و بهینه

## مشکلات نسخه قبلی

### 1. تصاویر در جای درست ذخیره نمی‌شد
- **قبل:** `images_json` حاوی URL خارجی مستقیم (`https://source.unsplash.com/...` یا `https://reddit.com/...`) بود
- **مشکل:** 
  - لینک خارجی ناپایدار (Unsplash 302 می‌دهد)
  - عدم رعایت `media_url` و واترمارک
  - عدم ذخیره در `/uploads/` 
  - برای قلق‌های پولی/لایکی، تصویر اصلی لو می‌رفت
- **بعد:** تابع `download_image()` بهینه شد:
  - دانلود واقعی با `fetch_url` (25 ثانیه timeout)
  - بررسی حجم (1KB تا 8MB)
  - تشخیص MIME با `file_mime` + fallback از پسوند URL
  - تبدیل هوشمند به JPG با GD (1600px max) + پس‌زمینه سفید برای PNG شفاف
  - ذخیره با نام `auto-YYYYMMDDHHMMSS-xxxx.jpg` در `UPLOAD_DIR`
  - بازگشت `/uploads/auto-....jpg` برای ذخیره درست

### 2. سایت‌های معتبر کم بود
- **قبل:** فقط 5 منبع (2 Reddit + 3 عمومی)
- **بعد:** 14 منبع معتبر:

#### Reddit (معتبرترین انجمن تعمیرکاران)
- `https://www.reddit.com/r/AskElectronics/.rss` - 2M عضو، پرسش و پاسخ الکترونیک
- `https://www.reddit.com/r/ElectronicsRepair/.rss` - تعمیرات الکترونیک
- `https://www.reddit.com/r/TVRepair/.rss` - تخصصی تعمیر تلویزیون
- `https://www.reddit.com/r/computerrepair/.rss` - تعمیر کامپیوتر

#### مرجع تعمیرات
- `https://www.ifixit.com/News/rss` - iFixit، بزرگترین مرجع تعمیرات جهان

#### سایت‌های تخصصی الکترونیک معتبر
- `https://hackaday.com/feed/` - Hackaday، پروژه‌های الکترونیک
- `https://blog.adafruit.com/feed/` - Adafruit، آموزش آردوینو/الکترونیک
- `https://www.eevblog.com/feed/` - EEVblog، ویدیوهای تعمیرات حرفه‌ای
- `https://www.allaboutcircuits.com/new/rss/` - All About Circuits، مقالات آموزشی
- `https://www.electronics-lab.com/feed/` - Electronics Lab
- `https://www.circuitdigest.com/feed` - Circuit Digest
- `https://www.electroschematics.com/feed/` - ElectroSchematics
- `https://www.edn.com/feed/` - EDN، اخبار صنعت الکترونیک
- `https://electronics.stackexchange.com/feeds` - StackExchange مهندسی

### 3. هوشمندی کم بود

#### قبل:
- فقط عنوان + description کوتاه RSS
- ترجمه ساده با دیکشنری 60 کلمه‌ای
- تشخیص برند/دستگاه با 10 مورد
- دسته‌بندی تصادفی
- تصاویر Unsplash URL مستقیم

#### بعد - بهینه‌سازی هوشمند:

##### الف) استخراج محتوای کامل
```php
function fetch_article_details(string $url): array {
    $html = fetch_url($url, 12);
    $text = extract_article_text($html, 4000); // هوشمند از <article> یا div.content
    $images = extract_images_from_html($html, $url, 5); // استخراج 5 عکس با تبدیل نسبی→مطلق
    return ['text'=>$text, 'images'=>$images];
}
```
- اگر URL مقاله موجود باشد و Reddit نباشد، محتوای کامل صفحه را می‌خواند
- `extract_article_text()`: حذف script/style + جستجو در div.content/post/entry + انتخاب طولانی‌ترین متن
- `extract_images_from_html()`: regex img src + تبدیل URL نسبی به مطلق + فیلتر آیکون/لوگو

##### ب) ترجمه هوشمند
- دیکشنری از 60 به 90+ کلمه افزایش (مثل `motherboard repair → تعمیر مادربرد`, `led tv → تلویزیون ال‌ای‌دی`)
- مرتب‌سازی بر اساس طول نزولی برای جلوگیری از جایگزینی اشتباه

##### ج) تشخیص هوشمند
- برند: از 20 به 35 مورد (اضافه: STM32, Xbox, PlayStation, Bosch, Siemens, Philips)
- دستگاه: از 15 به 25 مورد (اضافه: کنسول بازی، پرینتر، مودم و شبکه)
- خرابی: از 20 به 35 مورد (اضافه: آب‌خوردگی، پرپر زدن، نویز تصویر، چشمک زدن)

##### د) دسته‌بندی هوشمند
```php
function category_for_device(string $device, ?int $preferred): int {
    // نگاشت دقیق دستگاه → نام دسته والد
    // سپس انتخاب اولین فرزند فعال با sort_order
}
```
- اگر دسته مقصد انتخاب شده باشد، همان را استفاده می‌کند
- اگر نه، بر اساس دستگاه تشخیص داده شده، دسته والد را پیدا و اولین فرزند فعال را انتخاب می‌کند

##### ه) تولید عنوان و توضیح هوشمند
- 5 قالب عنوان متنوع با `array_rand`
- توضیح کوتاه ثابت ولی حرفه‌ای
- توضیح کامل شامل ابزار مورد نیاز + هشدار ایمنی + منبع

##### و) ذخیره درست
- بررسی تکراری هوشمند: هم عنوان و هم `source_url`
- حداکثر 3 تصویر دانلود از منابع remote
- اگر دانلود نشد و free بود، URL خارجی موقت نگه داشته می‌شود (برای جلوگیری از خالی ماندن)
- اگر هیچ عکسی نبود، از `picsum.photos` با seed از عنوان به عنوان fallback قابل دانلود
- ذخیره `source_url` و `source_name` در دیتابیس (قبلاً NULL بود)

---

## جریان جدید ربات

```
1. دریافت bot_id (09100000000)
2. دریافت queries و sources از settings (یا پیش‌فرض معتبر 14 مورد)
3. candidates = []
   - برای هر 6 منبع RSS اول: fetch + parse_rss_items → add
   - برای هر 4 query اول: discover_reddit (3) + discover_web (3) → add
   - تا 3 برابر count مورد نیاز جمع‌آوری (برای فیلتر تکراری)
4. برای هر candidate:
   - fullText = description
   - remoteImages = image + images[]
   - اگر URL دارد و Reddit نیست: fetch_article_details → fullText و images به‌روز
   - build_persian_tip(title, fullText, url, source_name) → عنوان/توضیح/مراحل هوشمند
   - بررسی تکراری (title یا source_url)
   - دسته‌بندی هوشمند
   - تعیین سختی هوشمند (hard/medium/easy) بر اساس fault
   - ابزار هوشمند بر اساس سختی
   - دانلود تصاویر به /uploads/auto-*.jpg (حداکثر 3)
   - fallback به picsum قابل دانلود
   - INSERT با source_url و source_name
```

---

## تست ربات

### تست بدون DB (شبیه‌سازی)
```bash
# بررسی کد
grep -n "reputable_sources_list\|extract_images_from_html\|fetch_article_details" index.php

# باید 3 تابع جدید وجود داشته باشد
```

### تست با DB واقعی (روی هاست)
1. وارد `/admin?tab=collect` شوید
2. باید 10 منبع معتبر پیش‌فرض نمایش داده شود (اگر قبلاً ذخیره نشده)
3. 16 query پیش‌فرض نمایش داده شود
4. `تعداد قلق در هر اجرا` را 5 بگذارید و `اجرای فوری` بزنید
5. بررسی:
   - [ ] 5 قلق فارسی جدید در `/tips` منتشر شد
   - [ ] تصاویر در `/uploads/auto-*.jpg` ذخیره شده (نه لینک خارجی)
   - [ ] `source_url` و `source_name` در دیتابیس پر است
   - [ ] دسته‌بندی درست (مثلاً لپ‌تاپ ایسوس → لپ‌تاپ → مدل)
   - [ ] مراحل گام‌به‌گام مرتبط با fault

### Cron خودکار
```
# در cPanel → Cron Jobs، هر 6 ساعت:
wget -q -O /dev/null "https://yoursite.com/cron-collect?key=YOUR_CRON_KEY"
```

---

## مزایای نسخه بهینه

1. **تصاویر در جای درست:** همیشه در `/uploads/` با نام استاندارد، قابل استفاده با `media_url` و واترمارک
2. **سایت‌های معتبر:** 14 منبع تخصصی تعمیرات به جای 5 عمومی
3. **هوشمند:** استخراج محتوای کامل + ترجمه بهتر + تشخیص دقیق‌تر + دسته‌بندی درست
4. **جلوگیری از تکرار:** بررسی هم title و هم source_url
5. **پایدار:** fallbackهای متعدد (picsum قابل دانلود به جای unsplash URL)
6. **سریع:** محدودیت 6 RSS + 4 query برای جلوگیری از timeout

---

## فایل‌های تغییر یافته

- `index.php`: بازنویسی کامل از `fetch_url` تا `collect_tips_web` (36KB جدید) + تابع `reputable_sources_list()` + `extract_images_from_html()` + `extract_article_text()` + `fetch_article_details()`
- `pages/admin.php`: نمایش پیش‌فرض منابع معتبر و queryهای هوشمند در UI
- `install.php`: به‌روزرسانی `defaultSources` به 14 مورد معتبر + 15 query
