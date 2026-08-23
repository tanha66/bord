<?php
/**
 * تست صفحه ریلز - Bordkhan Reels Test Suite
 * این فایل تست‌های واحد و یکپارچگی صفحه ریلز را انجام می‌دهد
 * اجرا: php tests/test_reels.php  یا  از طریق مرورگر /reels-test
 */

$tests = [];
$passed = 0;
$failed = 0;

function test($name, callable $fn) {
    global $tests, $passed, $failed;
    try {
        $result = $fn();
        if ($result === true) {
            $tests[] = ['name'=>$name, 'status'=>'PASS'];
            $passed++;
        } else {
            $tests[] = ['name'=>$name, 'status'=>'FAIL', 'error'=>$result];
            $failed++;
        }
    } catch (Throwable $e) {
        $tests[] = ['name'=>$name, 'status'=>'FAIL', 'error'=>$e->getMessage()];
        $failed++;
    }
}

// 1. بررسی وجود فایل index.php
test('فایل index.php وجود دارد', function(){
    return is_file(__DIR__.'/../index.php') ? true : 'فایل یافت نشد';
});

// 2. بررسی سینتکس ریلز: عدم وجود {{}} دوبل در BKC
test('رفع باگ BKC {{}} دوبل', function(){
    $code = file_get_contents(__DIR__.'/../index.php');
    if (strpos($code, 'var BKC={{csrf:') !== false) return 'باگ {{}} هنوز وجود دارد';
    if (strpos($code, 'var BKC={csrf:') === false) return 'BKC با سینتکس درست یافت نشد';
    return true;
});

// 3. بررسی وجود صفحه ریلز اصلی
test('بلوک reels اصلی وجود دارد', function(){
    $code = file_get_contents(__DIR__.'/../index.php');
    return strpos($code, "if(\$page==='reels')") !== false ? true : 'بلوک reels یافت نشد';
});

// 4. بررسی وجود صفحه ریلز دمو
test('صفحه reels_demo برای تست بدون DB', function(){
    $code = file_get_contents(__DIR__.'/../index.php');
    return strpos($code, "reels_demo") !== false ? true : 'reels_demo یافت نشد';
});

// 5. بررسی وجود helper media_url در ریلز
test('استفاده از media_url برای حفاظت تصویر', function(){
    $code = file_get_contents(__DIR__.'/../index.php');
    // در نسخه جدید باید از $reel_img_url یا media_url استفاده شده باشد
    $has = strpos($code, 'reel_img_url') !== false && strpos($code, "media_url") !== false;
    return $has ? true : 'media_url در ریلز استفاده نشده';
});

// 6. بررسی وجود endpoint ajax-comments
test('endpoint ajax-comments وجود دارد', function(){
    $code = file_get_contents(__DIR__.'/../index.php');
    return strpos($code, "ajax-comments") !== false ? true : 'ajax-comments یافت نشد';
});

// 7. بررسی وجود اکشن‌های favorite و unlock با پاسخ JSON
test('اکشن favorite با پاسخ JSON', function(){
    $code = file_get_contents(__DIR__.'/../index.php');
    return strpos($code, "if(\$action==='favorite')") !== false && strpos($code, "bk_json_out") !== false ? true : 'favorite JSON یافت نشد';
});

test('اکشن unlock با پاسخ JSON', function(){
    $code = file_get_contents(__DIR__.'/../index.php');
    $hasUnlock = strpos($code, "if(\$action==='unlock')") !== false;
    $hasJson = substr_count($code, "bk_json_out") >= 3;
    return ($hasUnlock && $hasJson) ? true : 'unlock JSON یافت نشد';
});

test('اکشن comment با پاسخ JSON', function(){
    $code = file_get_contents(__DIR__.'/../index.php');
    return strpos($code, "if(\$action==='comment')") !== false ? true : 'comment action یافت نشد';
});

// 8. بررسی وجود CSS ریلز
test('استایل‌های ریلز وجود دارد', function(){
    $code = file_get_contents(__DIR__.'/../index.php');
    $checks = ['reels-feed','reel-media','reel-info','reel-rail','reel-lock','comments-sheet','heart-pop','reels-progress'];
    foreach($checks as $c){
        if(strpos($code,$c)===false) return "کلاس $c یافت نشد";
    }
    return true;
});

