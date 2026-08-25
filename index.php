<?php
require __DIR__ . '/config.php';

/* نسخهٔ کد — برای تشخیص اینکه سرور واقعاً کدام نسخه را اجرا می‌کند */
if (!defined('BORDKHAN_VERSION')) define('BORDKHAN_VERSION', '5.12');

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
    /* v5.3: تصاویر خارجی (http/https) مستقیم از منبع سرو می‌شوند — پروکسی serve فقط برای فایل‌های محلی است */
    if (preg_match('#^https?://#i', trim($path))) return $path;
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
    /* v5.9: اگر کل فرم به‌خاطر post_max_size سرور حذف شده باشد، پیام درست بده */
    if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        if (is_ajax_request()) {
            http_response_code(413);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok'=>false,'error'=>'حجم کل فرم بیش از سقف سرور (post_max_size) است — تعداد یا حجم عکس‌ها را کمتر کنید یا با پشتیبانی هاست بخواهید سقف را افزایش دهند.'], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(413);
            exit('حجم کل فرم بیش از سقف سرور است — عکس‌های کمتر/کوچک‌تر بفرستید.');
        }
        exit;
    }
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
/* ================== ایمیل و کد تأیید (v5.5) ================== */
/** ارسال ایمیل HTML با mail() هاست اشتراکی */
function bk_send_mail(string $to, string $subject, string $bodyHtml): bool {
    $to = strtolower(trim($to));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !function_exists('mail')) return false;
    $host = (string)(parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost');
    $host = preg_replace('/^www\./i', '', $host);
    $from = 'no-reply@' . $host;
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\nFrom: =?UTF-8?B?" . base64_encode('بردخان') . "?= <{$from}>\r\nReply-To: {$from}\r\nX-Mailer: Bordkhan";
    $body = '<!doctype html><html lang="fa" dir="rtl"><body style="margin:0;background:#f2f5f8;padding:24px"><div style="max-width:520px;margin:auto;background:#fff;border-radius:14px;padding:26px;border:1px solid #e3e8ee;direction:rtl;text-align:right;font-family:Tahoma,Arial;line-height:2.1"><div style="font-size:20px;font-weight:900;color:#078659;margin-bottom:10px">⌁ بردخان</div>' . $bodyHtml . '<hr style="border:0;border-top:1px solid #eef1f4;margin:18px 0"><p style="font-size:11px;color:#8b98a5;margin:0">این ایمیل به‌صورت خودکار ارسال شده است؛ لطفاً پاسخ ندهید.</p></div></body></html>';
    $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return @mail($to, $encSubject, $body, $headers);
}
/** ماسک ایمیل برای نمایش: al***@gmail.com */
function bk_mask_email(string $email): string {
    $p = explode('@', $email);
    if (count($p) !== 2) return '***';
    $l = mb_substr($p[0], 0, min(2, mb_strlen($p[0])));
    return $l . '***@' . $p[1];
}
/** ارسال کد ۶ رقمی به ایمیل (انقضا ۱۰ دقیقه، حداکثر ۳ کد در ۱۰ دقیقه) */
function bk_send_email_code(string $email, string $purpose): array {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'آدرس ایمیل معتبر نیست.'];
    $pdo = db();
    try {
        $q = $pdo->prepare("SELECT COUNT(*) FROM email_codes WHERE email=? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
        $q->execute([$email]);
        if ((int)$q->fetchColumn() >= 3) return ['ok' => false, 'error' => 'تعداد درخواست کد زیاد است؛ چند دقیقه بعد دوباره امتحان کنید.'];
        $code = (string)random_int(100000, 999999);
        $pdo->prepare('DELETE FROM email_codes WHERE email=? AND purpose=?')->execute([$email, $purpose]);
        $pdo->prepare('INSERT INTO email_codes(email,code,purpose,expires_at) VALUES(?,?,?,DATE_ADD(NOW(), INTERVAL 10 MINUTE))')->execute([$email, $code, $purpose]);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'خطای دیتابیس در ثبت کد: ' . $e->getMessage()];
    }
    $titles = ['register' => 'تأیید ثبت‌نام', 'gate' => 'تأیید ایمیل', 'reset' => 'بازیابی رمز عبور'];
    $title = $titles[$purpose] ?? 'تأیید';
    $sent = bk_send_mail(
        $email,
        'کد تأیید بردخان — ' . $title,
        '<h2 style="margin:0 0 10px;font-size:15px">کد تأیید ' . $title . '</h2><p style="font-size:13px;color:#334155;margin:0 0 14px">کد تأیید شما:</p><div style="font-size:30px;font-weight:900;letter-spacing:8px;color:#078659;background:#ecfdf5;border:1px dashed #078659;border-radius:12px;padding:12px 18px;text-align:center;direction:ltr">' . $code . '</div><p style="font-size:12px;color:#64748b;margin:14px 0 0">این کد ۱۰ دقیقه اعتبار دارد. اگر شما درخواست نکرده‌اید این ایمیل را نادیده بگیرید.</p>'
    );
    if (!$sent) return ['ok' => false, 'error' => 'ارسال ایمیل روی سرور ممکن نشد (تابع mail غیرفعال است). با پشتیبانی هاست تماس بگیرید.'];
    return ['ok' => true];
}
/** بررسی صحت کد ایمیل + حذف پس از استفاده */
function bk_check_email_code(string $email, string $code, string $purpose): bool {
    $email = strtolower(trim($email));
    $code = preg_replace('/\D/', '', (string)$code);
    if (mb_strlen($code) !== 6) return false;
    try {
        $q = db()->prepare('SELECT id FROM email_codes WHERE email=? AND code=? AND purpose=? AND expires_at > NOW() LIMIT 1');
        $q->execute([$email, $code, $purpose]);
        $id = $q->fetchColumn();
        if (!$id) return false;
        db()->prepare('DELETE FROM email_codes WHERE id=?')->execute([(int)$id]);
        return true;
    } catch (Throwable $e) { return false; }
}
/** آیا کاربر ایمیلش تأیید شده؟ */
function email_verified(?array $u): bool {
    return $u && !empty($u['email']) && (int)($u['email_verified'] ?? 0) === 1;
}
/** متن واترمارک از تنظیمات مدیر (v5.9) */
function watermark_text(): string {
    $s = settings();
    $t = trim((string)($s['watermark_text'] ?? ''));
    return $t !== '' ? $t : '© بردخان';
}
/** پوشش واترمارک متحرک روی عکس/ویدیو — در اسکرین‌شات هم دیده می‌شود (v5.9) */
function bk_watermark_overlay(?array $u): string {
    if ((int)(settings()['watermark_enabled'] ?? 1) !== 1) return '';
    $txt = h(watermark_text()) . ($u ? ' · ' . fa((int)$u['id']) : '');
    $pos = [[6,8],[38,4],[70,10],[16,42],[50,46],[82,40],[8,78],[42,80],[74,76]];
    $html = '<span class="wm-grid" aria-hidden="true">';
    foreach ($pos as $k => $p) { $html .= '<i style="right:' . $p[0] . '%;top:' . $p[1] . '%;animation-delay:' . ($k * 0.7) . 's">' . $txt . '</i>'; }
    return $html . '</span>';
}
/** ثبت رویداد امنیتی (v5.7) */
function sec_log(?int $uid, string $action, string $detail = ''): void {
    try {
        db()->prepare('INSERT INTO security_log(user_id,action,detail,ip,agent) VALUES(?,?,?,?,?)')
            ->execute([$uid, $action, mb_substr($detail, 0, 280), $_SERVER['REMOTE_ADDR'] ?? '', mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 240)]);
    } catch (Throwable $e) {}
}
/** نشست‌های فعال — ثبت/اعتبارسنجی (v5.7) */
function bk_sid_hash(): string { try { return hash('sha256', session_id() ?: ('n' . PHP_SESSION_NONE)); } catch (Throwable $e) { return 'x'; } }
function bk_session_adopt_or_valid(int $uid): bool {
    try {
        $q = db()->prepare('SELECT id FROM user_sessions WHERE user_id=? AND sid_hash=? LIMIT 1');
        $q->execute([$uid, bk_sid_hash()]);
        if ($q->fetchColumn()) return true;
        /* نشست قدیمی (قبل از v5.7) → به‌صورت خودکار ثبت می‌شود تا همه لاگ‌اوت نشوند */
        db()->prepare('INSERT INTO user_sessions(user_id,sid_hash,ip,agent) VALUES(?,?,?,?)')
            ->execute([$uid, bk_sid_hash(), $_SERVER['REMOTE_ADDR'] ?? '', mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 240)]);
        return true;
    } catch (Throwable $e) { return true; /* جدول نبود → مزاحم نشو */ }
}
function bk_session_register(int $uid): void {
    try {
        db()->prepare('DELETE FROM user_sessions WHERE sid_hash=?')->execute([bk_sid_hash()]);
        db()->prepare('INSERT INTO user_sessions(user_id,sid_hash,ip,agent) VALUES(?,?,?,?)')
            ->execute([$uid, bk_sid_hash(), $_SERVER['REMOTE_ADDR'] ?? '', mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 240)]);
    } catch (Throwable $e) {}
}
function bk_session_drop(): void { try { db()->prepare('DELETE FROM user_sessions WHERE sid_hash=?')->execute([bk_sid_hash()]); } catch (Throwable $e) {} }
/** دروازهٔ تأیید ایمیل برای خرید/ثبت قلق */
function bk_require_email_verified(array $u, string $backUrl, bool $ajax = false): void {
    if (email_verified($u)) return;
    if (staff($u)) return; /* v5.9: مدیر/ناظر از دروازهٔ تأیید ایمیل معاف است */
    $_SESSION['email_gate'] = ['back' => $backUrl];
    if ($ajax) bk_json_out(['ok' => false, 'error' => 'برای ادامه باید ایمیل خود را تأیید کنید — کد تأیید به ایمیل شما ارسال شد.', 'need_verify' => true, 'url' => url('verify-email')], 403);
    flash('برای خرید یا ثبت قلق ابتدا باید ایمیل خود را تأیید کنید. کد تأیید به ایمیل شما ارسال شد.', 'error');
    redirect_to('verify-email');
}
function admin_user(?array $u): bool { return $u && in_array($u['role'], ['admin','superadmin'], true); }
function current_user(): ?array { static $user = false; if ($user !== false) return $user; $id = (int)($_SESSION['user_id'] ?? 0); if (!$id) return $user = null; $s = db()->prepare('SELECT * FROM users WHERE id=? LIMIT 1'); $s->execute([$id]); $u = $s->fetch() ?: null; if ($u && !empty($u['is_deleted'])) $u = null; if ($u && !bk_session_adopt_or_valid((int)$u['id'])) { unset($_SESSION['user_id']); return $user = null; } return $user = $u; }
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
        'watermark_enabled'=>1,
        'watermark_text'=>'',
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
function notify_user(int $id, string $type, string $title, string $body, string $link=''): void { $s=db()->prepare('INSERT INTO notifications(user_id,type,title,body,link) VALUES(?,?,?,?,?)'); $s->execute([$id,$type,$title,$body,$link]); if(function_exists('bk_send_push')){try{bk_send_push($id,$title,$body,$link);}catch(\Throwable $e){}} } 
/* ---------- Web Push Notifications (v5.12) ---------- */
function bk_vapid_keys(): array {
    $s = settings();
    $pub = trim((string)($s['vapid_public_key'] ?? ''));
    $prv = trim((string)($s['vapid_private_key'] ?? ''));
    if ($pub !== '' && $prv !== '') return ['public' => $pub, 'private' => $prv];
    // Generate VAPID keys if not exists
    if (function_exists('openssl_pkey_new') && defined('OPENSSL_ALGO_SHA256')) {
        try {
            $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
            if ($key) {
                $detail = openssl_pkey_get_details($key);
                $d = $detail['ec']['d'] ?? '';
                $x = str_pad($detail['ec']['x'] ?? '', 32, "\0", STR_PAD_LEFT);
                $y = str_pad($detail['ec']['y'] ?? '', 32, "\0", STR_PAD_LEFT);
                $pubKey = chr(4) . $x . $y;
                $pubB64 = rtrim(strtr(base64_encode($pubKey), '+/', '-_'), '=');
                $prvB64 = rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
                try { db()->prepare("UPDATE settings SET vapid_public_key=?, vapid_private_key=? WHERE id=1")->execute([$pubB64, $prvB64]); } catch(\Throwable $e) {}
                return ['public' => $pubB64, 'private' => $prvB64];
            }
        } catch(\Throwable $e) {}
    }
    return ['public' => '', 'private' => ''];
}
function bk_send_push(int $userId, string $title, string $body, string $link = ''): void {
    try {
        $q = db()->prepare('SELECT endpoint,p256dh,auth FROM push_subscriptions WHERE user_id=?');
        $q->execute([$userId]);
        $subs = $q->fetchAll();
        if (!$subs) return;
        $keys = bk_vapid_keys();
        if ($keys['public'] === '' || $keys['private'] === '') return;
        $payload = json_encode(['title' => $title, 'body' => mb_substr($body, 0, 200), 'link' => $link, 'id' => $userId . '-' . time(), 'tag' => 'bk-' . $userId], JSON_UNESCAPED_UNICODE);
        foreach ($subs as $sub) {
            try {
                bk_web_push_send($sub['endpoint'], $sub['p256dh'], $sub['auth'], $payload, $keys);
            } catch(\Throwable $e) {
                // Remove dead subscription
                if (str_contains($e->getMessage(), '410') || str_contains($e->getMessage(), '404')) {
                    db()->prepare('DELETE FROM push_subscriptions WHERE endpoint=?')->execute([$sub['endpoint']]);
                }
            }
        }
    } catch(\Throwable $e) {}
}
function bk_web_push_send(string $endpoint, string $p256dh, string $auth, string $payload, array $vapid): void {
    if (!function_exists('curl_init')) return;
    // Simplified Web Push - uses the VAPID JWT for authorization
    $aud = parse_url(SITE_URL, PHP_URL_HOST);
    $now = time();
    $jwtHeader = rtrim(strtr(base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256'])), '+/', '-_'), '=');
    $jwtClaim = rtrim(strtr(base64_encode(json_encode(['aud' => 'https://' . $aud, 'exp' => $now + 86400, 'sub' => 'mailto:push@' . $aud])), '+/', '-_'), '=');
    // For simplicity, we use a basic auth header - full ECDH encryption requires more complex implementation
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'TTL: 86400',
        ],
        CURLOPT_TIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 400) throw new \RuntimeException("Push failed: HTTP $code");
}

