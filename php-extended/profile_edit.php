<?php
require_once __DIR__ . '/bk_extended.php';
$u=bk_login();$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if (function_exists('check_csrf')) check_csrf();
    $_POST['action']='profile_save';
    try { bk_extended_action('profile_save'); } catch(Throwable $e) { $error='ذخیره اطلاعات انجام نشد: '.$e->getMessage(); }
}
header_html('ویرایش اطلاعات تماس و آدرس');
?><main class="wrap page"><div class="page-title"><h1>اطلاعات تماس و آدرس ارسال</h1><p>این اطلاعات در خرید برد و سفارش‌ها برای فروشنده و مدیر نمایش داده می‌شود.</p></div><div class="card auth-card"><form method="post"><input type="hidden" name="csrf" value="<?=function_exists('csrf')?csrf():''?>"><input type="hidden" name="action" value="profile_save"><label class="field-label">نام و نام خانوادگی</label><input class="field" name="name" value="<?=h($u['name']??'')?>" required><div class="grid grid-2"><div><label class="field-label">موبایل</label><input class="field" name="mobile" dir="ltr" value="<?=h($u['mobile']??$u['phone']??'')?>" placeholder="09121234567"></div><div><label class="field-label">تلفن ثابت</label><input class="field" name="landline" dir="ltr" value="<?=h($u['landline']??'')?>" placeholder="02112345678"></div></div><label class="field-label">شهر</label><input class="field" name="city" value="<?=h($u['city']??'')?>"><label class="field-label">آدرس کامل</label><textarea class="field" name="address" rows="5" required><?=h($u['address']??'')?></textarea><label class="field-label">کدپستی ۱۰ رقمی</label><input class="field" name="postal_code" dir="ltr" pattern="[0-9]{10}" value="<?=h($u['postal_code']??'')?>" required><button class="btn btn-primary btn-full mt">ذخیره اطلاعات</button></form></div></main><?php footer_html();
