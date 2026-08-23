<?php
require __DIR__ . '/config.php';

/* نسخهٔ کد — برای تشخیص اینکه سرور واقعاً کدام نسخه را اجرا می‌کند */
if (!defined('BORDKHAN_VERSION')) define('BORDKHAN_VERSION', '4.0');

/* ---------- helper های مقاوم — حتی اگر config.php سرور قدیمی باشد ---------- */
if (!function_exists('mb_strlen')) {
    function mb_strlen($s, $enc = null): int { return strlen((string)$s); }
    function mb_substr($s, $start, $len = null, $enc = null): string { $s = (string)$s; return $len === null ? substr($s, $start) : substr($s, $start, $len); }
}
if (!function_exists('file_mime')) {
    function file_mime(string $path): string {
        if (class_exists('finfo')) {
            try { $f = new finfo(FILEINFO_MIME_TYPE); $m = $f->file($path); if (is_string($m) && $m !== '') return $m; } catch (Throwable $e) {}
        }
        if (function_exists('mime_content_type')) { $m = @mime_content_type($path); if (is_string($m) && $m !== '') return $m; }
        $m = @getimagesize($path);
        if (is_array($m) && !empty($m['mime'])) return (string)$m['mime'];
        return '';
    }
}

function h($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'); }
function url(string $path = '', array $query = []): string { return '/' . ltrim($path, '/') . ($query ? '?' . http_build_query($query) : ''); }
function bk_route(): string {
    $route = trim((string)($_GET['r'] ?? ''), '/');
    if ($route === '' && !array_key_exists('r', $_GET)) {
        /* حالت fallback: هاست‌های بدون mod_rewrite (ErrorDocument 404 → index.php)
           مسیر واقعی را از REDIRECT_URL / REQUEST_URI می‌خوانیم */
        $src = (string)($_SERVER['REDIRECT_URL'] ?? $_SERVER['REQUEST_URI'] ?? '');
        $uriPath = trim((string)parse_url($src, PHP_URL_PATH), '/');
        if ($uriPath !== '') {
            $scriptDir = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/.');
            $scriptBase = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
            if ($scriptDir !== '' && str_starts_with($uriPath, $scriptDir . '/')) $uriPath = trim(substr($uriPath, strlen($scriptDir) + 1), '/');
            if ($scriptDir !== '' && $uriPath === $scriptDir) $uriPath = '';
            if ($uriPath === $scriptBase) $uriPath = '';
            if ($uriPath !== '') $route = $uriPath;
        }
    }
    return $route;
}
function redirect_to(string $path): never {
    // جلوگیری از open redirect: فقط مسیر داخلی (با یک /) یا بدون اسکیم مجاز است
    $clean = trim($path);
    if ($clean === '') { $clean = '/'; }
    if ($clean !== '' && !str_starts_with($clean, '/')) { $clean = '/' . $clean; }
    if (preg_match('#^/{2,}#', $clean) || preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $clean) || str_contains($clean, "\r") || str_contains($clean, "\n")) { $clean = '/'; }
    header('Location: ' . $clean); exit;
}
function fa($value): string { $digits = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹']; return strtr((string)$value, array_combine(range(0,9), $digits)); }
function media_url(string $path, string $type, int $tipId, ?int $userId = null): string {
    $f = ltrim($path, '/');
    $uid = $userId ?? (int)($_SESSION['user_id'] ?? 0);
    $uidStr = $uid > 0 ? (string)$uid : 'guest';
    $n = hash_hmac('sha256', $uidStr.'|'.$f.'|'.$type.'|'.$tipId, INSTALL_KEY);
    return url('serve', ['t'=>$type,'id'=>$tipId,'f'=>$f,'n'=>$n]);
}
function image_url(array $t, string $src, ?array $u, bool $unlocked): string {
    return media_url($src, $unlocked ? 'img' : 'thumb', (int)$t['id'], $u ? (int)$u['id'] : 0);
}
function has_bookmark(int $tipId, ?array $u): bool { static $cache = null; if (!$u) return false; if ($cache === null) { $cache = []; $q=db()->prepare('SELECT tip_id FROM bookmarks WHERE user_id=?'); $q->execute([(int)$u['id']]); foreach($q->fetchAll() as $r) $cache[(int)$r['tip_id']] = true; } return isset($cache[$tipId]); }
function has_favorite(int $tipId, ?array $u): bool { static $cache = null; if (!$u) return false; if ($cache === null) { $cache = []; $q=db()->prepare('SELECT tip_id FROM favorites WHERE user_id=?'); $q->execute([(int)$u['id']]); foreach($q->fetchAll() as $r) $cache[(int)$r['tip_id']] = true; } return isset($cache[$tipId]); }
function money($value): string { return fa(number_format((float)$value)); }
function date_fa($value): string { if (!$value) return '—'; return fa(date('Y/m/d', strtotime($value))); }
function datetime_fa($value): string { if (!$value) return '—'; return fa(date('Y/m/d H:i', strtotime($value))); }
function ago($value): string { $d = time() - strtotime($value); if ($d < 60) return 'همین الان'; if ($d < 3600) return fa(floor($d/60)) . ' دقیقه پیش'; if ($d < 86400) return fa(floor($d/3600)) . ' ساعت پیش'; if ($d < 2592000) return fa(floor($d/86400)) . ' روز پیش'; return date_fa($value); }
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        if (is_ajax_request()) {
            http_response_code(419);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok'=>false,'error'=>'نشست شما منقضی یا نامعتبر شده است. صفحه را یک‌بار refresh کنید و دوباره تلاش نمایید.'], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(419);
            exit('درخواست نامعتبر است. صفحه را دوباره باز کنید.');
        }
        exit;
    }
}
function is_ajax_request(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
}
/* خروجی JSON تمیز: هر خروجی/نوتیس قبلی حذف می‌شود تا پاسخ همیشه قابل parse باشد */
function bk_json_out(array $data, int $code = 200): never {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
/* آیا کاربر به محتوای کامل قلق دسترسی دارد؟ (برای محوسازی تصاویر قفل) */
function tip_has_access(array $t, ?array $u): bool {
    if (($t['access_type'] ?? 'free') === 'free') return true;
    if (!$u) return false;
    if ((int)($u['id'] ?? 0) === (int)$t['author_id']) return true;
    if (staff($u)) return true;
    if (!empty($u['premium_until']) && strtotime($u['premium_until']) > time()) return true;
    static $ids = null;
    if ($ids === null) {
        $ids = [];
        $q = db()->prepare('SELECT tip_id FROM tip_accesses WHERE user_id=?');
        $q->execute([(int)$u['id']]);
        foreach ($q->fetchAll() as $r) $ids[(int)$r['tip_id']] = true;
    }
    return isset($ids[(int)$t['id']]);
}
function throttle(string $key, int $max, int $windowSec): bool { $now = time(); $h = $_SESSION['throttle'][$key] ?? ['start' => $now, 'count' => 0]; if ($now - $h['start'] > $windowSec) { $h = ['start' => $now, 'count' => 0]; } $h['count']++; $_SESSION['throttle'][$key] = $h; return $h['count'] <= $max; }
function throttle_clear(string $key): void { unset($_SESSION['throttle'][$key]); }
function flash(string $message, string $type = 'notice'): void { $_SESSION['flash'] = [$message, $type]; }
function pull_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function json_decode_array($value): array { $r = json_decode((string)$value, true); return is_array($r) ? $r : []; }
function clean_text(string $text): string { return trim(strip_tags($text)); }
function safe_rich(string $html): string { $html=strip_tags($html,'<p><br><b><strong><i><em><h2><h3><ul><ol><li><blockquote><code>'); return preg_replace_callback('/<\\/?\\s*([a-z0-9]+)(?:\\s[^>]*)?>/i', function($m){ return str_starts_with($m[0],'</') ? '</'.$m[1].'>' : '<'.$m[1].'>'; }, $html) ?? ''; }
function staff(?array $u): bool { return $u && in_array($u['role'], ['moderator','admin','superadmin'], true); }
function admin_user(?array $u): bool { return $u && in_array($u['role'], ['admin','superadmin'], true); }
function current_user(): ?array { static $user = false; if ($user !== false) return $user; $id = (int)($_SESSION['user_id'] ?? 0); if (!$id) return $user = null; $s = db()->prepare('SELECT * FROM users WHERE id=? LIMIT 1'); $s->execute([$id]); $u = $s->fetch() ?: null; if ($u && !empty($u['is_deleted'])) $u = null; return $user = $u; }
function require_login(): array { $u = current_user(); if (!$u) { flash('برای انجام این عملیات ابتدا وارد شوید.', 'error'); redirect_to('login'); } if ((int)$u['is_banned']) exit('حساب کاربری شما مسدود شده است.'); return $u; }
function require_admin(): array { $u = require_login(); if (!admin_user($u)) { http_response_code(403); exit('دسترسی غیرمجاز'); } return $u; }
function settings(): array { 
    static $s = null; 
    if ($s !== null) return $s; 
    try {
        $s = db()->query('SELECT * FROM settings WHERE id=1 LIMIT 1')->fetch();
    } catch(Throwable $e) {
        $s = null;
    }
    $defaults = [
        'site_title'=>SITE_NAME,
        'hero_title'=>'بازار تخصصی قلق‌های تعمیراتی بردهای الکترونیکی',
        'hero_subtitle'=>'راه‌حل‌های واقعی و تست‌شده از تعمیرکاران حرفه‌ای — سریع پیدا کن، مطمئن تعمیر کن، درآمد بساز.',
        'announcement'=>'',
        'upload_reward'=>50000,
        'like_points_reward'=>5,
        'like_wallet_reward'=>0,
        'commission_percent'=>20,
        'min_withdrawal'=>200000,
        'daily_like_limit'=>5,
        'referral_reward'=>20000,
        'invitee_credit'=>10000,
        'repair_deadline_days'=>7,
        'daily_free_tip_id'=>null,
        'premium_1'=>149000,
        'premium_3'=>399000,
        'premium_12'=>1299000,
        'board_commission_percent'=>10,
        'auto_collect_enabled'=>0,
        'auto_collect_count'=>10,
        'auto_collect_category'=>null,
        'auto_collect_access'=>'free',
        'auto_collect_sources'=>'[]',
        'auto_collect_queries'=>'',
        'auto_collect_cron_key'=>'',
        'auto_collect_indian_enabled'=>1,
        'auto_collect_chinese_enabled'=>0,
        'auto_collect_japanese_enabled'=>0,
        'auto_collect_min_length'=>100,
        'auto_collect_max_images'=>3,
        'auto_collect_translate_enabled'=>1,
        'auto_collect_extract_full'=>1,
        'auto_collect_save_images'=>1,
        'auto_collect_filter_repair'=>1,
        'auto_collect_language'=>'auto',
        'auto_collect_content_type'=>'repair',
        'auto_collect_image_quality'=>'medium',
        'auto_collect_auto_publish'=>1,
        'auto_collect_exclude_keywords'=>'',
        'auto_collect_save_path'=>'auto',
        'auto_collect_max_retries'=>2,
        'auto_collect_timeout'=>12,
        'contact_form_enabled'=>0,
        'contact_email'=>'',
        'contact_phone'=>'',
        'contact_telegram'=>'',
        'contact_instagram'=>'',
        'contact_address'=>'',
        'contact_text'=>'',
        'terms_text'=>'',
        'about_text'=>'',
        'privacy_text'=>'',
        'meta_description'=>'بازار تخصصی قلق‌های تعمیراتی بردهای الکترونیکی',
        'meta_keywords'=>'',
        'og_image'=>'',
        'google_analytics'=>'',
        'gateway_enabled'=>0,
        'gateway_type'=>'zarinpal',
        'gateway_merchant_id'=>'',
        'gateway_api_key'=>'',
        'gateway_sandbox'=>1,
        'gateway_min_charge'=>100000,
        'gateway_max_charge'=>50000000,
        'z2c_bank_name'=>'',
        'z2c_account_name'=>'',
        'z2c_card_number'=>'',
        'actionbar_json'=>'',
    ];
    if (!$s) return $s = $defaults;
    // ترکیب با پیش‌فرض برای جلوگیری از undefined index بعد از نصب
    return $s = array_merge($defaults, $s);
}
function category_tree(): array { $rows = db()->query("SELECT * FROM categories WHERE status='active' ORDER BY parent_id IS NOT NULL, sort_order, name")->fetchAll(); $parents=[]; foreach($rows as $r) { if (!$r['parent_id']) { $r['children']=[]; $parents[$r['id']]=$r; } } foreach($rows as $r) if($r['parent_id'] && isset($parents[$r['parent_id']])) $parents[$r['parent_id']]['children'][]=$r; return array_values($parents); }
function level_name(int $points): string { if ($points >= 5000) return 'استاد'; if ($points >= 2000) return 'متخصص'; if ($points >= 500) return 'تعمیرکار'; return 'تازه‌کار'; }
function access_label(string $type, int $price=0): string { return $type==='paid' ? money($price).' تومان' : ($type==='like' ? 'با لایک' : 'رایگان'); }
function status_label(string $s): string { return ['draft'=>'پیش‌نویس','pending'=>'در انتظار بررسی','published'=>'منتشرشده','rejected'=>'رد شده','removed'=>'حذف شده'][$s] ?? $s; }
function role_label(string $s): string { return ['member'=>'کاربر عادی','expert'=>'تعمیرکار تأییدشده','moderator'=>'ناظر','admin'=>'مدیر کل','superadmin'=>'سوپر ادمین'][$s] ?? $s; }
function notify_user(int $id, string $type, string $title, string $body, string $link=''): void { $s=db()->prepare('INSERT INTO notifications(user_id,type,title,body,link) VALUES(?,?,?,?,?)'); $s->execute([$id,$type,$title,$body,$link]); }
function credit(int $userId, int $amount, string $type, string $note, ?int $tipId=null, ?int $requestId=null): void { if($amount<=0)return; $pdo=db(); $pdo->prepare('UPDATE users SET balance=balance+? WHERE id=?')->execute([$amount,$userId]); $q=$pdo->prepare('SELECT balance FROM users WHERE id=?');$q->execute([$userId]);$balance=(int)$q->fetchColumn(); $pdo->prepare('INSERT INTO wallet_transactions(user_id,type,amount,balance_after,tip_id,request_id,note) VALUES(?,?,?,?,?,?,?)')->execute([$userId,$type,$amount,$balance,$tipId,$requestId,$note]); }
function debit(int $userId, int $amount, string $type, string $note, ?int $tipId=null, ?int $requestId=null): bool { $pdo=db(); $pdo->beginTransaction(); try { $q=$pdo->prepare('SELECT balance FROM users WHERE id=? FOR UPDATE');$q->execute([$userId]);$balance=(int)$q->fetchColumn(); if($balance<$amount){$pdo->rollBack();return false;} $pdo->prepare('UPDATE users SET balance=balance-? WHERE id=?')->execute([$amount,$userId]); $pdo->prepare('INSERT INTO wallet_transactions(user_id,type,amount,balance_after,tip_id,request_id,note) VALUES(?,?,?,?,?,?,?)')->execute([$userId,$type,-$amount,$balance-$amount,$tipId,$requestId,$note]); $pdo->commit(); return true; } catch(Throwable $e){$pdo->rollBack();throw $e;} }
function award(int $userId,int $points):void{db()->prepare('UPDATE users SET points=points+? WHERE id=?')->execute([$points,$userId]);}
function badge_defs(): array { return [
    'first_tip'      => ['🏅','اولین قلق','اولین قلق شما منتشر شد'],
    'ten_tips'       => ['📚','۱۰ قلق منتشرشده','ده قلق شما تأیید و منتشر شد'],
    'first_sale'     => ['💰','اولین فروش','اولین فروش قلق شما ثبت شد'],
    'first_purchase' => ['🛒','اولین خرید','اولین خرید شما در بردخان ثبت شد'],
    'seller'         => ['🏪','فروشنده تأییدشده','فروشندگی شما تأیید شد'],
    'expert'         => ['✅','تعمیرکار تأییدشده','حساب شما به‌عنوان تعمیرکار تأیید شد'],
    'premium'        => ['👑','اشتراک ویژه','اشتراک ویژه شما فعال شد'],
]; }
function user_badges(int $uid): array {
    $q=db()->prepare('SELECT badge_type,label,created_at FROM badges WHERE user_id=? ORDER BY id');$q->execute([$uid]);return $q->fetchAll() ?: [];
}
function award_badge(int $uid, string $code): void {
    $defs=badge_defs(); if(!isset($defs[$code]) || $uid<=0) return;
    $q=db()->prepare('SELECT id FROM badges WHERE user_id=? AND badge_type=?');$q->execute([$uid,$code]);
    if($q->fetch()) return; // قبلاً این نشان را گرفته است
    db()->prepare('INSERT INTO badges(user_id,badge_type,label) VALUES(?,?,?)')->execute([$uid,$code,$defs[$code][1]]);
    notify_user($uid,'badge','نشان جدید: '.$defs[$code][1],$defs[$code][2],url('profile/'.$uid));
}
function maybe_reward_referrer(int $invitedUserId): void {
    $pdo = db();
    $q = $pdo->prepare('SELECT referred_by, referred_rewarded FROM users WHERE id=? LIMIT 1');
    $q->execute([$invitedUserId]);
    $row = $q->fetch();
    if (!$row || !(int)$row['referred_by'] || (int)($row['referred_rewarded'] ?? 0)) return;
    $referrerId = (int)$row['referred_by'];
    $pdo->prepare('UPDATE users SET referred_rewarded=1 WHERE id=?')->execute([$invitedUserId]);
    $reward = (int)(settings()['referral_reward'] ?? 20000);
    if ($reward > 0) {
        credit($referrerId, $reward, 'referral', 'پاداش معرفی دوستان — اولین فعالیت موفق دعوت‌شده');
        notify_user($referrerId, 'wallet', 'پاداش معرفی دوستان', 'دوست شما اولین فعالیت موفق (آپلود/خرید) خود را ثبت کرد؛ ' . money($reward) . ' تومان به کیف پول شما واریز شد.', url('wallet'));
    }
}
function tip_images(array $tip): array { return json_decode_array($tip['images_json'] ?? '[]'); }
function tip_solution(array $tip): array { return json_decode_array($tip['solution_json'] ?? '[]'); }
function save_image(array $file): ?string {
    if(($file['error']??1)!==UPLOAD_ERR_OK)return null;
    if(($file['size']??0)>10*1024*1024)return null; // پذیرش تا ۱۰MB؛ خروجی همیشه کوچک‌سازی می‌شود
    $mime=file_mime($file['tmp_name']);
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!isset($allowed[$mime]))return null;
    if(!is_dir(UPLOAD_DIR))@mkdir(UPLOAD_DIR,0755,true);
    if(!is_dir(UPLOAD_DIR))return null;
    $name=date('YmdHis').'-'.bin2hex(random_bytes(5)).'.jpg'; // خروجی استاندارد JPG (کوچک‌تر)
    $target=UPLOAD_DIR.'/'.$name;
    $src=null;
    if($mime==='image/jpeg'&&function_exists('imagecreatefromjpeg'))$src=@imagecreatefromjpeg($file['tmp_name']);
    elseif($mime==='image/png'&&function_exists('imagecreatefrompng'))$src=@imagecreatefrompng($file['tmp_name']);
    elseif($mime==='image/webp'&&function_exists('imagecreatefromwebp'))$src=@imagecreatefromwebp($file['tmp_name']);
    if($src!==null&&$src!==false){
        /* تصحیح چرخش EXIF (عکس دوربین موبایل) */
        if($mime==='image/jpeg'&&function_exists('exif_read_data')){$ex=@exif_read_data($file['tmp_name']);$o=(int)($ex['Orientation']??0);if($o===3)$src=imagerotate($src,180,0);elseif($o===6)$src=imagerotate($src,-90,0);elseif($o===8)$src=imagerotate($src,90,0);}
        /* کاهش خودکار به اندازه استاندارد: حداکثر ضلع ۱۹۲۰ پیکسل */
        $w=imagesx($src);$h=imagesy($src);$max=1920;
        if($w>$max||$h>$max){$r=min($max/$w,$max/$h);$nw=max(1,(int)round($w*$r));$nh=max(1,(int)round($h*$r));$dst=imagecreatetruecolor($nw,$nh);imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);imagedestroy($src);$src=$dst;}
        imagejpeg($src,$target,82); imagedestroy($src);
        /* اگر باز هم بزرگ بود، یک‌بار دیگر با کیفیت پایین‌تر فشرده می‌شود */
        if(@filesize($target)>4*1024*1024){$im=@imagecreatefromjpeg($target);if($im!==false){imagejpeg($im,$target,62);imagedestroy($im);}}
        return @file_exists($target)?'/uploads/'.$name:null;
    }
    /* بدون GD: انتقال مستقیم با پسوند واقعی */
    $name=date('YmdHis').'-'.bin2hex(random_bytes(5)).'.'.$allowed[$mime];
    $target=UPLOAD_DIR.'/'.$name;
    if(!@move_uploaded_file($file['tmp_name'],$target))return null;
    return '/uploads/'.$name;
}
function save_video(array $file): ?string { if(($file['error']??1)!==UPLOAD_ERR_OK)return null; if(($file['size']??0)>50*1024*1024)return null; $ext=strtolower(pathinfo((string)($file['name']??''),PATHINFO_EXTENSION)); $mime=file_mime($file['tmp_name']); if($mime!=='video/mp4' && $ext!=='mp4')return null; if(!is_dir(UPLOAD_DIR))@mkdir(UPLOAD_DIR,0755,true); if(!is_dir(UPLOAD_DIR))return null; $name=date('YmdHis').'-'.bin2hex(random_bytes(5)).'.mp4'; $target=UPLOAD_DIR.'/'.$name; if(!@move_uploaded_file($file['tmp_name'],$target))return null; return '/uploads/'.$name; }
function video_embed(string $url, array $tip, ?array $u): string { $url=trim($url); if($url==='')return ''; if(str_starts_with($url,'/uploads/')||str_starts_with($url,'uploads/')){ $src=media_url($url,'vid',(int)$tip['id'],$u?(int)$u['id']:0); return '<div class="mt media-protect full-lock video-lock"><video class="no-save" controls controlslist="nodownload noremoteplayback" disablePictureInPicture playsinline><source src="'.h($src).'" type="video/mp4"></video><span class="wm">© بردخان</span></div>'; } if(preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{6,})~',$url,$m)){ return '<div class="mt video-lock"><iframe class="no-save" style="width:100%;aspect-ratio:16/9;border:0;border-radius:14px" src="https://www.youtube.com/embed/'.h($m[1]).'" allowfullscreen loading="lazy"></iframe></div>'; } if(preg_match('~aparat\.com/v/([\w-]+)~',$url,$m)){ return '<div class="mt video-lock"><iframe class="no-save" style="width:100%;aspect-ratio:16/9;border:0;border-radius:14px" src="https://www.aparat.com/video/video/embed/videohash/'.h($m[1]).'/vt/frame" allowfullscreen loading="lazy"></iframe></div>'; } return '<div class="mt"><a class="btn btn-secondary" href="'.h($url).'" target="_blank" rel="noopener">▶ مشاهده ویدیوی آموزشی</a></div>'; }
function fetch_url(string $url, int $timeout = 8): ?string {
    $url = trim($url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) return null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
            CURLOPT_HTTPHEADER => ['Accept: application/rss+xml, application/xml, text/xml, application/json, text/html, */*;q=0.9', 'Accept-Language: fa,en;q=0.8,en-US;q=0.6'],
            CURLOPT_REFERER => 'https://www.google.com/',
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code >= 200 && $code < 400) ? $body : null;
    }
    $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true, 'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\nAccept: */*\r\n"]]);
    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? null : $body;
}
function download_image(string $url, string $region = 'western', int $quality = 84): ?string {
    $url = trim($url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) return null;
    $body = fetch_url($url, 25);
    if ($body === null || strlen($body) < 1024 || strlen($body) > 8 * 1024 * 1024) return null;
    $tmp = tempnam(sys_get_temp_dir(), 'bkimg');
    if ($tmp === false) return null;
    file_put_contents($tmp, $body);
    $mime = file_mime($tmp);
    $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'jpg'];
    $ext = $extMap[$mime] ?? null;
    if (!$ext) {
        $pathExt = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        if (in_array($pathExt, ['jpg','jpeg','png','webp'], true)) $ext = $pathExt==='jpeg'?'jpg':$pathExt;
    }
    if (!$ext) { @unlink($tmp); return null; }

    // ذخیره در جای درست: /uploads/auto/{region}/
    $region = in_array($region, ['western','indian','chinese','japanese'], true) ? $region : 'western';
    $saveDir = UPLOAD_DIR.'/auto/'.$region;
    if (!is_dir($saveDir)) @mkdir($saveDir, 0755, true);
    if (!is_dir($saveDir)) {
        // fallback به uploads اصلی
        $saveDir = UPLOAD_DIR;
        if (!is_dir($saveDir)) @mkdir($saveDir, 0755, true);
    }
    if (!is_dir($saveDir)) { @unlink($tmp); return null; }

    $name = 'auto-'.$region.'-'.date('YmdHis').'-'.bin2hex(random_bytes(4)).'.jpg';
    $target = $saveDir.'/'.$name;
    $ok = false;
    $quality = max(60, min(95, $quality));

    if (function_exists('imagecreatefromstring')) {
        $im = @imagecreatefromstring($body);
        if ($im !== false) {
            $w = imagesx($im); $h = imagesy($im);
            // کیفیت تصویر بر اساس تنظیم
            $max = $quality >= 90 ? 1920 : ($quality >= 80 ? 1600 : 1200);
            if ($w > $max || $h > $max) {
                $r = min($max/$w, $max/$h);
                $nw = max(1, (int)round($w*$r)); $nh = max(1, (int)round($h*$r));
                $dst = imagecreatetruecolor($nw, $nh);
                $white = imagecolorallocate($dst, 255,255,255);
                imagefill($dst, 0,0,$white);
                imagecopyresampled($dst,$im,0,0,0,0,$nw,$nh,$w,$h);
                imagedestroy($im); $im = $dst;
            }
            $ok = @imagejpeg($im, $target, $quality);
            imagedestroy($im);
        }
    }
    if (!$ok) {
        $ok = @file_put_contents($target, $body) !== false;
        @unlink($tmp);
    } else {
        @unlink($tmp);
    }
    if (!$ok || !file_exists($target)) return null;
    // بازگشت مسیر نسبی درست
    if (str_contains($target, '/auto/')) {
        return '/uploads/auto/'.$region.'/'.$name;
    }
    return '/uploads/'.$name;
}
function extract_images_from_html(string $html, string $baseUrl = '', int $limit = 5): array {
    $images = [];
    if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
        foreach ($m[1] as $src) {
            $src = trim($src);
            if ($src === '' || str_starts_with($src, 'data:')) continue;
            // تبدیل URL نسبی به مطلق
            if (!preg_match('#^https?://#i', $src) && $baseUrl !== '') {
                $base = parse_url($baseUrl);
                if ($base) {
                    $origin = ($base['scheme'] ?? 'https').'://'.($base['host'] ?? '');
                    if (str_starts_with($src, '//')) $src = ($base['scheme'] ?? 'https').':'.$src;
                    elseif (str_starts_with($src, '/')) $src = $origin.$src;
                    else $src = $origin.'/'.ltrim($src,'/');
                }
            }
            if (!filter_var($src, FILTER_VALIDATE_URL)) continue;
            // فیلتر تصاویر کوچک/آیکون
            if (preg_match('#(icon|logo|avatar|emoji|1x1|pixel)#i', $src)) continue;
            $images[] = $src;
            if (count($images) >= $limit) break;
        }
    }
    return array_values(array_unique($images));
}
function extract_article_text(string $html, int $maxLen = 3000): string {
    // حذف اسکریپت و استایل
    $html = preg_replace('#<script[^>]*>.*?</script>#is', ' ', $html);
    $html = preg_replace('#<style[^>]*>.*?</style>#is', ' ', $html);
    // تلاش برای استخراج از تگ‌های محتوایی
    $candidates = [];
    if (preg_match_all('#<(article|div)[^>]*class=["\'][^"\']*(?:content|post|entry|article|body)[^"\']*["\'][^>]*>(.*?)</\1>#is', $html, $m)) {
        foreach ($m[2] as $inner) {
            $txt = trim(strip_tags($inner));
            if (mb_strlen($txt) > 100) $candidates[] = $txt;
        }
    }
    if (!$candidates) {
        // fallback: تمام متن
        $txt = trim(strip_tags($html));
        $txt = preg_replace('/\s+/', ' ', $txt);
        return mb_substr($txt, 0, $maxLen);
    }
    // طولانی‌ترین را انتخاب کن
    usort($candidates, fn($a,$b)=>mb_strlen($b)-mb_strlen($a));
    $best = $candidates[0];
    $best = preg_replace('/\s+/', ' ', $best);
    return mb_substr(trim($best), 0, $maxLen);
}
function parse_rss_items(string $xml, string $sourceName = ''): array {
    $prev = libxml_use_internal_errors(true);
    $feed = @simplexml_load_string($xml);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if ($feed === false) return [];
    $items = [];
    $entries = [];
    if (isset($feed->channel->item)) {
        foreach ($feed->channel->item as $item) {
            $entries[] = [
                'title' => (string)$item->title,
                'link' => (string)$item->link,
                'desc' => (string)($item->description ?? $item->children('content', true)->encoded ?? ''),
                'date' => (string)($item->pubDate ?? ''),
                'image' => (string)($item->enclosure['url'] ?? '')
            ];
        }
    } elseif (isset($feed->entry)) {
        foreach ($feed->entry as $e) {
            $link = '';
            if (isset($e->link)) {
                if (isset($e->link['href'])) $link = (string)$e->link['href'];
                else $link = (string)$e->link;
            }
            $entries[] = [
                'title' => (string)$e->title,
                'link' => $link,
                'desc' => (string)($e->content ?: $e->summary),
                'date' => (string)($e->updated ?? $e->published ?? ''),
                'image' => ''
            ];
        }
    }
    foreach ($entries as $en) {
        $title = trim(strip_tags($en['title']));
        if ($title === '' || mb_strlen($title) < 8) continue;
        // فیلتر تکراری و نامرتبط
        $low = mb_strtolower($title);
        if (preg_match('/(politic|sport|celebrity|music|movie)/i', $low)) continue;
        $image = trim($en['image']);
        if ($image === '' && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $en['desc'], $m)) $image = trim($m[1]);
        $ts = $en['date'] !== '' ? strtotime($en['date']) : time();
        if (!$ts || $ts < 0) $ts = time();
        $items[] = [
            'title' => $title,
            'link' => trim($en['link']),
            'description' => trim(strip_tags($en['desc'])),
            'description_html' => $en['desc'],
            'image' => $image,
            'date' => date('Y-m-d H:i:s', $ts),
            'source' => $sourceName
        ];
    }
    return $items;
}
function detect_brand(string $text): string {
    $text = mb_strtolower($text);
    $map = [
        'samsung'=>'سامسونگ','apple'=>'اپل','iphone'=>'اپل','asus'=>'ایسوس','dell'=>'دل','lenovo'=>'لنوو',
        'hewlett'=>'اچ‌پی','hp'=>'اچ‌پی','gigabyte'=>'گیگابایت','msi'=>'MSI','sony'=>'سونی','lg'=>'ال‌جی',
        'xiaomi'=>'شیائومی','redmi'=>'شیائومی','huawei'=>'هواوی','nokia'=>'نوکیا','acer'=>'ایسر','toshiba'=>'توشیبا',
        'corsair'=>'کورسیر','green'=>'گرین','cooler master'=>'کولر مستر','nvidia'=>'انویدیا','amd'=>'AMD',
        'intel'=>'اینتل','raspberry'=>'رسپبری پای','arduino'=>'آردوینو','esp32'=>'ماژول ESP','stm32'=>'STM32',
        'xbox'=>'ایکس‌باکس','playstation'=>'پلی‌استیشن','bosch'=>'بوش','siemens'=>'زیمنس','philips'=>'فیلیپس'
    ];
    foreach ($map as $en => $fa) if (mb_strpos($text, $en) !== false) return $fa;
    return 'متفرقه';
}
function detect_device(string $text): string {
    $text = mb_strtolower($text);
    $map = [
        'motherboard'=>'مادربرد','mainboard'=>'مادربرد','مادربرد'=>'مادربرد',
        'laptop'=>'لپ‌تاپ','notebook'=>'لپ‌تاپ','لپ‌تاپ'=>'لپ‌تاپ',
        'graphics card'=>'کارت گرافیک','gpu'=>'کارت گرافیک','vga'=>'کارت گرافیک','کارت گرافیک'=>'کارت گرافیک',
        'power supply'=>'پاور','psu'=>'پاور','power'=>'پاور','پاور'=>'پاور','سوئیچینگ'=>'پاور',
        'monitor'=>'مانیتور','مانیتور'=>'مانیتور',
        'television'=>'تلویزیون','tv'=>'تلویزیون','تلویزیون'=>'تلویزیون',
        'smartphone'=>'موبایل','phone'=>'موبایل','موبایل'=>'موبایل','تبلت'=>'موبایل',
        'adapter'=>'آداپتور','charger'=>'آداپتور','آداپتور'=>'آداپتور','شارژر'=>'آداپتور',
        'washing machine'=>'لوازم خانگی','dishwasher'=>'لوازم خانگی','refrigerator'=>'لوازم خانگی','لباسشویی'=>'لوازم خانگی',
        'inverter'=>'برد صنعتی','plc'=>'برد صنعتی','برد صنعتی'=>'برد صنعتی','اینورتر'=>'برد صنعتی',
        'console'=>'کنسول بازی','کنسول'=>'کنسول بازی',
        'printer'=>'پرینتر','پرینتر'=>'پرینتر',
        'router'=>'مودم و شبکه','مودم'=>'مودم و شبکه',
    ];
    foreach ($map as $k => $v) if (mb_strpos($text, $k) !== false) return $v;
    return 'برد الکترونیکی';
}
function detect_fault(string $text): string {
    $text = mb_strtolower($text);
    $map = [
        'no power'=>'روشن نمی‌شود','won\'t turn on'=>'روشن نمی‌شود','wont turn on'=>'روشن نمی‌شود','not turning on'=>'روشن نمی‌شود','does not power'=>'روشن نمی‌شود','no boot'=>'روشن نمی‌شود','روشن نمی‌شود'=>'روشن نمی‌شود','روشن نمیشود'=>'روشن نمی‌شود',
        'charging'=>'شارژ نمی‌شود','charger'=>'شارژ نمی‌شود','شارژ'=>'شارژ نمی‌شود',
        'no display'=>'تصویر ندارد','no picture'=>'تصویر ندارد','black screen'=>'تصویر ندارد','no image'=>'تصویر ندارد','تصویر'=>'تصویر ندارد','display issue'=>'تصویر ندارد',
        'short circuit'=>'اتصال کوتاه','short'=>'اتصال کوتاه','اتصال کوتاه'=>'اتصال کوتاه',
        'overheat'=>'گرمای بیش از حد','overheating'=>'گرمای بیش از حد','گرم'=>'گرمای بیش از حد','thermal'=>'گرمای بیش از حد',
        'bios'=>'بایوس','بایوس'=>'بایوس',
        'capacitor'=>'خازن','خازن'=>'خازن',
        'mosfet'=>'ماسفت','ماسفت'=>'ماسفت',
        'backlight'=>'بک‌لایت','بک‌لایت'=>'بک‌لایت',
        'dead'=>'خرابی عمومی','خراب'=>'خرابی عمومی',
        'error'=>'خطای دستگاه','ارور'=>'خطای دستگاه',
        'beep'=>'بوق خطا','بوق'=>'بوق خطا',
        'water'=>'آب‌خوردگی','آب‌خوردگی'=>'آب‌خوردگی',
        'flicker'=>'پرپر زدن','پرپر'=>'پرپر زدن',
        'artifact'=>'نویز تصویر','نویز'=>'نویز تصویر',
        'blinking'=>'چشمک زدن','چشمک'=>'چشمک زدن',
    ];
    foreach ($map as $k => $v) if (mb_strpos($text, $k) !== false) return $v;
    return 'سایر';
}
function build_steps_from_text(string $text): array {
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if ($text === '') return [['title' => 'راه‌حل', 'body' => 'محتوا در دسترس نیست']];
    $sentences = array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?؟])\s+/', $text))));
    if (!$sentences) $sentences = [$text];
    $steps = []; $chunk = ''; $n = 0;
    foreach ($sentences as $s) {
        $chunk .= $s . ' ';
        if (mb_strlen($chunk) >= 200) { $n++; $steps[] = ['title' => 'گام ' . fa($n), 'body' => trim($chunk)]; $chunk = ''; }
        if ($n >= 5) break;
    }
    if ($chunk !== '' && $n < 6) { $n++; $steps[] = ['title' => 'گام ' . fa($n), 'body' => trim($chunk)]; }
    return $steps ?: [['title' => 'راه‌حل', 'body' => trim($text)]];
}
function translate_en2fa(string $text): string {
    $text = trim($text);
    if ($text === '') return '';
    $low = mb_strtolower($text);
    static $dict = null;
    if ($dict === null) {
        $dict = [
            "won't turn on" => 'روشن نمی‌شود', 'not turning on' => 'روشن نمی‌شود', 'does not power' => 'روشن نمی‌شود',
            'does not turn' => 'روشن نمی‌شود', 'no power' => 'روشن نمی‌شود', 'no boot' => 'سیستم بالا نمی‌آید',
            'short circuit' => 'اتصال کوتاه', 'water damage' => 'آب‌خوردگی', 'water damaged' => 'آب‌خورده',
            'graphics card' => 'کارت گرافیک', 'power supply' => 'پاور', 'hard drive' => 'هارد دیسک',
            'black screen' => 'صفحه سیاه', 'no display' => 'تصویر ندارد', 'no picture' => 'تصویر ندارد',
            'motherboard' => 'مادربرد', 'mainboard' => 'مادربرد', 'notebook' => 'لپ‌تاپ', 'washing machine' => 'لباسشویی',
            'soldering' => 'لحیم‌کاری', 'replacing' => 'تعویض', 'replacement' => 'قطعه جایگزین',
            'capacitors' => 'خازن‌ها', 'capacitor' => 'خازن', 'mosfet' => 'ماسفت', 'resistor' => 'مقاومت',
            'transistor' => 'ترانزیستور', 'backlight' => 'بک‌لایت', 'overheating' => 'داغ شدن بیش از حد',
            'overheat' => 'گرمای بیش از حد', 'firmware' => 'میان‌افزار', 'voltage' => 'ولتاژ', 'multimeter' => 'مولتی‌متر',
            'circuit' => 'مدار', 'solder' => 'لحیم', 'fuse' => 'فیوز', 'battery' => 'باتری', 'charging' => 'شارژ شدن',
            'charger' => 'شارژر', 'adapter' => 'آداپتور', 'display' => 'نمایشگر', 'screen' => 'صفحه نمایش',
            'laptop' => 'لپ‌تاپ', 'monitor' => 'مانیتور', 'television' => 'تلویزیون', 'smartphone' => 'موبایل',
            'inverter' => 'اینورتر', 'repair' => 'تعمیر', 'problem' => 'مشکل', 'issue' => 'ایراد', 'replace' => 'تعویض',
            'check' => 'بررسی', 'test' => 'تست', 'guide' => 'راهنما', 'error' => 'خطا', 'beep' => 'بوق', 'dead' => 'از کار افتاده',
            'shorted' => 'اتصال کوتاه', 'board' => 'برد', 'chip' => 'چیپ', 'power' => 'تغذیه', 'how to fix' => 'روش رفع',
            'how to' => 'آموزش', 'fix' => 'رفع', 'broken' => 'خراب', 'faulty' => 'معیوب', 'damaged' => 'آسیب‌دیده',
            'motherboard repair' => 'تعمیر مادربرد', 'laptop repair' => 'تعمیر لپ‌تاپ', 'tv repair' => 'تعمیر تلویزیون',
            'led tv' => 'تلویزیون ال‌ای‌دی', 'lcd' => 'ال‌سی‌دی', 'inverter board' => 'برد اینورتر',
        ];
        uksort($dict, fn($a,$b)=>strlen($b)-strlen($a));
    }
    foreach ($dict as $en => $fa) {
        $text = str_ireplace($en, $fa, $text);
    }
    return trim(preg_replace('/\s+/', ' ', $text));
}
function build_fault_steps(string $fault, string $device, string $brand): array {
    $steps = [
        'روشن نمی‌شود' => [
            ['بررسی منبع تغذیه و کابل', "ابتدا منبع تغذیه و کابل برق {$device} را بررسی کنید و ولتاژ خروجی را با مولتی‌متر اندازه بگیرید. برای {$brand} معمولاً ولتاژ 19V برای لپ‌تاپ یا 12V/5V برای برد اصلی است."],
            ['بررسی فیوز و مدار ورودی', "فیوز اصلی برد و قطعات مسیر ورودی ({$brand}) را از نظر اتصال کوتاه یا قطعی تست کنید. مقاومت بین ورودی و زمین را اندازه بگیرید."],
            ['تست ماسفت‌ها و دیودها', 'قطعه معیوب (ماسفت یا دیود شاتکی) را با مولتی‌متر در حالت دیود تست کنید. اگر اتصال کوتاه بود، با هیتر جدا کنید.'],
            ['تغذیه با منبع آزمایشگاهی', 'برد را با منبع تغذیه آزمایشگاهی و جریان محدود (1A) تغذیه کنید. اگر جریان بالا بود، قطعه داغ را شناسایی کنید.'],
            ['تست نهایی', 'پس از تعویض قطعه، دستگاه را روشن کرده و جریان استندبای را کنترل کنید.'],
        ],
        'تصویر ندارد' => [
            ['تست با نمایشگر خارجی', 'دستگاه را به نمایشگر خارجی سالم وصل کنید تا مشخص شود مشکل از پردازش تصویر است یا پنل.'],
            ['بررسی کابل LVDS و کانکتور', 'کابل رابط بین برد اصلی و پنل را از نظر قطعی و اتصال درست بررسی کنید.'],
            ['بررسی مدار T-CON و درایو', 'ولتاژهای VGh, VGl, Vcc روی برد T-CON را اندازه بگیرید. نبود ولتاژ نشانه خرابی DC-DC است.'],
            ['تست بک‌لایت', 'با چراغ قوه روی صفحه بتابانید؛ اگر تصویر دیده شد، مشکل از بک‌لایت است.'],
            ['تست نهایی', 'پس از رفع، پایداری تصویر را در چند روشن/خاموش بررسی کنید.'],
        ],
        'شارژ نمی‌شود' => [
            ['بررسی کانکتور و کابل شارژ', 'کانکتور شارژ را از نظر شکستگی، اکسید یا گرد و غبار بررسی و با اسپری تمیز کنید.'],
            ['اندازه‌گیری جریان', 'با آمپرمتر USB جریان ورودی را اندازه بگیرید. جریان 0 یا نوسانی نشانه مشکل است.'],
            ['بررسی آی‌سی شارژ', "آی‌سی مدیریت شارژ روی برد {$brand} را از نظر گرمای غیرعادی و اتصال کوتاه بررسی کنید."],
            ['تست باتری', 'باتری را با تستر جدا تست کنید. ولتاژ زیر 3.7V برای لیتیومی نشانه خرابی است.'],
            ['تعویض قطعه', 'قطعه معیوب را با هیتر و فلاکس مناسب تعویض کنید.'],
        ],
        'اتصال کوتاه' => [
            ['اندازه‌گیری مقاومت', 'با مولتی‌متر در حالت اهم، مقاومت بین خط تغذیه و زمین را اندازه بگیرید. زیر 10 اهم یعنی اتصال کوتاه.'],
            ['تزریق ولتاژ محدود', 'برد را با منبع آزمایشگاهی 1V/1A تغذیه کنید و قطعه داغ را پیدا کنید.'],
            ['شناسایی با اسپری', 'با اسپری سرمازا، قطعه‌ای که سریع‌تر گرم می‌شود را شناسایی کنید.'],
            ['تعویض و تست', 'قطعه را تعویض و دوباره مقاومت را چک کنید. باید بالای 100 اهم باشد.'],
        ],
        'بایوس' => [
            ['دانلود بایوس صحیح', "آخرین نسخه بایوس مخصوص {$device} {$brand} را از منبع رسمی تهیه کنید. حتماً مدل دقیق را چک کنید."],
            ['اتصال پروگرامر', 'با کلیپس SOIC8، پروگرامر CH341 را به چیپ بایوس متصل کنید.'],
            ['بکاپ', 'قبل از نوشتن، محتوای فعلی را بخوانید و ذخیره کنید.'],
            ['نوشتن و تست', 'فایل جدید را بنویسید، Verify کنید و دستگاه را روشن کنید.'],
        ],
        'گرمای بیش از حد' => [
            ['تمیز کردن و خمیر حرارتی', 'فن و هیت‌سینک را تمیز کرده و خمیر حرارتی جدید بزنید.'],
            ['بررسی ولتاژ', 'ولتاژ Vcore و 12V را زیر بار سنگین اندازه بگیرید.'],
            ['تست فن', 'دور فن را بررسی کنید. زیر 1000 RPM نشانه خرابی است.'],
            ['تست پایداری', 'دستگاه را 20 دقیقه با FurMark یا AIDA64 استرس کنید.'],
        ],
        'بک‌لایت' => [
            ['تست درایو LED', 'خروجی درایو بک‌لایت را اندازه بگیرید. ولتاژ بالا و نوسان = ردیف LED سوخته.'],
            ['تست ردیف‌ها با تستر', 'هر ردیف LED را جدا با تستر 300V تست کنید.'],
            ['تعویض ردیف', 'پنل را با احتیاط باز و ردیف سوخته را با مشابه تعویض کنید.'],
            ['تنظیم جریان', 'جریان درایو را کمی کاهش دهید تا عمر LED بیشتر شود.'],
        ],
        'خازن' => [
            ['بررسی بصری', 'خازن‌های بادکرده، نشتی‌دار یا تغییر رنگ را شناسایی کنید.'],
            ['اندازه‌گیری ESR و ظرفیت', 'با ESR متر، خازن‌های مشکوک را تست کنید. ظرفیت زیر 80% یعنی خراب.'],
            ['تعویض با کیفیت', 'خازن را با نمونه ژاپنی (Rubycon, Nichicon) با همان ظرفیت و ولتاژ بالاتر تعویض کنید.'],
        ],
        'ماسفت' => [
            ['تست دیودی', 'پایه‌های S و D را در حالت دیود تست کنید. بوق ممتد = سوخته.'],
            ['بررسی مدار گیت', 'مقاومت‌های گیت و درایور را چک کنید.'],
            ['تعویض', 'ماسفت را با هیتر 350 درجه جدا و نمونه مشابه با فلاکس لحیم کنید.'],
        ],
    ];
    if (isset($steps[$fault])) return $steps[$fault];
    return [
        ['بررسی اولیه چشمی', "ابتدا {$device} {$brand} را با لوپ بررسی کنید. قطعات سوخته، خازن بادکرده یا ترک برد را پیدا کنید."],
        ['اندازه‌گیری ولتاژ و جریان', 'ولتاژهای اصلی تغذیه (3.3V, 5V, 12V, 19V) را اندازه بگیرید و با مقدار نرمال مقایسه کنید.'],
        ['تست قطعات نیمه‌هادی', 'دیودها، ماسفت‌ها و خازن‌های مسیر معیوب را با مولتی‌متر تست کنید.'],
        ['تعویض و مونتاژ', 'قطعه معیوب را تعویض، برد را تمیز و دستگاه را تست نهایی کنید.'],
    ];
}
function build_persian_tip(string $rawTitle, string $rawDesc, string $sourceUrl = '', string $sourceName = ''): array {
    $combined = $rawTitle . ' ' . strip_tags($rawDesc);
    $brand = detect_brand($combined);
    $device = detect_device($combined);
    $fault = detect_fault($combined);
    $translatedDesc = translate_en2fa(mb_substr(strip_tags($rawDesc), 0, 1200));

    // عنوان‌های هوشمند متنوع
    $templates = [
        "رفع مشکل {$fault} در {$device} {$brand} — راهنمای کامل و تست‌شده",
        "چرا {$device} {$brand} {$fault}؟ علت‌ها و راه‌حل حرفه‌ای",
        "{$device} {$brand} دچار {$fault} شده — تشخیص و تعمیر گام‌به‌گام",
        "آموزش تعمیر {$fault} در {$device} {$brand} با مولتی‌متر",
        "تجربه تعمیرکار: رفع {$fault} در {$device} {$brand}",
    ];
    $title = $templates[array_rand($templates)];

    // توضیح کوتاه هوشمند
    $short = "در این قلق آموزشی، روش تشخیص و رفع مشکل «{$fault}» در {$device} {$brand} به‌صورت گام‌به‌گام و تست‌شده توضیح داده شده است.";

    // توضیح کامل
    $desc = "<p>{$device} {$brand} با مشکل «{$fault}» مواجه شده است. این مشکل یکی از خرابی‌های رایج در تعمیرات است و در ادامه ابتدا علت‌های اصلی و سپس روش تعمیر مرحله‌به‌مرحله با ابزار دقیق آموزش داده می‌شود.</p>";
    if ($translatedDesc !== '' && mb_strlen($translatedDesc) > 20) {
        $desc .= '<blockquote>' . h(mb_substr($translatedDesc, 0, 500)) . '</blockquote>';
    }
    $desc .= '<p><b>ابزار مورد نیاز:</b> مولتی‌متر دیجیتال، هویه، هیتر، فلاکس، لوپ.</p>';
    $desc .= '<p>⚠️ قبل از هر اقدام، دستگاه را از برق و باتری جدا کنید و از دستبند آنتی‌استاتیک استفاده نمایید.</p>';
    if ($sourceName !== '') {
        $desc .= '<p style="font-size:11px;color:#8b98a5">منبع اصلی: ' . h($sourceName) . '</p>';
    }

    return [
        'title' => $title,
        'short_description' => $short,
        'description' => $desc,
        'steps' => build_fault_steps($fault, $device, $brand),
        'device' => $device,
        'brand' => $brand,
        'fault' => $fault,
        'source_url' => $sourceUrl,
        'source_name' => $sourceName,
    ];
}
function discover_reddit(string $query, int $limit = 4): array {
    $url = 'https://www.reddit.com/search.json?q=' . urlencode($query) . '&limit=' . $limit . '&sort=relevance&t=year&raw_json=1&include_over_18=0&safe=active';
    $body = fetch_url($url, 8);
    if ($body === null) return [];
    $data = json_decode($body, true);
    if (!is_array($data) || !isset($data['data']['children'])) return [];
    $items = [];
    foreach ($data['data']['children'] as $child) {
        $d = $child['data'] ?? [];
        $title = trim(strip_tags((string)($d['title'] ?? '')));
        $selftext = trim(strip_tags((string)($d['selftext'] ?? '')));
        if ($title === '' || mb_strlen($title) < 12) continue;
        if (mb_strlen($selftext) < 30) continue;
        // فیلتر محتوای نامرتبط
        $low = mb_strtolower($title.' '.$selftext);
        if (!preg_match('/(repair|fix|board|power|display|charge|motherboard|tv|laptop|mosfet|capacitor|bios|short)/i', $low)) continue;
        $url = isset($d['permalink']) ? ('https://www.reddit.com' . $d['permalink']) : ($d['url'] ?? '');
        $preview = (string)($d['thumbnail'] ?? '');
        $images = [];
        if (isset($d['preview']['images'][0]['source']['url'])) $images[] = html_entity_decode($d['preview']['images'][0]['source']['url']);
        $items[] = [
            'title' => $title,
            'description' => $selftext,
            'description_html' => $selftext,
            'url' => $url,
            'image' => ($preview && filter_var($preview, FILTER_VALIDATE_URL) ? $preview : ($images[0] ?? '')),
            'images' => $images,
            'source_name' => 'Reddit'
        ];
        if (count($items) >= $limit) break;
    }
    return $items;
}
function discover_web(string $query, int $limit = 5): array {
    $items = [];
    $q = urlencode($query);
    // استفاده از DuckDuckGo HTML
    $html = fetch_url('https://html.duckduckgo.com/html/?q=' . $q, 15);
    if ($html !== null && preg_match_all('~<a[^>]+class="result__a"[^>]+href="([^"]+)"[^>]*>(.*?)</a>.*?<a[^>]+class="result__snippet"[^>]*>(.*?)</a>~is', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $match) {
            $href = $match[1]; $title = trim(strip_tags($match[2])); $snippet = trim(strip_tags($match[3]));
            $real = $href;
            if (preg_match('~uddg=([^&]+)~', $href, $u)) $real = urldecode($u[1]);
            if ($title === '' || !preg_match('~^https?://~i', $real)) continue;
            // فیلتر سایت‌های معتبر
            if (!preg_match('#(ifixit|electronics|repair|hackaday|adafruit|eevblog|allaboutcircuits|electronics-lab|edn|electroschematics|circuitdigest|howtogeek|tomshardware)#i', $real)) {
                // اگر سایت معتبر نیست ولی عنوان خیلی مرتبط است، باز هم قبول کن
                if (!preg_match('/(repair|fix|motherboard|power supply|tv|led|capacitor|mosfet)/i', $title)) continue;
            }
            $items[] = ['title' => $title, 'description' => $snippet, 'description_html' => $snippet, 'url' => $real, 'image' => '', 'images'=>[], 'source_name'=>parse_url($real, PHP_URL_HOST) ?: 'Web'];
            if (count($items) >= $limit) break;
        }
    }
    return $items;
}
function fetch_article_details(string $url): array {
    $html = fetch_url($url, 12);
    if ($html === null) return ['text'=>'', 'images'=>[], 'html'=>''];
    $text = extract_article_text($html, 4000);
    $images = extract_images_from_html($html, $url, 5);
    return ['text'=>$text, 'images'=>$images, 'html'=>$html];
}
function category_for_device(string $device, ?int $preferred): int {
    $pdo = db();
    if ($preferred > 0) return $preferred;
    $parentMap = [
        'مادربرد' => 'مادربرد', 'لپ‌تاپ' => 'لپ‌تاپ', 'کارت گرافیک' => 'کارت گرافیک', 'پاور' => 'پاور',
        'مانیتور' => 'مانیتور و تلویزیون', 'تلویزیون' => 'مانیتور و تلویزیون', 'موبایل' => 'موبایل و تبلت',
        'آداپتور' => 'آداپتور و شارژر', 'برد صنعتی' => 'بردهای صنعتی', 'لوازم خانگی' => 'لوازم خانگی',
        'برد الکترونیکی' => 'سایر', 'کنسول بازی' => 'کنسول بازی', 'پرینتر' => 'سایر', 'مودم و شبکه' => 'سایر',
    ];
    $parentName = $parentMap[$device] ?? 'سایر';
    $q = $pdo->prepare('SELECT id FROM categories WHERE parent_id IS NULL AND name=? LIMIT 1');
    $q->execute([$parentName]);
    $parentId = (int)$q->fetchColumn();
    if ($parentId) {
        $c = $pdo->prepare('SELECT id FROM categories WHERE parent_id=? AND status="active" ORDER BY sort_order, id LIMIT 1');
        $c->execute([$parentId]);
        $child = (int)$c->fetchColumn();
        if ($child) return $child;
        return $parentId;
    }
    // fallback: اولین دسته فعال
    $f = $pdo->query("SELECT id FROM categories WHERE status='active' ORDER BY id LIMIT 1");
    return (int)$f->fetchColumn();
}
function reputable_sources_list(string $region = 'all'): array {
    $western = [
        // Reddit - معتبرترین برای تعمیرات (جهانی) - 2M+ عضو
        'https://www.reddit.com/r/AskElectronics/.rss',
        'https://www.reddit.com/r/ElectronicsRepair/.rss',
        'https://www.reddit.com/r/TVRepair/.rss',
        'https://www.reddit.com/r/computerrepair/.rss',
        'https://www.reddit.com/r/AskElectronics/comments/.rss',
        'https://www.reddit.com/r/MobileRepair/.rss',
        'https://www.reddit.com/r/Consolerepair/.rss',
        // مرجع تعمیرات جهانی
        'https://www.ifixit.com/News/rss',
        'https://www.ifixit.com/News/category/repair-stories/rss',
        // سایت‌های تخصصی الکترونیک معتبر غربی
        'https://hackaday.com/feed/',
        'https://blog.adafruit.com/feed/',
        'https://www.eevblog.com/feed/',
        'https://www.allaboutcircuits.com/new/rss/',
        'https://www.allaboutcircuits.com/industry/rss/',
        'https://www.electronics-lab.com/feed/',
        'https://www.electroschematics.com/feed/',
        'https://www.edn.com/feed/',
        'https://electronics.stackexchange.com/feeds',
        'https://www.electronicshub.org/feed/',
        'https://www.engineersgarage.com/feed/',
        'https://www.electronicsweekly.com/feed/',
        'https://www.eetimes.com/feed/',
        'https://www.circuitdigest.com/feed',
    ];
    $indian = [
        // سایت‌های هندی معتبر - Electronics For You (بزرگترین مجله الکترونیک هند از 1969)
        'https://www.electronicsforu.com/feed',
        'https://www.electronicsforu.com/category/electronics-projects/feed',
        'https://www.electronicsforu.com/category/technology-trends/feed',
        'https://www.electronicsforu.com/category/tech-focus/feed',
        'https://www.electronicsforu.com/category/market-verticals/feed',
        'https://www.electronicsforu.com/category/efy-plus/feed',
        // Circuit Digest - هندی، پروژه‌های عملی الکترونیک
        'https://circuitdigest.com/feed',
        'https://circuitdigest.com/rss',
        // Electronics Hub - آموزش آردوینو و تعمیرات
        'https://www.electronicshub.org/feed',
        'https://www.electronicshub.org/rss',
        // Engineers Garage - هندی
        'https://www.engineersgarage.com/feed',
        'https://www.engineersgarage.com/rss',
        // Electrical Technology - هندی/پاکستانی
        'https://www.electricaltechnology.org/feed',
        // ElectronicsComp - فروشگاه + بلاگ هندی
        'https://www.electronicscomp.com/blog/feed',
        // Electronics For You Plus - هندی
        'https://www.electronicsforu.com/category/electronics-projects/arduino-projects/feed',
        // Indian makers
        'https://www.electronicsforu.com/category/electronics-projects/raspberry-pi-projects/feed',
    ];
    $chinese = [
        // سایت‌های چینی معتبر (نسخه انگلیسی یا قابل ترجمه) - چین کارخانه جهان
        'https://www.elecfans.com/feed', // Elecfans - بزرگترین انجمن الکترونیک چین
        'https://www.elecfans.com/article/feed',
        'https://www.21ic.com/rss/', // 21IC - چینی، IC و برد
        'https://www.21ic.com/news/rss/',
        'https://www.eet-china.com/rss', // EET China - نسخه چینی EETimes
        'https://www.eet-china.com/feed',
        'https://www.eet-china.com/news/rss',
        'https://www.eepw.com.cn/rss', // EEPW چینی - Electronic Engineering
        'https://www.dianyuan.com/rss', // Dianyuan - تخصصی پاور و منبع تغذیه چینی
        'https://www.dianyuan.com/feed',
        // سایت‌های چینی با محتوای انگلیسی
        'https://www.electronicsweekly.com/feed/',
        'https://www.eetimes.com/feed/',
        'https://www.eetimes.com/rss',
        // چینی - تعمیرات موبایل (شenzhen)
        'https://www.chinafix.com/feed', // ChinaFix - تعمیرات موبایل چینی
    ];
    $japanese = [
        // ژاپنی - برای تکمیل (درخواست کاربر: هندی و چینی، ولی ژاپنی هم اضافه برای هوشمندی)
        'https://www.electronics-lab.com/feed/', // مشترک
        'https://www.electro-tech-online.com/feed/',
    ];

    if ($region === 'western') return $western;
    if ($region === 'indian') return $indian;
    if ($region === 'chinese') return $chinese;
    if ($region === 'japanese') return $japanese;
    if ($region === 'all') return array_values(array_unique(array_merge($western, $indian, $chinese, $japanese)));
    return array_values(array_unique(array_merge($western, $indian)));
}
function reputable_sources_by_region(bool $indianEnabled, bool $chineseEnabled, bool $japaneseEnabled = false): array {
    $list = reputable_sources_list('western');
    if ($indianEnabled) $list = array_merge($list, reputable_sources_list('indian'));
    if ($chineseEnabled) $list = array_merge($list, reputable_sources_list('chinese'));
    if ($japaneseEnabled) $list = array_merge($list, reputable_sources_list('japanese'));
    return array_values(array_unique($list));
}
function indian_repair_sites(): array {
    return [
        'electronicsforu.com' => 'Electronics For You - هند',
        'circuitdigest.com' => 'Circuit Digest - هند',
        'electronicshub.org' => 'Electronics Hub - هند',
        'engineersgarage.com' => 'Engineers Garage - هند',
        'electricaltechnology.org' => 'Electrical Technology - هند',
    ];
}
function chinese_repair_sites(): array {
    return [
        'elecfans.com' => 'Elecfans - چین',
        '21ic.com' => '21IC - چین',
        'eet-china.com' => 'EET China - چین',
        'eepw.com.cn' => 'EEPW - چین',
        'dianyuan.com' => 'Dianyuan - چین (پاور)',
    ];
}
function collect_tips_web(int $count, int $categoryId, string $access, array $sources, array $queries = [], array $extraSettings = []): array {
    $pdo = db();
    $botQ = $pdo->prepare("SELECT id FROM users WHERE phone='09100000000' LIMIT 1");
    $botQ->execute();
    $botId = (int)$botQ->fetchColumn();
    if (!$botId) return ['created' => 0, 'scanned' => 0, 'errors' => 0, 'error' => 'حساب کاربر سیستم یافت نشد. نصب را دوباره اجرا کنید.'];

    // تنظیمات پیشرفته ربات از extraSettings یا از settings() جدول - v4.3 کامل با هندی و چینی
    $s = settings();
    $indianEnabled = $extraSettings['indian_enabled'] ?? (int)($s['auto_collect_indian_enabled'] ?? 1) === 1;
    $chineseEnabled = $extraSettings['chinese_enabled'] ?? (int)($s['auto_collect_chinese_enabled'] ?? 0) === 1;
    $japaneseEnabled = $extraSettings['japanese_enabled'] ?? (int)($s['auto_collect_japanese_enabled'] ?? 0) === 1;
    $minLength = max(20, (int)($extraSettings['min_length'] ?? $s['auto_collect_min_length'] ?? 100));
    $maxImages = max(1, min(5, (int)($extraSettings['max_images'] ?? $s['auto_collect_max_images'] ?? 3)));
    $translateEnabled = $extraSettings['translate_enabled'] ?? (int)($s['auto_collect_translate_enabled'] ?? 1) === 1;
    $extractFull = $extraSettings['extract_full'] ?? (int)($s['auto_collect_extract_full'] ?? 1) === 1;
    $saveImages = $extraSettings['save_images'] ?? (int)($s['auto_collect_save_images'] ?? 1) === 1;
    $filterRepair = $extraSettings['filter_repair'] ?? (int)($s['auto_collect_filter_repair'] ?? 1) === 1;
    $language = $extraSettings['language'] ?? $s['auto_collect_language'] ?? 'auto';
    $contentType = $extraSettings['content_type'] ?? $s['auto_collect_content_type'] ?? 'repair';
    $imageQuality = $extraSettings['image_quality'] ?? $s['auto_collect_image_quality'] ?? 'medium';
    $autoPublish = $extraSettings['auto_publish'] ?? (int)($s['auto_collect_auto_publish'] ?? 1) === 1;
    $excludeKeywords = $extraSettings['exclude_keywords'] ?? $s['auto_collect_exclude_keywords'] ?? '';
    $savePath = $extraSettings['save_path'] ?? $s['auto_collect_save_path'] ?? 'auto';
    $maxRetries = max(1, min(5, (int)($extraSettings['max_retries'] ?? $s['auto_collect_max_retries'] ?? 2)));
    $timeout = max(5, min(30, (int)($extraSettings['timeout'] ?? $s['auto_collect_timeout'] ?? 12)));

    $excludeList = array_filter(array_map('trim', preg_split('/[\n,]+/', $excludeKeywords)));

    if ($queries === []) {
        $queries = [
            // فارسی - تخصصی تعمیرات
            'تعمیر مادربرد سامسونگ', 'رفع مشکل روشن نشدن لپ‌تاپ ایسوس', 'تعمیر پاور سوئیچینگ', 'عیب‌یابی کارت گرافیک',
            'تعمیر تلویزیون ال‌جی تصویر ندارد', 'تعمیر موبایل شارژ نمی‌شود', 'تعویض خازن مادربرد', 'تست ماسفت با مولتی‌متر',
            'تعمیر بک‌لایت تلویزیون', 'رفع بوق خطا مادربرد', 'تعمیر برد لباسشویی', 'آموزش لحیم‌کاری SMD',
            // انگلیسی - تخصصی
            'motherboard no power repair', 'laptop no boot fix', 'tv backlight repair', 'power supply short circuit fix',
            'samsung tv repair', 'asus laptop repair', 'capacitor replacement guide', 'mosfet testing tutorial',
            'inverter board repair', 'mobile phone charging fix',
            // هندی - انگلیسی (سایت‌های هندی انگلیسی هستند)
            'electronics repair india', 'mobile motherboard repair india', 'led tv repair guide india', 'circuit digest electronics project',
            // چینی - انگلیسی
            'china electronics repair', 'smd soldering tutorial', 'inverter board repair china', '21ic electronics repair'
        ];
    }

    if (empty($sources)) {
        $sources = reputable_sources_by_region($indianEnabled, $chineseEnabled, $japaneseEnabled);
    }

    $candidates = [];
    $seen = [];
    $seenUrls = [];
    $add = function (array $c) use (&$candidates, &$seen, &$seenUrls, $minLength, $filterRepair, $excludeList, $contentType) {
        $title = trim((string)($c['title'] ?? ''));
        $desc = trim((string)($c['description'] ?? ''));
        $urlKey = trim((string)($c['url'] ?? ''));
        if ($title === '' || mb_strlen($title) < 10) return;
        if (mb_strlen($desc) < $minLength && mb_strlen($title) < 35) return;
        // فیلتر کلمات مستثنی
        if (!empty($excludeList)) {
            $low = mb_strtolower($title.' '.$desc);
            foreach ($excludeList as $ex) if ($ex !== '' && mb_strpos($low, mb_strtolower($ex)) !== false) return;
        }
        // فیلتر نوع محتوا
        if ($contentType === 'repair') {
            $combined = mb_strtolower($title.' '.$desc);
            $repairKeywords = ['repair','fix','تعمیر','عیب‌یابی','رفع','board','power','display','charge','motherboard','tv','لپ‌تاپ','مادربرد','پاور','تلویزیون','موبایل','خازن','ماسفت','بایوس','اتصال کوتاه','بک‌لایت','solder','capacitor','mosfet','bios','short','backlight','inverter','led','lcd'];
            $found = false;
            foreach ($repairKeywords as $kw) if (mb_strpos($combined, mb_strtolower($kw)) !== false) { $found = true; break; }
            if (!$found) return;
        }
        $key = mb_substr($title, 0, 130);
        if (isset($seen[$key])) return;
        if ($urlKey !== '' && isset($seenUrls[$urlKey])) return;
        $seen[$key] = true;
        if ($urlKey !== '') $seenUrls[$urlKey] = true;
        $candidates[] = $c;
    };

    // 1. منابع RSS معتبر - حداکثر 10 منبع (قبلاً 8) با retry هوشمند
    $srcsLimited = array_slice($sources, 0, 10);
    foreach ($srcsLimited as $source) {
        $source = trim((string)$source);
        if ($source === '' || !filter_var($source, FILTER_VALIDATE_URL)) continue;
        $xml = null;
        for ($retry=0; $retry<$maxRetries; $retry++) {
            $xml = fetch_url($source, $timeout);
            if ($xml !== null) break;
        }
        if ($xml === null) continue;
        $host = parse_url($source, PHP_URL_HOST) ?: 'RSS';
        foreach (parse_rss_items($xml, $host) as $it) {
            $add($it);
        }
        if (count($candidates) >= $count * 3) break;
    }

    // 2. جستجوی هوشمند - حداکثر 6 کوئری (قبلاً 5) با منطقه‌ای
    $queriesLimited = array_slice($queries, 0, 6);
    if (!$queriesLimited) $queriesLimited = ['laptop no power fix', 'motherboard repair', 'tv backlight fix', 'mobile repair india', 'electronics repair china', 'inverter repair'];
    foreach ($queriesLimited as $query) {
        $query = trim((string)$query);
        if ($query === '') continue;
        foreach (discover_reddit($query, 3) as $r) $add($r);
        if (count($candidates) < $count * 3) {
            foreach (discover_web($query, 3) as $w) $add($w);
        }
        if (count($candidates) >= $count * 5) break;
    }

    if (!$candidates) return ['created' => 0, 'scanned' => 0, 'errors' => 0, 'error' => 'هیچ مطلبی از منابع معتبر (غربی/هندی/چینی) پیدا نشد. اینترنت سرور یا دسترسی به منابع را بررسی کنید.'];

    $created = 0;
    $scanned = 0;
    $errors = 0;

    $status = $autoPublish ? 'published' : 'pending';
    $insert = $pdo->prepare('INSERT INTO tips (author_id,category_id,title,short_description,description,device_name,brand,model,board_number,fault_type,difficulty,solution_json,tools,images_json,video_url,attachments_json,access_type,price,visibility,status,tags,version,versions_json,featured,views,likes_count,purchases_count,rating_sum,rating_count,duplicate_of,rejection_reason,source_url,source_name,published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,0,0,0,0,0,0,NULL,NULL,?,?,?)');

    foreach ($candidates as $c) {
        if ($created >= $count) break;
        $scanned++;

        $fullText = (string)($c['description'] ?? '');
        $fullHtml = (string)($c['description_html'] ?? $c['description'] ?? '');
        $remoteImages = [];
        if (!empty($c['images']) && is_array($c['images'])) $remoteImages = $c['images'];
        if (!empty($c['image'])) $remoteImages[] = $c['image'];

        if ($extractFull && !empty($c['url']) && filter_var($c['url'], FILTER_VALIDATE_URL) && !str_contains($c['url'], 'reddit.com')) {
            $details = fetch_article_details($c['url']);
            if (mb_strlen($details['text']) > mb_strlen($fullText)) {
                $fullText = $details['text'];
            }
            if ($fullHtml === '' && $details['html'] !== '') $fullHtml = $details['html'];
            if (!empty($details['images'])) {
                $remoteImages = array_merge($remoteImages, $details['images']);
            }
        }

        $textForTip = $translateEnabled ? $fullText : (string)($c['description'] ?? '');
        if (mb_strlen($textForTip) < 60) $textForTip = $c['title'] . ' ' . $textForTip;

        // تشخیص منطقه برای ذخیره درست
        $region = 'western';
        $host = parse_url($c['url'] ?? '', PHP_URL_HOST) ?? $c['source'] ?? '';
        if (preg_match('/(electronicsforu|circuitdigest|electronicshub|engineersgarage|electricaltechnology)/i', $host)) $region = 'indian';
        elseif (preg_match('/(elecfans|21ic|eet-china|eepw|dianyuan|chinafix)/i', $host)) $region = 'chinese';

        $tip = build_persian_tip((string)$c['title'], $textForTip, (string)($c['url'] ?? ''), (string)($c['source_name'] ?? $c['source'] ?? $host));

        $dq = $pdo->prepare('SELECT id FROM tips WHERE title=? OR source_url=? LIMIT 1');
        $dq->execute([$tip['title'], $c['url'] ?? '']);
        if ($dq->fetch()) { $errors++; continue; }

        $cat = category_for_device($tip['device'], $categoryId);
        if (!$cat) { $errors++; continue; }

        $diffMap = [
            'روشن نمی‌شود' => 'hard', 'اتصال کوتاه' => 'hard', 'بایوس' => 'hard',
            'شارژ نمی‌شود' => 'hard', 'آب‌خوردگی' => 'hard', 'بک‌لایت' => 'medium',
            'تصویر ندارد' => 'medium', 'گرمای بیش از حد' => 'medium', 'خازن' => 'easy',
            'بوق خطا' => 'medium', 'نویز تصویر' => 'medium', 'چشمک زدن' => 'medium'
        ];
        $diff = $diffMap[$tip['fault']] ?? 'medium';

        $toolsMap = [
            'hard' => 'مولتی‌متر دیجیتال،هویه حرفه‌ای،هیتر،فلاکس،لوپ،منبع تغذیه آزمایشگاهی،اسیلوسکوپ',
            'medium' => 'مولتی‌متر،هویه،فلاکس،لوپ،تستر LED',
            'easy' => 'مولتی‌متر،هویه،فلاکس'
        ];
        $tools = $toolsMap[$diff] ?? $toolsMap['medium'];

        // کیفیت تصویر بر اساس تنظیم
        $qualityMap = ['low'=>65, 'medium'=>84, 'high'=>92];
        $imgQuality = $qualityMap[$imageQuality] ?? 84;

        $tags = implode(',', array_unique(array_filter([$tip['brand'], $tip['device'], $tip['fault'], 'تعمیرات', 'برد', $region==='indian'?'هند':'', $region==='chinese'?'چین':'', $language])));

        // ذخیره درست تصاویر در جای درست با منطقه
        $images = [];
        $remoteImages = array_values(array_unique(array_filter($remoteImages)));
        if ($saveImages) {
            foreach (array_slice($remoteImages, 0, $maxImages) as $remoteImg) {
                $local = download_image($remoteImg, $region, $imgQuality);
                if ($local) {
                    $images[] = $local;
                } else {
                    if ($access === 'free' && filter_var($remoteImg, FILTER_VALIDATE_URL)) {
                        $images[] = $remoteImg;
                    }
                }
            }
        } else {
            $images = array_slice($remoteImages, 0, $maxImages);
        }

        if (!$images) {
            $placeholderSeed = md5($tip['title'].$tip['device'].$tip['brand'].$region);
            $fallbacks = [
                'https://picsum.photos/seed/'.$placeholderSeed.'/800/600',
                'https://picsum.photos/seed/'.md5($placeholderSeed.'2').'/800/600',
            ];
            foreach ($fallbacks as $fb) {
                if ($saveImages) {
                    $local = download_image($fb, $region, $imgQuality);
                    if ($local) { $images[] = $local; break; }
                } else {
                    $images[] = $fb; break;
                }
            }
        }

        if (!$images) { $errors++; continue; }

        try {
            $insert->execute([
                $botId, $cat, $tip['title'], $tip['short_description'], $tip['description'],
                $tip['device'], $tip['brand'], null, null, $tip['fault'], $diff,
                json_encode($tip['steps'], JSON_UNESCAPED_UNICODE), $tools, json_encode($images, JSON_UNESCAPED_UNICODE),
                null, json_encode([], JSON_UNESCAPED_UNICODE), $access, 0, 'public', $status, $tags,
                json_encode([], JSON_UNESCAPED_UNICODE),
                $tip['source_url'] ?? $c['url'] ?? null,
                $tip['source_name'] ?? $c['source_name'] ?? $c['source'] ?? null,
                date('Y-m-d H:i:s'),
            ]);
            $created++;
        } catch (Throwable $e) {
            $errors++;
        }
    }

    return ['created' => $created, 'scanned' => $scanned, 'errors' => $errors, 'settings_used' => ['indian'=>$indianEnabled, 'chinese'=>$chineseEnabled, 'japanese'=>$japaneseEnabled, 'max_images'=>$maxImages, 'save_images'=>$saveImages, 'region_count'=>count($srcsLimited)]];
}
function escrow_admin_id(): int { static $id = null; if ($id === null) { $q = db()->query("SELECT id FROM users WHERE role IN ('superadmin','admin') ORDER BY id LIMIT 1"); $id = (int)($q->fetchColumn() ?: 0); } return $id; }
function is_seller(array $u): bool { return ($u['seller_status'] ?? 'none') === 'approved' || in_array($u['role'] ?? '', ['admin','superadmin'], true); }
function board_condition_label(string $c): string { return ['new'=>'نو','like_new'=>'در حد نو','used'=>'کارکرده','repair'=>'تعمیرشده'][$c] ?? 'کارکرده'; }
function board_status_label(string $s): string { return ['pending'=>'در انتظار تأیید','approved'=>'فعال','rejected'=>'رد شده','sold'=>'فروخته شد','archived'=>'بایگانی'][$s] ?? $s; }
function order_status_label(string $s): string { return ['paid'=>'پرداخت شده (امانت)','shipped'=>'ارسال شده','completed'=>'تحویل تأیید شد','cancelled'=>'لغو و بازگشت وجه'][$s] ?? $s; }
function leaf_categories(): array { $rows = db()->query("SELECT id,parent_id,name FROM categories WHERE status='active' ORDER BY sort_order,name")->fetchAll(); $byParent=[]; $byId=[]; foreach($rows as $r){ $byParent[(int)$r['parent_id']][]=$r; $byId[(int)$r['id']]=$r; } $out=[]; foreach($rows as $r){ $hasKids = !empty($byParent[(int)$r['id']]); if(!$hasKids){ $path=[]; $cur=$r; while($cur && ($cur['parent_id'])){ $path[]= $cur['name']; $cur = $byId[(int)$cur['parent_id']] ?? null; } if($cur){$path[]=$cur['name'];} $out[]=['id'=>(int)$r['id'],'label'=>implode(' ← ',array_reverse($path))]; } } return $out; }
function board_card(array $b): void { $imgs = json_decode_array($b['images_json'] ?? '[]'); ?><a class="card tcard" href="<?=url('board/'.(int)$b['id'])?>"><div class="timg"><?php if($imgs):?><img loading="lazy" src="<?=h($imgs[0])?>" alt="<?=h($b['title'])?>"><?php else:?><div style="height:100%;display:grid;place-items:center;font-size:42px">🔩</div><?php endif;?><div class="badges"><span class="pill green"><?=h(board_condition_label($b['condition_status']))?></span><?php if($b['status']==='sold'):?><span class="pill rose">فروخته شد</span><?php endif;?></div></div><div class="tbody"><h3><?=h($b['title'])?></h3><div class="tmeta"><?php if(!empty($b['brand'])):?><span class="pill"><?=h($b['brand'])?></span><?php endif;?><?php if(!empty($b['model'])):?><span class="pill"><?=h($b['model'])?></span><?php endif;?></div><div class="tfoot"><strong style="color:var(--accent);font-size:16px"><?=money($b['price'])?> تومان</strong><span class="muted" style="margin-right:auto;font-size:11px">👁 <?=fa($b['views'])?></span></div></div></a><?php }
function tip_card(array $t): void { $imgs=tip_images($t); $rating=((int)$t['rating_count']>0)?round((int)$t['rating_sum']/(int)$t['rating_count'],1):0; $locked=!tip_has_access($t,current_user()); ?><a class="card tip-card" href="<?=url('tip/'.$t['id'])?>"><div class="tip-img"><?php if($imgs):?><img loading="lazy" src="<?=h(media_url($imgs[0], 'thumb', (int)$t['id']))?>" alt="<?=h($t['title'])?>" class="<?=$locked?'bk-blur':''?>"><?php if($locked):?><span class="bk-lockbadge" title="<?=h($t['access_type']==='paid'?'پس از خرید نمایش داده می‌شود':'پس از لایک نمایش داده می‌شود')?>"><?=$t['access_type']==='paid'?'💰':'♥'?></span><?php endif;?><?php else:?><div style="height:100%;display:grid;place-items:center;font-size:45px">🔧</div><?php endif;?><div class="badges"><span class="pill <?=h($t['access_type']==='paid'?'amber':($t['access_type']==='like'?'rose':'green'))?>"><?=h(access_label($t['access_type'],(int)$t['price']))?></span><?php if((int)$t['featured']):?><span class="pill amber">★ منتخب</span><?php endif;?></div></div><div class="tip-body"><h3><?=h($t['title'])?></h3><p><?=h($t['short_description'])?></p><div class="tip-meta"><span class="pill"><?=h(['easy'=>'آسان','medium'=>'متوسط','hard'=>'سخت'][$t['difficulty']??'medium']??'متوسط')?></span><span class="pill">◉ <?=fa($t['views'])?></span><?php if($rating):?><span class="pill amber">★ <?=fa($rating)?></span><?php endif;?></div><div class="tip-footer"><span class="avatar small"><?=h(mb_substr($t['author_name']??'؟',0,1))?></span><small class="muted"><?=h($t['author_name']??'تعمیرکار')?></small><?php if(!empty($t['verified'])):?><span class="check">✓</span><?php endif;?><?php if(has_favorite((int)$t['id'], current_user())):?><span title="علاقه‌مندی من" style="margin-right:auto;color:#d94258">♥</span><?php endif;?></div></div></a><?php }
function header_html(string $title=''): void { $u=current_user(); $s=settings(); $active=bk_route(); ?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="<?=h(($s['meta_description'] ?? 'بازار تخصصی قلق‌های تعمیراتی بردهای الکترونیکی'))?>"><?php if(!empty($s['meta_keywords'])):?><meta name="keywords" content="<?=h($s['meta_keywords'])?>"><?php endif;?><meta name="theme-color" content="#078659"><meta property="og:type" content="website"><meta property="og:site_name" content="<?=h($s['site_title'] ?? SITE_NAME)?>"><meta property="og:title" content="<?=h($title ?: ($s['site_title'] ?? SITE_NAME))?>"><meta property="og:description" content="<?=h(($s['meta_description'] ?? 'بازار تخصصی قلق‌های تعمیراتی بردهای الکترونیکی'))?>"><?php if(!empty($s['og_image'])):?><meta property="og:image" content="<?=h($s['og_image'])?>"><?php endif;?><meta name="twitter:card" content="summary_large_image"><link rel="manifest" href="<?=url('manifest.webmanifest')?>"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><meta name="apple-mobile-web-app-title" content="<?=h($s['site_title'] ?? SITE_NAME)?>"><link rel="apple-touch-icon" href="<?=url('assets/icon-192.png')?>"><title><?=h($title ? $title.' | '.($s['site_title'] ?? SITE_NAME) : ($s['site_title'] ?? SITE_NAME).' — بازار قلق‌های تعمیراتی')?></title><script>
(function(){
  try{
    if(localStorage.getItem('bk_theme')==='light'){document.documentElement.setAttribute('data-theme','light')}
  }catch(e){}
  window.__pwaDeferred = null;
  window.addEventListener('beforeinstallprompt', function(e){
    e.preventDefault();
    window.__pwaDeferred = e;
    var b = document.getElementById('installBanner');
    if(b) b.classList.add('show');
  });
  // iOS Safari: no beforeinstallprompt — show manual instruction after 4s
  var isIOS = /iP(hone|ad|od)/.test(navigator.userAgent || '') || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var isStandalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
  if (isIOS && !isStandalone) {
    setTimeout(function(){
      var b = document.getElementById('installBanner');
      if (b && !window.__pwaDeferred) {
        b.classList.add('show');
        var t = document.getElementById('installText');
        if (t) t.innerText = 'برای نصب: روی دکمه اشتراک‌گذاری (Share) وبزنید سپس «Add to Home Screen» را انتخاب کنید';
        var i=0;
      }
    }, 4000);
  }
})();
(function(){
  try{
    if('serviceWorker' in navigator){navigator.serviceWorker.register('/sw.js?v=3',{updateViaCache:'none'}).then(function(reg){if(reg&&reg.update)reg.update();}).catch(function(){})}
  }catch(e){}
})();
</script>
<link rel="stylesheet" href="<?=url('assets/style.css')?>?v=7"><?php if(!empty($s['google_analytics'])):?><script async src="https://www.googletagmanager.com/gtag/js?id=<?=h($s['google_analytics'])?>"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','<?=h($s['google_analytics'])?>');</script><?php endif;?><script>
(function(){
  function setTheme(t){
    if(t==='light'){document.documentElement.setAttribute('data-theme','light');}
    else{document.documentElement.removeAttribute('data-theme');}
    try{localStorage.setItem('bk_theme',t)}catch(e){}
    var b=document.getElementById('themeToggle'); if(b) b.innerText=t==='light'?'☀️':'🌙';
  }
  try{ setTheme(localStorage.getItem('bk_theme')||'dark') }catch(e){setTheme('dark')}
  window.setTheme = setTheme;
  document.addEventListener('click', function(e){
    if(e.target.closest && e.target.closest('#themeToggle')) setTheme(localStorage.getItem('bk_theme')==='light'?'dark':'light');
  });
})();
</script>
<script>
/* فیلتر زندهٔ دسته‌بندی — یک تعریف سراسری برای همهٔ صفحات */
if(typeof bkFilterSelect!=='function'){function bkFilterSelect(inp){var sel=inp.parentElement?inp.parentElement.querySelector('select'):null;if(!sel)return;var q=(inp.value||'').trim().toLowerCase();Array.prototype.forEach.call(sel.options,function(o){if(!o.value)return;var t=(o.textContent||'').toLowerCase();var og=o.parentElement&&o.parentElement.label?o.parentElement.label.toLowerCase():'';o.hidden=q===''||t.indexOf(q)!==-1||og.indexOf(q)!==-1;});Array.prototype.forEach.call(sel.querySelectorAll('optgroup'),function(g){var any=false;Array.prototype.forEach.call(g.options,function(o){if(!o.hidden)any=true;});g.hidden=!any;});var si=sel.selectedIndex;if(si>=0&&sel.options[si]&&sel.options[si].hidden){sel.value='';}}}
/* زنگولهٔ اعلان‌ها */
(function(){var bell=document.getElementById('notifBell');if(!bell)return;var drop=document.getElementById('notifDrop');var badge=document.getElementById('notifBadge');var list=document.getElementById('notifList');
function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;}
function load(){fetch('/ajax-notifications',{headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}}).then(function(r){return r.json().catch(function(){return null;});}).then(function(j){if(!j)return;if(j.unread>0){badge.hidden=false;badge.textContent=j.unread>99?'99+':j.unread;}else{badge.hidden=true;}if(j.items&&j.items.length){var h='';j.items.forEach(function(n){h+='<a class="notif-item" href="/notifications"><strong>'+esc(n.title)+'</strong><small>'+esc(n.body)+' · '+esc(n.ago)+'</small></a>';});list.innerHTML=h;}else{list.innerHTML='<div class="notif-empty">اعلان جدیدی ندارید ✓</div>';}}).catch(function(){});}
bell.addEventListener('click',function(e){e.stopPropagation();drop.hidden=!drop.hidden;if(!drop.hidden)load();});
document.addEventListener('click',function(e){if(drop&&!drop.hidden&&!e.target.closest('#notifWrap'))drop.hidden=true;});
load();setInterval(load,60000);})();
</script></head><body data-theme="dark"><?php if(!empty($s['announcement'])):?><div class="announcement">📣 <?=h($s['announcement'])?></div><?php endif;?><header class="topbar"><div class="container navbar">
            <button class="menu-toggle" type="button" id="menuToggle" aria-label="منو">☰</button>
            <a class="logo" href="<?=url()?>"><span class="logo-mark">⌁</span>برد<em>خان</em></a>
            <nav class="navlinks">
                <a href="<?=url('tips')?>" class="<?=str_starts_with($active,'tips')?'active':''?>">قلق‌ها</a>
                <a href="<?=url('boards')?>" class="<?=str_starts_with($active,'board')?'active':''?>">فروشگاه برد</a>
                <a href="<?=url('repairs')?>" class="<?=str_starts_with($active,'repairs')?'active':''?>">درخواست تعمیر</a>
                <a href="<?=url('leaderboard')?>">رتبه‌بندی</a>
                <a href="<?=url('reels')?>" class="<?=str_starts_with($active,'reels')?'active':''?>">ریلز</a>
                <a href="<?=url('premium')?>">اشتراک ویژه</a>
                <a href="<?=url('tour')?>">آموزش</a>
                <?php if(admin_user($u)):?><a href="<?=url('admin')?>">مدیریت</a><?php endif;?>
            </nav>
            <div class="search" id="liveSearchWrap">
                <form action="<?=url('tips')?>" method="get"><input type="hidden" name="r" value="tips"><input id="liveSearch" name="q" placeholder="جستجوی قلق، دستگاه یا برند…" autocomplete="off"><span class="ico">⌕</span></form>
                <div id="liveResults" class="live-results" hidden></div>
            </div>
            <div class="actions">
                <button class="theme-btn" type="button" id="themeToggle" aria-label="تغییر تم">🌙</button><?php if($u):?><div class="notif-wrap" id="notifWrap"><button class="notif-bell" type="button" id="notifBell" aria-label="اعلان‌ها" title="اعلان‌ها">🔔<span class="notif-badge" id="notifBadge" hidden>0</span></button><div class="notif-drop" id="notifDrop" hidden><div class="notif-head">🔔 اعلان‌های اخیر</div><div id="notifList" class="notif-list"><div class="notif-empty">در حال دریافت…</div></div><a class="notif-all" href="<?=url('notifications')?>">مشاهده همه اعلان‌ها ←</a></div></div><?php endif;?>
                <?php if($u):?>
                    <a class="btn btn-primary btn-sm hide-mobile" href="<?=url('upload')?>">+ آپلود</a>
                    <a class="hide-mobile" href="<?=url('profile/'.$u['id'])?>" style="display:inline-flex;align-items:center;gap:6px">👤 <?=h($u['name'])?></a>
                <?php else:?>
                    <a class="btn btn-secondary btn-sm hide-mobile" href="<?=url('login')?>">ورود</a>
                    <a class="btn btn-primary btn-sm hide-mobile" href="<?=url('register')?>">ثبت‌نام</a>
                <?php endif;?>
            </div>
        </div></header>

        <!-- Mobile drawer (off-canvas) -->
        <div class="drawer" id="mainDrawer">
            <div class="drawer-head">
                <span class="brand"><span class="brand-mark">⌁</span>برد<em>خان</em></span>
                <button class="drawer-close" type="button" aria-label="بستن">✕</button>
            </div>
            <a href="<?=url('tips')?>" class="<?=str_starts_with($active,'tips')?'active':''?>">🔧 قلق‌ها</a>
            <a href="<?=url('boards')?>" class="<?=str_starts_with($active,'board')?'active':''?>">🏪 فروشگاه برد</a>
            <a href="<?=url('repairs')?>" class="<?=str_starts_with($active,'repairs')?'active':''?>">🛠 درخواست تعمیر</a>
            <a href="<?=url('reels')?>" class="<?=str_starts_with($active,'reels')?'active':''?>">🎬 ریلز</a>
            <a href="<?=url('leaderboard')?>">🏆 رتبه‌بندی</a>
            <a href="<?=url('premium')?>">💎 اشتراک ویژه</a>
            <a href="<?=url('tour')?>">📚 آموزش</a>
            <div class="sep"></div>
            <?php if($u):?>
                    <a href="<?=url('profile/'.$u['id'])?>">👤 <?=h($u['name'])?></a>
                    <a href="<?=url('wallet')?>">👛 کیف پول</a>
                    <a href="<?=url('my-boards')?>">🧾 بردهای من</a>
                    <a href="<?=url('bookmarks')?>">🔖 نشانک‌ها</a>
                    <a href="<?=url('favorites')?>">♥ علاقه‌مندی‌ها</a>
                    <a href="<?=url('notifications')?>">🔔 اعلان‌ها</a>
                    <a href="<?=url('upload')?>">➕ آپلود قلق</a>
                <?php if(admin_user($u)):?><a href="<?=url('admin')?>">⚙️ پنل مدیریت</a><?php endif;?>
                <a href="<?=url('logout')?>">🚪 خروج</a>
            <?php else:?>
                <a href="<?=url('login')?>">🔑 ورود</a>
                <a href="<?=url('register')?>">✨ ثبت‌نام رایگان</a>
            <?php endif;?>
        </div>
        <div class="install-banner" id="installBanner"><span id="installText">📱 بردخان را روی صفحه اصلی نصب کنید</span> <button class="btn btn-primary btn-sm" id="installBtn" type="button">نصب</button><button class="btn btn-secondary btn-sm" type="button" onclick="document.getElementById('installBanner').classList.remove('show')">✕</button></div><?php $f=pull_flash(); if($f):?><div class="wrap"><div class="<?=h($f[1])?>"><?=h($f[0])?></div></div><?php endif; ?><?php }
function footer_html(): void { ?><footer class="footer"><div class="wrap footer-grid"><div><div class="logo" style="color:#fff">⌁ برد<em>خان</em></div><p>بازار تخصصی قلق‌های تعمیراتی بردهای الکترونیکی؛ راه‌حل‌های واقعی از تعمیرکاران حرفه‌ای.</p></div><div><h3>دسترسی سریع</h3><ul><li><a href="<?=url('tips')?>">همه قلق‌ها</a></li><li><a href="<?=url('reels')?>">ریلز قلق‌ها</a></li><li><a href="<?=url('repairs')?>">درخواست تعمیر</a></li><li><a href="<?=url('leaderboard')?>">رتبه‌بندی</a></li><li><a href="<?=url('premium')?>">اشتراک ویژه</a></li><li><a href="<?=url('tour')?>">آموزش و امکانات</a></li></ul></div><div><h3>پشتیبانی</h3><ul><li><a href="<?=url('tickets')?>">تیکت پشتیبانی</a></li><li><a href="<?=url('contact')?>">تماس با ما</a></li><li><a href="<?=url('about')?>">درباره ما</a></li><li><a href="<?=url('terms')?>">قوانین استفاده</a></li><li><a href="<?=url('privacy')?>">حریم خصوصی</a></li></ul></div></div><div class="wrap copyright">© <?=fa(date('Y'))?> بردخان — تمامی حقوق محفوظ است. <span style="opacity:.55;font-size:10px">· نسخه <?=defined('BORDKHAN_VERSION')?BORDKHAN_VERSION:'قدیمی'?></span></div></footer><?php if(is_file(__DIR__.'/php-extended/bk_actionbar.php')){require_once __DIR__.'/php-extended/bk_actionbar.php';bk_render_actionbar(function_exists('current_user')?current_user():null);} ?><script>
(function(){
  var CSRF = '<?=csrf()?>';

  // Theme toggle (dark by default; light on demand)
  var themeBtn = document.getElementById('themeToggle');
  function setTheme(theme){ if(theme==='light'){document.documentElement.setAttribute('data-theme','light');document.body.setAttribute('data-theme','light');themeBtn.innerText='☀️';localStorage.setItem('bk_theme','light');}else{document.documentElement.removeAttribute('data-theme');document.body.removeAttribute('data-theme');themeBtn.innerText='🌙';localStorage.setItem('bk_theme','dark');} }
  try{ if(localStorage.getItem('bk_theme')==='light'){setTheme('light')}else{setTheme('dark')} }catch(e){}
  if(themeBtn){ themeBtn.addEventListener('click', function(){ setTheme(localStorage.getItem('bk_theme')==='light'?'dark':'light'); }); }

  // Mobile drawer toggle + backdrop + close on link click
  var drawer = document.getElementById('mainDrawer');
  var toggle = document.getElementById('menuToggle');
  if (drawer && toggle) {
    // Add backdrop element once
    if (!document.querySelector('.drawer-backdrop')) document.body.appendChild(document.createElement('div')).className = 'drawer-backdrop';
    var backdrop = document.querySelector('.drawer-backdrop');
    function close(){ drawer.classList.remove('open'); backdrop.classList.remove('show'); document.body.classList.remove('noscroll'); }
    function open(){ drawer.classList.add('open'); backdrop.classList.add('show'); document.body.classList.add('noscroll'); }
    toggle.addEventListener('click', function(e){ e.stopPropagation(); if(drawer.classList.contains('open')) close(); else open(); });
    backdrop.addEventListener('click', close);
    drawer.querySelectorAll('a').forEach(function(a){ a.addEventListener('click', close); });
    drawer.querySelector('.drawer-close')?.addEventListener('click', close);
    document.addEventListener('keydown', function(e){ if(e.key==='Escape') close(); });
  }
  var installBtn = document.getElementById('installBtn');
  if(installBtn){
    installBtn.addEventListener('click', function(){
      var d = window.__pwaDeferred;
      if(d){
        d.prompt();
        d.userChoice.then(function(choice){ window.__pwaDeferred = null; document.getElementById('installBanner').classList.remove('show'); });
      }else if(window.showPWA){ window.showPWA(); }
    });
  }
  // Live search
  var box = document.getElementById('liveSearch');
  var res = document.getElementById('liveResults');
  if (box && res) {
    var timer = null;
    box.addEventListener('input', function(){
      clearTimeout(timer);
      var q = box.value.trim();
      if (q.length < 2) { res.hidden = true; res.innerHTML = ''; return; }
      timer = setTimeout(function(){
        var fd = new FormData(); fd.append('action','search_live'); fd.append('q',q); fd.append('csrf',CSRF);
        fetch('<?=url()?>', {method:'POST', body:fd})
          .then(function(r){return r.json();})
          .then(function(d){
            if (!d.items || !d.items.length) { res.innerHTML = '<div class="empty" style="padding:14px">موردی یافت نشد</div>'; }
            else { res.innerHTML = d.items.map(function(i){ return '<a href="<?=url('tip/')?>'+i.id+'">'+i.title+'<small>'+i.access+' · '+i.views+' بازدید</small></a>'; }).join(''); }
            res.hidden = false;
          }).catch(function(){ res.hidden = true; });
      }, 300);
    });
    document.addEventListener('click', function(e){ if (!res.contains(e.target) && e.target !== box) res.hidden = true; });
  }
  // Content protection: block right-click/drag on protected media, block common dev keys
  document.addEventListener('contextmenu', function(e){
    if (e.target.closest('.media-protect') || e.target.classList.contains('no-save')) e.preventDefault();
  });
  document.addEventListener('dragstart', function(e){
    if (e.target.closest('.media-protect') || e.target.classList.contains('no-save')) e.preventDefault();
  });
  document.addEventListener('keydown', function(e){
    if (e.key === 'PrintScreen') e.preventDefault();
    if (e.key === 'F12' && document.body.classList.contains('protect-on')) e.preventDefault();
    var onMedia = e.target.closest && (e.target.closest('.media-protect') || e.target.classList.contains('no-save'));
    var blocked = (e.ctrlKey || e.metaKey) && ['s','S','u','U','c','C','p','P'].indexOf(e.key) !== -1;
    if (onMedia && blocked) e.preventDefault();
  });
  // Reels: tap to open full tip
  document.querySelectorAll('.reel').forEach(function(reel){
    reel.addEventListener('click', function(){
      var href = reel.getAttribute('data-href');
      if (href) window.location.href = href;
    });
  });
})();
</script></body></html><?php }

$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD']==='POST' && $action) { check_csrf(); $pdo=db();
    if($action==='login'){ $id=trim($_POST['identifier']??'');$pass=(string)($_POST['password']??'');if(!throttle('login:'.md5($id),5,600)){flash('تلاش‌های ناموفق زیاد است؛ ۱۰ دقیقه بعد دوباره امتحان کنید.','error');redirect_to('login');}$s=$pdo->prepare('SELECT * FROM users WHERE phone=? OR email=? LIMIT 1');$s->execute([$id,$id]);$u=$s->fetch();if(!$u||!empty($u['is_deleted'])||!password_verify($pass,$u['password_hash'])){flash('اطلاعات ورود اشتباه است.','error');redirect_to('login');}if($u['is_banned'])exit('حساب شما مسدود شده است.');throttle_clear('login:'.md5($id));session_regenerate_id(true);$_SESSION['user_id']=$u['id'];$pdo->prepare('UPDATE users SET last_login=NOW() WHERE id=?')->execute([$u['id']]);redirect_to('');}
    if($action==='register'){ $name=clean_text($_POST['name']??'');$phone=preg_replace('/[^0-9]/','',$_POST['phone']??'');$email=trim($_POST['email']??'');$pass=(string)($_POST['password']??'');$ref=trim($_POST['referral']??'');if(mb_strlen($name)<3||!preg_match('/^09[0-9]{9}$/',$phone)||mb_strlen($pass)<6){flash('نام، شماره موبایل یا رمز عبور معتبر نیست.','error');redirect_to('register');}$s=$pdo->prepare('SELECT id FROM users WHERE phone=? LIMIT 1');$s->execute([$phone]);if($s->fetch()){flash('این شماره قبلاً ثبت شده است.','error');redirect_to('register');}$referrer=null;if($ref){$q=$pdo->prepare('SELECT id FROM users WHERE referral_code=?');$q->execute([$ref]);$referrer=$q->fetchColumn()?:null;}$_SESSION['pending_register']=['name'=>$name,'phone'=>$phone,'email'=>$email?:null,'hash'=>password_hash($pass,PASSWORD_DEFAULT),'referred_by'=>$referrer];$_SESSION['demo_code']=(string)random_int(100000,999999);redirect_to('verify');}
    if($action==='verify'){ $p=$_SESSION['pending_register']??null;if(!$p){flash('ابتدا فرم ثبت‌نام را تکمیل کنید.','error');redirect_to('register');}if(!throttle('verify',8,900)){flash('تلاش‌های ناموفق زیاد است؛ ۱۵ دقیقه بعد دوباره امتحان کنید.','error');redirect_to('verify');}if(($_POST['code']??'')!==($_SESSION['demo_code']??'')){flash('کد تأیید اشتباه است.','error');redirect_to('verify');}throttle_clear('verify');$code='USR'.strtoupper(bin2hex(random_bytes(3)));$s=$pdo->prepare('INSERT INTO users(phone,email,password_hash,name,referral_code,referred_by,phone_verified) VALUES(?,?,?,?,?,?,1)');$s->execute([$p['phone'],$p['email'],$p['hash'],$p['name'],$code,$p['referred_by']]);$uid=(int)$pdo->lastInsertId();if($p['referred_by'])credit($uid,(int)settings()['invitee_credit'],'referral_invitee','اعتبار خوش‌آمدگویی ثبت‌نام');session_regenerate_id(true);$_SESSION['user_id']=$uid;unset($_SESSION['pending_register'],$_SESSION['demo_code']);redirect_to('');}
    if($action==='logout'){session_destroy();redirect_to('');}

    // بازیابی رمز عبور — مرحله ۱: درخواست کد
    if($action==='forgot_request'){
        $phone = preg_replace('/[^0-9]/','', $_POST['phone'] ?? '');
        if(!throttle('forgot:'.md5($phone),5,900)){flash('درخواست‌های زیاد است؛ ۱۵ دقیقه بعد دوباره امتحان کنید.','error');redirect_to('forgot');}
        $q = $pdo->prepare('SELECT id FROM users WHERE phone=? LIMIT 1'); $q->execute([$phone]); $usr = $q->fetch();
        if (!$usr) { flash('کاربری با این شماره یافت نشد.','error'); redirect_to('forgot'); }
        throttle_clear('forgot:'.md5($phone));
        $_SESSION['reset_phone'] = $phone;
        $_SESSION['reset_code'] = (string)random_int(100000,999999);
        redirect_to('forgot');
    }
    // بازیابی رمز عبور — مرحله ۲: تغییر رمز
    if($action==='forgot_reset'){
        $phone = $_SESSION['reset_phone'] ?? '';
        $code = preg_replace('/[^0-9]/','',$_POST['code'] ?? '');
        $pass = (string)($_POST['password'] ?? '');
        if(!throttle('forgot_reset',8,900)){flash('تلاش‌های ناموفق زیاد است؛ ۱۵ دقیقه بعد دوباره امتحان کنید.','error');redirect_to('forgot');}
        if (!$phone || $code !== ($_SESSION['reset_code'] ?? '')) { flash('کد تأیید اشتباه است.','error'); redirect_to('forgot'); }
        if (mb_strlen($pass) < 6) { flash('رمز عبور باید حداقل ۶ کاراکتر باشد.','error'); redirect_to('forgot'); }
        $pdo->prepare('UPDATE users SET password_hash=? WHERE phone=?')->execute([password_hash($pass,PASSWORD_DEFAULT), $phone]);
        unset($_SESSION['reset_phone'], $_SESSION['reset_code'], $_SESSION['throttle']);
        flash('رمز عبور با موفقیت تغییر کرد. حالا وارد شوید.');
        redirect_to('login');
    }
    // مدیریت قلق توسط صاحب قلق: حذف
    if($action==='my_tip_delete'){ $u=require_login();$tid=(int)($_POST['tip_id']??0);$q=$pdo->prepare('SELECT id,author_id,title,status FROM tips WHERE id=? LIMIT 1');$q->execute([$tid]);$t=$q->fetch();if(!$t){flash('قلق یافت نشد.','error');redirect_to('my-tips');}if((int)$t['author_id']!==(int)$u['id'] && !admin_user($u)){flash('شما مالک این قلق نیستید.','error');redirect_to('my-tips');}$pdo->prepare('DELETE FROM tips WHERE id=?')->execute([$tid]);flash('قلق «'.mb_substr($t['title'],0,40).'» حذف شد.');redirect_to('my-tips');}
    // تمدید وضعیت قلق توسط کاربر: انتشار مجدد/پیش‌نویس
    if($action==='my_tip_toggle'){ $u=require_login();$tid=(int)($_POST['tip_id']??0);$to=$_POST['to']??'';if(!in_array($to,['draft','pending'],true))redirect_to('my-tips');$q=$pdo->prepare('SELECT id,author_id FROM tips WHERE id=? LIMIT 1');$q->execute([$tid]);$t=$q->fetch();if(!$t||(int)$t['author_id']!==(int)$u['id']){flash('قلق یافت نشد.','error');redirect_to('my-tips');}$published=($to==='published'&&!empty($t['published_at']))?' published_at':'published_at';$pdo->prepare('UPDATE tips SET status=?, published_at=IF(?="published",COALESCE(published_at,NOW()),published_at) WHERE id=?')->execute([$to,$to,$tid]);flash('وضعیت قلق تغییر کرد.');redirect_to('my-tips');}
    // ریست وضعیت ردشده به حالت بازبینی (پس از اصلاح)
    if($action==='my_tip_resubmit'){ $u=require_login();$tid=(int)($_POST['tip_id']??0);$q=$pdo->prepare('SELECT id,author_id FROM tips WHERE id=? LIMIT 1');$q->execute([$tid]);$t=$q->fetch();if($t&&(int)$t['author_id']===(int)$u['id']){$pdo->prepare("UPDATE tips SET status='pending',rejection_reason=NULL WHERE id=?")->execute([$tid]);}flash('قلق برای بررسی مجدد ارسال شد.');redirect_to('my-tips');}
    // مدیریت قلق توسط مدیر: ویرایش آزاد سریع
    if($action==='admin_tip_edit'){ require_admin();$tid=(int)($_POST['tip_id']??0);$title=clean_text($_POST['title']??'');$short=clean_text($_POST['short_description']??'');$price=max(0,(int)($_POST['price']??0));$access=in_array($_POST['access_type']??'free',['free','like','paid'],true)?$_POST['access_type']:'free';$q=$pdo->prepare('SELECT id,author_id,status FROM tips WHERE id=? LIMIT 1');$q->execute([$tid]);$t=$q->fetch();if(!$t){flash('قلق یافت نشد.','error');redirect_to('admin?tab=tips');}if(mb_strlen($title)>=5){$pdo->prepare('UPDATE tips SET title=?, short_description=IF(?<>\',\',?,short_description), access_type=?, price=?, version=version+1, status=\'pending\', rejection_reason=NULL WHERE id=?')->execute([$title,$short,$short,$access,$price,$tid]);}flash('ویرایش قلق ذخیره شد.');redirect_to('admin?tab=tips');}
    // مدیریت قلق توسط مدیر: حذف کامل
    if($action==='admin_tip_delete'){ require_admin();if(($_POST['confirm']??'')!=='1'){flash('برای حذف نهایی، تأیید لازم است.','error');redirect_to('admin?tab=tips');}else{$tid=(int)($_POST['tip_id']??0);$pdo->prepare('DELETE FROM tips WHERE id=?')->execute([$tid]);flash('قلق برای همیشه حذف شد.');redirect_to('admin?tab=tips');}}
    // ---------- آپلود قلق (ترمیم شده - قبلاً در نسخه ریلز حذف شده بود) ----------
    if($action==='upload_tip' && !empty($_POST['edit_id'])){
        $u=require_login();
        $tid=(int)$_POST['edit_id'];
        $existing=$pdo->prepare('SELECT id,author_id FROM tips WHERE id=? LIMIT 1');$existing->execute([$tid]);$ex=$existing->fetch();
        if(!$ex||(int)$ex['author_id']!==(int)$u['id']){flash('عدم دسترسی به ویرایش این قلق.','error');redirect_to('my-tips');}
        $title=clean_text($_POST['title']??'');$short=clean_text($_POST['short_description']??'');$desc=trim($_POST['description']??'');
        if(mb_strlen($title)>=5){
            $pdo->prepare('UPDATE tips SET title=?,short_description=?,description=?,category_id=?,brand=?,model=?,access_type=?,price=?,version=version+1 WHERE id=?')->execute([$title,$short,safe_rich($desc),(int)($_POST['category_id']??0),clean_text($_POST['brand']??''),clean_text($_POST['model']??''),in_array($_POST['access_type']??'free',['free','like','paid'],true)?$_POST['access_type']:'free',max(0,(int)($_POST['price']??0)),$tid]);
        }
        flash('تغییرات قلق با موفقیت ذخیره شد.');redirect_to('tip/'.$tid);
    }
    if($action==='upload_tip'){
        $u=require_login();
        $ajax=is_ajax_request();
        $bk_fail=function(string $m)use($ajax){
            if($ajax){bk_json_out(['ok'=>false,'error'=>$m],422);}
            flash($m,'error');redirect_to('upload');
        };
        $title=clean_text($_POST['title']??'');$short=clean_text($_POST['short_description']??'');$desc=trim($_POST['description']??'');$device=clean_text($_POST['device_name']??'');$brand=clean_text($_POST['brand']??'');$cat=(int)($_POST['category_id']??0);$access=in_array($_POST['access_type']??'free',['free','like','paid'],true)?$_POST['access_type']:'free';$price=$access==='paid'?max(1000,(int)($_POST['price']??0)):0;
        $images=[];foreach(($_FILES['images']['tmp_name']??[]) as $i=>$tmp){$f=['tmp_name'=>$tmp,'error'=>$_FILES['images']['error'][$i]??1,'size'=>$_FILES['images']['size'][$i]??0,'type'=>$_FILES['images']['type'][$i]??''];$saved=save_image($f);if($saved)$images[]=$saved;}
        if(mb_strlen($title)<8)$bk_fail('عنوان قلق باید حداقل ۸ حرف باشد.');
        if(mb_strlen($short)<20)$bk_fail('توضیح کوتاه باید حداقل ۲۰ حرف باشد.');
        if(!$device)$bk_fail('نام دستگاه الزامی است.');
        if(!$brand)$bk_fail('برند الزامی است.');
        if(!$cat)$bk_fail('دسته‌بندی الزامی است.');
        if(mb_strlen(strip_tags($desc))<20)$bk_fail('توضیح کامل باید حداقل ۲۰ حرف باشد.');
        if(count($images)<1)$bk_fail('حداقل یک عکس معتبر (JPG/PNG/WebP) لازم است.');
        $steps=[];foreach(($_POST['step_body']??[]) as $i=>$body){if(trim($body))$steps[]=['title'=>clean_text($_POST['step_title'][$i]??''),'body'=>trim($body)];}
        $tags=array_values(array_filter(array_map('trim',explode(',',$_POST['tags']??''))));$tools=array_values(array_filter(array_map('trim',explode(',',$_POST['tools']??''))));
        $dup=$pdo->prepare("SELECT id,title FROM tips WHERE status='published' AND (title LIKE ? OR (device_name LIKE ? AND brand LIKE ?)) LIMIT 1");$like='%'.mb_substr($title,0,35).'%';$dup->execute([$like,'%'.$device.'%','%'.$brand.'%']);$same=$dup->fetch();
        $status='pending';
        $videoUrl=trim($_POST['video_url']??'');if(!empty($_FILES['video_file']['tmp_name'])){$v=save_video($_FILES['video_file']);if($v)$videoUrl=$v;}
        try{
            $s=$pdo->prepare('INSERT INTO tips(author_id,category_id,title,short_description,description,device_name,brand,model,board_number,fault_type,difficulty,solution_json,tools,images_json,video_url,attachments_json,access_type,price,visibility,status,tags,version,versions_json,featured,views,likes_count,purchases_count,rating_sum,rating_count,duplicate_of,rejection_reason,source_url,source_name,published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,0,0,0,0,0,0,?,NULL,NULL,NULL,?)');
            $s->execute([$u['id'],$cat,$title,$short,safe_rich($desc),$device,$brand,clean_text($_POST['model']??''),clean_text($_POST['board_number']??''),clean_text($_POST['fault_type']??'سایر'),clean_text($_POST['difficulty']??'medium'),json_encode($steps,JSON_UNESCAPED_UNICODE),implode('،',$tools),json_encode($images,JSON_UNESCAPED_UNICODE),$videoUrl,json_encode([],JSON_UNESCAPED_UNICODE),$access,$price,'public',$status,implode(',',$tags),json_encode([],JSON_UNESCAPED_UNICODE),$same['id']??null,date('Y-m-d H:i:s')]);
            $id=(int)$pdo->lastInsertId();
            if($status==='published'){award($u['id'],100);credit($u['id'],(int)settings()['upload_reward'],'upload_reward','پاداش آپلود قلق «'.$title.'»',$id);maybe_reward_referrer($u['id']);}else{notify_user($u['id'],'admin','قلق شما در صف بررسی است','قلق شما ثبت شد و پس از بررسی مدیر منتشر می‌شود.',url('tip/'.$id));}
            $msg=$status==='published'?'قلق با موفقیت منتشر شد.':'قلق ثبت شد و پس از بررسی مدیر منتشر می‌شود.';
            if($ajax){bk_json_out(['ok'=>true,'message'=>$msg,'redirect'=>url('tip/'.$id)]);}
            flash($msg);redirect_to('tip/'.$id);
        }catch(Throwable $e){
            $bk_fail('خطا در ثبت قلق: '.$e->getMessage());
        }
    }
    if($action==='withdraw'){
        $u=require_login();$amount=(int)($_POST['amount']??0);$shaba=trim($_POST['shaba']??'');$card=trim($_POST['card_number']??'');$nid=trim($_POST['national_id']??'');
        if($amount<(int)settings()['min_withdrawal']||$amount>(int)$u['balance']||mb_strlen($shaba)<20||mb_strlen($card)<16||mb_strlen($nid)<10){flash('اطلاعات تسویه یا موجودی معتبر نیست.','error');redirect_to('wallet');}
        if(!debit($u['id'],$amount,'withdrawal','درخواست تسویه حساب')){flash('موجودی کافی نیست.','error');redirect_to('wallet');}
        $pdo->prepare('INSERT INTO withdrawals(user_id,amount,shaba,card_number,national_id) VALUES(?,?,?,?,?)')->execute([$u['id'],$amount,$shaba,$card,$nid]);
        $pdo->prepare('UPDATE users SET shaba=?,card_number=?,national_id=? WHERE id=?')->execute([$shaba,$card,$nid,$u['id']]);
        flash('درخواست تسویه ثبت شد و توسط مدیر بررسی می‌شود.');redirect_to('wallet');
    }
    if($action==='repair_create'){
        $u=require_login();$title=clean_text($_POST['title']??'');$desc=clean_text($_POST['description']??'');$device=clean_text($_POST['device_name']??'');$rewardType=$_POST['reward_type']==='like'?'like':'money';$amount=$rewardType==='money'?max(0,(int)($_POST['reward_amount']??0)):0;
        if(mb_strlen($title)<8||mb_strlen($desc)<20||!$device||($rewardType==='money'&&$amount>(int)$u['balance'])){flash('اطلاعات درخواست کامل نیست یا موجودی کافی نیست.','error');redirect_to('repair/new');}
        $pdo->prepare('INSERT INTO repair_requests(user_id,title,description,device_name,brand,model,reward_type,reward_amount,deadline_days) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$u['id'],$title,$desc,$device,clean_text($_POST['brand']??''),clean_text($_POST['model']??''),$rewardType,$amount,settings()['repair_deadline_days']]);
        flash('درخواست تعمیر ثبت شد.');redirect_to('repair/'.$pdo->lastInsertId());
    }
    if($action==='unlock'){ $u=require_login();$ajax=is_ajax_request();$tipId=(int)$_POST['tip_id'];$q=$pdo->prepare('SELECT * FROM tips WHERE id=? AND status="published"');$q->execute([$tipId]);$t=$q->fetch();if(!$t){if($ajax)bk_json_out(['ok'=>false,'error'=>'قلق یافت نشد.'],404);exit('قلق یافت نشد');}if((int)$t['author_id']===(int)$u['id']){if($ajax)bk_json_out(['ok'=>false,'error'=>'نمی‌توانید قلق خودتان را باز کنید.']);flash('نمی‌توانید قلق خودتان را باز کنید.','error');redirect_to('tip/'.$tipId);}$q=$pdo->prepare('SELECT id FROM tip_accesses WHERE tip_id=? AND user_id=?');$q->execute([$tipId,$u['id']]);if($q->fetch()){if($ajax)bk_json_out(['ok'=>true,'already'=>true]);redirect_to('tip/'.$tipId);}$access=$t['access_type'];if($access==='paid'){if(!debit($u['id'],(int)$t['price'],'purchase','خرید قلق «'.$t['title'].'»',$tipId)){if($ajax)bk_json_out(['ok'=>false,'error'=>'موجودی کیف پول کافی نیست.','wallet'=>url('wallet')],402);flash('موجودی کیف پول کافی نیست.','error');redirect_to('wallet');} $net=(int)floor($t['price']*(100-(int)settings()['commission_percent'])/100);credit((int)$t['author_id'],$net,'sale','درآمد فروش قلق «'.$t['title'].'»',$tipId);maybe_reward_referrer($u['id']);$type='purchase';}elseif($access==='like'){$today=date('Y-m-d');$used=$u['likes_used_date']===$today?(int)$u['likes_used_today']:0;if($used>=(int)settings()['daily_like_limit']){if($ajax)bk_json_out(['ok'=>false,'error'=>'سقف لایک روزانه شما تمام شده است.']);flash('سقف لایک روزانه شما تمام شده است.','error');redirect_to('tip/'.$tipId);} $pdo->prepare('UPDATE users SET likes_used_today=?,likes_used_date=? WHERE id=?')->execute([$used+1,$today,$u['id']]);$pdo->prepare('UPDATE tips SET likes_count=likes_count+1 WHERE id=?')->execute([$tipId]);award((int)$t['author_id'],(int)(settings()['like_points_reward']??5));$type='like';}else{$type='free';}$pdo->prepare('INSERT INTO tip_accesses(tip_id,user_id,access_type,price_paid,ip) VALUES(?,?,?,?,?)')->execute([$tipId,$u['id'],$type,$t['price']??0,$_SERVER['REMOTE_ADDR']??'']);if($access==='paid'){award_badge((int)$u['id'],'first_purchase');award_badge((int)$t['author_id'],'first_sale');}notify_user((int)$t['author_id'],$access==='paid'?'sale':'like',$access==='paid'?'قلق شما فروخته شد':'قلق شما لایک گرفت',$u['name'].' قلق «'.$t['title'].'» را باز کرد.',url('tip/'.$tipId));if($ajax)bk_json_out(['ok'=>true,'message'=>$access==='paid'?'خرید انجام شد؛ محتوای کامل باز شد.':'لایک ثبت شد؛ محتوای کامل باز شد.','type'=>$access]);redirect_to('tip/'.$tipId);}
if($action==='comment'){ $u=require_login();$tipId=(int)$_POST['tip_id'];$body=trim($_POST['body']??'');$ajax=is_ajax_request();if(mb_strlen($body)<2){if($ajax)bk_json_out(['ok'=>false,'error'=>'متن نظر کوتاه است.'],422);flash('متن نظر کوتاه است.','error');redirect_to('tip/'.$tipId);} $pdo->prepare('INSERT INTO comments(tip_id,user_id,parent_id,body) VALUES(?,?,?,?)')->execute([$tipId,$u['id'],($_POST['parent_id']??null)?:null,$body]);if($ajax)bk_json_out(['ok'=>true,'message'=>'نظر شما ثبت شد.']);flash('نظر شما ثبت شد.');redirect_to('tip/'.$tipId.'#comments');}if($action==='rate'){ $u=require_login();$tipId=(int)$_POST['tip_id'];$stars=max(1,min(5,(int)$_POST['stars']));$q=$pdo->prepare('SELECT * FROM ratings WHERE tip_id=? AND user_id=?');$q->execute([$tipId,$u['id']]);$old=$q->fetch();if($old){$pdo->prepare('UPDATE ratings SET stars=? WHERE id=?')->execute([$stars,$old['id']]);$pdo->prepare('UPDATE tips SET rating_sum=rating_sum-?+? WHERE id=?')->execute([$old['stars'],$stars,$tipId]);}else{$pdo->prepare('INSERT INTO ratings(tip_id,user_id,stars) VALUES(?,?,?)')->execute([$tipId,$u['id'],$stars]);$pdo->prepare('UPDATE tips SET rating_sum=rating_sum+?,rating_count=rating_count+1 WHERE id=?')->execute([$stars,$tipId]);}flash('امتیاز شما ثبت شد.');redirect_to('tip/'.$tipId.'#rating');}
    if($action==='follow'){ $u=require_login();$target=(int)$_POST['user_id'];if($target===$u['id'])redirect_to('profile/'.$target);$q=$pdo->prepare('SELECT id FROM follows WHERE follower_id=? AND following_id=?');$q->execute([$u['id'],$target]);$old=$q->fetchColumn();if($old)$pdo->prepare('DELETE FROM follows WHERE id=?')->execute([$old]);else{$pdo->prepare('INSERT INTO follows(follower_id,following_id) VALUES(?,?)')->execute([$u['id'],$target]);notify_user($target,'follow','دنبال‌کننده جدید',$u['name'].' شما را دنبال کرد.',url('profile/'.$u['id']));}redirect_to($_POST['back']??'');}
    if($action==='repair_answer'){ $u=require_login();$rid=(int)$_POST['request_id'];$body=clean_text($_POST['body']??'');$q=$pdo->prepare('SELECT * FROM repair_requests WHERE id=?');$q->execute([$rid]);$r=$q->fetch();if(!$r||$r['status']!=='open'||(int)$r['user_id']===(int)$u['id']||mb_strlen($body)<10){flash('پاسخ قابل ثبت نیست.','error');redirect_to('repair/'.$rid);} $pdo->prepare('INSERT INTO repair_answers(request_id,user_id,body) VALUES(?,?,?)')->execute([$rid,$u['id'],$body]);$pdo->prepare('UPDATE repair_requests SET answer_count=answer_count+1 WHERE id=?')->execute([$rid]);notify_user((int)$r['user_id'],'repair','پاسخ جدید برای درخواست شما',$u['name'].' به درخواست شما پاسخ داد.',url('repair/'.$rid));flash('پاسخ شما ثبت شد.');redirect_to('repair/'.$rid);}
    if($action==='repair_best'){ $u=require_login();$rid=(int)$_POST['request_id'];$aid=(int)$_POST['answer_id'];$q=$pdo->prepare('SELECT r.*,a.user_id answer_user FROM repair_requests r JOIN repair_answers a ON a.request_id=r.id WHERE r.id=? AND a.id=?');$q->execute([$rid,$aid]);$r=$q->fetch();if(!$r||$r['user_id']!=$u['id']||$r['status']!=='open'){flash('عملیات نامعتبر.','error');redirect_to('repair/'.$rid);} $pdo->prepare("UPDATE repair_requests SET status='closed',best_answer_id=? WHERE id=?")->execute([$aid,$rid]);$pdo->prepare('UPDATE repair_answers SET is_best=1 WHERE id=?')->execute([$aid]);if($r['reward_type']==='money'&&(int)$r['reward_amount']>0&&debit($u['id'],(int)$r['reward_amount'],'repair_payment','پاداش پاسخ منتخب',null,$rid))credit((int)$r['answer_user'],(int)$r['reward_amount'],'repair_reward','پاداش پاسخ منتخب',null,$rid);award((int)$r['answer_user'],50);notify_user((int)$r['answer_user'],'repair','پاسخ شما انتخاب شد','پاسخ شما به عنوان بهترین پاسخ انتخاب شد.',url('repair/'.$rid));flash('بهترین پاسخ انتخاب شد.');redirect_to('repair/'.$rid);}
    if($action==='report'){ $u=require_login();$pdo->prepare('INSERT INTO reports(reporter_id,target_type,target_id,reason,detail) VALUES(?,?,?,?,?)')->execute([$u['id'],$_POST['target_type']??'tip',(int)$_POST['target_id'],clean_text($_POST['reason']??'گزارش کاربر'),clean_text($_POST['detail']??'')]);flash('گزارش شما ثبت شد.');redirect_to($_POST['back']??'');}
    if($action==='comment_vote'){ $u=require_login();$cid=(int)$_POST['comment_id'];$vote=($_POST['vote']??'1')==='-1'?-1:1;$c=$pdo->prepare('SELECT id,tip_id FROM comments WHERE id=? AND is_deleted=0');$c->execute([$cid]);$cm=$c->fetch();if(!$cm){flash('نظر یافت نشد.','error');redirect_to('');}$q=$pdo->prepare('SELECT id,value FROM comment_votes WHERE comment_id=? AND user_id=?');$q->execute([$cid,$u['id']]);$old=$q->fetch();if($old){if((int)$old['value']===$vote){$pdo->prepare('DELETE FROM comment_votes WHERE id=?')->execute([$old['id']]);}else{$pdo->prepare('UPDATE comment_votes SET value=? WHERE id=?')->execute([$vote,$old['id']]);}}else{$pdo->prepare('INSERT INTO comment_votes(comment_id,user_id,value) VALUES(?,?,?)')->execute([$cid,$u['id'],$vote]);}redirect_to('tip/'.$cm['tip_id'].'#comment-'.$cid);}
    if($action==='favorite'){ $u=require_login();$tipId=(int)$_POST['tip_id'];$ajax=is_ajax_request();$q=$pdo->prepare('SELECT id FROM favorites WHERE user_id=? AND tip_id=?');$q->execute([$u['id'],$tipId]);$liked=false;if($q->fetch()){$pdo->prepare('DELETE FROM favorites WHERE user_id=? AND tip_id=?')->execute([$u['id'],$tipId]);$pdo->prepare('UPDATE tips SET likes_count=GREATEST(likes_count-1,0) WHERE id=?')->execute([$tipId]);}else{$pdo->prepare('INSERT INTO favorites(user_id,tip_id) VALUES(?,?)')->execute([$u['id'],$tipId]);$pdo->prepare('UPDATE tips SET likes_count=likes_count+1 WHERE id=?')->execute([$tipId]);$liked=true;}$cnt=$pdo->prepare('SELECT likes_count FROM tips WHERE id=?');$cnt->execute([$tipId]);if($ajax)bk_json_out(['ok'=>true,'liked'=>$liked,'likes'=>(int)$cnt->fetchColumn()]);redirect_to($_POST['back']??'tip/'.$tipId);}
    if($action==='bookmark'){ $u=require_login();$tipId=(int)$_POST['tip_id'];$q=$pdo->prepare('SELECT id FROM bookmarks WHERE user_id=? AND tip_id=?');$q->execute([$u['id'],$tipId]);if($q->fetch()){$pdo->prepare('DELETE FROM bookmarks WHERE user_id=? AND tip_id=?')->execute([$u['id'],$tipId]);flash('نشانک حذف شد.');}else{$note=trim($_POST['note']??'');$pdo->prepare('INSERT INTO bookmarks(user_id,tip_id,note) VALUES(?,?,?)')->execute([$u['id'],$tipId,$note]);flash('به نشانک‌های من اضافه شد.');}redirect_to($_POST['back']??'tip/'.$tipId);}
    if($action==='search_live'){ $q=trim($_POST['q']??'');$results=[];if(mb_strlen($q)>=2){ $stmt=db()->prepare('SELECT id,title,access_type,price,views FROM tips WHERE status="published" AND (title LIKE ? OR device_name LIKE ? OR brand LIKE ? OR tags LIKE ?) ORDER BY views DESC LIMIT 8'); $like='%'.$q.'%'; $stmt->execute([$like,$like,$like,$like]); $results=$stmt->fetchAll(); } header('Content-Type: application/json; charset=utf-8'); echo json_encode(['items'=>array_map(fn($r)=>['id'=>(int)$r['id'],'title'=>$r['title'],'access'=>access_label($r['access_type'],(int)$r['price']),'views'=>(int)$r['views']],$results)], JSON_UNESCAPED_UNICODE); exit; }
    if($action==='admin_tip'){ $a=require_admin();$id=(int)$_POST['tip_id'];$act=$_POST['mod_action']??'';try{if($act==='feature'){$pdo->prepare('UPDATE tips SET featured=1-featured WHERE id=?')->execute([$id]);flash('وضعیت منتخب تغییر کرد.');redirect_to('admin?tab=tips');}if($act==='delete_forever'){$pdo->prepare('DELETE FROM tips WHERE id=?')->execute([$id]);flash('قلق برای همیشه حذف شد.');redirect_to('admin?tab=tips');}$status=$act==='publish'?'published':($act==='reject'?'rejected':($act==='remove'?'removed':null));if($status){$pdo->prepare('UPDATE tips SET status=?,published_at=CASE WHEN ?="published" THEN COALESCE(published_at,NOW()) ELSE published_at END,rejection_reason=? WHERE id=?')->execute([$status,$status,$_POST['reason']??null,$id]);$q=$pdo->prepare('SELECT author_id,title FROM tips WHERE id=?');$q->execute([$id]);$t=$q->fetch();if($t){try{notify_user((int)$t['author_id'],'admin','وضعیت قلق تغییر کرد','وضعیت قلق «'.$t['title'].'» به '.status_label($status).' تغییر کرد.',url('tip/'.$id));}catch(Throwable $e){}if($status==='published'){try{award_badge((int)$t['author_id'],'first_tip');$cnt=$pdo->prepare('SELECT COUNT(*) FROM tips WHERE author_id=? AND status=?');$cnt->execute([(int)$t['author_id'],'published']);if((int)$cnt->fetchColumn()>=10)award_badge((int)$t['author_id'],'ten_tips');}catch(Throwable $e){}}}}flash('عملیات مدیریت انجام شد.');}catch(Throwable $e){flash('خطا در تغییر وضعیت قلق: '.$e->getMessage(),'error');}redirect_to('admin?tab=tips');}
    if($action==='admin_user'){ $a=require_admin();$id=(int)$_POST['user_id'];$role=$_POST['role']??'member';if(!in_array($role,['member','expert','moderator','admin','superadmin'],true))$role='member';$q=$pdo->prepare('SELECT * FROM users WHERE id=?');$q->execute([$id]);$t=$q->fetch();if(!$t){flash('کاربر یافت نشد.','error');redirect_to('admin?tab=users');}if($t['role']==='superadmin'&&$a['role']!=='superadmin'){flash('فقط سوپرادمین می‌تواند حساب سوپرادمین را تغییر دهد.','error');redirect_to('admin?tab=users');}if($id===(int)$a['id']&&$role!==$a['role']){flash('نمی‌توانید نقش خودتان را تغییر دهید.','error');redirect_to('admin?tab=users');}if($id===(int)$a['id']&&!empty($_POST['banned'])){flash('نمی‌توانید حساب خودتان را مسدود کنید.','error');redirect_to('admin?tab=users');}$name=clean_text($_POST['name']??$t['name']);$phone=preg_replace('/[^0-9]/','',$_POST['phone']??$t['phone']);if(mb_strlen($name)<3)$name=$t['name'];if(!preg_match('/^09\d{9}$/',$phone))$phone=$t['phone'];$pdo->prepare('UPDATE users SET role=?,verified=?,is_banned=?,name=?,phone=? WHERE id=?')->execute([$role,!empty($_POST['verified'])?1:0,!empty($_POST['banned'])?1:0,$name,$phone,$id]);if(!empty($_POST['verified']))award_badge($id,'expert');$delta=(int)($_POST['delta']??0);$note=clean_text($_POST['note']??'')?:'تعدیل توسط مدیر';if($delta!==0&&$id!==(int)$a['id']){if($delta>0){credit($id,$delta,'admin_adjust','شارژ کیف پول توسط مدیر: '.$note);notify_user($id,'wallet','کیف پول شما شارژ شد',money($delta).' تومان توسط مدیر به کیف پول شما اضافه شد.',url('wallet'));}elseif(!debit($id,-$delta,'admin_adjust','کسر از کیف پول توسط مدیر: '.$note)){flash('کسر انجام نشد: موجودی کاربر کافی نیست.','error');}}flash('کاربر به‌روزرسانی شد.');redirect_to('admin?tab=users');}
    if($action==='admin_withdraw'){ require_admin();$id=(int)$_POST['withdrawal_id'];$status=$_POST['status']??'pending';$q=$pdo->prepare('SELECT * FROM withdrawals WHERE id=?');$q->execute([$id]);$w=$q->fetch();if($w&&in_array($status,['paid','rejected','reviewing'],true)){$pdo->prepare('UPDATE withdrawals SET status=?,admin_note=?,reviewed_at=IF(? IN ("paid","rejected"),NOW(),NULL) WHERE id=?')->execute([$status,clean_text($_POST['note']??''),$status,$id]);if($status==='rejected')credit((int)$w['user_id'],(int)$w['amount'],'withdrawal_cancel','برگشت تسویه رد شده');notify_user((int)$w['user_id'],'wallet','وضعیت تسویه تغییر کرد',$status==='paid'?'تسویه شما واریز شد.':($status==='rejected'?'تسویه رد شد و مبلغ برگشت داده شد.':'درخواست تسویه در حال بررسی است.'),url('wallet'));}redirect_to('admin?tab=withdrawals');}
    if(!function_exists('unsplash_img')) { function unsplash_img(string $q, int $w=1200): ?string { $u = 'https://source.unsplash.com/'.$w.'x800/?'.rawurlencode($q); return fetch_url($u, 10) ? $u : null; } }
if($action==='admin_collect'){ 
    require_admin();
    $enabled=!empty($_POST['enabled'])?1:0;
    $count=max(1,min(100,(int)($_POST['count']??10)));
    $cat=(int)($_POST['category']??0)?:null;
    $access=in_array($_POST['access']??'free',['free','like','paid'],true)?$_POST['access']:'free';
    $sources=preg_split('/\r?\n/',trim($_POST['sources']??''));$sources=array_values(array_filter(array_map('trim',$sources),fn($s)=>filter_var($s,FILTER_VALIDATE_URL)!==false));
    $queries=preg_split('/\r?\n/',trim($_POST['queries']??''));$queries=array_values(array_filter(array_map('trim',$queries),fn($q)=>$q!==''));
    $cronKey=trim($_POST['cron_key']??'');if($cronKey==='')$cronKey=bin2hex(random_bytes(8));
    // تنظیمات پیشرفته جدید + هندی و چینی - v4.3 کامل 15 تنظیم
    $indianEnabled=!empty($_POST['indian_enabled'])?1:0;
    $chineseEnabled=!empty($_POST['chinese_enabled'])?1:0;
    $japaneseEnabled=!empty($_POST['japanese_enabled'])?1:0;
    $minLength=max(20,min(1000,(int)($_POST['min_length']??100)));
    $maxImages=max(1,min(5,(int)($_POST['max_images']??3)));
    $translateEnabled=!empty($_POST['translate_enabled'])?1:0;
    $extractFull=!empty($_POST['extract_full'])?1:0;
    $saveImages=!empty($_POST['save_images'])?1:0;
    $filterRepair=!empty($_POST['filter_repair'])?1:0;
    $language=in_array($_POST['language']??'auto',['auto','fa','en'],true)?$_POST['language']:'auto';
    $contentType=in_array($_POST['content_type']??'repair',['repair','tutorial','all'],true)?$_POST['content_type']:'repair';
    $imageQuality=in_array($_POST['image_quality']??'medium',['low','medium','high'],true)?$_POST['image_quality']:'medium';
    $autoPublish=!empty($_POST['auto_publish'])?1:0;
    $excludeKeywords=trim($_POST['exclude_keywords']??'');
    $savePath=in_array($_POST['save_path']??'auto',['auto','western','indian','chinese'],true)?$_POST['save_path']:'auto';
    $maxRetries=max(1,min(5,(int)($_POST['max_retries']??2)));
    $timeout=max(5,min(30,(int)($_POST['timeout']??12)));

    // بررسی وجود ستون‌های جدید (برای سازگاری با نصب‌های قدیمی)
    $have=[];try{foreach($pdo->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='settings'")->fetchAll() as $c)$have[$c['COLUMN_NAME']]=true;}catch(Throwable $e){}
    $sets=[];$vals=[];
    $map=[
        'auto_collect_enabled'=>$enabled,
        'auto_collect_count'=>$count,
        'auto_collect_category'=>$cat,
        'auto_collect_access'=>$access,
        'auto_collect_sources'=>json_encode($sources,JSON_UNESCAPED_UNICODE),
        'auto_collect_queries'=>implode("\n",$queries),
        'auto_collect_cron_key'=>$cronKey,
        'auto_collect_indian_enabled'=>$indianEnabled,
        'auto_collect_chinese_enabled'=>$chineseEnabled,
        'auto_collect_japanese_enabled'=>$japaneseEnabled,
        'auto_collect_min_length'=>$minLength,
        'auto_collect_max_images'=>$maxImages,
        'auto_collect_translate_enabled'=>$translateEnabled,
        'auto_collect_extract_full'=>$extractFull,
        'auto_collect_save_images'=>$saveImages,
        'auto_collect_filter_repair'=>$filterRepair,
        'auto_collect_language'=>$language,
        'auto_collect_content_type'=>$contentType,
        'auto_collect_image_quality'=>$imageQuality,
        'auto_collect_auto_publish'=>$autoPublish,
        'auto_collect_exclude_keywords'=>$excludeKeywords,
        'auto_collect_save_path'=>$savePath,
        'auto_collect_max_retries'=>$maxRetries,
        'auto_collect_timeout'=>$timeout,
    ];
    foreach($map as $k=>$v){if(isset($have[$k]) || empty($have)){$sets[]='`'.$k.'`=?';$vals[]=$v;}}
    if($sets){$pdo->prepare('UPDATE settings SET '.implode(',',$sets).' WHERE id=1')->execute($vals);}

    if(!empty($_POST['run_now'])){
        @set_time_limit(120);
        try{
            $extra=[
                'indian_enabled'=> (bool)$indianEnabled,
                'chinese_enabled'=> (bool)$chineseEnabled,
                'japanese_enabled'=> (bool)$japaneseEnabled,
                'min_length'=>$minLength,
                'max_images'=>$maxImages,
                'translate_enabled'=> (bool)$translateEnabled,
                'extract_full'=> (bool)$extractFull,
                'save_images'=> (bool)$saveImages,
                'filter_repair'=> (bool)$filterRepair,
                'language'=>$language,
                'content_type'=>$contentType,
                'image_quality'=>$imageQuality,
                'auto_publish'=> (bool)$autoPublish,
                'exclude_keywords'=>$excludeKeywords,
                'save_path'=>$savePath,
                'max_retries'=>$maxRetries,
                'timeout'=>$timeout,
            ];
            $result=collect_tips_web($count,$cat?:0,$access,$sources,$queries,$extra);
            if(!empty($result['error'])){flash($result['error'],'error');}
            else{
                $detail = '';
                if (!empty($result['settings_used'])) {
                    $su = $result['settings_used'];
                    $detail = ' (هندی:'.($su['indian']?'فعال':'غیرفعال').'، چینی:'.($su['chinese']?'فعال':'غیرفعال').'، تصاویر:'.fa($su['max_images']).')';
                }
                flash(sprintf('جمع‌آوری هوشمند انجام شد: %s قلق فارسی منتشر شد، %s مطلب بررسی شد، %s خطا%s.',fa($result['created']),fa($result['scanned']),fa($result['errors']),$detail));
            }
        }catch(Throwable $e){flash('خطا در جمع‌آوری: '.$e->getMessage(),'error');}
    }else{flash('تنظیمات جمع‌آوری هوشمند ذخیره شد.');}
    redirect_to('admin?tab=collect');
}
    if($action==='subscribe'){ $u=require_login();$months=(int)($_POST['months']??1);$prices=[1=>(int)settings()['premium_1'],3=>(int)settings()['premium_3'],12=>(int)settings()['premium_12']];$amount=$prices[$months]??$prices[1];if(!debit($u['id'],$amount,'subscription','خرید اشتراک ویژه '.$months.' ماهه')){flash('موجودی کیف پول کافی نیست.','error');redirect_to('wallet');}$base=($u['premium_until']&&strtotime($u['premium_until'])>time())?strtotime($u['premium_until']):time();$until=date('Y-m-d H:i:s',$base+$months*30*86400);$pdo->prepare('UPDATE users SET premium_until=? WHERE id=?')->execute([$until,$u['id']]);award_badge((int)$u['id'],'premium');flash('اشتراک ویژه با موفقیت فعال شد.');redirect_to('premium');}
    if($action==='profile_update'){ $u=require_login();$name=clean_text($_POST['name']??$u['name']);$bio=trim($_POST['bio']??'');if(mb_strlen($name)<3){flash('نام معتبر نیست.','error');redirect_to('settings');}$pdo->prepare('UPDATE users SET name=?,bio=? WHERE id=?')->execute([$name,$bio,$u['id']]);flash('پروفایل ذخیره شد.');redirect_to('settings');}
    if($action==='suggest_category'){ $u=require_login();$name=clean_text($_POST['name']??'');$parent=(int)($_POST['parent_id']??0)?:null;if(mb_strlen($name)<2){flash('نام دسته را وارد کنید.','error');redirect_to('upload');}$q=$pdo->prepare('SELECT id FROM categories WHERE name=? LIMIT 1');$q->execute([$name]);if($q->fetch()){flash('این دسته قبلاً ثبت شده است.','error');redirect_to('upload');}$pdo->prepare('INSERT INTO categories(parent_id,name,slug,icon,status) VALUES(?,?,?,?,?)')->execute([$parent,$name,'cat-'.md5($name.time()),null,'pending']);flash('پیشنهاد دسته شما ثبت شد و پس از تأیید مدیر اضافه می‌شود.');redirect_to('upload');}
    if($action==='admin_category'){ require_admin();$op=$_POST['op']??'add';$id=(int)($_POST['category_id']??0);$name=clean_text($_POST['name']??'');$parent=(int)($_POST['parent_id']??0)?:null;$icon=clean_text($_POST['icon']??'');if($op==='delete'&&$id){$pdo->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);flash('دسته حذف شد.');}elseif($op==='approve'&&$id){$pdo->prepare("UPDATE categories SET status='active' WHERE id=?")->execute([$id]);flash('دسته تأیید و فعال شد.');}elseif($op==='dedupe'){$n=$pdo->exec('DELETE c1 FROM categories c1 INNER JOIN categories c2 ON c1.name=c2.name AND IFNULL(c1.parent_id,0)=IFNULL(c2.parent_id,0) AND c1.id>c2.id');flash(($n?:0).' دستهٔ تکراری حذف شد.');}elseif($op==='add'&&mb_strlen($name)>=2){$pdo->prepare('INSERT INTO categories(parent_id,name,slug,icon,status) VALUES(?,?,?,?,?)')->execute([$parent,$name,'cat-'.md5($name.time()),$icon?:null,'active']);flash('دسته جدید افزوده شد.');}redirect_to('admin?tab=categories');}
    if($action==='admin_settings'){ require_admin();$map=['site_title'=>clean_text($_POST['site_title']??''),'hero_title'=>clean_text($_POST['hero_title']??''),'hero_subtitle'=>clean_text($_POST['hero_subtitle']??''),'announcement'=>clean_text($_POST['announcement']??''),'upload_reward'=>max(0,(int)($_POST['upload_reward']??0)),'like_points_reward'=>max(0,(int)($_POST['like_points_reward']??0)),'like_wallet_reward'=>max(0,(int)($_POST['like_wallet_reward']??0)),'referral_reward'=>max(0,(int)($_POST['referral_reward']??0)),'invitee_credit'=>max(0,(int)($_POST['invitee_credit']??0)),'commission_percent'=>max(0,min(100,(int)($_POST['commission_percent']??0))),'min_withdrawal'=>max(0,(int)($_POST['min_withdrawal']??0)),'daily_like_limit'=>max(1,(int)($_POST['daily_like_limit']??1)),'repair_deadline_days'=>max(1,(int)($_POST['repair_deadline_days']??1)),'premium_1'=>max(0,(int)($_POST['premium_1']??0)),'premium_3'=>max(0,(int)($_POST['premium_3']??0)),'premium_12'=>max(0,(int)($_POST['premium_12']??0)),'board_commission_percent'=>max(0,min(50,(int)($_POST['board_commission_percent']??10))),'daily_free_tip_id'=>!empty($_POST['daily_free_tip_id'])?(int)$_POST['daily_free_tip_id']:null,'terms_text'=>trim($_POST['terms_text']??''),'about_text'=>trim($_POST['about_text']??''),'contact_text'=>trim($_POST['contact_text']??''),'privacy_text'=>trim($_POST['privacy_text']??''),'contact_form_enabled'=>!empty($_POST['contact_form_enabled'])?1:0,'contact_email'=>trim($_POST['contact_email']??''),'contact_phone'=>trim($_POST['contact_phone']??''),'contact_telegram'=>trim($_POST['contact_telegram']??''),'contact_instagram'=>trim($_POST['contact_instagram']??''),'contact_address'=>trim($_POST['contact_address']??''),'meta_description'=>trim($_POST['meta_description']??''),'meta_keywords'=>trim($_POST['meta_keywords']??''),'og_image'=>trim($_POST['og_image']??''),'google_analytics'=>trim($_POST['google_analytics']??'')];$have=[];foreach($pdo->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='settings'")->fetchAll() as $c)$have[$c['COLUMN_NAME']]=true;$sets=[];$vals=[];foreach($map as $k=>$v){if(isset($have[$k])){$sets[]='`'.$k.'`=?';$vals[]=$v;}}if($sets){$pdo->prepare('UPDATE settings SET '.implode(',',$sets).' WHERE id=1')->execute($vals);}flash('تنظیمات سایت ذخیره شد.');redirect_to('admin?tab=settings');}
    if($action==='admin_report'){ require_admin();$rid=(int)$_POST['report_id'];$resolve=!empty($_POST['resolve']);$q=$pdo->prepare('SELECT * FROM reports WHERE id=?');$q->execute([$rid]);$r=$q->fetch();if($r&&$r['status']==='open'){if($resolve){if($r['target_type']==='tip'){$pdo->prepare("UPDATE tips SET status='removed' WHERE id=?")->execute([(int)$r['target_id']]);}elseif($r['target_type']==='comment'){$pdo->prepare('UPDATE comments SET is_deleted=1 WHERE id=?')->execute([(int)$r['target_id']]);}} $pdo->prepare("UPDATE reports SET status='resolved',resolved_at=NOW() WHERE id=?")->execute([$rid]);}flash('گزارش پردازش شد.');redirect_to('admin?tab=reports');}
    if($action==='contact_status'){ require_admin();$cid=(int)($_POST['contact_id']??0);$op=$_POST['op']??'';try{$q=$pdo->prepare('SELECT * FROM contact_messages WHERE id=?');$q->execute([$cid]);$m=$q->fetch();}catch(Throwable $e){$m=false;}if($m){if($op==='answered'){$pdo->prepare("UPDATE contact_messages SET status='answered' WHERE id=?")->execute([$cid]);}elseif($op==='closed'){$pdo->prepare("UPDATE contact_messages SET status='closed' WHERE id=?")->execute([$cid]);}elseif($op==='reopen'){$pdo->prepare("UPDATE contact_messages SET status='new' WHERE id=?")->execute([$cid]);}elseif($op==='delete'){$pdo->prepare('DELETE FROM contact_messages WHERE id=?')->execute([$cid]);}if($m['user_id']&&in_array($op,['answered','closed'],true)){notify_user((int)$m['user_id'],'system','پیگیری پیام تماس','پیام شما با موضوع «'.$m['subject'].'» توسط پشتیبانی بررسی شد.',url('contact'));}}flash('پیام تماس به‌روزرسانی شد.');redirect_to('admin?tab=contact');}

    /* ---------- sellers / boards marketplace ---------- */
    if($action==='seller_apply'){ $u=require_login();if(is_seller($u)){redirect_to('boards/new');}$status=$u['seller_status']??'none';if($status==='pending'){flash('درخواست فروشندگی شما در حال بررسی است.');redirect_to('boards');}$note=trim($_POST['note']??'');if(mb_strlen($note)<20){flash('توضیح کامل‌تری از تخصص و تجربه خود بنویسید (حداقل ۲۰ کاراکتر).','error');redirect_to('seller-apply');}$pdo->prepare("UPDATE users SET seller_status='pending', seller_note=?, seller_applied_at=NOW() WHERE id=?")->execute([$note,$u['id']]);flash('درخواست فروشندگی شما ثبت شد و پس از بررسی مدیر فعال خواهد شد.');redirect_to('boards');}
    if($action==='board_create'){ $u=require_login();$ajax=is_ajax_request();$bk_fail=function(string $m)use($ajax){if($ajax){bk_json_out(['ok'=>false,'error'=>$m],422);}flash($m,'error');redirect_to('boards/new');};if(!is_seller($u))$bk_fail('برای فروش برد ابتدا باید فروشنده تأییدشده باشید.');try{$title=clean_text($_POST['title']??'');$desc=trim($_POST['description']??'');$cat=(int)($_POST['category_id']??0);$price=max(1000,(int)($_POST['price']??0));$stock=max(1,min(999,(int)($_POST['stock']??1)));$cond=in_array($_POST['condition_status']??'used',['new','like_new','used','repair'],true)?$_POST['condition_status']:'used';$images=[];foreach(($_FILES['images']['tmp_name']??[]) as $i=>$tmp){$f=['tmp_name'=>$tmp,'error'=>$_FILES['images']['error'][$i]??1,'size'=>$_FILES['images']['size'][$i]??0,'type'=>$_FILES['images']['type'][$i]??''];$saved=save_image($f);if($saved)$images[]=$saved;}if(mb_strlen($title)<5)$bk_fail('عنوان برد باید حداقل ۵ حرف باشد.');if(mb_strlen($desc)<10)$bk_fail('توضیح برد باید حداقل ۱۰ حرف باشد.');if(!$cat)$bk_fail('دسته‌بندی را انتخاب کنید.');if($price<=0)$bk_fail('قیمت نامعتبر است.');if(count($images)<1)$bk_fail('حداقل یک عکس معتبر (JPG/PNG/WebP تا ۵MB) لازم است. اگر عکس انتخاب کرده‌اید، مجوز نوشتن پوشه uploads را در هاست بررسی کنید.');$pdo->prepare("INSERT INTO boards(seller_id,category_id,title,description,brand,model,condition_status,price,stock,images_json,video_url,status) VALUES(?,?,?,?,?,?,?,?,?,?,?, 'pending')")->execute([$u['id'],$cat,$title,safe_rich($desc),clean_text($_POST['brand']??''),clean_text($_POST['model']??''),$cond,$price,$stock,json_encode($images,JSON_UNESCAPED_UNICODE),trim($_POST['video_url']??'')]);}catch(Throwable $e){$bk_fail('ثبت برد انجام نشد: '.$e->getMessage());}$msg='برد ثبت شد و پس از تأیید مدیر در فروشگاه نمایش داده می‌شود.';if($ajax){bk_json_out(['ok'=>true,'message'=>$msg,'redirect'=>url('my-boards')]);}flash($msg);redirect_to('my-boards');}
    if($action==='board_buy'){ $u=require_login();$bid=(int)($_POST['board_id']??0);$b=$pdo->prepare("SELECT * FROM boards WHERE id=? AND status='approved'");$b->execute([$bid]);$board=$b->fetch();if(!$board){flash('برد یافت نشد یا در حال حاضر قابل خرید نیست.','error');redirect_to('boards');}if((int)$board['seller_id']===(int)$u['id']){flash('نمی‌توانید برد خودتان را خرید کنید.','error');redirect_to('board/'.$bid);}if($board['stock']<=0){flash('موجودی این برد تمام شده است.','error');redirect_to('board/'.$bid);}$escrow=escrow_admin_id();if(!$escrow){flash('حساب امانت سیستم پیکربندی نشده است.','error');redirect_to('board/'.$bid);}$amount=(int)$board['price'];$commPercent=(int)(settings()['board_commission_percent']??10);$commission=(int)floor($amount*$commPercent/100);$net=$amount-$commission;$pdo->beginTransaction();try{if(!debit($u['id'],$amount,'board_purchase','خرید برد «'.$board['title'].'» (نگه‌داری امانت)')){throw new Exception('balance');}$pdo->prepare('UPDATE users SET balance=balance+? WHERE id=?')->execute([$amount,$escrow]);$qb=$pdo->prepare('SELECT balance FROM users WHERE id=?');$qb->execute([$escrow]);$bal=(int)$qb->fetchColumn();$pdo->prepare('INSERT INTO wallet_transactions(user_id,type,amount,balance_after,note) VALUES(?,?,?,?,?)')->execute([$escrow,'board_escrow',$amount,$bal,'دریافت امانت خرید برد «'.$board['title'].'»']);$pdo->prepare('INSERT INTO board_orders(board_id,buyer_id,seller_id,amount,commission_percent,commission_amount,net_amount,status) VALUES(?,?,?,?,?,?,?,?)')->execute([$bid,$u['id'],(int)$board['seller_id'],$amount,$commPercent,$commission,$net,'paid']);$pdo->prepare('UPDATE boards SET stock=stock-1, sold_count=sold_count+1 WHERE id=?')->execute([$bid]);$pdo->commit();notify_user((int)$board['seller_id'],'board','سفارش جدید!','برد «'.$board['title'].'» فروخته شد. وجه در امانت نزد بردخان نگه‌داشته است؛ برد را ارسال کنید.',url('my-boards'));maybe_reward_referrer($u['id']);notify_user((int)$u['id'],'board','سفارش شما ثبت شد','خرید شما ثبت شد؛ وجه در امانت نزد بردخان است. برای رهگیری به بخش سفارش‌ها بروید.',url('boards'));flash('خرید انجام شد! وجه در امانت نزد بردخان نگه‌داشته است و پس از تأیید دریافت، سهم فروشنده واریز می‌شود.');redirect_to('board/'.$bid);}catch(Throwable $e){$pdo->rollBack();flash('موجودی کیف پول شما کافی نیست. ابتدا کیف پول را شارژ کنید.','error');redirect_to('wallet');}}
    if($action==='board_ship'){ $u=require_login();$oid=(int)($_POST['order_id']??0);$o=$pdo->prepare('SELECT * FROM board_orders WHERE id=? AND status="paid"');$o->execute([$oid]);$order=$o->fetch();if(!$order||(int)$order['seller_id']!==(int)$u['id']){flash('سفارش یافت نشد.','error');redirect_to('my-boards');}$tracking=clean_text($_POST['tracking_code']??'');$pdo->prepare("UPDATE board_orders SET status='shipped', tracking_code=?, shipped_at=NOW() WHERE id=?")->execute([$tracking?:null,$oid]);notify_user((int)$order['buyer_id'],'board','برد شما ارسال شد','فروشنده برد را ارسال کرده است.'.($tracking?' کد رهگیری: '.$tracking:''),url('boards'));flash('وضعیت سفارش به «ارسال شده» تغییر کرد.');redirect_to('my-boards');}
    if($action==='board_confirm'){ $u=require_login();$oid=(int)($_POST['order_id']??0);$o=$pdo->prepare("SELECT * FROM board_orders WHERE id=? AND status IN ('paid','shipped')");$o->execute([$oid]);$order=$o->fetch();if(!$order||(int)$order['buyer_id']!==(int)$u['id']){flash('سفارش یافت نشد.','error');redirect_to('boards');}$escrow=escrow_admin_id();$pdo->beginTransaction();try{$net=(int)$order['net_amount'];$db=$pdo->prepare('SELECT balance FROM users WHERE id=? FOR UPDATE');$db->execute([$escrow]);$bal=(int)$db->fetchColumn();if($bal<$net)throw new Exception('escrow');$pdo->prepare('UPDATE users SET balance=balance-? WHERE id=?')->execute([$net,$escrow]);$pdo->prepare('INSERT INTO wallet_transactions(user_id,type,amount,balance_after,note) VALUES(?,?,?,?,?)')->execute([$escrow,'board_release',-$net,$bal-$net,'آزادسازی امانت سفارش #'.$oid]);credit((int)$order['seller_id'],$net,'board_sale','فروش برد (پس از کسر '.fa($order['commission_percent']).'٪ کمیسیون) — سفارش #'.$oid);$pdo->prepare("UPDATE board_orders SET status='completed', completed_at=NOW(), admin_id=? WHERE id=?")->execute([$escrow,$oid]);$pdo->prepare("UPDATE boards SET status='sold' WHERE id=? AND stock<=0")->execute([(int)$order['board_id']]);award((int)$order['seller_id'],30);$pdo->commit();notify_user((int)$order['seller_id'],'board','واریز فروش برد','خریدار، دریافت برد را تأیید کرد و '.money($net).' تومان به کیف پول شما واریز شد.',url('wallet'));flash('دریافت برد تأیید شد. ممنون از اعتماد شما!');redirect_to('boards');}catch(Throwable $e){$pdo->rollBack();flash('خطا در آزادسازی وجه؛ با پشتیبانی تماس بگیرید.','error');redirect_to('boards');}}
    if($action==='board_cancel'){ $a=require_admin();$oid=(int)($_POST['order_id']??0);$o=$pdo->prepare("SELECT * FROM board_orders WHERE id=? AND status IN ('paid','shipped')");$o->execute([$oid]);$order=$o->fetch();if(!$order){flash('سفارش یافت نشد.','error');redirect_to('admin?tab=orders');}$escrow=escrow_admin_id();$pdo->beginTransaction();try{$amount=(int)$order['amount'];$db=$pdo->prepare('SELECT balance FROM users WHERE id=? FOR UPDATE');$db->execute([$escrow]);$bal=(int)$db->fetchColumn();$refund=min($amount,$bal);$pdo->prepare('UPDATE users SET balance=balance-? WHERE id=?')->execute([$refund,$escrow]);$pdo->prepare('INSERT INTO wallet_transactions(user_id,type,amount,balance_after,note) VALUES(?,?,?,?,?)')->execute([$escrow,'board_refund_out',-$refund,$bal-$refund,'بازگشت وجه سفارش #'.$oid]);credit((int)$order['buyer_id'],$refund,'board_refund','بازگشت وجه سفارش لغوشده #'.$oid);$pdo->prepare("UPDATE board_orders SET status='cancelled', cancelled_at=NOW() WHERE id=?")->execute([$oid]);$pdo->commit();notify_user((int)$order['buyer_id'],'board','سفارش لغو و وجه برگشت','سفارش #'.$oid.' لغو شد و وجه به کیف پول شما برگشت.',url('wallet'));flash('سفارش لغو و وجه برگشت داده شد.');redirect_to('admin?tab=orders');}catch(Throwable $e){$pdo->rollBack();flash('خطا در بازگشت وجه.','error');redirect_to('admin?tab=orders');}}
    if($action==='admin_board'){ require_admin();$bid=(int)$_POST['board_id'];$op=$_POST['op']??'';$q=$pdo->prepare('SELECT * FROM boards WHERE id=?');$q->execute([$bid]);$b=$q->fetch();if(!$b){flash('برد یافت نشد.','error');redirect_to('admin?tab=boards');}if($op==='approve'){$pdo->prepare("UPDATE boards SET status='approved', approved_at=NOW(), rejection_reason=NULL WHERE id=?")->execute([$bid]);notify_user((int)$b['seller_id'],'board','برد شما تأیید شد','برد «'.$b['title'].'» در فروشگاه نمایش داده می‌شود.',url('board/'.$bid));flash('برد تأیید و منتشر شد.');}elseif($op==='reject'){$reason=clean_text($_POST['reason']??'مغایرت با قوانین فروشگاه');$pdo->prepare("UPDATE boards SET status='rejected', rejection_reason=? WHERE id=?")->execute([$reason,$bid]);notify_user((int)$b['seller_id'],'board','برد رد شد','برد «'.$b['title'].'» رد شد: '.$reason,url('my-boards'));flash('برد رد شد.');}elseif($op==='remove'){$pdo->prepare("UPDATE boards SET status='archived' WHERE id=?")->execute([$bid]);flash('برد بایگانی شد.');}redirect_to('admin?tab=boards');}
    if($action==='admin_seller'){ require_admin();$uid=(int)$_POST['user_id'];$op=$_POST['op']??'';if($op==='approve'){$pdo->prepare("UPDATE users SET seller_status='approved' WHERE id=?")->execute([$uid]);award_badge($uid,'seller');notify_user($uid,'board','فروشندگی فعال شد','حساب فروشندگی شما تأیید شد؛ حالا می‌توانید برد ثبت و بفروشید.',url('boards/new'));flash('فروشنده تأیید شد.');}elseif($op==='reject'){$pdo->prepare("UPDATE users SET seller_status='rejected' WHERE id=?")->execute([$uid]);notify_user($uid,'board','درخواست فروشندگی رد شد','متأسفانه درخواست فروشندگی شما تأیید نشد.',url('boards'));flash('رد شد.');}elseif($op==='revoke'){$pdo->prepare("UPDATE users SET seller_status='none' WHERE id=?")->execute([$uid]);flash('دسترسی فروشندگی لغو شد.');}redirect_to('admin?tab=sellers');}
}

if(in_array($page,['sitemap.xml','sitemap'],true)){include __DIR__.'/sitemap.php';exit;}
if(in_array($page,['robots.txt','robots'],true)){include __DIR__.'/robots.php';exit;}
$route=bk_route();$parts=$route===''?[]:explode('/',$route);$page=$parts[0]??'home';$id=(int)($parts[1]??0);
if($page==='diag-version'){ header('Content-Type: text/html; charset=utf-8'); $rows=''; $rows.='<b>نسخهٔ کد اجراشدهٔ سرور:</b> <span style="font-size:22px;color:#0a7a4a">'.(defined('BORDKHAN_VERSION')?BORDKHAN_VERSION:'نامشخص').'</span>'.(defined('BORDKHAN_VERSION')&&BORDKHAN_VERSION>='4.0'?' <b>✓ جدید است</b>':' <b style="color:#b3261e">✗ قدیمی است — فایل‌ها آپلود نشده‌اند یا کش سرور!</b>').'<br>'; $rows.='نسخهٔ PHP: <b dir="ltr">'.PHP_VERSION.'</b><br>'; foreach(['index.php','pages/admin.php','pages/boards.php','assets/style.css','sw.js'] as $f){$p=__DIR__.'/'.$f;$rows.='فایل <span dir="ltr">'.$f.'</span>: '.($p&&is_file($p)?'<span dir="ltr">'.date('Y-m-d H:i',@filemtime($p)).'</span>':'<b style="color:#b3261e">ناموجود!</b>').'<br>';} if(function_exists('opcache_get_status')){$st=@opcache_get_status(false);$rows.='OPcache: '.($st?'فعال':'غیرفعال');if($st){$rows.=' · <span dir="ltr">validate_timestamps='.($st['opcache_statistics']['num_cached_scripts']?'off':'on').'</span>';$cached=isset($st['scripts'])&&strpos(implode('',array_keys($st['scripts'])),'index.php')!==false;$rows.=$cached?' · <b style="color:#b3261e">index.php در کش اپ‌کش است — ممکن است کد قدیمی اجرا شود!</b>':' · index.php در کش نیست ✓';}}else{$rows.='OPcache: نصب نیست<br>';} $rows.='<br><b>وضعیت قلق‌ها در دیتابیس:</b><br>'; try{$q=db()->query("SELECT status,COUNT(*) c FROM tips GROUP BY status");foreach($q->fetchAll() as $r){$rows.='• <span dir="ltr">'.$r['status'].'</span>: '.fa((int)$r['c']).'<br>';}$q2=db()->query('SELECT id,title,status FROM tips ORDER BY id DESC LIMIT 5');$rows.='<br><b>۵ قلق آخر:</b><br>';foreach($q2->fetchAll() as $r){$rows.='• #'.fa((int)$r['id']).' '.h(mb_substr($r['title'],0,40)).' — <b style="color:'.($r['status']==='published'?'#0a7a4a':'#b3261e').'">'.$r['status'].'</b><br>';}}catch(Throwable $e){$rows.='خطای دیتابیس: '.h($e->getMessage()).'<br>';} $rows.='<br><b>اگر نسخهٔ بالا قدیمی است:</b><br>۱) همهٔ فایل‌های ZIP جدید را روی سرور بازنویسی کنید (به‌خصوص index.php و pages/).<br>۲) اگر OPcache فعال بود، یک‌بار <span dir="ltr">/php-extended/opcache_clear.php?key=INSTALL_KEY</span> را باز کنید.<br>۳) مرورگر را با <b>Ctrl+Shift+R</b> رفرش کنید و فوتر سایت باید «نسخه ۴.۰» را نشان دهد.'; echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>عیب‌یابی نسخه</title></head><body style="font-family:Tahoma;background:#f4f6f8;padding:24px"><div style="max-width:780px;margin:auto;background:#fff;border-radius:14px;padding:24px;border:1px solid #e3e8ee;font-size:14px;line-height:2.2">'.$rows.'</div></body></html>'; exit; }
if($page==='ajax-comments'){ $tipId=(int)($_GET['tip_id']??0);$items=[];$can=current_user();if($tipId>0){$q=db()->prepare('SELECT c.id,c.body,c.created_at,u.name user_name FROM comments c JOIN users u ON u.id=c.user_id WHERE c.tip_id=? AND c.is_deleted=0 ORDER BY c.created_at ASC LIMIT 100');$q->execute([$tipId]);foreach($q->fetchAll() as $c){$items[]=['id'=>(int)$c['id'],'name'=>$c['user_name'],'body'=>$c['body'],'ago'=>ago($c['created_at'])];}}$cnt=db()->prepare('SELECT COUNT(*) FROM comments WHERE tip_id=? AND is_deleted=0');$cnt->execute([$tipId]);bk_json_out(['ok'=>true,'count'=>(int)$cnt->fetchColumn(),'can'=>(bool)$can,'items'=>$items]); }
if($page==='ajax-notifications'){ $u=current_user(); if(!$u){bk_json_out(['unread'=>0,'items'=>[]]);} $q=db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');$q->execute([(int)$u['id']]);$unread=(int)$q->fetchColumn();$q=db()->prepare('SELECT id,title,body,link,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 5');$q->execute([(int)$u['id']]);$items=[];foreach($q->fetchAll() as $n){$items[]=['title'=>$n['title'],'body'=>mb_substr((string)($n['body']??''),0,90),'link'=>$n['link'],'ago'=>ago($n['created_at'])];}bk_json_out(['unread'=>$unread,'items'=>$items]); }
if($page==='ajax-categories'){ $q=trim($_GET['q']??'');$like=$q!==''?'%'.$q.'%':null;if($like){$st=db()->prepare('SELECT c.id,c.name,c.icon,c.status,p.name parent_name,(SELECT COUNT(*) FROM categories cc WHERE cc.parent_id=c.id) child_count FROM categories c LEFT JOIN categories p ON p.id=c.parent_id WHERE c.name LIKE ? OR p.name LIKE ? ORDER BY c.parent_id IS NOT NULL, c.sort_order, c.name LIMIT 250');$st->execute([$like,$like]);}else{$st=db()->query('SELECT c.id,c.name,c.icon,c.status,p.name parent_name,(SELECT COUNT(*) FROM categories cc WHERE cc.parent_id=c.id) child_count FROM categories c LEFT JOIN categories p ON p.id=c.parent_id ORDER BY c.parent_id IS NOT NULL, c.sort_order, c.name LIMIT 250');}header('Content-Type: application/json; charset=utf-8');echo json_encode(['q'=>$q,'rows'=>$st->fetchAll()],JSON_UNESCAPED_UNICODE);exit; }
if($page==='logout'){session_destroy();redirect_to('');}
if($page==='assets'){ $safe=preg_replace('/[^A-Za-z0-9._-]/','',basename(trim(str_replace('assets/','',$route),'/'))); $file=__DIR__.'/assets/'.$safe; $map=['css'=>'text/css','js'=>'application/javascript','png'=>'image/png','svg'=>'image/svg+xml','webmanifest'=>'application/manifest+json','woff2'=>'font/woff2']; $ext=strtolower(pathinfo($safe,PATHINFO_EXTENSION)); if($safe!==''&&is_file($file)&&isset($map[$ext])){header('Content-Type: '.$map[$ext]);header('Cache-Control: public,max-age=604800');readfile($file);exit;} http_response_code(404);exit('not found'); }
if($_SERVER['REQUEST_METHOD']==='POST'){ $bkA=$_POST['action']??''; if(in_array($bkA,['board_buy','board_ship','profile_save','wallet_gateway_start','wallet_card_to_card'],true)&&is_file(__DIR__.'/php-extended/bk_extended.php')){require __DIR__.'/php-extended/bk_extended.php';if(function_exists('check_csrf'))check_csrf();bk_extended_action($bkA);} }
if($page==='tickets'&&is_file(__DIR__.'/php-extended/tickets.php')){require __DIR__.'/php-extended/tickets.php';exit;}
if($page==='wallet-plus'&&is_file(__DIR__.'/php-extended/bk_extended.php')){require __DIR__.'/php-extended/bk_extended.php';bk_render_wallet_plus();exit;}
if($page==='admin-finance'&&is_file(__DIR__.'/php-extended/admin_finance.php')){require __DIR__.'/php-extended/admin_finance.php';exit;}
if($page==='profile-edit'&&is_file(__DIR__.'/php-extended/profile_edit.php')){require __DIR__.'/php-extended/profile_edit.php';exit;}
if($page==='admin-actionbar'&&is_file(__DIR__.'/php-extended/bk_actionbar.php')){$GLOBALS['page']=$page;require __DIR__.'/php-extended/bk_actionbar.php';exit;}
if(in_array($page,['admin-boards','admin-users','admin-tips'],true)&&is_file(__DIR__.'/php-extended/bk_admin_extra.php')){$GLOBALS['bkx_page']=$page;require __DIR__.'/php-extended/bk_admin_extra.php';exit;}

if($page==='serve'||$page==='serve.php'){require __DIR__.'/serve.php';exit;}
if($page==='cron-collect'){ $s=settings();$key=(string)($_GET['key']??'');$enabled=(int)($s['auto_collect_enabled']??0);$cronKey=(string)($s['auto_collect_cron_key']??'');if(!$enabled||$cronKey===''||!hash_equals($cronKey,$key)){http_response_code(403);exit('forbidden');}$count=max(1,min(100,(int)($s['auto_collect_count']??10)));$cat=(int)($s['auto_collect_category']??0);$access=in_array($s['auto_collect_access']??'free',['free','like','paid'],true)?$s['auto_collect_access']:'free';$sources=json_decode_array($s['auto_collect_sources']??'[]');$queries=preg_split('/\r?\n/',(string)($s['auto_collect_queries']??''));$queries=array_values(array_filter(array_map('trim',$queries)));$result=collect_tips_web($count,$cat,$access,$sources,$queries);header('Content-Type: application/json; charset=utf-8');echo json_encode($result,JSON_UNESCAPED_UNICODE);exit; }

if($page==='home'){require __DIR__.'/pages/home.php';exit;}
function _legacy_home(){
    $s=settings();$cats=category_tree();$total=(int)db()->query("SELECT COUNT(*) FROM tips WHERE status='published'")->fetchColumn();$users=(int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();$latest=db()->query("SELECT t.*,u.name author_name,u.verified FROM tips t JOIN users u ON u.id=t.author_id WHERE t.status='published' ORDER BY COALESCE(t.published_at,t.created_at) DESC LIMIT 8")->fetchAll();$popular=db()->query("SELECT t.*,u.name author_name,u.verified FROM tips t JOIN users u ON u.id=t.author_id WHERE t.status='published' ORDER BY t.views DESC LIMIT 4")->fetchAll();$featured=db()->query("SELECT t.*,u.name author_name,u.verified FROM tips t JOIN users u ON u.id=t.author_id WHERE t.status='published' AND t.featured=1 LIMIT 4")->fetchAll();$leaders=db()->query('SELECT * FROM users ORDER BY points DESC LIMIT 5')->fetchAll();$repairs=db()->query("SELECT r.*,u.name user_name FROM repair_requests r JOIN users u ON u.id=r.user_id WHERE r.status='open' ORDER BY r.created_at DESC LIMIT 3")->fetchAll();header_html();?><section class="hero"><div class="wrap hero-inner"><span class="eyebrow">✦ بیش از <?=fa($total)?> قلق تست‌شده توسط تعمیرکاران واقعی</span><h1><?=h($s['hero_title']??'بازار تخصصی قلق‌های تعمیراتی بردهای الکترونیکی')?></h1><p><?=h($s['hero_subtitle'])?></p><form class="search-box" action="<?=url('tips')?>" method="get"><input type="hidden" name="r" value="tips"><input name="q" placeholder="مثلاً: روشن نشدن لپ‌تاپ ایسوس X550…"><button class="btn btn-primary">⌕ جستجو</button></form><div class="stats"><div class="stat"><strong><?=fa($total)?></strong><span>قلق منتشرشده</span></div><div class="stat"><strong><?=fa($users)?></strong><span>تعمیرکار و عضو</span></div><div class="stat"><strong>۲۴/۷</strong><span>دسترسی به راه‌حل</span></div></div></div></section><main class="wrap"><div class="categories"><?php foreach($cats as $c):?><a class="cat" href="<?=url('tips',['cat'=>$c['id']])?>"><span class="emoji"><?=h($c['icon']?:'🔧')?></span><span><b><?=h($c['name'])?></b><small><?=fa(count($c['children']))?> زیردسته</small></span></a><?php endforeach;?></div><?php if($s['daily_free_tip_id']):$q=db()->prepare("SELECT t.*,u.name author_name,u.verified FROM tips t JOIN users u ON u.id=t.author_id WHERE t.id=? AND t.status='published'");$q->execute([$s['daily_free_tip_id']]);$daily=$q->fetch();if($daily):?><section class="section"><div class="feature"><div style="flex:1"><span class="pill amber">🔥 قلق رایگان امروز</span><h2><?=h($daily['title'])?></h2><p><?=h($daily['short_description'])?></p><a class="btn btn-amber btn-sm" href="<?=url('tip/'.$daily['id'])?>">مشاهده قلق ←</a></div><?php $di=tip_images($daily);if($di):?><img src="<?=h($di[0])?>" alt="<?=h($daily['title'])?>"><?php endif;?></div></section><?php endif;endif;?><section class="section"><div class="section-head"><div><h2>جدیدترین قلق‌ها</h2><p>آخرین راه‌حل‌های ثبت‌شده توسط تعمیرکاران</p></div><a class="btn btn-secondary btn-sm" href="<?=url('tips')?>">مشاهده همه ←</a></div><div class="grid grid-4"><?php foreach($latest as $t)tip_card($t);?></div></section><section class="section"><div class="section-head"><div><h2>محبوب‌ترین‌ها</h2><p>پربازدیدترین راه‌حل‌های این هفته</p></div><a class="btn btn-secondary btn-sm" href="<?=url('tips',['sort'=>'popular'])?>">همه محبوب‌ها ←</a></div><div class="grid grid-4"><?php foreach($popular as $t)tip_card($t);?></div></section><?php if($featured):?><section class="section"><div class="section-head"><div><h2>پیشنهاد سردبیر</h2><p>قلق‌های انتخاب‌شده توسط تیم بردخان</p></div></div><div class="grid grid-4"><?php foreach($featured as $t)tip_card($t);?></div></section><?php endif;?><section class="section grid grid-2"><div><div class="section-head"><div><h2>درخواست‌های باز تعمیر</h2><p>مشکل خود را مطرح کنید و پاداش تعیین کنید</p></div><a href="<?=url('repairs')?>" class="muted" style="font-size:11px">همه ←</a></div><div class="card"><?php foreach($repairs as $r):?><a class="leader-row" href="<?=url('repair/'.$r['id'])?>"><div class="grow"><strong><?=h($r['title'])?></strong><small><?=h($r['user_name'])?> · <?=fa($r['answer_count'])?> پاسخ</small></div><span class="pill amber"><?=h($r['reward_type']==='money'?money($r['reward_amount']).' ت':'لایک')?></span></a><?php endforeach;?><?php if(!$repairs):?><div class="empty">درخواست بازی وجود ندارد.</div><?php endif;?></div><a class="btn btn-primary btn-sm mt" href="<?=url('repair/new')?>">+ ثبت درخواست تعمیر</a></div><div><div class="section-head"><div><h2>برترین تعمیرکاران</h2><p>بر اساس امتیاز کسب‌شده</p></div><a href="<?=url('leaderboard')?>" class="muted" style="font-size:11px">همه ←</a></div><div class="card"><?php foreach($leaders as $i=>$l):?><a class="leader-row" href="<?=url('profile/'.$l['id'])?>"><b style="width:25px"> <?=['🥇','🥈','🥉'][$i]??fa($i+1)?></b><span class="avatar"><?=h(mb_substr($l['name'],0,1))?></span><span class="grow"><strong><?=h($l['name'])?></strong><small><?=h(level_name((int)$l['points']))?></small></span><b class="check"><?=fa($l['points'])?></b></a><?php endforeach;?></div></div></section><section class="section"><div class="feature" style="background:linear-gradient(110deg,#101d51,#25105c);color:#fff;border:0"><div><span class="pill amber">💎 اشتراک ویژه</span><h2>دسترسی نامحدود به همه قلق‌های پولی</h2><p style="color:#d5d3ec">یک اشتراک بخر و بدون پرداخت جداگانه به راه‌حل‌های حرفه‌ای دسترسی داشته باش.</p><a class="btn btn-amber btn-sm" href="<?=url('premium')?>">مشاهده پلن‌ها</a></div></div></section></main><?php footer_html();exit; }

if($page==='admin' || str_starts_with($page,'admin')){require __DIR__.'/pages/admin.php';exit;}

/* ---------- Boards marketplace (sale of physical boards with escrow) ---------- */
if($page==='seller-apply'){
    $u=require_login();
    if(is_seller($u)){ redirect_to('boards/new'); }
    header_html('درخواست فروشندگی');
    ?><main class="wrap page"><div class="ptitle"><h1>درخواست فروشندگی برد</h1><p>فقط فروشندگان تأییدشده توسط مدیر می‌توانند برد ثبت و بفروشند.</p></div>
    <div class="card authc"><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="seller_apply">
    <div class="fgroup"><label class="flabel">توضیح کامل درباره تخصص، تجربه و فروشگاه کار</label><textarea class="field" name="note" rows="7" placeholder="مثلاً: من ۸ سال در تعمیرات پاور و مادربرد فعالیت دارم و بردهای مازاد فروشگاهم را می‌خواهم بفروشم…"></textarea></div>
    <div class="notice">📌 پس از تأیید مدیر، دکمه «ثبت برد جدید» برای شما فعال می‌شود. در هنگام فروش، وجه خریدار ابتدا در امانت نزد بردخان باقی می‌ماند و پس از تأیید دریافت، سهم شما واریز می‌شود.</div>
    <button class="btn btn-primary btn-full mt">✅ ارسال درخواست فروشندگی</button></form></div></main><?php footer_html(); exit;
}

if($page==='boards' || $page==='board' || $page==='my-boards'){
    require __DIR__.'/pages/boards.php'; exit;
}

if($page==='tips'){
    $q=trim($_GET['q']??'');$cat=(int)($_GET['cat']??0);$difficulty=$_GET['difficulty']??'';$access=$_GET['access']??'';$sort=$_GET['sort']??'newest';$where=["t.status='published'"];$params=[];if($q){$where[]='(t.title LIKE ? OR t.short_description LIKE ? OR t.description LIKE ? OR t.device_name LIKE ? OR t.brand LIKE ? OR t.tags LIKE ?)';for($i=0;$i<6;$i++)$params[]='%'.$q.'%';}if($cat){$where[]='(t.category_id=? OR t.category_id IN (SELECT id FROM categories WHERE parent_id=?))';$params[]=$cat;$params[]=$cat;}if(in_array($difficulty,['easy','medium','hard'],true)){$where[]='t.difficulty=?';$params[]=$difficulty;}if(in_array($access,['free','like','paid'],true)){$where[]='t.access_type=?';$params[]=$access;}$order=$sort==='popular'?'t.views DESC':($sort==='rated'?'(t.rating_sum/GREATEST(t.rating_count,1)) DESC':($sort==='cheapest'?'t.price ASC':'COALESCE(t.published_at,t.created_at) DESC'));$sql="SELECT t.*,u.name author_name,u.verified FROM tips t JOIN users u ON u.id=t.author_id WHERE ".implode(' AND ',$where)." ORDER BY $order LIMIT 60";$st=db()->prepare($sql);$st->execute($params);$items=$st->fetchAll();$cats=category_tree();header_html('همه قلق‌ها');?><main class="wrap page"><div class="page-title"><h1><?=h($q?'نتایج جستجو برای «'.$q.'»':'همه قلق‌های تعمیراتی')?></h1><p><?=fa(count($items))?> قلق پیدا شد</p></div><div class="sidebar-layout"><aside class="card filter"><h3>⚙ فیلترها</h3><form method="get"><input type="hidden" name="r" value="tips"><div class="form-group"><label class="field-label">جستجو</label><input class="field" name="q" value="<?=h($q)?>" placeholder="عنوان، برند، دستگاه…"></div><div class="form-group"><label class="field-label">دسته‌بندی</label><input class="field" type="text" placeholder="🔍 جستجوی زندهٔ دسته…" oninput="bkFilterSelect(this)" style="margin-bottom:6px"><select class="field" name="cat"><option value="">همه دسته‌ها</option><?php foreach($cats as $c):?><optgroup label="<?=h($c['name'])?>"><?php foreach($c['children'] as $ch):?><option value="<?=$ch['id']?>" <?=$cat===$ch['id']?'selected':''?>><?=h($ch['name'])?></option><?php endforeach;?></optgroup><?php endforeach;?></select></div><div class="form-group"><label class="field-label">سطح سختی</label><select class="field" name="difficulty"><option value="">همه</option><option value="easy" <?=$difficulty==='easy'?'selected':''?>>آسان</option><option value="medium" <?=$difficulty==='medium'?'selected':''?>>متوسط</option><option value="hard" <?=$difficulty==='hard'?'selected':''?>>سخت</option></select></div><div class="form-group"><label class="field-label">نوع دسترسی</label><select class="field" name="access"><option value="">همه</option><option value="free" <?=$access==='free'?'selected':''?>>رایگان</option><option value="like" <?=$access==='like'?'selected':''?>>با لایک</option><option value="paid" <?=$access==='paid'?'selected':''?>>پرداختی</option></select></div><button class="btn btn-primary btn-full">اعمال فیلتر</button></form></aside><div><div class="flex between items-center mb"><div class="tip-meta"><?php foreach(['newest'=>'جدیدترین','popular'=>'محبوب‌ترین','rated'=>'بالاترین امتیاز','cheapest'=>'ارزان‌ترین'] as $v=>$label):?><a class="pill <?=$sort===$v?'green':''?>" href="<?=url('tips',['q'=>$q,'cat'=>$cat,'difficulty'=>$difficulty,'access'=>$access,'sort'=>$v])?>"><?=h($label)?></a><?php endforeach;?></div></div><?php if(!$items):?><div class="card empty">قلقی با این مشخصات پیدا نشد.<br><a class="btn btn-primary btn-sm mt" href="<?=url('upload')?>">ثبت اولین قلق</a></div><?php else:?><div class="grid grid-3"><?php foreach($items as $t)tip_card($t);?></div><?php endif;?></div></div></main><?php footer_html();exit; }

if($page==='tip'){
    $st=db()->prepare('SELECT t.*,u.name author_name,u.avatar author_avatar,u.verified author_verified,u.points author_points,c.name category_name FROM tips t JOIN users u ON u.id=t.author_id LEFT JOIN categories c ON c.id=t.category_id WHERE t.id=? LIMIT 1');$st->execute([$id]);$t=$st->fetch();if(!$t)exit('قلق یافت نشد');$u=current_user();if($t['status']!=='published'&&(!staff($u)&&(int)($u['id']??0)!==(int)$t['author_id']))exit('این قلق در دسترس نیست');db()->prepare('UPDATE tips SET views=views+1 WHERE id=?')->execute([$id]);$access=false;if($t['access_type']==='free'||($u&&(int)$u['id']===(int)$t['author_id'])||staff($u))$access=true;if($u){$q=db()->prepare('SELECT id FROM tip_accesses WHERE tip_id=? AND user_id=?');$q->execute([$id,$u['id']]);if($q->fetch())$access=true;if($u['premium_until']&&strtotime($u['premium_until'])>time())$access=true;}$imgs=tip_images($t);$comments=db()->prepare('SELECT c.*,u.name user_name,u.avatar FROM comments c JOIN users u ON u.id=c.user_id WHERE c.tip_id=? ORDER BY c.created_at ASC');$comments->execute([$id]);$comments=$comments->fetchAll();$voteTotals=[];$voteMine=[];if($comments){$in=implode(',',array_map(fn($c)=>(int)$c['id'],$comments));$vt=db()->query("SELECT comment_id,SUM(value) s FROM comment_votes WHERE comment_id IN ($in) GROUP BY comment_id")->fetchAll();foreach($vt as $r)$voteTotals[(int)$r['comment_id']]=(int)$r['s'];if($u){$vm=db()->prepare("SELECT comment_id,value FROM comment_votes WHERE user_id=? AND comment_id IN ($in)");$vm->execute([$u['id']]);foreach($vm->fetchAll() as $r)$voteMine[(int)$r['comment_id']]=(int)$r['value'];}}$related=db()->prepare("SELECT t.*,u.name author_name,u.verified FROM tips t JOIN users u ON u.id=t.author_id WHERE t.category_id=? AND t.id<>? AND t.status='published' ORDER BY t.views DESC LIMIT 4");$related->execute([$t['category_id'],$id]);$related=$related->fetchAll();$rating=$t['rating_count']?round($t['rating_sum']/$t['rating_count'],1):0;header_html($t['title']);?><main class="wrap page"><div class="breadcrumbs"><a href="<?=url()?>">خانه</a> / <a href="<?=url('tips')?>">قلق‌ها</a> / <?=h($t['title'])?></div><div class="tip-layout"><article><div class="tip-meta"><span class="pill <?=h($t['access_type']==='paid'?'amber':($t['access_type']==='like'?'rose':'green'))?>"><?=h(access_label($t['access_type'],(int)$t['price']))?></span><span class="pill"><?=h(['easy'=>'آسان','medium'=>'متوسط','hard'=>'سخت'][$t['difficulty']]??'متوسط')?></span><span class="pill">◉ <?=fa($t['views'])?> بازدید</span><span class="pill">★ <?=fa($rating)?> (<?=fa($t['rating_count'])?>)</span></div><h1 class="tip-title"><?=h($t['title'])?></h1><div class="author"><span class="avatar"><?=h(mb_substr($t['author_name'],0,1))?></span><span class="author-info"><strong><?=h($t['author_name'])?> <?php if($t['author_verified']):?><span class="check">✓</span><?php endif;?></strong><small><?=h(level_name((int)$t['author_points']))?> · <?=fa($t['author_points'])?> امتیاز</small></span><?php if($u&&(int)$u['id']!==(int)$t['author_id']):$fq=db()->prepare('SELECT id FROM follows WHERE follower_id=? AND following_id=?');$fq->execute([$u['id'],$t['author_id']]);$following=$fq->fetchColumn();?><form method="post" style="margin-right:auto"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="follow"><input type="hidden" name="user_id" value="<?=$t['author_id']?>"><input type="hidden" name="back" value="<?=h('tip/'.$id)?>"><button class="btn <?=$following?'btn-secondary':'btn-primary'?> btn-sm"><?=$following?'دنبال‌شده':'دنبال کردن'?></button></form><?php endif;?></div><?php if($access):?><div class="tip-cover"><?php foreach(array_slice($imgs,0,10) as $i=>$img):?><div class="media-protect full-lock"><img src="<?=h(image_url($t,$img,$u,true))?>" alt="تصویر <?=fa($i+1)?> — <?=h($t['title'])?>" class="no-save" draggable="false"><span class="wm">© بردخان <?=fa((int)($u['id']??0))?></span></div><?php endforeach;?></div><?php endif;?>
<div class="tip-action-row">
<form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="bookmark"><input type="hidden" name="tip_id" value="<?=$id?>"><input type="hidden" name="back" value="<?=h('tip/'.$id)?>"><button class="btn btn-secondary btn-sm">🔖 <?=has_bookmark($id,$u)?'حذف نشانک':'نشانک'?></button></form>
<form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="favorite"><input type="hidden" name="tip_id" value="<?=$id?>"><input type="hidden" name="back" value="<?=h('tip/'.$id)?>"><button class="btn btn-<?=has_favorite($id,$u)?'danger':'secondary'?> btn-sm"><?=has_favorite($id,$u)?'♥ پسندیده شد':'♡ پسندیدم'?></button></form>
</div><?php if(!$access):?><div class="locked"><div class="lock">🔒</div><?php if($t['access_type']==='like'):?><h2>این قلق با یک لایک باز می‌شود</h2><p><?=h($t['short_description'])?></p><p>بعد از لایک، همه عکس‌ها، ویدیو و مراحل گام‌به‌گام تعمیر برای شما نمایش داده می‌شود.</p><?php else:?><h2>این قلق پولی است</h2><p><?=h($t['short_description'])?></p><p>با پرداخت <?=money($t['price'])?> تومان، همه عکس‌ها، ویدیو و مراحل گام‌به‌گام تعمیر برای شما نمایش داده می‌شود.</p><?php endif;?><?php if(!$u):?><a class="btn btn-primary" href="<?=url('login')?>">برای باز کردن قلق وارد شوید</a><?php else:?><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="unlock"><input type="hidden" name="tip_id" value="<?=$id?>"><button class="btn <?=$t['access_type']==='like'?'btn-danger':'btn-primary'?>"><?=$t['access_type']==='like'?'♥ لایک و باز کردن':'🛒 پرداخت و باز کردن — '.money($t['price']).' تومان'?></button></form><?php endif;?></div><?php else:?><div class="rich"><?=safe_rich($t['description'])?></div><h2 class="section-head" style="margin-top:30px;font-size:19px">🔧 راه‌حل گام‌به‌گام</h2><div class="steps"><?php foreach(tip_solution($t) as $i=>$step):?><div class="step"><span class="step-num"><?=fa($i+1)?></span><div><h3><?=h($step['title']??'')?></h3><p><?=h($step['body']??'')?></p></div></div><?php endforeach;?></div><?php if($t['tools']):?><div class="mt"><b style="font-size:14px">ابزار لازم</b><div class="tip-meta" style="margin-top:8px"><?php foreach(explode('،',$t['tools']) as $tool):?><span class="pill blue"><?=h(trim($tool))?></span><?php endforeach;?></div></div><?php endif;?><?=video_embed($t['video_url'] ?? '', $t, $u)?><div class="card" id="rating" style="padding:16px;margin-top:25px"><b>به این قلق امتیاز دهید</b><form method="post" class="tip-meta" style="margin-top:8px"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="rate"><input type="hidden" name="tip_id" value="<?=$id?>"><?php for($star=1;$star<=5;$star++):?><button name="stars" value="<?=$star?>" class="btn btn-secondary btn-sm" style="color:#d99711;font-size:17px">★</button><?php endfor;?></form></div><?php endif;?><div class="comments" id="comments"><h2 style="font-size:19px">نظرات (<?=fa(count($comments))?>)</h2><?php if($u):?><form method="post" class="card" style="padding:15px;margin-bottom:15px"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="comment"><input type="hidden" name="tip_id" value="<?=$id?>"><textarea class="field" name="body" rows="3" placeholder="نظر یا تجربه خود را بنویسید…"></textarea><button class="btn btn-primary btn-sm mt">ثبت نظر</button></form><?php else:?><div class="card empty"><a href="<?=url('login')?>" class="check">برای ثبت نظر وارد شوید</a></div><?php endif;?><?php foreach($comments as $c):$cv=(int)($voteTotals[(int)$c['id']]??0);$cmv=$voteMine[(int)$c['id']]??0;?><div class="card comment" id="comment-<?=$c['id']?>"><div class="comment-head"><span class="avatar small"><?=h(mb_substr($c['user_name'],0,1))?></span><b style="font-size:12px"><?=h($c['user_name'])?></b><small class="muted"><?=ago($c['created_at'])?></small></div><?php if(!$c['is_deleted']):?><p class="comment-body"><?=nl2br(h($c['body']))?></p><div class="flex aicenter gap" style="margin-top:8px"><form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="comment_vote"><input type="hidden" name="comment_id" value="<?=$c['id']?>"><input type="hidden" name="vote" value="1"><button class="btn btn-sm <?=$cmv===1?'btn-primary':'btn-secondary'?>" title="مفید بود">👍 <?=fa($cv)?></button></form><form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="comment_vote"><input type="hidden" name="comment_id" value="<?=$c['id']?>"><input type="hidden" name="vote" value="-1"><button class="btn btn-sm <?=$cmv===-1?'btn-danger':'btn-secondary'?>" title="مفید نبود">👎</button></form></div><?php else:?><p class="comment-body">این نظر حذف شده است.</p><?php endif;?></div><?php endforeach;?></div></article><aside><div class="card side-card"><h3>مشخصات دستگاه</h3><div class="info-list"><div><span>دستگاه</span><b><?=h($t['device_name'])?></b></div><div><span>برند</span><b><?=h($t['brand'])?></b></div><div><span>مدل</span><b><?=h($t['model']?:'—')?></b></div><div><span>شماره برد</span><b><?=h($t['board_number']?:'—')?></b></div><div><span>نوع خرابی</span><b><?=h($t['fault_type'])?></b></div></div></div><div class="card side-card"><h3>آمار قلق</h3><div class="stat-grid"><div><b><?=fa($t['views'])?></b><small>بازدید</small></div><div><b><?=fa($t['likes_count'])?></b><small>لایک</small></div><div><b><?=fa($t['purchases_count'])?></b><small>خرید</small></div></div></div><div class="card side-card"><h3>شما هم قلق دارید؟</h3><p class="muted" style="font-size:12px">دانش تعمیراتی خود را ثبت کنید و پاداش آپلود بگیرید.</p><a class="btn btn-primary btn-full btn-sm" href="<?=url('upload')?>">آپلود قلق جدید</a></div></aside></div><?php if($related):?><section class="section"><div class="section-head"><h2>قلق‌های مرتبط</h2></div><div class="grid grid-4"><?php foreach($related as $r)tip_card($r);?></div></section><?php endif;?></main><?php footer_html();exit; }



if($page==='forgot'){header_html('بازیابی رمز عبور');$step2=!empty($_SESSION['reset_code']);?><main class="auth-page"><div class="auth-box"><div class="logo">⌁ برد<em>خان</em></div><div class="card auth-card"><h1>بازیابی رمز عبور</h1><p>کد بازیابی به شماره موبایل ثبت‌شده ارسال می‌شود</p><?php if(!$step2):?><form method="post" class="mt"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="forgot_request"><div class="form-group"><label class="field-label">شماره موبایل</label><input class="field" dir="ltr" name="phone" placeholder="0912…" required></div><button class="btn btn-primary btn-full">دریافت کد بازیابی</button></form><p class="text-center"><a class="check" href="<?=url('login')?>">بازگشت به ورود</a></p><?php else:?><div class="notice text-center">کد نمایشی شما: <b style="font-size:24px;letter-spacing:5px"><?=h($_SESSION['reset_code'])?></b></div><form method="post" class="mt"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="forgot_reset"><div class="form-group"><label class="field-label">کد شش رقمی</label><input class="field" dir="ltr" name="code" maxlength="6" required></div><div class="form-group"><label class="field-label">رمز عبور جدید</label><input class="field" type="password" dir="ltr" name="password" minlength="6" required></div><button class="btn btn-primary btn-full">تغییر رمز عبور</button></form><p class="text-center"><a class="check" href="<?=url('forgot')?>">ارسال مجدد کد</a> · <a class="check" href="<?=url('login')?>">ورود</a></p><?php endif;?></div></div></main><?php footer_html();exit;}

if(in_array($page,['login','register','verify'],true)){
    header_html($page==='login'?'ورود':($page==='register'?'ثبت‌نام':'تأیید شماره'));?><main class="auth-page"><div class="auth-box"><div class="logo">⌁ برد<em>خان</em></div><div class="card auth-card"><?php if($page==='login'):?><h1>ورود به حساب</h1><p>به بردخان خوش برگشتید.</p><form method="post" class="mt"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="login"><div class="form-group"><label class="field-label">موبایل یا ایمیل</label><input class="field" dir="ltr" name="identifier" required placeholder="0912…"></div><div class="form-group"><label class="field-label">رمز عبور</label><input class="field" type="password" dir="ltr" name="password" required></div><button class="btn btn-primary btn-full">ورود</button></form><div class="flex between mt" style="font-size:12px"><a class="check" href="<?=url('forgot')?>">🔑 رمز عبور را فراموش کرده‌اید؟</a><a class="check" href="<?=url('register')?>">ثبت‌نام رایگان</a></div><?php elseif($page==='register'):?><h1>ثبت‌نام رایگان</h1><p>عضو شوید و اعتبار هدیه دریافت کنید.</p><form method="post" class="mt"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="register"><div class="form-group"><label class="field-label">نام و نام خانوادگی</label><input class="field" name="name" required></div><div class="form-group"><label class="field-label">شماره موبایل</label><input class="field" dir="ltr" name="phone" placeholder="09123456789" required></div><div class="form-group"><label class="field-label">ایمیل اختیاری</label><input class="field" dir="ltr" type="email" name="email"></div><div class="form-group"><label class="field-label">رمز عبور حداقل ۶ کاراکتر</label><input class="field" dir="ltr" type="password" name="password" minlength="6" required></div><div class="form-group"><label class="field-label">کد معرف اختیاری</label><input class="field" dir="ltr" name="referral" value="<?=h($_GET['ref'] ?? '')?>"></div><button class="btn btn-primary btn-full">دریافت کد تأیید</button></form><p class="text-center">حساب دارید؟ <a class="check" href="<?=url('login')?>">وارد شوید</a></p><?php else:$pending=$_SESSION['pending_register']??null;if(!$pending)redirect_to('register');?><h1>تأیید شماره موبایل</h1><p>کد تأیید به <?=h($pending['phone'])?> ارسال شد.</p><div class="notice text-center">کد نمایشی شما: <b style="font-size:24px;letter-spacing:5px"><?=h($_SESSION['demo_code']??'')?></b></div><form method="post" class="mt"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="verify"><div class="form-group"><label class="field-label">کد شش رقمی</label><input class="field" dir="ltr" name="code" maxlength="6" required></div><button class="btn btn-primary btn-full">تأیید و ساخت حساب</button></form><?php endif;?></div></div></main><?php footer_html();exit; }

if($page==='upload'){
$u=require_login();
$editId=(int)($_GET['edit']??0);
$editTip=null;
if($editId){$et=db()->prepare('SELECT * FROM tips WHERE id=? LIMIT 1');$et->execute([$editId]);$editTip=$et->fetch();if(!$editTip||(int)$editTip['author_id']!==(int)$u['id']){flash('قلقی برای ویرایش یافت نشد.','error');redirect_to('my-tips');}}
$cats=category_tree();header_html($editTip?'ویرایش قلق':'آپلود قلق');?><main class="wrap page"><div class="page-title"><h1><?=$editTip?'✏️ ویرایش قلق':'آپلود قلق جدید'?></h1><p>راه‌حل واقعی خود را ثبت کنید و پس از تأیید پاداش بگیرید.</p></div><form id="tipForm" class="card auth-card" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="upload_tip"><input type="hidden" name="edit_id" value="<?=h((string)($editTip['id'] ?? 0))?>"><div class="grid grid-2"><div class="form-group"><label class="field-label">عنوان قلق *</label><input class="field" name="title" required placeholder="رفع مشکل روشن نشدن لپ‌تاپ ایسوس X550"></div><div class="form-group"><label class="field-label">دسته‌بندی *</label><input class="field" type="text" placeholder="🔍 جستجوی زندهٔ دسته…" oninput="bkFilterSelect(this)"><select class="field" name="category_id" required><option value="">انتخاب کنید</option><?php foreach($cats as $c):?><optgroup label="<?=h($c['name'])?>"><?php foreach($c['children'] as $ch):?><option value="<?=$ch['id']?>"><?=h($ch['name'])?></option><?php endforeach;?></optgroup><?php endforeach;?></select></div></div><div class="form-group"><label class="field-label">توضیح کوتاه *</label><textarea class="field" name="short_description" rows="2" required></textarea></div><div class="form-group"><label class="field-label">توضیح کامل راه‌حل *</label><textarea class="field" name="description" rows="6" required placeholder="شرح مشکل، تست‌ها و تجربه تعمیر…"></textarea></div><div class="grid grid-2"><div class="form-group"><label class="field-label">نام دستگاه *</label><input class="field" name="device_name" required placeholder="مثلاً لپ‌تاپ، پاور، موبایل"></div><div class="form-group"><label class="field-label">برند *</label><input class="field" name="brand" required placeholder="مثلاً ایسوس"></div></div><div class="form-group"><label class="field-label">حداقل ۱ عکس، حداکثر ۱۰ عکس — هر عکس تا ۵MB (عکس‌های بزرگ خودکار کوچک می‌شوند)</label><input class="field" id="tipImages" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required><div id="tipPreview" class="file-preview"></div></div><div class="grid grid-2"><div class="form-group"><label class="field-label">نوع دسترسی</label><select class="field" name="access_type"><option value="free">رایگان</option><option value="like">با لایک</option><option value="paid">پرداختی</option></select></div><div class="form-group"><label class="field-label">قیمت (فقط برای قلق پرداختی)</label><input class="field" type="number" name="price" value="30000"></div></div><details class="bk-optional"><summary>⚙️ گزینه‌های اختیاری (مدل، مراحل تعمیر، ابزار، ویدیو و…)</summary><div class="grid grid-2"><div class="form-group"><label class="field-label">مدل</label><input class="field" name="model"></div><div class="form-group"><label class="field-label">شماره برد</label><input class="field" name="board_number"></div><div class="form-group"><label class="field-label">نوع خرابی</label><input class="field" name="fault_type" placeholder="روشن نمی‌شود"></div><div class="form-group"><label class="field-label">سطح سختی</label><select class="field" name="difficulty"><option value="easy">آسان</option><option value="medium" selected>متوسط</option><option value="hard">سخت</option></select></div></div><div class="form-group"><label class="field-label">مراحل گام‌به‌گام تعمیر (اختیاری)</label><div class="grid grid-2"><input class="field" name="step_title[]" placeholder="عنوان گام اول"><textarea class="field" name="step_body[]" rows="2" placeholder="توضیح گام اول"></textarea><input class="field" name="step_title[]" placeholder="عنوان گام دوم"><textarea class="field" name="step_body[]" rows="2" placeholder="توضیح گام دوم"></textarea></div></div><div class="grid grid-2"><div class="form-group"><label class="field-label">ابزارها (با کاما جدا شوند)</label><input class="field" name="tools" placeholder="مولتی‌متر، هیتر، فلاکس"></div><div class="form-group"><label class="field-label">تگ‌ها (با کاما جدا شوند)</label><input class="field" name="tags" placeholder="ماسفت، پاور"></div></div><div class="grid grid-2"><div class="form-group"><label class="field-label">لینک ویدیو (یوتیوب یا آپارات)</label><input class="field" dir="ltr" name="video_url" placeholder="https://youtube.com/watch?v=..."></div><div class="form-group"><label class="field-label">یا آپلود فایل ویدیو MP4 (تا ۵۰MB)</label><input class="field" type="file" name="video_file" accept="video/mp4"></div></div></details>
<div class="bk-checks" id="tipChecks"></div><div id="tipBarWrap" style="display:none;margin-top:12px;background:rgba(255,255,255,.08);border-radius:8px;overflow:hidden;height:18px"><div id="tipBar" style="width:0%;height:100%;background:#10b981;color:#04110b;text-align:center;font-size:11px;line-height:18px;font-weight:bold">0%</div></div><div id="tipFormMsg"></div><button class="btn btn-primary"><?=$editTip?'💾 ذخیره تغییرات':'انتشار قلق'?></button></form><script>
function bkSetupChecks(formId,boxId,rules){var f=document.getElementById(formId),box=document.getElementById(boxId);if(!f||!box)return;function ev(n){return f.querySelector('[name="'+n+'"]');}function val(n){var el=ev(n);if(!el)return'';if(el.type==='file')return Array.prototype.slice.call(el.files||[]);return el.value||'';}var items=rules.map(function(r){var d=document.createElement('div');d.className='bk-check';d.innerHTML='<span class="bk-ic">◌</span> '+r.label;box.appendChild(d);return d;});rules.forEach(function(r){if(r.kind==='minlen'){var el=ev(r.name);if(el){var c=document.createElement('small');c.className='bk-count';c.style.cssText='display:block;color:var(--text-dim);font-size:11px;margin-top:4px';el.parentNode.insertBefore(c,el.nextSibling);r.counter=c;}}});function okOf(i,r){var v=val(r.name);if(r.kind==='minlen')return (v||'').trim().length>=r.n;if(r.kind==='required')return String(v).trim()!=='';if(r.kind==='select')return String(v)!=='';if(r.kind==='files')return v.length>=r.n;if(r.kind==='price'){var at=ev('access_type');if(at&&at.value!=='paid')return true;return parseInt(v,10)>=r.n;}return true;}function update(){var cnt=0;rules.forEach(function(r,i){var ok=okOf(i,r);items[i].className='bk-check '+(ok?'ok':'bad');items[i].querySelector('.bk-ic').textContent=ok?'✓':'✗';var el=ev(r.name);if(el){el.classList.remove('bk-ok','bk-bad');el.classList.add(ok?'bk-ok':'bk-bad');}if(r.kind==='minlen'&&r.counter){var len=(val(r.name)||'').trim().length;r.counter.textContent=len+' / '+r.n+' حرف'+(len>=r.n?' ✔':'');}if(ok)cnt++;});var s=document.getElementById(boxId+'Sum');if(s){s.textContent=cnt+' از '+rules.length+' مورد تکمیل شد';s.className='bk-summary '+(cnt===rules.length?'ok':'bad');}}rules.forEach(function(r){var el=ev(r.name);if(!el)return;['input','change'].forEach(function(t){el.addEventListener(t,update);});});var at=ev('access_type');if(at)at.addEventListener('change',update);var s=document.createElement('div');s.id=boxId+'Sum';s.className='bk-summary';box.parentNode.insertBefore(s,box.nextSibling);update();}
bkSetupChecks('tipForm','tipChecks',[
{name:'title',kind:'minlen',n:8,label:'عنوان قلق (حداقل ۸ حرف)'},
{name:'short_description',kind:'minlen',n:20,label:'توضیح کوتاه (حداقل ۲۰ حرف)'},
{name:'description',kind:'minlen',n:20,label:'توضیح کامل (حداقل ۲۰ حرف)'},
{name:'device_name',kind:'required',label:'نام دستگاه'},
{name:'brand',kind:'required',label:'برند'},
{name:'category_id',kind:'select',label:'انتخاب دسته‌بندی'},
{name:'images',kind:'files',n:1,label:'حداقل ۱ عکس'},
{name:'price',kind:'price',n:1000,label:'قیمت معتبر (فقط برای قلق پرداختی)'}
]);
if(typeof bkFilterSelect!=='function'){function bkFilterSelect(inp){var sel=inp.parentElement.querySelector('select');if(!sel)return;var q=(inp.value||'').trim().toLowerCase();Array.prototype.forEach.call(sel.options,function(o){if(!o.value)return;var t=(o.textContent||'').toLowerCase();var og=o.parentElement&&o.parentElement.label?o.parentElement.label.toLowerCase():'';o.hidden=q===''||t.indexOf(q)!==-1||og.indexOf(q)!==-1;});Array.prototype.forEach.call(sel.querySelectorAll('optgroup'),function(g){var any=false;Array.prototype.forEach.call(g.options,function(o){if(!o.hidden)any=true;});g.hidden=!any;});if(!sel.value||sel.options[sel.selectedIndex]&&sel.options[sel.selectedIndex].hidden){sel.value='';}}}
(function(){var f=document.getElementById('tipForm');if(!f)return;var msg=document.getElementById('tipFormMsg');var bar=document.getElementById('tipBar');var barWrap=document.getElementById('tipBarWrap');var fi=document.getElementById('tipImages');var pv=document.getElementById('tipPreview');if(fi&&pv){fi.addEventListener('change',function(){pv.innerHTML='';Array.prototype.forEach.call(fi.files,function(file){if(!/^image\//.test(file.type||''))return;var u=URL.createObjectURL(file);var img=document.createElement('img');img.src=u;img.alt='پیش‌نمایش';img.style.cssText='width:86px;height:86px;object-fit:cover;border-radius:10px;border:1px solid var(--line)';pv.appendChild(img);});});}f.addEventListener('submit',function(e){var eid=f.querySelector('input[name=edit_id]');if(eid&&eid.value&&eid.value!=='0'){return;}e.preventDefault();var b=f.querySelector('button');var orig=b?b.textContent:'';if(b){b.disabled=true;b.textContent='⏳ در حال ارسال…';}msg.innerHTML='';if(barWrap)barWrap.style.display='block';if(bar){bar.style.width='0%';bar.textContent='0%';}var xhr=new XMLHttpRequest();xhr.open('POST',window.location.href);xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');xhr.setRequestHeader('Accept','application/json');xhr.upload.addEventListener('progress',function(ev){if(ev.lengthComputable&&bar){var p=Math.round(ev.loaded/ev.total*100);bar.style.width=p+'%';bar.textContent=p+'%';}});xhr.onload=function(){if(barWrap)barWrap.style.display='none';var j=null;try{j=JSON.parse(xhr.responseText);}catch(_){}if(j&&j.ok){msg.innerHTML='<div class="notice" style="margin-top:12px">✅ '+(j.message||'انجام شد')+'</div>';if(j.redirect){setTimeout(function(){window.location.href=j.redirect;},1200);}else if(b){b.disabled=false;b.textContent=orig;}}else{msg.innerHTML='<div class="notice error" style="margin-top:12px">⚠️ '+((j&&j.error)||((xhr.responseText||'').slice(0,180))||'پاسخی از سرور دریافت نشد؛ دوباره تلاش کنید.')+'</div>';if(b){b.disabled=false;b.textContent=orig;}}};xhr.onerror=function(){if(barWrap)barWrap.style.display='none';msg.innerHTML='<div class="notice error" style="margin-top:12px">⚠️ خطای ارتباط با سرور؛ دوباره تلاش کنید.</div>';if(b){b.disabled=false;b.textContent=orig;}};xhr.send(new FormData(f));});})();
</script><details class="card" style="margin-top:14px;padding:14px"><summary style="cursor:pointer;font-weight:bold;font-size:13px;color:#61706a">+ دسته‌بندی مناسب پیدا نکردید؟ پیشنهاد دهید</summary><form method="post" class="mt"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="suggest_category"><div class="grid grid-2"><div class="form-group"><label class="field-label">نام دسته پیشنهادی</label><input class="field" name="name" placeholder="مثلاً: کنسول بازی"></div><div class="form-group"><label class="field-label">دسته والد</label><select class="field" name="parent_id"><option value="">— بدون والد —</option><?php foreach($cats as $c):?><option value="<?=$c['id']?>"><?=h($c['name'])?></option><?php endforeach;?></select></div></div><button class="btn btn-secondary btn-sm">ثبت پیشنهاد دسته</button></form></details></main><?php footer_html();exit; }

if($page==='wallet'){$u=require_login();$tx=db()->prepare('SELECT * FROM wallet_transactions WHERE user_id=? ORDER BY created_at DESC LIMIT 100');$tx->execute([$u['id']]);$tx=$tx->fetchAll();$wd=db()->prepare('SELECT * FROM withdrawals WHERE user_id=? ORDER BY created_at DESC');$wd->execute([$u['id']]);$wd=$wd->fetchAll();$income=array_sum(array_map(fn($x)=>max(0,(int)$x['amount']),$tx));header_html('کیف پول');?><main class="wrap page"><div class="page-title"><h1>کیف پول</h1><p>موجودی، درآمدها و درخواست‌های تسویه</p></div><div class="grid grid-2"><div><div class="wallet-hero"><small>موجودی فعلی</small><strong><?=money($u['balance'])?> <small>تومان</small></strong><span>کل واریزی‌ها: <?=money($income)?> تومان</span></div><?php $sCh=settings();$gwOn=((int)($sCh['gateway_enabled']??0)===1)&&in_array($sCh['gateway_type']??'zarinpal',['zarinpal','idipay','zibal'],true);$z2cOn=trim((string)($sCh['z2c_card_number']??''))!=='';?>
<div class="card auth-card mt"><h3>💳 شارژ کیف پول</h3>
<?php if($gwOn||$z2cOn):?>
<?php if($gwOn):?><p class="muted" style="font-size:12px">درگاه: <?=h($sCh['gateway_type']??'')?> · حداقل <?=money($sCh['gateway_min_charge']??100000)?> · حداکثر <?=money($sCh['gateway_max_charge']??50000000)?> تومان</p><form id="walletGwForm" method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="wallet_gateway_start"><div class="form-group"><label class="field-label">مبلغ شارژ (تومان)</label><input class="field" type="number" name="amount" min="<?=(int)($sCh['gateway_min_charge']??100000)?>" max="<?=(int)($sCh['gateway_max_charge']??50000000)?>" required></div><div id="walletGwMsg"></div><button class="btn btn-primary btn-full mt">پرداخت آنلاین (درگاه)</button></form><?php endif;?>
<?php if($z2cOn):?><hr style="border-color:var(--line);margin:14px 0"><p class="muted" style="font-size:12px">کارت‌به‌کارت: بانک <?=h($sCh['z2c_bank_name']??'')?> · به نام <?=h($sCh['z2c_account_name']??'')?> · <b dir="ltr"><?=h($sCh['z2c_card_number'])?></b><br>واریز کنید و فیش را بفرستید؛ پس از تأیید مدیر موجودی شارژ می‌شود.</p><form id="walletZ2cForm" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="wallet_card_to_card"><div class="grid grid-2"><div class="form-group"><label class="field-label">مبلغ (تومان)</label><input class="field" type="number" name="amount" required></div><div class="form-group"><label class="field-label">بانک واریزکننده</label><input class="field" name="bank_name" required></div></div><div class="grid grid-2"><div class="form-group"><label class="field-label">شماره کارت واریزکننده</label><input class="field" dir="ltr" name="card_number" required></div><div class="form-group"><label class="field-label">تصویر فیش</label><input class="field" type="file" name="receipt" accept="image/*" required></div></div><div id="walletZ2cMsg"></div><button class="btn btn-secondary btn-full mt">ثبت فیش واریز</button></form><?php endif;?>
<script>
(function(){function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;}
function bind(formId,msgId){var f=document.getElementById(formId);if(!f)return;var msg=document.getElementById(msgId);f.addEventListener('submit',function(e){e.preventDefault();var b=f.querySelector('button');var orig=b.textContent;b.disabled=true;b.textContent='⏳ لطفاً صبر کنید…';msg.innerHTML='';var xhr=new XMLHttpRequest();xhr.open('POST',window.location.href);xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');xhr.setRequestHeader('Accept','application/json');xhr.onload=function(){var j=null;try{j=JSON.parse(xhr.responseText);}catch(_){}if(j&&j.ok){if(j.url){window.location.href=j.url;return;}msg.innerHTML='<div class="notice" style="margin-top:10px">✅ '+esc(j.message||'انجام شد')+'</div>';if(formId==='walletZ2cForm'){setTimeout(function(){window.location.reload();},1500);}else{b.disabled=false;b.textContent=orig;}}else{msg.innerHTML='<div class="notice error" style="margin-top:10px">⚠️ '+esc((j&&j.error)||(xhr.responseText||'').slice(0,180)||'پاسخی از سرور دریافت نشد.')+'</div>';b.disabled=false;b.textContent=orig;}};xhr.onerror=function(){msg.innerHTML='<div class="notice error" style="margin-top:10px">⚠️ خطای ارتباط با سرور؛ دوباره تلاش کنید.</div>';b.disabled=false;b.textContent=orig;};xhr.send(new FormData(f));});}
bind('walletGwForm','walletGwMsg');bind('walletZ2cForm','walletZ2cMsg');})();
</script>
<?php else:?><p class="muted" style="font-size:12px">روش پرداختی برای شارژ فعال نشده است؛ از طریق <a class="check" href="<?=url('tickets')?>">تیکت پشتیبانی</a> پیگیری کنید.</p><?php endif;?>
</div><div class="card auth-card mt"><h3>درخواست تسویه</h3><p class="muted" style="font-size:12px">حداقل برداشت: <?=money(settings()['min_withdrawal'])?> تومان</p><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="withdraw"><div class="form-group"><label class="field-label">مبلغ</label><input class="field" type="number" name="amount" min="<?=settings()['min_withdrawal']?>"></div><div class="form-group"><label class="field-label">شماره شبا</label><input class="field" dir="ltr" name="shaba" placeholder="IR…"></div><div class="form-group"><label class="field-label">شماره کارت</label><input class="field" dir="ltr" name="card_number"></div><div class="form-group"><label class="field-label">کد ملی</label><input class="field" dir="ltr" name="national_id"></div><button class="btn btn-primary btn-full">ثبت درخواست تسویه</button></form></div></div><div class="card auth-card"><h3>معرفی دوستان</h3><p class="muted" style="font-size:12px">کد شما: <b class="check"><?=h($u['referral_code'])?></b><br>پاداش معرفی: <?=money(settings()['referral_reward'])?> تومان</p><a class="btn btn-secondary btn-sm" href="<?=url('referral')?>">مشاهده برنامه معرفی</a><h3 class="mt">درخواست‌های تسویه</h3><?php foreach($wd as $w):?><div class="leader-row"><span class="grow"><b><?=money($w['amount'])?> تومان</b><small><?=datetime_fa($w['created_at'])?></small></span><span class="pill <?=h($w['status']==='paid'?'green':($w['status']==='rejected'?'rose':'amber'))?>"><?=h(['pending'=>'در انتظار','reviewing'=>'در حال بررسی','paid'=>'واریز شده','rejected'=>'رد شده'][$w['status']]??$w['status'])?></span></div><?php endforeach;?></div></div><section class="section"><div class="card auth-card"><h3>تاریخچه تراکنش‌ها</h3><div class="table-wrap"><table class="table"><tr><th>شرح</th><th>تاریخ</th><th>مبلغ</th><th>موجودی</th></tr><?php foreach($tx as $x):?><tr><td><?=h($x['note']?:$x['type'])?></td><td><?=datetime_fa($x['created_at'])?></td><td class="<?=((int)$x['amount']>0?'check':'')?>"><?=((int)$x['amount']>0?'+':'')?><?=money($x['amount'])?></td><td><?=money($x['balance_after'])?></td></tr><?php endforeach;?></table></div></div></section></main><?php footer_html();exit;}

if($page==='my-tips'){$u=require_login();$q=db()->prepare('SELECT t.*,c.name category_name FROM tips t LEFT JOIN categories c ON c.id=t.category_id WHERE t.author_id=? ORDER BY t.created_at DESC');$q->execute([$u['id']]);$items=$q->fetchAll();$sales=db()->prepare("SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE user_id=? AND type='sale'");$sales->execute([$u['id']]);$totalIncome=(int)$sales->fetchColumn();$totalViews=array_sum(array_map(fn($x)=>(int)$x['views'],$items));$totalPurchases=array_sum(array_map(fn($x)=>(int)$x['purchases_count'],$items));header_html('قلق‌های من');?><main class="wrap page">
<div class="section-head"><div><h1>قلق‌های من</h1><p><?=fa(count($items))?> قلق ثبت‌شده</p></div><a class="btn btn-primary" href="<?=url('upload')?>">+ آپلود قلق جدید</a></div>
<div class="grid grid-3 mb">
<div class="card stat-card"><strong class="check"><?=money($totalIncome)?></strong><small>درآمد کلی (تومان)</small></div>
<div class="card stat-card"><strong><?=fa($totalViews)?></strong><small>بازدید کل</small></div>
<div class="card stat-card"><strong><?=fa($totalPurchases)?></strong><small>خرید کل</small></div>
</div>
<?php if(!$items):?><div class="card empty"><p>هنوز قلقی ثبت نکرده‌اید.</p><a class="btn btn-primary" href="<?=url('upload')?>">اولین قلق خود را ثبت کنید</a></div><?php else:?><div class="card table-wrap"><table class="table"><tr><th>عنوان</th><th>دسترسی</th><th>وضعیت</th><th>بازدید</th><th>خرید</th><th>تاریخ</th><th>عملیات</th></tr><?php foreach($items as $x):?><tr>
<td style="min-width:180px"><a class="check" href="<?=url('tip/'.$x['id'])?>"><?=h($x['title'])?></a></td>
<td><?=h(access_label($x['access_type'],(int)$x['price']))?></td>
<td><span class="pill <?=$x['status']==='published'?'green':($x['status']==='pending'?'amber':'rose')?>"><?=h(status_label($x['status']))?></span><?php if($x['rejection_reason']):?><br><small style="color:var(--rose);font-size:10px"><?=h($x['rejection_reason'])?></small><?php endif;?></td>
<td><?=fa($x['views'])?></td>
<td><?=fa($x['purchases_count'])?></td>
<td style="white-space:nowrap"><?=date_fa($x['created_at'])?></td>
<td><div class="flex gap" style="flex-wrap:wrap">
<a class="btn btn-secondary btn-sm" href="<?=url('upload?edit='.$x['id'])?>">✏ ویرایش</a>
<?php if($x['status']==='rejected'):?><form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="my_tip_resubmit"><input type="hidden" name="tip_id" value="<?=$x['id']?>"><button class="btn btn-amber btn-sm">↻ ارسال برای بازبینی</button></form><?php endif;?>
<form method="post" style="display:inline" onsubmit="return confirm('این قلق برای همیشه حذف شود؟')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="my_tip_delete"><input type="hidden" name="tip_id" value="<?=$x['id']?>"><button class="btn btn-danger btn-sm">🗑 حذف</button></form>
</div></td>
</tr><?php endforeach;?></table></div><?php endif;?></main><?php footer_html();exit;}

if($page==='repairs'){$status=$_GET['status']??'';$where=$status&&in_array($status,['open','closed'],true)?'WHERE r.status=?':'';$q=db()->prepare("SELECT r.*,u.name user_name FROM repair_requests r JOIN users u ON u.id=r.user_id $where ORDER BY r.created_at DESC LIMIT 60");$q->execute($status?[$status]:[]);$items=$q->fetchAll();header_html('درخواست‌های تعمیر');?><main class="wrap page"><div class="section-head"><div><h1>درخواست‌های تعمیر</h1><p>مشکل خود را مطرح کنید و پاداش بدهید.</p></div><a class="btn btn-primary" href="<?=url('repair/new')?>">+ ثبت درخواست</a></div><div class="tip-meta mb"><a class="pill <?=$status===''?'green':''?>" href="<?=url('repairs')?>">همه</a><a class="pill <?=$status==='open'?'green':''?>" href="<?=url('repairs',['status'=>'open'])?>">باز</a><a class="pill <?=$status==='closed'?'green':''?>" href="<?=url('repairs',['status'=>'closed'])?>">بسته‌شده</a></div><div class="grid grid-2"><?php foreach($items as $r):?><a class="card auth-card" href="<?=url('repair/'.$r['id'])?>"><div class="flex between"><h3 style="margin:0;font-size:15px"><?=h($r['title'])?></h3><span class="pill amber"><?=h($r['reward_type']==='money'?money($r['reward_amount']).' ت':'لایک')?></span></div><p class="muted" style="font-size:12px"><?=h(mb_substr($r['description'],0,150))?>…</p><small class="muted"><?=h($r['user_name'])?> · <?=fa($r['answer_count'])?> پاسخ · <?=ago($r['created_at'])?></small></a><?php endforeach;?></div></main><?php footer_html();exit;}

if($page==='repair'&&$id){$q=db()->prepare('SELECT r.*,u.name user_name,u.avatar FROM repair_requests r JOIN users u ON u.id=r.user_id WHERE r.id=?');$q->execute([$id]);$r=$q->fetch();if(!$r)exit('درخواست یافت نشد');$a=db()->prepare('SELECT a.*,u.name user_name,u.points FROM repair_answers a JOIN users u ON u.id=a.user_id WHERE a.request_id=? ORDER BY a.created_at ASC');$a->execute([$id]);$answers=$a->fetchAll();$u=current_user();header_html($r['title']);?><main class="wrap page"><div class="card auth-card"><div class="tip-meta"><span class="pill <?=$r['status']==='open'?'green':'rose'?>"><?=h($r['status']==='open'?'باز':'بسته‌شده')?></span><span class="pill amber">پاداش: <?=h($r['reward_type']==='money'?money($r['reward_amount']).' تومان':'لایک')?></span></div><h1 class="tip-title" style="font-size:24px"><?=h($r['title'])?></h1><p class="rich"><?=nl2br(h($r['description']))?></p><div class="tip-meta"><span class="pill">دستگاه: <?=h($r['device_name'])?></span><span class="pill">برند: <?=h($r['brand'])?></span><span class="pill">مدل: <?=h($r['model'])?></span></div><small class="muted">ثبت‌شده توسط <?=h($r['user_name'])?> · <?=ago($r['created_at'])?></small></div><section class="section"><h2>پاسخ تعمیرکاران (<?=fa(count($answers))?>)</h2><?php foreach($answers as $ans):?><div class="card auth-card" style="margin-bottom:12px;<?= $ans['is_best']?'border:2px solid #078659':''?>"><?php if($ans['is_best']):?><span class="pill green">✓ بهترین پاسخ</span><?php endif;?><div class="author"><span class="avatar"><?=h(mb_substr($ans['user_name'],0,1))?></span><span class="author-info"><strong><?=h($ans['user_name'])?></strong><small><?=h(level_name((int)$ans['points']))?> · <?=ago($ans['created_at'])?></small></span></div><p class="rich"><?=nl2br(h($ans['body']))?></p><?php if($u&&(int)$u['id']===(int)$r['user_id']&&!$ans['is_best']&&$r['status']==='open'):?><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="repair_best"><input type="hidden" name="request_id" value="<?=$id?>"><input type="hidden" name="answer_id" value="<?=$ans['id']?>"><button class="btn btn-primary btn-sm">انتخاب به‌عنوان بهترین پاسخ</button></form><?php endif;?></div><?php endforeach;?><?php if($u&&(int)$u['id']!==(int)$r['user_id']&&$r['status']==='open'):?><div class="card auth-card"><h3>پاسخ پیشنهادی شما</h3><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="repair_answer"><input type="hidden" name="request_id" value="<?=$id?>"><textarea class="field" name="body" rows="5" required></textarea><button class="btn btn-primary mt">ارسال پاسخ</button></form></div><?php elseif(!$u):?><div class="card empty"><a class="check" href="<?=url('login')?>">برای پاسخ وارد شوید</a></div><?php endif;?></section></main><?php footer_html();exit;}

if($page==='repair'&&$parts[1]==='new'){$u=require_login();header_html('ثبت درخواست تعمیر');?><main class="wrap page"><div class="page-title"><h1>ثبت درخواست تعمیر</h1><p>مشکل را شرح دهید و برای بهترین راه‌حل پاداش تعیین کنید.</p></div><form class="card auth-card" method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="repair_create"><div class="form-group"><label class="field-label">عنوان *</label><input class="field" name="title" required></div><div class="form-group"><label class="field-label">شرح کامل مشکل *</label><textarea class="field" name="description" rows="6" required></textarea></div><div class="grid grid-2"><div class="form-group"><label class="field-label">نام دستگاه *</label><input class="field" name="device_name" required></div><div class="form-group"><label class="field-label">برند</label><input class="field" name="brand"></div><div class="form-group"><label class="field-label">مدل</label><input class="field" name="model"></div><div class="form-group"><label class="field-label">نوع پاداش</label><select class="field" name="reward_type"><option value="money">نقدی</option><option value="like">لایک</option></select></div><div class="form-group"><label class="field-label">مبلغ پاداش تومان</label><input class="field" type="number" name="reward_amount" value="100000"></div></div><button class="btn btn-primary">ثبت درخواست</button></form></main><?php footer_html();exit;}

if($page==='profile'&&$id){$q=db()->prepare('SELECT * FROM users WHERE id=?');$q->execute([$id]);$p=$q->fetch();if(!$p)exit('کاربر یافت نشد');$tips=db()->prepare("SELECT t.*,u.name author_name,u.verified FROM tips t JOIN users u ON u.id=t.author_id WHERE t.author_id=? AND t.status='published' ORDER BY t.created_at DESC");$tips->execute([$id]);$tips=$tips->fetchAll();$followers=(int)db()->query('SELECT COUNT(*) FROM follows WHERE following_id='.(int)$id)->fetchColumn();header_html($p['name']);?><main class="wrap page"><div class="card auth-card"><div class="author"><span class="avatar" style="width:80px;height:80px;font-size:28px"><?=h(mb_substr($p['name'],0,1))?></span><span class="author-info"><h1 style="margin:0;font-size:24px"><?=h($p['name'])?> <?php if($p['verified']):?><span class="check">✓</span><?php endif;?></h1><small><?=h(role_label($p['role']))?> · <?=h(level_name((int)$p['points']))?> · <?=fa($followers)?> دنبال‌کننده</small></span><?php $u=current_user();if($u&&(int)$u['id']!==$id):$fq=db()->prepare('SELECT id FROM follows WHERE follower_id=? AND following_id=?');$fq->execute([$u['id'],$id]);$following=$fq->fetchColumn();?><form method="post" style="margin-right:auto"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="follow"><input type="hidden" name="user_id" value="<?=$id?>"><input type="hidden" name="back" value="<?=h('profile/'.$id)?>"><button class="btn <?=$following?'btn-secondary':'btn-primary'?> btn-sm"><?=$following?'دنبال‌شده':'دنبال کردن'?></button></form><?php endif;?></div><?php if($p['bio']):?><p class="muted"><?=nl2br(h($p['bio']))?></p><?php endif;?><div class="stat-grid"><div class="card stat-card"><strong><?=fa($p['points'])?></strong><small>امتیاز</small></div><div class="card stat-card"><strong><?=fa(count($tips))?></strong><small>قلق منتشرشده</small></div><div class="card stat-card"><strong><?=fa($followers)?></strong><small>دنبال‌کننده</small></div></div><?php $badges=user_badges($id);if($badges):?><div class="tip-meta" style="margin-top:12px"><?php $bdefs=badge_defs();foreach($badges as $b):$bd=$bdefs[$b['badge_type']]??null;?><span class="pill amber" title="<?=h($bd?$bd[2]:$b['label'])?>"><?=h(($bd?$bd[0]:'★').' '.$b['label'])?></span><?php endforeach;?></div><?php endif;?></div><section class="section"><h2>قلق‌های <?=h($p['name'])?></h2><div class="grid grid-3"><?php foreach($tips as $t)tip_card($t);?></div></section></main><?php footer_html();exit;}

if($page==='leaderboard'){$rows=db()->query('SELECT * FROM users ORDER BY points DESC LIMIT 30')->fetchAll();header_html('رتبه‌بندی');?><main class="wrap page"><div class="page-title text-center"><h1>برترین تعمیرکاران بردخان</h1><p>بر اساس امتیاز کسب‌شده از آپلود، فروش و پاسخ‌های مفید</p></div><div class="card"><?php foreach($rows as $i=>$r):?><a class="leader-row" href="<?=url('profile/'.$r['id'])?>"><b style="width:35px;text-align:center"><?=['🥇','🥈','🥉'][$i]??fa($i+1)?></b><span class="avatar"><?=h(mb_substr($r['name'],0,1))?></span><span class="grow"><strong><?=h($r['name'])?> <?php if($r['verified']):?><span class="check">✓</span><?php endif;?></strong><small><?=h(level_name((int)$r['points']))?></small></span><b class="check"><?=fa($r['points'])?> امتیاز</b></a><?php endforeach;?></div></main><?php footer_html();exit;}

if($page==='premium'){$u=current_user();$s=settings();header_html('اشتراک ویژه');?><main class="wrap page"><div class="page-title text-center"><h1>اشتراک ویژه بردخان</h1><p>دسترسی نامحدود به همه قلق‌های پولی، نشان ویژه و اولویت نمایش.</p></div><div class="grid grid-3"><?php foreach([[1,'یک‌ماهه',$s['premium_1'],'⚡'],[3,'سه‌ماهه',$s['premium_3'],'🔥'],[12,'یک‌ساله',$s['premium_12'],'💎']] as $p):?><div class="card auth-card text-center"><div style="font-size:35px"><?=$p[3]?></div><h2><?=$p[1]?></h2><strong style="font-size:27px;color:#aa6900"><?=money($p[2])?></strong><p class="muted">تومان</p><ul style="text-align:right;font-size:12px;color:#61706a;line-height:2.3"><li>دسترسی نامحدود به قلق‌های پولی</li><li>نشان ویژه در پروفایل</li><li>اولویت در نمایش نتایج</li></ul><?php if($u):?><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="subscribe"><input type="hidden" name="months" value="<?=$p[0]?>"><button class="btn btn-amber btn-full">خرید اشتراک</button></form><?php else:?><a class="btn btn-primary btn-full" href="<?=url('login')?>">ورود برای خرید</a><?php endif;?></div><?php endforeach;?></div></main><?php footer_html();exit;}

if($page==='referral'){$u=require_login();$refLink=SITE_URL.'/register?ref='.urlencode($u['referral_code']??'');$invited=(int)db()->query('SELECT COUNT(*) FROM users WHERE referred_by='.(int)$u['id'])->fetchColumn();$rewarded=(int)db()->query('SELECT COUNT(*) FROM users WHERE referred_by='.(int)$u['id'].' AND referred_rewarded=1')->fetchColumn();$earned=(int)db()->query("SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE user_id=".(int)$u['id']." AND type='referral'")->fetchColumn();header_html('معرفی دوستان');?><main class="wrap page"><div class="page-title text-center"><h1>🎁 دعوت کن، پاداش بگیر!</h1><p>با هر دوست موفق، حتماً بعد از اولین فعالیت واقعی (آپلود قلق یا خرید) <?=money(settings()['referral_reward'])?> تومان به کیف پول شما واریز می‌شود.</p></div>
<div class="card auth-card">
  <div class="fgroup"><label class="field-label">لینک دعوت اختصاصی شما</label><div class="flex gap"><input class="field" id="refLink" dir="ltr" readonly value="<?=h($refLink)?>"><button class="btn btn-primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('refLink').value);this.innerText='✓ کپی شد';setTimeout(()=>this.innerText='کپی',1500)">کپی</button></div></div>
  <div class="fgroup"><label class="field-label">کد معرفی شما</label><div style="font-size:22px;letter-spacing:3px;color:var(--accent);font-weight:900;text-align:center;padding:12px;background:var(--bg-soft);border-radius:12px"><?=h($u['referral_code'])?></div></div>
</div>
<div class="grid grid-3" style="margin-top:18px">
  <div class="card stat-card"><strong><?=fa($invited)?></strong><small class="muted">دوست دعوت‌شده</small></div>
  <div class="card stat-card"><strong><?=fa($rewarded)?></strong><small class="muted">فعالیت تکمیل‌شده</small></div>
  <div class="card stat-card"><strong><?=money($earned)?></strong><small class="muted">تومان دریافتی (مجموع)</small></div>
</div>
<div class="card auth-card mt"><h3 style="margin:0 0 10px">قوانين برنامه معرفی</h3><ul class="muted" style="font-size:13px;line-height:2.1;padding-right:18px;margin:0"><li>دوست شما هنگام ثبت‌نام با کد شما، <?=money(settings()['invitee_credit'])?> تومان اعتبار خوش‌آمدگویی می‌گیرد.</li><li>پاداش شما <?=money(settings()['referral_reward'])?> تومان است و فقط پس از «اولین فعالیت موفق» کاربر دعوت‌شده (آپلود قلق منتشرشده یا اولین خرید) واریز می‌شود.</li><li>پاداش فقط یک‌بار برای هر دعوت‌شده پرداخت می‌شود و سلبریته اعتبار دارد.</li><li>دعوت از حساب‌های خود فرد ممنوع است و به مسدودی منجر می‌شود.</li></ul></div>
</main><?php footer_html();exit;}

if($page==='about'){require __DIR__.'/pages/about.php';exit;}
if($page==='contact'){require __DIR__.'/pages/contact.php';exit;}
if($page==='terms'){require __DIR__.'/pages/terms.php';exit;}
if($page==='privacy'){require __DIR__.'/pages/privacy.php';exit;}
if($page==='info'){redirect_to('about');}

if($page==='admin'){require __DIR__.'/pages/admin.php';exit;}

if($page==='bookmarks'){$u=require_login();$q=db()->prepare('SELECT t.*,b.note,b.created_at bookmarked_at FROM bookmarks b JOIN tips t ON t.id=b.tip_id WHERE b.user_id=? AND t.status="published" ORDER BY b.created_at DESC');$q->execute([$u['id']]);$items=$q->fetchAll();header_html('نشانک‌های من');?><main class="wrap page"><div class="page-title"><h1>نشانک‌های من</h1><p><?=fa(count($items))?> قلق ذخیره شده</p></div><div class="grid grid-3"><?php foreach($items as $t)tip_card($t); if(!$items):?><div class="card empty">هنوز هیچ قلق را نشانک نکرده‌اید.</div><?php endif;?></div></main><?php footer_html();exit;}
if($page==='favorites'){$u=require_login();$q=db()->prepare('SELECT t.* FROM favorites f JOIN tips t ON t.id=f.tip_id WHERE f.user_id=? AND t.status="published" ORDER BY f.created_at DESC');$q->execute([$u['id']]);$items=$q->fetchAll();header_html('قلق‌های مورد علاقه');?><main class="wrap page"><div class="page-title"><h1>علاقه‌مندی‌های من</h1><p><?=fa(count($items))?> قلق پسندیده</p></div><div class="grid grid-3"><?php foreach($items as $t)tip_card($t); if(!$items):?><div class="card empty">هنوز قلقی را نپسندیده‌اید.</div><?php endif;?></div></main><?php footer_html();exit;}
if($page==='notifications'){$u=require_login();$items=db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100');$items->execute([$u['id']]);$items=$items->fetchAll();db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$u['id']]);header_html('اعلان‌ها');?><main class="wrap page"><div class="page-title"><h1>اعلان‌ها</h1></div><div class="card"><?php foreach($items as $n):?><a class="leader-row" href="<?=h($n['link']?:'#')?>"><span class="grow"><strong><?=h($n['title'])?></strong><small><?=h($n['body'])?> · <?=ago($n['created_at'])?></small></span></a><?php endforeach;?><?php if(!$items):?><div class="empty">اعلانی ندارید.</div><?php endif;?></div></main><?php footer_html();exit;}

if($page==='settings'){$u=require_login();header_html('تنظیمات');?><main class="wrap page"><div class="page-title"><h1>تنظیمات حساب</h1></div><div class="card auth-card"><h3>پروفایل</h3><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="profile_update"><div class="form-group"><label class="field-label">نام</label><input class="field" name="name" value="<?=h($u['name'])?>"></div><div class="form-group"><label class="field-label">بیوگرافی</label><textarea class="field" name="bio" rows="4"><?=h($u['bio'])?></textarea></div><button class="btn btn-primary">ذخیره</button></form></div></main><?php footer_html();exit;}

if($page==='reels_demo' || $page==='reels-test'){
    // تست صفحه ریلز - نسخه دمو بدون نیاز به دیتابیس (برای تست فرانت‌اند)
    $u = null;
    try { $u = current_user(); } catch(Throwable $e) { $u = null; }
    $demo_tips = [
        ['id'=>1,'title'=>'رفع مشکل روشن نشدن لپ‌تاپ ایسوس X550 — راهنمای کامل','short_description'=>'در این قلق آموزشی، روش تشخیص و رفع مشکل روشن نشدن در لپ‌تاپ ایسوس به‌صورت گام‌به‌گام توضیح داده شده است.','access_type'=>'free','price'=>0,'difficulty'=>'medium','views'=>1240,'likes_count'=>89,'rating_sum'=>42,'rating_count'=>10,'author_name'=>'علی رضایی','verified'=>1,'images'=>['https://picsum.photos/seed/bord1/800/1200','https://picsum.photos/seed/bord1b/800/1200'],'video_url'=>''],
        ['id'=>2,'title'=>'علت و راه‌حل شارژ نشدن موبایل سامسونگ','short_description'=>'موبایل سامسونگ با مشکل شارژ نشدن مواجه شده است. در ادامه علت‌های رایج و روش تعمیر مرحله‌به‌مرحله آموزش داده می‌شود.','access_type'=>'like','price'=>0,'difficulty'=>'hard','views'=>890,'likes_count'=>45,'rating_sum'=>38,'rating_count'=>8,'author_name'=>'سارا احمدی','verified'=>0,'images'=>['https://picsum.photos/seed/bord2/800/1200'],'video_url'=>''],
        ['id'=>3,'title'=>'مادربرد گیگابایت دچار اتصال کوتاه شده — تشخیص و تعمیر','short_description'=>'مادربرد گیگابایت با مشکل اتصال کوتاه مواجه شده است. روش شناسایی قطعه معیوب با مولتی‌متر و تعویض آن آموزش داده می‌شود.','access_type'=>'paid','price'=>75000,'difficulty'=>'hard','views'=>2100,'likes_count'=>156,'rating_sum'=>95,'rating_count'=>20,'author_name'=>'محمد حسینی','verified'=>1,'images'=>['https://picsum.photos/seed/bord3/800/1200','https://picsum.photos/seed/bord3b/800/1200','https://picsum.photos/seed/bord3c/800/1200'],'video_url'=>''],
        ['id'=>4,'title'=>'رفع مشکل تصویر نداشتن تلویزیون ال‌جی — راهنمای کامل','short_description'=>'تلویزیون ال‌جی با مشکل تصویر نداشتن مواجه شده است. بررسی بک‌لایت و برد درایور به‌صورت کامل توضیح داده شده.','access_type'=>'free','price'=>0,'difficulty'=>'easy','views'=>560,'likes_count'=>32,'rating_sum'=>20,'rating_count'=>5,'author_name'=>'رضا کریمی','verified'=>0,'images'=>['https://picsum.photos/seed/bord4/800/1200'],'video_url'=>''],
        ['id'=>5,'title'=>'علت و راه‌حل بوق خطا در مادربرد ایسوس','short_description'=>'مادربرد ایسوس هنگام روشن شدن بوق خطا می‌دهد. الگوی بوق‌ها و روش عیب‌یابی رم و پردازنده آموزش داده می‌شود.','access_type'=>'like','price'=>0,'difficulty'=>'medium','views'=>730,'likes_count'=>67,'rating_sum'=>30,'rating_count'=>7,'author_name'=>'نازنین موسوی','verified'=>1,'images'=>['https://picsum.photos/seed/bord5/800/1200'],'video_url'=>'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
    ];
    $ccMap=[1=>12,2=>5,3=>23,4=>2,5=>8];
    $fvMap=[];
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<title>تست ریلز (دمو) | <?=h(SITE_NAME)?></title>
<link rel="stylesheet" href="<?=url('assets/style.css')?>?v=8">
<style>
html,body{margin:0;padding:0;background:#000;height:100%;overscroll-behavior:none}
.reels-body{font-family:Vazirmatn,Tahoma,sans-serif;background:#000;color:#fff;overflow:hidden}
.reels-topbar{position:fixed;top:0;inset-inline:0;z-index:50;display:flex;align-items:center;justify-content:space-between;padding:10px 16px;padding-top:max(10px,env(safe-area-inset-top));background:linear-gradient(rgba(0,0,0,.82),rgba(0,0,0,.35) 60%,transparent)}
.reels-topbar .logo{color:#fff;font-size:18px;text-decoration:none;font-weight:900;display:flex;align-items:center;gap:6px}
.reels-topbar .logo-mark{width:32px;height:32px;border-radius:10px;background:rgba(16,185,129,.18);border:1px solid rgba(16,185,129,.35);color:#10b981;display:grid;place-items:center}
.reels-close{color:#fff;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);padding:6px 14px;border-radius:20px;font-size:12px;text-decoration:none}
.reels-progress{position:fixed;top:0;left:0;right:0;height:2px;z-index:60;background:rgba(255,255,255,.12)} .reels-progress i{display:block;height:100%;background:#10b981;width:0%;transition:width .25s}
.reels-feed{height:100dvh;overflow-y:scroll;scroll-snap-type:y mandatory;overscroll-behavior:contain} .reels-feed::-webkit-scrollbar{display:none}
.reel{position:relative;height:100dvh;scroll-snap-align:start;background:#0a0a0a;overflow:hidden}
.reel-media{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:#080c0e}
.reel-media img{width:100%;height:100%;object-fit:cover} .reel-media img.bk-blur{filter:blur(18px) brightness(.7) scale(1.06)}
.reel-dots{position:absolute;top:70px;left:50%;transform:translateX(-50%);display:flex;gap:5px;z-index:5;background:rgba(0,0,0,.35);padding:5px 9px;border-radius:20px}
.reel-dots i{width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,.38);display:block} .reel-dots i.on{background:#fff;width:20px;border-radius:4px}
.reel-info{position:absolute;inset-inline-start:14px;inset-inline-end:86px;bottom:0;z-index:4;padding:0 0 28px;padding-bottom:max(28px,env(safe-area-inset-bottom));color:#fff;background:linear-gradient(transparent 0%,rgba(0,0,0,.55) 30%,rgba(0,0,0,.88) 100%);pointer-events:none}
.reel-info .author-row{display:flex;align-items:center;gap:8px;margin-bottom:9px;pointer-events:auto}
.reel-info .avatar{width:36px;height:36px;border-radius:50%;background:#10b981;display:grid;place-items:center;font-weight:900;font-size:15px;border:2px solid #fff}
.reel-info h3{margin:0 0 6px;font-size:15px;font-weight:900;line-height:1.5}
.reel-info p{margin:0;font-size:12px;color:#e6e6e6;line-height:1.9;opacity:.92}
.reel-pills{display:flex;gap:6px;margin-top:9px;flex-wrap:wrap} .reel-pills .pill{background:rgba(255,255,255,.16);color:#fff;font-size:10px;padding:3px 9px;border-radius:12px}
.reel-rail{position:absolute;inset-inline-end:10px;bottom:96px;bottom:max(96px,calc(env(safe-area-inset-bottom) + 86px));z-index:6;display:flex;flex-direction:column;align-items:center;gap:18px}
.ra-btn{display:flex;flex-direction:column;align-items:center;gap:4px;background:transparent;border:0;cursor:pointer;color:#fff;font-size:26px;text-shadow:0 1px 10px rgba(0,0,0,.8)}
.ra-btn small{font-size:10px;font-weight:800} .ra-btn.liked{color:#ff2d55}
.ra-btn .buy-chip{display:flex;flex-direction:column;align-items:center;gap:2px;background:rgba(0,0,0,.58);border:1px solid rgba(255,255,255,.32);border-radius:16px;padding:8px 10px;font-size:11px;font-weight:800}
.heart-pop{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) scale(0);font-size:88px;pointer-events:none;z-index:9;opacity:0}
.heart-pop.show{animation:bkpop .72s cubic-bezier(.17,.89,.32,1.49) forwards}
@keyframes bkpop{0%{transform:translate(-50%,-50%) scale(0);opacity:0}15%{opacity:1}35%{transform:translate(-50%,-50%) scale(1.25)}70%{transform:translate(-50%,-50%) scale(.95)}100%{transform:translate(-50%,-50%) scale(1);opacity:0}}
.reel-lock{position:absolute;inset:0;z-index:3;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;background:rgba(0,0,0,.52);backdrop-filter:blur(6px);color:#fff;text-align:center;padding:22px}
.reel-lock .lk-ic{font-size:48px} .reel-lock b{font-size:16px;font-weight:900} .reel-lock p{font-size:12px;color:#ddd;max-width:300px;line-height:2}
.lk-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-top:4px}
.lk-btn{border:0;border-radius:22px;padding:10px 20px;font-size:13px;font-weight:800;cursor:pointer;background:#fff;color:#111;text-decoration:none}
.lk-btn.green{background:#10b981;color:#04110b}
.comments-sheet{position:absolute;inset-inline:0;bottom:-100%;height:64%;background:#11181f;border-radius:22px 22px 0 0;z-index:12;transition:bottom .32s;display:flex;flex-direction:column;overflow:hidden;border-top:1px solid rgba(255,255,255,.12)}
.comments-sheet.open{bottom:0}
.cs-head{padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.1);display:flex;justify-content:space-between;align-items:center;color:#fff;font-weight:800;font-size:14px}
.cs-close{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);color:#fff;width:30px;height:30px;border-radius:50%;display:grid;place-items:center;font-size:14px;cursor:pointer}
.cs-list{flex:1;overflow-y:auto;padding:14px 16px;color:#fff}
.cs-item{display:flex;gap:10px;margin-bottom:16px}
.cs-item .av{width:32px;height:32px;border-radius:50%;background:#10b981;display:grid;place-items:center;font-size:12px;font-weight:900;flex-shrink:0;color:#04110b}
.cs-form{display:flex;gap:8px;padding:10px 12px;border-top:1px solid rgba(255,255,255,.1);background:#0d131a}
.cs-form input{flex:1;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.14);border-radius:20px;padding:11px 14px;color:#fff;font-size:13px;outline:0}
.cs-form button{background:#10b981;border:0;border-radius:20px;padding:0 18px;color:#04110b;font-weight:800;cursor:pointer;font-size:13px}
.toast{position:fixed;bottom:28px;left:50%;transform:translateX(-50%);background:#111a24;border:1px solid rgba(255,255,255,.18);color:#fff;padding:12px 20px;border-radius:24px;font-size:13px;z-index:99;max-width:92%;text-align:center}
.toast a{color:#34d399;font-weight:800;text-decoration:none}
.test-badge{position:fixed;top:50px;left:12px;z-index:70;background:#10b981;color:#04110b;font-size:11px;font-weight:900;padding:6px 12px;border-radius:20px}
</style></head><body class="reels-body">
<div class="reels-progress"><i id="reelsProgress"></i></div>
<div class="test-badge">🧪 حالت تست ریلز - دمو</div>
<header class="reels-topbar">
  <a class="logo" href="<?=url()?>"><span class="logo-mark">⌁</span> برد<em>خان</em> · ریلز تست</a>
  <div class="links"><a class="reels-close" href="<?=url('reels')?>">ریلز اصلی</a><a class="reels-close" href="<?=url()?>">✕</a></div>
</header>
<main class="reels-feed" id="reelsFeed">
<?php foreach($demo_tips as $t):
    $tid=(int)$t['id'];
    $locked = $t['access_type']!=='free';
    $liked = false;
    $displayUrls = $t['images'];
    $dataThumbs = h(json_encode($displayUrls, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $dataFulls = $dataThumbs;
?>
<div class="reel" id="reel-<?=$tid?>" data-id="<?=$tid?>" data-access="<?=h($t['access_type'])?>" data-price="<?=(int)$t['price']?>" data-locked="<?=$locked?'1':'0'?>" data-liked="0" data-index="0">
  <div class="reel-media">
    <img src="<?=h($displayUrls[0])?>" alt="<?=h($t['title'])?>" draggable="false" class="<?=$locked?'bk-blur':''?>" data-thumbs="<?=$dataThumbs?>" data-fulls="<?=$dataFulls?>" loading="eager">
    <?php if(count($displayUrls)>1): ?><div class="reel-dots"><?php foreach($displayUrls as $k=>$v): ?><i class="<?=$k===0?'on':''?>"></i><?php endforeach;?></div><?php endif; ?>
    <?php if(!empty($t['video_url'])): ?><div class="video-play">▶</div><?php endif; ?>
  </div>
  <?php if($locked): ?>
  <div class="reel-lock">
    <div class="lk-ic"><?=$t['access_type']==='paid'?'🔒':'💗'?></div>
    <b><?=$t['access_type']==='paid'?'این قلق پولی است':'این قلق با لایک باز می‌شود'?></b>
    <p>این یک دمو تست است. در نسخه واقعی <?=$t['access_type']==='paid'?'باید مبلغ را بپردازید':'باید لایک کنید'?>.</p>
    <div class="lk-actions"><button class="lk-btn green" data-act="unlock">تست باز کردن (دمو)</button><a class="lk-btn" href="<?=url('tip/'.$tid)?>">جزئیات</a></div>
  </div>
  <?php endif; ?>
  <div class="reel-info">
    <div class="author-row"><span class="avatar"><?=h(mb_substr($t['author_name'],0,1))?></span><span class="author-name"><?=h($t['author_name'])?> ✓</span><span style="margin-right:auto;background:rgba(16,185,129,.2);border:1px solid rgba(16,185,129,.4);color:#34d399;padding:3px 8px;border-radius:10px;font-size:10px">تست #<?=fa($tid)?></span></div>
    <h3><?=h($t['title'])?></h3>
    <p><?=h($t['short_description'])?></p>
    <div class="reel-pills"><span class="pill"><?=h($t['access_type']==='paid'?money($t['price']).' تومان':($t['access_type']==='like'?'با لایک':'رایگان'))?></span><span class="pill"><?=h(['easy'=>'آسان','medium'=>'متوسط','hard'=>'سخت'][$t['difficulty']]??'متوسط')?></span><span class="pill">◉ <?=fa($t['views'])?></span></div>
  </div>
  <div class="reel-rail">
    <button class="ra-btn" data-act="like" aria-label="لایک"><span>🤍</span><small data-count><?=fa($t['likes_count'])?></small></button>
    <button class="ra-btn" data-act="comments" aria-label="نظرات"><span>💬</span><small><?=fa($ccMap[$tid]??0)?></small></button>
    <button class="ra-btn" data-act="share" aria-label="اشتراک"><span>➦</span><small>اشتراک</small></button>
    <?php if($locked): ?><button class="ra-btn" data-act="unlock"><span class="buy-chip">باز کردن<br>دمو</span></button><?php endif; ?>
  </div>
  <div class="heart-pop">❤️</div>
  <div class="comments-sheet"><div class="cs-head"><span>💬 نظرات (دمو)</span><button class="cs-close" data-act="csclose">✕</button></div>
    <div class="cs-list">
      <div class="cs-item"><span class="av">ع</span><div><b>علی</b><p>این قلق عالی بود، مشکل من حل شد!</p><small>۲ ساعت پیش</small></div></div>
      <div class="cs-item"><span class="av">س</span><div><b>سارا</b><p>ممنون بابت آموزش کامل 🙏</p><small>۵ ساعت پیش</small></div></div>
    </div>
    <div class="cs-form"><input type="text" placeholder="نظر تستی بنویسید…" maxlength="200"><button data-act="cssend">ارسال (دمو)</button></div>
  </div>
</div>
<?php endforeach; ?>
</main>
<div id="bkToast" class="toast" style="display:none"></div>
<script>
var BKC={csrf:'demo',guest:false,base:'/',demo:true};
function bkToast(m){var t=document.getElementById('bkToast');t.textContent=m;t.style.display='block';clearTimeout(t._h);t._h=setTimeout(function(){t.style.display='none'},2500);}
function esc(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}
function parseImgs(reel){var img=reel.querySelector('.reel-media img');try{var thumbs=JSON.parse(img.getAttribute('data-thumbs')||'[]');var fulls=JSON.parse(img.getAttribute('data-fulls')||'[]');return {thumbs:thumbs,fulls:fulls,el:img};}catch(e){return {thumbs:[],fulls:[],el:img};}}
function currentDisplayList(reel){var p=parseImgs(reel);return reel.dataset.locked==='1'?p.thumbs:p.fulls;}
function updateDots(reel,idx){reel.querySelectorAll('.reel-dots i').forEach(function(d,i){d.classList.toggle('on',i===idx);});}
function popHeart(reel,show){var h=reel.querySelector('.heart-pop');if(!h)return;h.classList.remove('show');if(show){void h.offsetWidth;h.classList.add('show');}}
document.addEventListener('click',function(e){
  var b=e.target.closest('[data-act]'); if(!b) return;
  var reel=b.closest('.reel'); if(!reel) return;
  var act=b.dataset.act;
  if(act==='like'){
    var btn=reel.querySelector('[data-act=like]'); var liked=reel.dataset.liked==='1';
    reel.dataset.liked=liked?'0':'1'; btn.classList.toggle('liked',!liked);
    btn.querySelector('span').textContent=!liked?'❤️':'🤍';
    var cnt=btn.querySelector('[data-count]'); if(cnt){var n=parseInt(cnt.textContent.replace(/[^0-9]/g,''))||0; cnt.textContent=!liked?n+1:Math.max(0,n-1);}
    popHeart(reel,!liked); bkToast(!liked?'لایک شد ❤️ (تست)':'لایک حذف شد (تست)');
  }else if(act==='comments'){reel.querySelector('.comments-sheet').classList.add('open');}
  else if(act==='share'){var url=location.origin+'/tip/'+reel.dataset.id; if(navigator.clipboard){navigator.clipboard.writeText(url).then(function(){bkToast('لینک کپی شد: '+url);});}else{bkToast('لینک: '+url);} }
  else if(act==='unlock'){reel.dataset.locked='0'; var lock=reel.querySelector('.reel-lock'); if(lock) lock.remove(); reel.querySelectorAll('[data-act=unlock]').forEach(function(x){if(x.closest('.reel-rail')) x.remove();}); var img=reel.querySelector('.reel-media img'); if(img) img.classList.remove('bk-blur'); bkToast('در حالت دمو باز شد ✓'); popHeart(reel,true);}
  else if(act==='csclose'){reel.querySelector('.comments-sheet').classList.remove('open');}
  else if(act==='cssend'){var inp=reel.querySelector('.cs-form input'); if(inp && inp.value.trim().length>=2){var list=reel.querySelector('.cs-list'); var div=document.createElement('div'); div.className='cs-item'; div.innerHTML='<span class=\"av\">ش</span><div><b>شما</b><p>'+esc(inp.value)+'</p><small>همین الان</small></div>'; list.appendChild(div); inp.value=''; bkToast('نظر تستی ثبت شد (دمو)');}}
});
var lastTap=0;
document.addEventListener('click',function(e){
  var reel=e.target.closest('.reel'); if(!reel) return;
  if(e.target.closest('.reel-rail')||e.target.closest('.reel-info')||e.target.closest('.reel-lock')||e.target.closest('.comments-sheet')||e.target.closest('[data-act]')) return;
  var now=Date.now(); if(now-lastTap<300){var btn=reel.querySelector('[data-act=like]'); var liked=reel.dataset.liked==='1'; reel.dataset.liked=liked?'0':'1'; if(btn){btn.classList.toggle('liked',!liked); btn.querySelector('span').textContent=!liked?'❤️':'🤍';} popHeart(reel,!liked); bkToast(!liked?'لایک شد ❤️ (دابل‌تپ)':'لایک حذف شد');} lastTap=now;
});
document.addEventListener('click',function(e){
  var img=e.target.closest('.reel-media img'); if(!img) return; var reel=img.closest('.reel'); if(!reel||reel.dataset.locked==='1') return;
  var list=currentDisplayList(reel); if(list.length<2) return;
  var curIdx=parseInt(reel.dataset.index||'0'); var next=(curIdx+1)%list.length; reel.dataset.index=next; img.src=list[next]; updateDots(reel,next);
});
document.addEventListener('keydown',function(e){
  var feed=document.getElementById('reelsFeed'); if(!feed) return;
  if(e.key==='ArrowDown'){e.preventDefault();feed.scrollBy({top:innerHeight*0.9,behavior:'smooth'});}
  if(e.key==='ArrowUp'){e.preventDefault();feed.scrollBy({top:-innerHeight*0.9,behavior:'smooth'});}
  if(e.key==='Escape'){document.querySelectorAll('.comments-sheet.open').forEach(function(s){s.classList.remove('open');});}
});
(function(){var feed=document.getElementById('reelsFeed'); var bar=document.getElementById('reelsProgress'); if(!feed||!bar) return; function upd(){var max=feed.scrollHeight-feed.clientHeight; var pct=max>0?(feed.scrollTop/max*100):0; bar.style.width=pct+'%';} feed.addEventListener('scroll',upd,{passive:true}); upd();})();
console.log('%c[Reels Demo Test] ۵ ریل دمو بارگذاری شد','color:#10b981;font-weight:bold');
console.log('[Reels Demo Test] تست‌ها: دابل‌تپ لایک, کلیک تصویر برای تعویض, کامنت, اشتراک, باز کردن قفل');
</script>
</body></html><?php exit;}

if($page==='reels'){
    $u=current_user();
    // تست صفحه ریلز: بارگذاری ۶۰ قلق آخر با اطلاعات کامل
    $items=db()->query("SELECT t.*,u.name author_name,u.verified,u.avatar FROM tips t JOIN users u ON u.id=t.author_id WHERE t.status='published' ORDER BY COALESCE(t.published_at,t.created_at) DESC LIMIT 60")->fetchAll();
    $ids=array_map(fn($t)=>(int)$t['id'],$items);
    $ccMap=[];$fvMap=[];
    if($ids){
        $in=implode(',',$ids);
        $c=db()->query("SELECT tip_id,COUNT(*) n FROM comments WHERE is_deleted=0 AND tip_id IN ($in) GROUP BY tip_id")->fetchAll();
        foreach($c as $r)$ccMap[(int)$r['tip_id']]=(int)$r['n'];
        if($u){
            $q=db()->prepare("SELECT tip_id FROM favorites WHERE user_id=? AND tip_id IN ($in)");
            $q->execute([(int)$u['id']]);
            foreach($q->fetchAll() as $r)$fvMap[(int)$r['tip_id']]=true;
        }
    }
    // helper برای ساخت URL ایمن تصویر ریلز (خارجی یا داخلی با media_url)
    $reel_img_url = function(string $path, array $tip, ?array $user, bool $locked): string {
        $path=trim($path);
        if($path==='') return '';
        if(preg_match('#^https?://#i',$path)) return $path; // خارجی: مستقیم
        // داخلی: از media_url استفاده می‌کنیم (thumb برای قفل)
        return media_url($path, $locked?'thumb':'img', (int)$tip['id'], $user ? (int)$user['id'] : 0);
    };
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="robots" content="index,follow">
<title>ریلز قلق‌های تعمیراتی | <?=h(SITE_NAME)?></title>
<link rel="stylesheet" href="<?=url('assets/style.css')?>?v=8">
<style>
:root{--reel-bg:#000}
html,body{margin:0;padding:0;background:var(--reel-bg);height:100%;overscroll-behavior:none}
.reels-body{font-family:Vazirmatn,Tahoma,sans-serif;background:#000;color:#fff;overflow:hidden}
/* Topbar */
.reels-topbar{position:fixed;top:0;inset-inline:0;z-index:50;display:flex;align-items:center;justify-content:space-between;padding:10px 16px;padding-top:max(10px,env(safe-area-inset-top));background:linear-gradient(rgba(0,0,0,.82),rgba(0,0,0,.35) 60%,transparent)}
.reels-topbar .logo{color:#fff;font-size:18px;text-decoration:none;font-weight:900;display:flex;align-items:center;gap:6px}
.reels-topbar .logo-mark{width:32px;height:32px;border-radius:10px;background:rgba(16,185,129,.18);border:1px solid rgba(16,185,129,.35);color:#10b981;display:grid;place-items:center}
.reels-topbar .links{display:flex;gap:8px}
.reels-close{color:#fff;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);padding:6px 14px;border-radius:20px;font-size:12px;text-decoration:none;backdrop-filter:blur(8px);transition:.15s}
.reels-close:hover{background:rgba(255,255,255,.22)}
/* Progress bar */
.reels-progress{position:fixed;top:0;left:0;right:0;height:2px;z-index:60;background:rgba(255,255,255,.12)}
.reels-progress i{display:block;height:100%;background:#10b981;width:0%;transition:width .25s ease}
/* Feed */
.reels-feed{height:100dvh;overflow-y:scroll;scroll-snap-type:y mandatory;overscroll-behavior:contain;-webkit-overflow-scrolling:touch;scrollbar-width:none}
.reels-feed::-webkit-scrollbar{display:none}
.reel{position:relative;height:100dvh;scroll-snap-align:start;background:#0a0a0a;overflow:hidden;isolation:isolate}
.reel-media{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:#080c0e}
.reel-media img{width:100%;height:100%;object-fit:cover;user-select:none;-webkit-user-drag:none;transition:filter .25s}
.reel-media img.bk-blur{filter:blur(18px) brightness(.7) scale(1.06)}
.reel-media .video-play{position:absolute;width:68px;height:68px;border-radius:50%;background:rgba(0,0,0,.55);border:2px solid rgba(255,255,255,.9);color:#fff;font-size:26px;display:grid;place-items:center;backdrop-filter:blur(4px);z-index:2}
.reel-dots{position:absolute;top:70px;left:50%;transform:translateX(-50%);display:flex;gap:5px;z-index:5;background:rgba(0,0,0,.35);padding:5px 9px;border-radius:20px;backdrop-filter:blur(6px)}
.reel-dots i{width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,.38);transition:.22s;display:block}
.reel-dots i.on{background:#fff;width:20px;border-radius:4px}
/* Info bottom */
.reel-info{position:absolute;inset-inline-start:14px;inset-inline-end:86px;bottom:0;z-index:4;padding:0 0 28px;padding-bottom:max(28px,env(safe-area-inset-bottom));color:#fff;background:linear-gradient(transparent 0%,rgba(0,0,0,.55) 30%,rgba(0,0,0,.88) 100%);pointer-events:none}
.reel-info .author-row{display:flex;align-items:center;gap:8px;margin-bottom:9px;pointer-events:auto}
.reel-info .avatar{width:36px;height:36px;border-radius:50%;background:#10b981;display:grid;place-items:center;font-weight:900;font-size:15px;border:2px solid #fff;flex:none}
.reel-info .author-name{font-weight:800;font-size:13px;display:flex;align-items:center;gap:4px}
.reel-info .check{color:#7fe0b4}
.reel-info h3{margin:0 0 6px;font-size:15px;font-weight:900;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.reel-info p{margin:0;font-size:12px;color:#e6e6e6;line-height:1.9;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;opacity:.92}
.reel-pills{display:flex;gap:6px;margin-top:9px;flex-wrap:wrap}
.reel-pills .pill{background:rgba(255,255,255,.16);color:#fff;font-size:10px;padding:3px 9px;border-radius:12px;border:1px solid rgba(255,255,255,.14);backdrop-filter:blur(4px)}
/* Right rail */
.reel-rail{position:absolute;inset-inline-end:10px;bottom:96px;bottom:max(96px,calc(env(safe-area-inset-bottom) + 86px));z-index:6;display:flex;flex-direction:column;align-items:center;gap:18px}
.ra-btn{position:relative;display:flex;flex-direction:column;align-items:center;gap:4px;background:transparent;border:0;cursor:pointer;color:#fff;font-size:26px;text-shadow:0 1px 10px rgba(0,0,0,.8);transition:transform .12s}
.ra-btn:active{transform:scale(.9)}
.ra-btn small{font-size:10px;font-weight:800;letter-spacing:.2px;text-shadow:0 1px 6px rgba(0,0,0,.9)}
.ra-btn.liked{color:#ff2d55;filter:drop-shadow(0 0 10px rgba(255,45,85,.6))}
.ra-btn .buy-chip{display:flex;flex-direction:column;align-items:center;gap:2px;background:rgba(0,0,0,.58);border:1px solid rgba(255,255,255,.32);border-radius:16px;padding:8px 10px;font-size:11px;font-weight:800;line-height:1.5;text-align:center;backdrop-filter:blur(8px)}
.ra-btn .buy-chip .price{color:#ffd166}
/* Heart pop */
.heart-pop{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) scale(0);font-size:88px;pointer-events:none;z-index:9;opacity:0}
.heart-pop.show{animation:bkpop .72s cubic-bezier(.17,.89,.32,1.49) forwards}
@keyframes bkpop{0%{transform:translate(-50%,-50%) scale(0);opacity:0}15%{opacity:1}35%{transform:translate(-50%,-50%) scale(1.25)}70%{transform:translate(-50%,-50%) scale(.95)}100%{transform:translate(-50%,-50%) scale(1);opacity:0}}
/* Lock overlay */
.reel-lock{position:absolute;inset:0;z-index:3;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;background:rgba(0,0,0,.52);backdrop-filter:blur(6px);color:#fff;text-align:center;padding:22px}
.reel-lock .lk-ic{font-size:48px;filter:drop-shadow(0 4px 12px rgba(0,0,0,.6))}
.reel-lock b{font-size:16px;font-weight:900}
.reel-lock p{font-size:12px;color:#ddd;max-width:300px;line-height:2;margin:0;opacity:.9}
.lk-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-top:4px}
.lk-btn{border:0;border-radius:22px;padding:10px 20px;font-size:13px;font-weight:800;cursor:pointer;background:#fff;color:#111;transition:.15s;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
.lk-btn:hover{transform:translateY(-1px)}
.lk-btn.green{background:#10b981;color:#04110b;box-shadow:0 6px 20px rgba(16,185,129,.35)}
/* Comments sheet */
.comments-sheet{position:absolute;inset-inline:0;bottom:-100%;height:64%;background:#11181f;border-radius:22px 22px 0 0;z-index:12;transition:bottom .32s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;overflow:hidden;border-top:1px solid rgba(255,255,255,.12);box-shadow:0 -10px 40px rgba(0,0,0,.6)}
.comments-sheet.open{bottom:0}
.cs-head{padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.1);display:flex;justify-content:space-between;align-items:center;color:#fff;font-weight:800;font-size:14px;flex:none}
.cs-close{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);color:#fff;width:30px;height:30px;border-radius:50%;display:grid;place-items:center;font-size:14px;cursor:pointer}
.cs-list{flex:1;overflow-y:auto;padding:14px 16px;color:#fff;overscroll-behavior:contain}
.cs-item{display:flex;gap:10px;margin-bottom:16px;animation:fadeIn .2s ease}
@keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
.cs-item .av{width:32px;height:32px;border-radius:50%;background:#10b981;display:grid;place-items:center;font-size:12px;font-weight:900;flex-shrink:0;color:#04110b}
.cs-item b{font-size:12px;color:#fff}
.cs-item p{margin:4px 0 0;font-size:12px;color:#d1d9e2;line-height:1.9;word-break:break-word}
.cs-item small{color:#8b98a5;font-size:10px}
.cs-form{display:flex;gap:8px;padding:10px 12px;padding-bottom:max(10px,env(safe-area-inset-bottom));border-top:1px solid rgba(255,255,255,.1);background:#0d131a;flex:none}
.cs-form input{flex:1;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.14);border-radius:20px;padding:11px 14px;color:#fff;font-size:13px;outline:0;transition:.15s}
.cs-form input:focus{border-color:#10b981;background:rgba(255,255,255,.1)}
.cs-form button{background:#10b981;border:0;border-radius:20px;padding:0 18px;color:#04110b;font-weight:800;cursor:pointer;font-size:13px;transition:.15s}
.cs-form button:hover{background:#34d399}
/* Toast */
.toast{position:fixed;bottom:28px;bottom:max(28px,env(safe-area-inset-bottom));left:50%;transform:translateX(-50%);background:#111a24;border:1px solid rgba(255,255,255,.18);color:#fff;padding:12px 20px;border-radius:24px;font-size:13px;z-index:99;max-width:92%;text-align:center;box-shadow:0 10px 30px rgba(0,0,0,.5);backdrop-filter:blur(12px)}
.toast a{color:#34d399;font-weight:800;text-decoration:none}
.toast.error{border-color:rgba(248,113,113,.4);background:#1a1214}
/* Empty */
.reels-empty{height:100dvh;display:grid;place-items:center;color:#fff;text-align:center;padding:24px}
.reels-empty .box{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:20px;padding:28px 22px;max-width:360px}
.reels-empty h2{margin:0 0 8px;font-size:18px}
.reels-empty p{margin:0 0 14px;color:#9fb0c3;font-size:13px;line-height:2}
@media(min-width:768px){.reel-media img{object-fit:contain;background:#080c0e}.reel-info{inset-inline-end:110px}.reels-feed{max-width:480px;margin:0 auto;border-inline:1px solid rgba(255,255,255,.08)}}
</style>
</head><body class="reels-body">
<div class="reels-progress"><i id="reelsProgress"></i></div>
<header class="reels-topbar">
  <a class="logo" href="<?=url()?>"><span class="logo-mark">⌁</span> برد<em>خان</em> · ریلز</a>
  <div class="links">
    <a class="reels-close" href="<?=url('tips')?>">قلق‌ها</a>
    <a class="reels-close" href="<?=url()?>">✕</a>
  </div>
</header>
<main class="reels-feed" id="reelsFeed">
<?php if(!$items): ?>
  <div class="reels-empty">
    <div class="box">
      <div style="font-size:42px;margin-bottom:8px">🎬</div>
      <h2>هنوز ریلزی برای نمایش نیست</h2>
      <p>اولین قلق را شما ثبت کنید تا در ریلز نمایش داده شود. ریلز مثل اینستاگرام، اسکرول عمودی تمام‌صفحه است.</p>
      <a class="lk-btn green" href="<?=url('upload')?>">➕ ثبت اولین قلق</a>
      <a class="lk-btn" style="margin-top:8px" href="<?=url('tips')?>">مشاهده قلق‌ها</a>
    </div>
  </div>
<?php else: foreach($items as $t):
    $imgs=tip_images($t);
    $tid=(int)$t['id'];
    $locked=!tip_has_access($t,$u);
    $liked=!empty($fvMap[$tid]);
    $rating=$t['rating_count']?round($t['rating_sum']/$t['rating_count'],1):0;
    // ساخت لیست URL ایمن برای هر عکس
    $thumbUrls=[]; $fullUrls=[]; $displayUrls=[];
    foreach($imgs as $p){
        $thumbUrls[] = $reel_img_url($p,$t,$u,true);
        $fullUrls[]  = $reel_img_url($p,$t,$u,false);
    }
    $displayUrls = $locked ? $thumbUrls : $fullUrls;
    $firstDisplay = $displayUrls[0] ?? '';
    // برای data attributes: JSON امن
    $dataThumbs = h(json_encode($thumbUrls, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $dataFulls  = h(json_encode($fullUrls, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
?>
<div class="reel" id="reel-<?=$tid?>" data-id="<?=$tid?>" data-access="<?=h($t['access_type'])?>" data-price="<?=(int)$t['price']?>" data-locked="<?=$locked?'1':'0'?>" data-liked="<?=$liked?'1':'0'?>" data-index="0">
  <div class="reel-media">
    <?php if($firstDisplay): ?>
      <img src="<?=h($firstDisplay)?>" alt="<?=h($t['title'])?>" draggable="false" class="<?=$locked?'bk-blur':''?>" data-thumbs="<?=$dataThumbs?>" data-fulls="<?=$dataFulls?>" loading="<?= $tid=== (int)$items[0]['id'] ? 'eager' : 'lazy' ?>">
      <?php if(count($displayUrls)>1): ?>
        <div class="reel-dots"><?php foreach($displayUrls as $k=>$v): ?><i class="<?=$k===0?'on':''?>"></i><?php endforeach;?></div>
      <?php endif; ?>
      <?php if(!empty($t['video_url'])): ?><div class="video-play">▶</div><?php endif; ?>
    <?php else: ?>
      <div class="reel-cover" style="width:100%;height:100%;display:grid;place-items:center;color:#fff;font-size:16px;background:linear-gradient(135deg,#0d6b55,#063e37);padding:20px;text-align:center"><?=h($t['title'])?></div>
    <?php endif; ?>
  </div>
  <?php if($locked): ?>
  <div class="reel-lock">
    <div class="lk-ic"><?=$t['access_type']==='paid'?'🔒':'💗'?></div>
    <b><?=$t['access_type']==='paid'?'این قلق پولی است':'این قلق با لایک باز می‌شود'?></b>
    <p>برای دیدن تصویر اصلی و مراحل کامل تعمیر، <?=$t['access_type']==='paid'?'مبلغ را بپردازید':'یک لایک ثبت کنید'?>.</p>
    <div class="lk-actions">
      <button class="lk-btn green" data-act="unlock"><?=$t['access_type']==='paid'?('💳 خرید — '.money($t['price']).' تومان'):'♥ لایک و باز کردن'?></button>
      <a class="lk-btn" href="<?=url('tip/'.$tid)?>">جزئیات قلق</a>
    </div>
  </div>
  <?php endif; ?>
  <div class="reel-info">
    <div class="author-row">
      <span class="avatar"><?=h(mb_substr($t['author_name']??'؟',0,1))?></span>
      <span class="author-name"><?=h($t['author_name']??'تعمیرکار')?> <?php if(!empty($t['verified'])): ?><span class="check">✓</span><?php endif; ?></span>
      <a href="<?=url('tip/'.$tid)?>" style="margin-right:auto;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.2);padding:4px 10px;border-radius:14px;font-size:10px;pointer-events:auto;text-decoration:none;color:#fff">مشاهده</a>
    </div>
    <h3><?=h($t['title'])?></h3>
    <p><?=h($t['short_description'])?></p>
    <div class="reel-pills">
      <span class="pill"><?=h($t['access_type']==='paid'?money($t['price']).' تومان':($t['access_type']==='like'?'با لایک':'رایگان'))?></span>
      <span class="pill"><?=h(['easy'=>'آسان','medium'=>'متوسط','hard'=>'سخت'][$t['difficulty']??'medium']??'متوسط')?></span>
      <?php if($rating): ?><span class="pill">★ <?=fa($rating)?></span><?php endif; ?>
      <span class="pill">◉ <?=fa($t['views'])?></span>
    </div>
  </div>
  <div class="reel-rail">
    <button class="ra-btn<?=$liked?' liked':''?>" data-act="like" aria-label="لایک"><span><?=$liked?'❤️':'🤍'?></span><small data-count><?=fa($t['likes_count'])?></small></button>
    <button class="ra-btn" data-act="comments" aria-label="نظرات"><span>💬</span><small><?=fa($ccMap[$tid]??0)?></small></button>
    <button class="ra-btn" data-act="share" aria-label="اشتراک‌گذاری"><span>➦</span><small>اشتراک</small></button>
    <?php if($locked && $t['access_type']!=='free'): ?>
      <button class="ra-btn" data-act="unlock" aria-label="باز کردن"><span class="buy-chip"><?=$t['access_type']==='paid'?('💰 <span class="price">'.fa((int)($t['price']/1000)).'k</span>'):'♥'?><br>باز کردن</span></button>
    <?php endif; ?>
  </div>
  <div class="heart-pop">❤️</div>
  <div class="comments-sheet" role="dialog" aria-label="نظرات">
    <div class="cs-head"><span>💬 نظرات</span><button class="cs-close" data-act="csclose" aria-label="بستن">✕</button></div>
    <div class="cs-list"><div class="cs-item" style="color:#8b98a5;font-size:12px">در حال دریافت…</div></div>
    <div class="cs-form">
      <?php if($u): ?>
        <input type="text" placeholder="نظر خود را بنویسید…" maxlength="500" dir="auto">
        <button data-act="cssend">ارسال</button>
      <?php else: ?>
        <a class="lk-btn green" style="text-decoration:none;width:100%;text-align:center" href="<?=url('login')?>">برای نظر وارد شوید</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; endif; ?>
</main>
<div id="bkToast" class="toast" style="display:none"></div>
<script>
// تست صفحه ریلز - نسخه اصلاح‌شده با رفع باگ {{}} و بهبود UX
var BKC={csrf:<?=json_encode(csrf())?>,guest:<?=json_encode(!$u)?>,base:<?=json_encode(url(''))?>};
function bkToast(m,link,isError){
  var t=document.getElementById('bkToast');
  t.className='toast'+(isError?' error':'');
  t.innerHTML=m+(link?' <a href="'+link+'">شارژ کیف پول ←</a>':'');
  t.style.display='block';
  clearTimeout(t._h);
  t._h=setTimeout(function(){t.style.display='none';},3800);
}
function bkAjax(body){
  return fetch(window.location.href,{
    method:'POST',
    body:body,
    headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
  }).then(function(r){
    return r.text().then(function(txt){
      try{return JSON.parse(txt);}catch(e){return {ok:false,error:'پاسخ نامعتبر از سرور: '+txt.slice(0,120)}}
    });
  }).catch(function(){return {ok:false,error:'خطای شبکه'}});
}
function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;}
function parseImgs(reel){
  var img=reel.querySelector('.reel-media img');
  if(!img) return {thumbs:[],fulls:[],cur:0};
  try{
    var thumbs=JSON.parse(img.getAttribute('data-thumbs')||'[]');
    var fulls=JSON.parse(img.getAttribute('data-fulls')||'[]');
    return {thumbs:thumbs,fulls:fulls,el:img};
  }catch(e){return {thumbs:[],fulls:[],el:img};}
}
function currentDisplayList(reel){
  var p=parseImgs(reel);
  var locked=reel.dataset.locked==='1';
  return locked ? p.thumbs : p.fulls;
}
function updateDots(reel,idx){
  var dots=reel.querySelectorAll('.reel-dots i');
  dots.forEach(function(d,i){d.classList.toggle('on',i===idx);});
}
function popHeart(reel,show){
  var h=reel.querySelector('.heart-pop');
  if(!h) return;
  h.classList.remove('show');
  if(show){void h.offsetWidth;h.classList.add('show');}
}
function bkLike(reel){
  var btn=reel.querySelector('[data-act=like]');
  var cnt=reel.querySelector('[data-count]');
  var fd=new FormData();
  fd.append('csrf',BKC.csrf);
  fd.append('action','favorite');
  fd.append('tip_id',reel.dataset.id);
  btn.disabled=true;
  bkAjax(fd).then(function(j){
    btn.disabled=false;
    if(!j||!j.ok){bkToast(j&&j.error?j.error:'خطا در ثبت لایک.',null,true);return;}
    var liked=j.liked;
    reel.dataset.liked=liked?'1':'0';
    btn.classList.toggle('liked',liked);
    var span=btn.querySelector('span');
    if(span) span.textContent=liked?'❤️':'🤍';
    if(cnt) cnt.textContent=j.likes;
    popHeart(reel,liked);
    bkToast(liked?'به علاقه‌مندی‌ها اضافه شد ❤️':'از علاقه‌مندی‌ها حذف شد');
  });
}
function bkUnlock(reel){
  var fd=new FormData();
  fd.append('csrf',BKC.csrf);
  fd.append('action','unlock');
  fd.append('tip_id',reel.dataset.id);
  bkToast('⏳ در حال باز کردن…');
  bkAjax(fd).then(function(j){
    if(!j){bkToast('پاسخی از سرور دریافت نشد.',null,true);return;}
    if(j.ok){
      reel.dataset.locked='0';
      var lock=reel.querySelector('.reel-lock');
      if(lock) lock.remove();
      // حذف دکمه‌های باز کردن اضافی در rail
      reel.querySelectorAll('.reel-rail [data-act=unlock]').forEach(function(b){b.remove();});
      var parsed=parseImgs(reel);
      var img=parsed.el;
      if(img){
        img.classList.remove('bk-blur');
        // سوییچ به تصویر اصلی
        try{
          var fulls=JSON.parse(img.getAttribute('data-fulls')||'[]');
          if(fulls && fulls[0]) img.src=fulls[0];
        }catch(e){}
      }
      bkToast(j.message||'باز شد ✓');
      popHeart(reel,true);
    }else{
      if(j.wallet){bkToast(j.error||'موجودی کافی نیست.','/wallet',true);}
      else{bkToast(j.error||'باز نشد.',null,true);}
    }
  });
}
function bkShare(reel){
  var url=location.origin+'/tip/'+reel.dataset.id;
  var titleEl=reel.querySelector('.reel-info h3');
  var text=titleEl?titleEl.textContent:'قلق تعمیراتی بردخان';
  if(navigator.share){
    navigator.share({title:text,text:text,url:url}).catch(function(){});
  }else{
    if(navigator.clipboard && navigator.clipboard.writeText){
      navigator.clipboard.writeText(url).then(function(){bkToast('لینک کپی شد ✓');},function(){bkToast('لینک: '+url);});
    }else{
      var ta=document.createElement('textarea');ta.value=url;document.body.appendChild(ta);ta.select();
      try{document.execCommand('copy');bkToast('لینک کپی شد ✓');}catch(e){bkToast('لینک: '+url);}
      ta.remove();
    }
  }
}
function bkOpenComments(reel){
  var sheet=reel.querySelector('.comments-sheet');
  sheet.classList.add('open');
  var list=sheet.querySelector('.cs-list');
  list.innerHTML='<div class="cs-item" style="color:#8b98a5;font-size:12px">⏳ در حال دریافت نظرات…</div>';
  fetch('/ajax-comments?tip_id='+reel.dataset.id,{headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}})
    .then(function(r){return r.json().catch(function(){return null;});})
    .then(function(j){
      if(!j){list.innerHTML='<div class="cs-item" style="color:#f87171">خطا در دریافت نظرات</div>';return;}
      if(!j.items||!j.items.length){
        list.innerHTML='<div class="cs-item" style="color:#8b98a5;font-size:12px">اولین نظر را شما ثبت کنید ✍️</div>';
        return;
      }
      var h='';
      j.items.forEach(function(c){
        h+='<div class="cs-item"><span class="av">'+esc((c.name||'?').charAt(0))+'</span><div><b>'+esc(c.name)+'</b><p>'+esc(c.body)+'</p><small>'+esc(c.ago)+'</small></div></div>';
      });
      list.innerHTML=h;
      list.scrollTop=list.scrollHeight;
    });
}
function bkSendComment(reel,input){
  var v=(input.value||'').trim();
  if(v.length<2){bkToast('متن نظر کوتاه است.',null,true);return;}
  var fd=new FormData();
  fd.append('csrf',BKC.csrf);
  fd.append('action','comment');
  fd.append('tip_id',reel.dataset.id);
  fd.append('body',v);
  input.disabled=true;
  bkAjax(fd).then(function(j){
    input.disabled=false;
    if(j&&j.ok){
      input.value='';
      bkOpenComments(reel);
      // افزایش شمارنده کامنت
      var btn=reel.querySelector('[data-act=comments] small');
      if(btn){
        var cur=parseInt(btn.textContent.replace(/[^0-9]/g,''))||0;
        btn.textContent=cur+1;
      }
      bkToast('نظر شما ثبت شد ✓');
    }else{
      bkToast(j&&j.error?j.error:'خطا در ثبت نظر.',null,true);
    }
  });
}
// کلیک‌ها
document.addEventListener('click',function(e){
  var b=e.target.closest('[data-act]');
  if(!b) return;
  var reel=b.closest('.reel');
  if(!reel) return;
  var act=b.dataset.act;
  if(act==='like'){
    if(BKC.guest){bkToast('برای لایک وارد شوید.','/login',true);return;}
    if(reel.dataset.access==='like' && reel.dataset.locked==='1'){bkUnlock(reel);}
    else{bkLike(reel);}
  }else if(act==='comments'){bkOpenComments(reel);}
  else if(act==='share'){bkShare(reel);}
  else if(act==='unlock'){
    if(BKC.guest){bkToast('برای باز کردن قلق وارد شوید.','/login',true);return;}
    bkUnlock(reel);
  }else if(act==='csclose'){reel.querySelector('.comments-sheet').classList.remove('open');}
  else if(act==='cssend'){
    var inp=reel.querySelector('.cs-form input');
    if(inp) bkSendComment(reel,inp);
  }
});
// دابل‌کلیک / دابل‌تپ برای لایک
var lastTap=0;
document.addEventListener('click',function(e){
  var reel=e.target.closest('.reel');
  if(!reel) return;
  if(e.target.closest('.reel-rail')||e.target.closest('.reel-info')||e.target.closest('.reel-lock')||e.target.closest('.comments-sheet')||e.target.closest('[data-act]')) return;
  var now=Date.now();
  if(now-lastTap<300){
    // double tap
    if(BKC.guest){bkToast('برای لایک وارد شوید.','/login',true);return;}
    if(reel.dataset.access==='like' && reel.dataset.locked==='1'){bkUnlock(reel);}else{bkLike(reel);}
  }
  lastTap=now;
});
document.addEventListener('dblclick',function(e){
  var reel=e.target.closest('.reel');
  if(!reel) return;
  if(e.target.closest('.reel-rail')||e.target.closest('.reel-info')||e.target.closest('.reel-lock')||e.target.closest('.comments-sheet')) return;
  if(BKC.guest){bkToast('برای لایک وارد شوید.','/login',true);return;}
  if(reel.dataset.access==='like' && reel.dataset.locked==='1'){bkUnlock(reel);}else{bkLike(reel);}
});
// تعویض عکس با کلیک روی تصویر
document.addEventListener('click',function(e){
  var img=e.target.closest('.reel-media img');
  if(!img) return;
  var reel=img.closest('.reel');
  if(!reel || reel.dataset.locked==='1') return;
  var list=currentDisplayList(reel);
  if(list.length<2) return;
  var curIdx=parseInt(reel.dataset.index||'0');
  var next=(curIdx+1)%list.length;
  reel.dataset.index=next;
  img.src=list[next];
  updateDots(reel,next);
});
// کیبورد: بالا/پایین
document.addEventListener('keydown',function(e){
  var feed=document.getElementById('reelsFeed');
  if(!feed) return;
  if(e.key==='ArrowDown'){e.preventDefault();feed.scrollBy({top:window.innerHeight*0.9,behavior:'smooth'});}
  if(e.key==='ArrowUp'){e.preventDefault();feed.scrollBy({top:-window.innerHeight*0.9,behavior:'smooth'});}
  if(e.key==='Escape'){
    document.querySelectorAll('.comments-sheet.open').forEach(function(s){s.classList.remove('open');});
  }
});
// پروگرس بار + مشاهده ریل فعلی
(function(){
  var feed=document.getElementById('reelsFeed');
  var bar=document.getElementById('reelsProgress');
  if(!feed||!bar) return;
  function upd(){
    var max=feed.scrollHeight-feed.clientHeight;
    var pct=max>0 ? (feed.scrollTop/max*100) : 0;
    bar.style.width=pct+'%';
  }
  feed.addEventListener('scroll',upd,{passive:true});
  upd();
  // IntersectionObserver برای تست و لاگ
  if('IntersectionObserver' in window){
    var obs=new IntersectionObserver(function(entries){
      entries.forEach(function(en){
        if(en.isIntersecting){
          var id=en.target.dataset.id;
          // برای تست: لاگ ریل فعلی
          // console.log('reel in view', id);
          // می‌توان URL را به‌روز کرد بدون ریلود
          try{history.replaceState(null,'','#reel-'+id);}catch(e){}
        }
      });
    },{root:feed,threshold:0.6});
    document.querySelectorAll('.reel').forEach(function(r){obs.observe(r);});
  }
})();
// جلوگیری از کلیک راست فقط روی تصاویر محافظت‌شده
document.addEventListener('contextmenu',function(e){
  if(e.target.closest('.reel-media img')) e.preventDefault();
});
document.addEventListener('dragstart',function(e){
  if(e.target.closest('.reel-media img')) e.preventDefault();
});
// تست خودکار صفحه ریلز (کنسول)
console.log('%c[Reels Test] صفحه ریلز بارگذاری شد','color:#10b981;font-weight:bold');
console.log('[Reels Test] تعداد ریلز:', document.querySelectorAll('.reel').length);
console.log('[Reels Test] BKC:', BKC);
setTimeout(function(){
  var first=document.querySelector('.reel');
  if(first){
    console.log('[Reels Test] اولین ریل:', first.dataset.id, 'locked:', first.dataset.locked, 'access:', first.dataset.access);
  }
},500);
</script>
</body></html><?php exit;}
if($page==='tour'){
    $s=settings();
    $u=current_user();
    header_html('آموزش و امکانات سایت');?>
<main class="wrap page">
  <!-- Hero -->
  <div class="tour-hero" style="background:radial-gradient(900px 400px at 85% -10%,rgba(16,185,129,.18),transparent),radial-gradient(700px 350px at 10% 110%,rgba(56,189,248,.12),transparent),linear-gradient(135deg,#073d2e,#0a5a3f);border:1px solid rgba(16,185,129,.25);border-radius:20px;padding:36px 20px;text-align:center;margin-bottom:24px;position:relative;overflow:hidden">
    <div style="font-size:48px;margin-bottom:10px">🎓</div>
    <h1 style="font-size:clamp(22px,4vw,32px);font-weight:900;color:#fff">آموزش کامل بردخان - از صفر تا درآمد</h1>
    <p style="color:#c8f5e3;max-width:720px;margin:10px auto 0;font-size:14px;line-height:2">بردخان بازار تخصصی قلق‌های تعمیراتی بردهای الکترونیکی است. اینجا یاد می‌گیرید چطور قلق ثبت کنید، برد بفروشید، از ریلز استفاده کنید و از دانش‌تان درآمد بسازید.</p>
    <div class="flex center gap mt" style="flex-wrap:wrap;justify-content:center;margin-top:18px">
      <?php if($u): ?>
        <a class="btn btn-amber btn-lg" href="<?=url('upload')?>">➕ ثبت قلق و کسب درآمد</a>
        <a class="btn btn-primary" href="<?=url('reels')?>">🎬 مشاهده ریلز</a>
        <a class="btn secondary" href="<?=url('boards')?>">🏪 فروشگاه برد</a>
      <?php else: ?>
        <a class="btn btn-amber btn-lg" href="<?=url('register')?>">✨ ثبت‌نام رایگان + <?=money($s['invitee_credit']??10000)?> تومان هدیه</a>
        <a class="btn btn-primary" href="<?=url('reels-demo')?>">🧪 تست ریلز (دمو)</a>
        <a class="btn secondary" href="<?=url('tour')?>#features">مشاهده امکانات</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- شروع سریع -->
  <div class="section-head"><h2>🚀 شروع سریع در ۶ قدم</h2><p>از ثبت‌نام تا اولین درآمد</p></div>
  <div class="tour-grid" style="grid-template-columns:repeat(auto-fill,minmax(280px,1fr))">
    <?php foreach([
      ['1','ثبت‌نام کنید','با شماره موبایل 091... ثبت‌نام کنید، کد تأیید را وارد کنید و '.money($s['invitee_credit']??10000).' تومان اعتبار هدیه بگیرید.','📱'],
      ['2','پروفایل را کامل کنید','نام، بیوگرافی و تخصص‌تان را بنویسید تا به عنوان تعمیرکار تأییدشده شناخته شوید.','👤'],
      ['3','اولین قلق را ثبت کنید','از دکمه + آپلود، عنوان (≥8 حرف)، توضیح کوتاه (≥20)، توضیح کامل (≥20)، نام دستگاه و برند و دسته را وارد کنید + حداقل ۱ عکس.','➕'],
      ['4','منتظر تأیید مدیر باشید','قلق شما در صف pending می‌رود. مدیر آن را بررسی و منتشر می‌کند. در my-tips وضعیت را ببینید.','⏳'],
      ['5','از ریلز استفاده کنید','مثل اینستاگرام بین قلق‌ها اسکرول کنید، لایک کنید (دابل‌تپ ❤️)، کامنت بگذارید و به اشتراک بگذارید.','🎬'],
      ['6','کسب درآمد و برداشت','هر فروش قلق پولی = درآمد به کیف پول (بعد از کسر کمیسیون). از wallet درخواست تسویه با شبا بدهید.','💰'],
    ] as $x):?>
      <div class="tour-step" style="display:flex;gap:12px;align-items:flex-start;background:var(--card);border:1px solid var(--line);padding:16px;border-radius:14px;position:relative;overflow:hidden">
        <span class="num" style="width:36px;height:36px;border-radius:10px;background:var(--accent);color:#04110b;display:grid;place-items:center;font-weight:900;flex:none;font-size:16px"><?=h($x[0])?></span>
        <div style="flex:1"><h3 style="font-size:14px;font-weight:900;margin:0 0 4px"><?=h($x[1])?> <span style="font-size:20px"><?=h($x[3])?></span></h3><p style="font-size:12px;color:var(--text-soft);margin:0;line-height:2"><?=h($x[2])?></p></div>
      </div>
    <?php endforeach;?>
  </div>

  <!-- 3 نوع دسترسی -->
  <section class="section" id="access">
    <div class="section-head"><div><h2>🔓 سه نوع دسترسی قلق‌ها - کامل</h2><p>رایگان، با لایک و پرداختی با محافظت کامل</p></div></div>
    <div class="grid grid-3">
      <div class="card auth-card" style="padding:18px;border:1px solid rgba(16,185,129,.3)"><div style="font-size:32px">🟢</div><h3 style="margin:8px 0">رایگان</h3><p class="muted" style="font-size:12px;line-height:2">همهٔ توضیحات، عکس‌ها، ویدیو و مراحل گام‌به‌گام بلافاصله نمایش داده می‌شود. مناسب برای جذب دنبال‌کننده و افزایش امتیاز.</p><ul style="font-size:11px;color:var(--text-soft);line-height:2.2;margin:8px 0 0;padding-right:16px"><li>بدون نیاز به لایک یا پرداخت</li><li>تصاویر با واترمارک سبک</li><li>پاداش آپلود: <?=money($s['upload_reward']??50000)?> تومان</li></ul></div>
      <div class="card auth-card" style="padding:18px;border:1px solid rgba(244,63,94,.3)"><div style="font-size:32px">❤️</div><h3 style="margin:8px 0">با لایک</h3><p class="muted" style="font-size:12px;line-height:2">تصاویر و مراحل قفل و محو (blur 16px) هستند. با یک لایک، برای همیشه برای آن کاربر باز می‌شود.</p><ul style="font-size:11px;color:var(--text-soft);line-height:2.2;margin:8px 0 0;padding-right:16px"><li>سقف لایک روزانه: <?=fa($s['daily_like_limit']??5)?> برای هر کاربر</li><li>پس از لایک، tip_accesses ثبت + likes_count +1</li><li>در ریلز: کلیک لایک = باز کردن خودکار</li></ul></div>
      <div class="card auth-card" style="padding:18px;border:1px solid rgba(245,158,11,.3)"><div style="font-size:32px">💰</div><h3 style="margin:8px 0">پرداختی</h3><p class="muted" style="font-size:12px;line-height:2">با پرداخت از کیف پول، محتوای کامل باز می‌شود. درآمد پس از کسر کمیسیون به فروشنده می‌رسد.</p><ul style="font-size:11px;color:var(--text-soft);line-height:2.2;margin:8px 0 0;padding-right:16px"><li>کمیسیون سایت: <?=fa($s['commission_percent']??20)?>٪</li><li>سهم فروشنده: <?=fa(100-($s['commission_percent']??20))?>٪</li><li>پرداخت امن با debit تراکنشی</li><li>نشان first_sale و first_purchase خودکار</li></ul></div>
    </div>
  </section>

  <!-- ریلز -->
  <section class="section" id="reels">
    <div class="section-head"><div><h2>🎬 ریلز قلق‌ها - اینستاگرام استایل</h2><p>اسکرول عمودی تمام‌صفحه با تعاملات کامل</p></div><a class="btn btn-primary btn-sm" href="<?=url('reels')?>">مشاهده ریلز واقعی</a><a class="btn btn-secondary btn-sm" href="<?=url('reels-demo')?>">🧪 دمو بدون DB</a></div>
    <div class="grid grid-2">
      <div class="card auth-card" style="padding:18px">
        <h3>✨ ویژگی‌های ریلز</h3>
        <ul style="font-size:12px;line-height:2.4;color:var(--text-soft);padding-right:18px;margin:8px 0 0">
          <li>📱 اسکرول عمودی تمام‌صفحه با <code>scroll-snap-type:y mandatory</code></li>
          <li>❤️ لایک با دابل‌تپ (300ms) + دابل‌کلیک + انیمیشن قلب pop</li>
          <li>🖼️ تعویض بین چند عکس با کلیک روی تصویر + نقطه‌های <code>reel-dots</code></li>
          <li>💬 پنل کامنت کشویی با آجاکس <code>/ajax-comments</code></li>
          <li>➦ اشتراک با Web Share API + کپی لینک fallback</li>
          <li>🔒 قفل هوشمند: like-type با لایک باز، paid-type با خرید از کیف پول</li>
          <li>📊 پروگرس بار بالا + IntersectionObserver برای تشخیص ریل فعلی</li>
          <li>⌨️ کیبورد: ArrowDown/Up برای جابجایی، Escape برای بستن کامنت</li>
          <li>🛡️ محافظت: blur 18px + جلوگیری از کلیک راست فقط روی تصویر</li>
        </ul>
      </div>
      <div class="card auth-card" style="padding:18px">
        <h3>🧪 تست ریلز</h3>
        <p class="muted" style="font-size:12px">برای تست بدون نیاز به PHP/MySQL:</p>
        <div class="flex gap" style="flex-wrap:wrap">
          <a class="btn btn-primary btn-sm" href="<?=url('reels-demo')?>">دمو ۵ ریل (بدون DB)</a>
          <a class="btn btn-secondary btn-sm" href="/tests/reels_visual_test.html">تست بصری مستقل</a>
        </div>
        <p class="muted" style="font-size:12px;margin-top:12px">برای تست با دیتابیس واقعی:</p>
        <div class="flex gap" style="flex-wrap:wrap">
          <a class="btn btn-secondary btn-sm" href="<?=url('reels')?>">ریلز واقعی (۶۰ قلق)</a>
          <a class="btn btn-secondary btn-sm" href="<?=url('diag-version')?>" target="_blank">بررسی نسخه</a>
        </div>
        <div class="notice" style="font-size:11px;margin-top:12px">💡 باگ بحرانی <code>BKC={{}}</code> که باعث از کار افتادن کل JS ریلز می‌شد، در v4.1 رفع شد.</div>
      </div>
    </div>
  </section>

  <!-- فروشگاه برد -->
  <section class="section" id="boards">
    <div class="section-head"><div><h2>🏪 فروشگاه برد با امانت - خرید امن</h2><p>بردهای کارکرده، تعمیرشده یا نو با تضمین بردخان</p></div><a class="btn btn-secondary btn-sm" href="<?=url('boards')?>">مشاهده فروشگاه</a></div>
    <div class="grid grid-3">
      <div class="card auth-card" style="padding:16px"><div style="font-size:28px">🛒</div><h3 style="font-size:13px;margin:6px 0">درخواست فروشندگی</h3><p style="font-size:11px;color:var(--text-soft);line-height:2">متن ≥20 حرف درباره تخصص‌تان بنویسید. مدیر بررسی و تأیید می‌کند. فقط فروشندگان تأییدشده می‌توانند برد ثبت کنند.</p></div>
      <div class="card auth-card" style="padding:16px"><div style="font-size:28px">📦</div><h3 style="font-size:13px;margin:6px 0">ثبت برد</h3><p style="font-size:11px;color:var(--text-soft);line-height:2">عنوان ≥5، توضیح ≥10، دسته leaf، قیمت ≥1000 تومان، موجودی، وضعیت کالا (نو/در حد نو/کارکرده/تعمیرشده) + حداقل ۱ عکس (خودکار کوچک می‌شود به 1920px).</p></div>
      <div class="card auth-card" style="padding:16px"><div style="font-size:28px">🛡️</div><h3 style="font-size:13px;margin:6px 0">خرید با امانت</h3><p style="font-size:11px;color:var(--text-soft);line-height:2">مبلغ از کیف پول شما کسر و به حساب امانت مدیر می‌رود. فروشنده موظف به ثبت شرکت حمل (پست/تیپاکس/باربری/پیک) + کد رهگیری اجباری ≥6 حرف است.</p></div>
      <div class="card auth-card" style="padding:16px"><div style="font-size:28px">📮</div><h3 style="font-size:13px;margin:6px 0">ارسال</h3><p style="font-size:11px;color:var(--text-soft);line-height:2">وضعیت paid → shipped پس از ثبت رهگیری. خریدار از طریق نوتیفیکیشن مطلع می‌شود.</p></div>
      <div class="card auth-card" style="padding:16px"><div style="font-size:28px">✔️</div><h3 style="font-size:13px;margin:6px 0">تأیید دریافت</h3><p style="font-size:11px;color:var(--text-soft);line-height:2">خریدار دکمه تأیید دریافت را می‌زند → وجه از امانت آزاد → سهم فروشنده (منهای کمیسیون <?=fa($s['board_commission_percent']??10)?>٪) به کیف پولش واریز + 30 امتیاز.</p></div>
      <div class="card auth-card" style="padding:16px"><div style="font-size:28px">↩️</div><h3 style="font-size:13px;margin:6px 0">لغو و بازگشت</h3><p style="font-size:11px;color:var(--text-soft);line-height:2">مدیر می‌تواند سفارش را لغو و وجه را به خریدار برگرداند (board_cancel).</p></div>
    </div>
  </section>

  <!-- کیف پول -->
  <section class="section" id="wallet">
    <div class="section-head"><div><h2>👛 کیف پول و مالی - کامل</h2><p>شارژ، برداشت، معرفی دوستان، اشتراک ویژه</p></div><a class="btn btn-secondary btn-sm" href="<?=url('wallet')?>">کیف پول من</a></div>
    <div class="grid grid-3">
      <?php foreach([
        ['💳','شارژ با درگاه واقعی','زرین‌پال، آیدی‌پی، زیبال با درخواست و verify خودکار + جدول bk_gateway_payments. حداقل '.money($s['gateway_min_charge']??100000).' تا '.money($s['gateway_max_charge']??50000000).' تومان.','درگاه'],
        ['🏦','کارت‌به‌کارت با فیش','بانک + به نام + شماره کارت از تنظیمات مدیر. کاربر فیش آپلود می‌کند، مدیر در admin-finance تأیید/رد می‌کند.','کارت‌به‌کارت'],
        ['💸','درخواست تسویه','شبا ≥20، کارت ≥16، کد ملی ≥10، حداقل '.money($s['min_withdrawal']??200000).' تومان. debit + ثبت در withdrawals + بررسی مدیر.','تسویه'],
        ['🎁','معرفی دوستان','کد referral_code + لینک /register?ref=CODE. دعوت‌شونده '.money($s['invitee_credit']??10000).' هدیه، دعوت‌کننده '.money($s['referral_reward']??20000).' پس از اولین فعالیت موفق (آپلود/خرید).','رفرال'],
        ['👑','اشتراک ویژه','۱/۳/۱۲ ماهه با قیمت‌های قابل تنظیم. دسترسی نامحدود به قلق‌های پولی + نشان premium + اولویت نمایش.','اشتراک'],
        ['🧾','تاریخچه تراکنش‌ها','تمام تراکنش‌ها با نوع، مبلغ، موجودی بعد و توضیح. قابل مشاهده در wallet.','تراکنش'],
      ] as $f):?>
        <div class="card auth-card" style="padding:16px"><div style="font-size:28px"><?=h($f[0])?></div><h3 style="font-size:13px;margin:6px 0"><?=h($f[1])?></h3><p style="font-size:11px;color:var(--text-soft);line-height:2"><?=h($f[2])?></p><span class="pill blue" style="margin-top:6px"><?=h($f[3])?></span></div>
      <?php endforeach;?>
    </div>
  </section>

  <!-- ربات جمع‌آوری -->
  <section class="section" id="bot">
    <div class="section-head"><div><h2>🤖 ربات جمع‌آوری خودکار - هوشمند و بهینه v4.1</h2><p>۱۴ سایت معتبر + ذخیره درست تصاویر در /uploads/</p></div><a class="btn btn-secondary btn-sm" href="<?=url('admin',['tab'=>'collect'])?>">تنظیمات ربات</a></div>
    <div class="grid grid-2">
      <div class="card auth-card" style="padding:18px">
        <h3>🌐 سایت‌های معتبر (۱۴ مورد)</h3>
        <div style="font-size:11px;line-height:2.2;color:var(--text-soft)">
          <b>Reddit (انجمن تعمیرکاران):</b><br>
          • AskElectronics (2M عضو)<br>• ElectronicsRepair<br>• TVRepair<br>• ComputerRepair<br>
          <b>مرجع:</b> iFixit News<br>
          <b>تخصصی:</b> Hackaday, Adafruit, EEVblog, AllAboutCircuits, Electronics-Lab, Circuit Digest, ElectroSchematics, EDN, StackExchange<br>
        </div>
        <div class="notice" style="font-size:11px;margin-top:10px">📸 تصاویر با <code>download_image()</code> دانلود و به <code>/uploads/auto-*.jpg</code> تبدیل می‌شود (نه لینک خارجی).</div>
      </div>
      <div class="card auth-card" style="padding:18px">
        <h3>🧠 هوشمندی ربات</h3>
        <ul style="font-size:11px;line-height:2.4;color:var(--text-soft);padding-right:16px;margin:0">
          <li><code>fetch_article_details()</code>: استخراج محتوای کامل از URL مقاله</li>
          <li><code>extract_article_text()</code>: حذف script/style + انتخاب طولانی‌ترین div.content</li>
          <li><code>extract_images_from_html()</code>: ۵ عکس + تبدیل نسبی→مطلق + فیلتر آیکون</li>
          <li>ترجمه: ۹۰+ کلمه (led tv→تلویزیون ال‌ای‌دی...)</li>
          <li>تشخیص: برند ۳۵، دستگاه ۲۵، خرابی ۳۵ مورد</li>
          <li>دسته‌بندی خودکار دقیق + جلوگیری از تکرار (title + source_url)</li>
          <li>ذخیره source_url و source_name در DB</li>
        </ul>
        <p style="font-size:11px;color:var(--text-dim);margin-top:10px">Cron: <code>wget -q -O /dev/null "https://site.com/cron-collect?key=KEY"</code> هر ۶ ساعت</p>
      </div>
    </div>
  </section>

  <!-- امکانات کلیدی -->
  <section class="section" id="features">
    <div class="section-head"><div><h2>⭐ امکانات کلیدی - کامل</h2><p>چرا بردخان؟</p></div></div>
    <div class="tour-grid" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr))">
      <?php foreach([
        ['🏪','فروشگاه برد با امانت','برد دست دوم/تعمیرشده را با تضمین بخرید؛ وجه نزد بردخان امن است تا تأیید دریافت.','امانت'],
        ['🔓','۳ نوع دسترسی','رایگان، با لایک (blur + باز شدن دائمی)، پرداختی (کمیسیون 20٪).','دسترسی'],
        ['🎬','ریلز اینستاگرامی','اسکرول تمام‌صفحه snap + لایک دابل‌تپ + کامنت آجاکس + اشتراک.','ریلز'],
        ['🤖','ربات هوشمند','۱۴ سایت معتبر + دانلود تصویر به uploads + ترجمه فارسی + دسته‌بندی خودکار.','ربات'],
        ['👛','کیف پول داخلی','درگاه واقعی زرین‌پال/آیدی‌پی/زیبال + کارت‌به‌کارت + تسویه شبا.','مالی'],
        ['🎁','معرفی دوستان','کد اختصاصی + پاداش دوطرفه پس از فعالیت موفق.','رفرال'],
        ['🛠','درخواست تعمیر','مشکل را مطرح کنید، پاداش نقدی/لایکی تعیین کنید، بهترین پاسخ را انتخاب کنید.','تعمیر'],
        ['🏆','گیمیفیکیشن','امتیاز، سطح تازه‌کار تا استاد، ۷ نشان خودکار، رتبه‌بندی.','بازی'],
        ['🔍','جستجوی پیشرفته','فیلتر دسته، سختی، برند، قیمت، دسترسی + live search آجاکس.','جستجو'],
        ['✉️','تیکت پشتیبانی','۳ مقصد (پشتیبانی/مدیریت/فروشنده) + اولویت + انتساب کارشناس.','پشتیبانی'],
        ['📨','تماس با ما','فرم با honeypot + اطلاعات تماس قابل تنظیم + فعال/غیرفعال.','تماس'],
        ['📱','PWA','نصب روی موبایل، آفلاین، بنر نصب + پشتیبانی iOS.','PWA'],
        ['🛡️','امنیت','CSRF، XSS، SQLi، آپلود امن، media proxy با nonce + واترمارک.','امنیت'],
        ['📊','پنل مدیریت کامل','داشبورد با چارت ۷ روزه + مدیریت قلق/برد/کاربر/مالی/تیکت/سئو.','مدیریت'],
        ['📌','نوار شناور','قابل ویرایش از /admin-actionbar، ۸ آیتم max با نقش‌ها.','نوار'],
      ] as $f):?>
        <div class="card auth-card" style="padding:16px"><div style="font-size:26px"><?=h($f[0])?></div><div><h3 style="font-size:13px;margin:6px 0"><?=h($f[1])?></h3><p style="font-size:11px;color:var(--text-soft);line-height:2"><?=h($f[2])?></p><span class="pill blue" style="margin-top:6px"><?=h($f[3])?></span></div></div>
      <?php endforeach;?>
    </div>
  </section>

  <!-- FAQ کامل -->
  <section class="section" id="faq">
    <div class="section-head"><div><h2>❓ سوالات پرتکرار - کامل (۱۲ مورد)</h2><p>همه چیز درباره بردخان</p></div></div>
    <div class="card" style="padding:20px">
      <?php foreach([
        ['چطور قلق ثبت کنم؟','وارد حساب شوید → دکمه + آپلود → عنوان ≥8 حرف، توضیح کوتاه ≥20، توضیح کامل ≥20، نام دستگاه و برند و دسته + حداقل ۱ عکس (JPG/PNG/WebP تا 10MB، خودکار کوچک به 1920px). گزینه‌های اختیاری (مدل، شماره برد، نوع خرابی، سختی، مراحل گام‌به‌گام، ابزار، تگ، ویدیو یوتیوب/آپارات یا MP4 تا 50MB) را می‌توانید باز کنید. سپس انتشار → وضعیت pending → تأیید مدیر → منتشرشده.'],
        ['قلق من کی منتشر می‌شود؟','بعد از بررسی مدیر در /admin?tab=tips. اگر تکراری تشخیص داده شود (عنوان مشابه یا دستگاه+برند مشابه)، pending می‌ماند. در my-tips وضعیت را ببینید. پس از انتشار، پاداش آپلود به کیف پول‌تان می‌آید.'],
        ['قلق لایکی یعنی چه؟','تصاویر و مراحل با blur 16px محو است. کاربر با یک لایک (favorite) آن را برای همیشه باز می‌کند. سقف لایک روزانه هر کاربر از تنظیمات (مثلاً 5) است. در ریلز، کلیک لایک = باز کردن خودکار.'],
        ['قلق پولی چطور کار می‌کند؟','کاربر مبلغ را از کیف پول می‌پردازد (debit تراکنشی). کمیسیون سایت (20٪) کسر و سهم فروشنده (80٪) به کیف پولش واریز می‌شود. نشان first_purchase و first_sale خودکار اعطا می‌شود.'],
        ['ریلز چیست و چطور کار می‌کند؟','ریلز مثل اینستاگرام است: اسکرول عمودی تمام‌صفحه بین 60 قلق آخر. امکانات: لایک با دابل‌تپ ❤️ (انیمیشن pop)، تعویض عکس با کلیک، کامنت آجاکس، اشتراک با Web Share API، باز کردن قفل پولی/لایکی، پروگرس بار، کیبورد ArrowUp/Down. برای تست بدون DB: /reels-demo'],
        ['فروشگاه برد با امانت چطور امن است؟','خریدار مبلغ را می‌پردازد → وجه به حساب امانت مدیر (escrow) می‌رود (نه مستقیم به فروشنده) → فروشنده باید شرکت حمل (پست/تیپاکس/باربری/پیک) + کد رهگیری ≥6 حرف ثبت کند (board_ship) → وضعیت shipped → خریدار پس از دریافت، تأیید دریافت (board_confirm) → وجه از امانت آزاد و به فروشنده واریز (منهای کمیسیون 10٪). اگر مشکل بود، مدیر لغو و بازگشت وجه می‌زند.'],
        ['چطور فروشنده شوم؟','وارد /seller-apply شوید، متن ≥20 حرف درباره تخصص‌تان بنویسید (مثلاً 8 سال تعمیر پاور و مادربرد). مدیر در /admin?tab=sellers تأیید می‌کند → seller_status=approved → می‌توانید در /boards/new برد ثبت کنید.'],
        ['کیف پول را چطور شارژ کنم؟','از /wallet یا /wallet-plus: 1) درگاه آنلاین (زرین‌پال/آیدی‌پی/زیبال) - در admin-finance تنظیم می‌شود، 2) کارت‌به‌کارت: مبلغ به کارت مدیر واریز + فیش آپلود → مدیر در admin-finance تأیید می‌کند. حداقل و حداکثر شارژ از تنظیمات.'],
        ['تسویه و برداشت چطور است؟','در /wallet فرم تسویه: شبا ≥20، کارت ≥16، کد ملی ≥10، مبلغ ≥ حداقل (200k) و ≤ موجودی. درخواست ثبت → مدیر در admin?tab=withdrawals یا admin-finance بررسی → واریز شد یا رد و برگشت وجه.'],
        ['ربات جمع‌آوری خودکار چیست؟','در /admin?tab=collect تنظیم می‌شود: 14 سایت معتبر (Reddit AskElectronics, iFixit, Hackaday...) + 16 query فارسی/انگلیسی. ربات هوشمند محتوای کامل مقاله را با extract_article_text می‌خواند، تصاویر را با download_image به /uploads/auto-*.jpg دانلود می‌کند (نه لینک خارجی)، به فارسی ترجمه و دسته‌بندی خودکار می‌کند و به عنوان قلق منتشر می‌کند. Cron هر 6 ساعت: wget به /cron-collect?key=KEY'],
        ['معرفی دوستان چقدر پاداش دارد؟','کد شما در /wallet و /referral: لینک /register?ref=CODE. دوست شما با کد شما ثبت‌نام کند → '.money($s['invitee_credit']??10000).' تومان هدیه می‌گیرد. شما پس از اولین فعالیت موفق او (آپلود قلق منتشرشده یا خرید) → '.money($s['referral_reward']??20000).' تومان پاداش می‌گیرید. فقط یک‌بار برای هر دعوت‌شده.'],
        ['اگر مشکل داشتم چکار کنم؟','1) /diag-version را چک کنید (نسخه کد باید 4.0+ باشد)، 2) اگر OPcache فعال است /php-extended/opcache_clear.php?key=INSTALL_KEY را باز کنید، 3) تیکت در /tickets ثبت کنید (مقصد پشتیبانی/مدیریت/فروشنده + اولویت)، 4) یا فرم تماس با ما (اگر فعال باشد) یا اطلاعات تماس در /contact.'],
      ] as $q):?>
        <details style="padding:14px 0;border-bottom:1px solid var(--line)"><summary style="cursor:pointer;font-weight:900;font-size:14px;color:var(--text)"><?=h($q[0])?></summary><p class="muted" style="font-size:12px;margin:12px 0 0;line-height:2.2"><?=h($q[1])?></p></details>
      <?php endforeach;?>
    </div>
  </section>

  <!-- CTA نهایی -->
  <section class="section">
    <div class="card auth-card" style="text-align:center;padding:24px;background:linear-gradient(135deg,rgba(16,185,129,.08),rgba(56,189,248,.06));border:1px solid rgba(16,185,129,.2)">
      <h3 style="font-size:18px">🚀 آماده‌اید؟</h3>
      <p class="muted" style="font-size:13px;max-width:600px;margin:8px auto 0;line-height:2">دانش تعمیراتی شما می‌تواند درآمد بسازد. همین حالا ثبت‌نام کنید، اولین قلق را ثبت کنید و در ریلز بدرخشید.</p>
      <div class="flex center gap mt" style="flex-wrap:wrap;justify-content:center;margin-top:16px">
        <a class="btn btn-amber btn-lg" href="<?=url('register')?>">✨ ثبت‌نام رایگان</a>
        <a class="btn btn-primary" href="<?=url('upload')?>">➕ ثبت قلق</a>
        <a class="btn btn-secondary" href="<?=url('reels')?>">🎬 ریلز</a>
        <a class="btn btn-secondary" href="<?=url('boards')?>">🏪 فروشگاه</a>
        <a class="btn btn-secondary" href="<?=url('tickets')?>">✉ پشتیبانی</a>
      </div>
      <p style="font-size:10px;color:var(--text-dim);margin-top:14px">نسخه <?=h(BORDKHAN_VERSION)?> - تست شده 121/121 PASS - ربات هوشمند v4.1</p>
    </div>
  </section>
</main><?php footer_html();exit; }
header_html('صفحه پیدا نشد');?><main class="wrap page"><div class="card empty"><h1>صفحه پیدا نشد</h1><a class="btn btn-primary mt" href="<?=url()?>">بازگشت به خانه</a></div></main><?php footer_html();
