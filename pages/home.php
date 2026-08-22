<?php
/* Bordkhan home page — separate template for clarity */
$s = settings();
$cats = category_tree();
$total = (int)db()->query("SELECT COUNT(*) FROM tips WHERE status='published'")->fetchColumn();
$usersCnt = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$latest = db()->query("SELECT t.*,u.name author_name,u.verified FROM tips t JOIN users u ON u.id=t.author_id WHERE t.status='published' ORDER BY t.published_at DESC LIMIT 8")->fetchAll();
$popular = db()->query("SELECT t.*,u.name author_name,u.verified FROM tips t JOIN users u ON u.id=t.author_id WHERE t.status='published' ORDER BY t.views DESC LIMIT 4")->fetchAll();
$featured = db()->query("SELECT t.*,u.name author_name,u.verified FROM tips t JOIN users u ON u.id=t.author_id WHERE t.status='published' AND t.featured=1 LIMIT 4")->fetchAll();
$leaders = db()->query('SELECT * FROM users ORDER BY points DESC LIMIT 5')->fetchAll();
$repairs = db()->query("SELECT r.*,u.name user_name FROM repair_requests r JOIN users u ON u.id=r.user_id WHERE r.status='open' ORDER BY r.created_at DESC LIMIT 3")->fetchAll();
$me = current_user();

header_html();
?>

<section class="hero">
  <div class="wrap hero-inner">
    <span class="eyebrow">✦ بازار تخصصی تعمیرات — <?=fa($total)?> قلق تست‌شده توسط تعمیرکاران واقعی</span>
    <h1><?=h($s['hero_title'] ?? 'بازار تخصصی قلق‌های تعمیراتی بردهای الکترونیکی')?></h1>
    <p><?=h($s['hero_subtitle'])?></p>
    <form class="search-box" action="<?=url('tips')?>" method="get"><input type="hidden" name="r" value="tips"><input name="q" placeholder="مثلاً: روشن نشدن لپ‌تاپ ایسوس X550…"><button class="btn btn-primary">🔍 جستجو</button></form>
    <div class="hero-cta">
      <?php if($me): ?>
        <a class="btn btn-amber btn-lg" href="<?=url('upload')?>">➕ ثبت قلق و کسب درآمد</a>
        <a class="btn btn-primary btn-lg" href="<?=url('boards')?>">🏪 فروشگاه برد</a>
        <a class="btn hero-btn-ghost" href="<?=url('tour')?>">راهنمای کامل</a>
      <?php else: ?>
        <a class="btn btn-amber btn-lg" href="<?=url('register')?>">✨ ثبت‌نام رایگان و کسب <?=fa($s['invitee_credit'])?> تومان اعتبار</a>
        <a class="btn btn-primary btn-lg" href="<?=url('boards')?>">🏪 فروشگاه برد</a>
        <a class="btn hero-btn-ghost" href="<?=url('upload')?>">➕ آپلود قلق</a>
      <?php endif; ?>
    </div>
    <div class="stats">
      <div class="stat"><strong><?=fa($total)?></strong><span>قلق منتشرشده</span></div>
      <div class="stat"><strong><?=fa($usersCnt)?></strong><span>تعمیرکار و عضو</span></div>
      <div class="stat"><strong>۲۴/۷</strong><span>دسترسی به راه‌حل</span></div>
    </div>
  </div>
</section>