function bk_notif_icon(string $t): string { return ['admin'=>'⚙️','system'=>'📢','ticket'=>'🎫','wallet'=>'👛','board'=>'🏪','sale'=>'💰','like'=>'❤️','follow'=>'👥','badge'=>'🏅','repair'=>'🛠'][$t] ?? '🔔'; }
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
    /* v5.8: همهٔ پسوندهای تصویری پذیرفته می‌شوند — JPEG/PNG/WebP/BMP با GD به JPG فشرده
       می‌شوند؛ GIF (حفظ انیمیشن) دست‌نخورده؛ AVIF/HEIC/TIFF با Imagick تبدیل یا اصلی؛ SVG پاک‌سازی می‌شود */
    if(($file['error']??1)!==UPLOAD_ERR_OK)return null;
    if(($file['size']??0)>12*1024*1024)return null;
    $mime=strtolower((string)file_mime($file['tmp_name']));
    $ext=strtolower(pathinfo((string)($file['name']??''),PATHINFO_EXTENSION));
    $byMime=['image/jpeg'=>'jpg','image/pjpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','image/bmp'=>'bmp','image/x-ms-bmp'=>'bmp','image/x-bitmap'=>'bmp','image/avif'=>'avif','image/heic'=>'heic','image/heif'=>'heif','image/svg+xml'=>'svg','image/tiff'=>'tif'];
    $extOk=['jpg','jpeg','png','webp','gif','bmp','avif','heic','heif','svg','tif','tiff'];
    $out=$byMime[$mime] ?? null;
    if($out===null && in_array($ext,$extOk,true))$out=$ext==='jpeg'?'jpg':($ext==='tiff'?'tif':$ext);
    if($out===null)return null;
    if(!is_dir(UPLOAD_DIR))@mkdir(UPLOAD_DIR,0755,true);
    if(!is_dir(UPLOAD_DIR))return null;

    if($out==='svg'){
        $raw=(string)@file_get_contents($file['tmp_name']);
        if($raw===''||stripos($raw,'<svg')===false)return null;
        $raw=preg_replace('/<script\b.*?<\/script>/is','',$raw) ?? $raw;
        $raw=preg_replace('/\son\w+\s*=\s*"[^"]*"/i','',$raw) ?? $raw;
        $raw=preg_replace("/\son\w+\s*=\s*'[^']*'/i",'',$raw) ?? $raw;
        $raw=str_ireplace('javascript:','',$raw);
        $name=date('YmdHis').'-'.bin2hex(random_bytes(5)).'.svg';
        if(@file_put_contents(UPLOAD_DIR.'/'.$name,$raw)===false)return null;
        return '/uploads/'.$name;
    }
    if($out==='gif'){
        $name=date('YmdHis').'-'.bin2hex(random_bytes(5)).'.gif';
        if(!@move_uploaded_file($file['tmp_name'],UPLOAD_DIR.'/'.$name))return null;
        return '/uploads/'.$name;
    }
    if(in_array($out,['avif','heic','heif','tif'],true)&&class_exists('Imagick')){
        try{
            $im=new Imagick($file['tmp_name']);
            $im->setImageFormat('jpeg');
            $im->thumbnailImage(1920,1920,true);
            $name=date('YmdHis').'-'.bin2hex(random_bytes(5)).'.jpg';
            $im->writeImage(UPLOAD_DIR.'/'.$name);
            $im->destroy();
            if(@file_exists(UPLOAD_DIR.'/'.$name))return '/uploads/'.$name;
        }catch(Throwable $e){}
    }
    $src=null;
    if($out==='jpg'&&function_exists('imagecreatefromjpeg'))$src=@imagecreatefromjpeg($file['tmp_name']);
    elseif($out==='png'&&function_exists('imagecreatefrompng'))$src=@imagecreatefrompng($file['tmp_name']);
    elseif($out==='webp'&&function_exists('imagecreatefromwebp'))$src=@imagecreatefromwebp($file['tmp_name']);
    elseif($out==='bmp'&&function_exists('imagecreatefrombmp'))$src=@imagecreatefrombmp($file['tmp_name']);
    if($src!==null&&$src!==false){
        if($out==='jpg'&&function_exists('exif_read_data')){$ex=@exif_read_data($file['tmp_name']);$o=(int)($ex['Orientation']??0);if($o===3)$src=imagerotate($src,180,0);elseif($o===6)$src=imagerotate($src,-90,0);elseif($o===8)$src=imagerotate($src,90,0);}
        $w=imagesx($src);$h=imagesy($src);$max=1920;
        if($w>$max||$h>$max){$r=min($max/$w,$max/$h);$nw=max(1,(int)round($w*$r));$nh=max(1,(int)round($h*$r));$dst=imagecreatetruecolor($nw,$nh);imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);imagedestroy($src);$src=$dst;}
        $name=date('YmdHis').'-'.bin2hex(random_bytes(5)).'.jpg';
        $target=UPLOAD_DIR.'/'.$name;
        imagejpeg($src,$target,82);imagedestroy($src);
        if(@filesize($target)>4*1024*1024){$im=@imagecreatefromjpeg($target);if($im!==false){imagejpeg($im,$target,62);imagedestroy($im);}}
        return @file_exists($target)?'/uploads/'.$name:null;
    }
    $name=date('YmdHis').'-'.bin2hex(random_bytes(5)).'.'.$out;
    if(!@move_uploaded_file($file['tmp_name'],UPLOAD_DIR.'/'.$name)){
        if(!@copy($file['tmp_name'],UPLOAD_DIR.'/'.$name))return null;
    }
    return '/uploads/'.$name;
}
/** علت شکست آپلود — پیام قابل فهم (v5.8) */
function bk_upload_error_reason(array $f): string {
    $e=(int)($f['error']??1);
    if($e===UPLOAD_ERR_INI_SIZE||$e===UPLOAD_ERR_FORM_SIZE)return 'بیش از سقف آپلود سرور — عکس کوچک‌تر بفرستید یا از پشتیبانی هاست بخواهید upload_max_filesize را افزایش دهند';
    if($e===UPLOAD_ERR_PARTIAL)return 'انتقال فایل ناقص ماند — دوباره تلاش کنید';
    if($e===UPLOAD_ERR_NO_FILE)return 'فایلی دریافت نشد';
    if(($f['size']??0)>12*1024*1024)return 'بیشتر از ۱۲ مگابایت';
    return 'فرمت تصویری معتبر تشخیص داده نشد';
}
function save_video(array $file): ?string { if(($file['error']??1)!==UPLOAD_ERR_OK)return null; if(($file['size']??0)>50*1024*1024)return null; $ext=strtolower(pathinfo((string)($file['name']??''),PATHINFO_EXTENSION)); $mime=file_mime($file['tmp_name']); if($mime!=='video/mp4' && $ext!=='mp4')return null; if(!is_dir(UPLOAD_DIR))@mkdir(UPLOAD_DIR,0755,true); if(!is_dir(UPLOAD_DIR))return null; $name=date('YmdHis').'-'.bin2hex(random_bytes(5)).'.mp4'; $target=UPLOAD_DIR.'/'.$name; if(!@move_uploaded_file($file['tmp_name'],$target))return null; return '/uploads/'.$name; }
function bk_video_codec(string $path): string { $sz=@filesize($path); if(!$sz)return 'unknown'; $fp=@fopen($path,'rb'); if(!$fp)return 'unknown'; $head=@fread($fp,min($sz,2*1024*1024)); $tail=''; if($sz>2*1024*1024){@fseek($fp,$sz-2*1024*1024);$tail=@fread($fp,2*1024*1024);} @fclose($fp); $d=$head.$tail; if(strpos($d,'hvc1')!==false||strpos($d,'hev1')!==false)return 'h265'; if(strpos($d,'avc1')!==false||strpos($d,'avc3')!==false)return 'h264'; if(strpos($d,'mp4v')!==false)return 'mpeg4'; return 'unknown'; }
function video_embed(string $url, array $tip, ?array $u): string { $url=trim($url); if($url==='')return ''; $host=parse_url(SITE_URL,PHP_URL_HOST); if($host&&preg_match('#^https?://'.preg_quote($host,'#').'(/uploads/.+)$#i',$url,$m)){$url=$m[1];} if(str_starts_with($url,'/uploads/')||str_starts_with($url,'uploads/')){ $src=media_url($url,'vid',(int)$tip['id'],$u?(int)$u['id']:0); $vidId='bkvid'.(int)$tip['id']; $pid='bkvidwrap'.(int)$tip['id']; $codec='unknown'; $vf=UPLOAD_DIR.'/'.basename(ltrim($url,'/')); if(is_file($vf))$codec=bk_video_codec($vf); $css='<style>'.str_replace('PID',$pid,'#PID{position:relative;background:#000}#PID video.bk-video{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;background:#000;display:block}#PID .bk-ui{position:absolute;inset:0;z-index:6;pointer-events:none;direction:ltr}#PID .bk-ui button,#PID .bk-ui input,#PID .bk-ui select{pointer-events:auto}#PID .bk-big{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:72px;height:72px;border-radius:50%;background:rgba(10,16,24,.72);border:2px solid rgba(255,255,255,.9);color:#fff;font-size:26px;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:8;transition:.15s}#PID .bk-big:hover{background:#10b981;color:#04110b}#PID .bk-spin{position:absolute;top:50%;left:50%;width:44px;height:44px;margin:-22px 0 0 -22px;border:4px solid rgba(255,255,255,.25);border-top-color:#10b981;border-radius:50%;animation:bkspin .8s linear infinite;display:none;z-index:8}@keyframes bkspin{to{transform:rotate(360deg)}}#PID .bk-bar{position:absolute;left:0;right:0;bottom:0;display:flex;align-items:center;gap:8px;padding:8px 10px;background:linear-gradient(transparent,rgba(0,0,0,.85));z-index:7}#PID .bk-btn{background:transparent;border:0;color:#fff;font-size:16px;cursor:pointer;width:30px;height:30px;display:grid;place-items:center;flex:none;text-shadow:0 1px 4px rgba(0,0,0,.8)}#PID .bk-btn:hover{color:#10b981}#PID .bk-time{color:#e5e7eb;font-size:11px;font-variant-numeric:tabular-nums;flex:none;min-width:88px;text-align:center}#PID .bk-seek{position:relative;flex:1;height:14px;display:flex;align-items:center;min-width:40px}#PID .bk-buf,#PID .bk-done{position:absolute;left:0;top:50%;transform:translateY(-50%);height:4px;border-radius:3px;pointer-events:none}#PID .bk-buf{background:rgba(255,255,255,.35);width:0}#PID .bk-done{background:#10b981;width:0;z-index:1}#PID .bk-range{-webkit-appearance:none;appearance:none;width:100%;height:14px;background:transparent;cursor:pointer;position:relative;z-index:2;margin:0;padding:0}#PID .bk-range::-webkit-slider-runnable-track{height:4px;background:rgba(255,255,255,.18);border-radius:3px}#PID .bk-range::-webkit-slider-thumb{-webkit-appearance:none;width:14px;height:14px;border-radius:50%;background:#10b981;border:2px solid #fff;margin-top:-5px;box-shadow:0 0 6px rgba(0,0,0,.5)}#PID .bk-range::-moz-range-track{height:4px;background:rgba(255,255,255,.18);border-radius:3px}#PID .bk-range::-moz-range-thumb{width:12px;height:12px;border-radius:50%;background:#10b981;border:2px solid #fff}#PID .bk-vol{width:80px;flex:none;display:flex;align-items:center}#PID .bk-rate{background:rgba(10,16,24,.85);color:#fff;border:1px solid rgba(255,255,255,.25);border-radius:8px;font-size:11px;padding:3px 4px;flex:none;cursor:pointer}#PID .bk-err{position:absolute;left:10px;right:10px;bottom:46px;z-index:9;display:none;background:rgba(127,29,29,.94);color:#fff;font-size:12px;line-height:1.9;padding:8px 12px;border-radius:10px;border:1px solid rgba(248,113,113,.5)}#PID .bk-codec{position:absolute;top:10px;left:10px;z-index:8;background:rgba(10,16,24,.7);color:#9fe8c4;font-size:11px;padding:3px 10px;border-radius:8px;border:1px solid rgba(255,255,255,.15)}#PID .bk-codec.warn{background:rgba(120,53,15,.85);color:#fde68a;border-color:rgba(251,191,36,.4)}@media(max-width:560px){#PID .bk-vol{display:none}#PID .bk-rate{font-size:10px}#PID .bk-time{min-width:70px;font-size:10px}}').'</style>'; $js='<script>'.str_replace(['VID','PID','CODEC'],[$vidId,$pid,$codec],'(function(){try{var v=document.getElementById("VID");if(!v)return;var w=document.getElementById("PID");if(!w)return;var ui=w.querySelector(".bk-ui");if(!ui)return;var big=ui.querySelector(".bk-big"),spin=ui.querySelector(".bk-spin"),pb=ui.querySelector(".bk-play"),mu=ui.querySelector(".bk-mute"),fs=ui.querySelector(".bk-fs"),tm=ui.querySelector(".bk-time"),sk=ui.querySelector(".bk-seekr"),dn=ui.querySelector(".bk-done"),bf=ui.querySelector(".bk-buf"),vl=ui.querySelector(".bk-volr"),rt=ui.querySelector(".bk-rate"),er=ui.querySelector(".bk-err");var codec="CODEC";function f(s){if(!isFinite(s))return "0:00";s=Math.max(0,Math.floor(s));var m=Math.floor(s/60),x=s%60;return m+":"+(x<10?"0":"")+x;}function up(){if(sk&&v.duration){var p=v.currentTime/v.duration;sk.value=Math.round(p*1000);dn.style.width=(p*100)+"%";}tm.textContent=f(v.currentTime)+" / "+f(v.duration);}function bu(){if(sk&&v.duration&&v.buffered&&v.buffered.length){var e=v.buffered.end(v.buffered.length-1);bf.style.width=(e/v.duration*100)+"%";}}function sp(){pb.textContent=v.paused?"▶":"❚❚";big.style.display=v.paused?"flex":"none";}function err(){var c=v.error?v.error.code:0;var m=["","قطع/توقف پخش","خطای شبکه یا سرور","کدک یا فایل ناسازگار","فرمت پشتیبانینشده"][c]||("خطای "+c);if(codec==="h265"||c===3){m="کدک H.265/HEVC — این مرورگر نمی‌تواند آن را پخش کند؛ ویدیو را با H.264 دوباره آپلود کنید.";}er.textContent="⚠️ "+m;er.style.display="block";spin.style.display="none";big.style.display="none";}function play(){var p=v.play();if(p&&p.catch){p.catch(err);}setTimeout(function(){if(v.paused&&!v.error&&!v.ended){er.textContent="⏳ ویدیو در حال بارگذاری است… لطفاً چند لحظه صبر کنید.";er.style.display="block";}},4000);}pb.addEventListener("click",function(){if(v.paused){play();}else{v.pause();}});big.addEventListener("click",play);v.addEventListener("click",function(){if(v.paused){play();}else{v.pause();}});sk.addEventListener("input",function(){if(v.duration){v.currentTime=(sk.value/1000)*v.duration;up();}});mu.addEventListener("click",function(){v.muted=!v.muted;mu.textContent=v.muted?"🔇":"🔊";});vl.addEventListener("input",function(){v.volume=parseFloat(vl.value);v.muted=(v.volume===0);mu.textContent=v.muted?"🔇":"🔊";});rt.addEventListener("change",function(){v.playbackRate=parseFloat(rt.value);});fs.addEventListener("click",function(){if(document.fullscreenElement||document.webkitFullscreenElement){if(document.exitFullscreen){document.exitFullscreen();}else if(document.webkitExitFullscreen){document.webkitExitFullscreen();}}else{var done=false;if(v.requestFullscreen){var p=v.requestFullscreen();if(p&&p.catch){p.catch(function(){});}done=true;}else if(v.webkitEnterFullscreen){v.webkitEnterFullscreen();done=true;}if(!done&&w.requestFullscreen){w.requestFullscreen();}}});document.addEventListener("fullscreenchange",function(){v.controls=(document.fullscreenElement===v);});v.addEventListener("error",err);v.addEventListener("play",sp);v.addEventListener("pause",sp);v.addEventListener("ended",sp);v.addEventListener("timeupdate",up);v.addEventListener("loadedmetadata",up);v.addEventListener("progress",bu);v.addEventListener("waiting",function(){spin.style.display="block";});v.addEventListener("playing",function(){spin.style.display="none";er.style.display="none";});v.addEventListener("canplay",function(){spin.style.display="none";});sp();up();v.controls=false;}catch(e){}})();').'</script>'; $codecNote=$codec==='h265'?'<div class="bk-codec warn">⚠️ این ویدیو با کدک H.265/HEVC است و روی برخی مرورگرها پخش نمی‌شود — با H.264 دوباره آپلود کنید.</div>':($codec==='h264'?'<div class="bk-codec">کدک: H.264 ✓</div>':''); return '<div class="mt media-protect full-lock video-lock bk-player" id="'.$pid.'">'.$css.'<video id="'.$vidId.'" class="no-save bk-video" controls playsinline preload="auto" src="'.h($src).'"></video>'.bk_watermark_overlay(null).'<span class="wm">'.h(watermark_text()).'</span>'.'<div class="bk-ui">'.'<button type="button" class="bk-big" aria-label="پخش">▶</button>'.'<div class="bk-spin"></div>'.'<div class="bk-bar">'.'<button type="button" class="bk-btn bk-play">▶</button>'.'<span class="bk-time">0:00 / 0:00</span>'.'<div class="bk-seek"><div class="bk-buf"></div><div class="bk-done"></div><input class="bk-range bk-seekr" type="range" min="0" max="1000" step="1" value="0" aria-label="نوار پیشرفت"></div>'.'<button type="button" class="bk-btn bk-mute">🔊</button>'.'<div class="bk-vol"><input class="bk-range bk-volr" type="range" min="0" max="1" step="0.05" value="1" aria-label="صدا"></div>'.'<select class="bk-rate" aria-label="سرعت"><option value="0.5">0.5×</option><option value="0.75">0.75×</option><option value="1" selected>1×</option><option value="1.25">1.25×</option><option value="1.5">1.5×</option><option value="2">2×</option></select>'.'<button type="button" class="bk-btn bk-fs">⛶</button>'.'</div>'.'<div class="bk-err" role="alert"></div>'.'</div>'.$codecNote.$js.'</div>'; } if(preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{6,})~',$url,$m)){ return '<div class="mt video-lock"><iframe class="no-save" style="width:100%;aspect-ratio:16/9;border:0;border-radius:14px" src="https://www.youtube.com/embed/'.h($m[1]).'" allowfullscreen loading="lazy"></iframe></div>'; } if(preg_match('~aparat\.com/v/([\w-]+)~',$url,$m)){ return '<div class="mt video-lock"><iframe class="no-save" style="width:100%;aspect-ratio:16/9;border:0;border-radius:14px" src="https://www.aparat.com/video/video/embed/videohash/'.h($m[1]).'/vt/frame" allowfullscreen loading="lazy"></iframe></div>'; } return '<div class="mt"><a class="btn btn-secondary" href="'.h($url).'" target="_blank" rel="noopener">▶ مشاهده ویدیوی آموزشی</a></div>'; }
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
function escrow_admin_id(): int { static $id = null; if ($id === null) { $q = db()->query("SELECT id FROM users WHERE role IN ('superadmin','admin') ORDER BY id LIMIT 1"); $id = (int)($q->fetchColumn() ?: 0); } return $id; }
function is_seller(array $u): bool { return ($u['seller_status'] ?? 'none') === 'approved' || in_array($u['role'] ?? '', ['admin','superadmin'], true); }
function board_condition_label(string $c): string { return ['new'=>'نو','like_new'=>'در حد نو','used'=>'کارکرده','repair'=>'تعمیرشده'][$c] ?? 'کارکرده'; }
function board_status_label(string $s): string { return ['pending'=>'در انتظار تأیید','approved'=>'فعال','rejected'=>'رد شده','sold'=>'فروخته شد','archived'=>'بایگانی'][$s] ?? $s; }
function order_status_label(string $s): string { return ['paid'=>'پرداخت شده (امانت)','shipped'=>'ارسال شده','completed'=>'تحویل تأیید شد','cancelled'=>'لغو و بازگشت وجه'][$s] ?? $s; }
function leaf_categories(): array { $rows = db()->query("SELECT id,parent_id,name FROM categories WHERE status='active' ORDER BY sort_order,name")->fetchAll(); $byParent=[]; $byId=[]; foreach($rows as $r){ $byParent[(int)$r['parent_id']][]=$r; $byId[(int)$r['id']]=$r; } $out=[]; foreach($rows as $r){ $hasKids = !empty($byParent[(int)$r['id']]); if(!$hasKids){ $path=[]; $cur=$r; while($cur && ($cur['parent_id'])){ $path[]= $cur['name']; $cur = $byId[(int)$cur['parent_id']] ?? null; } if($cur){$path[]=$cur['name'];} $out[]=['id'=>(int)$r['id'],'label'=>implode(' ← ',array_reverse($path))]; } } return $out; }
function board_card(array $b): void { $imgs = json_decode_array($b['images_json'] ?? '[]'); ?><a class="card tcard" href="<?=url('board/'.(int)$b['id'])?>"><div class="timg"><?php if($imgs):?><img loading="lazy" src="<?=h($imgs[0])?>" alt="<?=h($b['title'])?>"><?php else:?><div style="height:100%;display:grid;place-items:center;font-size:42px">🔩</div><?php endif;?><div class="badges"><span class="pill green"><?=h(board_condition_label($b['condition_status']))?></span><?php if($b['status']==='sold'):?><span class="pill rose">فروخته شد</span><?php endif;?></div></div><div class="tbody"><h3><?=h($b['title'])?></h3><div class="tmeta"><?php if(!empty($b['brand'])):?><span class="pill"><?=h($b['brand'])?></span><?php endif;?><?php if(!empty($b['model'])):?><span class="pill"><?=h($b['model'])?></span><?php endif;?></div><div class="tfoot"><strong style="color:var(--accent);font-size:16px"><?=money($b['price'])?> تومان</strong><span class="muted" style="margin-right:auto;font-size:11px">👁 <?=fa($b['views'])?></span></div></div></a><?php }
function tip_card(array $t): void { $imgs=tip_images($t); $rating=((int)$t['rating_count']>0)?round((int)$t['rating_sum']/(int)$t['rating_count'],1):0; $locked=!tip_has_access($t,current_user()); ?><a class="card tip-card" href="<?=url('tip/'.$t['id'])?>"><div class="tip-img"><?php if($imgs):?><img loading="lazy" src="<?=h(media_url($imgs[0], 'thumb', (int)$t['id']))?>" alt="<?=h($t['title'])?>" class="<?=$locked?'bk-blur':''?>"><?php if($locked):?><span class="bk-lockbadge" title="<?=h($t['access_type']==='paid'?'پس از خرید نمایش داده می‌شود':'پس از لایک نمایش داده می‌شود')?>"><?=$t['access_type']==='paid'?'💰':'♥'?></span><?php endif;?><?php else:?><div style="height:100%;display:grid;place-items:center;font-size:45px">🔧</div><?php endif;?><div class="badges"><span class="pill <?=h($t['access_type']==='paid'?'amber':($t['access_type']==='like'?'rose':'green'))?>"><?=h(access_label($t['access_type'],(int)$t['price']))?></span><?php if((int)$t['featured']):?><span class="pill amber">★ منتخب</span><?php endif;?></div></div><div class="tip-body"><h3><?=h($t['title'])?></h3><p><?=h($t['short_description'])?></p><div class="tip-meta"><span class="pill"><?=h(['easy'=>'آسان','medium'=>'متوسط','hard'=>'سخت'][$t['difficulty']??'medium']??'متوسط')?></span><span class="pill">◉ <?=fa($t['views'])?></span><?php if($rating):?><span class="pill amber">★ <?=fa($rating)?></span><?php endif;?></div><div class="tip-footer"><span class="avatar small"><?=h(mb_substr($t['author_name']??'؟',0,1))?></span><small class="muted"><?=h($t['author_name']??'تعمیرکار')?></small><?php if(!empty($t['verified'])):?><span class="check">✓</span><?php endif;?><?php if(has_favorite((int)$t['id'], current_user())):?><span title="علاقه‌مندی من" style="margin-right:auto;color:#d94258">♥</span><?php endif;?></div></div></a><?php }
function header_html(string $title=''): void { $u=current_user(); $s=settings(); $active=bk_route();
    @header('X-Content-Type-Options: nosniff');
    @header('X-Frame-Options: SAMEORIGIN');
    @header('Referrer-Policy: strict-origin-when-cross-origin');
    @header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    /* v5.7: CSP — اسکریپت/استایل این‌لاین مجاز (کد پایه)، منابع بیرونی محدود */
    $csp = "default-src 'self'; script-src 'self' 'unsafe-inline'" . (!empty($s['google_analytics']) ? ' https://www.googletagmanager.com https://www.google-analytics.com' : '') . "; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:; media-src 'self' blob: https:; frame-src https://www.youtube.com https://www.aparat.com; connect-src 'self'" . (!empty($s['google_analytics']) ? ' https://www.google-analytics.com https://analytics.google.com' : '') . "; font-src 'self' data:; object-src 'none'; base-uri 'self'";
    @header('Content-Security-Policy: ' . $csp);
    if ($u) { @header('Cache-Control: no-store, max-age=0'); @header('Vary: Cookie'); } ?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="<?=h(($s['meta_description'] ?? 'بازار تخصصی قلق‌های تعمیراتی بردهای الکترونیکی'))?>"><?php if(!empty($s['meta_keywords'])):?><meta name="keywords" content="<?=h($s['meta_keywords'])?>"><?php endif;?><meta name="theme-color" content="#078659"><meta property="og:type" content="website"><meta property="og:site_name" content="<?=h($s['site_title'] ?? SITE_NAME)?>"><meta property="og:title" content="<?=h($title ?: ($s['site_title'] ?? SITE_NAME))?>"><meta property="og:description" content="<?=h(($s['meta_description'] ?? 'بازار تخصصی قلق‌های تعمیراتی بردهای الکترونیکی'))?>"><?php if(!empty($s['og_image'])):?><meta property="og:image" content="<?=h($s['og_image'])?>"><?php endif;?><meta name="twitter:card" content="summary_large_image"><link rel="manifest" href="<?=url('manifest.webmanifest')?>"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><meta name="apple-mobile-web-app-title" content="<?=h($s['site_title'] ?? SITE_NAME)?>"><link rel="apple-touch-icon" href="<?=url('assets/icon-192.png')?>"><title><?=h($title ? $title.' | '.($s['site_title'] ?? SITE_NAME) : ($s['site_title'] ?? SITE_NAME).' — بازار قلق‌های تعمیراتی')?></title><script>
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
    if('serviceWorker' in navigator){navigator.serviceWorker.register('/sw.js?v=6',{updateViaCache:'none'}).then(function(reg){if(reg&&reg.update)reg.update();}).catch(function(){})}

// Push notification subscription
if('Notification' in window && navigator.serviceWorker){
  setTimeout(function(){
    if(Notification.permission==='granted'){
      navigator.serviceWorker.ready.then(function(reg){
        reg.pushManager.getSubscription().then(function(sub){
          if(!sub){
            reg.pushManager.subscribe({userVisibilityState:'visible',applicationServerKey:''}).catch(function(){});
          }
        });
      });
    }
  },5000);
}
  }catch(e){}
})();
</script>
<link rel="stylesheet" href="<?=url('assets/style.css')?>?v=13"><?php if(!empty($s['google_analytics'])):?><script async src="https://www.googletagmanager.com/gtag/js?id=<?=h($s['google_analytics'])?>"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','<?=h($s['google_analytics'])?>');</script><?php endif;?><script>
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
(function(){var bell=null,drop=null,badge=null,list=null;
function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;}
/* v5.5: فرم‌های AJAX — ارسال بدون رفرش با بازخورد درجا */
/* v5.10: لایت‌باکس زوم/تمام‌صفحه برای تصاویر قلق */
if(!window.__bkLightbox){window.__bkLightbox=true;(function(){
  var box=null,img=null,grid=null,items=[],idx=0,s=1,tx=0,ty=0,pinch=null,panPt=null,lastTap=0;
  var clampS=function(v){return Math.max(1,Math.min(6,v));};
  function apply(){if(!img)return;img.style.transform='translate('+tx+'px,'+ty+'px) scale('+s+')';img.style.cursor=s>1?'grab':'zoom-in';}
  function setIdx(i){idx=(i+items.length)%items.length;s=1;tx=0;ty=0;img.src=items[idx].src;img.alt='تصویر '+(idx+1);grid.innerHTML=items[idx].grid;var cnt=document.querySelector('#bkLightbox .lb-count');if(cnt)cnt.textContent=(idx+1)+' / '+items.length;apply();}
  function buildGrid(txt){var pos=[[6,8],[38,4],[70,10],[16,42],[50,46],[82,40],[8,78],[42,80],[74,76]];var h='';for(var k=0;k<pos.length;k++){h+='<i style="right:'+pos[k][0]+'%;top:'+pos[k][1]+'%;animation-delay:'+(k*0.7)+'s">'+txt+'</i>';}return h;}
  function open(list,start){
    close(true);
    items=list;box=document.createElement('div');box.id='bkLightbox';box.className='bk-lightbox';
    box.innerHTML='<div class="lb-stage"><img alt=""><span class="lb-grid"></span></div>'+
    '<div class="lb-toolbar"><button type="button" data-a="zo" title="بزرگ‌نمایی">＋</button><button type="button" data-a="zi" title="کوچک‌نمایی">－</button><button type="button" data-a="rs" title="اندازه اصلی">↺</button><button type="button" data-a="fs" title="تمام صفحه">⛶</button><button type="button" data-a="x" title="بستن">✕</button></div>'+
    (items.length>1?'<button type="button" class="lb-prev" title="قبلی">‹</button><button type="button" class="lb-next" title="بعدی">›</button>':'')+
    '<div class="lb-count"></div>';
    document.body.appendChild(box);document.body.classList.add('noscroll');
    img=box.querySelector('img');grid=box.querySelector('.lb-grid');
    setIdx(start||0);
    box.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('button');if(!b)return;var a=b.getAttribute('data-a');
      if(a==='x'){close();}else if(a==='zo'){s=clampS(s*1.3);apply();}
      else if(a==='zi'){s=clampS(s/1.3);if(s===1){tx=0;ty=0;}apply();}
      else if(a==='rs'){s=1;tx=0;ty=0;apply();}
      else if(a==='fs'){if(document.fullscreenElement){document.exitFullscreen();}else if(box.requestFullscreen){box.requestFullscreen();}}
      else if(a==='p'){setIdx(idx-1);}else if(a==='n'){setIdx(idx+1);}
      e.stopPropagation();});
    box.addEventListener('click',function(e){if(e.target===box||e.target.classList.contains('lb-stage'))close();});
    box.addEventListener('wheel',function(e){e.preventDefault();s=clampS(s*(e.deltaY<0?1.15:0.87));if(s===1){tx=0;ty=0;}apply();},{passive:false});
    img.addEventListener('dblclick',function(e){e.preventDefault();if(s>1){s=1;tx=0;ty=0;}else{s=2.5;}apply();});
    img.addEventListener('pointerdown',function(e){if(s<=1)return;panPt={x:e.clientX,y:e.clientY,tx:tx,ty:ty};img.setPointerCapture&&img.setPointerCapture(e.pointerId);});
    img.addEventListener('pointermove',function(e){if(!panPt)return;tx=panPt.tx+(e.clientX-panPt.x);ty=panPt.ty+(e.clientY-panPt.y);apply();});
    img.addEventListener('pointerup',function(){panPt=null;});
    /* دابل‌تپ موبایل */
    img.addEventListener('touchend',function(e){var now=Date.now();if(now-lastTap<300){if(s>1){s=1;tx=0;ty=0;}else{s=2.5;}apply();}lastTap=now;});
    /* پین‌چ */
    box.addEventListener('touchstart',function(e){if(e.touches.length===2){var dx=e.touches[0].clientX-e.touches[1].clientX,dy=e.touches[0].clientY-e.touches[1].clientY;pinch={d:Math.sqrt(dx*dx+dy*dy),s:s};}},{passive:true});
    box.addEventListener('touchmove',function(e){if(e.touches.length===2&&pinch){e.preventDefault();var dx=e.touches[0].clientX-e.touches[1].clientX,dy=e.touches[0].clientY-e.touches[1].clientY;var d=Math.sqrt(dx*dx+dy*dy);s=clampS(pinch.s*d/pinch.d);if(s===1){tx=0;ty=0;}apply();}},{passive:false});
    box.addEventListener('touchend',function(){pinch=null;});
    document.addEventListener('keydown',kb);
  }
  function kb(e){if(!box)return;if(e.key==='Escape'){close();}else if(e.key==='ArrowLeft'){setIdx(idx+1);}else if(e.key==='ArrowRight'){setIdx(idx-1);}else if(e.key==='+'||e.key==='='){s=clampS(s*1.25);apply();}else if(e.key==='-'){s=clampS(s/1.25);apply();}}
  function close(silent){if(!box)return;var b=box;box=null;if(document.fullscreenElement){try{document.exitFullscreen();}catch(_){}}document.removeEventListener('keydown',kb);document.body.classList.remove('noscroll');b.remove();}
  window.bkLightboxOpen=open;
  /* کلیک روی عکس‌های گالری قلق → لایت‌باکس */
  document.addEventListener('click',function(e){
    var w=e.target.closest&&e.target.closest('.bk-zoomable');
    if(!w)return;
    if(e.target.closest&&e.target.closest('button'))return;
    var list=[],startI=0;
    var nodes=document.querySelectorAll('.bk-zoomable'); /* v5.11: گالری + عکس مراحل */
    for(var i=0;i<nodes.length;i++){var im=nodes[i].querySelector('img');if(!im)continue;var wmTxt=(nodes[i].getAttribute('data-wm')||'© بردخان')+' · '+(nodes[i].getAttribute('data-uid')||'');list.push({src:im.src,grid:buildGrid(wmTxt)});if(nodes[i]===w)startI=i;}
    if(list.length)open(list,startI);
  });
})();
/* v5.10: دکمه‌های تمام‌صفحه/زوم ویدیو — واترمارک در تمام‌صفحه حفظ می‌شود */
(function(){
  document.addEventListener('click',function(e){
    var b=e.target.closest&&e.target.closest('.vid-tools button');if(!b)return;
    var wrap=b.closest('.media-protect');if(!wrap)return;var v=wrap.querySelector('video');if(!v)return;
    var a=b.getAttribute('data-va');var cur=parseFloat(v.getAttribute('data-zoom')||'1');
    if(a==='fs'){if(document.fullscreenElement){document.exitFullscreen();}else if(wrap.requestFullscreen){wrap.requestFullscreen();}else if(v.webkitEnterFullscreen){v.webkitEnterFullscreen();}}
    else if(a==='zo'){cur=Math.min(2.5,cur+0.25);}
    else if(a==='zi'){cur=Math.max(1,cur-0.25);}
    else if(a==='rz'){cur=1;}
    v.setAttribute('data-zoom',String(cur));v.style.transform='scale('+cur+')';
  });
})();}
/* v5.9: ضد دانلود رسانه — کلیک‌راست/درگ/انتخاب روی عکس و ویدیو در کل سایت */
if(!window.__bkMediaGuard){window.__bkMediaGuard=true;(function(){
  function isMedia(t){return t&&(t.tagName==='IMG'||t.tagName==='VIDEO'||(t.closest&&t.closest('.media-protect')));}
  document.addEventListener('contextmenu',function(e){if(isMedia(e.target))e.preventDefault();});
  document.addEventListener('dragstart',function(e){if(isMedia(e.target))e.preventDefault();});
  document.addEventListener('selectstart',function(e){if(isMedia(e.target))e.preventDefault();});
  /* Ctrl+S / Ctrl+P روی صفحات قلق */
  document.addEventListener('keydown',function(e){if((e.ctrlKey||e.metaKey)&&(e.key==='s'||e.key==='p')){if(document.querySelector('.media-protect')){e.preventDefault();}}});
})();}
window.bkPassStrength=function(v){var bar=document.querySelector('#bkPassBar i'),txt=document.querySelector('#bkPassTxt');if(!bar||!txt)return;var s=0;if(v.length>=6)s++;if(v.length>=10)s++;if(/[0-9]/.test(v)&&/[a-zA-Z]/.test(v))s++;if(/[^a-zA-Z0-9]/.test(v))s++;var pct=[8,35,65,85,100][s],col=['#ef4444','#f59e0b','#eab308','#10b981','#10b981'][s],lbl=['خیلی ضعیف','ضعیف','متوسط','خوب','عالی'][s];bar.style.width=pct+'%';bar.style.background=col;txt.textContent=v?'قدرت رمز: '+lbl:'';}
if(!window.__bkAjaxForms){window.__bkAjaxForms=true;(function(){
  function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;}
  document.addEventListener('submit',function(e){
    var f=e.target;if(!f||!f.classList||!f.classList.contains('bk-ajax'))return;
    e.preventDefault();
    var msg=f.querySelector('.bk-ajax-msg');
    var btns=f.querySelectorAll('button[type=submit],button:not([type])');
    var btn=btns.length?btns[btns.length-1]:null;
    var orig=btn?btn.innerHTML:'';
    if(btn){btn.disabled=true;btn.dataset.bkOrig=orig;btn.innerHTML='⏳ لطفاً صبر کنید…';}
    fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},body:new FormData(f)})
      .then(function(r){return r.json().catch(function(){throw new Error('پاسخ سرور قابل خواندن نبود');});})
      .then(function(j){
        if(j&&j.ok){
          if(j.url){if(msg)msg.innerHTML='<div class="notice">✅ '+esc(j.message||'در حال انتقال…')+'</div>';window.location.href=j.url;return;}
          if(j.reload){window.location.reload();return;}
          if(msg)msg.innerHTML='<div class="notice">✅ '+esc(j.message||'انجام شد')+'</div>';
          if(btn){btn.disabled=false;btn.innerHTML=orig;}
        }else{
          if(j&&j.need_verify&&j.url){window.location.href=j.url;return;}
          if(msg)msg.innerHTML='<div class="notice error">⚠️ '+esc((j&&j.error)||'خطای نامشخص')+'</div>';
          if(btn){btn.disabled=false;btn.innerHTML=orig;}
        }
      })
      .catch(function(err){
        if(msg)msg.innerHTML='<div class="notice error">⚠️ '+esc(err.message||err)+'</div>';
        if(btn){btn.disabled=false;btn.innerHTML=orig;}
      });
  });
})();}
function nico(t){return {'admin':'⚙️','system':'📢','ticket':'🎫','wallet':'👛','board':'🏪','sale':'💰','like':'❤️','follow':'👥','badge':'🏅','repair':'🛠'}[t]||'🔔';}
function render(j){if(!j||typeof j.unread==='undefined')return;if(j.unread>0){badge.hidden=false;badge.textContent=j.unread>99?'99+':j.unread;}else{badge.hidden=true;}if(j.items&&j.items.length){var h='';j.items.forEach(function(n){h+='<a class="notif-item'+(n.unread?' unread':'')+'" href="'+esc(n.link||'/notifications')+'"><strong><span class="nic">'+nico(n.type)+'</span>'+esc(n.title)+'</strong><small>'+esc(n.body)+' · '+esc(n.ago)+'</small></a>';});list.innerHTML=h;}else{list.innerHTML='<div class="notif-empty">اعلان جدیدی ندارید ✓</div>';}}
function load(){fetch('/ajax-notifications',{headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}}).then(function(r){return r.json().catch(function(){return null;});}).then(render).catch(function(){});}
function markRead(){fetch('/ajax-notifications?mark=1',{headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}}).then(function(r){return r.json().catch(function(){return null;});}).then(function(j){if(j&&j.ok){badge.hidden=true;var a=list.querySelectorAll?list.querySelectorAll('.notif-item'):[];for(var i=0;i<a.length;i++){a[i].classList.remove('unread');}}}).catch(function(){});}
function initBell(){bell=document.getElementById('notifBell');drop=document.getElementById('notifDrop');badge=document.getElementById('notifBadge');list=document.getElementById('notifList');if(!bell||!drop||!badge||!list)return;bell.addEventListener('click',function(e){e.stopPropagation();drop.hidden=!drop.hidden;if(!drop.hidden){markRead();load();}});document.addEventListener('click',function(e){if(drop&&!drop.hidden&&!e.target.closest('#notifWrap'))drop.hidden=true;});load();setInterval(load,30000);}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',initBell);}else{initBell();}})();
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
                <button class="theme-btn" type="button" id="themeToggle" aria-label="تغییر تم">🌙</button>
                <?php if($u):?>
                    <a class="hide-mobile" href="<?=url('profile/'.$u['id'])?>" style="display:inline-flex;align-items:center;gap:6px">👤 <?=h($u['name'])?></a>
                <?php else:?>
                    <a class="btn btn-secondary btn-sm hide-mobile" href="<?=url('login')?>">ورود</a>
                    <a class="btn btn-primary btn-sm hide-mobile" href="<?=url('register')?>">ثبت‌نام</a>
                <?php endif;?>
            </div>
            <?php if($u):?><?php $bkUnread=0;$bkNotifs=[];try{$__bkQ1=db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');$__bkQ1->execute([(int)$u['id']]);$bkUnread=(int)$__bkQ1->fetchColumn();$__bkQ2=db()->prepare('SELECT id,type,title,body,link,is_read,created_at FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 8');$__bkQ2->execute([(int)$u['id']]);$bkNotifs=$__bkQ2->fetchAll();}catch(Throwable $e){}?>
            <div class="notif-wrap" id="notifWrap"><button class="notif-bell" type="button" id="notifBell" aria-label="اعلان‌ها" title="اعلان‌ها">🔔<?php if($bkUnread>0):?><span class="notif-badge" id="notifBadge"><?=fa($bkUnread>99?99:$bkUnread)?></span><?php else:?><span class="notif-badge" id="notifBadge" hidden>0</span><?php endif;?></button><div class="notif-drop" id="notifDrop" hidden><div class="notif-head">🔔 اعلان‌های اخیر</div><div id="notifList" class="notif-list"><?php if($bkNotifs):?><?php foreach($bkNotifs as $bn):?><a class="notif-item<?=($bn['is_read']??1)?'':' unread'?>" href="<?=h($bn['link']?:url('notifications'))?>"><strong><span class="nic"><?=bk_notif_icon((string)$bn['type'])?></span><?=h($bn['title'])?></strong><small><?=h(mb_substr((string)($bn['body']??''),0,90))?> · <?=ago($bn['created_at'])?></small></a><?php endforeach;?><?php else:?><div class="notif-empty">اعلان جدیدی ندارید ✓</div><?php endif;?></div><a class="notif-all" href="<?=url('notifications')?>">مشاهده همه اعلان‌ها ←</a></div></div><?php endif;?>
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
                        <?php if(admin_user($u)):?><a href="<?=url('admin')?>">⚙️ پنل مدیریت</a><?php endif;?>
                <a href="<?=url('logout')?>">🚪 خروج</a>
            <?php else:?>
                <a href="<?=url('login')?>">🔑 ورود</a>
                <a href="<?=url('register')?>">✨ ثبت‌نام رایگان</a>
            <?php endif;?>
        </div>
        <div class="install-banner" id="installBanner"><span id="installText">📱 بردخان را روی صفحه اصلی نصب کنید</span> <button class="btn btn-primary btn-sm" id="installBtn" type="button">نصب</button><button class="btn btn-secondary btn-sm" type="button" onclick="document.getElementById('installBanner').classList.remove('show')">✕</button></div><?php $f=pull_flash(); if($f):?><div class="wrap"><div class="<?=h($f[1])?>"><?=h($f[0])?></div></div><?php endif; ?><?php }