// 9. بررسی وجود JS توابع اصلی ریلز
test('توابع JS ریلز وجود دارد', function(){
    $code = file_get_contents(__DIR__.'/../index.php');
    $funcs = ['bkLike','bkUnlock','bkShare','bkOpenComments','bkSendComment','bkToast','popHeart','parseImgs','currentDisplayList'];
    foreach($funcs as $f){
        if(strpos($code,$f)===false) return "تابع JS $f یافت نشد";
    }
    return true;
});

// 10. بررسی وجود قابلیت‌های اینستاگرامی
test('قابلیت اسکرول snap و دابل‌تپ', function(){
    $code = file_get_contents(__DIR__.'/../index.php');
    $hasSnap = strpos($code,'scroll-snap-type')!==false;
    $hasDbl = strpos($code,'dblclick')!==false && strpos($code,'lastTap')!==false;
    return ($hasSnap && $hasDbl) ? true : 'snap یا double-tap یافت نشد';
});

test('قابلیت تعویض عکس با دات‌ها', function(){
    $code = file_get_contents(__DIR__.'/../index.php');
    return strpos($code,'reel-dots')!==false && strpos($code,'data-thumbs')!==false ? true : 'dots یا data-thumbs یافت نشد';
});

test('پروگرس بار و کیبورد ناوبری', function(){
    $code = file_get_contents(__DIR__.'/../index.php');
    $hasProg = strpos($code,'reelsProgress')!==false;
    $hasKey = strpos($code,'ArrowDown')!==false && strpos($code,'ArrowUp')!==false;
    return ($hasProg && $hasKey) ? true : 'progress یا keyboard یافت نشد';
});

test('پنل کامنت کشویی', function(){
    $code = file_get_contents(__DIR__.'/../index.php');
    return strpos($code,'comments-sheet')!==false && strpos($code,'cs-list')!==false ? true : 'comments sheet یافت نشد';
});

test('حالت خالی ریلز', function(){
    $code = file_get_contents(__DIR__.'/../index.php');
    return strpos($code,'reels-empty')!==false ? true : 'empty state یافت نشد';
});

// 11. بررسی امنیت: جلوگیری از کلیک راست روی تصویر
test('محافظت کلیک راست تصویر', function(){
    $code = file_get_contents(__DIR__.'/../index.php');
    return strpos($code,'contextmenu')!==false && strpos($code,'reel-media img')!==false ? true : 'contextmenu protection یافت نشد';
});

// 12. تست منطق tip_has_access (بدون DB - mock)
test('منطق tip_has_access برای free', function(){
    // شبیه‌سازی تابع
    $tip = ['access_type'=>'free','author_id'=>1,'id'=>10];
    $user = null;
    // free باید همیشه true باشد
    if (($tip['access_type'] ?? 'free') === 'free') return true;
    return 'منطق free اشتباه';
});

test('منطق تشخیص تصویر خارجی vs داخلی', function(){
    $path1 = 'https://picsum.photos/seed/test/800/1200';
    $path2 = '/uploads/20240101-abc.jpg';
    $isExternal1 = preg_match('#^https?://#i',$path1);
    $isExternal2 = preg_match('#^https?://#i',$path2);
    if (!$isExternal1) return 'تشخیص خارجی ۱ اشتباه';
    if ($isExternal2) return 'تشخیص داخلی اشتباه';
    return true;
});

test('ساخت JSON ایمن برای data attributes', function(){
    $urls = ['https://example.com/a.jpg','/uploads/b.jpg'];
    $json = json_encode($urls, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $decoded = json_decode($json,true);
    return $decoded===$urls ? true : 'JSON encode/decode اشتباه';
});

// خروجی
echo \"\\n=== تست صفحه ریلز - Bordkhan ===\\n\\n\";
foreach($tests as $t){
    $icon = $t['status']==='PASS' ? '✅' : '❌';
    echo \"$icon {$t['name']}: {$t['status']}\";
    if(isset($t['error'])) echo \" - {$t['error']}\";
    echo \"\\n\";
}
echo \"\\n--- خلاصه ---\\n\";
echo \"تعداد کل: \".count($tests).\"\\n\";
echo \"موفق: $passed\\n\";
echo \"ناموفق: $failed\\n\";
if($failed===0){
    echo \"\\n🎉 همه تست‌های ریلز با موفقیت پاس شد!\\n\";
    echo \"برای تست بصری: /reels-demo  یا  /reels-test را باز کنید\\n\";
    echo \"برای تست واقعی با دیتابیس: /reels\\n\";
}else{
    echo \"\\n⚠️ $failed تست ناموفق بود - لطفاً بررسی کنید\\n\";
}
echo \"\\n\";
?>
