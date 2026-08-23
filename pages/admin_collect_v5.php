<?php
/* ================================================================
   ربات پیشرفته v5.0 — پنل حرفه‌ای جمع‌آوری خودکار
   زیرتب‌ها: run (پیشخوان و اجرای زنده) / sources (سلامت منابع)
             content (محتوای ربات) / settings (تنظیمات کامل)
   ================================================================ */
$botReady = bot_table_ready('bot_runs');
$srcReady = bot_table_ready('bot_sources');
$cs = $s;
$cats = category_tree();
$cronUrl = url('cron-collect', ['key' => $cs['auto_collect_cron_key'] ?? 'KEY']);
$st = $_GET['st'] ?? 'run';
if (!in_array($st, ['run', 'sources', 'content', 'settings'], true)) $st = 'run';

/* شناسهٔ کاربر ربات */
$botId = 0;
try { $bq = $pdo->prepare("SELECT id FROM users WHERE phone='09100000000' LIMIT 1"); $bq->execute(); $botId = (int)$bq->fetchColumn(); } catch (Throwable $e) {}

/* آمار کلی ربات */
$botKpi = ['total' => 0, 'published' => 0, 'pending' => 0, 'week' => 0, 'today' => 0, 'imgbytes' => 0];
if ($botId) {
    try {
        $botKpi['total'] = (int)$pdo->query("SELECT COUNT(*) FROM tips WHERE author_id=$botId")->fetchColumn();
        $botKpi['published'] = (int)$pdo->query("SELECT COUNT(*) FROM tips WHERE author_id=$botId AND status='published'")->fetchColumn();
        $botKpi['pending'] = (int)$pdo->query("SELECT COUNT(*) FROM tips WHERE author_id=$botId AND status='pending'")->fetchColumn();
        $botKpi['week'] = (int)$pdo->query("SELECT COUNT(*) FROM tips WHERE author_id=$botId AND created_at>=DATE(NOW())-INTERVAL 7 DAY")->fetchColumn();
        $botKpi['today'] = (int)$pdo->query("SELECT COUNT(*) FROM tips WHERE author_id=$botId AND created_at>=DATE(NOW())")->fetchColumn();
    } catch (Throwable $e) {}
}

/* نرخ موفقیت و میانگین ۳۰ روز اخیر از روی لاگ اجراها */
$runStats = ['runs30' => 0, 'success30' => 0, 'created30' => 0, 'dup30' => 0, 'avgCreated' => 0, 'avgDur' => 0];
$lastRun = null;
$runs = [];
if ($botReady) {
    try {
        $r = $pdo->query("SELECT COUNT(*) runs, SUM(status='completed') ok, SUM(created) cr, SUM(duplicates) dup, AVG(NULLIF(created,0)) avgc, AVG(NULLIF(duration_sec,0)) avgd FROM bot_runs WHERE created_at>=DATE(NOW())-INTERVAL 30 DAY")->fetch();
        $runStats = ['runs30' => (int)($r['runs'] ?? 0), 'success30' => (int)($r['ok'] ?? 0), 'created30' => (int)($r['cr'] ?? 0), 'dup30' => (int)($r['dup'] ?? 0), 'avgCreated' => round((float)($r['avgc'] ?? 0), 1), 'avgDur' => round((float)($r['avgd'] ?? 0), 1)];
        $lastRun = $pdo->query('SELECT * FROM bot_runs ORDER BY id DESC LIMIT 1')->fetch() ?: null;
        $runs = $pdo->query('SELECT * FROM bot_runs ORDER BY id DESC LIMIT 15')->fetchAll();
    } catch (Throwable $e) {}
}
$successRate = $runStats['runs30'] > 0 ? round($runStats['success30'] / $runStats['runs30'] * 100) : null;

/* نمودار ۱۴ روزهٔ قلق‌های ربات */
$botSeries = [];
for ($i = 13; $i >= 0; $i--) $botSeries[date('Y-m-d', strtotime("-{$i} day"))] = 0;
if ($botId) {
    try {
        $q = $pdo->query("SELECT DATE(created_at) d, COUNT(*) c FROM tips WHERE author_id=$botId AND created_at>=DATE(NOW())-INTERVAL 13 DAY GROUP BY DATE(created_at)");
        foreach ($q->fetchAll() as $row) if (isset($botSeries[$row['d']])) $botSeries[$row['d']] = (int)$row['c'];
    } catch (Throwable $e) {}
}

/* منابع پیش‌فرض برای نمایش در تنظیمات */
$defaultReputable = [
    'https://www.reddit.com/r/AskElectronics/.rss','https://www.reddit.com/r/ElectronicsRepair/.rss',
    'https://www.reddit.com/r/TVRepair/.rss','https://www.reddit.com/r/MobileRepair/.rss',
    'https://www.ifixit.com/News/rss','https://hackaday.com/feed/','https://blog.adafruit.com/feed/',
    'https://www.eevblog.com/feed/','https://www.allaboutcircuits.com/new/rss/','https://electronics.stackexchange.com/feeds',
    'https://www.electronicsforu.com/feed','https://circuitdigest.com/feed','https://www.electronicshub.org/feed',
    'https://www.engineersgarage.com/feed','https://www.electricaltechnology.org/feed',
    'https://www.elecfans.com/feed','https://www.21ic.com/rss/','https://www.eet-china.com/rss',
];
$srcs = json_decode_array($cs['auto_collect_sources'] ?? '');
$displaySources = !empty($srcs) ? implode("\n", $srcs) : implode("\n", $defaultReputable);
$defaultQueries = "تعمیر مادربرد سامسونگ\nرفع مشکل روشن نشدن لپ‌تاپ ایسوس\nتعمیر پاور سوئیچینگ\nعیب‌یابی کارت گرافیک\nتعمیر تلویزیون ال‌جی تصویر ندارد\nتعمیر موبایل شارژ نمی‌شود\nتعویض خازن مادربرد\nتست ماسفت با مولتی‌متر\nتعمیر بک‌لایت تلویزیون\nآموزش لحیم‌کاری SMD\nmotherboard no power repair\nlaptop no boot fix\ntv backlight repair\npower supply short circuit fix\nelectronics repair india\nmobile motherboard repair india\nchina electronics repair\nsmd soldering tutorial";
$displayQueries = trim((string)($cs['auto_collect_queries'] ?? '')) !== '' ? (string)$cs['auto_collect_queries'] : $defaultQueries;

