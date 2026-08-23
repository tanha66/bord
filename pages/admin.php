<?php
/* Bordkhan admin panel — included from index.php (all helpers are in scope). */
$a = require_admin();
$tab = $_GET['tab'] ?? 'dashboard';
$pdo = db();
$s = settings();

$TABS = [
    'dashboard' => '📊 داشبورد',
    'tips' => '🔧 قلق‌ها',
    'boards' => '🏪 فروشگاه برد',
    'sellers' => '🛒 فروشندگان',
    'orders' => '📦 سفارش‌های برد',
    'users' => '👥 کاربران',
    'reports' => '🚩 گزارش‌ها',
    'categories' => '🗂 دسته‌بندی‌ها',
    'withdrawals' => '🏦 تسویه‌ها',
    'transactions' => '🧾 تراکنش‌ها',
    'contact' => '📨 پیام‌های تماس',
    'settings' => '⚙️ تنظیمات سایت',
    'collect' => '🤖 ربات پیشرفته v5',
];

header_html('پنل مدیریت');
$flash = pull_flash();
?>
<main class="wrap page">
  <div class="page-title">
    <h1>پنل مدیریت بردخان</h1>
    <p>خوش آمدید، <?=h($a['name'])?> — نقش: <?=h(role_label($a['role']))?></p>
  </div>
  <?php if ($flash): ?><div class="<?=h($flash[1])?>"><?=h($flash[0])?></div><?php endif; ?>

  <div class="admin-wrap">
    <aside class="admin-side card" style="padding:10px">
      <nav class="admin-nav">
        <?php foreach ($TABS as $k => $v): ?>
          <a class="<?=$tab === $k ? 'active' : ''?>" href="<?=url('admin', ['tab' => $k])?>"><?=h($v)?></a>
        <?php endforeach; ?>
        <a href="<?=url('admin-boards')?>">🛠 مدیریت بردها</a>
        <a href="<?=url('admin-tips')?>">🔧 مدیریت قلق‌ها</a>
        <a href="<?=url('admin-users')?>">👥 مدیریت کاربران</a>
        <a href="<?=url('admin-finance')?>">💳 مالی و درگاه</a>
        <a href="<?=url('admin-actionbar')?>">📌 نوار شناور</a>
        <a href="<?=url('tickets')?>">✉ تیکت‌ها</a>
      </nav>
      <div class="notice" style="font-size:11px;line-height:2;margin-top:12px">
        از همین پنل می‌توانید محتوا را تأیید/رد کنید، نقش کاربران را تغییر دهید، تسویه‌ها را بررسی کنید و تنظیمات مالی و سئو را تغییر دهید.<br>
        <b>نسخهٔ کد: <?=defined('BORDKHAN_VERSION')?BORDKHAN_VERSION:'قدیمی'?></b> — اگر کمتر از 4.0 بود، فایل‌های جدید روی سرور آپلود نشده‌اند (<a class="check" href="<?=url('diag-version')?>" target="_blank">بررسی</a>).
      </div>
    </aside>

    <section class="admin-main">
<?php
/* ---------------- DASHBOARD (پیشخوان حرفه‌ای v5) ---------------- */
if ($tab === 'dashboard') {
    require __DIR__ . '/admin_dashboard_v5.php';
}

/* ---------------- TIPS ---------------- */
elseif ($tab === 'boards') {
    $items = $pdo->query("SELECT b.*,u.name seller_name FROM boards b JOIN users u ON u.id=b.seller_id ORDER BY FIELD(b.status,'pending','approved','sold','rejected','archived'),b.created_at DESC LIMIT 150")->fetchAll();
    ?><div class="card tablewrap"><table class="table"><tr><th>#</th><th>برد</th><th>فروشنده</th><th>قیمت</th><th>وضعیت</th><th>موجودی</th><th>عملیات</th></tr>
    <?php foreach($items as $x):?><tr>
      <td><?=fa($x['id'])?></td><td><a class="check" href="<?=url('board/'.$x['id'])?>"><?=h(mb_substr($x['title'],0,45))?></a></td><td><?=h($x['seller_name'])?></td>
      <td><?=money($x['price'])?></td>
      <td><span class="pill <?=in_array($x['status'],['approved'])?'green':($x['status']==='pending'?'amber':($x['status']==='sold'?'blue':'rose'))?>"><?=h(board_status_label($x['status']))?></span></td>
      <td><?=fa($x['stock'])?> / فروخته <?=fa($x['sold_count'])?></td>
      <td><form method="post" style="display:flex;gap:4px;flex-wrap:wrap"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="admin_board"><input type="hidden" name="board_id" value="<?=$x['id']?>">
        <?php if($x['status']==='pending'):?><button class="btn btn-primary btn-sm" name="op" value="approve">تأیید</button><button class="btn btn-secondary btn-sm" name="op" value="reject" onclick="return confirm('این برد رد شود؟')">رد</button><?php endif;?>
        <?php if(in_array($x['status'],['approved','sold'])):?><button class="btn btn-danger btn-sm" name="op" value="remove" onclick="return confirm('بایگانی شود؟')">بایگانی</button><?php endif;?></form></td>
    </tr><?php endforeach;?></table></div><?php
}

