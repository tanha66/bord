<?php
/**
 * Bordkhan — مدیریت تکمیلی برای مدیر:
 *  - بردها: افزودن، ویرایش، حذف
 *  - قلق‌ها: افزودن، ویرایش (روی admin_tip_edit اصلی) و حذف
 *  - کاربران: افزودن، ویرایش کامل، حذف، تعدیل موجودی، تعیین رمز جدید
 * صفحات: /admin-boards ، /admin-users ، /admin-tips
 * روی همان دیتابیس نصب اصلی کار می‌کند.
 */
require_once __DIR__ . '/bk_extended.php';

function bkx_admin(): array { return function_exists('require_admin') ? require_admin() : bk_admin(); }
function bkx_clean(string $v): string { return function_exists('clean_text') ? clean_text($v) : trim(strip_tags($v)); }
function bkx_col(PDO $pdo, string $t, string $c): bool { $q=$pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'); $q->execute([$t,$c]); return (bool)$q->fetchColumn(); }

$admin = bkx_admin();
$pdo = db();
$notice = '';
$act = $_POST['xaction'] ?? '';

if ($act) {
    if (function_exists('check_csrf')) check_csrf();

    /* ---------- BOARDS ---------- */
    if ($act === 'board_save') {
        $bid = (int)($_POST['board_id'] ?? 0);
        $title = bkx_clean($_POST['title'] ?? ''); $desc = trim($_POST['description'] ?? '');
        $cat = (int)($_POST['category_id'] ?? 0); $price = max(0, (int)($_POST['price'] ?? 0));
        $stock = max(0, (int)($_POST['stock'] ?? 1)); $cond = $_POST['condition_status'] ?? 'used';
        $status = in_array($_POST['status'] ?? 'approved', ['pending','approved','sold','rejected','archived'], true) ? $_POST['status'] : 'approved';
        if (mb_strlen($title) < 5 || $price <= 0) { $notice = 'عنوان و قیمت الزامی است.'; }
        else {
            $imgs = [];
            foreach (($_FILES['images']['tmp_name'] ?? []) as $i => $tmp) {
                if (($_FILES['images']['error'][$i] ?? 1) !== UPLOAD_ERR_OK) continue;
                if (function_exists('save_image')) { $s = save_image(['tmp_name'=>$tmp,'error'=>0,'size'=>$_FILES['images']['size'][$i]??0,'type'=>'']); if ($s) $imgs[] = $s; }
            }
            if ($bid) {
                $sql = "UPDATE boards SET category_id=?,title=?,description=?,brand=?,model=?,condition_status=?,price=?,stock=?,status=?";
                $par = [$cat,$title,$desc,bkx_clean($_POST['brand']??''),bkx_clean($_POST['model']??''),$cond,$price,$stock,$status];
                if ($imgs) { $sql .= ",images_json=?"; $par[] = json_encode($imgs, JSON_UNESCAPED_UNICODE); }
                $sql .= " WHERE id=?"; $par[] = $bid;
                $pdo->prepare($sql)->execute($par); $notice = 'برد ویرایش شد.';
            } else {
                if (!$imgs) $imgs[] = 'data:image/svg+xml;utf8,' . rawurlencode("<svg xmlns='http://www.w3.org/2000/svg' width='400' height='300'><rect width='400' height='300' fill='#0b1119'/><text x='200' y='160' font-size='22' text-anchor='middle' fill='#64748b' font-family='Tahoma'>بردخان</text></svg>");
                $pdo->prepare("INSERT INTO boards(seller_id,category_id,title,description,brand,model,condition_status,price,stock,images_json,status) VALUES(?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$admin['id'],$cat,$title,$desc,bkx_clean($_POST['brand']??''),bkx_clean($_POST['model']??''),$cond,$price,$stock,json_encode($imgs,JSON_UNESCAPED_UNICODE),$status]);
                $notice = 'برد جدید افزوده شد.';
            }
        }
    }
    if ($act === 'board_del') { $pdo->prepare('DELETE FROM boards WHERE id=?')->execute([(int)$_POST['board_id']]); $notice = 'برد حذف شد.'; }

    /* ---------- USERS ---------- */
    if ($act === 'user_save') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $name = bkx_clean($_POST['name'] ?? ''); $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        $role = in_array($_POST['role'] ?? 'member', ['member','expert','moderator','admin','superadmin'], true) ? $_POST['role'] : 'member';
        $verified = !empty($_POST['verified']) ? 1 : 0; $banned = !empty($_POST['is_banned']) ? 1 : 0;
        $sellerStatus = in_array(($_POST['seller_status'] ?? 'none'), ['none','pending','approved','rejected'], true) ? ($_POST['seller_status'] ?? 'none') : 'none';
        if ($sellerStatus === '' || $sellerStatus === null) $sellerStatus = 'none';
        $blocked = false;
        if ($uid) {
            $q = $pdo->prepare('SELECT role FROM users WHERE id=?'); $q->execute([$uid]); $target = $q->fetch();
            if ($target && $target['role'] === 'superadmin' && $admin['role'] !== 'superadmin') { $notice = 'فقط سوپرادمین می‌تواند حساب سوپرادمین را تغییر دهد.'; $blocked = true; }
            if ($target && $uid === (int)$admin['id'] && $role !== $admin['role']) { $notice = 'نمی‌توانید نقش خودتان را تغییر دهید.'; $blocked = true; }
            if ($target && $uid === (int)$admin['id'] && $banned) { $notice = 'نمی‌توانید حساب خودتان را مسدود کنید.'; $blocked = true; }
        }
        if ($blocked) { /* skip */ }
        elseif (mb_strlen($name) < 2 || !preg_match('/^09\d{9}$/', $phone)) { $notice = 'نام و موبایل معتبر لازم است.'; }
        else {
            if ($uid) {
                $pdo->prepare('UPDATE users SET name=?,phone=?,role=?,verified=?,is_banned=?,seller_status=? WHERE id=?')
                    ->execute([$name,$phone,$role,$verified,$banned,$sellerStatus,$uid]);
                if (!empty($_POST['password'])) $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), $uid]);
                $notice = 'کاربر ویرایش شد.';
            } else {
                $chk = $pdo->prepare('SELECT id FROM users WHERE phone=?'); $chk->execute([$phone]);
                if ($chk->fetch()) { $notice = 'این موبایل قبلاً ثبت شده است.'; }
                else {
                    $pdo->prepare('INSERT INTO users(phone,password_hash,name,role,referral_code,phone_verified,verified,seller_status) VALUES(?,?,?,?,?,1,?,?)')
                        ->execute([$phone, password_hash($_POST['password'] ?: '123456', PASSWORD_DEFAULT), $name, $role, 'U'.strtoupper(bin2hex(random_bytes(3))), $verified, $sellerStatus]);
                    $notice = 'کاربر جدید افزوده شد.';
                }
            }
        }
    }
    if ($act === 'user_del') { $uid=(int)$_POST['user_id']; if ($uid === (int)$admin['id']) { $notice='حساب خودتان را حذف نکنید.'; } else { $q=$pdo->prepare('SELECT role FROM users WHERE id=?');$q->execute([$uid]);$t=$q->fetch(); if ($t && $t['role']==='superadmin' && $admin['role']!=='superadmin') { $notice='فقط سوپرادمین می‌تواند حساب سوپرادمین را حذف کند.'; } elseif (bkx_col($pdo,'users','is_deleted')) { $pdo->prepare("UPDATE users SET is_deleted=1, phone=CONCAT('del-',id,'-',phone), email=LEFT(CONCAT('del-',id,'-',COALESCE(email,'')),190) WHERE id=?")->execute([$uid]); $notice='کاربر حذف شد (محتواهای او حفظ می‌شود).'; } else { $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$uid]); $notice='کاربر حذف شد.'; } } }
    if ($act === 'user_balance') {
        $uid=(int)$_POST['user_id']; $delta=(int)($_POST['delta']??0);
        if ($delta) { $pdo->prepare('UPDATE users SET balance=GREATEST(0,balance+?) WHERE id=?')->execute([$delta,$uid]);
            if (bkx_col($pdo,'wallet_transactions','balance_after')) { $q=$pdo->prepare('SELECT balance FROM users WHERE id=?');$q->execute([$uid]);$b=(int)$q->fetchColumn(); @$pdo->prepare('INSERT INTO wallet_transactions(user_id,type,amount,balance_after,note) VALUES(?,?,?,?,?)')->execute([$uid,'admin_adjust',$delta,$b,'تعدیل توسط مدیر']); }
            $notice='موجودی تعدیل شد.'; }
    }

    /* ---------- TIPS ---------- */
    if ($act === 'tip_save') {
        $tid=(int)($_POST['tip_id']??0); $title=bkx_clean($_POST['title']??''); $short=bkx_clean($_POST['short_description']??'');
        $desc=trim($_POST['description']??''); $cat=(int)($_POST['category_id']??0);
        $access=in_array($_POST['access_type']??'free',['free','like','paid'],true)?$_POST['access_type']:'free'; $price=max(0,(int)($_POST['price']??0));
        $status=in_array($_POST['status']??'published',['draft','pending','published','rejected'],true)?$_POST['status']:'published';
        if (mb_strlen($title)<5) { $notice='عنوان قلق الزامی است.'; }
        else if ($tid) {
            $pdo->prepare('UPDATE tips SET title=?,short_description=?,description=?,category_id=?,access_type=?,price=?,status=?,published_at=IF(?="published",COALESCE(published_at,NOW()),published_at) WHERE id=?')
                ->execute([$title,$short,$desc,$cat,$access,$price,$status,$status,$tid]); $notice='قلق ویرایش شد.';
        } else {
            $pdo->prepare('INSERT INTO tips(author_id,category_id,title,short_description,description,device_name,brand,fault_type,solution_json,access_type,price,status,published_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,IF(?="published",NOW(),NULL))')
                ->execute([$admin['id'],$cat,$title,$short?:$title,$desc?:$title,bkx_clean($_POST['device_name']??'عمومی'),bkx_clean($_POST['brand']??''),bkx_clean($_POST['fault_type']??'سایر'),'[]',$access,$price,$status,$status]);
            $notice='قلق جدید افزوده شد.';
        }
    }
    if ($act === 'tip_del') { $pdo->prepare('DELETE FROM tips WHERE id=?')->execute([(int)$_POST['tip_id']]); $notice='قلق حذف شد.'; }
}

