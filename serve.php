<?php
/**
 * Media proxy: serves images/videos only for users who have unlocked the tip.
 * Blocks direct hotlinks and returns 403 if not authorized. Also applies watermark for paid/like content.
 */
require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$type = $_GET['t'] ?? '';
$id   = (int)($_GET['id'] ?? 0);
$file = trim($_GET['f'] ?? '');
$nonce = trim($_GET['n'] ?? '');

if ($type !== 'img' && $type !== 'vid' && $type !== 'thumb') { http_response_code(400); exit('bad'); }
if ($file === '' || $id === 0) { http_response_code(400); exit('bad'); }

$base = realpath(UPLOAD_DIR);
$full = realpath(UPLOAD_DIR . '/' . basename($file));
if (!$full || !str_starts_with($full, $base) || !is_file($full)) { http_response_code(404); exit('not found'); }

$pdo = db();
$tip = $pdo->prepare('SELECT id,author_id,access_type,price,status FROM tips WHERE id=? LIMIT 1');
$tip->execute([$id]);
$t = $tip->fetch();
if (!$t) { http_response_code(404); exit('tip not found'); }

if ($t['status'] !== 'published' && (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] !== (int)$t['author_id'])) {
    http_response_code(404); exit;
}

// Nonce validation: must be signed for this user+file.
$expected = hash_hmac('sha256', ($_SESSION['user_id']??'guest')."|".$file.'|'.$type.'|'.$id, INSTALL_KEY);
if (!hash_equals($expected, $nonce)) { http_response_code(403); exit('forbidden'); }