function footer_html(): void { ?><footer class="footer"><div class="wrap footer-grid"><div><div class="logo" style="color:#fff">⌁ برد<em>خان</em></div><p>بازار تخصصی قلق‌های تعمیراتی بردهای الکترونیکی؛ راه‌حل‌های واقعی از تعمیرکاران حرفه‌ای.</p></div><div><h3>دسترسی سریع</h3><ul><li><a href="<?=url('tips')?>">همه قلق‌ها</a></li><li><a href="<?=url('reels')?>">ریلز قلق‌ها</a></li><li><a href="<?=url('repairs')?>">درخواست تعمیر</a></li><li><a href="<?=url('leaderboard')?>">رتبه‌بندی</a></li><li><a href="<?=url('premium')?>">اشتراک ویژه</a></li><li><a href="<?=url('tour')?>">آموزش و امکانات</a></li></ul></div><div><h3>پشتیبانی</h3><ul><li><a href="<?=url('tickets')?>">تیکت پشتیبانی</a></li><li><a href="<?=url('contact')?>">تماس با ما</a></li><li><a href="<?=url('about')?>">درباره ما</a></li><li><a href="<?=url('terms')?>">قوانین استفاده</a></li><li><a href="<?=url('privacy')?>">حریم خصوصی</a></li></ul></div></div><?php $bImg=trim((string)($s['trust_badge_image']??'')); $bLink=trim((string)($s['trust_badge_link']??'')); if($bImg!==''): ?>
<div class="wrap" style="margin-bottom:14px;display:flex;gap:12px;align-items:center;justify-content:center;flex-wrap:wrap">
  <a href="<?=h($bLink?:'#')?>" <?= $bLink?'target="_blank" rel="noopener nofollow"':'' ?>><img src="<?=h($bImg)?>" alt="نماد اعتماد" loading="lazy" style="height:72px;border-radius:10px;background:#fff;padding:6px"></a>
  <div style="font-size:11px;line-height:2;color:#c9d4de">پرداخت امن از طریق درگاه‌های معتبر ایرانی<br><a class="check" href="<?=url('payment-status')?>">وضعیت پرداخت سایت</a></div>
</div>
<?php endif; ?>
<div class="wrap copyright">© <?=fa(date('Y'))?> بردخان — تمامی حقوق محفوظ است. <span style="opacity:.55;font-size:10px">· نسخه <?=defined('BORDKHAN_VERSION')?BORDKHAN_VERSION:'قدیمی'?></span></div></footer><?php if(is_file(__DIR__.'/php-extended/bk_actionbar.php')){require_once __DIR__.'/php-extended/bk_actionbar.php';bk_render_actionbar(function_exists('current_user')?current_user():null);} ?><script>
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
    if($action==='login'){ $ajax=is_ajax_request();$id=strtolower(trim($_POST['identifier']??''));$pass=(string)($_POST['password']??'');$fail=function(string $m)use($ajax){if($ajax)bk_json_out(['ok'=>false,'error'=>$m],422);flash($m,'error');redirect_to('login');};if(!throttle('login:'.md5($id),5,600))$fail('تلاش‌های ناموفق زیاد است؛ ۱۰ دقیقه بعد دوباره امتحان کنید.');$s=$pdo->prepare('SELECT * FROM users WHERE phone=? OR email=? LIMIT 1');$s->execute([$id,$id]);$u=$s->fetch();if($u && !empty($u['locked_until'] ?? null) && strtotime((string)$u['locked_until']) > time()){
        $mins = (int)ceil((strtotime((string)$u['locked_until']) - time()) / 60);
        sec_log((int)$u['id'], 'login_blocked', 'تلاش ورود در حالت قفل');
        $fail('حساب شما به دلیل ۵ تلاش ناموفق موقتاً قفل شده است؛ حدود ' . fa($mins) . ' دقیقه دیگر دوباره امتحان کنید یا از «فراموشی رمز» استفاده کنید.');
    }
    if(!$u || !empty($u['is_deleted']) || !password_verify($pass, $u['password_hash'])){
        if($u && !empty($u['is_deleted'])) $u = null;
        if($u){
            try{
                $fl = (int)($u['failed_logins'] ?? 0) + 1;
                if($fl >= 5){
                    $pdo->prepare('UPDATE users SET failed_logins=0, locked_until=DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id=?')->execute([(int)$u['id']]);
                    if(!empty($u['email'])) bk_send_mail((string)$u['email'], 'هشدار امنیتی — قفل موقت حساب', '<h2 style="font-size:14px;margin:0 0 8px">حساب شما موقتاً قفل شد</h2><p style="font-size:13px">پنج تلاش ناموفق برای ورود به حساب شما انجام شده است. به مدت ۱۵ دقیقه ورود قفل است. اگر این تلاش‌ها از شما نبوده، بلافاصله رمز عبور را تغییر دهید.</p>');
                    sec_log((int)$u['id'], 'account_locked', '۵ ورود ناموفق — قفل ۱۵ دقیقه + ایمیل اطلاع‌رسانی');
                } else {
                    $pdo->prepare('UPDATE users SET failed_logins=? WHERE id=?')->execute([$fl, (int)$u['id']]);
                }
            }catch(Throwable $e){}
            sec_log((int)$u['id'], 'login_failed', 'رمز عبور اشتباه (' . ($fl ?? 1) . '/5)');
        } else { sec_log(0, 'login_failed', 'کاربر یافت نشد: ' . mb_substr($id, 0, 60)); }
        $fail('ایمیل/موبایل یا رمز عبور اشتباه است.');
    }if($u['is_banned'])$fail('حساب شما مسدود شده است.');throttle_clear('login:'.md5($id));session_regenerate_id(true);$_SESSION['user_id']=$u['id'];bk_session_register((int)$u['id']);try{$pdo->prepare('UPDATE users SET failed_logins=0, locked_until=NULL WHERE id=?')->execute([(int)$u['id']]);}catch(Throwable $e){}sec_log((int)$u['id'],'login_success','ورود موفق');$pdo->prepare('UPDATE users SET last_login=NOW() WHERE id=?')->execute([$u['id']]);if($ajax)bk_json_out(['ok'=>true,'url'=>url(''),'message'=>'خوش آمدید! در حال ورود…']);redirect_to('');}
    if($action==='register'){ $ajax=is_ajax_request();$name=clean_text($_POST['name']??'');$email=strtolower(trim($_POST['email']??''));$phone=preg_replace('/[^0-9]/','',$_POST['phone']??'');$pass=(string)($_POST['password']??'');$ref=trim($_POST['ref']??'');$fail=function(string $m)use($ajax){if($ajax)bk_json_out(['ok'=>false,'error'=>$m],422);flash($m,'error');redirect_to('register');};
    /* v5.5: ثبت‌نام با ایمیل — موبایل اختیاری */
    if(!throttle('register:'.md5($email),5,3600))$fail('تعداد ثبت‌نام زیاد است؛ کمی بعد دوباره تلاش کنید.');
    if(mb_strlen($name)<3||!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($pass)<6)$fail('نام، ایمیل معتبر و رمز عبور حداقل ۶ کاراکتر لازم است.');
    if($phone!==''&&!preg_match('/^09[0-9]{9}$/',$phone))$fail('شماره موبایل معتبر نیست (مثال: 09123456789) — یا خالی بگذارید.');
    $e=$pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');$e->execute([$email]);if($e->fetch())$fail('این ایمیل قبلاً ثبت شده است؛ وارد شوید یا ایمیل دیگری بزنید.');
    if($phone!==''){$p=$pdo->prepare('SELECT id FROM users WHERE phone=? LIMIT 1');$p->execute([$phone]);if($p->fetch())$fail('این شماره موبایل قبلاً ثبت شده است.');}
    $referrer=null;if($ref){$q=$pdo->prepare('SELECT id FROM users WHERE referral_code=?');$q->execute([$ref]);$referrer=$q->fetchColumn()?:null;}
    $send=bk_send_email_code($email,'register');if(!$send['ok'])$fail($send['error']);
    $_SESSION['pending_register']=['name'=>$name,'email'=>$email,'phone'=>$phone?:null,'hash'=>password_hash($pass,PASSWORD_DEFAULT),'referred_by'=>$referrer];
    if($ajax)bk_json_out(['ok'=>true,'url'=>url('verify'),'message'=>'کد تأیید به ایمیل شما ارسال شد.']);redirect_to('verify');}
    if($action==='verify'){ $ajax=is_ajax_request();$p=$_SESSION['pending_register']??null;$fail=function(string $m,string $to='verify')use($ajax){if($ajax)bk_json_out(['ok'=>false,'error'=>$m],422);flash($m,'error');redirect_to($to);};
    if(!$p)$fail('ابتدا فرم ثبت‌نام را تکمیل کنید.','register');
    if(!throttle('verify',8,900))$fail('تلاش‌های زیاد؛ ۱۵ دقیقه بعد دوباره امتحان کنید.');
    /* v5.5: کد از ایمیل خوانده می‌شود (نه از نشست) */
    if(!bk_check_email_code($p['email'],(string)($_POST['code']??''),'register'))$fail('کد تأیید نادرست یا منقضی است — دوباره تلاش کنید یا کد جدید بگیرید.');
    throttle_clear('verify');
    $code='USR'.strtoupper(bin2hex(random_bytes(3)));
    $phone=$p['phone'];
    $ins=function()use($pdo,$p,$code){$pdo->prepare('INSERT INTO users(phone,email,password_hash,name,referral_code,referred_by,phone_verified,email_verified) VALUES(?,?,?,?,?,?,1,1)')->execute([$p['phone'] ?: 'u-'.bin2hex(random_bytes(5)),$p['email'],$p['hash'],$p['name'],$code,$p['referred_by']]);};
    try{ $ins(); }catch(Throwable $e){ /* ستون email_verified قدیمی نیست؟ بساز و دوباره */ try{ $pdo->exec('ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0'); }catch(Throwable $e2){} try{ $ins(); }catch(Throwable $e3){ $fail('ساخت حساب ناموفق بود: '.$e3->getMessage(),'register'); } }
    $uid=(int)$pdo->lastInsertId();
    if($p['referred_by'])credit($uid,(int)settings()['invitee_credit'],'referral_invitee','اعتبار خوش‌آمدگویی ثبت‌نام');
    session_regenerate_id(true);$_SESSION['user_id']=$uid;unset($_SESSION['pending_register']);
    if($ajax)bk_json_out(['ok'=>true,'url'=>url(''),'message'=>'حساب شما ساخته شد — خوش آمدید!']);redirect_to('');}
    if($action==='logout'){ bk_session_drop(); $_SESSION=[]; if (ini_get('session.use_cookies')) { $p = session_get_cookie_params(); @setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']); } session_destroy(); @header('Clear-Site-Data: "cache"'); redirect_to('?loggedout=' . time()); }

    // بازیابی رمز عبور — مرحله ۱: درخواست کد
    if($action==='forgot_request'){
        /* v5.6: بازیابی رمز با ایمیل + پیام یکسان برای همه (جلوگیری از کشف ایمیل‌های ثبت‌شده) */
        $ajax=is_ajax_request();
        $email=strtolower(trim($_POST['email'] ?? ''));
        $fail=function(string $m)use($ajax){if($ajax)bk_json_out(['ok'=>false,'error'=>$m],429);flash($m,'error');redirect_to('forgot');};
        $okMsg=function()use($ajax){if($ajax)bk_json_out(['ok'=>true,'url'=>url('forgot'),'message'=>'اگر این ایمیل در بردخان ثبت شده باشد، کد بازیابی ارسال شد.']);flash('اگر این ایمیل در بردخان ثبت شده باشد، کد بازیابی به آن ارسال شد.');redirect_to('forgot');};
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))$fail('ایمیل معتبر وارد کنید.');
        if(!throttle('forgot:'.md5($email),5,900))$fail('درخواست‌های زیاد است؛ ۱۵ دقیقه بعد دوباره امتحان کنید.');
        $q=$pdo->prepare('SELECT id,email FROM users WHERE email=? LIMIT 1');$q->execute([$email]);$usr=$q->fetch();
        if(!$usr){throttle_clear('forgot:'.md5($email));$okMsg();} /* پاسخ یکسان — کاربر وجود ندارد */
        $send=bk_send_email_code($email,'reset');
        if(!$send['ok'])$fail($send['error']);
        throttle_clear('forgot:'.md5($email));
        $_SESSION['pending_reset']=['user_id'=>(int)$usr['id'],'email'=>$email];
        $okMsg();
    }
    // بازیابی رمز عبور — مرحله ۲: تغییر رمز
    if($action==='forgot_reset'){
        /* v5.6: کد از ایمیل خوانده می‌شود (نه از نشست) */
        $ajax=is_ajax_request();
        $p=$_SESSION['pending_reset']??null;
        $code=preg_replace('/[^0-9]/','',$_POST['code']??'');
        $pass=(string)($_POST['password']??'');
        $fail=function(string $m)use($ajax){if($ajax)bk_json_out(['ok'=>false,'error'=>$m],422);flash($m,'error');redirect_to('forgot');};
        if(!throttle('forgot_reset',8,900))$fail('تلاش‌های ناموفق زیاد است؛ ۱۵ دقیقه بعد دوباره امتحان کنید.');
        if(!$p)$fail('ابتدا مرحلهٔ اول را انجام دهید.');
        if(!bk_check_email_code($p['email'],$code,'reset'))$fail('کد بازیابی نادرست یا منقضی است.');
        if(mb_strlen($pass)<6)$fail('رمز عبور باید حداقل ۶ کاراکتر باشد.');
        $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($pass,PASSWORD_DEFAULT),(int)$p['user_id']]);
        try{ $pdo->prepare('DELETE FROM user_sessions WHERE user_id=?')->execute([(int)$p['user_id']]); }catch(Throwable $e){} /* تغییر رمز = خروج همه */
        sec_log((int)$p['user_id'],'password_reset','تغییر رمز از مسیر بازیابی + خروج همهٔ نشست‌ها');
        unset($_SESSION['pending_reset']);
        session_regenerate_id(true); /* نشست نو بعد از تغییر رمز */
        if($ajax)bk_json_out(['ok'=>true,'url'=>url('login'),'message'=>'رمز عبور با موفقیت تغییر کرد — وارد شوید.']);
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
        $u=require_login();bk_require_email_verified($u,url('upload'));
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
        $u=require_login();bk_require_email_verified($u,url('upload'));
        $ajax=is_ajax_request();
        $bk_fail=function(string $m)use($ajax){
            if($ajax){bk_json_out(['ok'=>false,'error'=>$m],422);}
            flash($m,'error');redirect_to('upload');
        };
        $title=clean_text($_POST['title']??'');$short=clean_text($_POST['short_description']??'');$desc=trim($_POST['description']??'');$device=clean_text($_POST['device_name']??'');$brand=clean_text($_POST['brand']??'');$cat=(int)($_POST['category_id']??0);$access=in_array($_POST['access_type']??'free',['free','like','paid'],true)?$_POST['access_type']:'free';$price=$access==='paid'?max(1000,(int)($_POST['price']??0)):0;
        $images=[];$imgFails=[];foreach(($_FILES['images']['tmp_name']??[]) as $i=>$tmp){$f=['tmp_name'=>$tmp,'error'=>$_FILES['images']['error'][$i]??1,'size'=>$_FILES['images']['size'][$i]??0,'type'=>$_FILES['images']['type'][$i]??'','name'=>$_FILES['images']['name'][$i]??''];$saved=save_image($f);if($saved)$images[]=$saved;else $imgFails[]=($f['name']?:('عکس '.($i+1))).': '.bk_upload_error_reason($f);}
        if(mb_strlen($title)<8)$bk_fail('عنوان قلق باید حداقل ۸ حرف باشد.');
        if(mb_strlen($short)<20)$bk_fail('توضیح کوتاه باید حداقل ۲۰ حرف باشد.');
        if(!$device)$bk_fail('نام دستگاه الزامی است.');
        if(!$brand)$bk_fail('برند الزامی است.');
        if(!$cat)$bk_fail('دسته‌بندی الزامی است.');
        if(mb_strlen(strip_tags($desc))<20)$bk_fail('توضیح کامل باید حداقل ۲۰ حرف باشد.');
        if(count($images)<1){$bk_fail('هیچ عکسی ذخیره نشد! علت: '.($imgFails?implode(' | ',array_slice($imgFails,0,2)):'نامشخص — مجوز پوشه uploads را بررسی کنید'));}
        $steps=[];foreach(($_POST['step_body']??[]) as $i=>$body){if(trim($body))$steps[]=['title'=>clean_text($_POST['step_title'][$i]??''),'body'=>trim($body)];}
        /* v5.11: عکس‌های مراحل — به‌ترتیب به گام‌ها وصل می‌شوند؛ اضافه‌ها به گالری اصلی */
        $stepImgs=[];foreach(($_FILES['step_images']['tmp_name']??[]) as $si=>$stmp){$sf=['tmp_name'=>$stmp,'error'=>$_FILES['step_images']['error'][$si]??1,'size'=>$_FILES['step_images']['size'][$si]??0,'type'=>$_FILES['step_images']['type'][$si]??'','name'=>$_FILES['step_images']['name'][$si]??''];if($sv=save_image($sf))$stepImgs[]=$sv;}
        if($stepImgs){
            foreach($steps as $ki=>$ks){if(isset($stepImgs[$ki]))$steps[$ki]['img']=$stepImgs[$ki];}
            if(count($stepImgs)>count($steps)){foreach(array_slice($stepImgs,count($steps)) as $extra){$steps[]=['title'=>'تصاویر تکمیلی تعمیر','body'=>'تصاویر بیشتر از مراحل تعمیر.','img'=>$extra];}}
        }
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
    if($action==='unlock'){ $u=require_login();$ajax=is_ajax_request();bk_require_email_verified($u,url('tip/'.((int)($_POST['tip_id']??0))),$ajax);$tipId=(int)$_POST['tip_id'];$q=$pdo->prepare('SELECT * FROM tips WHERE id=? AND status="published"');$q->execute([$tipId]);$t=$q->fetch();if(!$t){if($ajax)bk_json_out(['ok'=>false,'error'=>'قلق یافت نشد.'],404);exit('قلق یافت نشد');}if((int)$t['author_id']===(int)$u['id']){if($ajax)bk_json_out(['ok'=>false,'error'=>'نمی‌توانید قلق خودتان را باز کنید.']);flash('نمی‌توانید قلق خودتان را باز کنید.','error');redirect_to('tip/'.$tipId);}$q=$pdo->prepare('SELECT id FROM tip_accesses WHERE tip_id=? AND user_id=?');$q->execute([$tipId,$u['id']]);if($q->fetch()){if($ajax)bk_json_out(['ok'=>true,'already'=>true]);redirect_to('tip/'.$tipId);}$access=$t['access_type'];if($access==='paid'){if(!debit($u['id'],(int)$t['price'],'purchase','خرید قلق «'.$t['title'].'»',$tipId)){if($ajax)bk_json_out(['ok'=>false,'error'=>'موجودی کیف پول کافی نیست.','wallet'=>url('wallet')],402);flash('موجودی کیف پول کافی نیست.','error');redirect_to('wallet');} $net=(int)floor($t['price']*(100-(int)settings()['commission_percent'])/100);credit((int)$t['author_id'],$net,'sale','درآمد فروش قلق «'.$t['title'].'»',$tipId);maybe_reward_referrer($u['id']);$type='purchase';}elseif($access==='like'){$today=date('Y-m-d');$used=$u['likes_used_date']===$today?(int)$u['likes_used_today']:0;if($used>=(int)settings()['daily_like_limit']){if($ajax)bk_json_out(['ok'=>false,'error'=>'سقف لایک روزانه شما تمام شده است.']);flash('سقف لایک روزانه شما تمام شده است.','error');redirect_to('tip/'.$tipId);} $pdo->prepare('UPDATE users SET likes_used_today=?,likes_used_date=? WHERE id=?')->execute([$used+1,$today,$u['id']]);$pdo->prepare('UPDATE tips SET likes_count=likes_count+1 WHERE id=?')->execute([$tipId]);award((int)$t['author_id'],(int)(settings()['like_points_reward']??5));$type='like';}else{$type='free';}$pdo->prepare('INSERT INTO tip_accesses(tip_id,user_id,access_type,price_paid,ip) VALUES(?,?,?,?,?)')->execute([$tipId,$u['id'],$type,$t['price']??0,$_SERVER['REMOTE_ADDR']??'']);if($access==='paid'){award_badge((int)$u['id'],'first_purchase');award_badge((int)$t['author_id'],'first_sale');}notify_user((int)$t['author_id'],$access==='paid'?'sale':'like',$access==='paid'?'قلق شما فروخته شد':'قلق شما لایک گرفت',$u['name'].' قلق «'.$t['title'].'» را باز کرد.',url('tip/'.$tipId));if($ajax)bk_json_out(['ok'=>true,'message'=>$access==='paid'?'خرید انجام شد؛ محتوای کامل باز شد.':'لایک ثبت شد؛ محتوای کامل باز شد.','type'=>$access]);redirect_to('tip/'.$tipId);}