$regionLabels = ['western' => '🌍 غربی', 'indian' => '🇮🇳 هندی', 'chinese' => '🇨🇳 چینی', 'japanese' => '🇯🇵 ژاپنی'];
?>
<div class="bk-subtabs">
  <a class="<?=$st === 'run' ? 'active' : ''?>" href="<?=url('admin', ['tab' => 'collect', 'st' => 'run'])?>">⚡ پیشخوان و اجرای زنده</a>
  <a class="<?=$st === 'sources' ? 'active' : ''?>" href="<?=url('admin', ['tab' => 'collect', 'st' => 'sources'])?>">📡 سلامت منابع</a>
  <a class="<?=$st === 'content' ? 'active' : ''?>" href="<?=url('admin', ['tab' => 'collect', 'st' => 'content'])?>">📦 محتوای ربات</a>
  <a class="<?=$st === 'settings' ? 'active' : ''?>" href="<?=url('admin', ['tab' => 'collect', 'st' => 'settings'])?>">⚙️ تنظیمات</a>
</div>

<?php if (!$botReady): ?>
  <div class="notice error" style="font-size:12px;line-height:2.2">
    ⚠️ جدول‌های ربات پیشرفته (<code dir="ltr">bot_runs</code> و <code dir="ltr">bot_sources</code>) هنوز ساخته نشده‌اند.
    برای تاریخچهٔ اجراها و پایش منابع، یک‌بار <a class="check" href="<?=url('php-extended/migrate.php')?>" target="_blank">migrate.php</a> را با کلید نصب اجرا کنید. ربات فعلاً بدون لاگ کار می‌کند.
  </div>
<?php endif; ?>

<?php /* ================= تب: پیشخوان و اجرای زنده ================= */ ?>
<?php if ($st === 'run'): ?>
<div class="admin-cards">
  <div class="card"><div class="k">📦 کل قلق‌های ربات</div><div class="v" id="bkKpiTotal"><?=fa(number_format($botKpi['total']))?></div></div>
  <div class="card"><div class="k">✅ منتشرشده</div><div class="v" id="bkKpiPub" style="color:#0a7a4a"><?=fa(number_format($botKpi['published']))?></div></div>
  <div class="card"><div class="k">⏳ در انتظار بررسی</div><div class="v" id="bkKpiPend" style="color:#b8860b"><?=fa(number_format($botKpi['pending']))?></div></div>
  <div class="card"><div class="k">📅 هفت روز اخیر</div><div class="v" id="bkKpiWeek"><?=fa(number_format($botKpi['week']))?></div></div>
  <div class="card"><div class="k">🎯 نرخ موفقیت (۳۰ روز)</div><div class="v"><?=$successRate === null ? '—' : fa($successRate) . '٪'?></div></div>
  <div class="card"><div class="k">📈 میانگین هر اجرا</div><div class="v"><?=$runStats['runs30'] ? fa($runStats['avgCreated']) : '—'?></div></div>
  <div class="card"><div class="k">🔁 تکراری ردشده (۳۰ روز)</div><div class="v"><?=fa(number_format($runStats['dup30']))?></div></div>
  <div class="card"><div class="k">🕒 امروز</div><div class="v" style="color:#078659"><?=fa(number_format($botKpi['today']))?></div></div>
</div>

