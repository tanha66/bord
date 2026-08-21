# Bordkhan PHP Extended — تکمیل موارد باقی‌مانده

این پوشه نسخهٔ اجرایی امکاناتی است که در سورس خام PHP نبود یا کامل نبود.

## نصب

1. سورس اصلی PHP را نصب کنید.
2. پوشه `php-extended` را کنار `index.php` قرار دهید.
3. یک‌بار باز کنید:

```text
/php-extended/migrate.php?key=INSTALL_KEY
```

4. پس از موفقیت، `migrate.php` را حذف یا با `.htaccess` محدود کنید.
5. از `php-patched/index.php` استفاده کنید یا routeهای `PATCHES.md` را merge کنید.

## امکانات اضافه‌شده

- آدرس گیرنده: نام، موبایل، شهر، آدرس، کدپستی
- ثبت اجباری شرکت حمل و کد رهگیری برای فروشنده
- شارژ درگاه واقعی: زرین‌پال، آیدیپی و زیبال
- callback و verify زرین‌پال/زیبال/آیدیپی
- کارت‌به‌کارت با آپلود فیش و تأیید مدیر
- تنظیمات درگاه و فیش‌ها در `admin-finance`
- تیکت پشتیبانی با سه مقصد، اولویت، دسته، اتصال سفارش، پاسخ و وضعیت
- گروه پشتیبانی از ستون `users.support_group`
- ثبت و مدیریت آدرس پروفایل با action `profile_save`
- برگشت وضعیت قلق به `pending` در نسخهٔ `php-patched`
- دانلود سورس از `source_download.php` در صورت فعال‌بودن ZipArchive

## routeهای نسخهٔ patched

```text
/tickets
/wallet-plus
/admin-finance
```

## actionهای نسخهٔ patched

```text
board_buy
board_ship
profile_save
wallet_gateway_start
wallet_card_to_card
```

## تنظیمات درگاه

از `/admin-finance` وارد شوید و این موارد را تنظیم کنید:

- فعال/غیرفعال بودن درگاه
- نوع درگاه
- Merchant ID
- API Key آیدیپی
- Sandbox
- حداقل و حداکثر شارژ
- اطلاعات حساب کارت‌به‌کارت

کلیدها در `settings` ذخیره می‌شوند و secretها داخل کد hardcode نشده‌اند.