elseif ($tab === 'sellers') {
    $pend = $pdo->query("SELECT * FROM users WHERE seller_status='pending' ORDER BY seller_applied_at DESC")->fetchAll();
    $approved = $pdo->query("SELECT * FROM users WHERE seller_status='approved' ORDER BY name LIMIT 100")->fetchAll();
    ?>
    <div class="card" style="padding:18px;margin-bottom:16px"><h3 style="margin:0 0 12px">درخواست‌های در انتظار (<?=fa(count($pend))?>)</h3>
      <?php if(!$pend):?><p class="muted">درخواستی نیست.</p><?php endif;?>
      <?php foreach($pend as $x):?><div class="aline"><div class="grow" style="flex:1"><strong><?=h($x['name'])?> — <?=h($x['phone'])?></strong><p class="muted" style="font-size:12px;margin:3px 0 0"><?=h($x['seller_note']??'')?></p></div>
        <form method="post" style="display:flex;gap:5px"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="admin_seller"><input type="hidden" name="user_id" value="<?=$x['id']?>">
          <button class="btn btn-primary btn-sm" name="op" value="approve">✔ تأیید فروشنده</button><button class="btn btn-danger btn-sm" name="op" value="reject">✘ رد</button></form></div><?php endforeach;?>
    </div>
    <div class="card tablewrap"><table class="table"><tr><th>فروشنده تأییدشده</th><th>موبایل</th><th>امتیاز</th><th>عملیات</th></tr>
      <?php foreach($approved as $x):?><tr><td><a class="check" href="<?=url('profile/'.$x['id'])?>"><?=h($x['name'])?></a></td><td dir="ltr"><?=h($x['phone'])?></td><td><?=fa($x['points'])?></td><td><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="admin_seller"><input type="hidden" name="user_id" value="<?=$x['id']?>"><button class="btn btn-danger btn-sm" name="op" value="revoke" onclick="return confirm('دسترسی فروشندگی لغو شود؟')">لغو</button></form></td></tr><?php endforeach;?>
    </table></div>
    <?php
}

elseif ($tab === 'orders') {
    $items = $pdo->query("SELECT o.*,b.title,bh.name buyer,sh.name seller FROM board_orders o JOIN boards b ON b.id=o.board_id JOIN users bh ON bh.id=o.buyer_id JOIN users sh ON sh.id=o.seller_id ORDER BY FIELD(o.status,'paid','shipped','completed','cancelled'),o.created_at DESC LIMIT 150")->fetchAll();
    $escrowBalance = (int)$pdo->query("SELECT balance FROM users WHERE id=".(escrow_admin_id()))->fetchColumn();
    ?><div class="card" style="padding:14px;margin-bottom:14px"><b>موجودی حساب امانت (مدیر):</b> <span class="check" style="font-size:18px;font-weight:900"><?=money($escrowBalance)?> تومان</span> — این مبلغ شامل وجوه در حال نگه‌داری و کمیسیون‌های تسوینشده است.</div>
    <div class="card tablewrap"><table class="table"><tr><th>#</th><th>برد</th><th>خریدار</th><th>فروشنده</th><th>مبلغ</th><th>کمیسیون</th><th>سهم فروشنده</th><th>وضعیت</th><th>عملیات</th></tr>
    <?php foreach($items as $o):?><tr>
      <td><?=fa($o['id'])?></td><td><?=h(mb_substr($o['title'],0,30))?></td><td><?=h($o['buyer'])?></td><td><?=h($o['seller'])?></td>
      <td><?=money($o['amount'])?></td><td><?=money($o['commission_amount'])?> (<?=fa($o['commission_percent'])?>٪)</td><td class="check" style="font-weight:bold"><?=money($o['net_amount'])?></td>
      <td><span class="pill <?=$o['status']==='completed'?'green':($o['status']==='cancelled'?'rose':'amber')?>"><?=h(order_status_label($o['status']))?></span><?php if($o['tracking_code']):?><br><small dir="ltr" class="muted"><?=h($o['tracking_code'])?></small><?php endif;?></td>
      <td><?php if(in_array($o['status'],['paid','shipped'],true)):?><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="board_cancel"><input type="hidden" name="order_id" value="<?=$o['id']?>"><button class="btn btn-danger btn-sm" onclick="return confirm('سفارش لغو و وجه به خریدار برگردد؟')">لغو و بازگشت وجه</button></form><?php endif;?></td>
    </tr><?php endforeach;?></table></div><?php
}