if($action==='comment'){ $u=require_login();$tipId=(int)$_POST['tip_id'];$body=trim($_POST['body']??'');$ajax=is_ajax_request();if(mb_strlen($body)<2){if($ajax)bk_json_out(['ok'=>false,'error'=>'متن نظر کوتاه است.'],422);flash('متن نظر کوتاه است.','error');redirect_to('tip/'.$tipId);} $pdo->prepare('INSERT INTO comments(tip_id,user_id,parent_id,body) VALUES(?,?,?,?)')->execute([$tipId,$u['id'],($_POST['parent_id']??null)?:null,$body]);$tq=$pdo->prepare('SELECT author_id,title FROM tips WHERE id=?');$tq->execute([$tipId]);$trow=$tq->fetch();if($trow&&(int)$trow['author_id']!==(int)$u['id']){notify_user((int)$trow['author_id'],'like','نظر جدید روی قلق شما',$u['name'].' روی «'.mb_substr($trow['title'],0,40).'» نظر گذاشت.',url('tip/'.$tipId.'#comments'));}if($ajax)bk_json_out(['ok'=>true,'message'=>'نظر شما ثبت شد.']);flash('نظر شما ثبت شد.');redirect_to('tip/'.$tipId.'#comments');}if($action==='rate'){ $u=require_login();$tipId=(int)$_POST['tip_id'];$stars=max(1,min(5,(int)$_POST['stars']));$q=$pdo->prepare('SELECT * FROM ratings WHERE tip_id=? AND user_id=?');$q->execute([$tipId,$u['id']]);$old=$q->fetch();if($old){$pdo->prepare('UPDATE ratings SET stars=? WHERE id=?')->execute([$stars,$old['id']]);$pdo->prepare('UPDATE tips SET rating_sum=rating_sum-?+? WHERE id=?')->execute([$old['stars'],$stars,$tipId]);}else{$pdo->prepare('INSERT INTO ratings(tip_id,user_id,stars) VALUES(?,?,?)')->execute([$tipId,$u['id'],$stars]);$pdo->prepare('UPDATE tips SET rating_sum=rating_sum+?,rating_count=rating_count+1 WHERE id=?')->execute([$stars,$tipId]);}if(is_ajax_request())bk_json_out(['ok'=>true,'reload'=>true,'message'=>'امتیاز شما ثبت شد ✓']);flash('امتیاز شما ثبت شد.');redirect_to('tip/'.$tipId.'#rating');}
    if($action==='follow'){ $u=require_login();$target=(int)$_POST['user_id'];if($target===$u['id'])redirect_to('profile/'.$target);$q=$pdo->prepare('SELECT id FROM follows WHERE follower_id=? AND following_id=?');$q->execute([$u['id'],$target]);$old=$q->fetchColumn();if($old)$pdo->prepare('DELETE FROM follows WHERE id=?')->execute([$old]);else{$pdo->prepare('INSERT INTO follows(follower_id,following_id) VALUES(?,?)')->execute([$u['id'],$target]);notify_user($target,'follow','دنبال‌کننده جدید',$u['name'].' شما را دنبال کرد.',url('profile/'.$u['id']));}redirect_to($_POST['back']??'');}
    if($action==='repair_answer'){ $u=require_login();$rid=(int)$_POST['request_id'];$body=clean_text($_POST['body']??'');$q=$pdo->prepare('SELECT * FROM repair_requests WHERE id=?');$q->execute([$rid]);$r=$q->fetch();if(!$r||$r['status']!=='open'||(int)$r['user_id']===(int)$u['id']||mb_strlen($body)<10){flash('پاسخ قابل ثبت نیست.','error');redirect_to('repair/'.$rid);} $pdo->prepare('INSERT INTO repair_answers(request_id,user_id,body) VALUES(?,?,?)')->execute([$rid,$u['id'],$body]);$pdo->prepare('UPDATE repair_requests SET answer_count=answer_count+1 WHERE id=?')->execute([$rid]);notify_user((int)$r['user_id'],'repair','پاسخ جدید برای درخواست شما',$u['name'].' به درخواست شما پاسخ داد.',url('repair/'.$rid));flash('پاسخ شما ثبت شد.');redirect_to('repair/'.$rid);}
    if($action==='repair_best'){ $u=require_login();$rid=(int)$_POST['request_id'];$aid=(int)$_POST['answer_id'];$q=$pdo->prepare('SELECT r.*,a.user_id answer_user FROM repair_requests r JOIN repair_answers a ON a.request_id=r.id WHERE r.id=? AND a.id=?');$q->execute([$rid,$aid]);$r=$q->fetch();if(!$r||$r['user_id']!=$u['id']||$r['status']!=='open'){flash('عملیات نامعتبر.','error');redirect_to('repair/'.$rid);} $pdo->prepare("UPDATE repair_requests SET status='closed',best_answer_id=? WHERE id=?")->execute([$aid,$rid]);$pdo->prepare('UPDATE repair_answers SET is_best=1 WHERE id=?')->execute([$aid]);if($r['reward_type']==='money'&&(int)$r['reward_amount']>0&&debit($u['id'],(int)$r['reward_amount'],'repair_payment','پاداش پاسخ منتخب',null,$rid))credit((int)$r['answer_user'],(int)$r['reward_amount'],'repair_reward','پاداش پاسخ منتخب',null,$rid);award((int)$r['answer_user'],50);notify_user((int)$r['answer_user'],'repair','پاسخ شما انتخاب شد','پاسخ شما به عنوان بهترین پاسخ انتخاب شد.',url('repair/'.$rid));flash('بهترین پاسخ انتخاب شد.');redirect_to('repair/'.$rid);}
    if($action==='report'){ $u=require_login();$pdo->prepare('INSERT INTO reports(reporter_id,target_type,target_id,reason,detail) VALUES(?,?,?,?,?)')->execute([$u['id'],$_POST['target_type']??'tip',(int)$_POST['target_id'],clean_text($_POST['reason']??'گزارش کاربر'),clean_text($_POST['detail']??'')]);flash('گزارش شما ثبت شد.');redirect_to($_POST['back']??'');}
    if($action==='comment_vote'){ $u=require_login();$cid=(int)$_POST['comment_id'];$vote=($_POST['vote']??'1')==='-1'?-1:1;$c=$pdo->prepare('SELECT id,tip_id FROM comments WHERE id=? AND is_deleted=0');$c->execute([$cid]);$cm=$c->fetch();if(!$cm){flash('نظر یافت نشد.','error');redirect_to('');}$q=$pdo->prepare('SELECT id,value FROM comment_votes WHERE comment_id=? AND user_id=?');$q->execute([$cid,$u['id']]);$old=$q->fetch();if($old){if((int)$old['value']===$vote){$pdo->prepare('DELETE FROM comment_votes WHERE id=?')->execute([$old['id']]);}else{$pdo->prepare('UPDATE comment_votes SET value=? WHERE id=?')->execute([$vote,$old['id']]);}}else{$pdo->prepare('INSERT INTO comment_votes(comment_id,user_id,value) VALUES(?,?,?)')->execute([$cid,$u['id'],$vote]);}redirect_to('tip/'.$cm['tip_id'].'#comment-'.$cid);}
    if($action==='favorite'){ $u=require_login();$tipId=(int)$_POST['tip_id'];$ajax=is_ajax_request();$q=$pdo->prepare('SELECT id FROM favorites WHERE user_id=? AND tip_id=?');$q->execute([$u['id'],$tipId]);$liked=false;if($q->fetch()){$pdo->prepare('DELETE FROM favorites WHERE user_id=? AND tip_id=?')->execute([$u['id'],$tipId]);$pdo->prepare('UPDATE tips SET likes_count=GREATEST(likes_count-1,0) WHERE id=?')->execute([$tipId]);}else{$pdo->prepare('INSERT INTO favorites(user_id,tip_id) VALUES(?,?)')->execute([$u['id'],$tipId]);$pdo->prepare('UPDATE tips SET likes_count=likes_count+1 WHERE id=?')->execute([$tipId]);$liked=true;}$cnt=$pdo->prepare('SELECT likes_count FROM tips WHERE id=?');$cnt->execute([$tipId]);if($ajax)bk_json_out(['ok'=>true,'liked'=>$liked,'likes'=>(int)$cnt->fetchColumn()]);redirect_to($_POST['back']??'tip/'.$tipId);}
    if($action==='bookmark'){ $u=require_login();$tipId=(int)$_POST['tip_id'];$q=$pdo->prepare('SELECT id FROM bookmarks WHERE user_id=? AND tip_id=?');$q->execute([$u['id'],$tipId]);if($q->fetch()){$pdo->prepare('DELETE FROM bookmarks WHERE user_id=? AND tip_id=?')->execute([$u['id'],$tipId]);flash('نشانک حذف شد.');}else{$note=trim($_POST['note']??'');$pdo->prepare('INSERT INTO bookmarks(user_id,tip_id,note) VALUES(?,?,?)')->execute([$u['id'],$tipId,$note]);flash('به نشانک‌های من اضافه شد.');}redirect_to($_POST['back']??'tip/'.$tipId);}
    if($action==='search_live'){ $q=trim($_POST['q']??'');$results=[];if(mb_strlen($q)>=2){ $stmt=db()->prepare('SELECT id,title,access_type,price,views FROM tips WHERE status="published" AND (title LIKE ? OR device_name LIKE ? OR brand LIKE ? OR tags LIKE ?) ORDER BY views DESC LIMIT 8'); $like='%'.$q.'%'; $stmt->execute([$like,$like,$like,$like]); $results=$stmt->fetchAll(); } header('Content-Type: application/json; charset=utf-8'); echo json_encode(['items'=>array_map(fn($r)=>['id'=>(int)$r['id'],'title'=>$r['title'],'access'=>access_label($r['access_type'],(int)$r['price']),'views'=>(int)$r['views']],$results)], JSON_UNESCAPED_UNICODE); exit; }
    if($action==='admin_tip'){ $a=require_admin();$id=(int)$_POST['tip_id'];$act=$_POST['mod_action']??'';try{if($act==='feature'){$pdo->prepare('UPDATE tips SET featured=1-featured WHERE id=?')->execute([$id]);flash('وضعیت منتخب تغییر کرد.');redirect_to('admin?tab=tips');}if($act==='delete_forever'){$pdo->prepare('DELETE FROM tips WHERE id=?')->execute([$id]);flash('قلق برای همیشه حذف شد.');redirect_to('admin?tab=tips');}$status=$act==='publish'?'published':($act==='reject'?'rejected':($act==='remove'?'removed':null));if($status){$pdo->prepare('UPDATE tips SET status=?,published_at=CASE WHEN ?="published" THEN COALESCE(published_at,NOW()) ELSE published_at END,rejection_reason=? WHERE id=?')->execute([$status,$status,$_POST['reason']??null,$id]);$q=$pdo->prepare('SELECT author_id,title FROM tips WHERE id=?');$q->execute([$id]);$t=$q->fetch();if($t){try{notify_user((int)$t['author_id'],'admin','وضعیت قلق تغییر کرد','وضعیت قلق «'.$t['title'].'» به '.status_label($status).' تغییر کرد.',url('tip/'.$id));}catch(Throwable $e){}if($status==='published'){try{award_badge((int)$t['author_id'],'first_tip');$cnt=$pdo->prepare('SELECT COUNT(*) FROM tips WHERE author_id=? AND status=?');$cnt->execute([(int)$t['author_id'],'published']);if((int)$cnt->fetchColumn()>=10)award_badge((int)$t['author_id'],'ten_tips');}catch(Throwable $e){}}}}flash('عملیات مدیریت انجام شد.');}catch(Throwable $e){flash('خطا در تغییر وضعیت قلق: '.$e->getMessage(),'error');}redirect_to('admin?tab=tips');}
    if($action==='admin_user'){ $a=require_admin();$id=(int)$_POST['user_id'];$role=$_POST['role']??'member';if(!in_array($role,['member','expert','moderator','admin','superadmin'],true))$role='member';$q=$pdo->prepare('SELECT * FROM users WHERE id=?');$q->execute([$id]);$t=$q->fetch();if(!$t){flash('کاربر یافت نشد.','error');redirect_to('admin?tab=users');}if($t['role']==='superadmin'&&$a['role']!=='superadmin'){flash('فقط سوپرادمین می‌تواند حساب سوپرادمین را تغییر دهد.','error');redirect_to('admin?tab=users');}if($id===(int)$a['id']&&$role!==$a['role']){flash('نمی‌توانید نقش خودتان را تغییر دهید.','error');redirect_to('admin?tab=users');}if($id===(int)$a['id']&&!empty($_POST['banned'])){flash('نمی‌توانید حساب خودتان را مسدود کنید.','error');redirect_to('admin?tab=users');}$name=clean_text($_POST['name']??$t['name']);$phone=preg_replace('/[^0-9]/','',$_POST['phone']??$t['phone']);if(mb_strlen($name)<3)$name=$t['name'];if(!preg_match('/^09\d{9}$/',$phone))$phone=$t['phone'];$pdo->prepare('UPDATE users SET role=?,verified=?,is_banned=?,name=?,phone=? WHERE id=?')->execute([$role,!empty($_POST['verified'])?1:0,!empty($_POST['banned'])?1:0,$name,$phone,$id]);if(!empty($_POST['verified']))award_badge($id,'expert');$delta=(int)($_POST['delta']??0);$note=clean_text($_POST['note']??'')?:'تعدیل توسط مدیر';if($delta!==0&&$id!==(int)$a['id']){if($delta>0){credit($id,$delta,'admin_adjust','شارژ کیف پول توسط مدیر: '.$note);notify_user($id,'wallet','کیف پول شما شارژ شد',money($delta).' تومان توسط مدیر به کیف پول شما اضافه شد.',url('wallet'));}elseif(!debit($id,-$delta,'admin_adjust','کسر از کیف پول توسط مدیر: '.$note)){flash('کسر انجام نشد: موجودی کاربر کافی نیست.','error');}}sec_log((int)$a['id'],'admin_user_updated','تغییر کاربر #'.$id.' (نقش: '.$role.')');flash('کاربر به‌روزرسانی شد.');redirect_to('admin?tab=users');}
    if($action==='admin_withdraw'){ require_admin();$id=(int)$_POST['withdrawal_id'];$status=$_POST['status']??'pending';$q=$pdo->prepare('SELECT * FROM withdrawals WHERE id=?');$q->execute([$id]);$w=$q->fetch();if($w&&in_array($status,['paid','rejected','reviewing'],true)){$pdo->prepare('UPDATE withdrawals SET status=?,admin_note=?,reviewed_at=IF(? IN ("paid","rejected"),NOW(),NULL) WHERE id=?')->execute([$status,clean_text($_POST['note']??''),$status,$id]);if($status==='rejected')credit((int)$w['user_id'],(int)$w['amount'],'withdrawal_cancel','برگشت تسویه رد شده');notify_user((int)$w['user_id'],'wallet','وضعیت تسویه تغییر کرد',$status==='paid'?'تسویه شما واریز شد.':($status==='rejected'?'تسویه رد شد و مبلغ برگشت داده شد.':'درخواست تسویه در حال بررسی است.'),url('wallet'));}redirect_to('admin?tab=withdrawals');}
    if(!function_exists('unsplash_img')) { function unsplash_img(string $q, int $w=1200): ?string { $u = 'https://source.unsplash.com/'.$w.'x800/?'.rawurlencode($q); return fetch_url($u, 10) ? $u : null; } }
