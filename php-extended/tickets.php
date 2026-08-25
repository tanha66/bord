<?php
/**
 * Bordkhan PHP support tickets — UI + handlers.
 * از طریق index.php لود می‌شود (/tickets).
 */
require_once __DIR__ . '/bk_extended.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('check_csrf')) check_csrf();

$u = bk_login();
$pdo = db();
$error = '';
$action = $_POST['ticket_action'] ?? '';

$statusLabels  = ['open'=>'باز', 'replied'=>'پاسخ داده شد', 'solved'=>'حل‌شده', 'closed'=>'بسته'];
$destLabels    = ['support'=>'پشتیبانی', 'admin'=>'مدیریت', 'seller'=>'فروشنده'];
$prioLabels    = ['low'=>'کم', 'normal'=>'معمولی', 'high'=>'بالا'];
$staffRoles    = ['admin','superadmin','moderator','support'];
$staff         = in_array($u['role'], $staffRoles, true);

function tk_notify_staff($pdo, string $title, string $body, string $link, int $excludeUserId = 0): void {
    /* اعلان تیکت فقط برای کارکنان (غیر از ارسال‌کننده) ارسال می‌شود — هر اعلان فقط برای همان کاربر قابل مشاهده است */
    $q = $pdo->prepare("SELECT id FROM users WHERE role IN ('admin','superadmin','moderator','support') AND id != ? AND id > 0");
    $q->execute([(int)$excludeUserId]);
    $staffIds = $q->fetchAll(PDO::FETCH_COLUMN);
    foreach ($staffIds as $sid) {
        $sid = (int)$sid;
        if ($sid > 0 && $sid !== (int)$excludeUserId) {
            bk_notify($sid, $title, $body, $link, 'ticket');
        }
    }
}