elseif ($tab === 'tips') {
    $tf = in_array($_GET['f'] ?? '', ['pending','published','draft','rejected','removed'], true) ? $_GET['f'] : '';
    $tq = trim($_GET['q'] ?? '');
    $where = ''; $params = [];
    if ($tf !== '') { $where = 'WHERE t.status=?'; $params[] = $tf; }
    if ($tq !== '') { $where .= ($where === '' ? 'WHERE ' : ' AND ') . '(t.title LIKE ? OR t.device_name LIKE ? OR t.brand LIKE ?)'; $params[] = '%'.$tq.'%'; $params[] = '%'.$tq.'%'; $params[] = '%'.$tq.'%'; }
    $tstmt = $pdo->prepare("SELECT t.*,u.name author_name FROM tips t JOIN users u ON u.id=t.author_id $where ORDER BY FIELD(t.status,'pending','published','draft','rejected','removed'),t.created_at DESC LIMIT 500");
    $tstmt->execute($params);
    $items = $tstmt->fetchAll();
    $published=(int)$pdo->query("SELECT COUNT(*) FROM tips WHERE status='published'")->fetchColumn();
    $pending=(int)$pdo->query("SELECT COUNT(*) FROM tips WHERE status='pending'")->fetchColumn();
    $draft=(int)$pdo->query("SELECT COUNT(*) FROM tips WHERE status='draft'")->fetchColumn();
    $rejected=(int)$pdo->query("SELECT COUNT(*) FROM tips WHERE status='rejected'")->fetchColumn();
    $removed=(int)$pdo->query("SELECT COUNT(*) FROM tips WHERE status='removed'")->fetchColumn();
    $totalViews=(int)$pdo->query('SELECT COALESCE(SUM(views),0) FROM tips')->fetchColumn();
    $totalSales=(int)$pdo->query("SELECT COALESCE(SUM(purchases_count),0) FROM tips")->fetchColumn();
    ?>
    <div class="flex between items-center mb" style="gap:10px;flex-wrap:wrap">
      <div class="tip-meta">
        <a class="pill <?=$tf===''?'green':''?>" href="<?=url('admin',['tab'=>'tips'])?>">همه</a>
        <a class="pill <?=$tf==='pending'?'green':''?>" href="<?=url('admin',['tab'=>'tips','f'=>'pending'])?>">⏳ در انتظار (<?=fa($pending)?>)</a>
        <a class="pill <?=$tf==='published'?'green':''?>" href="<?=url('admin',['tab'=>'tips','f'=>'published'])?>">✅ منتشرشده (<?=fa($published)?>)</a>
        <a class="pill <?=$tf==='rejected'?'green':''?>" href="<?=url('admin',['tab'=>'tips','f'=>'rejected'])?>">ردشده (<?=fa($rejected)?>)</a>
        <a class="pill <?=$tf==='removed'?'green':''?>" href="<?=url('admin',['tab'=>'tips','f'=>'removed'])?>">حذفشده (<?=fa($removed)?>)</a>
      </div>
      <form method="get" style="display:flex;gap:6px;max-width:300px">
        <input type="hidden" name="r" value="admin"><input type="hidden" name="tab" value="tips">
        <input class="field" style="padding:8px" name="q" value="<?=h($tq)?>" placeholder="جستجوی قلق…">
        <button class="btn btn-secondary btn-sm">جستجو</button>
      </form>
    </div>
    <div class="grid grid-4 mb">
      <div class="card stat-card"><strong><?=fa($published)?></strong><small>منتشرشده</small></div>
      <div class="card stat-card"><strong><?=fa($pending)?></strong><small>در انتظار بررسی</small></div>
      <div class="card stat-card"><strong><?=fa($totalViews)?></strong><small>بازدید کل</small></div>
      <div class="card stat-card"><strong><?=fa($totalSales)?></strong><small>خرید کل</small></div>
    </div>
    <div class="card table-wrap">
      <table class="table">
        <tr><th>#</th><th>قلق</th><th>نویسنده</th><th>دسترسی</th><th>وضعیت</th><th>بازدید</th><th>فروش</th><th>عملیات</th></tr>
        <?php foreach ($items as $x): ?>
        <tr>
          <td><?=fa($x['id'])?></td>
          <td style="max-width:220px"><a class="check" href="<?=url('tip/' . $x['id'])?>"><?=h(mb_substr($x['title'], 0, 55))?></a>
            <details style="margin-top:4px"><summary style="font-size:11px;color:var(--text-dim);cursor:pointer">ویرایش سریع</summary>
              <form method="post" style="margin-top:6px">
                <input type="hidden" name="csrf" value="<?=csrf()?>">
                <input type="hidden" name="action" value="admin_tip_edit">
                <input type="hidden" name="tip_id" value="<?=$x['id']?>">
                <input class="field" style="margin-bottom:5px;font-size:12px" name="title" value="<?=h($x['title'])?>">
                <input class="field" style="margin-bottom:5px;font-size:12px" name="short_description" value="<?=h($x['short_description'])?>">
                <input class="field" style="margin-bottom:5px;font-size:12px;width:70px" type="number" name="price" value="<?=(int)$x['price']?>">
                <select class="field" style="margin-bottom:5px;font-size:12px;width:auto" name="access_type"><option value="free" <?=$x['access_type']==='free'?'selected':''?>>رایگان</option><option value="like" <?=$x['access_type']==='like'?'selected':''?>>با لایک</option><option value="paid" <?=$x['access_type']==='paid'?'selected':''?>>پولی</option></select>
                <button class="btn btn-primary btn-sm">💾 ذخیره</button>
              </form>
            </details>
          </td>
          <td><?=h($x['author_name'])?></td>
          <td><?=h(access_label($x['access_type'], (int)$x['price']))?></td>
          <td><span class="pill <?=$x['status']==='published'?'green':($x['status']==='pending'?'amber':'rose')?>"><?=h(status_label($x['status']))?></span></td>
          <td><?=fa($x['views'])?></td>
          <td><?=fa($x['purchases_count'])?></td>
          <td>
            <form method="post" style="display:flex;gap:4px;flex-wrap:wrap">
              <input type="hidden" name="csrf" value="<?=csrf()?>">
              <input type="hidden" name="action" value="admin_tip">
              <input type="hidden" name="tip_id" value="<?=$x['id']?>">
              <?php if ($x['status'] !== 'published'): ?><button class="btn btn-primary btn-sm" name="mod_action" value="publish" title="تأیید و انتشار">تأیید</button><?php endif; ?>
              <button class="btn btn-secondary btn-sm" name="mod_action" value="feature">⭐</button>
              <?php if ($x['status'] !== 'removed'): ?><button class="btn btn-danger btn-sm" name="mod_action" value="remove">حذف</button><?php endif; ?>
              <a class="btn btn-secondary btn-sm" href="<?=url('tip/'.$x['id'])?>" target="_blank">👁</a>
              <button class="btn btn-danger btn-sm" name="mod_action" value="delete_forever" onclick="return confirm('حذف نهایی و بدون برگشت؟')" title="حذف برای همیشه">🗑</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php
}

