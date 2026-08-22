<?php
/* Bordkhan — boards marketplace pages (included from index.php). */
$pdo = db();
$u = current_user();
$sub = $parts[1] ?? '';

/* ---------- browse ---------- */
if ($page === 'boards' && $sub === '') {
    $q = trim($_GET['q'] ?? '');
    $cat = (int)($_GET['cat'] ?? 0);
    $sort = $_GET['sort'] ?? 'newest';
    $where = ["b.status='approved'", 'b.stock>0'];
    $params = [];
    if ($q !== '') { $where[] = '(b.title LIKE ? OR b.description LIKE ? OR b.brand LIKE ? OR b.model LIKE ?)'; for ($i=0;$i<4;$i++) $params[] = '%'.$q.'%'; }
    if ($cat) { $where[] = '(b.category_id=? OR b.category_id IN (SELECT id FROM categories WHERE parent_id=?))'; $params[] = $cat; $params[] = $cat; }
    $order = $sort === 'price_asc' ? 'b.price ASC' : ($sort === 'price_desc' ? 'b.price DESC' : ($sort === 'views' ? 'b.views DESC' : 'b.created_at DESC'));
    $stmt = $pdo->prepare('SELECT b.*,u.name seller_name FROM boards b JOIN users u ON u.id=b.seller_id WHERE '.implode(' AND ',$where)." ORDER BY $order LIMIT 60");
    $stmt->execute($params);
    $items = $stmt->fetchAll();
    $leaves = leaf_categories();
    header_html('فروشگاه برد');
    ?><main class="wrap page">
      <div class="ptitle flex aicenter jbetween flex" style="flex-wrap:wrap;gap:12px"><div><h1>🏪 فروشگاه برد</h1><p>بردهای کارکرده، تعمیرشده یا سالم — خرید امن با امانت بردخان</p></div><?php if(is_seller($u ?? [])): ?><a class="btn btn-primary" href="<?=url('boards/new')?>">➕ ثبت برد جدید</a><?php elseif($u): ?><a class="btn btn-secondary" href="<?=url('seller-apply')?>">درخواست فروشندگی</a><?php endif; ?></div>
      <div class="layout">
        <aside class="card side">
          <h3>فیلترها</h3>
          <form method="get"><input type="hidden" name="r" value="boards">
            <div class="fgroup"><label class="flabel">جستجو</label><input class="field" name="q" value="<?=h($q)?>" placeholder=" عنوان، برند، مدل…"></div>
            <div class="fgroup"><label class="flabel">دسته‌بندی</label><select class="field" name="cat"><option value="">همه</option><?php foreach($leaves as $l): ?><option value="<?=$l['id']?>" <?=$cat===$l['id']?'selected':''?>><?=h($l['label'])?></option><?php endforeach; ?></select></div>
            <div class="fgroup"><label class="flabel">مرتب‌سازی</label><select class="field" name="sort"><option value="newest" <?=$sort==='newest'?'selected':''?>>جدیدترین</option><option value="price_asc" <?=$sort==='price_asc'?'selected':''?>>ارزان‌ترین</option><option value="price_desc" <?=$sort==='price_desc'?'selected':''?>>گران‌ترین</option><option value="views" <?=$sort==='views'?'selected':''?>>پربازدید</option></select></div>
            <button class="btn btn-primary btn-full">اعمال فیلتر</button>
          </form>
          <hr style="border-color:var(--line)"><p class="muted" style="font-size:11px">🛡 وجه شما ابتدا در امانت نزد بردخان نگه داده می‌شود و تنها پس از تأیید دریافت، به فروشنده واریز می‌گردد.</p>
        </aside>
        <div>
          <?php if(!$items): ?>
            <div class="card empty">هنوز بردی ثبت نشده است.<br><?php if(is_seller($u ?? [])):?><a class="btn btn-primary btn-sm mt" href="<?=url('boards/new')?>">اولین برد را ثبت کنید</a><?php endif; ?></div>
          <?php else: ?>
            <div class="grid g3"><?php foreach($items as $b) board_card($b); ?></div>
          <?php endif; ?>
        </div>
      </div>
    </main><?php footer_html(); exit;
}