/* ---------- routing to sub-pages ---------- */
$sub = $GLOBALS['bkx_page'] ?? '';
$cats = $pdo->query("SELECT id,name FROM categories ORDER BY name LIMIT 100")->fetchAll();
$condLabels = ['new'=>'نو','refurb'=>'بازسازی','used'=>'دست‌دوم','faulty'=>'معیوب','like_new'=>'درحد نو','repair'=>'برای تعمیر'];

header_html($sub==='admin-users'?'مدیریت کاربران':($sub==='admin-tips'?'مدیریت قلق‌ها':'مدیریت بردها'));
echo '<main class="wrap page">';
if ($notice) echo '<div class="notice" style="margin-bottom:14px">'.h($notice).'</div>';

/* ===== BOARDS ADMIN ===== */
if ($sub === 'admin-boards') {
    $eid = (int)($_GET['edit'] ?? 0); $edit = null;
    if ($eid) { $q=$pdo->prepare('SELECT * FROM boards WHERE id=?'); $q->execute([$eid]); $edit=$q->fetch(); }
    $items = $pdo->query("SELECT b.*,u.name sn FROM boards b JOIN users u ON u.id=b.seller_id ORDER BY b.id DESC LIMIT 200")->fetchAll();
    ?>
    <div class="flex between items-center" style="margin-bottom:16px"><h1 style="font-size:22px;font-weight:900">مدیریت بردها</h1><a class="btn btn-secondary btn-sm" href="<?=url('admin?tab=boards')?>">بازگشت به پنل</a></div>
    <div class="card authc" style="padding:18px;margin-bottom:20px"><h3 style="margin-bottom:12px"><?=$edit?'ویرایش برد #'.fa($edit['id']):'افزودن برد جدید'?></h3>
    <form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="xaction" value="board_save"><input type="hidden" name="board_id" value="<?=$edit['id']??0?>">
      <div class="grid grid-2"><div class="fgroup"><label class="field-label">عنوان</label><input class="field" name="title" value="<?=h($edit['title']??'')?>" required></div>
      <div class="fgroup"><label class="field-label">قیمت (تومان)</label><input class="field" type="number" name="price" value="<?=(int)($edit['price']??0)?>" required></div>
      <div class="fgroup"><label class="field-label">دسته</label><select class="field" name="category_id"><?php foreach($cats as $c):?><option value="<?=$c['id']?>" <?=($edit['category_id']??0)==$c['id']?'selected':''?>><?=h($c['name'])?></option><?php endforeach;?></select></div>
      <div class="fgroup"><label class="field-label">وضعیت کالا</label><select class="field" name="condition_status"><?php foreach(['new','refurb','used','faulty'] as $k):?><option value="<?=$k?>" <?=($edit['condition_status']??'used')===$k?'selected':''?>><?=h($condLabels[$k])?></option><?php endforeach;?></select></div>
      <div class="fgroup"><label class="field-label">موجودی</label><input class="field" type="number" name="stock" value="<?=(int)($edit['stock']??1)?>"></div>
      <div class="fgroup"><label class="field-label">وضعیت انتشار</label><select class="field" name="status"><?php foreach(['approved'=>'تأییدشده','pending'=>'در انتظار','rejected'=>'ردشده','archived'=>'بایگانی','sold'=>'فروخته‌شده'] as $k=>$v):?><option value="<?=$k?>" <?=($edit['status']??'approved')===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></div></div>
      <div class="fgroup"><label class="field-label">توضیح</label><textarea class="field" name="description" rows="4"><?=h($edit['description']??'')?></textarea></div>
      <div class="fgroup"><label class="field-label">تصاویر (اختیاری)</label><input class="field" type="file" name="images[]" accept="image/*" multiple></div>
      <button class="btn btn-primary"><?=$edit?'ذخیره تغییرات':'افزودن برد'?></button> <?php if($edit):?><a class="btn btn-secondary" href="<?=url('admin-boards')?>">انصراف</a><?php endif;?>
    </form></div>
    <div class="card tablewrap"><table class="table"><tr><th>#</th><th>برد</th><th>فروشنده</th><th>قیمت</th><th>وضعیت</th><th>عملیات</th></tr>
    <?php foreach($items as $x):?><tr><td><?=fa($x['id'])?></td><td><a class="check" href="<?=url('board/'.$x['id'])?>"><?=h(mb_substr($x['title'],0,40))?></a></td><td><?=h($x['sn'])?></td><td><?=money($x['price'])?></td><td><span class="pill <?=$x['status']==='approved'?'green':($x['status']==='pending'?'amber':'rose')?>"><?=h(board_status_label($x['status']))?></span></td>
      <td style="display:flex;gap:4px"><a class="btn btn-secondary btn-sm" href="<?=url('admin-boards',['edit'=>$x['id']])?>">ویرایش</a>
      <form method="post" onsubmit="return confirm('حذف شود؟')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="xaction" value="board_del"><input type="hidden" name="board_id" value="<?=$x['id']?>"><button class="btn btn-danger btn-sm">حذف</button></form></td></tr><?php endforeach;?></table></div>
    <?php
}