/* ---------------- USERS (مدیریت کاربران حرفه‌ای v5) ---------------- */
elseif ($tab === 'users') {
    require __DIR__ . '/admin_users_v5.php';
}

/* ---------------- REPORTS ---------------- */
elseif ($tab === 'reports') {
    $items = $pdo->query("SELECT r.*,u.name reporter_name FROM reports r JOIN users u ON u.id=r.reporter_id ORDER BY FIELD(r.status,'open','resolved','dismissed'),r.created_at DESC LIMIT 100")->fetchAll();
    ?>
    <div class="card">
      <?php if (!$items): ?><div class="empty">گزارشی ثبت نشده است.</div><?php endif; ?>
      <?php foreach ($items as $r): ?>
        <div class="activity-item" style="display:block;padding:14px">
          <div class="flex between items-center">
            <span class="pill <?=$r['status']==='open'?'amber':'green'?>"><?=h($r['status']==='open'?'باز':'بررسی شده')?></span>
            <b style="color:#b42f45"><?=h($r['reason'])?></b>
            <small class="muted">توسط <?=h($r['reporter_name'])?> · <?=ago($r['created_at'])?></small>
          </div>
          <p class="muted" style="font-size:12px;margin:8px 0 0">نوع: <?=h($r['target_type'])?> — شناسه: <?=fa($r['target_id'])?><?php if ($r['detail']): ?> — <?=h($r['detail'])?><?php endif; ?></p>
          <?php if ($r['status'] === 'open'): ?>
            <div class="mt" style="display:flex;gap:6px">
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?=csrf()?>">
                <input type="hidden" name="action" value="admin_report">
                <input type="hidden" name="report_id" value="<?=$r['id']?>">
                <button class="btn btn-primary btn-sm" name="resolve" value="1">حذف محتوا و بستن</button>
                <button class="btn btn-secondary btn-sm" name="resolve" value="0">تأیید محتوا و بستن</button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
}