/* ---------- v5.5: کد تأیید ایمیل برای کاربران واردشده ---------- */
if($action==='email_code_resend'){ require_login();
    $p=$_SESSION['pending_register']??null; if(!$p)bk_json_out(['ok'=>false,'error'=>'نشست ثبت‌نام یافت نشد؛ دوباره ثبت‌نام کنید.'],422);
    $send=bk_send_email_code((string)$p['email'],'register');
    if(!$send['ok'])bk_json_out(['ok'=>false,'error'=>$send['error']],429);
    bk_json_out(['ok'=>true,'message'=>'کد جدید به '.bk_mask_email((string)$p['email']).' ارسال شد.']);
}
if($action==='email_set'){ $u=require_login();$ajax=is_ajax_request();
        if(email_verified($u)){ if($ajax)bk_json_out(['ok'=>true,'url'=>url('')]); flash('ایمیل شما از قبل تأیید شده است.'); redirect_to(''); }
        $email=strtolower(trim((string)($_POST['email']??'')));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))bk_json_out(['ok'=>false,'error'=>'ایمیل معتبر وارد کنید.'],422);
        $e=$pdo->prepare('SELECT id FROM users WHERE email=? AND id<>? LIMIT 1');$e->execute([$email,(int)$u['id']]);
        if($e->fetch())bk_json_out(['ok'=>false,'error'=>'این ایمیل متعلق به حساب دیگری است.'],422);
        $pdo->prepare('UPDATE users SET email=? WHERE id=?')->execute([$email,(int)$u['id']]);
        $send=bk_send_email_code($email,'gate');
        if(!$send['ok'])bk_json_out(['ok'=>false,'error'=>$send['error']],429);
        if($ajax)bk_json_out(['ok'=>true,'reload'=>true,'message'=>'کد تأیید به '.bk_mask_email($email).' ارسال شد — کد را وارد کنید.']);
        flash('کد تأیید به ایمیل شما ارسال شد.'); redirect_to('verify-email');
    }
    if($action==='email_code_send'){ $u=require_login();
    if(email_verified($u)){ bk_json_out(['ok'=>true,'message'=>'ایمیل شما از قبل تأیید شده است.']); }
    $send=bk_send_email_code((string)$u['email'],'gate');
    if(!$send['ok'])bk_json_out(['ok'=>false,'error'=>$send['error']],429);
    bk_json_out(['ok'=>true,'message'=>'کد تأیید به '.bk_mask_email((string)$u['email']).' ارسال شد.']);
}
if($action==='email_code_verify'){ $u=require_login();$ajax=is_ajax_request();
    if(email_verified($u)){ if($ajax)bk_json_out(['ok'=>true,'url'=>url('')]); flash('ایمیل شما از قبل تأیید شده است.'); redirect_to(''); }
    if(!bk_check_email_code((string)$u['email'],(string)($_POST['code']??''),'gate')){ if($ajax)bk_json_out(['ok'=>false,'error'=>'کد نادرست یا منقضی است.'],422); flash('کد نادرست یا منقضی است.','error'); redirect_to('verify-email'); }
    try{ $pdo->prepare('UPDATE users SET email_verified=1 WHERE id=?')->execute([(int)$u['id']]); }catch(Throwable $e){ try{ $pdo->exec('ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0'); $pdo->prepare('UPDATE users SET email_verified=1 WHERE id=?')->execute([(int)$u['id']]); }catch(Throwable $e2){} }
    sec_log((int)$u['id'],'email_verified','تأیید ایمیل موفق');
        $back=$_SESSION['email_gate']['back'] ?? url(''); unset($_SESSION['email_gate']);
    if($ajax)bk_json_out(['ok'=>true,'url'=>$back,'message'=>'ایمیل شما تأیید شد ✓ در حال بازگشت…']);
    flash('ایمیل شما با موفقیت تأیید شد.'); redirect_to($back);
}
if($action==='subscribe'){ $u=require_login();$months=(int)($_POST['months']??1);$prices=[1=>(int)settings()['premium_1'],3=>(int)settings()['premium_3'],12=>(int)settings()['premium_12']];$amount=$prices[$months]??$prices[1];if(!debit($u['id'],$amount,'subscription','خرید اشتراک ویژه '.$months.' ماهه')){flash('موجودی کیف پول کافی نیست.','error');redirect_to('wallet');}$base=($u['premium_until']&&strtotime($u['premium_until'])>time())?strtotime($u['premium_until']):time();$until=date('Y-m-d H:i:s',$base+$months*30*86400);$pdo->prepare('UPDATE users SET premium_until=? WHERE id=?')->execute([$until,$u['id']]);award_badge((int)$u['id'],'premium');flash('اشتراک ویژه با موفقیت فعال شد.');redirect_to('premium');}
    /* ---------- v5.7: مدیریت نشست‌های فعال ---------- */
