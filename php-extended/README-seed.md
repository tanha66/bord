# 📱 بستهٔ ۲۰۹ قلق تعمیر گوشی — بردخان

این بسته **۲۰۹ قلق تعمیر گوشی موبایل به زبان فارسی** (آیفون، سامسونگ، شیائومی/ردمی/پوکو، هواوی، آنر و قلق‌های عمومی) به‌همراه **۱۴ عکس مجاز** و **۱۰ ویدیوی آموزشی آپارات** را به دیتابیس سایت اضافه می‌کند.

## ✨ محتوا

| بخش | تعداد |
|---|---|
| آیفون | ۵۱ قلق |
| سامسونگ | ۴۵ قلق |
| شیائومی / ردمی / پوکو | ۳۴ قلق |
| هواوی | ۱۷ قلق |
| آنر | ۳ قلق |
| عمومی و آموزشی | ۵۹ قلق |
| **جمع** | **۲۰۹ قلق** |

هر قلق شامل: عنوان، خلاصه، توضیح کامل، ۴ گام راه‌حل گام‌به‌گام، ابزار لازم، برچسب‌ها، سطح سختی، عکس و در ۱۰ مورد ویدیوی آموزشی آپارات است.

## 🖼 منابع رسانه (همه مجاز)

- **۴ عکس واقعی** از Pexels و Wikimedia Commons (لایسنس آزاد، بدون واترمارک)
- **۱۰ عکس تولیدشده با هوش مصنوعی** (بدون کپی‌رایت شخص ثالث)
- **۱۰ ویدیو** از کانال‌های آموزشی معتبر آپارات — به‌صورت embed قانونی

## 🌐 نصب از مرورگر (بدون SSH — ساده‌ترین راه)

فایل‌ها را این‌طوری روی هاست بگذارید:

```
public_html/
  index.php, config.php, ...
  uploads-seed/          ← از این بسته (کل پوشه)
  php-extended/          ← اگر موجود نیست بسازید
    seed-data/           ← از این بسته (کل پوشه)
    seed_tips.php
    copy_seed_media.php
```

بعد به ترتیب این دو آدرس را باز کنید (`INSTALL_KEY` همان کلید داخل `config.php` شماست):

```text
https://bordkhan.ir/php-extended/copy_seed_media.php?key=INSTALL_KEY
https://bordkhan.ir/php-extended/seed_tips.php?key=INSTALL_KEY
```

گزینه‌های مرورگری:

```text
.../seed_tips.php?key=KEY&list=1     ← فقط پیش‌نمایش ۲۰۹ عنوان
.../seed_tips.php?key=KEY&fresh=1    ← حذف قبلی‌ها و نصب از نو
```

اگر وسط کار صفحه قطع شد (محدودیت زمانی هاست)، **دوباره همان آدرس را باز کنید** — اسکریپت از همان‌جا ادامه می‌دهد.

## 🚀 نصب با SSH/CLI (در صورت دسترسی)

```bash
php php-extended/copy_seed_media.php
php php-extended/seed_tips.php            # نصب/ادامه
php php-extended/seed_tips.php --list     # پیش‌نمایش
php php-extended/seed_tips.php --fresh    # حذف قبلی‌ها و نصب از نو
```

## 🔧 نسخهٔ phpMyAdmin (بدون CLI و بدون اجرای PHP)

فایل `seed-data/seed_mobile_tips.sql` را در phpMyAdmin → دیتابیس سایت → Import اجرا کنید. اجرای دوباره‌اش امن است. بعد از آن، پوشهٔ `uploads-seed/tips` را از File Manager به `uploads/tips` کپی کنید.

## ❓ رفع خطای 500 / خطاهای رایج

| مشکل | علت | راه‌حل |
|---|---|---|
| HTTP ERROR 500 در `bordkhan.ir/seed_tips.php` | فایل در ریشه است و config را پیدا نمی‌کند | نسخهٔ جدید را جایگزین کنید (هر دو مسیر را می‌فهمد) یا فایل را به `php-extended/` منتقل کنید |
| «دسترسی مجاز نیست» | کلید درست نیست | `?key=` را با مقدار `INSTALL_KEY` در `config.php` بگذارید |
| «پوشهٔ seed-data پیدا نشد» | دیتاست کنار فایل نیست | پوشهٔ `seed-data` را کنار فایل یا داخل `php-extended` بگذارید |
| «کاربر نویسنده پیدا نشد» | هنوز ادمینی در دیتابیس نیست | اول `install.php` را اجرا کنید |
| خطای اتصال دیتابیس | config ناقص | `DB_NAME/DB_USER/DB_PASS` را در `config.php` چک کنید |
| تصاویر قلق‌ها 404 می‌دهند | فایل‌های عکس کپی نشده‌اند | `copy_seed_media.php` را اجرا کنید یا `uploads-seed/tips` را دستی به `uploads/tips` کپی کنید |

## 📂 نقشهٔ فایل‌ها

```
seed_tips.php              ← سیدر اصلی (وب + CLI)
copy_seed_media.php        ← کپی تصاویر به uploads/tips (وب + CLI)
seed-data/
  part1.json ... part10.json  ← دیتاست ۲۰۹ قلق
  image_map.php               ← نگاشت کلید تصویر → فایل
  seed_mobile_tips.sql        ← نسخهٔ SQL خالص برای phpMyAdmin
uploads-seed/tips/
  mobile/ …                ← ۴ عکس واقعی آزاد
  gen/ …                   ← ۱۰ عکس تولیدی
```

## ⚠️ پس از نصب

1. فایل‌های `seed_tips.php` و `copy_seed_media.php` را از سرور **حذف کنید**.
2. یک‌بار `/php-extended/opcache_clear.php?key=INSTALL_KEY` را باز کنید تا کش PHP خالی شود.
3. در پنل ادمین، تعداد قلق‌ها را کنترل کنید (باید ۲۰۹ قلق «منتشرشده» اضافه شده باشد).