/* ---------------- CATEGORIES ---------------- */
elseif ($tab === 'categories') {
    $items = $pdo->query('SELECT c.*,p.name parent_name,(SELECT COUNT(*) FROM categories cc WHERE cc.parent_id=c.id) child_count FROM categories c LEFT JOIN categories p ON p.id=c.parent_id ORDER BY c.parent_id IS NOT NULL, c.sort_order, c.name')->fetchAll();
    $parents = $pdo->query('SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name')->fetchAll();
    ?>
    <div class="flex between items-center mb" style="gap:10px;flex-wrap:wrap">
      <p class="muted" style="font-size:11px;margin:0">اگر نامی را دوبار (با والد یکسان) می‌بینید، با دکمهٔ «حذف موارد تکراری» یک‌باره پاک‌سازی کنید.</p>
      <form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="admin_category"><input type="hidden" name="op" value="dedupe"><button class="btn btn-secondary btn-sm" onclick="return confirm('دسته‌های تکراری (نام + والد یکسان) حذف شوند؟')">🧹 حذف موارد تکراری</button></form>
    </div>
    <div class="form-group mb" style="max-width:340px"><label class="field-label">🔍 جستجوی زندهٔ دسته‌ها (آجاکس)</label><input class="field" id="catSearch" type="text" placeholder="مثلاً: سامسونگ، موبایل، هواوی…"></div>
    <div id="catResults"></div>
    <div class="grid grid-2">
      <div class="card" style="padding:18px">
        <h3 style="margin-bottom:12px">افزودن دسته جدید</h3>
        <form method="post">
          <input type="hidden" name="csrf" value="<?=csrf()?>">
          <input type="hidden" name="action" value="admin_category">
          <input type="hidden" name="op" value="add">
          <div class="form-group"><label class="field-label">نام دسته</label><input class="field" name="name" required></div>
          <div class="form-group"><label class="field-label">دسته والد (خالی = اصلی)</label>
            <select class="field" name="parent_id"><option value="">— بدون والد —</option>
              <?php foreach ($parents as $p): ?><option value="<?=$p['id']?>"><?=h($p['name'])?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label class="field-label">ایموجی (مثلاً ⚡)</label><input class="field" name="icon" placeholder="🔧"></div>
          <button class="btn btn-primary btn-full">افزودن دسته</button>
        </form>
      </div>
      <div class="card table-wrap" id="catStatic">
        <table class="table">
          <tr><th>دسته</th><th>والد</th><th>وضعیت</th><th>عملیات</th></tr>
          <?php foreach ($items as $x): ?>
          <tr>
            <td><?=h(($x['icon'] ?: '📁') . ' ' . $x['name'])?><?php if ((int)$x['child_count'] > 0): ?><small class="muted" style="display:block"><?=(int)$x['child_count']?> زیرمجموعه</small><?php endif; ?></td>
            <td><?=h($x['parent_name'] ?: 'اصلی')?></td>
            <td><span class="pill <?=$x['status']==='active'?'green':'amber'?>"><?=h($x['status']==='active'?'فعال':'پیشنهادی')?></span></td>
            <td>
              <form method="post" style="display:flex;gap:4px">
                <input type="hidden" name="csrf" value="<?=csrf()?>">
                <input type="hidden" name="action" value="admin_category">
                <input type="hidden" name="category_id" value="<?=$x['id']?>">
                <?php if ($x['status'] !== 'active'): ?><button class="btn btn-primary btn-sm" name="op" value="approve">تأیید</button><?php endif; ?>
                <button class="btn btn-danger btn-sm" name="op" value="delete" onclick="return confirm('این دسته حذف شود؟')">حذف</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
    <script>
(function(){var inp=document.getElementById('catSearch');if(!inp)return;var out=document.getElementById('catResults');var stat=document.getElementById('catStatic');var csrf=<?=json_encode(csrf())?>;var timer=null;function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;}function render(rows){if(!rows.length){out.innerHTML='<div class="card empty">دسته‌ای با این عبارت پیدا نشد.</div>';return;}var h='<div class="card table-wrap"><table class="table"><tr><th>دسته</th><th>والد</th><th>وضعیت</th><th>عملیات</th></tr>';rows.forEach(function(x){h+='<tr><td>'+esc((x.icon||'📁')+' '+x.name)+((+x.child_count)>0?'<small class="muted" style="display:block">'+x.child_count+' زیرمجموعه</small>':'')+'</td><td>'+esc(x.parent_name||'اصلی')+'</td><td><span class="pill '+(x.status==='active'?'green':'amber')+'">'+esc(x.status==='active'?'فعال':'پیشنهادی')+'</span></td><td><form method="post" style="display:flex;gap:4px"><input type="hidden" name="csrf" value="'+esc(csrf)+'"><input type="hidden" name="action" value="admin_category"><input type="hidden" name="category_id" value="'+x.id+'">'+(x.status!=='active'?'<button class="btn btn-primary btn-sm" name="op" value="approve">تأیید</button>':'')+'<button class="btn btn-danger btn-sm" name="op" value="delete" onclick="return confirm(\'این دسته حذف شود؟\')">حذف</button></form></td></tr>';});h+='</table></div>';out.innerHTML=h;}inp.addEventListener('input',function(){clearTimeout(timer);var q=inp.value.trim();if(q===''){out.innerHTML='';if(stat)stat.style.display='';return;}timer=setTimeout(function(){fetch('/ajax-categories?q='+encodeURIComponent(q),{headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}}).then(function(r){return r.json();}).then(function(j){if(j&&j.rows){if(stat)stat.style.display='none';render(j.rows);}}).catch(function(){out.innerHTML='<div class="notice error">خطا در دریافت نتایج.</div>';});},250);});})();
</script>
    <?php
}

/* ---------------- WITHDRAWALS ---------------- */
elseif ($tab === 'withdrawals') {
    $items = $pdo->query("SELECT w.*,u.name user_name,u.phone FROM withdrawals w JOIN users u ON u.id=w.user_id ORDER BY FIELD(w.status,'pending','reviewing','paid','rejected'),w.created_at DESC LIMIT 100")->fetchAll();
    ?>
    <div class="grid grid-2">
      <?php foreach ($items as $x): ?>
      <div class="card" style="padding:16px">
        <div class="flex between items-center">
          <b><?=h($x['user_name'])?></b>
          <span class="pill <?=$x['status']==='paid'?'green':($x['status']==='rejected'?'rose':'amber')?>"><?=h(['pending'=>'در انتظار','reviewing'=>'در حال بررسی','paid'=>'واریز شده','rejected'=>'رد شده'][$x['status']] ?? $x['status'])?></span>
        </div>
        <p style="font-size:20px;font-weight:900;color:#078659;margin:6px 0"><?=money($x['amount'])?> تومان</p>
        <p class="muted" style="font-size:11px" dir="ltr">شبا: <?=h($x['shaba'])?><br>کارت: <?=h($x['card_number'])?><br>کد ملی: <?=h($x['national_id'])?></p>
        <p class="muted" style="font-size:11px"><?=datetime_fa($x['created_at'])?></p>
        <?php if (in_array($x['status'], ['pending','reviewing'], true)): ?>
          <form method="post" style="display:flex;gap:6px;margin-top:10px">
            <input type="hidden" name="csrf" value="<?=csrf()?>">
            <input type="hidden" name="action" value="admin_withdraw">
            <input type="hidden" name="withdrawal_id" value="<?=$x['id']?>">
            <button class="btn btn-primary btn-sm" name="status" value="paid">واریز شد</button>
            <button class="btn btn-danger btn-sm" name="status" value="rejected">رد و برگشت پول</button>
          </form>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php if (!$items): ?><div class="card empty">درخواست تسویه‌ای وجود ندارد.</div><?php endif; ?>
    </div>
    <?php
}

