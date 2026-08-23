<?php
/* ================================================================
   پیشخوان حرفه‌ای مدیریت — v5.1
   KPI کامل + کارهای امروز + نمودار ۱۴ روزه + فعالیت‌ها
   ================================================================ */

/* ---------- کمکی: اجرای امن کوئری (جدول‌های ماژول شاید نباشند) ---------- */
$safe_q = function (string $sql, $fallback = 0) use ($pdo) {
    try { $v = $pdo->query($sql)->fetchColumn(); return $v === false || $v === null ? $fallback : $v; }
    catch (Throwable $e) { return $fallback; }
};


$stats = [
    'users' => (int)$safe_q('SELECT COUNT(*) FROM users'),
    'users7' => (int)$safe_q('SELECT COUNT(*) FROM users WHERE created_at >= DATE(NOW())-INTERVAL 7 DAY'),
    'banned' => (int)$safe_q('SELECT COUNT(*) FROM users WHERE is_banned=1'),
    'premium' => (int)$safe_q('SELECT COUNT(*) FROM users WHERE premium_until > NOW()'),
    'tips' => (int)$safe_q('SELECT COUNT(*) FROM tips'),
    'published' => (int)$safe_q("SELECT COUNT(*) FROM tips WHERE status='published'"),
    'pending' => (int)$safe_q("SELECT COUNT(*) FROM tips WHERE status='pending'"),
    'reports' => (int)$safe_q("SELECT COUNT(*) FROM reports WHERE status='open'"),
    'contact' => (int)$safe_q("SELECT COUNT(*) FROM contact_messages WHERE status='new'"),
    'tickets' => (int)$safe_q("SELECT COUNT(*) FROM tickets WHERE status='open'"),
    'withdrawals' => (int)$safe_q("SELECT COUNT(*) FROM withdrawals WHERE status IN ('pending','reviewing')"),
    'sellersPending' => (int)$safe_q("SELECT COUNT(*) FROM users WHERE seller_status='pending'"),
    'boardsPending' => (int)$safe_q("SELECT COUNT(*) FROM boards WHERE status='pending'"),
    'ordersActive' => (int)$safe_q("SELECT COUNT(*) FROM board_orders WHERE status IN ('paid','shipped')"),
    'sales' => (int)$safe_q("SELECT COALESCE(SUM(price_paid),0) FROM tip_accesses WHERE access_type='purchase'"),
    'sales30' => (int)$safe_q("SELECT COALESCE(SUM(price_paid),0) FROM tip_accesses WHERE access_type='purchase' AND created_at >= DATE(NOW())-INTERVAL 30 DAY"),
    'boardSales' => (int)$safe_q("SELECT COALESCE(SUM(net_amount),0) FROM board_orders WHERE status='completed'"),
    'balance' => (int)$safe_q('SELECT COALESCE(SUM(balance),0) FROM users'),
    'escrow' => (int)$safe_q('SELECT COALESCE(balance,0) FROM users WHERE id=' . (escrow_admin_id() ?: 0)),
];

