<?php
/** Bordkhan PHP support tickets — UI + handlers. */
require_once __DIR__ . '/bk_extended.php';
$u = bk_login();
$pdo = db();
$action = $_POST['ticket_action'] ?? '';

if ($action === 'create') {
    $title = bk_clean($_POST['title'] ?? ''); $body = trim($_POST['body'] ?? '');
    $destination = in_array($_POST['destination'] ?? '', ['support','admin','seller'], true) ? $_POST['destination'] : 'support';
    $priority = in_array($_POST['priority'] ?? '', ['low','normal','high'], true) ? $_POST['priority'] : 'normal';
    $category = bk_clean($_POST['category'] ?? 'عمومی'); $seller = (int)($_POST['seller_id'] ?? 0) ?: null; $order = (int)($_POST['order_id'] ?? 0) ?: null;
    if (mb_strlen($title) < 4 || mb_strlen($body) < 8) { $error = 'عنوان و شرح تیکت را کامل وارد کنید.'; }
    else {
        $pdo->prepare('INSERT INTO tickets(user_id,destination,seller_id,order_id,category,priority,title,body) VALUES(?,?,?,?,?,?,?,?)')->execute([$u['id'],$destination,$seller,$order,$category,$priority,$title,$body]);
        $ticketId = (int)$pdo->lastInsertId();
        $admins = $pdo->query("SELECT id FROM users WHERE role IN ('admin','superadmin')")->fetchAll(); foreach($admins as $a) bk_notify((int)$a['id'],'تیکت جدید', $title, url('tickets?ticket='.$ticketId));
        redirect_to('tickets?ticket=' . $ticketId);
    }
}
if ($action === 'reply') {
    $tid=(int)($_POST['ticket_id']??0);$body=trim($_POST['body']??'');
    $q=$pdo->prepare('SELECT * FROM tickets WHERE id=?');$q->execute([$tid]);$t=$q->fetch();
    $staff=in_array($u['role'],['admin','superadmin','moderator','support'],true);
    if(!$t || (!$staff && (int)$t['user_id']!==$u['id'] && (int)$t['seller_id']!==$u['id'])) $error='دسترسی به این تیکت ندارید.';
    elseif(mb_strlen($body)<2) $error='متن پاسخ را وارد کنید.';
    else { $pdo->prepare('INSERT INTO ticket_messages(ticket_id,sender_id,body) VALUES(?,?,?)')->execute([$tid,$u['id'],$body]); $pdo->prepare("UPDATE tickets SET status='replied',updated_at=NOW() WHERE id=?")->execute([$tid]); if((int)$t['user_id']!==$u['id'])bk_notify((int)$t['user_id'],'پاسخ جدید تیکت',$t['title'],url('tickets?ticket='.$tid)); redirect_to('tickets?ticket='.$tid); }
}
if ($action === 'status') {
    $tid=(int)($_POST['ticket_id']??0);$status=in_array($_POST['status']??'', ['open','replied','solved','closed'], true)?$_POST['status']:'open';
    if(in_array($u['role'],['admin','superadmin','moderator','support'],true))$pdo->prepare('UPDATE tickets SET status=? WHERE id=?')->execute([$status,$tid]);
    redirect_to('tickets?ticket='.$tid);
}