/* ===== USERS ADMIN (مدیریت پیشرفته کاربران — v5.1) ===== */
elseif ($sub === 'admin-users') {
    $eid = (int)($_GET['edit'] ?? 0); $edit=null;
    if ($eid) { $q=$pdo->prepare('SELECT * FROM users WHERE id=?'); $q->execute([$eid]); $edit=$q->fetch(); }

    /* KPI کاربران */
    $safe_u = function (string $sql) use ($pdo) { try { $v=$pdo->query($sql)->fetchColumn(); return $v===false||$v===null?0:$v; } catch (Throwable $e) { return 0; } };
    $ukpi = [
        'total' => (int)$safe_u('SELECT COUNT(*) FROM users'),
        'banned' => (int)$safe_u('SELECT COUNT(*) FROM users WHERE is_banned=1'),
        'premium' => (int)$safe_u('SELECT COUNT(*) FROM users WHERE premium_until > NOW()'),
        'sellers' => (int)$safe_u("SELECT COUNT(*) FROM users WHERE seller_status='approved'"),
        'experts' => (int)$safe_u("SELECT COUNT(*) FROM users WHERE role IN ('expert','moderator','admin','superadmin')"),
        'week' => (int)$safe_u('SELECT COUNT(*) FROM users WHERE created_at >= DATE(NOW())-INTERVAL 7 DAY'),
    ];

    /* فیلتر و صفحه‌بندی */
    $qsearch = trim($_GET['q'] ?? ''); $urole=$_GET['role']??''; $ustat=$_GET['status']??''; $upage=max(1,(int)($_GET['p']??1)); $uper=25;
    $w=[]; $par=[];
    if ($qsearch) { $w[]='(name LIKE ? OR phone LIKE ? OR IFNULL(email,"") LIKE ?)'; $par=["%$qsearch%","%$qsearch%","%$qsearch%"]; }
    if (in_array($urole,['member','expert','moderator','admin','superadmin'],true)) { $w[]='role=?'; $par[]=$urole; }
    if ($ustat==='banned') $w[]='is_banned=1'; elseif ($ustat==='verified') $w[]='verified=1'; elseif ($ustat==='premium') $w[]='premium_until > NOW()'; elseif ($ustat==='seller') $w[]="seller_status='approved'";
    $wsql = $w ? 'WHERE '.implode(' AND ',$w) : '';
    $totalU=0; try { $c=$pdo->prepare("SELECT COUNT(*) FROM users $wsql"); $c->execute($par); $totalU=(int)$c->fetchColumn(); } catch (Throwable $e) {}
    $pages=max(1,(int)ceil($totalU/$uper)); $upage=min($upage,$pages); $off=($upage-1)*$uper;
    $st=$pdo->prepare("SELECT u.*,(SELECT COUNT(*) FROM tips t WHERE t.author_id=u.id) tips_count FROM users u $wsql ORDER BY u.id DESC LIMIT $uper OFFSET $off");
    $st->execute($par); $items=$st->fetchAll();
    $roles=['member'=>'کاربر','expert'=>'تعمیرکار','moderator'=>'ناظر','admin'=>'مدیر','superadmin'=>'سوپرادمین'];

    /* آمار کاربرِ در حال ویرایش */
    $editStats = null; $editTx = [];
    if ($edit) {
        try {
            $qs=$pdo->prepare('SELECT (SELECT COUNT(*) FROM tips WHERE author_id=?) tips,(SELECT COUNT(*) FROM tip_accesses WHERE user_id=?) purchases,(SELECT COUNT(*) FROM comments WHERE user_id=?) comments');
            $qs->execute([$edit['id'],$edit['id'],$edit['id']]); $editStats=$qs->fetch();
        } catch (Throwable $e) { $editStats=['tips'=>0,'purchases'=>0,'comments'=>0]; }
        try { $qt=$pdo->prepare('SELECT type,amount,balance_after,note,created_at FROM wallet_transactions WHERE user_id=? ORDER BY id DESC LIMIT 6'); $qt->execute([$edit['id']]); $editTx=$qt->fetchAll(); } catch (Throwable $e) {}
    }
    ?>
    <div class="flex between items-center" style="margin-bottom:16px;flex-wrap:wrap;gap:10px">
      <h1 style="font-size:22px;font-weight:900">🧑‍💼 مدیریت پیشرفته کاربران</h1>
      <div class="flex gap" style="gap:6px"><a class="btn btn-secondary btn-sm" href="<?=url('admin?tab=users')?>">پنل سریع کاربران</a><a class="btn btn-secondary btn-sm" href="<?=url('admin')?>">بازگشت به داشبورد</a></div>
    </div>

    <div class="admin-cards">
      <?php foreach ([['کل کاربران',$ukpi['total'],'👥',''],['هفت روز اخیر',$ukpi['week'],'🆕','color:#078659'],['تعمیرکار و مدیران',$ukpi['experts'],'🛠',''],['اشتراک ویژه',$ukpi['premium'],'⭐','color:#b8860b'],['فروشنده فعال',$ukpi['sellers'],'🏪',''],['مسدودشده',$ukpi['banned'],'🚫','color:#b3261e']] as $uk): ?>
      <div class="card"><div class="k"><?=$uk[2]?> <?=h($uk[0])?></div><div class="v" style="<?=$uk[3]?>"><?=fa(number_format($uk[1]))?></div></div>
      <?php endforeach; ?>
    </div>

    <?php if ($edit): ?>
    <!-- ===== کارت جزئیات کاربر ===== -->
    <div class="card" style="padding:18px;margin-bottom:18px">
      <div class="flex between items-center" style="flex-wrap:wrap;gap:10px;margin-bottom:12px">
        <h3 style="margin:0">👤 پروفایل: <?=h($edit['name'])?> <small dir="ltr" class="muted"><?=h($edit['phone'])?></small></h3>
        <div class="tip-meta">
          <span class="pill <?=in_array($edit['role'],['admin','superadmin'],true)?'blue':''?>"><?=h($roles[$edit['role']]??$edit['role'])?></span>
          <?php if ((int)$edit['is_banned']): ?><span class="pill rose">🚫 مسدود</span><?php endif; ?>
          <?php if ((int)$edit['verified']): ?><span class="pill green">✅ تأییدشده</span><?php endif; ?>
          <?php if (!empty($edit['premium_until'])&&strtotime($edit['premium_until'])>time()): ?><span class="pill amber">⭐ ویژه تا <?=date_fa($edit['premium_until'])?></span><?php endif; ?>
        </div>
      </div>
      <?php if ($editStats): ?>
      <div class="bk-run-grid" style="margin-bottom:14px">
        <div class="bk-run-box"><b>🔧 قلق ثبت‌شده</b><span><?=fa((int)$editStats['tips'])?></span></div>
        <div class="bk-run-box"><b>🛒 خرید قلق</b><span><?=fa((int)$editStats['purchases'])?></span></div>
        <div class="bk-run-box"><b>💬 نظر ثبت‌شده</b><span><?=fa((int)$editStats['comments'])?></span></div>
        <div class="bk-run-box"><b>💰 موجودی (تومان)</b><span><?=money($edit['balance'])?></span></div>
        <div class="bk-run-box"><b>🏅 امتیاز</b><span><?=fa((int)$edit['points'])?></span></div>
        <div class="bk-run-box"><b>🗓 عضویت</b><span style="font-size:13px"><?=date_fa($edit['created_at'])?></span></div>
        <div class="bk-run-box"><b>🕐 آخرین ورود</b><span style="font-size:13px"><?=$edit['last_login']?ago($edit['last_login']):'—'?></span></div>
        <div class="bk-run-box"><b>🎁 کد معرف</b><span style="font-size:13px" dir="ltr"><?=h($edit['referral_code']??'—')?></span></div>
      </div>
      <?php endif; ?>
      <?php if ($editTx): ?>
      <details style="margin-bottom:14px"><summary style="cursor:pointer;font-weight:800;font-size:12px">💳 آخرین تراکنش‌های کیف پول (۶ مورد)</summary>
        <div class="table-wrap" style="margin-top:10px"><table class="bk-table">
          <thead><tr><th>نوع</th><th>مبلغ</th><th>موجودی پس از</th><th>توضیح</th><th>زمان</th></tr></thead>
          <tbody><?php foreach ($editTx as $tx): ?><tr><td><?=h($tx['type'])?></td><td style="color:<?=$tx['amount']>=0?'#0a7a4a':'#b3261e'?>"><?=money(abs((int)$tx['amount']))?></td><td><?=money((int)$tx['balance_after'])?></td><td class="muted"><?=h(mb_substr((string)$tx['note'],0,50))?></td><td><?=ago($tx['created_at'])?></td></tr><?php endforeach; ?></tbody>
        </table></div>
      </details>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="card" style="padding:18px;margin-bottom:20px">
      <h3 style="margin-bottom:12px"><?=$edit?'✏️ ویرایش کاربر: '.h($edit['name']):'➕ افزودن کاربر جدید'?></h3>
    <form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="xaction" value="user_save"><input type="hidden" name="user_id" value="<?=$edit['id']??0?>">
      <div class="grid grid-2"><div class="fgroup"><label class="field-label">نام</label><input class="field" name="name" value="<?=h($edit['name']??'')?>" required></div>
      <div class="fgroup"><label class="field-label">موبایل</label><input class="field" dir="ltr" name="phone" value="<?=h($edit['phone']??'')?>" required></div>
      <div class="fgroup"><label class="field-label">نقش</label><select class="field" name="role"><?php foreach($roles as $k=>$v):?><option value="<?=$k?>" <?=($edit['role']??'member')===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></div>
      <div class="fgroup"><label class="field-label">وضعیت فروشنده</label><select class="field" name="seller_status"><?php foreach(['none'=>'هیچ','pending'=>'در انتظار','approved'=>'تأییدشده','rejected'=>'ردشده'] as $k=>$v):?><option value="<?=$k?>" <?=($edit['seller_status']??'none')===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></div>
      <div class="fgroup"><label class="field-label">رمز جدید (اختیاری)</label><input class="field" type="text" name="password" placeholder="خالی = بدون تغییر"></div>
      <div class="fgroup"><label class="field-label" style="display:block">وضعیت</label><label style="font-size:12px"><input type="checkbox" name="verified" value="1" <?=!empty($edit['verified'])?'checked':''?>> تأییدشده</label> <label style="font-size:12px;margin-inline-start:12px"><input type="checkbox" name="is_banned" value="1" <?=!empty($edit['is_banned'])?'checked':''?>> مسدود</label></div></div>
      <div class="flex gap" style="flex-wrap:wrap">
        <button class="btn btn-primary"><?=$edit?'💾 ذخیره تغییرات':'➕ افزودن کاربر'?></button>
        <?php if($edit):?><a class="btn btn-secondary" href="<?=url('admin-users')?>">انصراف</a><?php endif;?>
      </div>
    </form></div>

    <!-- ===== فیلترها ===== -->
    <form method="get" class="flex gap" style="flex-wrap:wrap;gap:8px;margin-bottom:14px">
      <input type="hidden" name="r" value="admin-users">
      <input class="field" style="max-width:220px" name="q" value="<?=h($qsearch)?>" placeholder="🔍 نام، موبایل یا ایمیل…">
      <select class="field" style="max-width:150px" name="role">
        <option value="">همه نقش‌ها</option>
        <?php foreach ($roles as $rv=>$rl): ?><option value="<?=$rv?>" <?=$urole===$rv?'selected':''?>><?=h($rl)?></option><?php endforeach; ?>
      </select>
      <select class="field" style="max-width:150px" name="status">
        <?php foreach ([''=>'همه وضعیت‌ها','banned'=>'🚫 مسدود','verified'=>'✅ تأییدشده','premium'=>'⭐ اشتراک ویژه','seller'=>'🏪 فروشنده'] as $sv=>$sl): ?><option value="<?=$sv?>" <?=$ustat===$sv?'selected':''?>><?=h($sl)?></option><?php endforeach; ?>
      </select>
      <button class="btn btn-primary btn-sm">اعمال</button>
      <small class="muted" style="align-self:center"><?=fa($totalU)?> کاربر یافت شد</small>
    </form>

    <div class="card table-wrap"><table class="bk-table">
      <thead><tr><th>کاربر</th><th>نقش</th><th>قلق</th><th>موجودی</th><th>وضعیت</th><th>آخرین ورود</th><th>تعدیل سریع موجودی</th><th>عملیات</th></tr></thead>
    <?php foreach($items as $x): ?><tr>
      <td><span class="avatar small" style="display:inline-block"><?=h(mb_substr($x['name'],0,1))?></span> <a class="check" href="<?=url('admin-users',['edit'=>$x['id']])?>" style="font-weight:bold"><?=h(mb_substr($x['name'],0,24))?></a><br><small dir="ltr" class="muted" style="font-size:10px"><?=h($x['phone'])?></small></td>
      <td><span class="pill <?=in_array($x['role'],['admin','superadmin'],true)?'blue':''?>"><?=h($roles[$x['role']]??$x['role'])?></span></td>
      <td><?=fa((int)$x['tips_count'])?></td>
      <td><b><?=money($x['balance'])?></b></td>
      <td>
        <?php if((int)$x['is_banned']): ?><span class="pill rose">🚫</span><?php endif; ?>
        <?php if((int)$x['verified']): ?><span class="pill green">✅</span><?php endif; ?>
        <?php if(!empty($x['premium_until'])&&strtotime($x['premium_until'])>time()): ?><span class="pill amber">⭐</span><?php endif; ?>
        <?php if($x['seller_status']==='approved'): ?><span class="pill blue">🏪</span><?php endif; ?>
      </td>
      <td><small class="muted"><?=$x['last_login']?ago($x['last_login']):'—'?></small></td>
      <td><form method="post" style="display:flex;gap:4px"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="xaction" value="user_balance"><input type="hidden" name="user_id" value="<?=$x['id']?>"><input class="field" style="width:90px" type="number" name="delta" placeholder="± تومان"><button class="btn btn-secondary btn-sm">اعمال</button></form></td>
      <td><div class="flex" style="gap:4px"><a class="btn btn-secondary btn-sm" href="<?=url('admin-users',['edit'=>$x['id']])?>">✏️ ویرایش</a>
      <form method="post" onsubmit="return confirm('این کاربر برای همیشه حذف شود؟ محتواهای او حفظ می‌شود.')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="xaction" value="user_del"><input type="hidden" name="user_id" value="<?=$x['id']?>"><button class="btn btn-danger btn-sm">🗑 حذف</button></form></div></td></tr><?php endforeach;?>
    </table></div>
    <?php if ($pages>1): ?><div class="flex gap mt" style="flex-wrap:wrap"><?php for($pi=max(1,$upage-3);$pi<=min($pages,$upage+3);$pi++): ?><a class="pill <?=$pi===$upage?'green':''?>" href="<?=url('admin-users',['q'=>$qsearch,'role'=>$urole,'status'=>$ustat,'p'=>$pi])?>"><?=fa($pi)?></a><?php endfor; ?><small class="muted" style="align-self:center">صفحه <?=fa($upage)?> از <?=fa($pages)?></small></div><?php endif; ?>
    <?php
}

