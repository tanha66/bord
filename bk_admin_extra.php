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
        if (mb_strlen($name) < 2 || !preg_match('/^09\d{9}$/', $phone)) { $notice = 'نام و موبایل معتبر لازم است.'; }
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
    if ($act === 'user_del') { $uid=(int)$_POST['user_id']; if ($uid !== (int)$admin['id']) { $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$uid]); $notice='کاربر حذف شد.'; } else $notice='حساب خودتان را حذف نکنید.'; }
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

/* ===== USERS ADMIN ===== */
elseif ($sub === 'admin-users') {
    $eid = (int)($_GET['edit'] ?? 0); $edit=null;
    if ($eid) { $q=$pdo->prepare('SELECT * FROM users WHERE id=?'); $q->execute([$eid]); $edit=$q->fetch(); }
    $qsearch = trim($_GET['q'] ?? ''); $w=''; $par=[];
    if ($qsearch) { $w='WHERE name LIKE ? OR phone LIKE ?'; $par=["%$qsearch%","%$qsearch%"]; }
    $st=$pdo->prepare("SELECT * FROM users $w ORDER BY id DESC LIMIT 200"); $st->execute($par); $items=$st->fetchAll();
    $roles=['member'=>'کاربر','expert'=>'تعمیرکار','moderator'=>'ناظر','admin'=>'مدیر','superadmin'=>'سوپرادمین'];
    ?>
    <div class="flex between items-center" style="margin-bottom:16px"><h1 style="font-size:22px;font-weight:900">مدیریت کاربران</h1><a class="btn btn-secondary btn-sm" href="<?=url('admin?tab=users')?>">بازگشت به پنل</a></div>
    <div class="card authc" style="padding:18px;margin-bottom:20px"><h3 style="margin-bottom:12px"><?=$edit?'ویرایش کاربر: '.h($edit['name']):'افزودن کاربر جدید'?></h3>
    <form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="xaction" value="user_save"><input type="hidden" name="user_id" value="<?=$edit['id']??0?>">
      <div class="grid grid-2"><div class="fgroup"><label class="field-label">نام</label><input class="field" name="name" value="<?=h($edit['name']??'')?>" required></div>
      <div class="fgroup"><label class="field-label">موبایل</label><input class="field" dir="ltr" name="phone" value="<?=h($edit['phone']??'')?>" required></div>
      <div class="fgroup"><label class="field-label">نقش</label><select class="field" name="role"><?php foreach($roles as $k=>$v):?><option value="<?=$k?>" <?=($edit['role']??'member')===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></div>
      <div class="fgroup"><label class="field-label">وضعیت فروشنده</label><select class="field" name="seller_status"><?php foreach(['none'=>'هیچ','pending'=>'در انتظار','approved'=>'تأییدشده','rejected'=>'ردشده'] as $k=>$v):?><option value="<?=$k?>" <?=($edit['seller_status']??'none')===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></div>
      <div class="fgroup"><label class="field-label">رمز جدید (اختیاری)</label><input class="field" type="text" name="password" placeholder="خالی = بدون تغییر"></div>
      <div class="fgroup"><label class="field-label" style="display:block">وضعیت</label><label style="font-size:12px"><input type="checkbox" name="verified" value="1" <?=!empty($edit['verified'])?'checked':''?>> تأییدشده</label> <label style="font-size:12px;margin-inline-start:12px"><input type="checkbox" name="is_banned" value="1" <?=!empty($edit['is_banned'])?'checked':''?>> مسدود</label></div></div>
      <button class="btn btn-primary"><?=$edit?'ذخیره':'افزودن کاربر'?></button> <?php if($edit):?><a class="btn btn-secondary" href="<?=url('admin-users')?>">انصراف</a><?php endif;?>
    </form></div>
    <form method="get" style="max-width:320px;margin-bottom:14px"><input type="hidden" name="r" value="admin-users"><input class="field" name="q" value="<?=h($qsearch)?>" placeholder="جستجوی نام یا موبایل…"></form>
    <div class="card tablewrap"><table class="table"><tr><th>کاربر</th><th>نقش</th><th>موجودی</th><th>تعدیل موجودی</th><th>عملیات</th></tr>
    <?php foreach($items as $x):?><tr><td><?=h($x['name'])?><br><small dir="ltr" class="muted"><?=h($x['phone'])?></small></td><td><?=h($roles[$x['role']]??$x['role'])?></td><td><?=money($x['balance'])?></td>
      <td><form method="post" style="display:flex;gap:4px"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="xaction" value="user_balance"><input type="hidden" name="user_id" value="<?=$x['id']?>"><input class="field" style="width:90px" type="number" name="delta" placeholder="± تومان"><button class="btn btn-secondary btn-sm">اعمال</button></form></td>
      <td style="display:flex;gap:4px"><a class="btn btn-secondary btn-sm" href="<?=url('admin-users',['edit'=>$x['id']])?>">ویرایش</a>
      <form method="post" onsubmit="return confirm('حذف شود؟')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="xaction" value="user_del"><input type="hidden" name="user_id" value="<?=$x['id']?>"><button class="btn btn-danger btn-sm">حذف</button></form></td></tr><?php endforeach;?></table></div>
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