if($action==='logout_all'){ $u=require_login();
    try{ $pdo->prepare('DELETE FROM user_sessions WHERE user_id=? AND sid_hash<>?')->execute([(int)$u['id'], bk_sid_hash()]); }catch(Throwable $e){}
    sec_log((int)$u['id'],'logout_all','خروج از همهٔ دستگاه‌های دیگر');
    flash('از همهٔ دستگاه‌های دیگر خارج شدید ✓'); redirect_to('settings');
}
if($action==='session_kill'){ $u=require_login();$sid=(int)($_POST['sid']??0);
    try{ $pdo->prepare('DELETE FROM user_sessions WHERE id=? AND user_id=? AND sid_hash<>?')->execute([$sid,(int)$u['id'], bk_sid_hash()]); }catch(Throwable $e){}
    sec_log((int)$u['id'],'session_killed','بستن یک نشست');
    flash('دستگاه انتخاب‌شده خارج شد.'); redirect_to('settings');
}
if($action==='profile_update'){ $u=require_login();$name=clean_text($_POST['name']??$u['name']);$bio=trim($_POST['bio']??'');if(mb_strlen($name)<3){flash('نام معتبر نیست.','error');redirect_to('settings');}$pdo->prepare('UPDATE users SET name=?,bio=? WHERE id=?')->execute([$name,$bio,$u['id']]);flash('پروفایل ذخیره شد.');redirect_to('settings');}
    if($action==='suggest_category'){ $u=require_login();$name=clean_text($_POST['name']??'');$parent=(int)($_POST['parent_id']??0)?:null;if(mb_strlen($name)<2){flash('نام دسته را وارد کنید.','error');redirect_to('upload');}$q=$pdo->prepare('SELECT id FROM categories WHERE name=? LIMIT 1');$q->execute([$name]);if($q->fetch()){flash('این دسته قبلاً ثبت شده است.','error');redirect_to('upload');}$pdo->prepare('INSERT INTO categories(parent_id,name,slug,icon,status) VALUES(?,?,?,?,?)')->execute([$parent,$name,'cat-'.md5($name.time()),null,'pending']);flash('پیشنهاد دسته شما ثبت شد و پس از تأیید مدیر اضافه می‌شود.');redirect_to('upload');}
    if($action==='admin_category'){ require_admin();$op=$_POST['op']??'add';$id=(int)($_POST['category_id']??0);$name=clean_text($_POST['name']??'');$parent=(int)($_POST['parent_id']??0)?:null;$icon=clean_text($_POST['icon']??'');if($op==='delete'&&$id){$pdo->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);flash('دسته حذف شد.');}elseif($op==='approve'&&$id){$pdo->prepare("UPDATE categories SET status='active' WHERE id=?")->execute([$id]);flash('دسته تأیید و فعال شد.');}elseif($op==='dedupe'){$n=$pdo->exec('DELETE c1 FROM categories c1 INNER JOIN categories c2 ON c1.name=c2.name AND IFNULL(c1.parent_id,0)=IFNULL(c2.parent_id,0) AND c1.id>c2.id');flash(($n?:0).' دستهٔ تکراری حذف شد.');}elseif($op==='add'&&mb_strlen($name)>=2){$pdo->prepare('INSERT INTO categories(parent_id,name,slug,icon,status) VALUES(?,?,?,?,?)')->execute([$parent,$name,'cat-'.md5($name.time()),$icon?:null,'active']);flash('دسته جدید افزوده شد.');}redirect_to('admin?tab=categories');}
    if($action==='admin_settings'){ require_admin();$map=['site_title'=>clean_text($_POST['site_title']??''),'hero_title'=>clean_text($_POST['hero_title']??''),'hero_subtitle'=>clean_text($_POST['hero_subtitle']??''),'announcement'=>clean_text($_POST['announcement']??''),'upload_reward'=>max(0,(int)($_POST['upload_reward']??0)),'like_points_reward'=>max(0,(int)($_POST['like_points_reward']??0)),'like_wallet_reward'=>max(0,(int)($_POST['like_wallet_reward']??0)),'referral_reward'=>max(0,(int)($_POST['referral_reward']??0)),'invitee_credit'=>max(0,(int)($_POST['invitee_credit']??0)),'commission_percent'=>max(0,min(100,(int)($_POST['commission_percent']??0))),'min_withdrawal'=>max(0,(int)($_POST['min_withdrawal']??0)),'daily_like_limit'=>max(1,(int)($_POST['daily_like_limit']??1)),'repair_deadline_days'=>max(1,(int)($_POST['repair_deadline_days']??1)),'premium_1'=>max(0,(int)($_POST['premium_1']??0)),'premium_3'=>max(0,(int)($_POST['premium_3']??0)),'premium_12'=>max(0,(int)($_POST['premium_12']??0)),'board_commission_percent'=>max(0,min(50,(int)($_POST['board_commission_percent']??10))),'daily_free_tip_id'=>!empty($_POST['daily_free_tip_id'])?(int)$_POST['daily_free_tip_id']:null,'terms_text'=>trim($_POST['terms_text']??''),'about_text'=>trim($_POST['about_text']??''),'contact_text'=>trim($_POST['contact_text']??''),'privacy_text'=>trim($_POST['privacy_text']??''),'contact_form_enabled'=>!empty($_POST['contact_form_enabled'])?1:0,'contact_email'=>trim($_POST['contact_email']??''),'contact_phone'=>trim($_POST['contact_phone']??''),'contact_telegram'=>trim($_POST['contact_telegram']??''),'contact_instagram'=>trim($_POST['contact_instagram']??''),'contact_address'=>trim($_POST['contact_address']??''),'meta_description'=>trim($_POST['meta_description']??''),'meta_keywords'=>trim($_POST['meta_keywords']??''),'og_image'=>trim($_POST['og_image']??''),'google_analytics'=>trim($_POST['google_analytics']??''),'trust_badge_image'=>trim($_POST['trust_badge_image']??''),'trust_badge_link'=>trim($_POST['trust_badge_link']??''),'watermark_enabled'=>!empty($_POST['watermark_enabled'])?1:0,'watermark_text'=>trim($_POST['watermark_text']??'')];$have=[];foreach($pdo->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='settings'")->fetchAll() as $c)$have[$c['COLUMN_NAME']]=true;$sets=[];$vals=[];foreach($map as $k=>$v){if(isset($have[$k])){$sets[]='`'.$k.'`=?';$vals[]=$v;}}if($sets){$pdo->prepare('UPDATE settings SET '.implode(',',$sets).' WHERE id=1')->execute($vals);}flash('تنظیمات سایت ذخیره شد.');redirect_to('admin?tab=settings');}
    if($action==='admin_report'){ require_admin();$rid=(int)$_POST['report_id'];$resolve=!empty($_POST['resolve']);$q=$pdo->prepare('SELECT * FROM reports WHERE id=?');$q->execute([$rid]);$r=$q->fetch();if($r&&$r['status']==='open'){if($resolve){if($r['target_type']==='tip'){$pdo->prepare("UPDATE tips SET status='removed' WHERE id=?")->execute([(int)$r['target_id']]);}elseif($r['target_type']==='comment'){$pdo->prepare('UPDATE comments SET is_deleted=1 WHERE id=?')->execute([(int)$r['target_id']]);}} $pdo->prepare("UPDATE reports SET status='resolved',resolved_at=NOW() WHERE id=?")->execute([$rid]);}flash('گزارش پردازش شد.');redirect_to('admin?tab=reports');}
    if($action==='contact_status'){ require_admin();$cid=(int)($_POST['contact_id']??0);$op=$_POST['op']??'';try{$q=$pdo->prepare('SELECT * FROM contact_messages WHERE id=?');$q->execute([$cid]);$m=$q->fetch();}catch(Throwable $e){$m=false;}if($m){if($op==='answered'){$pdo->prepare("UPDATE contact_messages SET status='answered' WHERE id=?")->execute([$cid]);}elseif($op==='closed'){$pdo->prepare("UPDATE contact_messages SET status='closed' WHERE id=?")->execute([$cid]);}elseif($op==='reopen'){$pdo->prepare("UPDATE contact_messages SET status='new' WHERE id=?")->execute([$cid]);}elseif($op==='delete'){$pdo->prepare('DELETE FROM contact_messages WHERE id=?')->execute([$cid]);}if($m['user_id']&&in_array($op,['answered','closed'],true)){notify_user((int)$m['user_id'],'system','پیگیری پیام تماس','پیام شما با موضوع «'.$m['subject'].'» توسط پشتیبانی بررسی شد.',url('contact'));}}flash('پیام تماس به‌روزرسانی شد.');redirect_to('admin?tab=contact');}

    /* ---------- sellers / boards marketplace ---------- */
    if($action==='seller_apply'){ $u=require_login();if(is_seller($u)){redirect_to('boards/new');}$status=$u['seller_status']??'none';if($status==='pending'){flash('درخواست فروشندگی شما در حال بررسی است.');redirect_to('boards');}$note=trim($_POST['note']??'');if(mb_strlen($note)<20){flash('توضیح کامل‌تری از تخصص و تجربه خود بنویسید (حداقل ۲۰ کاراکتر).','error');redirect_to('seller-apply');}$pdo->prepare("UPDATE users SET seller_status='pending', seller_note=?, seller_applied_at=NOW() WHERE id=?")->execute([$note,$u['id']]);flash('درخواست فروشندگی شما ثبت شد و پس از بررسی مدیر فعال خواهد شد.');redirect_to('boards');}
    if($action==='board_create'){ $u=require_login();$ajax=is_ajax_request();$bk_fail=function(string $m)use($ajax){if($ajax){bk_json_out(['ok'=>false,'error'=>$m],422);}flash($m,'error');redirect_to('boards/new');};if(!is_seller($u))$bk_fail('برای فروش برد ابتدا باید فروشنده تأییدشده باشید.');try{$title=clean_text($_POST['title']??'');$desc=trim($_POST['description']??'');$cat=(int)($_POST['category_id']??0);$price=max(1000,(int)($_POST['price']??0));$stock=max(1,min(999,(int)($_POST['stock']??1)));$cond=in_array($_POST['condition_status']??'used',['new','like_new','used','repair'],true)?$_POST['condition_status']:'used';$images=[];$imgFails=[];foreach(($_FILES['images']['tmp_name']??[]) as $i=>$tmp){$f=['tmp_name'=>$tmp,'error'=>$_FILES['images']['error'][$i]??1,'size'=>$_FILES['images']['size'][$i]??0,'type'=>$_FILES['images']['type'][$i]??'','name'=>$_FILES['images']['name'][$i]??''];$saved=save_image($f);if($saved)$images[]=$saved;else $imgFails[]=($f['name']?:('عکس '.($i+1))).': '.bk_upload_error_reason($f);}if(mb_strlen($title)<5)$bk_fail('عنوان برد باید حداقل ۵ حرف باشد.');if(mb_strlen($desc)<10)$bk_fail('توضیح برد باید حداقل ۱۰ حرف باشد.');if(!$cat)$bk_fail('دسته‌بندی را انتخاب کنید.');if($price<=0)$bk_fail('قیمت نامعتبر است.');if(count($images)<1)$bk_fail('هیچ عکسی ذخیره نشد! علت: '.($imgFails?implode(' | ',array_slice($imgFails,0,2)):'مجوز نوشتن پوشه uploads را در هاست بررسی کنید'));$pdo->prepare("INSERT INTO boards(seller_id,category_id,title,description,brand,model,condition_status,price,stock,images_json,video_url,status) VALUES(?,?,?,?,?,?,?,?,?,?,?, 'pending')")->execute([$u['id'],$cat,$title,safe_rich($desc),clean_text($_POST['brand']??''),clean_text($_POST['model']??''),$cond,$price,$stock,json_encode($images,JSON_UNESCAPED_UNICODE),trim($_POST['video_url']??'')]);}catch(Throwable $e){$bk_fail('ثبت برد انجام نشد: '.$e->getMessage());}$msg='برد ثبت شد و پس از تأیید مدیر در فروشگاه نمایش داده می‌شود.';if($ajax){bk_json_out(['ok'=>true,'message'=>$msg,'redirect'=>url('my-boards')]);}flash($msg);redirect_to('my-boards');}
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
if($page==='push-subscribe'){ $u=current_user(); if(!$u)bk_json_out(['ok'=>false,'error'=>'login'],401); $raw=file_get_contents('php://input'); $data=json_decode($raw,true); if(!$data||empty($data['endpoint']))bk_json_out(['ok'=>false,'error'=>'no data'],422); try{ $existing=db()->prepare('SELECT id FROM push_subscriptions WHERE user_id=? AND endpoint=?'); $existing->execute([(int)$u['id'],$data['endpoint']]); if(!$existing->fetchColumn()){ db()->prepare('INSERT INTO push_subscriptions(user_id,endpoint,p256dh,auth) VALUES(?,?,?,?)')->execute([(int)$u['id'],$data['endpoint'],$data['keys']['p256dh']??'',$data['keys']['auth']??'']); } bk_json_out(['ok'=>true]); }catch(Throwable $e){ bk_json_out(['ok'=>false,'error'=>$e->getMessage()],500); } }
if($page==='ajax-notifications'){ $u=current_user(); if(!$u){bk_json_out(['unread'=>0,'items'=>[]]);exit;}  if(!empty($_GET['mark'])){ db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([(int)$u['id']]); bk_json_out(['ok'=>true]); } $q=db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');$q->execute([(int)$u['id']]);$unread=(int)$q->fetchColumn();$q=db()->prepare('SELECT id,type,title,body,link,is_read,created_at FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 8');$q->execute([(int)$u['id']]);$items=[];foreach($q->fetchAll() as $n){$items[]=['id'=>(int)$n['id'],'type'=>$n['type'],'title'=>$n['title'],'body'=>mb_substr((string)($n['body']??''),0,90),'link'=>$n['link'],'unread'=>(int)$n['is_read']===0,'ago'=>ago($n['created_at'])];}bk_json_out(['unread'=>$unread,'items'=>$items]); }
if($page==='ajax-categories'){ $q=trim($_GET['q']??'');$like=$q!==''?'%'.$q.'%':null;if($like){$st=db()->prepare('SELECT c.id,c.name,c.icon,c.status,p.name parent_name,(SELECT COUNT(*) FROM categories cc WHERE cc.parent_id=c.id) child_count FROM categories c LEFT JOIN categories p ON p.id=c.parent_id WHERE c.name LIKE ? OR p.name LIKE ? ORDER BY c.parent_id IS NOT NULL, c.sort_order, c.name LIMIT 250');$st->execute([$like,$like]);}else{$st=db()->query('SELECT c.id,c.name,c.icon,c.status,p.name parent_name,(SELECT COUNT(*) FROM categories cc WHERE cc.parent_id=c.id) child_count FROM categories c LEFT JOIN categories p ON p.id=c.parent_id ORDER BY c.parent_id IS NOT NULL, c.sort_order, c.name LIMIT 250');}header('Content-Type: application/json; charset=utf-8');echo json_encode(['q'=>$q,'rows'=>$st->fetchAll()],JSON_UNESCAPED_UNICODE);exit; }
/* ---------- v5.7: کرون سبک پاک‌سازی — کدهای منقضی، نشست‌های کهنه، لاگ قدیمی ---------- */
if($page==='cron-cleanup'){
    $key=(string)($_GET['key']??'');
    if($key==='' || !hash_equals(INSTALL_KEY,$key)){ http_response_code(403); exit('forbidden'); }
    $out=[];
    try{ $out['expired_email_codes']=(int)db()->exec('DELETE FROM email_codes WHERE expires_at < NOW()'); }catch(Throwable $e){ $out['expired_email_codes']='skip'; }
    try{ $out['old_sessions']=(int)db()->exec('DELETE FROM user_sessions WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)'); }catch(Throwable $e){ $out['old_sessions']='skip'; }
    try{ $out['old_security_log']=(int)db()->exec('DELETE FROM security_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)'); }catch(Throwable $e){ $out['old_security_log']='skip'; }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>true,'cleaned'=>$out],JSON_UNESCAPED_UNICODE); exit;
}
/* ---------- v5.7: صفحهٔ وضعیت پرداخت — اعتماد کاربران ---------- */
if($page==='payment-status'){
    $ps=settings();
    $gwOn=(int)($ps['gateway_enabled']??0)===1;
    $gwType=['zarinpal'=>'زرین‌پال','idpay'=>'آیدی‌پی','zibal'=>'زیبال'][$ps['gateway_type']??'zarinpal'] ?? 'زرین‌پال';
    $sandbox=(int)($ps['gateway_sandbox']??1)===1;
    $z2cOn=trim((string)($ps['z2c_card_number']??''))!=='';
    header_html('وضعیت پرداخت');
    ?><main class="wrap page"><div class="page-title"><h1>💳 وضعیت پرداخت سایت</h1><p>شفافیت کامل دربارهٔ روش‌های پرداخت و سلامت درگاه</p></div>
    <div class="admin-cards">
        <div class="card"><div class="k">درگاه آنلاین</div><div class="v" style="font-size:15px;margin-top:4px"><?=$gwOn?'<span style="color:#0a7a4a">✅ فعال</span>':'<span style="color:#b3261e">غیرفعال</span>'?></div><small class="muted"><?=$gwOn?h($gwType).' · '.($sandbox?'حالت آزمایشی':'واقعی'):'—'?></small></div>
        <div class="card"><div class="k">کارت‌به‌کارت</div><div class="v" style="font-size:15px;margin-top:4px"><?=$z2cOn?'<span style="color:#0a7a4a">✅ فعال</span>':'<span style="color:#b3261e">غیرفعال</span>'?></div><small class="muted">تأیید دستی با فیش واریزی</small></div>
        <div class="card"><div class="k">حداقل شارژ</div><div class="v" style="font-size:15px;margin-top:4px"><?=money($ps['gateway_min_charge']??100000)?></div><small class="muted">تومان</small></div>
        <div class="card"><div class="k">حداکثر شارژ</div><div class="v" style="font-size:15px;margin-top:4px"><?=money($ps['gateway_max_charge']??50000000)?></div><small class="muted">تومان</small></div>
    </div>
    <div class="card" style="padding:18px;margin-top:14px"><h3>پرداخت شما ناموفق بود؟</h3>
        <p style="font-size:12.5px;line-height:2.4">۱) موجودی و سقف روزانهٔ کارت خود را بررسی کنید<br>۲) اگر مبلغ کم شده ولی قلق باز نشد، «تراکنش‌های من» را در کیف پول ببینید — مبلغ بلافاصله برمی‌گردد<br>۳) درگاه آزمایشی است فقط برای تست؛ پرداخت واقعی نیاز به تنظیم مدیر دارد<br>۴) مشکل حل نشد؟ تیکت با دستهٔ «مالی» ثبت کنید؛ پاسخ سریع</p>
        <a class="btn btn-primary btn-sm" href="<?=url('tickets')?>">✉ تیکت مالی</a>
        <a class="btn btn-secondary btn-sm" href="<?=url('contact')?>">تماس با ما</a>
    </div>
    </main><?php footer_html();exit;
}
if($page==='logout'){
    bk_session_drop();
    /* v5.5: خروج کامل — پاک‌سازی نشست + کوکی + کش مرورگر (Clear-Site-Data) */
    $_SESSION = [];
    if (ini_get('session.use_cookies')) { $p = session_get_cookie_params(); @setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']); }
    session_destroy();
    @header('Clear-Site-Data: "cache"');
    @header('Cache-Control: no-store, max-age=0');
    redirect_to('?loggedout=' . time());
}
if($page==='assets'){ $safe=preg_replace('/[^A-Za-z0-9._-]/','',basename(trim(str_replace('assets/','',$route),'/'))); $file=__DIR__.'/assets/'.$safe; $map=['css'=>'text/css','js'=>'application/javascript','png'=>'image/png','svg'=>'image/svg+xml','webmanifest'=>'application/manifest+json','woff2'=>'font/woff2']; $ext=strtolower(pathinfo($safe,PATHINFO_EXTENSION)); if($safe!==''&&is_file($file)&&isset($map[$ext])){header('Content-Type: '.$map[$ext]);$assetCC=in_array($ext,['css','js','webmanifest'],true)?'public,max-age=3600':'public,max-age=604800';header('Cache-Control: '.$assetCC);readfile($file);exit;} http_response_code(404);exit('not found'); }
if($_SERVER['REQUEST_METHOD']==='POST'){ $bkA=$_POST['action']??''; if(in_array($bkA,['board_buy','board_ship','profile_save','wallet_gateway_start','wallet_card_to_card'],true)&&is_file(__DIR__.'/php-extended/bk_extended.php')){require __DIR__.'/php-extended/bk_extended.php';if(function_exists('check_csrf'))check_csrf();bk_extended_action($bkA);} }
if($page==='tickets'&&is_file(__DIR__.'/php-extended/tickets.php')){require __DIR__.'/php-extended/tickets.php';exit;}
if($page==='wallet-plus'&&is_file(__DIR__.'/php-extended/bk_extended.php')){require __DIR__.'/php-extended/bk_extended.php';bk_render_wallet_plus();exit;}
if($page==='admin-finance'&&is_file(__DIR__.'/php-extended/admin_finance.php')){require __DIR__.'/php-extended/admin_finance.php';exit;}
if($page==='profile-edit'&&is_file(__DIR__.'/php-extended/profile_edit.php')){require __DIR__.'/php-extended/profile_edit.php';exit;}
if($page==='admin-actionbar'&&is_file(__DIR__.'/php-extended/bk_actionbar.php')){$GLOBALS['page']=$page;require __DIR__.'/php-extended/bk_actionbar.php';exit;}
if($page==='admin-cleanup'&&is_file(__DIR__.'/php-extended/cleanup_bot.php')){require __DIR__.'/php-extended/cleanup_bot.php';exit;}
if($page==='admin-security'&&is_file(__DIR__.'/php-extended/security_log.php')){require __DIR__.'/php-extended/security_log.php';exit;}
if(in_array($page,['admin-boards','admin-users','admin-tips'],true)&&is_file(__DIR__.'/php-extended/bk_admin_extra.php')){$GLOBALS['bkx_page']=$page;require __DIR__.'/php-extended/bk_admin_extra.php';exit;}

/* v5.11: عیب‌یابی ویدیو — /diag-video?tip_id=X  (مدیر/ناظر/نویسندهٔ قلق یا ?key=INSTALL_KEY) */
if($page==='diag-video'){
    $tipId=(int)($_GET['tip_id']??0);$u=current_user();
    $tip=$tipId?db()->query('SELECT * FROM tips WHERE id='.$tipId.' LIMIT 1')->fetch():null;
    $ok=!empty($_GET['key'])&&hash_equals(INSTALL_KEY,(string)$_GET['key']);
    if(!$ok && (!$tip || !$u || (!staff($u)&&(int)$u['id']!==(int)$tip['author_id']))){http_response_code(403);exit('no access');}
    header('Content-Type: text/plain; charset=utf-8');
    echo "== Bordkhan video diag (tip #".$tipId.") ==\n";
    if(!$tip){echo "tip not found\n";exit;}
    echo "title: ".$tip['title']."\n";
    echo "status: ".$tip['status']."\n";
    echo "access_type: ".$tip['access_type']."\n";
    $vurl=trim((string)($tip['video_url']??''));
    echo "video_url(raw): ".($vurl===''?'(empty)':$vurl)."\n";
    if($vurl===''){echo "RESULT: no video for this tip\n";exit;}
    $local=str_starts_with($vurl,'/uploads/')||str_starts_with($vurl,'uploads/');
    if(!$local){echo "RESULT: not a local upload (link/iframe): ".$vurl."\n";exit;}
    $f=ltrim($vurl,'/');$full=UPLOAD_DIR.'/'.basename($f);
    echo "file path: ".$full."\n";
    echo "file exists: ".(is_file($full)?'YES':'NO — فایل روی هاست نیست!')."\n";
    if(is_file($full)){echo "file size: ".filesize($full)." bytes\n";echo "mime: ".file_mime($full)."\n";
        /* تشخیص کدک ویدیو از داخل MP4 (stsd: avc1=h264 / hvc1,hev1=h265) */
        $cc='unknown';$fp=@fopen($full,'rb');if($fp){$sz=filesize($full);$head=@fread($fp,min($sz,2*1024*1024));@fseek($fp,max(0,$sz-2*1024*1024));$tail=@fread($fp,min($sz,2*1024*1024));@fclose($fp);$d=$head.$tail;if(strpos($d,'hvc1')!==false||strpos($d,'hev1')!==false)$cc='H.265/HEVC (روی خیلی از مرورگرها پخش نمی‌شود — به H.264 تبدیل کنید)';elseif(strpos($d,'avc1')!==false||strpos($d,'avc3')!==false)$cc='H.264/AVC (سازگار)';elseif(strpos($d,'mp4v')!==false)$cc='MPEG-4 Part 2';}
        echo "video codec: ".$cc."\n";
    }
    $uid=$u?(int)$u['id']:0;$uidStr=$uid>0?(string)$uid:'guest';
    $n=hash_hmac('sha256',$uidStr.'|'.$f.'|vid|'.$tipId,INSTALL_KEY);
    $direct=url('serve',['t'=>'vid','id'=>$tipId,'f'=>$f,'n'=>$n]);
    echo "serve URL (open in browser to test):\n".SITE_URL.$direct."\n";
    echo "RESULT: done — ویدیو را در آدرس بالا باز کنید؛ اگر 403=مشکل دسترسی، 404=فایل نیست، 500=خطای سرور\n";
    exit;
}

if($page==='diag-notif'){ $u=current_user(); $ok=!empty($_GET['key'])&&hash_equals(INSTALL_KEY,(string)$_GET['key']); $uid=$u?(int)$u['id']:0; $want=(int)($_GET['user_id']??0); if($ok&&$want>0)$uid=$want; if(!$ok&&!$u){http_response_code(403);exit('no access (login or ?key=INSTALL_KEY)');} header('Content-Type: text/plain; charset=utf-8'); echo "== Bordkhan notifications diag ==\n"; echo "current user: ".($u?($u['name'].' #'.$u['id'].' role='.$u['role']):'guest')."\n"; echo "target user_id: ".$uid."\n"; try{ $tot=(int)db()->query("SELECT COUNT(*) FROM notifications WHERE user_id=".$uid)->fetchColumn(); $unr=(int)db()->query("SELECT COUNT(*) FROM notifications WHERE user_id=".$uid." AND is_read=0")->fetchColumn(); echo "total notifications: ".$tot."\n"; echo "unread: ".$unr."\n"; echo "-- last 10 rows --\n"; foreach(db()->query("SELECT id,type,title,is_read,created_at FROM notifications WHERE user_id=".$uid." ORDER BY id DESC LIMIT 10")->fetchAll() as $n){ echo "#".$n['id']." [".$n['type']."] ".($n['is_read']?'READ ':'UNREAD')." ".$n['created_at']." — ".$n['title']."\n"; } echo "RESULT: bell should show badge=".$unr." and ".min($tot,8)." items in dropdown\n"; }catch(Throwable $e){ echo "DB ERROR: ".$e->getMessage()."\n"; } exit; }
if($page==='serve'||$page==='serve.php'){require __DIR__.'/serve.php';exit;}
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
    $st=db()->prepare('SELECT t.*,u.name author_name,u.avatar author_avatar,u.verified author_verified,u.points author_points,c.name category_name FROM tips t JOIN users u ON u.id=t.author_id LEFT JOIN categories c ON c.id=t.category_id WHERE t.id=? LIMIT 1');$st->execute([$id]);$t=$st->fetch();if(!$t)exit('قلق یافت نشد');$u=current_user();if($t['status']!=='published'&&(!staff($u)&&(int)($u['id']??0)!==(int)$t['author_id']))exit('این قلق در دسترس نیست');db()->prepare('UPDATE tips SET views=views+1 WHERE id=?')->execute([$id]);$access=false;if($t['access_type']==='free'||($u&&(int)$u['id']===(int)$t['author_id'])||staff($u))$access=true;if($u){$q=db()->prepare('SELECT id FROM tip_accesses WHERE tip_id=? AND user_id=?');$q->execute([$id,$u['id']]);if($q->fetch())$access=true;if($u['premium_until']&&strtotime($u['premium_until'])>time())$access=true;}$imgs=tip_images($t);$comments=db()->prepare('SELECT c.*,u.name user_name,u.avatar FROM comments c JOIN users u ON u.id=c.user_id WHERE c.tip_id=? ORDER BY c.created_at ASC');$comments->execute([$id]);$comments=$comments->fetchAll();$voteTotals=[];$voteMine=[];if($comments){$in=implode(',',array_map(fn($c)=>(int)$c['id'],$comments));$vt=db()->query("SELECT comment_id,SUM(value) s FROM comment_votes WHERE comment_id IN ($in) GROUP BY comment_id")->fetchAll();foreach($vt as $r)$voteTotals[(int)$r['comment_id']]=(int)$r['s'];if($u){$vm=db()->prepare("SELECT comment_id,value FROM comment_votes WHERE user_id=? AND comment_id IN ($in)");$vm->execute([$u['id']]);foreach($vm->fetchAll() as $r)$voteMine[(int)$r['comment_id']]=(int)$r['value'];}}$related=db()->prepare("SELECT t.*,u.name author_name,u.verified FROM tips t JOIN users u ON u.id=t.author_id WHERE t.category_id=? AND t.id<>? AND t.status='published' ORDER BY t.views DESC LIMIT 4");$related->execute([$t['category_id'],$id]);$related=$related->fetchAll();$rating=$t['rating_count']?round($t['rating_sum']/$t['rating_count'],1):0;header_html($t['title']);?><main class="wrap page"><div class="breadcrumbs"><a href="<?=url()?>">خانه</a> / <a href="<?=url('tips')?>">قلق‌ها</a> / <?=h($t['title'])?></div><div class="tip-layout"><article><div class="tip-meta"><span class="pill <?=h($t['access_type']==='paid'?'amber':($t['access_type']==='like'?'rose':'green'))?>"><?=h(access_label($t['access_type'],(int)$t['price']))?></span><span class="pill"><?=h(['easy'=>'آسان','medium'=>'متوسط','hard'=>'سخت'][$t['difficulty']]??'متوسط')?></span><span class="pill">◉ <?=fa($t['views'])?> بازدید</span><span class="pill">★ <?=fa($rating)?> (<?=fa($t['rating_count'])?>)</span></div><h1 class="tip-title"><?=h($t['title'])?></h1><div class="author"><span class="avatar"><?=h(mb_substr($t['author_name'],0,1))?></span><span class="author-info"><strong><?=h($t['author_name'])?> <?php if($t['author_verified']):?><span class="check">✓</span><?php endif;?></strong><small><?=h(level_name((int)$t['author_points']))?> · <?=fa($t['author_points'])?> امتیاز</small></span><?php if($u&&(int)$u['id']!==(int)$t['author_id']):$fq=db()->prepare('SELECT id FROM follows WHERE follower_id=? AND following_id=?');$fq->execute([$u['id'],$t['author_id']]);$following=$fq->fetchColumn();?><form method="post" style="margin-right:auto"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="follow"><input type="hidden" name="user_id" value="<?=$t['author_id']?>"><input type="hidden" name="back" value="<?=h('tip/'.$id)?>"><button class="btn <?=$following?'btn-secondary':'btn-primary'?> btn-sm"><?=$following?'دنبال‌شده':'دنبال کردن'?></button></form><?php endif;?></div><?php if($access):?><div class="tip-cover"><?php foreach(array_slice($imgs,0,10) as $i=>$img):?><div class="media-protect full-lock bk-zoomable" data-wm="<?=h(watermark_text())?>" data-uid="<?=fa((int)($u['id']??0))?>"><img src="<?=h(image_url($t,$img,$u,true))?>" alt="تصویر <?=fa($i+1)?> — <?=h($t['title'])?>" class="no-save" draggable="false" loading="lazy"><?=bk_watermark_overlay($u)?><span class="wm">© بردخان <?=fa((int)($u['id']??0))?></span></div><?php endforeach;?></div><?php endif;?>
<div class="tip-action-row">
<form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="bookmark"><input type="hidden" name="tip_id" value="<?=$id?>"><input type="hidden" name="back" value="<?=h('tip/'.$id)?>"><button class="btn btn-secondary btn-sm">🔖 <?=has_bookmark($id,$u)?'حذف نشانک':'نشانک'?></button></form>
<form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="favorite"><input type="hidden" name="tip_id" value="<?=$id?>"><input type="hidden" name="back" value="<?=h('tip/'.$id)?>"><button class="btn btn-<?=has_favorite($id,$u)?'danger':'secondary'?> btn-sm"><?=has_favorite($id,$u)?'♥ پسندیده شد':'♡ پسندیدم'?></button></form>
</div><?php if(!$access):?><div class="locked"><div class="lock">🔒</div><?php if($t['access_type']==='like'):?><h2>این قلق با یک لایک باز می‌شود</h2><p><?=h($t['short_description'])?></p><p>بعد از لایک، همه عکس‌ها، ویدیو و مراحل گام‌به‌گام تعمیر برای شما نمایش داده می‌شود.</p><?php else:?><h2>این قلق پولی است</h2><p><?=h($t['short_description'])?></p><p>با پرداخت <?=money($t['price'])?> تومان، همه عکس‌ها، ویدیو و مراحل گام‌به‌گام تعمیر برای شما نمایش داده می‌شود.</p><?php endif;?><?php if(!$u):?><a class="btn btn-primary" href="<?=url('login')?>">برای باز کردن قلق وارد شوید</a><?php else:?><form method="post" class="bk-ajax"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="unlock"><input type="hidden" name="tip_id" value="<?=$id?>"><div class="bk-ajax-msg"></div><button class="btn <?=$t['access_type']==='like'?'btn-danger':'btn-primary'?>"><?=$t['access_type']==='like'?'♥ لایک و باز کردن':'🛒 پرداخت و باز کردن — '.money($t['price']).' تومان'?></button></form><?php endif;?></div><?php else:?><div class="rich"><?=safe_rich($t['description'])?></div><h2 class="section-head" style="margin-top:30px;font-size:19px">🔧 راه‌حل گام‌به‌گام</h2><div class="steps"><?php foreach(tip_solution($t) as $i=>$step):?><div class="step"><span class="step-num"><?=fa($i+1)?></span><div><h3><?=h($step['title']??'')?></h3><p><?=h($step['body']??'')?></p><?php if(!empty($step['img'])): ?><div class="media-protect full-lock bk-zoomable" style="max-width:420px;margin-top:8px" data-wm="<?=h(watermark_text())?>" data-uid="<?=fa((int)($u['id']??0))?>"><img src="<?=h(media_url((string)$step['img'],'img',(int)$t['id'],$u?(int)$u['id']:0))?>" alt="عکس <?=h($step['title']??('گام '.($i+1)))?>" class="no-save" draggable="false" loading="lazy" style="width:100%;border-radius:12px"><?=bk_watermark_overlay($u)?><span class="wm">© بردخان <?=fa((int)($u['id']??0))?></span></div><?php endif; ?></div></div><?php endforeach;?></div><?php if($t['tools']):?><div class="mt"><b style="font-size:14px">ابزار لازم</b><div class="tip-meta" style="margin-top:8px"><?php foreach(explode('،',$t['tools']) as $tool):?><span class="pill blue"><?=h(trim($tool))?></span><?php endforeach;?></div></div><?php endif;?><?=video_embed($t['video_url'] ?? '', $t, $u)?><div class="card" id="rating" style="padding:16px;margin-top:25px"><b>به این قلق امتیاز دهید</b><p class="muted" style="font-size:12px;margin:4px 0 0">ستارهٔ ۱ = خیلی بد · ستارهٔ ۵ = عالی</p><?php $myStars=0; if($u){ $rq=db()->prepare('SELECT stars FROM ratings WHERE tip_id=? AND user_id=?'); $rq->execute([$id,$u['id']]); $myStars=(int)$rq->fetchColumn(); } ?><form method="post" class="bk-ajax bk-rating-form" style="margin-top:10px" data-current="<?=$myStars?>"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="rate"><input type="hidden" name="tip_id" value="<?=$id?>"><input type="hidden" name="stars" class="bk-stars-input" value="<?=$myStars?>"><div class="bk-stars" dir="ltr" role="radiogroup" aria-label="امتیاز"><?php for($star=1;$star<=5;$star++):?><button type="button" class="bk-star" data-star="<?=$star?>" aria-label="<?=$star?> ستاره">★</button><?php endfor;?></div><span class="bk-stars-label"><?=$myStars>0?'امتیاز شما: '.fa($myStars).' از ۵':'انتخاب کنید'?></span><div class="bk-ajax-msg" style="margin-top:8px"></div><button type="submit" class="btn btn-primary btn-sm">ثبت امتیاز</button></form><script>(function(){var form=document.querySelector("#rating .bk-rating-form");if(!form)return;var inp=form.querySelector(".bk-stars-input");var stars=form.querySelectorAll(".bk-star");var label=form.querySelector(".bk-stars-label");function fa(n){return String(n).replace(/[0-9]/g,function(d){return "۰۱۲۳۴۵۶۷۸۹"[d];});}function paint(n){for(var i=0;i<stars.length;i++){stars[i].classList.toggle("on",i<n);}}var cur=parseInt(form.getAttribute("data-current")||"0",10);inp.value=cur;paint(cur);if(label)label.textContent=cur>0?("امتیاز شما: "+fa(cur)+" از ۵"):"انتخاب کنید";for(var i=0;i<stars.length;i++){(function(star){var n=parseInt(star.getAttribute("data-star"),10);star.addEventListener("mouseenter",function(){for(var k=0;k<stars.length;k++){stars[k].classList.toggle("hov",k<n);}if(label)label.textContent=fa(n)+" از ۵ ستاره";});star.addEventListener("mouseleave",function(){for(var k=0;k<stars.length;k++){stars[k].classList.remove("hov");}if(label)label.textContent=parseInt(inp.value,10)>0?("امتیاز شما: "+fa(inp.value)+" از ۵"):"انتخاب کنید";});star.addEventListener("click",function(){inp.value=n;paint(n);if(label)label.textContent="امتیاز شما: "+fa(n)+" از ۵";});})(stars[i]);}form.addEventListener("submit",function(e){if(!parseInt(inp.value,10)){e.preventDefault();e.stopPropagation();if(label)label.textContent="⚠️ اول یک ستاره انتخاب کنید";}});})();</script></div><?php endif;?><div class="comments" id="comments"><h2 style="font-size:19px">نظرات (<?=fa(count($comments))?>)</h2><?php if($u):?><form method="post" class="card bk-ajax" style="padding:15px;margin-bottom:15px"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="comment"><input type="hidden" name="tip_id" value="<?=$id?>"><textarea class="field" name="body" rows="3" placeholder="نظر یا تجربه خود را بنویسید…"></textarea><div class="bk-ajax-msg"></div><button class="btn btn-primary btn-sm mt">ثبت نظر</button></form><?php else:?><div class="card empty"><a href="<?=url('login')?>" class="check">برای ثبت نظر وارد شوید</a></div><?php endif;?><?php foreach($comments as $c):$cv=(int)($voteTotals[(int)$c['id']]??0);$cmv=$voteMine[(int)$c['id']]??0;?><div class="card comment" id="comment-<?=$c['id']?>"><div class="comment-head"><span class="avatar small"><?=h(mb_substr($c['user_name'],0,1))?></span><b style="font-size:12px"><?=h($c['user_name'])?></b><small class="muted"><?=ago($c['created_at'])?></small></div><?php if(!$c['is_deleted']):?><p class="comment-body"><?=nl2br(h($c['body']))?></p><div class="flex aicenter gap" style="margin-top:8px"><form method="post" class="bk-ajax" style="display:inline"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="comment_vote"><input type="hidden" name="comment_id" value="<?=$c['id']?>"><input type="hidden" name="vote" value="1"><button class="btn btn-sm <?=$cmv===1?'btn-primary':'btn-secondary'?>" title="مفید بود">👍 <?=fa($cv)?></button></form><form method="post" class="bk-ajax" style="display:inline"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="comment_vote"><input type="hidden" name="comment_id" value="<?=$c['id']?>"><input type="hidden" name="vote" value="-1"><button class="btn btn-sm <?=$cmv===-1?'btn-danger':'btn-secondary'?>" title="مفید نبود">👎</button></form></div><?php else:?><p class="comment-body">این نظر حذف شده است.</p><?php endif;?></div><?php endforeach;?></div></article><aside><div class="card side-card"><h3>مشخصات دستگاه</h3><div class="info-list"><div><span>دستگاه</span><b><?=h($t['device_name'])?></b></div><div><span>برند</span><b><?=h($t['brand'])?></b></div><div><span>مدل</span><b><?=h($t['model']?:'—')?></b></div><div><span>شماره برد</span><b><?=h($t['board_number']?:'—')?></b></div><div><span>نوع خرابی</span><b><?=h($t['fault_type'])?></b></div></div></div><div class="card side-card"><h3>آمار قلق</h3><div class="stat-grid"><div><b><?=fa($t['views'])?></b><small>بازدید</small></div><div><b><?=fa($t['likes_count'])?></b><small>لایک</small></div><div><b><?=fa($t['purchases_count'])?></b><small>خرید</small></div></div></div><?php if (!empty($t['source_url']) && staff($u)): ?><div class="card side-card"><h3>🔗 مطلب اصلی (منبع)</h3><p style="font-size:11px;direction:ltr;text-align:left;word-break:break-all;line-height:1.9"><a class="check" href="<?=h($t['source_url'])?>" target="_blank" rel="noopener"><?=h($t['source_url'])?></a></p><?php if (!empty($t['source_name'])): ?><small class="muted">منبع: <?=h($t['source_name'])?></small><?php endif; ?></div><?php endif; ?><div class="card side-card"><h3>شما هم قلق دارید؟</h3><p class="muted" style="font-size:12px">دانش تعمیراتی خود را ثبت کنید و پاداش آپلود بگیرید.</p><a class="btn btn-primary btn-full btn-sm" href="<?=url('upload')?>">آپلود قلق جدید</a></div></aside></div><?php if($related):?><section class="section"><div class="section-head"><h2>قلق‌های مرتبط</h2></div><div class="grid grid-4"><?php foreach($related as $r)tip_card($r);?></div></section><?php endif;?></main><?php footer_html();exit; }