/* ---------------- TRANSACTIONS ---------------- */
elseif ($tab === 'transactions') {
    $items = $pdo->query("SELECT t.*,u.name user_name FROM wallet_transactions t JOIN users u ON u.id=t.user_id ORDER BY t.created_at DESC LIMIT 200")->fetchAll();
    $typeLabels = ['upload_reward'=>'پاداش آپلود','sale'=>'درآمد فروش','like_reward'=>'پاداش لایک','referral'=>'پاداش معرفی','referral_invitee'=>'اعتبار هدیه','admin_adjust'=>'تسویه دستی','purchase'=>'خرید قلق','withdrawal'=>'برداشت','withdrawal_cancel'=>'برگشت تسویه','refund'=>'بازگشت هزینه','subscription'=>'اشتراک ویژه','repair_reward'=>'پاداش پاسخ','repair_payment'=>'پرداخت پاداش'];
    ?>
    <div class="card table-wrap">
      <table class="table">
        <tr><th>کاربر</th><th>نوع</th><th>مبلغ</th><th>موجودی بعد</th><th>شرح</th><th>تاریخ</th></tr>
        <?php foreach ($items as $x): ?>
        <tr>
          <td><?=h($x['user_name'])?></td>
          <td><span class="pill blue"><?=h($typeLabels[$x['type']] ?? $x['type'])?></span></td>
          <td class="<?=(int)$x['amount'] > 0 ? 'check' : ''?>" style="font-weight:bold"><?=(int)$x['amount'] > 0 ? '+' : ''?><?=money($x['amount'])?></td>
          <td><?=money($x['balance_after'])?></td>
          <td style="font-size:11px"><?=h(mb_substr($x['note'] ?? '', 0, 50))?></td>
          <td style="font-size:11px"><?=datetime_fa($x['created_at'])?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php
}

/* ---------------- CONTACT MESSAGES ---------------- */
elseif ($tab === 'contact') {
    $statusFilter = in_array($_GET['status'] ?? '', ['new','answered','closed'], true) ? $_GET['status'] : '';
    try {
        if ($statusFilter === '') {
            $rows = $pdo->query('SELECT * FROM contact_messages ORDER BY FIELD(status,"new","answered","closed"), created_at DESC LIMIT 200')->fetchAll();
        } else {
            $q = $pdo->prepare('SELECT * FROM contact_messages WHERE status=? ORDER BY created_at DESC LIMIT 200');
            $q->execute([$statusFilter]);
            $rows = $q->fetchAll();
        }
    } catch (Throwable $e) { $rows = []; }
    $statusLabels = ['new'=>'جدید','answered'=>'پاسخ داده شد','closed'=>'بسته شد'];
    ?>
    <div class="page-title" style="margin-top:0">
      <h1 style="font-size:20px">📨 پیام‌های تماس با ما</h1>
      <p>پیام‌هایی که از صفحه «تماس با ما» ثبت شده‌اند.</p>
    </div>
    <?php if (empty($s['contact_form_enabled'])): ?>
      <div class="notice" style="margin-bottom:14px">⚠️ فرم پیام «تماس با ما» در حال حاضر <b>غیرفعال</b> است؛ کاربران پیام جدیدی ثبت نمی‌کنند. برای فعال‌سازی به <a class="check" href="<?=url('admin',['tab'=>'settings'])?>">تنظیمات سایت</a> بروید.</div>
    <?php endif; ?>
    <div class="flex between items-center mb">
      <div class="tip-meta">
        <a class="pill <?=$statusFilter===''?'green':''?>" href="<?=url('admin',['tab'=>'contact'])?>">همه</a>
        <?php foreach ($statusLabels as $k => $v): ?>
          <a class="pill <?=$statusFilter===$k?'green':''?>" href="<?=url('admin',['tab'=>'contact','status'=>$k])?>"><?=h($v)?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if (!$rows): ?>
      <div class="card empty">پیامی ثبت نشده است.</div>
    <?php else: foreach ($rows as $m): ?>
      <div class="card" style="padding:16px;margin-bottom:12px">
        <div class="flex between items-center" style="gap:10px;flex-wrap:wrap">
          <div class="grow">
            <strong><?=h($m['subject'])?></strong>
            <small class="muted"> · <?=h($m['name'])?><?=!empty($m['email'])?' · <span dir="ltr">'.h($m['email']).'</span>':''?><?=!empty($m['phone'])?' · <span dir="ltr">'.h($m['phone']).'</span>':''?><?=!empty($m['user_id'])?' · <a class="check" href="'.url('profile/'.$m['user_id']).'">پروفایل کاربر</a>':''?></small>
            <div class="muted" style="font-size:11px"><?=ago($m['created_at'])?></div>
          </div>
          <span class="pill <?=$m['status']==='new'?'amber':($m['status']==='answered'?'green':'rose')?>"><?=h($statusLabels[$m['status']] ?? $m['status'])?></span>
        </div>
        <p class="rich mt" style="background:rgba(255,255,255,.03);border-radius:8px;padding:10px"><?=nl2br(h($m['body']))?></p>
        <form method="post" class="tip-meta mt">
          <input type="hidden" name="csrf" value="<?=csrf()?>">
          <input type="hidden" name="action" value="contact_status">
          <input type="hidden" name="contact_id" value="<?=$m['id']?>">
          <?php if ($m['status'] !== 'answered'): ?><button class="btn btn-primary btn-sm" name="op" value="answered">✔ پاسخ داده شد</button><?php endif; ?>
          <?php if ($m['status'] !== 'closed'): ?><button class="btn btn-secondary btn-sm" name="op" value="closed">بستن</button><?php else: ?><button class="btn btn-secondary btn-sm" name="op" value="reopen">باز کردن مجدد</button><?php endif; ?>
          <button class="btn btn-danger btn-sm" name="op" value="delete" onclick="return confirm('این پیام حذف شود؟')">حذف</button>
        </form>
      </div>
    <?php endforeach; endif;
}