// helper fallback برای fa (در config.php تعریف نشده، ولی در index.php هست)
if (!function_exists('fa')) {
    function fa($value): string { $digits = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹']; return strtr((string)$value, array_combine(range(0,9), $digits)); }
}
if (!function_exists('h')) {
    function h($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'); }
}

$u = null;
if (!empty($_SESSION['user_id'])) {
  $q = $pdo->prepare('SELECT id, role, premium_until, balance FROM users WHERE id=? LIMIT 1');
  $q->execute([(int)$_SESSION['user_id']]);
  $u = $q->fetch();
}

$isAuthor = $u && (int)$u['id'] === (int)$t['author_id'];
$isStaff  = false;
if ($u && in_array($u['role'] ?? 'member', ['moderator','admin','superadmin'], true)) $isStaff = true;
$premiumOk = $u && $u['premium_until'] && strtotime($u['premium_until']) > time();
$hasAccess = false;
if ($isAuthor || $isStaff || $t['access_type'] === 'free' || $premiumOk) $hasAccess = true;
if ($u) {
  $ex = $pdo->prepare('SELECT id FROM tip_accesses WHERE tip_id=? AND user_id=? LIMIT 1');
  $ex->execute([$id, (int)$u['id']]);
  if ($ex->fetch()) $hasAccess = true;
}

// For thumbnails we always serve a small blurred preview (2 images already shown to non-payers).
if ($type === 'thumb') {
  $tm = strtolower((string)file_mime($full));
  if ($tm === '') { $tm = 'image/jpeg'; } /* حدس امن */
  header('Content-Type: ' . $tm);
  header('Cache-Control: private, max-age=600');
  header('X-Robots-Tag: noindex');
  if ($tm === 'image/svg+xml') header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
  readfile($full); exit;
}

if (!$hasAccess) { http_response_code(403); exit('forbidden'); }

// Record (or reuse) media access token row for forensic watermark.
// v5.11: اگر جدول media_access روی نصب‌های قدیمی وجود نداشته باشد، این INSERT
// نباید سرو رسانه (مخصوصاً ویدیو) را با Fatal Error از کار بیندازد.
$userId = $u ? (int)$u['id'] : 0;
$path = '/uploads/'.basename($file);
try {
    $pdo->prepare("INSERT INTO media_access(user_id,media_type,path,nonce,ip) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE created_at=created_at")
        ->execute([$userId, $type, $path, $nonce, $_SERVER['REMOTE_ADDR'] ?? '']);
} catch (Throwable $e) {}

$mime = file_mime($full);
/* v5.11: ویدیوهای آپلودی همیشه MP4 هستند (save_video پسوند .mp4 می‌سازد)، پس نوع
   را صریح video/mp4 سرو می‌کنیم تا MIME ناشناخته/غلط (octet-stream/quicktime/خالی)
   با nosniff مانع پخش روی مرورگر نشود */
if ($type === 'vid') { $mime = 'video/mp4'; }
if ($type === 'vid' || ($mime && str_starts_with($mime, 'video/'))) {
  $size = filesize($full);
  header('Content-Type: '.$mime);
  header('Content-Disposition: inline; filename="bordkhan_'.basename($file).'"');
  header('Cache-Control: private, no-store');
  header('X-Content-Type-Options: nosniff');
  header('Accept-Ranges: bytes');
  // HTTP Range support for seeking
  if (isset($_SERVER['HTTP_RANGE'])) {
    if (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
      $start = $m[1] === '' ? 0 : (int)$m[1];
      $end = $m[2] === '' ? $size - 1 : (int)$m[2];
      $end = min($end, $size - 1);
      if ($start > $end || $start >= $size) { header('HTTP/1.1 416 Range Not Satisfiable', true, 416); header('Content-Range: bytes */'.$size); exit; }
      header('HTTP/1.1 206 Partial Content', true, 206);
      header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);
      header('Content-Length: '.($end - $start + 1));
      $fp = fopen($full, 'rb'); fseek($fp, $start);
      $remaining = $end - $start + 1;
      while ($remaining > 0 && !feof($fp)) { $chunk = fread($fp, min(8192, $remaining)); if ($chunk === false) break; echo $chunk; $remaining -= strlen($chunk); }
      fclose($fp); exit;
    }
  }
  header('Content-Length: '.$size);
  readfile($full); exit;
}

/* v5.9: واترمارک از تنظیمات مدیر — خاموش/روشن + متن دلخواه */
$wmOn = true; $wmText = '';
try {
  $st = $pdo->query('SELECT watermark_enabled, watermark_text FROM settings WHERE id=1 LIMIT 1');
  if ($st) { $sr = $st->fetch(); if ($sr) { $wmOn = (int)$sr['watermark_enabled'] === 1; $wmText = trim((string)$sr['watermark_text']); } }
} catch (Throwable $e) {}
if ($wmText === '') $wmText = 'بردخان — کپی غیرمجاز ممنوع';
if (!$wmOn) {
  header('Content-Type: image/jpeg');
  header('Cache-Control: private, no-store');
  header('X-Robots-Tag: noindex');
  header('Content-Length: ' . filesize($full));
  readfile($full); exit;
}

// Image: apply visual watermark with user id for screenshots.
/* v5.8: فرمت‌های غیر JPEG-compatible (gif/svg/avif/heic/tiff) مستقیم و با نوع درست سرو می‌شوند */
$rawMimes = ['image/gif','image/avif','image/heic','image/heif','image/svg+xml','image/tiff','image/x-ms-bmp'];
if ($mime === '' || in_array(strtolower((string)$mime), $rawMimes, true)) {
  $sm = strtolower((string)$mime);
  if ($sm === '') {
    $extMime = ['gif'=>'image/gif','avif'=>'image/avif','heic'=>'image/heic','heif'=>'image/heif','svg'=>'image/svg+xml','tif'=>'image/tiff','tiff'=>'image/tiff','bmp'=>'image/bmp'];
    $sm = $extMime[strtolower(pathinfo($full, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
  }
  header('Content-Type: ' . $sm);
  header('Content-Disposition: inline; filename="bordkhan_' . basename($file) . '"');
  header('Cache-Control: private, no-store');
  header('X-Content-Type-Options: nosniff');
  header('X-Robots-Tag: noindex');
  if ($sm === 'image/svg+xml') header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
  header('Content-Length: ' . filesize($full));
  readfile($full); exit;
}
header('Content-Type: image/jpeg');
header('Cache-Control: private, no-store, must-revalidate');
header('X-Robots-Tag: noindex');

if (function_exists('imagecreatefromjpeg')) {
  $src = null;
  if ($mime === 'image/png') $src = @imagecreatefrompng($full);
  elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($full);
  else $src = @imagecreatefromjpeg($full);
  if ($src !== false && $src !== null) {
    $w = imagesx($src); $h = imagesy($src);
    $alpha = imagecolorallocatealpha($src, 255, 255, 255, 85);
    $userLabel = $u ? 'کاربر '.fa((string)$u['id']) : 'مهمان';
    $urlLabel = parse_url(SITE_URL, PHP_URL_HOST) ?: 'bordkhan';
    $font = 3;
    $txt = '© '.$urlLabel.' — '.$userLabel;
  $badge = $wmText; /* v5.9: متن واترمارک از پنل */
    // Tiled watermark across the image (single pass, no per-stamp rotation)
    $stepY = 120; $stepX = 260;
    for ($y = 40; $y < $h; $y += $stepY) {
      for ($x = -120; $x < $w; $x += $stepX) {
        imagestring($src, $font, $x, $y, $txt, $alpha);
      }
    }
    // Corner badge
    imagestring($src, 2, max(10,$w-260), max(10,$h-22), $badge, imagecolorallocatealpha($src, 255, 80, 80, 20));
    imagejpeg($src, null, 82);
    imagedestroy($src); exit;
  }
}
// Fallback if GD is disabled on the host: serve raw but no-store.
readfile($full);