/* ---------- نمودار ۱۴ روزه ---------- */
$days = [];
for ($i = 13; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-{$i} day"))] = 0;
$tips14 = []; $users14 = [];
try {
    foreach ($pdo->query("SELECT DATE(created_at) d, COUNT(*) c FROM tips WHERE created_at >= DATE(NOW())-INTERVAL 13 DAY GROUP BY DATE(created_at)") as $r) if (isset($days[$r['d']])) $tips14[$r['d']] = (int)$r['c'];
} catch (Throwable $e) {}
try {
    foreach ($pdo->query("SELECT DATE(created_at) d, COUNT(*) c FROM users WHERE created_at >= DATE(NOW())-INTERVAL 13 DAY GROUP BY DATE(created_at)") as $r) if (isset($days[$r['d']])) $users14[$r['d']] = (int)$r['c'];
} catch (Throwable $e) {}
$tipsSeries = array_merge($days, $tips14);
$usersSeries = array_merge($days, $users14);

/* ---------- لیست‌های فعالیت ---------- */
$recentTips = [];
try { $recentTips = $pdo->query("SELECT t.id,t.title,t.status,t.created_at,u.name author FROM tips t JOIN users u ON u.id=t.author_id ORDER BY t.created_at DESC LIMIT 6")->fetchAll(); } catch (Throwable $e) {}
$newUsers = [];
try { $newUsers = $pdo->query('SELECT id,name,phone,role,verified,created_at FROM users ORDER BY created_at DESC LIMIT 6')->fetchAll(); } catch (Throwable $e) {}
$topTips = [];
try { $topTips = $pdo->query("SELECT t.id,t.title,t.views,t.likes_count,u.name author FROM tips t JOIN users u ON u.id=t.author_id WHERE t.status='published' ORDER BY t.views DESC LIMIT 5")->fetchAll(); } catch (Throwable $e) {}

/* کارهای امروز — موارد نیازمند اقدام */
$todo = [
    ['قلق در انتظار بررسی', $stats['pending'], 'admin?tab=tips', '🔧'],
    ['درخواست فروشندگی', $stats['sellersPending'], 'admin?tab=sellers', '🛒'],
    ['برد در انتظار تأیید', $stats['boardsPending'], 'admin?tab=boards', '🏪'],
    ['تسویه در انتظار', $stats['withdrawals'], 'admin?tab=withdrawals', '🏦'],
    ['گزارش‌های باز', $stats['reports'], 'admin?tab=reports', '🚩'],
    ['پیام تماس جدید', $stats['contact'], 'admin?tab=contact', '📨'],
    ['تیکت باز', $stats['tickets'], 'tickets', '✉️'],
    ['سفارش فعال برد', $stats['ordersActive'], 'admin?tab=orders', '📦'],
];
$todoTotal = 0; foreach ($todo as $t) $todoTotal += $t[1];
?>
<!-- ===== KPI اصلی ===== -->
<div class="admin-cards">
  <?php foreach ([
    ['کاربران', $stats['users'], '👥', ''],
    ['ثبت‌نام ۷ روز', $stats['users7'], '🆕', 'color:#078659'],
    ['کل قلق‌ها', $stats['tips'], '🔧', ''],
    ['در انتظار بررسی', $stats['pending'], '⏳', 'color:#b8860b'],
    ['فروش قلق (تومان)', $stats['sales'], '💳', ''],
    ['فروش ۳۰ روز', $stats['sales30'], '📈', 'color:#0a7a4a'],
    ['موجودی کاربران', $stats['balance'], '💰', ''],
  ] as $card): ?>
  <div class="card"><div class="k"><?=$card[2]?> <?=h($card[0])?></div><div class="v" style="<?=$card[3]?>"><?=fa(number_format($card[1]))?></div></div>
  <?php endforeach; ?>
</div>

<div class="grid grid-2">
  <!-- ===== کارهای امروز ===== -->
  <div class="card" style="padding:18px">
    <div class="flex between items-center" style="margin-bottom:10px">
      <h3 style="margin:0">📋 کارهای امروز <small class="muted">(<?=fa($todoTotal)?> مورد)</small></h3>
      <?php if ($todoTotal > 0): ?><span class="pill amber">نیازمند اقدام</span><?php else: ?><span class="pill green">همه انجام شد ✓</span><?php endif; ?>
    </div>
    <?php foreach ($todo as $t): ?>
    <a class="activity-item" href="<?=url($t[2])?>" style="text-decoration:none">
      <span style="font-size:16px"><?=$t[3]?></span>
      <span class="grow" style="font-weight:bold"><?=h($t[0])?></span>
      <?php if ($t[1] > 0): ?><span class="pill <?=$t[1] > 5 ? 'rose' : 'amber'?>"><?=fa($t[1])?></span><?php else: ?><span class="pill green">۰ ✓</span><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>

</div>

<!-- ===== نمودارها ===== -->
<div class="grid grid-2">
  <div class="card" style="padding:18px">
    <h3 style="margin-bottom:16px">📈 قلق‌های جدید (۱۴ روز اخیر)</h3>
    <div class="bar-chart">
      <?php $maxT = max(1, max($tipsSeries)); foreach ($tipsSeries as $d => $c): ?>
        <div class="bar" style="height:<?=max(3, round($c / $maxT * 115))?>px" title="<?=h($d)?>"><span><?=fa($c)?></span><i><?=fa(date('m/d', strtotime($d)))?></i></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card" style="padding:18px">
    <h3 style="margin-bottom:16px">👥 ثبت‌نام‌های جدید (۱۴ روز اخیر)</h3>
    <div class="bar-chart">
      <?php $maxU = max(1, max($usersSeries)); foreach ($usersSeries as $d => $c): ?>
        <div class="bar" style="height:<?=max(3, round($c / $maxU * 115))?>px" title="<?=h($d)?>"><span><?=fa($c)?></span><i><?=fa(date('m/d', strtotime($d)))?></i></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="grid grid-2">
  <!-- ===== آخرین قلق‌ها ===== -->
  <div class="card mt" style="padding:18px">
    <div class="flex between items-center" style="margin-bottom:8px">
      <h3 style="margin:0">🕒 آخرین قلق‌های ثبت‌شده</h3>
      <a class="check" style="font-size:11px" href="<?=url('admin?tab=tips')?>">همه →</a>
    </div>
    <?php foreach ($recentTips as $r): ?>
    <div class="activity-item">
      <a class="grow" href="<?=url('tip/' . $r['id'])?>" style="font-weight:bold"><?=h(mb_substr($r['title'], 0, 55))?></a>
      <span class="pill <?=$r['status'] === 'published' ? 'green' : ($r['status'] === 'pending' ? 'amber' : 'rose')?>"><?=h(status_label($r['status']))?></span>
      <small class="muted"><?=ago($r['created_at'])?></small>
    </div>
    <?php endforeach; ?>
    <?php if (!$recentTips): ?><p class="muted" style="font-size:12px">هنوز قلقی ثبت نشده.</p><?php endif; ?>
  </div>

  <!-- ===== کاربران جدید + محبوب‌ترین ===== -->
  <div>
    <div class="card" style="padding:18px">
      <div class="flex between items-center" style="margin-bottom:8px">
        <h3 style="margin:0">🆕 کاربران جدید</h3>
        <a class="check" style="font-size:11px" href="<?=url('admin?tab=users')?>">مدیریت کاربران →</a>
      </div>
      <?php foreach ($newUsers as $nu): ?>
      <div class="activity-item">
        <span class="avatar small"><?=h(mb_substr($nu['name'], 0, 1))?></span>
        <a class="grow" href="<?=url('admin-users', ['edit' => $nu['id']])?>" style="font-weight:bold"><?=h(mb_substr($nu['name'], 0, 30))?></a>
        <span class="pill <?=in_array($nu['role'], ['admin','superadmin','moderator'], true) ? 'blue' : ''?>"><?=h(role_label($nu['role']))?></span>
        <small class="muted"><?=ago($nu['created_at'])?></small>
      </div>
      <?php endforeach; ?>
      <?php if (!$newUsers): ?><p class="muted" style="font-size:12px">هنوز کاربری ثبت‌نام نکرده.</p><?php endif; ?>
    </div>

    <div class="card mt" style="padding:18px">
      <h3 style="margin-bottom:8px">🔥 پربازدیدترین قلق‌ها</h3>
      <?php foreach ($topTips as $tp): ?>
      <div class="activity-item">
        <a class="grow" href="<?=url('tip/' . $tp['id'])?>" style="font-weight:bold"><?=h(mb_substr($tp['title'], 0, 45))?></a>
        <span class="pill">👁 <?=fa($tp['views'])?></span>
        <span class="pill rose">♥ <?=fa($tp['likes_count'])?></span>
      </div>
      <?php endforeach; ?>
      <?php if (!$topTips): ?><p class="muted" style="font-size:12px">قلق منتشرشده‌ای موجود نیست.</p><?php endif; ?>
    </div>
  </div>
</div>

<!-- ===== دسترسی سریع ===== -->
<div class="grid grid-3 mt">
  <?php foreach ([
    ['admin?tab=users', '👥', 'مدیریت کاربران', 'جستجو، فیلتر و عملیات سریع'],
    ['admin-users', '🧑‍💼', 'مدیریت پیشرفته کاربران', 'ویرایش کامل، رمز و کیف پول'],
    ['admin?tab=boards', '🏪', 'فروشگاه برد', 'مدیریت بردها و سفارش‌ها'],
    ['admin?tab=withdrawals', '🏦', 'تسویه‌ها', 'درخواست‌های برداشت'],
    ['admin?tab=sellers', '🛒', 'فروشندگان', 'تأیید و مدیریت'],
    ['admin?tab=settings', '⚙️', 'تنظیمات سایت', 'متن‌ها، قیمت‌ها و سئو'],
    ['admin-finance', '💳', 'مالی و درگاه', 'زرین‌پال، زیبال، فیش'],
    ['tickets', '✉️', 'تیکت‌ها', 'پشتیبانی کاربران'],
  ] as $qa): ?>
  <a class="card" style="padding:18px;text-align:center" href="<?=url($qa[0])?>">
    <div style="font-size:26px"><?=$qa[1]?></div>
    <strong style="display:block;margin-top:6px;font-size:12.5px"><?=$qa[2]?></strong>
    <small class="muted"><?=$qa[3]?></small>
  </a>
  <?php endforeach; ?>
</div>

<!-- ===== سلامت سیستم ===== -->
<div class="card mt" style="padding:14px">
  <div class="flex gap" style="flex-wrap:wrap;align-items:center;font-size:11px">
    <b>🩺 سلامت سیستم:</b>
    <span class="pill <?=version_compare(BORDKHAN_VERSION, '5.0', '>=') ? 'green' : 'rose'?>">کد نسخه <?=h(BORDKHAN_VERSION)?></span>
    <span class="pill <?=is_dir(UPLOAD_DIR) && is_writable(UPLOAD_DIR) ? 'green' : 'rose'?>">پوشه uploads <?=is_dir(UPLOAD_DIR) ? (is_writable(UPLOAD_DIR) ? 'قابل نوشتن ✓' : 'بدون مجوز نوشتن!') : 'موجود نیست!'?></span>
    <span class="pill <?=extension_loaded('curl') ? 'green' : 'amber'?>">cURL <?=extension_loaded('curl') ? 'فعال' : 'غیرفعال'?></span>
    <span class="pill <?=extension_loaded('gd') ? 'green' : 'amber'?>">GD <?=extension_loaded('gd') ? 'فعال' : 'غیرفعال (بدون فشرده‌سازی عکس)'?></span>
    <span class="pill green">PHP <?=h(PHP_VERSION)?></span>
    <a class="check" href="<?=url('diag-version')?>" target="_blank">عیب‌یابی نسخه</a>
  </div>
</div>
