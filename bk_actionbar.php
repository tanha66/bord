<?php
/**
 * Bordkhan — نوار شناور پایین صفحه (قابل ویرایش از پنل مدیریت).
 * ذخیره در settings با کلید actionbar_json.
 * توابع: bk_actionbar_items(), bk_render_actionbar(), و روت مدیریت /admin-actionbar.
 * migrate.php ستون settings.actionbar_json را می‌سازد.
 */
require_once __DIR__ . '/bk_extended.php';

function bk_actionbar_default(): array {
    return [
        ['label'=>'خانه','href'=>'/','icon'=>'⌂','primary'=>false,'enabled'=>true,'roles'=>[]],
        ['label'=>'ثبت قلق','href'=>'/upload','icon'=>'＋','primary'=>true,'enabled'=>true,'roles'=>[]],
        ['label'=>'ثبت برد','href'=>'/boards/new','icon'=>'▣','primary'=>false,'enabled'=>true,'roles'=>['seller','admin']],
        ['label'=>'فروشگاه','href'=>'/boards','icon'=>'🛒','primary'=>false,'enabled'=>true,'roles'=>[]],
        ['label'=>'کیف پول','href'=>'/wallet','icon'=>'₮','primary'=>false,'enabled'=>true,'roles'=>[]],
        ['label'=>'پشتیبانی','href'=>'/tickets','icon'=>'✉','primary'=>false,'enabled'=>true,'roles'=>[]],
        ['label'=>'پروفایل','href'=>'/wallet','icon'=>'●','primary'=>false,'enabled'=>true,'roles'=>[]],
    ];
}

function bk_actionbar_items(): array {
    $raw = bk_setting('actionbar_json', '');
    if ($raw) { $j = json_decode($raw, true); if (is_array($j) && $j) return $j; }
    return bk_actionbar_default();
}

function bk_actionbar_visible(?array $u): array {
    $role = $u ? ($u['role'] ?? 'user') : 'guest';
    if ($role === 'member') $role = 'user';
    $out = [];
    foreach (bk_actionbar_items() as $it) {
        if (empty($it['enabled'])) continue;
        $roles = $it['roles'] ?? [];
        if (!$roles || in_array($role, $roles, true) || ($role !== 'guest' && in_array('user', $roles, true))) $out[] = $it;
    }
    return array_slice($out, 0, 8);
}

