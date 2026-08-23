<?php
/* ================================================================
   مدیریت کاربران حرفه‌ای v5.1 — تب admin?tab=users
   KPI + جستجو/فیلتر/مرتب‌سازی + صفحه‌بندی + عملیات سریع درجا
   عملیات‌ها به همان action=admin_user قبلی وصل هستند (سازگار)
   ================================================================ */
$safe_u = function (string $sql) use ($pdo) {
    try { $v = $pdo->query($sql)->fetchColumn(); return $v === false || $v === null ? 0 : $v; }
    catch (Throwable $e) { return 0; }
};
$uKpi = [
    'total' => (int)$safe_u('SELECT COUNT(*) FROM users'),
    'banned' => (int)$safe_u('SELECT COUNT(*) FROM users WHERE is_banned=1'),
    'verified' => (int)$safe_u('SELECT COUNT(*) FROM users WHERE verified=1'),
    'experts' => (int)$safe_u("SELECT COUNT(*) FROM users WHERE role IN ('expert','moderator','admin','superadmin')"),
    'premium' => (int)$safe_u('SELECT COUNT(*) FROM users WHERE premium_until > NOW()'),
    'sellers' => (int)$safe_u("SELECT COUNT(*) FROM users WHERE seller_status='approved'"),
    'week' => (int)$safe_u('SELECT COUNT(*) FROM users WHERE created_at >= DATE(NOW())-INTERVAL 7 DAY'),
    'today' => (int)$safe_u('SELECT COUNT(*) FROM users WHERE created_at >= DATE(NOW())'),
];

/* فیلترها */
$uq = trim($_GET['q'] ?? '');
$urole = $_GET['role'] ?? '';
$ustat = $_GET['status'] ?? '';
$usort = $_GET['sort'] ?? 'newest';
$upage = max(1, (int)($_GET['p'] ?? 1));
$uper = 25;

$where = []; $params = [];
if ($uq !== '') { $where[] = '(name LIKE ? OR phone LIKE ? OR IFNULL(email,"") LIKE ?)'; $params[] = "%$uq%"; $params[] = "%$uq%"; $params[] = "%$uq%"; }
if (in_array($urole, ['member', 'expert', 'moderator', 'admin', 'superadmin'], true)) { $where[] = 'role=?'; $params[] = $urole; }
if ($ustat === 'banned') $where[] = 'is_banned=1';
elseif ($ustat === 'verified') $where[] = 'verified=1';
elseif ($ustat === 'premium') $where[] = 'premium_until > NOW()';
elseif ($ustat === 'seller') $where[] = "seller_status='approved'";
$wsql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$orderSql = [
    'newest' => 'created_at DESC',
    'oldest' => 'created_at ASC',
    'richest' => 'balance DESC',
    'points' => 'points DESC',
    'name' => 'name ASC',
][$usort] ?? 'created_at DESC';