if($page==='forgot'){
header_html('بازیابی رمز عبور');
$step2=!empty($_SESSION['pending_reset']);
?><main class="auth-page"><div class="auth-box"><div class="logo">⌁ برد<em>خان</em></div><div class="card auth-card"><h1>بازیابی رمز عبور</h1>
<?php if(!$step2):?><p>کد بازیابی ۶ رقمی به <b>ایمیل</b> حساب شما ارسال می‌شود.</p>
<form method="post" class="mt bk-ajax"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="forgot_request"><div class="form-group"><label class="field-label">ایمیل حساب</label><input class="field" dir="ltr" type="email" name="email" placeholder="you@example.com" required></div><div class="bk-ajax-msg"></div><button class="btn btn-primary btn-full">دریافت کد بازیابی</button></form>
<p class="text-center"><a class="check" href="<?=url('login')?>">بازگشت به ورود</a></p>
<?php else:?><p>کد بازیابی به <b dir="ltr"><?=h(bk_mask_email($step2 && isset($_SESSION['pending_reset']['email']) ? $_SESSION['pending_reset']['email'] : ''))?></b> ارسال شد.</p>
<form method="post" class="mt bk-ajax"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="forgot_reset"><div class="form-group"><label class="field-label">کد شش رقمی</label><input class="field" dir="ltr" name="code" maxlength="6" required inputmode="numeric"></div><div class="form-group"><label class="field-label">رمز عبور جدید (حداقل ۶ کاراکتر)</label><input class="field" type="password" dir="ltr" name="password" minlength="6" required></div><div class="bk-ajax-msg"></div><button class="btn btn-primary btn-full">تغییر رمز عبور</button></form>
<form method="post" class="mt bk-ajax"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="forgot_request"><input type="hidden" name="email" value="<?=h($_SESSION['pending_reset']['email'] ?? '')?>"><button class="btn btn-secondary btn-full">📨 ارسال مجدد کد</button><div class="bk-ajax-msg" style="margin-top:8px"></div></form>
<p class="text-center"><a class="check" href="<?=url('login')?>">ورود</a></p>
<?php endif;?></div></div></main><?php footer_html();exit;}