/* ---------- ایجاد تیکت ---------- */
if ($action === 'create') {
    $title = bk_clean($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $destination = in_array($_POST['destination'] ?? '', ['support','admin','seller'], true) ? $_POST['destination'] : 'support';
    $priority = in_array($_POST['priority'] ?? '', ['low','normal','high'], true) ? $_POST['priority'] : 'normal';
    $category = bk_clean($_POST['category'] ?? 'عمومی') ?: 'عمومی';
    $seller = null;
    $order = (int)($_POST['order_id'] ?? 0) ?: null;
    if ($destination === 'seller') {
        $seller = (int)($_POST['seller_id'] ?? 0) ?: null;
        if (!$seller) { $error = 'برای تیکت فروشنده، فروشنده موردنظر را انتخاب کنید.'; }
    }
    if (mb_strlen($title) < 4 || mb_strlen($body) < 8) { $error = 'عنوان و شرح تیکت را کامل وارد کنید (عنوان حداقل ۴ و شرح حداقل ۸ حرف).'; }
    if ($error === '') {
        $pdo->prepare('INSERT INTO tickets(user_id,destination,seller_id,order_id,category,priority,title,body) VALUES(?,?,?,?,?,?,?,?)')
            ->execute([$u['id'],$destination,$seller,$order,$category,$priority,$title,$body]);
        $ticketId = (int)$pdo->lastInsertId();
        tk_notify_staff($pdo, 'تیکت جدید', $title . ' — از ' . $u['name'], url('tickets?ticket=' . $ticketId), (int)$u['id']);
        if ($seller && $seller !== (int)$u['id']) {
            bk_notify($seller, 'تیکت جدید خریدار', $title . ' — از ' . $u['name'], url('tickets?ticket=' . $ticketId), 'ticket');
        }
        redirect_to('tickets?ticket=' . $ticketId);
    }
}

/* ---------- پاسخ به تیکت ---------- */
if ($action === 'reply') {
    $tid = (int)($_POST['ticket_id'] ?? 0);
    $body = trim($_POST['body'] ?? '');
    $q = $pdo->prepare('SELECT * FROM tickets WHERE id=?');
    $q->execute([$tid]);
    $t = $q->fetch();
    if (!$t || (!$staff && (int)$t['user_id'] !== (int)$u['id'] && (int)$t['seller_id'] !== (int)$u['id'])) {
        $error = 'دسترسی به این تیکت ندارید.';
    } elseif ($t['status'] === 'closed') {
        $error = 'این تیکت بسته شده است.';
    } elseif (mb_strlen($body) < 2) {
        $error = 'متن پاسخ را وارد کنید.';
    } else {
        $pdo->prepare('INSERT INTO ticket_messages(ticket_id,sender_id,body) VALUES(?,?,?)')->execute([$tid,$u['id'],$body]);
        // پاسخ کاربر → باز شدن مجدد و اطلاع کارشناس‌ها؛ پاسخ کارشناس → وضعیت پاسخ داده شد
        if ($staff) {
            $pdo->prepare("UPDATE tickets SET status='replied' WHERE id=?")->execute([$tid]);
            if ((int)$t['user_id'] !== (int)$u['id']) bk_notify((int)$t['user_id'], 'پاسخ جدید تیکت', $t['title'], url('tickets?ticket=' . $tid), 'ticket');
            if ($t['seller_id'] && (int)$t['seller_id'] !== (int)$u['id']) bk_notify((int)$t['seller_id'], 'پاسخ جدید تیکت', $t['title'], url('tickets?ticket=' . $tid), 'ticket');
        } else {
            $pdo->prepare("UPDATE tickets SET status='open' WHERE id=?")->execute([$tid]);
            tk_notify_staff($pdo, 'پاسخ جدید کاربر به تیکت', $t['title'] . ' — ' . $u['name'], url('tickets?ticket=' . $tid), (int)$u['id']);
            if ($t['seller_id'] && (int)$t['seller_id'] !== (int)$u['id']) bk_notify((int)$t['seller_id'], 'پاسخ جدید خریدار', $t['title'], url('tickets?ticket=' . $tid), 'ticket');
        }
        redirect_to('tickets?ticket=' . $tid);
    }
}

/* ---------- تغییر وضعیت (فقط کارشناس) ---------- */
if ($action === 'status' && $staff) {
    $tid = (int)($_POST['ticket_id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['open','replied','solved','closed'], true) ? $_POST['status'] : 'open';
    $q = $pdo->prepare('SELECT * FROM tickets WHERE id=?');
    $q->execute([$tid]);
    $t = $q->fetch();
    if ($t) {
        $pdo->prepare('UPDATE tickets SET status=? WHERE id=?')->execute([$status,$tid]);
        if (in_array($status, ['solved','closed'], true) && (int)$t['user_id'] !== (int)$u['id']) {
            bk_notify((int)$t['user_id'], 'تیکت ' . ($status === 'solved' ? 'حل شد' : 'بسته شد'), $t['title'], url('tickets?ticket=' . $tid), 'ticket');
        }
    }
    redirect_to('tickets?ticket=' . $tid);
}

/* ---------- انتساب کارشناس (فقط کارشناس) ---------- */
if ($action === 'assign' && $staff) {
    $tid = (int)($_POST['ticket_id'] ?? 0);
    $me = ($_POST['op'] ?? '') === 'unassign' ? null : (int)$u['id'];
    $pdo->prepare('UPDATE tickets SET assigned_to=? WHERE id=?')->execute([$me,$tid]);
    redirect_to('tickets?ticket=' . $tid);
}

/* ---------- فهرست تیکت‌ها ---------- */
$filter = in_array($_GET['status'] ?? '', ['all','open','replied','solved','closed','mine'], true) ? $_GET['status'] : ($staff ? 'all' : 'all');
if ($staff) {
    if ($filter === 'mine') {
        $rows = $pdo->prepare('SELECT t.*,cu.name creator_name,au.name assigned_name FROM tickets t JOIN users cu ON cu.id=t.user_id LEFT JOIN users au ON au.id=t.assigned_to WHERE t.assigned_to=? ORDER BY FIELD(t.status,"open","replied","solved","closed"),t.updated_at DESC LIMIT 200');
        $rows->execute([$u['id']]);
        $rows = $rows->fetchAll();
    } elseif ($filter === 'all') {
        $rows = $pdo->query('SELECT t.*,cu.name creator_name,au.name assigned_name FROM tickets t JOIN users cu ON cu.id=t.user_id LEFT JOIN users au ON au.id=t.assigned_to ORDER BY FIELD(t.status,"open","replied","solved","closed"),t.updated_at DESC LIMIT 200')->fetchAll();
    } else {
        $rows = $pdo->prepare('SELECT t.*,cu.name creator_name,au.name assigned_name FROM tickets t JOIN users cu ON cu.id=t.user_id LEFT JOIN users au ON au.id=t.assigned_to WHERE t.status=? ORDER BY t.updated_at DESC LIMIT 200');
        $rows->execute([$filter]);
        $rows = $rows->fetchAll();
    }
} else {
    $q = $pdo->prepare('SELECT t.*,cu.name creator_name,au.name assigned_name FROM tickets t JOIN users cu ON cu.id=t.user_id LEFT JOIN users au ON au.id=t.assigned_to WHERE t.user_id=? OR t.seller_id=? ORDER BY FIELD(t.status,"open","replied","solved","closed"),t.updated_at DESC LIMIT 200');
    $q->execute([$u['id'],$u['id']]);
    $rows = $q->fetchAll();
}

/* ---------- تیکت انتخاب‌شده (با کنترل دسترسی) ---------- */
$selected = null; $messages = [];
$tid = (int)($_GET['ticket'] ?? 0);
if ($tid) {
    $q = $pdo->prepare('SELECT t.*,cu.name creator_name,au.name assigned_name FROM tickets t JOIN users cu ON cu.id=t.user_id LEFT JOIN users au ON au.id=t.assigned_to WHERE t.id=?');
    $q->execute([$tid]);
    $selected = $q->fetch();
    if ($selected && !$staff && (int)$selected['user_id'] !== (int)$u['id'] && (int)$selected['seller_id'] !== (int)$u['id']) {
        $selected = null; // بدون دسترسی: وانمود می‌کنیم تیکت وجود ندارد
    }
    if ($selected) {
        $q = $pdo->prepare('SELECT m.*,u.name sender_name,u.role sender_role FROM ticket_messages m JOIN users u ON u.id=m.sender_id WHERE m.ticket_id=? ORDER BY m.id');
        $q->execute([$tid]);
        $messages = $q->fetchAll();
    }
}

/* ---------- فروشنده‌های قابل انتخاب (برای تیکت فروشنده) ---------- */
$mySellers = [];
if ($u) {
    $q = $pdo->prepare("SELECT DISTINCT o.seller_id, us.name FROM board_orders o JOIN users us ON us.id=o.seller_id WHERE o.buyer_id=? AND o.status IN ('paid','shipped','completed') ORDER BY us.name LIMIT 50");
    $q->execute([$u['id']]);
    $mySellers = $q->fetchAll();
}

header_html('پشتیبانی و تیکت‌ها');
?><main class="wrap page">
<div class="page-title"><h1>پشتیبانی و تیکت‌ها</h1><p>مقصد: پشتیبانی، مدیریت یا فروشنده · وضعیت: <?=h(implode('، ', array_values($statusLabels)))?></p></div>
<?php if (!empty($error)):?><div class="notice error"><?=h($error)?></div><?php endif;?>
<div class="grid grid-2">

<section>
  <div class="card auth-card">
    <h3>تیکت جدید</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=function_exists('csrf') ? csrf() : ''?>">
      <input type="hidden" name="ticket_action" value="create">
      <label class="field-label">موضوع *</label><input class="field" name="title" required placeholder="خلاصه مشکل یا درخواست">
      <label class="field-label">شرح *</label><textarea class="field" name="body" rows="5" required placeholder="مشکل را کامل توضیح دهید…"></textarea>
      <div class="grid grid-2">
        <div><label class="field-label">مقصد</label>
          <select class="field" name="destination" id="tkDestination">
            <option value="support">تیم پشتیبانی</option>
            <option value="admin">مدیریت</option>
            <option value="seller">فروشنده</option>
          </select>
        </div>
        <div><label class="field-label">اولویت</label>
          <select class="field" name="priority">
            <option value="low">کم</option>
            <option value="normal" selected>معمولی</option>
            <option value="high">بالا</option>
          </select>
        </div>
      </div>
      <div id="tkSellerBox" style="display:none">
        <label class="field-label">انتخاب فروشنده</label>
        <select class="field" name="seller_id">
          <option value="">— انتخاب کنید —</option>
          <?php foreach ($mySellers as $sl): ?><option value="<?=$sl['seller_id']?>"><?=h($sl['name'])?></option><?php endforeach; ?>
        </select>
        <small class="muted">فقط فروشنده‌هایی که از آن‌ها خرید کرده‌اید نمایش داده می‌شوند.</small>
      </div>
      <div class="grid grid-2">
        <div><label class="field-label">دسته‌بندی</label><input class="field" name="category" value="عمومی"></div>
        <div><label class="field-label">شماره سفارش (اختیاری)</label><input class="field" type="number" name="order_id" min="1" placeholder="مثلاً ۱۰۲۴"></div>
      </div>
      <button class="btn btn-primary btn-full mt">ثبت تیکت</button>
    </form>
  </div>

  <div class="card mt" style="padding:0">
    <div style="padding:16px 16px 0">
      <h3>فهرست تیکت‌ها</h3>
      <div class="tip-meta" style="margin:10px 0">
        <?php foreach ([['all','همه'],['open','باز'],['replied','پاسخ'],['solved','حل‌شده'],['closed','بسته']] as $f): ?>
          <a class="pill <?=$filter===$f[0]?'green':''?>" href="<?=url('tickets',['status'=>$f[0]])?>"><?=$f[1]?></a>
        <?php endforeach; ?>
        <?php if ($staff): ?><a class="pill <?=$filter==='mine'?'green':''?>" href="<?=url('tickets',['status'=>'mine'])?>">تیکت‌های من</a><?php endif; ?>
      </div>
    </div>
    <?php foreach ($rows as $r): ?>
      <a class="leader-row" href="<?=url('tickets',['ticket'=>$r['id']])?>">
        <span class="grow"><strong>#<?=fa($r['id'])?> <?=h($r['title'])?></strong>
        <small><?=h($r['creator_name'])?> · <?=h($destLabels[$r['destination']] ?? $r['destination'])?> · <?=h($prioLabels[$r['priority']] ?? $r['priority'])?><?=$r['assigned_name'] ? ' · کارشناس: '.h($r['assigned_name']) : ''?></small></span>
        <span class="pill <?=$r['status']==='open'?'amber':($r['status']==='solved'?'green':($r['status']==='closed'?'rose':'blue'))?>"><?=h($statusLabels[$r['status']] ?? $r['status'])?></span>
      </a>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="empty">تیکتی ثبت نشده است.</div><?php endif; ?>
  </div>
</section>

<section>
  <?php if ($selected): ?>
  <div class="card auth-card">
    <div class="flex between items-center" style="gap:8px;flex-wrap:wrap">
      <div class="grow">
        <h2 style="margin:0">#<?=fa($selected['id'])?> <?=h($selected['title'])?></h2>
        <small class="muted"><?=h($selected['creator_name'])?> · <?=h($destLabels[$selected['destination']] ?? $selected['destination'])?> · <?=h($prioLabels[$selected['priority']] ?? $selected['priority'])?> · <?=ago($selected['created_at'])?><?=$selected['assigned_name'] ? ' · کارشناس: '.h($selected['assigned_name']) : ' · بدون کارشناس'?></small>
      </div>
      <span class="pill <?=$selected['status']==='open'?'amber':($selected['status']==='solved'?'green':($selected['status']==='closed'?'rose':'blue'))?>"><?=h($statusLabels[$selected['status']] ?? $selected['status'])?></span>
    </div>

    <div class="ticket-body mt">
      <div class="ticket-message">
        <div class="flex between"><b><?=h($selected['creator_name'])?></b><small class="muted"><?=ago($selected['created_at'])?></small></div>
        <p><?=nl2br(h($selected['body']))?></p>
      </div>
      <?php foreach ($messages as $m): ?>
        <div class="ticket-message <?=(int)$m['sender_id']===(int)$u['id'] ? 'mine' : ''?>">
          <div class="flex between"><b><?=h($m['sender_name'])?> <small>(<?=h(function_exists('role_label') ? role_label($m['sender_role'] ?? 'member') : ($m['sender_role'] ?? 'member'))?>)</small></b><small class="muted"><?=ago($m['created_at'])?></small></div>
          <p><?=nl2br(h($m['body']))?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($selected['status'] !== 'closed'): ?>
      <form method="post" class="mt">
        <input type="hidden" name="csrf" value="<?=function_exists('csrf') ? csrf() : ''?>">
        <input type="hidden" name="ticket_action" value="reply">
        <input type="hidden" name="ticket_id" value="<?=$selected['id']?>">
        <textarea class="field" name="body" rows="3" placeholder="پاسخ شما…" required></textarea>
        <button class="btn btn-primary mt">ارسال پاسخ</button>
      </form>
    <?php else: ?>
      <div class="notice mt">این تیکت بسته شده است. برای ادامه گفتگو تیکت جدید ثبت کنید.</div>
    <?php endif; ?>

    <?php if ($staff): ?>
      <div class="grid grid-2 mt">
        <form method="post">
          <input type="hidden" name="csrf" value="<?=function_exists('csrf') ? csrf() : ''?>">
          <input type="hidden" name="ticket_action" value="status">
          <input type="hidden" name="ticket_id" value="<?=$selected['id']?>">
          <label class="field-label">تغییر وضعیت</label>
          <select class="field" name="status">
            <?php foreach ($statusLabels as $k => $v): ?><option value="<?=$k?>" <?=$selected['status']===$k?'selected':''?>><?=h($v)?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-secondary mt">اعمال وضعیت</button>
        </form>
        <form method="post">
          <input type="hidden" name="csrf" value="<?=function_exists('csrf') ? csrf() : ''?>">
          <input type="hidden" name="ticket_action" value="assign">
          <input type="hidden" name="ticket_id" value="<?=$selected['id']?>">
          <label class="field-label">کارشناس</label>
          <?php if ((int)($selected['assigned_to'] ?? 0) === (int)$u['id']): ?>
            <button class="btn btn-secondary mt" name="op" value="unassign">لغو انتساب به من</button>
          <?php elseif ($selected['assigned_to']): ?>
            <button class="btn btn-secondary mt" name="op" value="assign">انتقال به من</button>
          <?php else: ?>
            <button class="btn btn-primary mt" name="op" value="assign">بر عهده من</button>
          <?php endif; ?>
        </form>
      </div>
    <?php endif; ?>
  </div>
  <?php else: ?>
    <div class="card empty">یک تیکت را از فهرست انتخاب کنید یا تیکت جدید بسازید.</div>
  <?php endif; ?>
</section>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var dst = document.getElementById('tkDestination');
  var box = document.getElementById('tkSellerBox');
  if (dst && box) {
    dst.addEventListener('change', function () { box.style.display = dst.value === 'seller' ? 'block' : 'none'; });
  }
});
</script>
</main>
<?php footer_html();