$totalUsers = 0;
try { $c = $pdo->prepare("SELECT COUNT(*) FROM users $wsql"); $c->execute($params); $totalUsers = (int)$c->fetchColumn(); } catch (Throwable $e) {}
$pages = max(1, (int)ceil($totalUsers / $uper));
$upage = min($upage, $pages);
$offset = ($upage - 1) * $uper;
$rows = [];
try {
    $st = $pdo->prepare("SELECT id,name,phone,email,role,points,balance,verified,is_banned,seller_status,premium_until,created_at,last_login,
        (SELECT COUNT(*) FROM tips t WHERE t.author_id=users.id) tips_count
        FROM users $wsql ORDER BY $orderSql LIMIT $uper OFFSET $offset");
    $st->execute($params);
    $rows = $st->fetchAll();
} catch (Throwable $e) {}
$uUrl = function (array $extra = []) use ($uq, $urole, $ustat, $usort) {
    return url('admin', array_merge(['tab' => 'users', 'q' => $uq, 'role' => $urole, 'status' => $ustat, 'sort' => $usort], $extra));
};
?>
<div class="admin-cards">
  <?php foreach ([
    ['کل کاربران', $uKpi['total'], '👥', ''],
    ['امروز', $uKpi['today'], '🆕', 'color:#078659'],
    ['هفت روز اخیر', $uKpi['week'], '📅', ''],
    ['تعمیرکار و مدیران', $uKpi['experts'], '🛠', ''],
    ['تأییدشده', $uKpi['verified'], '✅', ''],
    ['اشتراک ویژه فعال', $uKpi['premium'], '⭐', 'color:#b8860b'],
    ['فروشنده فعال', $uKpi['sellers'], '🏪', ''],
    ['مسدودشده', $uKpi['banned'], '🚫', 'color:#b3261e'],
  ] as $uk): ?>
  <div class="card"><div class="k"><?=$uk[2]?> <?=h($uk[0])?></div><div class="v" style="<?=$uk[3]?>"><?=fa(number_format($uk[1]))?></div></div>
  <?php endforeach; ?>
</div>

<div class="card" style="padding:16px">
  <div class="flex between items-center" style="flex-wrap:wrap;gap:10px;margin-bottom:12px">
    <h3 style="margin:0">👥 کاربران <small class="muted">(<?=fa($totalUsers)?> مورد)</small></h3>
    <a class="btn btn-primary btn-sm" href="<?=url('admin-users')?>">🧑‍💼 مدیریت پیشرفته (رمز، حذف، کیف پول)</a>
  </div>

  <form method="get" class="grid grid-2" style="gap:10px;margin-bottom:14px">
    <input type="hidden" name="r" value="admin"><input type="hidden" name="tab" value="users">
    <div class="flex gap" style="flex-wrap:wrap">
      <input class="field" style="max-width:220px" name="q" value="<?=h($uq)?>" placeholder="🔍 نام، موبایل یا ایمیل…">
      <select class="field" style="max-width:150px" name="role">
        <option value="">همه نقش‌ها</option>
        <?php foreach (['member' => 'کاربر عادی', 'expert' => 'تعمیرکار', 'moderator' => 'ناظر', 'admin' => 'مدیر', 'superadmin' => 'سوپرادمین'] as $rv => $rl): ?>
          <option value="<?=$rv?>" <?=$urole === $rv ? 'selected' : ''?>><?=h($rl)?></option>
        <?php endforeach; ?>
      </select>
      <select class="field" style="max-width:150px" name="status">
        <?php foreach (['' => 'همه وضعیت‌ها', 'banned' => '🚫 مسدود', 'verified' => '✅ تأییدشده', 'premium' => '⭐ اشتراک ویژه', 'seller' => '🏪 فروشنده'] as $sv => $sl): ?>
          <option value="<?=$sv?>" <?=$ustat === $sv ? 'selected' : ''?>><?=h($sl)?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex gap" style="flex-wrap:wrap;align-items:center">
      <select class="field" style="max-width:170px" name="sort">
        <?php foreach (['newest' => 'جدیدترین', 'oldest' => 'قدیمی‌ترین', 'richest' => 'بیشترین موجودی', 'points' => 'بیشترین امتیاز', 'name' => 'الفبا'] as $sv => $sl): ?>
          <option value="<?=$sv?>" <?=$usort === $sv ? 'selected' : ''?>><?=h($sl)?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary btn-sm">اعمال</button>
      <?php if ($uq !== '' || $urole !== '' || $ustat !== ''): ?><a class="btn btn-secondary btn-sm" href="<?=url('admin', ['tab' => 'users'])?>">پاک‌سازی فیلتر</a><?php endif; ?>
    </div>
  </form>

  <div class="table-wrap"><table class="bk-table">
    <thead><tr><th>کاربر</th><th>نقش</th><th>سطح</th><th>قلق</th><th>موجودی (تومان)</th><th>وضعیت</th><th>آخرین ورود</th><th>عضویت</th><th>عملیات سریع</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $x): ?>
      <tr>
        <td>
          <div class="flex aicenter gap" style="gap:8px">
            <span class="avatar small"><?=h(mb_substr($x['name'], 0, 1))?></span>
            <div>
              <a class="check" href="<?=url('admin-users', ['edit' => $x['id']])?>" style="font-weight:bold"><?=h(mb_substr($x['name'], 0, 28))?></a>
              <small dir="ltr" class="muted" style="display:block;font-size:10px"><?=h($x['phone'])?></small>
            </div>
          </div>
        </td>
        <td><span class="pill <?=in_array($x['role'], ['admin', 'superadmin'], true) ? 'blue' : ($x['role'] === 'moderator' ? 'amber' : '')?>"><?=h(role_label($x['role']))?></span></td>
        <td><small><?=h(level_name((int)$x['points']))?></small><br><small class="muted"><?=fa($x['points'])?> امتیاز</small></td>
        <td><?=fa((int)$x['tips_count'])?></td>
        <td><b><?=money($x['balance'])?></b></td>
        <td>
          <?php if ((int)$x['is_banned']): ?><span class="pill rose">🚫 مسدود</span><?php endif; ?>
          <?php if ((int)$x['verified']): ?><span class="pill green">✅ تأیید</span><?php endif; ?>
          <?php if ($x['premium_until'] && strtotime($x['premium_until']) > time()): ?><span class="pill amber">⭐ ویژه</span><?php endif; ?>
          <?php if ($x['seller_status'] === 'approved'): ?><span class="pill blue">🏪 فروشنده</span><?php endif; ?>
          <?php if (!(int)$x['is_banned'] && !(int)$x['verified'] && $x['seller_status'] !== 'approved' && !($x['premium_until'] && strtotime($x['premium_until']) > time())): ?><span class="muted" style="font-size:10px">عادی</span><?php endif; ?>
        </td>
        <td><small class="muted"><?=$x['last_login'] ? ago($x['last_login']) : '—'?></small></td>
        <td><small class="muted"><?=date_fa($x['created_at'])?></small></td>
        <td>
          <div class="flex" style="gap:4px">
            <?php if ((int)$x['is_banned']): ?>
              <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="admin_user"><input type="hidden" name="user_id" value="<?=$x['id']?>">
                <input type="hidden" name="role" value="<?=h($x['role'])?>"><input type="hidden" name="name" value="<?=h($x['name'])?>"><input type="hidden" name="phone" value="<?=h($x['phone'])?>"><input type="hidden" name="verified" value="<?=(int)$x['verified']?>">
                <button class="btn btn-sm btn-primary" title="آزادسازی حساب">🔓 آزاد</button></form>
            <?php else: ?>
              <form method="post" style="display:inline" onsubmit="return confirm('این کاربر مسدود شود؟')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="admin_user"><input type="hidden" name="user_id" value="<?=$x['id']?>">
                <input type="hidden" name="role" value="<?=h($x['role'])?>"><input type="hidden" name="name" value="<?=h($x['name'])?>"><input type="hidden" name="phone" value="<?=h($x['phone'])?>"><input type="hidden" name="verified" value="<?=(int)$x['verified']?>">
                <input type="hidden" name="banned" value="1">
                <button class="btn btn-sm btn-danger" title="مسدودسازی">🚫</button></form>
            <?php endif; ?>
            <?php if (!(int)$x['verified'] && !(int)$x['is_banned']): ?>
              <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="admin_user"><input type="hidden" name="user_id" value="<?=$x['id']?>">
                <input type="hidden" name="role" value="<?=h($x['role'])?>"><input type="hidden" name="name" value="<?=h($x['name'])?>"><input type="hidden" name="phone" value="<?=h($x['phone'])?>">
                <input type="hidden" name="verified" value="1">
                <button class="btn btn-sm btn-secondary" title="تأیید کاربر">✅</button></form>
            <?php endif; ?>
            <a class="btn btn-sm btn-secondary" href="<?=url('admin-users', ['edit' => $x['id']])?>" title="ویرایش کامل">✏️</a>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="9" style="text-align:center;padding:22px" class="muted">کاربری با این مشخصات پیدا نشد.</td></tr><?php endif; ?>
    </tbody>
  </table></div>

  <?php if ($pages > 1): ?>
  <div class="flex gap mt" style="flex-wrap:wrap">
    <?php for ($pi = max(1, $upage - 3); $pi <= min($pages, $upage + 3); $pi++): ?>
      <a class="pill <?=$pi === $upage ? 'green' : ''?>" href="<?=$uUrl(['p' => $pi])?>"><?=fa($pi)?></a>
    <?php endfor; ?>
    <small class="muted" style="align-self:center">صفحه <?=fa($upage)?> از <?=fa($pages)?></small>
  </div>
  <?php endif; ?>

  <div class="notice" style="font-size:11px;line-height:2;margin-top:12px">
    💡 شارژ/کسر کیف پول، تغییر رمز، حذف کاربر و ویرایش کامل در <a class="check" href="<?=url('admin-users')?>">مدیریت پیشرفته کاربران</a> انجام می‌شود.
  </div>
</div>