function bk_render_actionbar(?array $u): void {
    $items = bk_actionbar_visible($u);
    if (!$items) return;
    echo '<div class="bk-actionbar-wrap"><nav class="bk-actionbar">';
    foreach ($items as $it) {
        $cls = 'bk-ab-item' . (!empty($it['primary']) ? ' bk-ab-primary' : '');
        echo '<a class="' . $cls . '" href="' . h($it['href']) . '"><span class="bk-ab-ic">' . h($it['icon']) . '</span><span class="bk-ab-lb">' . h($it['label']) . '</span></a>';
    }
    echo '</nav></div>';
    echo '<style>
    .bk-actionbar-wrap{position:fixed;inset-inline:0;bottom:0;z-index:60;padding:8px 10px;pointer-events:none}
    .bk-actionbar{pointer-events:auto;max-width:760px;margin:auto;display:flex;gap:6px;background:rgba(13,20,32,.96);border:1px solid #2A3A4E;border-radius:16px;padding:8px;box-shadow:0 10px 40px rgba(0,0,0,.5);backdrop-filter:blur(10px);overflow-x:auto}
    .bk-ab-item{flex:1;min-width:56px;display:flex;flex-direction:column;align-items:center;gap:2px;padding:8px 4px;border-radius:12px;color:#94a3b8;font-size:11px;font-weight:800;text-decoration:none;white-space:nowrap}
    .bk-ab-item:hover{background:rgba(255,255,255,.06);color:#fff}
    .bk-ab-primary{background:#10b981;color:#04110b}
    .bk-ab-primary:hover{background:#34d399;color:#04110b}
    .bk-ab-ic{font-size:17px;line-height:1}
    @media(min-width:640px){.bk-ab-item{flex-direction:row;gap:7px;font-size:13px}}
    body{padding-bottom:76px}
    </style>';
}

/* روت مدیریت: /admin-actionbar */
if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'bk_actionbar.php' || (($GLOBALS['page'] ?? '') === 'admin-actionbar')) {
    $admin = bk_admin();
    $pdo = db();
    $notice = '';
    if (($_POST['ab_action'] ?? '') === 'save') {
        $labels = $_POST['label'] ?? []; $hrefs = $_POST['href'] ?? []; $icons = $_POST['icon'] ?? [];
        $primaries = $_POST['primary'] ?? []; $enabled = $_POST['enabled'] ?? []; $roles = $_POST['roles'] ?? [];
        $items = [];
        foreach ($labels as $i => $lb) {
            $lb = trim((string)$lb); $hr = trim((string)($hrefs[$i] ?? ''));
            if ($lb === '' || strpos($hr, '/') !== 0) continue;
            $items[] = [
                'label' => mb_substr($lb, 0, 24),
                'href' => mb_substr($hr, 0, 120),
                'icon' => mb_substr(trim((string)($icons[$i] ?? '•')) ?: '•', 0, 4),
                'primary' => isset($primaries[$i]),
                'enabled' => isset($enabled[$i]),
                'roles' => array_values(array_intersect((array)($roles[$i] ?? []), ['guest','user','seller','support','supervisor','admin'])),
            ];
        }
        $items = array_slice($items, 0, 12);
        $pdo->prepare("UPDATE settings SET actionbar_json=? WHERE id=1")->execute([json_encode($items, JSON_UNESCAPED_UNICODE)]);
        $notice = 'نوار شناور ذخیره شد.';
    }
    $items = bk_actionbar_items();
    $allRoles = ['guest'=>'مهمان','user'=>'کاربر','seller'=>'فروشنده','support'=>'پشتیبانی','supervisor'=>'ناظر','admin'=>'مدیر'];
    header_html('نوار شناور');
    ?><main class="wrap page"><div class="page-title"><h1>مدیریت نوار شناور پایین صفحه</h1><p><?=h($notice ?: 'آیتم‌ها را کم/زیاد کنید. حداکثر ۸ آیتم روی نوار نمایش داده می‌شود.')?></p></div>
    <form method="post"><input type="hidden" name="ab_action" value="save">
    <div id="ab-rows">
    <?php foreach ($items as $i => $it): ?>
      <div class="card auth-card mt ab-row">
        <div class="grid grid-2">
          <div><label class="field-label">عنوان</label><input class="field" name="label[]" value="<?=h($it['label'])?>" required></div>
          <div><label class="field-label">لینک (با / شروع شود)</label><input class="field" dir="ltr" name="href[]" value="<?=h($it['href'])?>" required></div>
          <div><label class="field-label">آیکون</label><input class="field" name="icon[]" value="<?=h($it['icon'])?>" style="text-align:center"></div>
          <div style="display:flex;gap:18px;align-items:center;padding-top:22px">
            <label><input type="checkbox" name="primary[<?=$i?>]" <?=!empty($it['primary'])?'checked':''?>> دکمه اصلی</label>
            <label><input type="checkbox" name="enabled[<?=$i?>]" <?=!empty($it['enabled'])?'checked':''?>> فعال</label>
          </div>
        </div>
        <div style="margin-top:8px"><span class="field-label" style="display:inline">نمایش برای:</span>
          <?php foreach ($allRoles as $rk=>$rv): ?>
            <label style="margin-inline-end:10px;font-size:12px"><input type="checkbox" name="roles[<?=$i?>][]" value="<?=$rk?>" <?=in_array($rk,$it['roles']??[],true)?'checked':''?>> <?=h($rv)?></label>
          <?php endforeach; ?>
          <small class="muted">(خالی = همه)</small>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-secondary mt" onclick="bkAddRow()">➕ افزودن آیتم</button>
    <button class="btn btn-primary mt">💾 ذخیره نوار</button>
    </form>
    <template id="ab-tpl">
      <div class="card auth-card mt ab-row">
        <div class="grid grid-2">
          <div><label class="field-label">عنوان</label><input class="field" name="label[]" required></div>
          <div><label class="field-label">لینک (با / شروع شود)</label><input class="field" dir="ltr" name="href[]" value="/" required></div>
          <div><label class="field-label">آیکون</label><input class="field" name="icon[]" value="•" style="text-align:center"></div>
          <div style="display:flex;gap:18px;align-items:center;padding-top:22px">
            <label><input type="checkbox" name="primary[__I__]"> دکمه اصلی</label>
            <label><input type="checkbox" name="enabled[__I__]" checked> فعال</label>
          </div>
        </div>
      </div>
    </template>
    <script>function bkAddRow(){var i=Date.now();var t=document.getElementById('ab-tpl').innerHTML.replace(/__I__/g,i);var d=document.createElement('div');d.innerHTML=t;document.getElementById('ab-rows').appendChild(d.firstElementChild);}</script>
    </main><?php footer_html(); exit;
}