$staff = in_array($u['role'], ['admin','superadmin','moderator','support'], true);
if ($staff) $rows=$pdo->query('SELECT t.*,cu.name creator_name,au.name assigned_name FROM tickets t JOIN users cu ON cu.id=t.user_id LEFT JOIN users au ON au.id=t.assigned_to ORDER BY FIELD(t.status,"open","replied","solved","closed"),t.updated_at DESC LIMIT 100')->fetchAll();
else { $q=$pdo->prepare('SELECT t.*,cu.name creator_name,au.name assigned_name FROM tickets t JOIN users cu ON cu.id=t.user_id LEFT JOIN users au ON au.id=t.assigned_to WHERE t.user_id=? OR t.seller_id=? ORDER BY t.updated_at DESC LIMIT 100');$q->execute([$u['id'],$u['id']]);$rows=$q->fetchAll(); }
$selected=null;$messages=[];$tid=(int)($_GET['ticket']??0);
if($tid){$q=$pdo->prepare('SELECT t.*,cu.name creator_name,au.name assigned_name FROM tickets t JOIN users cu ON cu.id=t.user_id LEFT JOIN users au ON au.id=t.assigned_to WHERE t.id=?');$q->execute([$tid]);$selected=$q->fetch();if($selected){$q=$pdo->prepare('SELECT m.*,u.name sender_name,u.role sender_role FROM ticket_messages m JOIN users u ON u.id=m.sender_id WHERE m.ticket_id=? ORDER BY m.id');$q->execute([$tid]);$messages=$q->fetchAll();}}
header_html('پشتیبانی و تیکت‌ها');
?><main class="wrap page"><div class="page-title"><h1>پشتیبانی و تیکت‌ها</h1><p>مقصد: پشتیبانی، مدیریت یا فروشنده · وضعیت: باز، پاسخ، حل‌شده، بسته</p></div>
<?php if(!empty($error)):?><div class="notice error"><?=h($error)?></div><?php endif;?><div class="grid grid-2"><section><div class="card auth-card"><h3>تیکت جدید</h3><form method="post"><input type="hidden" name="ticket_action" value="create"><label class="field-label">موضوع</label><input class="field" name="title" required><label class="field-label">شرح</label><textarea class="field" name="body" rows="5" required></textarea><div class="grid grid-2"><div><label class="field-label">مقصد</label><select class="field" name="destination"><option value="support">تیم پشتیبانی</option><option value="admin">مدیریت</option><option value="seller">فروشنده</option></select></div><div><label class="field-label">اولویت</label><select class="field" name="priority"><option value="low">کم</option><option value="normal">معمولی</option><option value="high">بالا</option></select></div></div><label class="field-label">دسته‌بندی</label><input class="field" name="category" value="عمومی"><button class="btn btn-primary btn-full mt">ثبت تیکت</button></form></div><div class="card mt" style="padding:0"><h3 style="padding:16px 16px 8px">فهرست تیکت‌ها</h3><?php foreach($rows as $r):?><a class="leader-row" href="<?=url('tickets',['ticket'=>$r['id']])?>"><span class="grow"><strong><?=h($r['title'])?></strong><small><?=h($r['creator_name'])?> · <?=h($r['category'])?> · <?=h($r['priority'])?></small></span><span class="pill <?=in_array($r['status'],['open','replied'])?'amber':'green'?>"><?=h($r['status'])?></span></a><?php endforeach;?><?php if(!$rows):?><div class="empty">تیکتی ثبت نشده است.</div><?php endif;?></div></section><section><?php if($selected):?><div class="card auth-card"><div class="flex between"><div><h2><?=h($selected['title'])?></h2><small class="muted">تیکت #<?=fa($selected['id'])?> · <?=h($selected['assigned_name']??'بدون کارشناس')?></small></div><span class="pill amber"><?=h($selected['status'])?></span></div><div class="ticket-body mt"><div class="ticket-message"><b><?=h($selected['creator_name'])?></b><p><?=nl2br(h($selected['body']))?></p></div><?php foreach($messages as $m):?><div class="ticket-message <?=((int)$m['sender_id']===(int)$u['id'])?'mine':''?>"><b><?=h($m['sender_name'])?> <small>(<?=h($m['sender_role'])?>)</small></b><p><?=nl2br(h($m['body']))?></p></div><?php endforeach;?></div><?php if($selected['status']!=='closed'):?><form method="post" class="mt"><input type="hidden" name="ticket_action" value="reply"><input type="hidden" name="ticket_id" value="<?=$selected['id']?>"><textarea class="field" name="body" rows="3" placeholder="پاسخ شما…" required></textarea><button class="btn btn-primary mt">ارسال پاسخ</button></form><?php endif;if($staff):?><form method="post" class="mt"><input type="hidden" name="ticket_action" value="status"><input type="hidden" name="ticket_id" value="<?=$selected['id']?>"><select class="field" name="status"><option value="open">باز</option><option value="replied">پاسخ داده شد</option><option value="solved">حل شد</option><option value="closed">بسته</option></select><button class="btn btn-secondary mt">تغییر وضعیت</button></form><?php endif;?></div><?php else:?><div class="card empty">یک تیکت را انتخاب کنید یا تیکت جدید بسازید.</div><?php endif;?></section></div></main><?php footer_html();