/* ===== TIPS ADMIN ===== */
elseif ($sub === 'admin-tips') {
    $eid = (int)($_GET['edit'] ?? 0); $edit=null;
    if ($eid) { $q=$pdo->prepare('SELECT * FROM tips WHERE id=?'); $q->execute([$eid]); $edit=$q->fetch(); }
    $items = $pdo->query("SELECT t.*,u.name an FROM tips t JOIN users u ON u.id=t.author_id ORDER BY t.id DESC LIMIT 200")->fetchAll();
    ?>
    <div class="flex between items-center" style="margin-bottom:16px"><h1 style="font-size:22px;font-weight:900">مدیریت قلق‌ها</h1><a class="btn btn-secondary btn-sm" href="<?=url('admin?tab=tips')?>">بازگشت به پنل</a></div>
    <div class="card authc" style="padding:18px;margin-bottom:20px"><h3 style="margin-bottom:12px"><?=$edit?'ویرایش قلق #'.fa($edit['id']):'افزودن قلق جدید'?></h3>
    <form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="xaction" value="tip_save"><input type="hidden" name="tip_id" value="<?=$edit['id']??0?>">
      <div class="grid grid-2"><div class="fgroup"><label class="field-label">عنوان</label><input class="field" name="title" value="<?=h($edit['title']??'')?>" required></div>
      <div class="fgroup"><label class="field-label">دسته</label><select class="field" name="category_id"><?php foreach($cats as $c):?><option value="<?=$c['id']?>" <?=($edit['category_id']??0)==$c['id']?'selected':''?>><?=h($c['name'])?></option><?php endforeach;?></select></div>
      <div class="fgroup"><label class="field-label">نوع دسترسی</label><select class="field" name="access_type"><?php foreach(['free'=>'رایگان','like'=>'با لایک','paid'=>'پرداختی'] as $k=>$v):?><option value="<?=$k?>" <?=($edit['access_type']??'free')===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></div>
      <div class="fgroup"><label class="field-label">قیمت (اگر پرداختی)</label><input class="field" type="number" name="price" value="<?=(int)($edit['price']??0)?>"></div>
      <div class="fgroup"><label class="field-label">وضعیت</label><select class="field" name="status"><?php foreach(['published'=>'منتشرشده','pending'=>'در انتظار','draft'=>'پیش‌نویس','rejected'=>'ردشده'] as $k=>$v):?><option value="<?=$k?>" <?=($edit['status']??'published')===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></div></div>
      <div class="fgroup"><label class="field-label">خلاصه</label><input class="field" name="short_description" value="<?=h($edit['short_description']??'')?>"></div>
      <div class="fgroup"><label class="field-label">متن کامل</label><textarea class="field" name="description" rows="4"><?=h($edit['description']??'')?></textarea></div>
      <button class="btn btn-primary"><?=$edit?'ذخیره':'افزودن قلق'?></button> <?php if($edit):?><a class="btn btn-secondary" href="<?=url('admin-tips')?>">انصراف</a><?php endif;?>
    </form></div>
    <div class="card tablewrap"><table class="table"><tr><th>#</th><th>عنوان</th><th>نویسنده</th><th>وضعیت</th><th>عملیات</th></tr>
    <?php foreach($items as $x):?><tr><td><?=fa($x['id'])?></td><td><a class="check" href="<?=url('tip/'.$x['id'])?>"><?=h(mb_substr($x['title'],0,40))?></a></td><td><?=h($x['an'])?></td><td><span class="pill <?=$x['status']==='published'?'green':($x['status']==='pending'?'amber':'rose')?>"><?=h(status_label($x['status']))?></span></td>
      <td style="display:flex;gap:4px"><a class="btn btn-secondary btn-sm" href="<?=url('admin-tips',['edit'=>$x['id']])?>">ویرایش</a>
      <form method="post" onsubmit="return confirm('حذف شود؟')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="xaction" value="tip_del"><input type="hidden" name="tip_id" value="<?=$x['id']?>"><button class="btn btn-danger btn-sm">حذف</button></form></td></tr><?php endforeach;?></table></div>
    <?php
}
echo '</main>';
footer_html();
