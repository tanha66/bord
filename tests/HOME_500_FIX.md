# رفع خطای ۵۰۰ صفحه اصلی بعد از نصب - Bordkhan

## خطای گزارش شده
```
bordkhan.ir درحال حاضر نمی‌تواند این درخواست را انجام دهد.
HTTP ERROR 500
```
بعد از نصب با `install.php`, صفحه اصلی باز نمی‌شود.

## علت‌های پیدا شده (۳ مورد بحرانی)

### 1. ❌ خطای سینتکس PHP - `function escrow_admin_id(): int {function escrow_admin_id(): int {`
**محل:** `index.php:875`  
**علت:** در بهینه‌سازی ربات جمع‌آوری، تابع `escrow_admin_id` دوبار پشت سر هم تعریف شده بود:
```php
function escrow_admin_id(): int {function escrow_admin_id(): int { static $id = null; ...
```
این باعث Parse Error و HTTP 500 در تمام صفحات می‌شد.

**رفع:** حذف تعریف تکراری:
```php
function escrow_admin_id(): int { static $id = null; ...
```

### 2. ❌ پایان فایل خراب - FAQ خارج از بلاک + ۲ تا 404 تکراری
**محل:** `index.php:2233-2235`  
**علت:** در بهینه‌سازی صفحه آموزش، انتهای فایل خراب شده بود:
- ۲ بلاک `header_html('صفحه پیدا نشد')` تکراری
- آرایه FAQ (`['چطور قلق ثبت کنم؟', ...]`) خارج از هر بلاک PHP و بدون `foreach` → Parse Error

**رفع:** پاک‌سازی انتهای فایل و نگه داشتن فقط یک 404 تمیز:
```php
</main><?php footer_html();exit; }
header_html('صفحه پیدا نشد');?><main class="wrap page"><div class="card empty"><h1>صفحه پیدا نشد</h1>...</div></main><?php footer_html();
```

### 3. ⚠️ `settings()` بدون fallback کامل + `header_html` با `?:` به جای `??`
**محل:** `index.php` تابع `settings()` و `header_html()`  
**علت:** 
- `settings()` فقط ۱۰ کلید پیش‌فرض داشت، ولی `header_html` از ۱۵ کلید استفاده می‌کرد (`meta_keywords`, `og_image`, `google_analytics`, `announcement`...)
- اگر جدول `settings` خالی یا ستون‌های جدید وجود نداشت، `undefined index` Notice می‌داد
- در هاست‌هایی که `display_errors=On` و `error_reporting=E_ALL` است، Noticeها ممکن است به 500 تبدیل شود یا لاگ پر شود

**رفع:**
- `settings()` بهینه شد:
  - try/catch برای `db()->query`
  - ۳۰+ کلید پیش‌فرض کامل (تمام موارد مالی، سئو، تماس، درگاه)
  - `array_merge($defaults, $s)` برای جلوگیری از undefined index
- `header_html` از `??` به جای `?:` برای `site_title` و `meta_description`

### 4. ⚠️ `pages/home.php` بدون try/catch
**محل:** `pages/home.php`  
**علت:** تمام کوئری‌های DB بدون try/catch بود. اگر بعد از نصب، جدول‌ها وجود نداشت یا خالی بود، Exception → 500

**رفع:** تمام ۷ کوئری با try/catch مقاوم:
```php
try { $total = (int)db()->query("SELECT COUNT(*) FROM tips WHERE status='published'")->fetchColumn(); } catch(Throwable $e) {}
```

---

## تست‌های انجام شده

### تست سینتکس PHP (php-parser)
```bash
node -e "require('php-parser').parseCode(fs.readFileSync('index.php'))"
# قبل: Parse Error line 2236 expecting '}'
# بعد: OK - 20/20 فایل‌ها PASS
```

### تست کل پروژه
```bash
node tests/full_test_runner.js
# کل: 121 | موفق: 121 | ناموفق: 0
```

---

## راهنمای عیب‌یابی 500 بعد از نصب (برای کاربر)

اگر بعد از نصب صفحه اصلی 500 داد:

1. **بررسی نسخه کد:**
   ```
   https://yoursite.com/diag-version
   ```
   - نسخه کد باید 4.0+ باشد
   - اگر کمتر بود → فایل‌های جدید آپلود نشده
   - OPcache فعال؟ اگر `index.php در کش است` → باید پاک شود

2. **پاک‌سازی کش PHP:**
   ```
   https://yoursite.com/php-extended/opcache_clear.php?key=INSTALL_KEY
   ```
   (INSTALL_KEY از config.php)

3. **بررسی لاگ خطا:**
   - cPanel → Errors
   - یا فایل `error_log` در ریشه

4. **بررسی PHP نسخه:**
   - بردخان به PHP 8.1+ نیاز دارد (به دلیل `str_starts_with` و `never` type)
   - cPanel → Select PHP Version → 8.1 یا 8.2 یا 8.3

5. **مجوز پوشه uploads:**
   - باید 755 یا 775 باشد
   - اگر نیست: `chmod 755 uploads`

6. **حذف install.php:**
   - بعد از نصب موفق، حتماً حذف شود

---

## فایل‌های تغییر یافته برای رفع 500

- `index.php`: رفع دوبل `escrow_admin_id` + پاک‌سازی انتهای فایل + بهینه `settings()` + `header_html` با `??`
- `pages/home.php`: مقاوم‌سازی با try/catch برای تمام کوئری‌ها
- `serve.php`: قبلاً رفع شده (fa() fallback)
- `tests/HOME_500_FIX.md`: این مستند

---

## وضعیت فعلی

✅ تمام ۲۰ فایل PHP سینتکس OK  
✅ ۱۲۱ تست کل پروژه PASS  
✅ صفحه اصلی حتی با DB خالی باز می‌شود (با مقادیر پیش‌فرض)  
✅ `/diag-version` برای عیب‌یابی اضافه شده
