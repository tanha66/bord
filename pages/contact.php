<?php
/* Bordkhan — تماس با ما + فرم ثبت پیام (included from index.php; helpers in scope). */
$s = settings();
$u = current_user();
$error = '';
$done  = false;
$contactEnabled = (int)($s['contact_form_enabled'] ?? 0) === 1;

/* ---------- form submit (فقط وقتی ماژول فعال است) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'contact_send') {
    if (function_exists('check_csrf')) check_csrf();
    if (!$contactEnabled) { flash('بخش پیام‌ها موقتاً غیرفعال است؛ از تیکت پشتیبانی استفاده کنید.', 'error'); redirect_to('tickets'); }
    if (!empty($_POST['website'])) { http_response_code(403); exit; } // honeypot ضد ربات
    $name    = function_exists('clean_text') ? clean_text($_POST['name'] ?? '') : trim(strip_tags($_POST['name'] ?? ''));
    $email   = trim($_POST['email'] ?? '');
    $phone   = function_exists('clean_text') ? clean_text($_POST['phone'] ?? '') : trim(strip_tags($_POST['phone'] ?? ''));
    $subject = function_exists('clean_text') ? clean_text($_POST['subject'] ?? '') : trim(strip_tags($_POST['subject'] ?? ''));
    $body    = trim($_POST['body'] ?? '');
    if (mb_strlen($name) < 2 || mb_strlen($subject) < 3 || mb_strlen($body) < 5) {
        $error = 'نام، موضوع و متن پیام را کامل وارد کنید.';
    } elseif ($email === '' && $phone === '') {
        $error = 'برای پاسخ‌گویی، ایمیل یا شماره تماس معتبر وارد کنید.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'ایمیل واردشده معتبر نیست.';
    } else {
        $pdo = db();
        try {
            $pdo->prepare('INSERT INTO contact_messages(user_id,name,email,phone,subject,body) VALUES(?,?,?,?,?,?)')
                ->execute([$u ? (int)$u['id'] : null, $name, $email !== '' ? $email : null, $phone !== '' ? $phone : null, $subject, $body]);
            $admins = $pdo->query("SELECT id FROM users WHERE role IN ('admin','superadmin')")->fetchAll();
            foreach ($admins as $a) notify_user((int)$a['id'], 'system', 'پیام تماس جدید', $subject . ' — ' . $name, url('admin', ['tab' => 'contact']));
            $done = true;
        } catch (Throwable $e) {
            $error = 'ثبت پیام انجام نشد؛ لطفاً از تیکت پشتیبانی استفاده کنید.';
        }
    }
}

$emailRow  = trim((string)($s['contact_email'] ?? ''));
$phoneRow  = trim((string)($s['contact_phone'] ?? ''));
$telegram  = trim((string)($s['contact_telegram'] ?? ''));
$instagram = trim((string)($s['contact_instagram'] ?? ''));
$address   = trim((string)($s['contact_address'] ?? ''));
$hasInfo   = ($emailRow !== '' || $phoneRow !== '' || $telegram !== '' || $instagram !== '' || $address !== '' || !empty($s['contact_text']));

header_html('تماس با ما');
?>
<main class="wrap page">
  <div class="page-title">
    <h1>تماس با ما</h1>
    <p>سؤال، پیشنهاد یا گزارش مشکل؟ پیام بگذارید؛ در سریع‌ترین زمان پاسخ می‌دهیم.</p>
  </div>

  <?php if ($done): ?>
    <div class="notice" style="margin-bottom:16px">✅ پیام شما ثبت شد و برای تیم پشتیبانی ارسال گردید. از طریق ایمیل/تلفنی که وارد کردید با شما تماس می‌گیریم.</div>
  <?php elseif ($error !== ''): ?>
    <div class="notice error" style="margin-bottom:16px"><?=h($error)?></div>
  <?php endif; ?>

  <div class="grid grid-2">
    <section class="card auth-card">
      <h3>📨 فرم تماس</h3>
      <?php if (!$contactEnabled): ?>
        <div class="notice mt">بخش پیام‌ها در حال حاضر غیرفعال است. برای ارتباط با ما از <a class="check" href="<?=url('tickets')?>">تیکت پشتیبانی</a> استفاده کنید.</div>
      <?php elseif (!$done): ?>
      <form method="post" class="mt">
        <input type="hidden" name="csrf" value="<?=function_exists('csrf') ? csrf() : ''?>">
        <input type="hidden" name="action" value="contact_send">
        <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div class="grid grid-2">
          <div class="form-group"><label class="field-label">نام و نام خانوادگی *</label><input class="field" name="name" value="<?=h($u['name'] ?? '')?>" required></div>
          <div class="form-group"><label class="field-label">شماره تماس</label><input class="field" dir="ltr" name="phone" value="<?=h($u['phone'] ?? '')?>" placeholder="0912…"></div>
        </div>
        <div class="form-group"><label class="field-label">ایمیل</label><input class="field" dir="ltr" type="email" name="email" value="<?=h($u['email'] ?? '')?>" placeholder="you@example.com"></div>
        <div class="form-group"><label class="field-label">موضوع *</label><input class="field" name="subject" required placeholder="مثلاً: مشکل در پرداخت"></div>
        <div class="form-group"><label class="field-label">متن پیام *</label><textarea class="field" name="body" rows="6" required placeholder="توضیح کامل مشکل یا پیشنهاد شما…"></textarea></div>
        <button class="btn btn-primary btn-full mt">ارسال پیام</button>
      </form>
      <?php else: ?>
        <div class="mt"><a class="btn btn-secondary" href="<?=url('contact')?>">ارسال پیام جدید</a></div>
      <?php endif; ?>
    </section>

    <section class="card auth-card">
      <h3>🗺 راه‌های ارتباطی</h3>
      <?php if ($hasInfo): ?>
        <?php if (!empty($s['contact_text'])): ?><div class="rich"><?=nl2br(h($s['contact_text']))?></div><?php endif; ?>
        <div class="info-list mt">
          <?php if ($emailRow !== ''): ?><div><span>ایمیل پشتیبانی</span><b dir="ltr"><?=h($emailRow)?></b></div><?php endif; ?>
          <?php if ($phoneRow !== ''): ?><div><span>تلفن</span><b dir="ltr"><?=h($phoneRow)?></b></div><?php endif; ?>
          <?php if ($telegram !== ''): ?><div><span>تلگرام</span><b dir="ltr"><?=h($telegram)?></b></div><?php endif; ?>
          <?php if ($instagram !== ''): ?><div><span>اینستاگرام</span><b dir="ltr"><?=h($instagram)?></b></div><?php endif; ?>
          <?php if ($address !== ''): ?><div><span>آدرس</span><b><?=h($address)?></b></div><?php endif; ?>
        </div>
      <?php else: ?>
        <p class="muted">اطلاعات تماس به‌زودی در این بخش قرار می‌گیرد. تا آن زمان از فرم کناری استفاده کنید.</p>
      <?php endif; ?>
      <div class="card mt" style="background:rgba(7,134,89,.08)">
        <h4 style="margin-bottom:8px">✉ پاسخ‌گویی سریع‌تر از طریق تیکت</h4>
        <p class="muted" style="font-size:12px">اگر عضو سایت هستید، تیکت پشتیبانی بهترین راه است؛ وضعیت پاسخ را همین‌جا پیگیری کنید.</p>
        <a class="btn btn-secondary btn-sm mt" href="<?=url('tickets')?>">ثبت تیکت پشتیبانی</a>
      </div>
    </section>
  </div>
</main>
<?php footer_html(); exit;