<div class="grid grid-2">
  <div class="card" style="padding:18px">
    <h3 style="margin-bottom:6px">⚡ اجرای زندهٔ ربات پیشرفته v5.0</h3>
    <p class="muted" style="font-size:11px">اجرای همزمان با cron قفل می‌شود · تشخیص تکرار ۳لایه · انتخاب بهترین محتوا با امتیازدهی · گارد زمان اجرا</p>
    <form id="bkBotRunForm" method="post" class="mt" data-status-url="<?=url('ajax-bot-status')?>">
      <input type="hidden" name="csrf" value="<?=csrf()?>">
      <input type="hidden" name="action" value="admin_bot_run">
      <div class="grid grid-3">
        <div class="form-group"><label class="field-label">تعداد قلق (۱-۱۰۰)</label><input class="field" type="number" name="count" min="1" max="100" value="<?=(int)($cs['auto_collect_count'] ?? 10)?>"></div>
        <div class="form-group"><label class="field-label">نوع دسترسی</label><select class="field" name="access">
          <option value="free" <?=($cs['auto_collect_access'] ?? 'free') === 'free' ? 'selected' : ''?>>رایگان</option>
          <option value="like" <?=($cs['auto_collect_access'] ?? '') === 'like' ? 'selected' : ''?>>با لایک</option>
          <option value="paid" <?=($cs['auto_collect_access'] ?? '') === 'paid' ? 'selected' : ''?>>پرداختی</option>
        </select></div>
        <div class="form-group"><label class="field-label">دسته مقصد</label><select class="field" name="category"><option value="0">🤖 هوشمند</option><?php foreach ($cats as $c): ?><optgroup label="<?=h($c['name'])?>"><?php foreach ($c['children'] as $ch): ?><option value="<?=$ch['id']?>" <?=(int)($cs['auto_collect_category'] ?? 0) === $ch['id'] ? 'selected' : ''?>><?=h($ch['name'])?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></div>
      </div>
      <label style="display:flex;align-items:center;gap:8px;font-size:12px;margin:8px 0"><input type="checkbox" name="dry_run" value="1"> 🧪 <b>اجرای آزمایشی</b> (تست بدون ذخیره — گزارش تعداد قابل تولید)</label>
      <div class="bk-progress-wrap" id="bkBotProgressWrap" style="display:none">
        <div class="bk-progress"><i id="bkBotBar"></i></div>
        <small class="muted" id="bkBotStage">در حال اتصال به منابع…</small>
      </div>
      <div id="bkBotResult" class="mt"></div>
      <div class="flex gap" style="flex-wrap:wrap;margin-top:10px">
        <button class="btn btn-primary" type="submit" id="bkBotRunBtn">🚀 اجرای زندهٔ ربات</button>
        <a class="btn btn-secondary" href="<?=url('admin', ['tab' => 'collect', 'st' => 'settings'])?>">⚙️ تنظیمات ربات</a>
      </div>
    </form>
  </div>

  <div class="card" style="padding:18px">
    <h3 style="margin-bottom:10px">📊 قلق‌های ربات در ۱۴ روز اخیر</h3>
    <div class="bar-chart" style="height:150px">
      <?php $maxS = max(1, max($botSeries)); foreach ($botSeries as $d => $c): ?>
        <div class="bar" style="height:<?=max(3, round($c / $maxS * 115))?>px" title="<?=h($d)?>"><span><?=fa($c)?></span><i><?=fa(date('m/d', strtotime($d)))?></i></div>
      <?php endforeach; ?>
    </div>
    <div class="notice" style="font-size:11px;line-height:2;margin-top:14px">
      <?php if ($lastRun): ?>
        <b>آخرین اجرا:</b> <?=h($lastRun['trigger_type'] === 'cron' ? '🕐 زمان‌بندی‌شده' : '🖐 دستی')?> ·
        <span class="pill <?=$lastRun['status'] === 'completed' ? 'green' : ($lastRun['status'] === 'failed' ? 'rose' : 'amber')?>"><?=h($lastRun['status'] === 'completed' ? 'موفق' : ($lastRun['status'] === 'failed' ? 'ناموفق' : 'در جریان'))?></span>
        · <?=fa((int)$lastRun['created'])?> قلق در <?=fa((float)$lastRun['duration_sec'])?> ثانیه · <?=ago($lastRun['created_at'])?>
        <?=!empty($lastRun['dry_run']) ? ' · <b>آزمایشی</b>' : ''?>
      <?php else: ?>
        هنوز اجرایی ثبت نشده — اولین اجرای زنده را انجام دهید.
      <?php endif; ?>
      <?php if (!empty($cs['auto_collect_enabled'])): ?><br>✅ زمان‌بندی فعال است — cron هر بازدید بعدی را اجرا می‌کند. <?php else: ?><br>⏸ زمان‌بندی خاموش است — از تب تنظیمات فعال کنید. <?php endif; ?>
    </div>
  </div>
</div>

<div class="card mt" style="padding:18px">
  <h3 style="margin-bottom:10px">🕘 تاریخچهٔ اجراها (۱۵ اجرای اخیر)</h3>
  <?php if (!$botReady): ?>
    <p class="muted" style="font-size:12px">جدول <code dir="ltr">bot_runs</code> موجود نیست — migrate.php را اجرا کنید تا از این پس همهٔ اجراها با جزئیات ثبت شوند.</p>
  <?php elseif (!$runs): ?>
    <p class="muted" style="font-size:12px">هنوز اجرایی ثبت نشده است.</p>
  <?php else: ?>
  <div class="table-wrap"><table class="bk-table">
    <thead><tr><th>#</th><th>محرك</th><th>وضعیت</th><th>تولید</th><th>بررسی</th><th>تکراری</th><th>خطا</th><th>عکس</th><th>منابع ✓/✗</th><th>مدت</th><th>زمان</th></tr></thead>
    <tbody>
    <?php foreach ($runs as $r): $rg = json_decode_array($r['regions_json']); ?>
      <tr>
        <td><?=fa((int)$r['id'])?></td>
        <td><?=h($r['trigger_type'] === 'cron' ? '🕐 cron' : '🖐 دستی')?><?=!empty($r['dry_run']) ? ' 🧪' : ''?></td>
        <td><span class="bk-dot <?=$r['status'] === 'completed' ? 'ok' : ($r['status'] === 'failed' ? 'bad' : 'run')?>"></span><?=h($r['status'] === 'completed' ? 'موفق' : ($r['status'] === 'failed' ? 'ناموفق' : 'در جریان'))?></td>
        <td><b style="color:#0a7a4a"><?=fa((int)$r['created'])?></b></td>
        <td><?=fa((int)$r['scanned'])?></td>
        <td><?=fa((int)$r['duplicates'])?></td>
        <td><?=fa((int)$r['errors'])?></td>
        <td><?=fa((int)$r['images_downloaded'])?></td>
        <td><?=fa((int)$r['sources_ok'])?>/<?=fa((int)$r['sources_failed'])?></td>
        <td><?=fa((float)$r['duration_sec'])?>ث</td>
        <td title="<?=h($r['message'] ?? '')?>"><?=ago($r['created_at'])?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <small class="muted" style="font-size:10px">نشانگر ماوس را روی زمان ببرید تا پیام اجرا دیده شود.</small>
  <?php endif; ?>
