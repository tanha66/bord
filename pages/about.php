<?php
/* Bordkhan — درباره ما (included from index.php; helpers in scope). */
$s = settings();
$totalTips  = (int)db()->query("SELECT COUNT(*) FROM tips WHERE status='published'")->fetchColumn();
$totalUsers = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalBoards= 0;
try { $totalBoards = (int)db()->query("SELECT COUNT(*) FROM boards WHERE status='approved'")->fetchColumn(); } catch (Throwable $e) {}
header_html('درباره ما');
?>
<main class="wrap page">
  <div class="page-title">
    <h1>درباره بردخان</h1>
    <p>بازار تخصصی قلق‌های تعمیراتی بردهای الکترونیکی</p>
  </div>

  <section class="card auth-card">
    <div class="rich"><?=nl2br(h($s['about_text'] ?: 'بردخان بازار تخصصی قلق‌های تعمیراتی بردهای الکترونیکی است؛ جایی برای به‌اشتراک‌گذاری دانش واقعی تعمیرکاران و کسب درآمد از تجربه‌ی آن‌ها. ما باور داریم دانش تعمیرات نباید در کشوی هر تعمیرکار خاک بخورد؛ بلکه باید در دسترس همه باشد تا هر برد الکترونیکی یک بار دیگر زنده شود.'))?></div>
  </section>

  <div class="stat-grid">
    <div class="card stat-card"><strong><?=fa($totalTips)?></strong><small>قلق منتشرشده</small></div>
    <div class="card stat-card"><strong><?=fa($totalUsers)?></strong><small>تعمیرکار و عضو</small></div>
    <div class="card stat-card"><strong><?=fa($totalBoards)?></strong><small>برد فعال در فروشگاه</small></div>
  </div>

  <section class="section">
    <div class="section-head"><h2>بردخان چطور کار می‌کند؟</h2></div>
    <div class="grid grid-3">
      <div class="card auth-card"><h3>۱. قلق ثبت کنید</h3><p class="muted" style="font-size:13px">راه‌حل‌های واقعی تعمیر برد را با عکس و توضیح گام‌به‌گام ثبت کنید؛ رایگان، با لایک یا پرداختی.</p></div>
      <div class="card auth-card"><h3>۲. تأیید و انتشار</h3><p class="muted" style="font-size:13px">کارشناسان بردخان محتوا را بررسی می‌کنند تا فقط راه‌حل‌های قابل‌اعتماد منتشر شود.</p></div>
      <div class="card auth-card"><h3>۳. کسب درآمد</h3><p class="muted" style="font-size:13px">از فروش هر قلق، پاداش آپلود و برنامه معرفی دوستان درآمد کسب کنید.</p></div>
      <div class="card auth-card"><h3>۴. فروشگاه برد</h3><p class="muted" style="font-size:13px">بردهای نو و سالم را با امانت‌داری وجه بخرید و بفروشید؛ پول تا تأیید دریافت نزد بردخان امن می‌ماند.</p></div>
      <div class="card auth-card"><h3>۵. پرسش و تیکت</h3><p class="muted" style="font-size:13px">درخواست تعمیر ثبت کنید یا از طریق تیکت با تیم پشتیبانی و فروشنده در تماس باشید.</p></div>
      <div class="card auth-card"><h3>۶. اشتراک ویژه</h3><p class="muted" style="font-size:13px">با اشتراک ویژه به همه قلق‌های پرداختی بدون پرداخت جداگانه دسترسی داشته باشید.</p></div>
    </div>
  </section>

  <section class="card auth-card" style="text-align:center">
    <h3>ارزش‌های ما</h3>
    <p class="muted">صداقت محتوا · احترام به مالکیت دانش · امنیت مالی خریدار و فروشنده · پشتیبانی واقعی</p>
    <div class="flex center gap mt">
      <a class="btn btn-primary" href="<?=url('upload')?>">ثبت اولین قلق</a>
      <a class="btn btn-secondary" href="<?=url('contact')?>">تماس با ما</a>
    </div>
  </section>
</main>
<?php footer_html(); exit;
