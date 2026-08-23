<?php
/**
 * Bordkhan — لاگ امنیتی مدیر (v5.7)
 * مسیر: /admin-security  (فقط مدیر)
 * ورودها، ورودهای ناموفق، قفل حساب، تغییر رمز، تأیید ایمیل، تغییرات مدیر و IP‌ها
 */
$a = require_admin();
$pdo = db();

$fAction = trim((string)($_GET['f'] ?? ''));
$fQ = trim((string)($_GET['q'] ?? ''));
$pageN = max(1, (int)($_GET['p'] ?? 1));
$per = 50;

$acts = [
    '' => 'همه رویدادها',
    'login_success' => '✅ ورود موفق',
    'login_failed' => '⚠️ ورود ناموفق',
    'login_blocked' => '⛔ تلاش در حالت قفل',
    'account_locked' => '🔒 قفل حساب',
    'password_reset' => '🔑 تغییر رمز',
    'email_verified' => '📧 تأیید ایمیل',
    'logout_all' => '🚪 خروج همهٔ دستگاه‌ها',
    'session_killed' => '🖥 بستن نشست',
    'admin_user_updated' => '👤 تغییر کاربر توسط مدیر',
    'bot_cleanup' => '🧹 پاک‌سازی ربات',
];

$where = []; $params = [];
if (isset($acts[$fAction]) && $fAction !== '') { $where[] = 'action=?'; $params[] = $fAction; }
if ($fQ !== '') { $where[] = '(detail LIKE ? OR ip LIKE ? OR u.name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)'; for ($i = 0; $i < 5; $i++) $params[] = "%$fQ%"; }
$wsql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = 0; $rows = [];
try {
    $cq = $pdo->prepare("SELECT COUNT(*) FROM security_log l LEFT JOIN users u ON u.id=l.user_id $wsql");
    $cq->execute($params); $total = (int)$cq->fetchColumn();
    $pages = max(1, (int)ceil($total / $per)); $pageN = min($pageN, $pages);
    $off = ($pageN - 1) * $per;
    $sq = $pdo->prepare("SELECT l.*,u.name user_name,u.phone,u.email FROM security_log l LEFT JOIN users u ON u.id=l.user_id $wsql ORDER BY l.id DESC LIMIT $per OFFSET $off");
    $sq->execute($params); $rows = $sq->fetchAll();
} catch (Throwable $e) {}

/* خلاصهٔ ۲۴ ساعت */
$sum = ['ok' => 0, 'fail' => 0, 'lock' => 0];
try {
    foreach ($pdo->query("SELECT action,COUNT(*) c FROM security_log WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY action") as $r) {
        if ($r['action'] === 'login_success') $sum['ok'] = (int)$r['c'];
        if ($r['action'] === 'login_failed') $sum['fail'] = (int)$r['c'];
        if ($r['action'] === 'account_locked') $sum['lock'] = (int)$r['c'];
    }
} catch (Throwable $e) {}

header_html('لاگ امنیتی');
?><main class="wrap page">
<div class="flex between items-center" style="flex-wrap:wrap;gap:10px;margin-bottom:16px">
  <h1 style="font-size:22px;font-weight:900">🛡 لاگ امنیتی</h1>
  <div class="flex gap" style="gap:6px">
    <a class="btn btn-secondary btn-sm" href="<?=url('admin')?>">بازگشت</a>
    <a class="btn btn-secondary btn-sm" href="<?=url('admin-cleanup')?>">🧹 پاک‌سازی ربات</a>
  </div>
</div>

<div class="admin-cards">
  <div class="card"><div class="k">✅ ورود موفق (۲۴س)</div><div class="v" style="color:#0a7a4a"><?=fa($sum['ok'])?></div></div>
  <div class="card"><div class="k">⚠️ ورود ناموفق (۲۴س)</div><div class="v" style="color:#b8860b"><?=fa($sum['fail'])?></div></div>
  <div class="card"><div class="k">🔒 قفل حساب (۲۴س)</div><div class="v" style="color:#b3261e"><?=fa($sum['lock'])?></div></div>
  <div class="card"><div class="k">کل رکوردها</div><div class="v"><?=fa($total)?></div></div>
</div>

<div class="card" style="padding:16px;margin-top:14px">
  <form method="get" class="flex gap" style="flex-wrap:wrap;gap:8px">
    <input type="hidden" name="r" value="admin-security">
    <select class="field" style="max-width:200px" name="f">
      <?php foreach ($acts as $k => $v): ?><option value="<?=$k?>" <?=$fAction === $k ? 'selected' : ''?>><?=h($v)?></option><?php endforeach; ?>
    </select>
    <input class="field" style="max-width:240px" name="q" value="<?=h($fQ)?>" placeholder="🔍 نام، ایمیل، موبایل یا IP…">
    <button class="btn btn-primary btn-sm">اعمال</button>
  </form>
</div>

<div class="card mt" style="padding:0">
<?php if (!$rows): ?>
  <p class="muted" style="padding:20px;font-size:12px">رویدادی ثبت نشده است. جدول security_log با اجرای migrate.php ساخته می‌شود.</p>
<?php else: ?>
<div class="table-wrap"><table class="bk-table">
  <thead><tr><th>#</th><th>رویداد</th><th>کاربر</th><th>جزئیات</th><th>IP</th><th>زمان</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?=fa((int)$r['id'])?></td>
    <td><span class="pill <?=in_array($r['action'],['login_success','email_verified'],true)?'green':(in_array($r['action'],['login_failed','login_blocked','account_locked'],true)?'rose':'')?>"><?=h($acts[$r['action']] ?? $r['action'])?></span></td>
    <td><?=$r['user_id'] ? '<a class="check" href="'.url('admin-users',['edit'=>(int)$r['user_id']]).'">'.h(mb_substr((string)$r['user_name'],0,20)).'</a>' : '<span class="muted">—</span>'?></td>
    <td class="muted" style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?=h($r['detail'] ?? '')?>"><?=h($r['detail'] ?: '—')?></td>
    <td dir="ltr" class="muted"><?=h($r['ip'] ?: '—')?></td>
    <td class="muted"><?=ago($r['created_at'])?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php if ($pages > 1): ?>
<div class="flex gap mt" style="flex-wrap:wrap;padding:12px">
<?php for ($pi = max(1,$pageN-3); $pi <= min($pages,$pageN+3); $pi++): ?>
  <a class="pill <?=$pi === $pageN ? 'green' : ''?>" href="<?=url('admin-security',['f'=>$fAction,'q'=>$fQ,'p'=>$pi])?>"><?=fa($pi)?></a>
<?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>
</div>

<div class="card mt" style="padding:14px">
  <p class="muted" style="font-size:11px;line-height:2.2;margin:0">
    🕐 پاک‌سازی خودکار لاگ‌های قدیمی‌تر از ۹۰ روز با کرون سبک:
    <code dir="ltr">wget -q -O /dev/null "<?=h(SITE_URL)?>/cron-cleanup?key=INSTALL_KEY"</code>
    (هر روز یک‌بار در cPanel → Cron Jobs)
  </p>
</div>
</main>
<?php footer_html();