/* ---------- create ---------- */
if ($page === 'boards' && $sub === 'new') {
    $user = require_login();
    if (!is_seller($user)) { flash('برای ثبت برد، ابتدا باید فروشنده تأییدشده باشید.', 'error'); redirect_to('seller-apply'); }
    $leaves = leaf_categories();
    header_html('ثبت برد برای فروش');
    ?><main class="wrap page"><div class="ptitle"><h1>➕ ثبت برد برای فروش</h1><p>اطلاعات برد را کامل وارد کنید؛ پس از تأیید مدیر منتشر می‌شود.</p></div>
    <form id="boardForm" class="card authc" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="board_create">
      <div class="fgroup"><label class="flabel">عنوان برد *</label><input class="field" name="title" required placeholder="مثلاً: برد تغذیه تلویزیون سامسونگ UA55F5500"></div>
      <div class="grid g2">
        <div class="fgroup"><label class="flabel">دسته‌بندی *</label><input class="field" type="text" placeholder="🔍 جستجوی زندهٔ دسته…" oninput="bkFilterSelect(this)"><select class="field" name="category_id" required><option value="">انتخاب کنید…</option><?php foreach($leaves as $l):?><option value="<?=$l['id']?>"><?=h($l['label'])?></option><?php endforeach;?></select></div>
        <div class="fgroup"><label class="flabel">وضعیت</label><select class="field" name="condition_status"><option value="new">نو</option><option value="like_new">در حد نو</option><option value="used" selected>کارکرده</option><option value="repair">تعمیرشده</option></select></div>
        <div class="fgroup"><label class="flabel">برند</label><input class="field" name="brand" placeholder="Samsung"></div>
        <div class="fgroup"><label class="flabel">مدل</label><input class="field" name="model" placeholder="UA55F5500"></div>
        <div class="fgroup"><label class="flabel">قیمت (تومان) *</label><input class="field" type="number" name="price" min="1000" step="1000" required></div>
        <div class="fgroup"><label class="flabel">تعداد موجودی</label><input class="field" type="number" name="stock" min="1" value="1"></div>
      </div>
      <div class="fgroup"><label class="flabel">توضیح کامل *</label><textarea class="field" name="description" rows="6" required placeholder="وضعیت فنی، قطعات روی برد، تاریخ تعمیر و…"></textarea></div>
      <div class="fgroup"><label class="flabel">عکس‌های برد (حداقل ۱، تا ۸)</label><input class="field" id="boardImages" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required><div id="boardPreview" class="file-preview"></div></div>
      <div class="fgroup"><label class="flabel">لینک ویدیو اختیاری</label><input class="field" dir="ltr" name="video_url" placeholder="YouTube / Aparat"></div>
      <div class="bk-checks" id="boardChecks"></div>
      <div id="boardBarWrap" style="display:none;margin-top:12px;background:rgba(255,255,255,.08);border-radius:8px;overflow:hidden;height:18px"><div id="boardBar" style="width:0%;height:100%;background:#10b981;color:#04110b;text-align:center;font-size:11px;line-height:18px;font-weight:bold">0%</div></div>
      <div id="boardFormMsg"></div>
      <button class="btn btn-primary btn-full">ثبت برد</button>
    </form><script>
if(typeof bkSetupChecks!=='function'){function bkSetupChecks(formId,boxId,rules){var f=document.getElementById(formId),box=document.getElementById(boxId);if(!f||!box)return;function ev(n){return f.querySelector('[name="'+n+'"]');}function val(n){var el=ev(n);if(!el)return'';if(el.type==='file')return Array.prototype.slice.call(el.files||[]);return el.value||'';}var items=rules.map(function(r){var d=document.createElement('div');d.className='bk-check';d.innerHTML='<span class="bk-ic">◌</span> '+r.label;box.appendChild(d);return d;});rules.forEach(function(r){if(r.kind==='minlen'){var el=ev(r.name);if(el){var c=document.createElement('small');c.className='bk-count';c.style.cssText='display:block;color:var(--text-dim);font-size:11px;margin-top:4px';el.parentNode.insertBefore(c,el.nextSibling);r.counter=c;}}});function okOf(i,r){var v=val(r.name);if(r.kind==='minlen')return (v||'').trim().length>=r.n;if(r.kind==='required')return String(v).trim()!=='';if(r.kind==='select')return String(v)!=='';if(r.kind==='files')return v.length>=r.n;if(r.kind==='price'){var at=ev('access_type');if(at&&at.value!=='paid')return true;return parseInt(v,10)>=r.n;}return true;}function update(){var cnt=0;rules.forEach(function(r,i){var ok=okOf(i,r);items[i].className='bk-check '+(ok?'ok':'bad');items[i].querySelector('.bk-ic').textContent=ok?'✓':'✗';var el=ev(r.name);if(el){el.classList.remove('bk-ok','bk-bad');el.classList.add(ok?'bk-ok':'bk-bad');}if(r.kind==='minlen'&&r.counter){var len=(val(r.name)||'').trim().length;r.counter.textContent=len+' / '+r.n+' حرف'+(len>=r.n?' ✔':'');}if(ok)cnt++;});var s=document.getElementById(boxId+'Sum');if(s){s.textContent=cnt+' از '+rules.length+' مورد تکمیل شد';s.className='bk-summary '+(cnt===rules.length?'ok':'bad');}}rules.forEach(function(r){var el=ev(r.name);if(!el)return;['input','change'].forEach(function(t){el.addEventListener(t,update);});});var s=document.createElement('div');s.id=boxId+'Sum';s.className='bk-summary';box.parentNode.insertBefore(s,box.nextSibling);update();}}
bkSetupChecks('boardForm','boardChecks',[
{name:'title',kind:'minlen',n:5,label:'عنوان برد (حداقل ۵ حرف)'},
{name:'description',kind:'minlen',n:10,label:'توضیح برد (حداقل ۱۰ حرف)'},
{name:'category_id',kind:'select',label:'انتخاب دسته‌بندی'},
{name:'price',kind:'price',n:1000,label:'قیمت (حداقل ۱۰۰۰ تومان)'},
{name:'images',kind:'files',n:1,label:'حداقل ۱ عکس'}
]);
if(typeof bkFilterSelect!=='function'){function bkFilterSelect(inp){var sel=inp.parentElement.querySelector('select');if(!sel)return;var q=(inp.value||'').trim().toLowerCase();Array.prototype.forEach.call(sel.options,function(o){if(!o.value)return;var t=(o.textContent||'').toLowerCase();var og=o.parentElement&&o.parentElement.label?o.parentElement.label.toLowerCase():'';o.hidden=q===''||t.indexOf(q)!==-1||og.indexOf(q)!==-1;});Array.prototype.forEach.call(sel.querySelectorAll('optgroup'),function(g){var any=false;Array.prototype.forEach.call(g.options,function(o){if(!o.hidden)any=true;});g.hidden=!any;});if(!sel.value||sel.options[sel.selectedIndex]&&sel.options[sel.selectedIndex].hidden){sel.value='';}}}
(function(){var f=document.getElementById('boardForm');if(!f)return;var msg=document.getElementById('boardFormMsg');var bar=document.getElementById('boardBar');var barWrap=document.getElementById('boardBarWrap');var fi=document.getElementById('boardImages');var pv=document.getElementById('boardPreview');if(fi&&pv){fi.addEventListener('change',function(){pv.innerHTML='';Array.prototype.forEach.call(fi.files,function(file){if(!/^image\//.test(file.type||''))return;var u=URL.createObjectURL(file);var img=document.createElement('img');img.src=u;img.alt='پیش‌نمایش';img.style.cssText='width:86px;height:86px;object-fit:cover;border-radius:10px;border:1px solid var(--line)';pv.appendChild(img);});});}f.addEventListener('submit',function(e){e.preventDefault();var b=f.querySelector('button');var orig=b?b.textContent:'';if(b){b.disabled=true;b.textContent='⏳ در حال ارسال…';}msg.innerHTML='';if(barWrap)barWrap.style.display='block';if(bar){bar.style.width='0%';bar.textContent='0%';}var xhr=new XMLHttpRequest();xhr.open('POST',window.location.href);xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');xhr.setRequestHeader('Accept','application/json');xhr.upload.addEventListener('progress',function(ev){if(ev.lengthComputable&&bar){var p=Math.round(ev.loaded/ev.total*100);bar.style.width=p+'%';bar.textContent=p+'%';}});xhr.onload=function(){if(barWrap)barWrap.style.display='none';var j=null;try{j=JSON.parse(xhr.responseText);}catch(_){}if(j&&j.ok){msg.innerHTML='<div class="notice" style="margin-top:12px">✅ '+(j.message||'انجام شد')+'</div>';if(j.redirect){setTimeout(function(){window.location.href=j.redirect;},1200);}else if(b){b.disabled=false;b.textContent=orig;}}else{msg.innerHTML='<div class="notice error" style="margin-top:12px">⚠️ '+((j&&j.error)||'پاسخی از سرور دریافت نشد؛ دوباره تلاش کنید.')+'</div>';if(b){b.disabled=false;b.textContent=orig;}}};xhr.onerror=function(){if(barWrap)barWrap.style.display='none';msg.innerHTML='<div class="notice error" style="margin-top:12px">⚠️ خطای ارتباط با سرور؛ دوباره تلاش کنید.</div>';if(b){b.disabled=false;b.textContent=orig;}};xhr.send(new FormData(f));});})();
</script></main><?php footer_html(); exit;
}

/* ---------- seller's own boards ---------- */
if ($page === 'my-boards') {
    $user = require_login();
    $boards = $pdo->prepare('SELECT b.* FROM boards b WHERE b.seller_id=? ORDER BY b.created_at DESC');
    $boards->execute([$user['id']]);
    $boards = $boards->fetchAll();
    $sales = $pdo->prepare("SELECT o.*,b.title,b.buyer_id,u.name buyer_name FROM board_orders o JOIN boards b ON b.id=o.board_id JOIN users u ON u.id=o.buyer_id WHERE o.seller_id=? ORDER BY o.created_at DESC LIMIT 50");
    $sales->execute([$user['id']]);
    $sales = $sales->fetchAll();
    header_html('بردهای من');
    ?><main class="wrap page">
      <div class="ptitle flex aicenter jbetween" style="flex-wrap:wrap;gap:12px"><div><h1>بردهای من</h1><p><?=fa(count($boards))?> برد ثبت‌شده</p></div><a class="btn btn-primary" href="<?=url('boards/new')?>">➕ ثبت برد جدید</a></div>
      <div class="card tablewrap"><table class="table"><tr><th>#</th><th>برد</th><th>قیمت</th><th>موجودی</th><th>وضعیت</th><th>بازدید</th></tr><?php foreach($boards as $b):?><tr><td><?=fa($b['id'])?></td><td><a class="check" href="<?=url('board/'.$b['id'])?>"><?=h(mb_substr($b['title'],0,45))?></a></td><td><?=money($b['price'])?></td><td><?=fa($b['stock'])?></td><td><span class="pill <?=in_array($b['status'],['approved'])?'green':($b['status']==='pending'?'amber':'rose')?>"><?=h(board_status_label($b['status']))?></span></td><td><?=fa($b['views'])?></td></tr><?php endforeach;?></table></div>
      <section class="section"><h2 class="sec-head" style="font-size:17px">سفارش‌های فروش</h2>
      <div class="card tablewrap"><table class="table"><tr><th>#</th><th>برد</th><th>خریدار</th><th>مبلغ</th><th>سهم شما</th><th>وضعیت</th><th>عملیات</th></tr>
      <?php foreach($sales as $o):?><tr>
        <td><?=fa($o['id'])?></td><td><?=h(mb_substr($o['title'],0,35))?></td><td><?=h($o['buyer_name'])?></td>
        <td><?=money($o['amount'])?></td><td class="check" style="font-weight:bold"><?=money($o['net_amount'])?></td>
        <td><span class="pill <?=$o['status']==='completed'?'green':($o['status']==='cancelled'?'rose':'amber')?>"><?=h(order_status_label($o['status']))?></span></td>
        <td><?php if($o['status']==='paid'):?><form method="post" style="display:flex;gap:6px;flex-wrap:wrap"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="board_ship"><input type="hidden" name="order_id" value="<?=$o['id']?>"><select class="field" name="carrier" required><option value="">شرکت حمل</option><option>پست</option><option>تیپاکس</option><option>باربری</option><option>پیک</option></select><input class="field" style="height:34px;width:150px;padding:6px" name="tracking_code" placeholder="کد رهگیری اجباری" required><button class="btn btn-primary btn-sm">📦 ثبت ارسال</button></form><?php elseif($o['status']==='shipped'):?><span class="muted" style="font-size:11px">در انتظار تأیید خریدار</span><?php endif;?></td>
      </tr><?php endforeach;?></table></div></section>
    </main><?php footer_html(); exit;
}

/* ---------- board detail ---------- */
if ($page === 'board' && $id) {
    $stmt = $pdo->prepare('SELECT b.*,u.name seller_name,u.verified seller_verified FROM boards b JOIN users u ON u.id=b.seller_id WHERE b.id=? LIMIT 1');
    $stmt->execute([$id]);
    $b = $stmt->fetch();
    if (!$b) { header_html('یافت نشد'); ?><main class="wrap page"><div class="card empty">برد یافت نشد.</div></main><?php footer_html(); exit; }
    if ($b['status'] !== 'approved' && (!staff($u) && (int)($u['id'] ?? 0) !== (int)$b['seller_id'])) { header_html('در دسترس نیست'); ?><main class="wrap page"><div class="card empty">این برد در انتظار تأیید است.</div></main><?php footer_html(); exit; }
    $pdo->prepare('UPDATE boards SET views=views+1 WHERE id=?')->execute([$id]);
    $imgs = json_decode_array($b['images_json'] ?? '[]');
    $myOrder = null;
    if ($u) { $q = $pdo->prepare("SELECT * FROM board_orders WHERE board_id=? AND buyer_id=? AND status IN('paid','shipped') ORDER BY id DESC LIMIT 1"); $q->execute([$id, $u['id']]); $myOrder = $q->fetch(); }
    header_html($b['title']);
    ?><main class="wrap page">
      <div class="crumbs"><a href="<?=url()?>">خانه</a> / <a href="<?=url('boards')?>">فروشگاه</a> / <?=h($b['title'])?></div>
      <div class="tlayout">
        <article>
          <div class="card authc">
            <div class="tmeta"><span class="pill green"><?=h(board_condition_label($b['condition_status']))?></span><?php if($b['brand']):?><span class="pill"><?=h($b['brand'])?></span><?php endif;?><?php if($b['model']):?><span class="pill"><?=h($b['model'])?></span><?php endif;?><span class="pill">👁 <?=fa($b['views'])?></span></div>
            <h1 class="ttitle"><?=h($b['title'])?></h1>
            <div class="author"><span class="avatar"><?=h(mb_substr($b['seller_name'],0,1))?></span><span class="author-info"><strong><?=h($b['seller_name'])?></strong><small><?php if($b['seller_verified']):?>تعمیرکار تأییدشده ✓<?php else:?>فروشنده تأییدشده<?php endif;?></small></span></div>
          </div>
          <div class="tcover"><?php foreach($imgs as $i=>$img):?><div class="mp"><img src="<?=h($img)?>" alt="تصویر <?=fa($i+1)?>" class="no-save" draggable="false"><span class="wm">© بردخان</span></div><?php endforeach;?></div>
          <div class="card authc mt"><h3 style="margin-bottom:8px">توضیحات</h3><div class="rich"><?=safe_rich($b['description'])?></div>
          <?php if($b['video_url']): $ve=video_embed((string)$b['video_url'], ['id'=>(int)$b['id']], $u); ?><?=$ve?><?php endif; ?></div>
        </article>
        <aside>
          <div class="card side">
            <p class="muted" style="font-size:11px;margin:0 0 6px">قیمت فروش</p>
            <p style="font-size:27px;font-weight:900;color:var(--accent);margin:0"><?=money($b['price'])?> <small style="font-size:14px;font-weight:normal;color:var(--text-dim)">تومان</small></p>
            <p class="muted" style="font-size:12px;margin:8px 0 14px">موجودی: <?=fa($b['stock'])?> عدد · فروخته‌شده: <?=fa($b['sold_count'])?></p>
            <div class="notice" style="font-size:11px;line-height:2;margin-bottom:14px">🛡 <b>خرید امن با امانت:</b> وجه شما ابتدا نزد بردخان نگه داشته می‌شود. پس از دریافت و تأیید سلامت برد، دکمه «تأیید دریافت» را بزنید تا سهم فروشنده واریز شود.</div>
            <?php if(!$u): ?>
              <a class="btn btn-primary btn-full" href="<?=url('login')?>">ورود برای خرید</a>
            <?php elseif((int)$b['seller_id']===(int)$u['id']): ?>
              <p class="muted" style="text-align:center;font-size:12px">این برد شماست.</p>
            <?php elseif($myOrder): ?>
              <p class="notice">سفارش شما #<?=fa($myOrder['id'])?> — <?=h(order_status_label($myOrder['status']))?></p>
              <?php if($myOrder['tracking_code']):?><p class="muted" style="font-size:12px">کد رهگیری: <b dir="ltr"><?=h($myOrder['tracking_code'])?></b></p><?php endif;?>
              <?php if($myOrder['status']==='shipped'):?><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="board_confirm"><input type="hidden" name="order_id" value="<?=h((string)$myOrder['id'])?>"><button class="btn btn-primary btn-full" onclick="return confirm('از صحت دریافت برد مطمئن هستید؟ پس از تأیید، وجه به فروشنده واریز می‌شود.')">✔ تأیید دریافت برد</button></form><?php endif;?>
            <?php elseif($b['stock']>0): ?>
              <form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="board_buy"><input type="hidden" name="board_id" value="<?=$id?>"><button class="btn btn-primary btn-full btn-lg" onclick="return confirm('مبلغ <?=money($b['price'])?> تومان از کیف پول شما کسر و در امانت نگه داشته می‌شود. ادامه می‌دهید؟')">🛒 خرید با امانت</button></form>
              <p class="muted" style="font-size:11px;text-align:center;margin-top:6px">موجودی شما: <?=money($u['balance'])?> تومان</p>
            <?php else: ?>
              <p class="error" style="text-align:center">موجودی تمام شده است</p>
            <?php endif; ?>
          </div>
        </aside>
      </div>
    </main><?php footer_html(); exit;
}

http_response_code(404);
header_html('یافت نشد');
?><main class="wrap page"><div class="card empty">صفحه یافت نشد.</div></main><?php
footer_html();
exit;