</div>

<script>
(function(){
  var form = document.getElementById('bkBotRunForm'); if (!form) return;
  var bar = document.getElementById('bkBotBar'), wrap = document.getElementById('bkBotProgressWrap'),
      stage = document.getElementById('bkBotStage'), out = document.getElementById('bkBotResult'),
      btn = document.getElementById('bkBotRunBtn');
  if (!bar || !out) return;
  function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;}
  form.addEventListener('submit', function(e){
    e.preventDefault();
    btn.disabled = true; var orig = btn.innerHTML; btn.innerHTML = '⏳ ربات در حال اجراست…';
    out.innerHTML = ''; wrap.style.display = 'block'; bar.style.width = '4%';
    var t0 = Date.now(), spin = 0;
    var stages = ['در حال اتصال به منابع معتبر…','خواندن فیدهای RSS…','جستجوی هوشمند کوئری‌ها…','امتیازدهی و انتخاب بهترین‌ها…','ترجمه و ساخت قلق فارسی…','دانلود تصاویر به هاست…'];
    var timer = setInterval(function(){
      spin++; var el = (Date.now()-t0)/1000;
      bar.style.width = Math.min(93, 4 + el*2.4) + '%';
      stage.textContent = stages[Math.min(stages.length-1, Math.floor(el/7))];
    }, 400);
    var fd = new FormData(form);
    fetch(window.location.href, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}, body:fd})
      .then(function(r){ return r.json().catch(function(){ throw new Error('پاسخ سرور قابل خواندن نبود'); }); })
      .then(function(j){
        clearInterval(timer); bar.style.width='100%';
        if (j && j.ok) {
          var v = j.result || {};
          var dry = !!(v.dry_run);
          var boxes = [
            ['📦', 'قلق ' + (dry?'قابل تولید':'جدید'), (v.created!=null?fa_num(v.created):'0'), ''],
            ['🔍', 'بررسی‌شده', (v.scanned!=null?fa_num(v.scanned):'0'), ''],
            ['🔁', 'تکراری رد شد', (v.duplicates!=null?fa_num(v.duplicates):'0'), ''],
            ['⚠️', 'خطا', (v.errors!=null?fa_num(v.errors):'0'), ''],
            ['🖼', 'عکس دانلود شد', (v.images_downloaded!=null?fa_num(v.images_downloaded):'0'), ''],
            ['📡', 'منبع سالم/خطا', (v.sources_ok!=null?fa_num(v.sources_ok):'0')+' / '+(v.sources_failed!=null?fa_num(v.sources_failed):'0'), ''],
            ['⏱', 'مدت اجرا', (v.duration_fa!=null?v.duration_fa:'—')+' ثانیه', '']
          ];
          var h = dry ? '<div class="notice" style="font-size:11px;margin-bottom:10px">🧪 اجرای آزمایشی — هیچ چیزی در دیتابیس ذخیره نشد.</div>' : '';
          h += '<div class="bk-run-grid">' + boxes.map(function(b){return '<div class="bk-run-box"><b>'+b[0]+' '+esc(b[1])+'</b><span '+b[3]+'>'+esc(b[2])+'</span></div>';}).join('') + '</div>';
          if (v.regions && Object.keys(v.regions).length) {
            var rg = []; for (var k in v.regions) if (v.regions[k]) rg.push(esc(k)+': '+fa_num(v.regions[k]));
            h += '<small class="muted" style="font-size:10px">منطقه‌ها — '+rg.join(' · ')+'</small>';
          }
          if (v.message) h += '<div class="notice" style="font-size:11px;margin-top:8px">✅ '+esc(v.message)+'</div>';
          out.innerHTML = h;
          refreshKpis();
        } else {
          stage.textContent = 'اجرا ناموفق بود';
          out.innerHTML = '<div class="notice error" style="font-size:12px">⚠️ '+esc((j&&j.error)||'خطای نامشخص')+'</div>';
        }
        btn.disabled = false; btn.innerHTML = orig;
      })
      .catch(function(err){
        clearInterval(timer);
        stage.textContent = 'خطای ارتباط با سرور';
        out.innerHTML = '<div class="notice error" style="font-size:12px">⚠️ '+esc(err.message||err)+'</div>';
        btn.disabled = false; btn.innerHTML = orig;
      });
  });
  function fa_num(n){ return String(n).replace(/[0-9]/g, function(d){return '۰۱۲۳۴۵۶۷۸۹'[d];}); }
  function refreshKpis(){
    fetch(form.getAttribute('data-status-url'), {headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}})
      .then(function(r){return r.json();}).then(function(j){
        if (!j || !j.ok) return;
        var set = function(id,val){var el=document.getElementById(id); if(el) el.textContent=fa_num(val);};
        set('bkKpiTotal', (j.kpi.total||0).toLocaleString('en')); set('bkKpiPub',(j.kpi.published||0).toLocaleString('en'));
        set('bkKpiPend',(j.kpi.pending||0).toLocaleString('en')); set('bkKpiWeek',(j.kpi.week||0).toLocaleString('en'));
      }).catch(function(){});
  }
})();
</script>
<?php endif; ?>