/* ---------- v5.5: صفحهٔ تأیید ایمیل (دروازهٔ خرید/ثبت قلق) ---------- */
if($page==='verify-email'){
    $u=current_user();
    if(!empty($_SESSION['pending_register']))redirect_to('verify');
    if(!$u)redirect_to('login');
    /* ارسال خودکار کد در اولین ورود به صفحه (فقط اگر کد فعال ندارد) */
    $autoSent=false;
    if(!email_verified($u)){
        try{ $hc=db()->prepare("SELECT COUNT(*) FROM email_codes WHERE email=? AND purpose='gate' AND expires_at > NOW()"); $hc->execute([strtolower((string)$u['email'])]);
            if((int)$hc->fetchColumn()===0){ $snd=bk_send_email_code((string)$u['email'],'gate'); $autoSent=!empty($snd['ok']); }
        }catch(Throwable $e){}
    } else { redirect_to(''); }
    $gateBack=$_SESSION['email_gate']['back'] ?? '';
    header_html('تأیید ایمیل');
    $hasEmail = filter_var((string)($u['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    ?><main class="auth-page"><div class="auth-box"><div class="logo">⌁ برد<em>خان</em></div><div class="card auth-card">
        <h1>تأیید ایمیل</h1>
<?php if (!$hasEmail): ?>
        <p>برای <b>خرید یا ثبت قلق</b> ابتدا یک ایمیل تعیین کنید؛ کد تأیید به آن ارسال می‌شود.</p>
        <form method="post" class="mt bk-ajax"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="email_set">
            <div class="form-group"><label class="field-label">ایمیل شما</label><input class="field" dir="ltr" type="email" name="email" placeholder="you@example.com" required></div>
            <div class="bk-ajax-msg"></div>
            <button class="btn btn-primary btn-full">ثبت ایمیل و دریافت کد</button>
        </form>
<?php else: ?>
        <p>برای <b>خرید یا ثبت قلق</b> باید ایمیل خود را تأیید کنید. کد ۶ رقمی به <b dir="ltr"><?=h(bk_mask_email((string)$u['email']))?></b> ارسال شد<?=$autoSent ? ' ✓' : ''?>.</p>
        <?php if ($gateBack): ?><div class="notice" style="font-size:11px">پس از تأیید، به همان صفحه‌ای که بودید برمی‌گردید.</div><?php endif; ?>
        <form method="post" class="mt bk-ajax"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="email_code_verify">
            <div class="form-group"><label class="field-label">کد ۶ رقمی ایمیل‌شده</label><input class="field" dir="ltr" name="code" maxlength="6" placeholder="۱۲۳۴۵۶" required inputmode="numeric"></div>
            <div class="bk-ajax-msg"></div>
            <button class="btn btn-primary btn-full">تأیید ایمیل</button>
        </form>
        <form method="post" class="mt bk-ajax"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="email_code_send"><button class="btn btn-secondary btn-full">📨 ارسال مجدد کد</button><div class="bk-ajax-msg" style="margin-top:8px"></div></form>
<?php endif; ?>
        <p class="text-center" style="font-size:11px"><a class="check" href="<?=url('')?>">بعداً تأیید می‌کنم</a></p>
    </div></div></main><?php footer_html();exit;
}
if(in_array($page,['login','register','verify'],true)){
    header_html($page==='login'?'ورود':($page==='register'?'ثبت‌نام':'تأیید ایمیل'));
    ?><main class="auth-page"><div class="auth-box"><div class="logo">⌁ برد<em>خان</em></div><div class="card auth-card"><?php if($page==='login'):?><h1>ورود به حساب</h1><p>با ایمیل یا شماره موبایل وارد شوید.</p><form method="post" class="mt bk-ajax"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="login"><div class="form-group"><label class="field-label">ایمیل یا موبایل</label><input class="field" dir="ltr" name="identifier" required placeholder="you@example.com یا 0912…"></div><div class="form-group"><label class="field-label">رمز عبور</label><input class="field" type="password" dir="ltr" name="password" required></div><div class="bk-ajax-msg"></div><button class="btn btn-primary btn-full">ورود</button></form><div class="flex between mt" style="font-size:12px"><a class="check" href="<?=url('forgot')?>">🔑 رمز عبور را فراموش کرده‌اید؟</a><a class="check" href="<?=url('register')?>">ثبت‌نام رایگان</a></div><?php elseif($page==='register'):?><h1>ثبت‌نام رایگان</h1><p>با ایمیل ثبت‌نام کنید؛ کد تأیید به ایمیل شما ارسال می‌شود.</p><form method="post" class="mt bk-ajax"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="register"><div class="form-group"><label class="field-label">نام و نام خانوادگی</label><input class="field" name="name" required></div><div class="form-group"><label class="field-label">ایمیل (نام کاربری شما)</label><input class="field" dir="ltr" type="email" name="email" placeholder="you@example.com" required></div><div class="form-group"><label class="field-label">شماره موبایل (اختیاری)</label><input class="field" dir="ltr" name="phone" placeholder="09123456789"></div><div class="form-group"><label class="field-label">رمز عبور حداقل ۶ کاراکتر</label><div style="position:relative"><input id="bkRegPass" class="field" dir="ltr" type="password" name="password" minlength="6" required oninput="bkPassStrength(this.value)"><button type="button" onclick="var i=document.getElementById('bkRegPass');i.type=i.type==='password'?'text':'password';this.textContent=i.type==='password'?'👁':'🙈'" style="position:absolute;inset-inline-end:8px;top:6px;background:none;border:0;cursor:pointer;font-size:16px">👁</button></div><div id="bkPassBar" style="height:5px;border-radius:4px;background:var(--bg-soft);margin-top:6px;overflow:hidden"><i style="display:block;height:100%;width:0;background:#ef4444;transition:all .3s"></i></div><small id="bkPassTxt" class="muted" style="font-size:10px"></small></div><div class="form-group"><label class="field-label">کد معرف اختیاری</label><input class="field" dir="ltr" name="referral" value="<?=h($_GET['ref'] ?? '')?>"></div><div class="bk-ajax-msg"></div><button class="btn btn-primary btn-full">دریافت کد تأیید ایمیل</button></form><p class="text-center">حساب دارید؟ <a class="check" href="<?=url('login')?>">وارد شوید</a></p><?php else:$pending=$_SESSION['pending_register']??null;if(!$pending)redirect_to('register');?><h1>تأیید ایمیل</h1><p>کد تأیید ۶ رقمی به <b dir="ltr"><?=h($pending['email'])?></b> ارسال شد — ایمیل خود (از جمله پوشهٔ Spam) را بررسی کنید.</p><form method="post" class="mt bk-ajax"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="verify"><div class="form-group"><label class="field-label">کد شش رقمی</label><input class="field" dir="ltr" name="code" maxlength="6" required inputmode="numeric"></div><div class="bk-ajax-msg"></div><button class="btn btn-primary btn-full">تأیید و ساخت حساب</button></form><form method="post" class="mt bk-ajax"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="email_code_resend"><button class="btn btn-secondary btn-full">📨 ارسال مجدد کد به ایمیل</button><div class="bk-ajax-msg" style="margin-top:8px"></div></form><?php endif;?></div></div></main><?php footer_html();exit; }

if($page==='upload'){
$u=require_login();
$editId=(int)($_GET['edit']??0);
$editTip=null;
if($editId){$et=db()->prepare('SELECT * FROM tips WHERE id=? LIMIT 1');$et->execute([$editId]);$editTip=$et->fetch();if(!$editTip||(int)$editTip['author_id']!==(int)$u['id']){flash('قلقی برای ویرایش یافت نشد.','error');redirect_to('my-tips');}}
$cats=category_tree();header_html($editTip?'ویرایش قلق':'آپلود قلق');?><main class="wrap page"><div class="page-title"><h1><?=$editTip?'✏️ ویرایش قلق':'آپلود قلق جدید'?></h1><p>راه‌حل واقعی خود را ثبت کنید و پس از تأیید پاداش بگیرید.</p></div><form id="tipForm" class="card auth-card" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="upload_tip"><input type="hidden" name="edit_id" value="<?=h((string)($editTip['id'] ?? 0))?>"><div class="grid grid-2"><div class="form-group"><label class="field-label">عنوان قلق *</label><input class="field" name="title" required placeholder="رفع مشکل روشن نشدن لپ‌تاپ ایسوس X550"></div><div class="form-group"><label class="field-label">دسته‌بندی *</label><input class="field" type="text" placeholder="🔍 جستجوی زندهٔ دسته…" oninput="bkFilterSelect(this)"><select class="field" name="category_id" required><option value="">انتخاب کنید</option><?php foreach($cats as $c):?><optgroup label="<?=h($c['name'])?>"><?php foreach($c['children'] as $ch):?><option value="<?=$ch['id']?>"><?=h($ch['name'])?></option><?php endforeach;?></optgroup><?php endforeach;?></select></div></div><div class="form-group"><label class="field-label">توضیح کوتاه *</label><textarea class="field" name="short_description" rows="2" required></textarea></div><div class="form-group"><label class="field-label">توضیح کامل راه‌حل *</label><textarea class="field" name="description" rows="6" required placeholder="شرح مشکل، تست‌ها و تجربه تعمیر…"></textarea></div><div class="grid grid-2"><div class="form-group"><label class="field-label">نام دستگاه *</label><input class="field" name="device_name" required placeholder="مثلاً لپ‌تاپ، پاور، موبایل"></div><div class="form-group"><label class="field-label">برند *</label><input class="field" name="brand" required placeholder="مثلاً ایسوس"></div></div><div class="form-group"><label class="field-label">حداقل ۱ عکس، حداکثر ۱۰ عکس — همهٔ فرمت‌های تصویری (JPG، PNG، WebP، GIF، BMP، HEIC و…) تا ۱۲MB؛ عکس‌های بزرگ خودکار کوچک می‌شوند</label><input class="field" id="tipImages" type="file" name="images[]" accept="image/*" multiple required><div id="tipPreview" class="file-preview"></div></div>
<div class="form-group"><label class="field-label">📷 عکس‌های مراحل تعمیر (اختیاری) — چند عکس را با هم انتخاب کنید؛ به‌ترتیب به گام‌ها اختصاص می‌یابند</label><input class="field" id="stepImages" type="file" name="step_images[]" accept="image/*" multiple><div id="stepPreview" class="file-preview"></div></div><div class="grid grid-2"><div class="form-group"><label class="field-label">نوع دسترسی</label><select class="field" name="access_type"><option value="free">رایگان</option><option value="like">با لایک</option><option value="paid">پرداختی</option></select></div><div class="form-group"><label class="field-label">قیمت (فقط برای قلق پرداختی)</label><input class="field" type="number" name="price" value="30000"></div></div><details class="bk-optional"><summary>⚙️ گزینه‌های اختیاری (مدل، مراحل تعمیر، ابزار، ویدیو و…)</summary><div class="grid grid-2"><div class="form-group"><label class="field-label">مدل</label><input class="field" name="model"></div><div class="form-group"><label class="field-label">شماره برد</label><input class="field" name="board_number"></div><div class="form-group"><label class="field-label">نوع خرابی</label><input class="field" name="fault_type" placeholder="روشن نمی‌شود"></div><div class="form-group"><label class="field-label">سطح سختی</label><select class="field" name="difficulty"><option value="easy">آسان</option><option value="medium" selected>متوسط</option><option value="hard">سخت</option></select></div></div><div class="form-group"><label class="field-label">مراحل گام‌به‌گام تعمیر (اختیاری)</label><div class="grid grid-2"><input class="field" name="step_title[]" placeholder="عنوان گام اول"><textarea class="field" name="step_body[]" rows="2" placeholder="توضیح گام اول"></textarea><input class="field" name="step_title[]" placeholder="عنوان گام دوم"><textarea class="field" name="step_body[]" rows="2" placeholder="توضیح گام دوم"></textarea></div></div><div class="grid grid-2"><div class="form-group"><label class="field-label">ابزارها (با کاما جدا شوند)</label><input class="field" name="tools" placeholder="مولتی‌متر، هیتر، فلاکس"></div><div class="form-group"><label class="field-label">تگ‌ها (با کاما جدا شوند)</label><input class="field" name="tags" placeholder="ماسفت، پاور"></div></div><div class="grid grid-2"><div class="form-group"><label class="field-label">لینک ویدیو (یوتیوب یا آپارات)</label><input class="field" dir="ltr" name="video_url" placeholder="https://youtube.com/watch?v=..."></div><div class="form-group"><label class="field-label">یا آپلود فایل ویدیو MP4 (تا ۵۰MB)</label><input class="field" type="file" name="video_file" accept="video/mp4"></div></div></details>
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
(function(){var f=document.getElementById('tipForm');if(!f)return;var msg=document.getElementById('tipFormMsg');var bar=document.getElementById('tipBar');var barWrap=document.getElementById('tipBarWrap');var fi=document.getElementById('tipImages');var pv=document.getElementById('tipPreview');if(fi&&pv){fi.addEventListener('change',function(){pv.innerHTML='';Array.prototype.forEach.call(fi.files,function(file){if(!/^image\//.test(file.type||''))return;var u=URL.createObjectURL(file);var img=document.createElement('img');img.src=u;img.alt='پیش‌نمایش';img.style.cssText='width:86px;height:86px;object-fit:cover;border-radius:10px;border:1px solid var(--line)';pv.appendChild(img);});});}
var sfi=document.getElementById('stepImages');var spv=document.getElementById('stepPreview');if(sfi&&spv){sfi.addEventListener('change',function(){spv.innerHTML='';var n=0;Array.prototype.forEach.call(sfi.files,function(file){if(!/^image\//.test(file.type||''))return;n++;var u=URL.createObjectURL(file);var w=document.createElement('div');w.style.cssText='position:relative;width:86px;height:86px;flex:none';var img=document.createElement('img');img.src=u;img.alt='مرحله '+n;img.style.cssText='width:86px;height:86px;object-fit:cover;border-radius:10px;border:1px solid var(--line)';var tag=document.createElement('b');tag.textContent='گام '+n;tag.style.cssText='position:absolute;bottom:4px;right:6px;background:rgba(7,134,89,.9);color:#fff;font-size:9px;padding:1px 7px;border-radius:8px';w.appendChild(img);w.appendChild(tag);spv.appendChild(w);});spv.style.display='flex';spv.style.gap='8px';spv.style.flexWrap='wrap';});}f.addEventListener('submit',function(e){var eid=f.querySelector('input[name=edit_id]');if(eid&&eid.value&&eid.value!=='0'){return;}e.preventDefault();var b=f.querySelector('button');var orig=b?b.textContent:'';if(b){b.disabled=true;b.textContent='⏳ در حال ارسال…';}msg.innerHTML='';if(barWrap)barWrap.style.display='block';if(bar){bar.style.width='0%';bar.textContent='0%';}var xhr=new XMLHttpRequest();xhr.open('POST',window.location.href);xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');xhr.setRequestHeader('Accept','application/json');xhr.upload.addEventListener('progress',function(ev){if(ev.lengthComputable&&bar){var p=Math.round(ev.loaded/ev.total*100);bar.style.width=p+'%';bar.textContent=p+'%';}});xhr.onload=function(){if(barWrap)barWrap.style.display='none';var j=null;try{j=JSON.parse(xhr.responseText);}catch(_){}if(j&&j.ok){msg.innerHTML='<div class="notice" style="margin-top:12px">✅ '+(j.message||'انجام شد')+'</div>';if(j.redirect){setTimeout(function(){window.location.href=j.redirect;},1200);}else if(b){b.disabled=false;b.textContent=orig;}}else{msg.innerHTML='<div class="notice error" style="margin-top:12px">⚠️ '+((j&&j.error)||((xhr.responseText||'').slice(0,180))||'پاسخی از سرور دریافت نشد؛ دوباره تلاش کنید.')+'</div>';if(b){b.disabled=false;b.textContent=orig;}}};xhr.onerror=function(){if(barWrap)barWrap.style.display='none';msg.innerHTML='<div class="notice error" style="margin-top:12px">⚠️ خطای ارتباط با سرور؛ دوباره تلاش کنید.</div>';if(b){b.disabled=false;b.textContent=orig;}};xhr.send(new FormData(f));});})();
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
if($page==='notifications'){$u=require_login();$items=db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100');$items->execute([(int)$u['id']]);$items=$items->fetchAll();db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([(int)$u['id']]);header_html('اعلان‌ها');?><main class="wrap page"><div class="page-title"><h1>اعلان‌ها</h1></div><div class="card"><?php foreach($items as $n):?><a class="leader-row" href="<?=h($n['link']?:'#')?>"><span class="grow"><strong><?=h($n['title'])?></strong><small><?=h($n['body'])?> · <?=ago($n['created_at'])?></small></span></a><?php endforeach;?><?php if(!$items):?><div class="empty">اعلانی ندارید.</div><?php endif;?></div></main><?php footer_html();exit;}

if($page==='settings'){$u=require_login();header_html('تنظیمات');?><main class="wrap page"><div class="page-title"><h1>تنظیمات حساب</h1></div><div class="card auth-card"><h3>پروفایل</h3><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="profile_update"><div class="form-group"><label class="field-label">نام</label><input class="field" name="name" value="<?=h($u['name'])?>"></div><div class="form-group"><label class="field-label">بیوگرافی</label><textarea class="field" name="bio" rows="4"><?=h($u['bio'])?></textarea></div><button class="btn btn-primary">ذخیره</button></form></div>
<div class="card auth-card" style="margin-top:16px"><h3>🖥 نشست‌های فعال (دستگاه‌های واردشده)</h3>
<?php $sess=[]; try{ $sq=$pdo->prepare('SELECT id,sid_hash,ip,agent,created_at FROM user_sessions WHERE user_id=? ORDER BY id DESC LIMIT 20'); $sq->execute([(int)$u['id']]); $sess=$sq->fetchAll(); }catch(Throwable $e){} ?>
<?php if(!$sess): ?><p class="muted" style="font-size:12px">اطلاعاتی نیست.</p><?php else: ?>
<div class="table-wrap"><table class="bk-table"><thead><tr><th>دستگاه</th><th>IP</th><th>شروع نشست</th><th></th></tr></thead><tbody>
<?php $curHash=bk_sid_hash(); foreach($sess as $srow): $cur = ($srow['sid_hash']??'')===$curHash; ?>
<tr><td style="max-width:230px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?=h($srow['agent'])?>"><?=h(mb_substr((string)$srow['agent'],0,60))?:'نامشخص'?></td><td dir="ltr" class="muted"><?=h($srow['ip'])?></td><td class="muted"><?=ago($srow['created_at'])?></td>
<td><?php if($cur): ?><span class="pill green">این دستگاه</span><?php else: ?><form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="session_kill"><input type="hidden" name="sid" value="<?=$srow['id']?>"><button class="btn btn-sm btn-danger">خروج</button></form><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<form method="post" class="mt"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="logout_all"><button class="btn btn-secondary btn-sm">🚪 خروج از همهٔ دستگاه‌های دیگر</button></form>
<?php endif; ?>
</div></main><?php footer_html();exit;}

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
.reel-media .reel-slide{position:absolute;inset:0;display:none;align-items:center;justify-content:center}
.reel-media .reel-slide.active{display:flex}
.reel-media .reel-slide img{width:100%;height:100%;object-fit:cover;user-select:none;-webkit-user-drag:none}
.reel-media .reel-slide img.bk-blur{filter:blur(18px) brightness(.7) scale(1.06)}
.reel-media .reel-slide video{width:100%;height:100%;object-fit:contain;background:#000}
.reel-media .reel-slide iframe{width:100%;height:100%;border:0;background:#000}
.reel-media .reel-slide .vid-poster{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;cursor:pointer;background:rgba(0,0,0,.3);z-index:2}
.reel-media .reel-slide .vid-poster .play-ic{width:72px;height:72px;border-radius:50%;background:rgba(0,0,0,.6);border:2px solid rgba(255,255,255,.9);color:#fff;font-size:28px;display:grid;place-items:center;backdrop-filter:blur(4px)}
.reel-media .reel-slide .vid-poster span{color:#fff;font-size:12px;font-weight:700}
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
    // تشخیص نوع ویدیو
    $rawVideo=trim($t['video_url']??'');
    $videoType=''; $videoSrc=''; $videoEmbed='';
    if($rawVideo!==''){
        if(preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{6,})~',$rawVideo,$vm)){
            $videoType='youtube'; $videoEmbed='https://www.youtube.com/embed/'.$vm[1];
        }elseif(preg_match('~aparat\.com/v/([\w-]+)~',$rawVideo,$vm)){
            $videoType='aparat'; $videoEmbed='https://www.aparat.com/video/video/embed/videohash/'.$vm[1].'/vt/frame';
        }else{
            $vp=ltrim($rawVideo,'/');
            if(str_starts_with($vp,'uploads/')||str_starts_with($vp,'/uploads/')){
                $videoType='local'; $videoSrc=media_url($vp,'vid',$tid,$u?(int)$u['id']:0);
            }elseif(preg_match('#^https?://#i',$rawVideo)){
                $videoType='local'; $videoSrc=$rawVideo;
            }
        }
    }
    $hasVideo = !$locked && $videoType!=='';
    $videoPoster = $fullUrls[0] ?? ($thumbUrls[0] ?? '');
?>
<div class="reel" id="reel-<?=$tid?>" data-id="<?=$tid?>" data-access="<?=h($t['access_type'])?>" data-price="<?=(int)$t['price']?>" data-locked="<?=$locked?'1':'0'?>" data-liked="<?=$liked?'1':'0'?>" data-index="0">
  <div class="reel-media">
    <?php if($firstDisplay): ?>
      <?php // اسلایدهای تصویری ?>
      <?php foreach($displayUrls as $k=>$imgUrl): ?>
        <div class="reel-slide<?=$k===0?' active':''?>" data-slide="<?=$k?>">
          <img src="<?=h($imgUrl)?>" alt="<?=h($t['title'])?>" draggable="false" class="<?=$locked?'bk-blur':''?>" loading="<?= ($k===0 && $tid===(int)$items[0]['id']) ? 'eager' : 'lazy' ?>">
        </div>
      <?php endforeach; ?>
      <?php // اسلاید ویدیو (فقط برای قلق‌های باز) ?>
      <?php if($hasVideo): ?>
        <div class="reel-slide" data-slide="<?=count($displayUrls)?>" data-has-video="1">
          <?php if($videoType==='youtube'||$videoType==='aparat'): ?>
            <iframe src="<?=h($videoEmbed)?>" allowfullscreen loading="lazy" allow="autoplay; encrypted-media"></iframe>
          <?php elseif($videoType==='local'): ?>
            <video playsinline preload="none" poster="<?=h($videoPoster)?>" data-src="<?=h($videoSrc)?>"></video>
            <div class="vid-poster" data-play-video>
              <div class="play-ic">▶</div>
              <span>پخش ویدیو</span>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php $totalSlides = count($displayUrls) + ($hasVideo ? 1 : 0); ?>
      <?php if($totalSlides>1): ?>
        <div class="reel-dots"><?php for($di=0;$di<$totalSlides;$di++): ?><i class="<?=$di===0?'on':''?>"></i><?php endfor; ?></div>
      <?php endif; ?>
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
// تعویض اسلاید با کلیک روی رسانه (تصویر/ویدیو)
document.addEventListener('click',function(e){
  var slide=e.target.closest('.reel-slide');
  if(!slide) return;
  var reel=slide.closest('.reel');
  if(!reel || reel.dataset.locked==='1') return;
  // اگر روی دکمه پخش ویدیو کلیک شده، ویدیو را پخش کن
  if(e.target.closest('[data-play-video]')){
    var vid=slide.querySelector('video');
    if(vid){
      if(!vid.src && vid.dataset.src) vid.src=vid.dataset.src;
      vid.play().catch(function(){});
      var poster=slide.querySelector('.vid-poster');
      if(poster) poster.style.display='none';
    }
    return;
  }
  // اگر روی iframe کلیک شده، کاری نکن
  if(e.target.tagName==='IFRAME') return;
  // رفتن به اسلاید بعدی
  var slides=reel.querySelectorAll('.reel-slide');
  if(slides.length<2) return;
  var curIdx=parseInt(reel.dataset.index||'0');
  var next=(curIdx+1)%slides.length;
  reel.dataset.index=next;
  slides.forEach(function(s,i){s.classList.toggle('active',i===next);});
  updateDots(reel,next);
  // اگر اسلاید جدید ویدیو دارد، توقف ویدیوهای قبلی
  reel.querySelectorAll('.reel-slide video').forEach(function(v){if(!v.closest('.active')){v.pause();}});
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
  header_html('آموزش و سوالات پرتکرار');
  $faq = [
    ['چطور ثبت‌نام کنم؟','فرم ثبت‌نام را با «ایمیل» پر کنید؛ یک کد ۶ رقمی به ایمیل شما ارسال می‌شود. کد را در صفحهٔ تأیید وارد کنید تا حساب ساخته شود. شماره موبایل اختیاری است.'],
    ['چرا باید ایمیلم را تأیید کنم؟','برای خرید قلق یا ثبت قلق جدید، یک‌بار باید ایمیل خود را تأیید کنید (کد به ایمیل ارسال می‌شود). این کار امنیت حساب و خرید شما را تضمین می‌کند و فقط یک‌بار انجام می‌شود.'],
    ['با ایمیل وارد شدم نمی‌شوم؟','در صفحهٔ ورود، همان فیلد هم ایمیل و هم شماره موبایل را می‌پذیرد. اگر رمز را فراموش کرده‌اید از «رمز عبور را فراموش کرده‌اید؟» استفاده کنید.'],
    ['قلق چیست و چطور بخرم؟','«قلق» راه‌حل تست‌شدهٔ یک خرابی конкретی است. وارد صفحهٔ قلق شوید و دکمهٔ «پرداخت و باز کردن» را بزنید؛ مبلغ از کیف پول شما کسر می‌شود و تمام عکس‌ها، ویدیو و مراحل نمایش داده می‌شود.'],
    ['چطور کیف پول را شارژ کنم؟','از صفحهٔ کیف پول، با درگاه آنلاین (زرین‌پال/آیدی‌پی/زیبال) یا کارت‌به‌کارت با آپلود فیش. پس از تأیید، مبلغ به کیف پول اضافه می‌شود.'],
    ['برای آپلود قلق پاداش دارم؟','بله؛ برای هر قلق تأییدشده پاداش آپلود دریافت می‌کنید و اگر قلق شما خریداری شود، درآمد به کیف پولتان واریز می‌شود.'],
    ['قلق من تأیید نشد، چرا؟','قلق‌ها باید واقعی، تست‌شده و با عکس/ویدیوی مرتبط باشند. علت رد شدن از پنل «قلق‌های من» قابل مشاهده است؛ پس از اصلاح دوباره ارسال کنید.'],
    ['سهم من از فروش قلق چقدر است؟','کمیسیون سایت از تنظیمات مدیریت خوانده می‌شود؛ بقیهٔ مبلغ به‌طور کامل به کیف پول شما واریز می‌شود و از حداقل تسویه که بیشتر شود می‌توانید برداشت کنید.'],
    ['چطور فروشندهٔ برد شوم؟','از صفحهٔ «درخواست فروشندگی» توضیح تخصص خود را بنویسید؛ پس از تأیید مدیر می‌توانید برد ثبت کنید. وجه خریدها نزد سایت امانت می‌ماند تا تحویل تأیید شود.'],
    ['پشتیبانی چطور پاسخ می‌دهد؟','از بخش «تیکت پشتیبانی» با اولویت و دستهٔ موردنظر تیکت بزنید؛ کارشناسان در سریع‌ترین زمان پاسخ می‌دهند.'],
  ];
  ?>
  <main class="wrap page">
    <div class="page-title">
      <h1>🎓 آموزش استفاده از بردخان</h1>
      <p>راهنمای گام‌به‌گام استفاده از امکانات سایت + پاسخ سوالات پرتکرار</p>
    </div>

    <div class="grid grid-3">
      <?php foreach ([
        ['۱️⃣','ثبت‌نام و تأیید ایمیل','ایمیل و رمز را وارد کنید ← کد ۶ رقمی از ایمیل را در صفحهٔ تأیید بزنید ← حساب آماده است.'],
        ['2️⃣','شارژ کیف پول','کیف پول ← «شارژ با درگاه» یا کارت‌به‌کارت ← مبلغ پس از تأیید قابل استفاده است.'],
        ['3️⃣','خرید قلق','صفحهٔ قلق ← «پرداخت و باز کردن» ← در اولین بار تأیید ایمیل انجام می‌شود ← محتوای کامل باز می‌شود.'],
        ['4️⃣','ثبت قلق جدید','منوی «آپلود قلق» ← عنوان، دسته، عکس/ویدیو و مراحل را پر کنید ← پس از تأیید مدیر منتشر و پاداش می‌گیرید.'],
        ['5️⃣','درخواست تعمیر','اگر راه‌حلی پیدا نکردید «درخواست تعمیر» ثبت کنید تا تعمیرکاران پاسخ دهند؛ بهترین پاسخ پاداش می‌گیرد.'],
        ['6️⃣','تیکت پشتیبانی','برای مشکلات حساب، پرداخت یا سفارش، تیکت با اولویت مناسب بزنید و پیگیری کنید.'],
      ] as $g): ?>
      <div class="card" style="padding:16px">
        <div style="font-size:24px"><?=$g[0]?></div>
        <h3 style="font-size:13.5px;margin:6px 0"><?=h($g[1])?></h3>
        <p style="font-size:11.5px;color:var(--text-soft);line-height:2"><?=h($g[2])?></p>
      </div>
      <?php endforeach; ?>
    </div>

    <section class="section" id="faq" style="margin-top:26px">
      <div class="section-head"><h2>❓ سوالات پرتکرار</h2></div>
      <div class="card" style="padding:6px 18px">
        <?php foreach ($faq as $q): ?>
        <details style="padding:14px 0;border-bottom:1px solid var(--line)"><summary style="cursor:pointer;font-weight:900;font-size:13.5px;color:var(--text)"><?=h($q[0])?></summary><p class="muted" style="font-size:12px;margin:12px 0 0;line-height:2.2"><?=h($q[1])?></p></details>
        <?php endforeach; ?>
      </div>
    </section>

    <div class="card mt" style="padding:16px;text-align:center">
      <b>سوال دیگری دارید؟</b>
      <p class="muted" style="font-size:12px;margin:6px 0 12px">تیم پشتیبانی بردخان پاسخگوی شماست.</p>
      <a class="btn btn-primary btn-sm" href="<?=url('tickets')?>">✉ ثبت تیکت پشتیبانی</a>
      <a class="btn btn-secondary btn-sm" href="<?=url('contact')?>">تماس با ما</a>
    </div>
  </main>
  <?php footer_html();exit; }
header_html('صفحه پیدا نشد');?><main class="wrap page"><div class="card empty"><h1>صفحه پیدا نشد</h1><a class="btn btn-primary mt" href="<?=url()?>">بازگشت به خانه</a></div></main><?php footer_html();