<main class="wrap">

  <!-- دسته‌بندی‌ها -->
  <div class="cats"><?php foreach($cats as $c):?>
    <a class="cat" href="<?=url('tips',['cat'=>$c['id']])?>"><span class="emo"><?=h($c['icon']?:'🔧')?></span><span><b><?=h($c['name'])?></b><small><?=fa(count($c['children']))?> زیردسته</small></span></a>
  <?php endforeach;?></div>

  <!-- قلق رایگان روزانه -->
  <?php if($s['daily_free_tip_id']):
    $q=db()->prepare("SELECT t.*,u.name author_name,u.verified FROM tips t JOIN users u ON u.id=t.author_id WHERE t.id=? AND t.status='published'");
    $q->execute([$s['daily_free_tip_id']]); $daily=$q->fetch();
    if($daily): ?>
  <section class="section">
    <div class="feat"><div style="flex:1"><span class="pill amber">🔥 قلق رایگان امروز</span><h2><?=h($daily['title'])?></h2><p><?=h($daily['short_description'])?></p><a class="btn btn-amber btn-sm" href="<?=url('tip/'.$daily['id'])?>">مشاهده قلق ←</a></div><?php $di=tip_images($daily);if($di):?><img src="<?=h($di[0])?>" alt="<?=h($daily['title'])?>" loading="lazy"><?php endif;?></div>
  </section>
  <?php endif; endif; ?>

  <!-- CTA ثبت قلق -->
  <section class="section">
    <div class="card cta-card">
      <div class="cta-text">
        <span class="pill green">💰 کسب درآمد از دانش</span>
        <h2>دستگاهی را تعمیر کردی؟ قلقش را ثبت کن!</h2>
        <p>هر چیزی که راجع به تعمیر برد الکترونیکی می‌دانی — از عیب‌یابی تا تعویض قطعه — می‌تواند برای تو درآمد بسازد. ثبت قلق رایگان است و پاداش آپلود خودکارات است.</p>
        <div class="cta-actions">
          <a class="btn btn-primary btn-lg" href="<?=url('upload')?>">➕ ثبت قلق جدید</a>
          <a class="btn btn-secondary" href="<?=url('tour')?>">راهنمای کامل</a>
        </div>
      </div>
      <div class="cta-illus">🔧</div>
    </div>
  </section>

  <!-- جدیدترین قلقها -->
  <section class="section">
    <div class="sec-head"><div><h2>جدیدترین قلق‌ها</h2><p>آخرین راه‌حل‌های ثبت‌شده توسط تعمیرکاران</p></div><a class="btn btn-secondary btn-sm" href="<?=url('tips')?>">مشاهده همه ←</a></div>
    <div class="grid g4"><?php foreach($latest as $t) tip_card($t); ?></div>
  </section>

  <!-- محبوب ترینها -->
  <section class="section">
    <div class="sec-head"><div><h2>محبوب‌ترین‌ها</h2><p>پربازدیدترین راه‌حل‌های این هفته</p></div><a class="btn btn-secondary btn-sm" href="<?=url('tips',['sort'=>'popular'])?>">همه محبوب‌ها ←</a></div>
    <div class="grid g4"><?php foreach($popular as $t) tip_card($t); ?></div>
  </section>

  <!-- پیشنهاد سردبیر -->
  <?php if($featured):?>
  <section class="section">
    <div class="sec-head"><div><h2>⭐ پیشنهاد سردبیر</h2><p>قلق‌های انتخاب‌شده توسط تیم</p></div></div>
    <div class="grid g4"><?php foreach($featured as $t) tip_card($t); ?></div>
  </section>
  <?php endif;?>

  <!-- چطور کار می‌کند -->
  <section class="section">
    <div class="sec-head"><div><h2>بردخان چطور کار می‌کند؟</h2><p>سه مرحله ساده تا کسب درآمد</p></div></div>
    <div class="hw-grid">
      <div class="card hw"><span class="hw-num">۱</span><div class="hw-ico">📝</div><h3>قلق ثبت کنید</h3><p>مشکل دستگاه و راه‌حل گام‌به‌گام را ثبت کنید. سیستم به‌صورت خودکار محتوای تکراری را تشخیص می‌دهد.</p></div>
      <div class="card hw"><span class="hw-num">۲</span><div class="hw-ico">💳</div><h3>درآمد کسب کنید</h3><p>قلق رایگان، لایکی یا پولی منتشر کنید. هر فروش به کیف پول شما واریز می‌شود.</p></div>
      <div class="card hw"><span class="hw-num">۳</span><div class="hw-ico">🏅</div><h3>حرفه‌ای شوید</h3><p>امتیاز بگیرید، نشان کسب کنید و به عنوان تعمیرکار تأییدشده شناخته شوید.</p></div>
    </div>
  </section>

  <!-- امکانات کلیدی -->
  <section class="section">
    <div class="sec-head"><div><h2>امکانات کلیدی</h2><p>چرا بردخان؟</p></div></div>
    <div class="tgrid">
      <div class="card tstep"><div class="num">🏪</div><div><h3>فروشگاه برد با امانت</h3><p>برد دست دوم یا تعمیرشده را بخرید؛ وجه شما نزد بردخان ایمن است تا دریافت.</p></div></div>
      <div class="card tstep"><div class="num">🔓</div><div><h3>سه نوع دسترسی</h3><p>رایگان، با لایک و پرداختی — قلق شما با شرایط دلخواه منتشر می‌شود.</p></div></div>
      <div class="card tstep"><div class="num">👛</div><div><h3>کیف پول داخلی</h3><p>درآمد فروش، پاداش آپلود و معرفی دوستان. تسویه به شبا با احراز هویت.</p></div></div>
      <div class="card tstep"><div class="num">🔍</div><div><h3>جستجوی پیشرفته</h3><p>فیلتر بر اساس دسته، سختی، برند، قیمت و نوع دسترسی.</p></div></div>
      <div class="card tstep"><div class="num">🛠</div><div><h3>درخواست تعمیر</h3><p>مشکل خود را مطرح کنید، پاداش تعیین کنید و بهترین پاسخ را انتخاب کنید.</p></div></div>
      <div class="card tstep"><div class="num">🏆</div><div><h3>گیمیفیکیشن</h3><p>سطح‌بندی تازه‌کار تا استاد، نشان‌ها و رتبه‌بندی تعمیرکاران.</p></div></div>
      <div class="card tstep"><div class="num">🎬</div><div><h3>ریلز قلق‌ها</h3><p>اسکرول سریع بین قلق‌ها و لایک کردن مثل شبکه‌های اجتماعی.</p></div></div>
      <div class="card tstep"><div class="num">🤖</div><div><h3>گردآوری خودکار</h3><p>مدیر می‌تواند قلق‌های جدید را خودکار از منابع اینترنتی فارسی جمع‌آوری کند.</p></div></div>
      <div class="card tstep"><div class="num">🤝</div><div><h3>معرفی دوستان</h3><p>با دعوت دوستان، هر دو طرف اعتبار و امتیاز دریافت می‌کنید.</p></div></div>
    </div>
  </section>

  <!-- درخواست‌های باز تعمیر + برترین‌ها -->
  <section class="section grid g2">
    <div>
      <div class="sec-head"><div><h2>درخواست‌های باز تعمیر</h2><p>مشکل خود را مطرح کنید و پاداش تعیین کنید</p></div><a href="<?=url('repairs')?>" class="muted" style="font-size:11px">همه ←</a></div>
      <div class="card">
        <?php foreach($repairs as $r):?><a class="lrow" href="<?=url('repair/'.$r['id'])?>"><div class="grow"><strong><?=h($r['title'])?></strong><small><?=h($r['user_name'])?> · <?=fa($r['answer_count'])?> پاسخ</small></div><span class="pill amber"><?=h($r['reward_type']==='money'?money($r['reward_amount']).' ت':'لایک')?></span></a><?php endforeach;?>
        <?php if(!$repairs):?><div class="empty">درخواست بازی وجود ندارد.</div><?php endif;?>
      </div>
      <a class="btn btn-primary btn-sm mt" href="<?=url('repair/new')?>">+ ثبت درخواست تعمیر</a>
    </div>
    <div>
      <div class="sec-head"><div><h2>برترین تعمیرکاران</h2><p>بر اساس امتیاز کسب‌شده</p></div><a href="<?=url('leaderboard')?>" class="muted" style="font-size:11px">همه ←</a></div>
      <div class="card">
        <?php foreach($leaders as $i=>$l):?><a class="lrow" href="<?=url('profile/'.$l['id'])?>"><b style="width:25px;text-align:center"><?=['🥇','🥈','🥉'][$i]??fa($i+1)?></b><span class="avatar"><?=h(mb_substr($l['name'],0,1))?></span><span class="grow"><strong><?=h($l['name'])?></strong><small><?=h(level_name((int)$l['points']))?></small></span><b class="check"><?=fa($l['points'])?></b></a><?php endforeach;?>
      </div>
    </div>
  </section>

  <!-- اشتراک ویژه -->
  <section class="section">
    <div class="feat" style="background:linear-gradient(120deg,rgba(42,31,87,.4),var(--card));border-color:rgba(167,142,246,.28)">
      <div><span class="pill violet">💎 اشتراک ویژه</span><h2>دسترسی نامحدود به همه قلق‌های پولی</h2><p>یک اشتراک بخر و بدون پرداخت جداگانه به همه قلق‌های پولی دسترسی داشته باش.</p><a class="btn btn-amber btn-sm" href="<?=url('premium')?>">مشاهده پلن‌ها</a></div>
    </div>
  </section>

</main>
<?php footer_html(); ?>