<?php /* ================= تب: سلامت منابع ================= */ ?>
<?php if ($st === 'sources'): ?>
<?php
$srcRows = [];
$srcSummary = ['total' => 0, 'ok' => 0, 'fail' => 0, 'dead' => 0];
if ($srcReady) {
    try {
        $srcRows = $pdo->query('SELECT * FROM bot_sources ORDER BY consecutive_fails DESC, last_check DESC LIMIT 100')->fetchAll();
        $srcSummary['total'] = count($srcRows);
        foreach ($srcRows as $sr) {
            if ((int)$sr['consecutive_fails'] >= 3) $srcSummary['dead']++;
            elseif ($sr['last_status'] === 'ok') $srcSummary['ok']++;
            else $srcSummary['fail']++;
        }
    } catch (Throwable $e) {}
}
?>
<div class="admin-cards">
  <div class="card"><div class="k">📡 منابع پایش‌شده</div><div class="v"><?=fa($srcSummary['total'])?></div></div>
  <div class="card"><div class="k">✅ سالم</div><div class="v" style="color:#0a7a4a"><?=fa($srcSummary['ok'])?></div></div>
  <div class="card"><div class="k">⚠️ خطای موقت</div><div class="v" style="color:#b8860b"><?=fa($srcSummary['fail'])?></div></div>
  <div class="card"><div class="k">❌ خراب (۳+ خطای متوالی)</div><div class="v" style="color:#b3261e"><?=fa($srcSummary['dead'])?></div></div>
</div>

<div class="card" style="padding:18px">
  <h3 style="margin-bottom:6px">📡 پایش سلامت منابع (v5.0)</h3>
  <p class="muted" style="font-size:11px">هر اجرا وضعیت هر منبع را به‌روز می‌کند: وضعیت آخرین اتصال، تعداد آیتم، زمان پاسخ و خطاهای متوالی. منابع با ۳ خطای متوالی را بررسی یا حذف کنید.</p>
  <?php if (!$srcReady): ?>
    <p class="muted" style="font-size:12px;margin-top:10px">جدول <code dir="ltr">bot_sources</code> موجود نیست — migrate.php را اجرا کنید.</p>
  <?php elseif (!$srcRows): ?>
    <p class="muted" style="font-size:12px;margin-top:10px">هنوز منبعی پایش نشده — یک اجرای زنده انجام دهید تا وضعیت منابع اینجا جمع شود.</p>
  <?php else: ?>
  <div class="table-wrap"><table class="bk-table">
    <thead><tr><th>منبع</th><th>منطقه</th><th>وضعیت</th><th>آیتم</th><th>پاسخ</th><th>موفق/خطا</th><th>خطای متوالی</th><th>آخرین بررسی</th><th>آخرین خطا</th></tr></thead>
    <tbody>
    <?php foreach ($srcRows as $sr): $dead = (int)$sr['consecutive_fails'] >= 3; ?>
      <tr>
        <td dir="ltr" style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><a class="check" href="<?=h($sr['url'])?>" target="_blank" rel="noopener"><?=h($sr['url'])?></a></td>
        <td><?=$regionLabels[$sr['region']] ?? h($sr['region'])?></td>
        <td><span class="bk-dot <?=$dead ? 'bad' : ($sr['last_status'] === 'ok' ? 'ok' : 'warn')?>"></span><?=$dead ? 'خراب' : ($sr['last_status'] === 'ok' ? 'سالم' : 'خطا')?></td>
        <td><?=fa((int)$sr['last_items'])?></td>
        <td dir="ltr"><?=fa((int)$sr['last_ms'])?>ms</td>
        <td><?=fa((int)$sr['ok_count'])?>/<?=fa((int)$sr['fail_count'])?></td>
        <td><?=$dead ? '<b style="color:#b3261e">' . fa((int)$sr['consecutive_fails']) . '</b>' : fa((int)$sr['consecutive_fails'])?></td>
        <td><?=$sr['last_check'] ? ago($sr['last_check']) : '—'?></td>
        <td class="muted" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?=h($sr['last_error'] ?? '')?>"><?=h($sr['last_error'] ?: '—')?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <div class="flex gap mt" style="flex-wrap:wrap">
    <a class="btn btn-secondary btn-sm" href="<?=url('admin', ['tab' => 'collect', 'st' => 'settings'])?>">✏️ ویرایش لیست منابع</a>
    <a class="btn btn-primary btn-sm" href="<?=url('admin', ['tab' => 'collect', 'st' => 'run'])?>">⚡ اجرای زنده برای تازه‌سازی وضعیت</a>
  </div>
</div>
<?php endif; ?>