/* ---------------- SETTINGS ---------------- */
elseif ($tab === 'settings') {
    ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=csrf()?>">
      <input type="hidden" name="action" value="admin_settings">

      <div class="card" style="padding:18px;margin-bottom:16px">
        <h3 style="margin-bottom:12px">🌐 هویت و متن‌های سایت</h3>
        <div class="grid grid-2">
          <div class="form-group"><label class="field-label">عنوان سایت</label><input class="field" name="site_title" value="<?=h($s['site_title'] ?? '')?>"></div>
          <div class="form-group"><label class="field-label">نوار اطلاع‌رسانی (خالی = مخفی)</label><input class="field" name="announcement" value="<?=h($s['announcement'] ?? '')?>"></div>
        </div>
        <div class="form-group"><label class="field-label">تیتر اصلی صفحه</label><input class="field" name="hero_title" value="<?=h($s['hero_title'] ?? '')?>"></div>
        <div class="form-group"><label class="field-label">زیرتیتر صفحه اصلی</label><textarea class="field" name="hero_subtitle" rows="2"><?=h($s['hero_subtitle'] ?? '')?></textarea></div>
        <div class="grid grid-3">
          <div class="form-group"><label class="field-label">قوانین استفاده</label><textarea class="field" name="terms_text" rows="5"><?=h($s['terms_text'] ?? '')?></textarea></div>
          <div class="form-group"><label class="field-label">حریم خصوصی</label><textarea class="field" name="privacy_text" rows="5"><?=h($s['privacy_text'] ?? '')?></textarea></div>
          <div class="form-group"><label class="field-label">درباره ما</label><textarea class="field" name="about_text" rows="5"><?=h($s['about_text'] ?? '')?></textarea></div>
        </div>
        <div class="form-group"><label class="field-label">متن اضافی تماس با ما (بالای اطلاعات تماس)</label><textarea class="field" name="contact_text" rows="3"><?=h($s['contact_text'] ?? '')?></textarea></div>
        <div class="form-group" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <label class="field-label" style="margin:0">فرم پیام «تماس با ما»</label>
          <select class="field" name="contact_form_enabled" style="width:auto">
            <option value="0" <?=empty($s['contact_form_enabled'])?'selected':''?>>غیرفعال (پیش‌فرض)</option>
            <option value="1" <?=!empty($s['contact_form_enabled'])?'selected':''?>>فعال</option>
          </select>
          <small class="muted" style="font-size:11px">وقتی غیرفعال است، فرم ثبت پیام نمایش داده نمی‌شود و کاربران به تیکت پشتیبانی هدایت می‌شوند؛ پیام‌های قبلی و تب «پیام‌های تماس» محفوظ می‌مانند.</small>
        </div>
        <div class="grid grid-3">
          <div class="form-group"><label class="field-label">ایمیل پشتیبانی</label><input class="field" dir="ltr" name="contact_email" value="<?=h($s['contact_email'] ?? '')?>" placeholder="support@example.com"></div>
          <div class="form-group"><label class="field-label">تلفن پشتیبانی</label><input class="field" dir="ltr" name="contact_phone" value="<?=h($s['contact_phone'] ?? '')?>" placeholder="021-00000000"></div>
          <div class="form-group"><label class="field-label">تلگرام</label><input class="field" dir="ltr" name="contact_telegram" value="<?=h($s['contact_telegram'] ?? '')?>" placeholder="@bordkhan"></div>
          <div class="form-group"><label class="field-label">اینستاگرام</label><input class="field" dir="ltr" name="contact_instagram" value="<?=h($s['contact_instagram'] ?? '')?>" placeholder="@bordkhan"></div>
          <div class="form-group"><label class="field-label">آدرس</label><input class="field" name="contact_address" value="<?=h($s['contact_address'] ?? '')?>"></div>
        </div>
      </div>

      <div class="card" style="padding:18px;margin-bottom:16px">
        <h3 style="margin-bottom:12px">💸 تنظیمات مالی</h3>
        <div class="grid grid-3">
          <div class="form-group"><label class="field-label">پاداش آپلود (تومان)</label><input class="field" type="number" name="upload_reward" value="<?=(int)($s['upload_reward'] ?? 0)?>"></div>
          <div class="form-group"><label class="field-label">کمیسیون سایت (٪)</label><input class="field" type="number" name="commission_percent" value="<?=(int)($s['commission_percent'] ?? 0)?>"></div>
          <div class="form-group"><label class="field-label">کمیسیون فروش برد فیزیکی (٪)</label><input class="field" type="number" name="board_commission_percent" min="0" max="50" value="<?=(int)($s['board_commission_percent'] ?? 10)?>"><p class="muted" style="font-size:10px;margin-top:4px">از هر فروش برد، این درصد به حساب مدیریت باقی می‌ماند و مابقی به فروشنده تعلق می‌گیرد.</p></div>
          <div class="form-group"><label class="field-label">حداقل برداشت (تومان)</label><input class="field" type="number" name="min_withdrawal" value="<?=(int)($s['min_withdrawal'] ?? 0)?>"></div>
          <div class="form-group"><label class="field-label">پاداش امتیاز هر لایک</label><input class="field" type="number" name="like_points_reward" value="<?=(int)($s['like_points_reward'] ?? 0)?>"></div>
          <div class="form-group"><label class="field-label">پاداش نقدی هر لایک (تومان)</label><input class="field" type="number" name="like_wallet_reward" value="<?=(int)($s['like_wallet_reward'] ?? 0)?>"></div>
          <div class="form-group"><label class="field-label">سقف لایک روزانه هر کاربر</label><input class="field" type="number" name="daily_like_limit" value="<?=(int)($s['daily_like_limit'] ?? 5)?>"></div>
          <div class="form-group"><label class="field-label">پاداش معرفی (تومان)</label><input class="field" type="number" name="referral_reward" value="<?=(int)($s['referral_reward'] ?? 0)?>"></div>
          <div class="form-group"><label class="field-label">اعتبار دعوت‌شده (تومان)</label><input class="field" type="number" name="invitee_credit" value="<?=(int)($s['invitee_credit'] ?? 0)?>"></div>
          <div class="form-group"><label class="field-label">مهلت پاسخ درخواست (روز)</label><input class="field" type="number" name="repair_deadline_days" value="<?=(int)($s['repair_deadline_days'] ?? 7)?>"></div>
        </div>
        <div class="grid grid-3">
          <div class="form-group"><label class="field-label">قیمت اشتراک ۱ ماهه</label><input class="field" type="number" name="premium_1" value="<?=(int)($s['premium_1'] ?? 0)?>"></div>
          <div class="form-group"><label class="field-label">قیمت اشتراک ۳ ماهه</label><input class="field" type="number" name="premium_3" value="<?=(int)($s['premium_3'] ?? 0)?>"></div>
          <div class="form-group"><label class="field-label">قیمت اشتراک ۱۲ ماهه</label><input class="field" type="number" name="premium_12" value="<?=(int)($s['premium_12'] ?? 0)?>"></div>
        </div>
      </div>

      <div class="card" style="padding:18px;margin-bottom:16px">
        <h3 style="margin-bottom:12px">🎯 قلق رایگان روزانه</h3>
        <?php $freeTips = $pdo->query("SELECT id,title FROM tips WHERE status='published' AND access_type='free' ORDER BY created_at DESC LIMIT 40")->fetchAll(); ?>
        <div class="form-group"><label class="field-label">انتخاب قلق رایگان امروز</label>
          <select class="field" name="daily_free_tip_id">
            <option value="">— بدون قلق رایگان —</option>
            <?php foreach ($freeTips as $ft): ?><option value="<?=$ft['id']?>" <?=(int)($s['daily_free_tip_id'] ?? 0) === (int)$ft['id'] ? 'selected' : ''?>>#<?=fa($ft['id'])?> — <?=h(mb_substr($ft['title'],0,55))?></option><?php endforeach; ?>
          </select>
          <p class="muted" style="font-size:10px;margin-top:4px">این قلق در صفحه اصلی به‌صورت ویژه به‌عنوان «قلق رایگان امروز» نمایش داده می‌شود.</p>
        </div>
      </div>

      <div class="card" style="padding:18px;margin-bottom:16px">
        <h3 style="margin-bottom:12px">🔍 سئو (SEO)</h3>
        <div class="form-group"><label class="field-label">توضیحات متا (Meta Description)</label><textarea class="field" name="meta_description" rows="2"><?=h($s['meta_description'] ?? '')?></textarea></div>
        <div class="form-group"><label class="field-label">کلمات کلیدی (با کاما جدا کنید)</label><input class="field" name="meta_keywords" value="<?=h($s['meta_keywords'] ?? '')?>"></div>
        <div class="form-group"><label class="field-label">تصویر OpenGraph (آدرس کامل)</label><input class="field" dir="ltr" name="og_image" value="<?=h($s['og_image'] ?? '')?>"></div>
        <div class="form-group"><label class="field-label">کد Google Analytics (مثلاً G-XXXXXXX)</label><input class="field" dir="ltr" name="google_analytics" value="<?=h($s['google_analytics'] ?? '')?>"></div>
      </div>

      <button class="btn btn-primary btn-full" style="padding:13px">💾 ذخیره همه تنظیمات</button>
    </form>
    <?php
}

/* ---------------- COLLECT (ربات پیشرفته v5.0 — پنل حرفه‌ای) ---------------- */
elseif ($tab === 'collect') {
    require __DIR__ . '/admin_collect_v5.php';
}
?>
    </section>
  </div>
</main>
<?php footer_html(); exit; ?>