<?php /* ================= تب: محتوای ربات ================= */ ?>
<?php if ($st === 'content'): ?>
<?php
$botTips = []; $botTotal = 0; $cpage = max(1, (int)($_GET['p'] ?? 1)); $per = 30;
$cfilter = $_GET['f'] ?? '';
if ($botId) {
    try {
        $w = "author_id=$botId"; $p2 = [];
        if (in_array($cfilter, ['published','pending','rejected','removed'], true)) { $w .= " AND status=?"; $p2[] = $cfilter; }
        $cq = $pdo->prepare("SELECT COUNT(*) FROM tips WHERE $w"); $cq->execute($p2); $botTotal = (int)$cq->fetchColumn();
        $tq = $pdo->prepare("SELECT id,title,status,created_at,views,likes_count,source_name,source_url,images_json FROM tips WHERE $w ORDER BY id DESC LIMIT $per OFFSET " . (($cpage-1)*$per));
        $tq->execute($p2); $botTips = $tq->fetchAll();
    } catch (Throwable $e) {}
}
$pages = max(1, (int)ceil($botTotal / $per));
?>
<div class="card" style="padding:18px">
  <div class="flex between items-center" style="flex-wrap:wrap;gap:10px;margin-bottom:10px">
    <h3 style="margin:0">📦 قلق‌های تولیدشده توسط ربات <small class="muted">(<?=fa($botTotal)?> مورد)</small></h3>
    <div class="tip-meta">
      <?php foreach (['' => 'همه', 'published' => 'منتشرشده', 'pending' => 'در انتظار', 'rejected' => 'ردشده', 'removed' => 'حذفشده'] as $fv => $fl): ?>
        <a class="pill <?=$cfilter === $fv ? 'green' : ''?>" href="<?=url('admin', ['tab' => 'collect', 'st' => 'content', 'f' => $fv, 'p' => 1])?>"><?=h($fl)?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php if (!$botTips): ?>
    <p class="muted" style="font-size:12px">قلقی از ربات ثبت نشده — از تب «پیشخوان و اجرای زنده» شروع کنید.</p>
  <?php else: ?>
  <div class="table-wrap"><table class="bk-table">
    <thead><tr><th>#</th><th>عنوان</th><th>وضعیت</th><th>منبع</th><th>👁</th><th>♥</th><th>زمان</th><th>عملیات سریع</th></tr></thead>
    <tbody>
    <?php foreach ($botTips as $bt): ?>
      <tr>
        <td><?=fa((int)$bt['id'])?></td>
        <td style="max-width:300px"><a class="check" href="<?=url('tip/' . (int)$bt['id'])?>" target="_blank"><?=h(mb_substr($bt['title'], 0, 70))?></a></td>
        <td><span class="pill <?=$bt['status'] === 'published' ? 'green' : ($bt['status'] === 'pending' ? 'amber' : 'rose')?>"><?=h(status_label($bt['status']))?></span></td>
        <td class="muted" style="font-size:10px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?=h($bt['source_name'] ?? '')?>"><?=h($bt['source_name'] ?: '—')?></td>
        <td><?=fa((int)$bt['views'])?></td>
        <td><?=fa((int)$bt['likes_count'])?></td>
        <td><?=ago($bt['created_at'])?></td>
        <td>
          <div class="flex gap" style="gap:4px">
            <?php if ($bt['status'] !== 'published'): ?>
            <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="admin_bot_tip"><input type="hidden" name="tip_id" value="<?=$bt['id']?>"><input type="hidden" name="op" value="publish"><button class="btn btn-sm btn-primary" title="انتشار">✅</button></form>
            <?php else: ?>
            <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="admin_bot_tip"><input type="hidden" name="tip_id" value="<?=$bt['id']?>"><input type="hidden" name="op" value="unpublish"><button class="btn btn-sm btn-secondary" title="برداشتن از انتشار">⏸</button></form>
            <?php endif; ?>
            <form method="post" style="display:inline" onsubmit="return confirm('این قلق ربات برای همیشه حذف شود؟')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="admin_bot_tip"><input type="hidden" name="tip_id" value="<?=$bt['id']?>"><input type="hidden" name="op" value="delete"><button class="btn btn-sm btn-danger" title="حذف">🗑</button></form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($pages > 1): ?>
  <div class="flex gap mt" style="flex-wrap:wrap">
    <?php for ($pi = max(1, $cpage-3); $pi <= min($pages, $cpage+3); $pi++): ?>
      <a class="pill <?=$pi === $cpage ? 'green' : ''?>" href="<?=url('admin', ['tab' => 'collect', 'st' => 'content', 'f' => $cfilter, 'p' => $pi])?>"><?=fa($pi)?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php /* ================= تب: تنظیمات ================= */ ?>
<?php if ($st === 'settings'): ?>
<div class="card" style="padding:18px">
  <h3 style="margin-bottom:6px">⚙️ تنظیمات ربات پیشرفته v5.0</h3>
  <p class="muted" style="font-size:12px">پس از ذخیره، تنظیمات هم برای اجرای زنده و هم برای cron اعمال می‌شود.</p>
  <form method="post" class="mt">
    <input type="hidden" name="csrf" value="<?=csrf()?>">
    <input type="hidden" name="action" value="admin_collect">

    <div class="card" style="padding:12px;background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.2);margin-bottom:14px">
      <h4 style="margin:0 0 8px;font-size:13px">⚙️ تنظیمات اصلی</h4>
      <div class="form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="enabled" value="1" <?=!empty($cs['auto_collect_enabled']) ? 'checked' : ''?>> <b>فعال‌سازی جمع‌آوری خودکار (Cron)</b></label></div>
      <div class="grid grid-2">
        <div class="form-group"><label class="field-label">تعداد قلق در هر اجرا (1-100)</label><input class="field" type="number" name="count" min="1" max="100" value="<?=(int)($cs['auto_collect_count'] ?? 10)?>"></div>
        <div class="form-group"><label class="field-label">نوع دسترسی</label><select class="field" name="access"><option value="free" <?=($cs['auto_collect_access'] ?? 'free') === 'free' ? 'selected' : ''?>>رایگان</option><option value="like" <?=($cs['auto_collect_access'] ?? '') === 'like' ? 'selected' : ''?>>با لایک</option><option value="paid" <?=($cs['auto_collect_access'] ?? '') === 'paid' ? 'selected' : ''?>>پرداختی</option></select></div>
      </div>
      <div class="grid grid-2">
        <div class="form-group"><label class="field-label">دسته‌بندی مقصد</label><select class="field" name="category"><option value="">🤖 هوشمند بر اساس دستگاه</option><?php foreach ($cats as $c): ?><optgroup label="<?=h($c['name'])?>"><?php foreach ($c['children'] as $ch): ?><option value="<?=$ch['id']?>" <?=(int)($cs['auto_collect_category'] ?? 0) === $ch['id'] ? 'selected' : ''?>><?=h($ch['name'])?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></div>
        <div class="form-group"><label class="field-label">زبان مقصد</label><select class="field" name="language"><option value="auto" <?=($cs['auto_collect_language'] ?? 'auto')==='auto'?'selected':''?>>🤖 خودکار</option><option value="fa" <?=($cs['auto_collect_language'] ?? '')==='fa'?'selected':''?>>فارسی</option><option value="en" <?=($cs['auto_collect_language'] ?? '')==='en'?'selected':''?>>English</option></select></div>
      </div>
      <div class="grid grid-2">
        <div class="form-group"><label class="field-label">نوع محتوا</label><select class="field" name="content_type"><option value="repair" <?=($cs['auto_collect_content_type'] ?? 'repair')==='repair'?'selected':''?>>🔧 فقط تعمیرات</option><option value="tutorial" <?=($cs['auto_collect_content_type'] ?? '')==='tutorial'?'selected':''?>>📚 آموزشی</option><option value="all" <?=($cs['auto_collect_content_type'] ?? '')==='all'?'selected':''?>>همه</option></select></div>
        <div class="form-group"><label class="field-label">انتشار</label><select class="field" name="auto_publish"><option value="1" <?=!empty($cs['auto_collect_auto_publish'] ?? 1)?'selected':''?>>✅ انتشار خودکار</option><option value="0" <?=empty($cs['auto_collect_auto_publish'] ?? 1)?'selected':''?>>⏳ پیش‌نویس (بررسی مدیر)</option></select></div>
      </div>
    </div>

    <div class="card" style="padding:12px;background:rgba(56,189,248,.06);border:1px solid rgba(56,189,248,.2);margin-bottom:14px">
      <h4 style="margin:0 0 8px;font-size:13px">🌍 منطقه‌ای — هندی 🇮🇳 و چینی 🇨🇳 و ژاپنی 🇯🇵</h4>
      <div class="grid grid-3">
        <div class="form-group"><label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" name="indian_enabled" value="1" <?=!empty($cs['auto_collect_indian_enabled'] ?? 1) ? 'checked' : ''?>> 🇮🇳 <b>هندی</b></label><small class="muted" style="font-size:9px">Electronics For You, Circuit Digest</small></div>
        <div class="form-group"><label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" name="chinese_enabled" value="1" <?=!empty($cs['auto_collect_chinese_enabled']) ? 'checked' : ''?>> 🇨🇳 <b>چینی</b></label><small class="muted" style="font-size:9px">Elecfans, 21IC, EET China</small></div>
        <div class="form-group"><label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" name="japanese_enabled" value="1" <?=!empty($cs['auto_collect_japanese_enabled']) ? 'checked' : ''?>> 🇯🇵 <b>ژاپنی</b></label><small class="muted" style="font-size:9px">اضافی</small></div>
      </div>
    </div>

    <div class="card" style="padding:12px;background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.2);margin-bottom:14px">
      <h4 style="margin:0 0 8px;font-size:13px">🧠 استخراج هوشمند + تصاویر</h4>
      <div class="grid grid-2">
        <div class="form-group"><label class="field-label">حداقل طول محتوا</label><input class="field" type="number" name="min_length" min="20" max="1000" value="<?=(int)($cs['auto_collect_min_length'] ?? 100)?>"></div>
        <div class="form-group"><label class="field-label">حداکثر تصاویر (1-5)</label><input class="field" type="number" name="max_images" min="1" max="5" value="<?=(int)($cs['auto_collect_max_images'] ?? 3)?>"></div>
        <div class="form-group"><label class="field-label">کیفیت تصویر</label><select class="field" name="image_quality"><option value="low" <?=($cs['auto_collect_image_quality'] ?? 'medium')==='low'?'selected':''?>>کم (65% + 1200px)</option><option value="medium" <?=($cs['auto_collect_image_quality'] ?? 'medium')==='medium'?'selected':''?>>متوسط (84% + 1600px)</option><option value="high" <?=($cs['auto_collect_image_quality'] ?? '')==='high'?'selected':''?>>بالا (92% + 1920px)</option></select></div>
        <div class="form-group"><label class="field-label">مسیر ذخیره</label><select class="field" name="save_path"><option value="auto" <?=($cs['auto_collect_save_path'] ?? 'auto')==='auto'?'selected':''?>>🤖 auto/{region}/</option><option value="western" <?=($cs['auto_collect_save_path'] ?? '')==='western'?'selected':''?>>غربی</option><option value="indian" <?=($cs['auto_collect_save_path'] ?? '')==='indian'?'selected':''?>>هندی</option><option value="chinese" <?=($cs['auto_collect_save_path'] ?? '')==='chinese'?'selected':''?>>چینی</option></select></div>
      </div>
      <div class="grid grid-2" style="margin-top:10px">
        <div class="form-group"><label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="translate_enabled" value="1" <?=!empty($cs['auto_collect_translate_enabled'] ?? 1) ? 'checked' : ''?>> <b>ترجمه EN→FA</b></label></div>
        <div class="form-group"><label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="extract_full" value="1" <?=!empty($cs['auto_collect_extract_full'] ?? 1) ? 'checked' : ''?>> <b>متن کامل مقاله</b></label></div>
        <div class="form-group"><label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="save_images" value="1" <?=!empty($cs['auto_collect_save_images'] ?? 1) ? 'checked' : ''?>> <b>دانلود تصاویر به هاست</b></label></div>
        <div class="form-group"><label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="filter_repair" value="1" <?=!empty($cs['auto_collect_filter_repair'] ?? 1) ? 'checked' : ''?>> <b>فیلتر فقط تعمیرات</b></label></div>
      </div>
      <div class="grid grid-2" style="margin-top:10px">
        <div class="form-group"><label class="field-label">کلمات مستثنی (با کاما یا خط جدید)</label><input class="field" name="exclude_keywords" value="<?=h($cs['auto_collect_exclude_keywords'] ?? '')?>" placeholder="politics, sport, celebrity"></div>
        <div class="form-group"><label class="field-label">تایم‌اوت و تلاش مجدد</label><div class="flex gap"><input class="field" type="number" name="timeout" min="5" max="30" value="<?=(int)($cs['auto_collect_timeout'] ?? 12)?>" placeholder="timeout"><input class="field" type="number" name="max_retries" min="1" max="5" value="<?=(int)($cs['auto_collect_max_retries'] ?? 2)?>" placeholder="retry"></div></div>
      </div>
    </div>

    <div class="card" style="padding:12px;background:rgba(124,58,237,.06);border:1px solid rgba(124,58,237,.2);margin-bottom:14px">
      <h4 style="margin:0 0 8px;font-size:13px">🛡 موتور پیشرفته v5.0</h4>
      <div class="grid grid-2">
        <div class="form-group"><label class="field-label">حداکثر زمان هر اجرا (ثانیه، 20-600)</label><input class="field" type="number" name="time_limit" min="20" max="600" value="<?=(int)($cs['auto_collect_time_limit'] ?? 100)?>"><small class="muted" style="font-size:9px">گارد زمان: اجرا با همان تولید انجام‌شده تمام می‌شود تا cron نصفه نماند</small></div>
        <div class="form-group" style="align-self:end"><label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" name="rotate" value="1" <?=!empty($cs['auto_collect_rotate'] ?? 1) ? 'checked' : ''?>> <b>🔄 چرخش هوشمند منابع</b></label><small class="muted" style="font-size:9px">هر اجرا از جای متفاوتی از لیست منابع/کوئری‌ها شروع می‌شود تا همه به‌مرور استفاده شوند</small></div>
      </div>
      <div class="notice" style="font-size:10px;line-height:2;margin-top:8px">
        ✅ قفل اجرای همزمان (دستی + cron تداخل نمی‌کنند) · ✅ تشخیص تکرار ۳لایه (URL + عنوان + شباهت فازی ۵۵٪) · ✅ امتیازدهی کیفیت و انتخاب بهترین کاندیدها · ✅ لاگ کامل اجراها · ✅ پایش سلامت منابع
      </div>
    </div>

    <div class="form-group"><label class="field-label">🔍 کلمات جستجو هوشمند (چرخش بین اجراها)</label><textarea class="field" name="queries" rows="10" placeholder="تعمیر مادربرد&#10;motherboard repair"><?=h($displayQueries)?></textarea></div>
    <div class="form-group"><label class="field-label">🌐 منابع RSS معتبر</label><textarea class="field" name="sources" rows="12" dir="ltr" placeholder="https://example.com/feed"><?=h($displaySources)?></textarea></div>
    <div class="form-group"><label class="field-label">🔑 کلید Cron</label><input class="field" dir="ltr" name="cron_key" value="<?=h($cs['auto_collect_cron_key'] ?? '')?>" placeholder="خالی = تولید خودکار"></div>

    <div class="card" style="padding:12px;background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.2);margin:14px 0">
      <h4 style="margin:0 0 8px;font-size:13px">🕐 زمان‌بندی (cPanel → Cron Jobs هر ۶ ساعت)</h4>
      <div class="field" dir="ltr" style="font-family:monospace;font-size:11px;word-break:break-all">wget -q -O /dev/null "<?=h(SITE_URL . $cronUrl)?>"</div>
      <small class="muted" style="font-size:10px">هر اجرا در تاریخچهٔ پنل با جزئیات ثبت می‌شود؛ اجرای همزمان دستی و cron قفل می‌شود.</small>
    </div>

    <div class="flex gap" style="flex-wrap:wrap">
      <button class="btn btn-primary" name="run_now" value="1">⚡ اجرای فوری (با رفرش صفحه)</button>
      <button class="btn btn-secondary" name="save" value="1">💾 ذخیره تنظیمات</button>
      <a class="btn btn-secondary" href="<?=url('admin', ['tab' => 'collect', 'st' => 'run'])?>">⚡ اجرای زنده بدون رفرش</a>
    </div>
  </form>
</div>
<?php endif; ?>
