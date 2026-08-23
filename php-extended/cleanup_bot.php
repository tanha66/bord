<?php
/**
 * Bordkhan — پاک‌سازی بقایای ربات جمع‌آوری (v5.7)
 * مسیر: /admin-cleanup  (فقط مدیر)
 * امکانات: تغییر نام حساب ربات به «تیم بردخان» / انتقال قلق‌ها به مدیر و حذف حساب /
 *          حذف کامل قلق‌های ربات و حساب / حذف جدول‌ها و ستون‌های قدیمی ربات
 */
$a = require_admin();
$pdo = db();
$notice = '';
$act = $_POST['xaction'] ?? '';

if ($act) {
    if (function_exists('check_csrf')) check_csrf();
    $botId = 0;
    try {
        $q = $pdo->prepare("SELECT id,name FROM users WHERE phone='09100000000' LIMIT 1");
        $q->execute();
        $bot = $q->fetch();
        if ($bot) $botId = (int)$bot['id'];
        if (!$botId) {
            $q = $pdo->prepare("SELECT id,name FROM users WHERE name IN ('سامانه جمع‌آوری هوشمند','گردآوری خودکار') LIMIT 1");
            $q->execute();
            $bot = $q->fetch();
            if ($bot) $botId = (int)$bot['id'];
        }
    } catch (Throwable $e) {}

    if ($act === 'rename') {
        if (!$botId) { $notice = 'حساب رباتی یافت نشد — شاید قبلاً پاک شده است ✓'; }
        else {
            $pdo->prepare("UPDATE users SET name='تیم بردخان', bio='انتشار محتوای آموزشی بردخان' WHERE id=?")->execute([$botId]);
            sec_log((int)$a['id'], 'bot_cleanup', 'تغییر نام حساب ربات به تیم بردخان (#' . $botId . ')');
            $notice = 'نام حساب «گردآوری خودکار» به «تیم بردخان» تغییر کرد ✓ از این به بعد در صفحات سایت همین نام نمایش داده می‌شود.';
        }
    }
    elseif ($act === 'transfer') {
        if (!$botId) { $notice = 'حساب رباتی یافت نشد.'; }
        else {
            try {
                $pdo->prepare('UPDATE tips SET author_id=? WHERE author_id=?')->execute([(int)$a['id'], $botId]);
                $pdo->prepare('UPDATE comments SET user_id=? WHERE user_id=?')->execute([(int)$a['id'], $botId]);
                $pdo->prepare('UPDATE wallet_transactions SET user_id=? WHERE user_id=?')->execute([(int)$a['id'], $botId]);
                $pdo->prepare('DELETE FROM user_sessions WHERE user_id=?')->execute([$botId]);
                $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$botId]);
                sec_log((int)$a['id'], 'bot_cleanup', 'انتقال محتوای ربات به مدیر و حذف حساب ربات');
                $notice = 'همهٔ قلق‌ها و نظرات ربات به نام شما منتقل شد و حساب ربات حذف شد ✓';
            } catch (Throwable $e) { $notice = 'خطا: ' . $e->getMessage(); }
        }
    }
    elseif ($act === 'purge') {
        if (!$botId) { $notice = 'حساب رباتی یافت نشد.'; }
        else {
            try {
                $ids = $pdo->prepare('SELECT id FROM tips WHERE author_id=?'); $ids->execute([$botId]);
                $tipIds = array_map(fn($r) => (int)$r['id'], $ids->fetchAll());
                if ($tipIds) {
                    $in = implode(',', $tipIds);
                    @$pdo->exec("DELETE FROM comments WHERE tip_id IN ($in)");
                    @$pdo->exec("DELETE FROM comment_votes WHERE comment_id IN (SELECT id FROM comments WHERE tip_id IN ($in))");
                    @$pdo->exec("DELETE FROM ratings WHERE tip_id IN ($in)");
                    @$pdo->exec("DELETE FROM favorites WHERE tip_id IN ($in)");
                    @$pdo->exec("DELETE FROM bookmarks WHERE tip_id IN ($in)");
                    @$pdo->exec("DELETE FROM tip_accesses WHERE tip_id IN ($in)");
                    @$pdo->exec("DELETE FROM media_access WHERE tip_id IN ($in)");
                    @$pdo->exec("DELETE FROM reports WHERE target_type='tip' AND target_id IN ($in)");
                    @$pdo->exec("DELETE FROM tips WHERE id IN ($in)");
                }
                @$pdo->exec("DELETE FROM comments WHERE user_id=" . $botId);
                @$pdo->exec('DELETE FROM users WHERE id=' . $botId);
                sec_log((int)$a['id'], 'bot_cleanup', 'حذف کامل قلق‌ها و حساب ربات (' . count($tipIds) . ' قلق)');
                $notice = count($tipIds) . ' قلق ربات به‌همراه حسابش کامل حذف شد ✓';
            } catch (Throwable $e) { $notice = 'خطا: ' . $e->getMessage(); }
        }
    }
    elseif ($act === 'droptables') {
        $done = [];
        try { @$pdo->exec('DROP TABLE IF EXISTS bot_runs'); @$pdo->exec('DROP TABLE IF EXISTS bot_sources'); $done[] = 'جدول‌های bot_runs و bot_sources'; } catch (Throwable $e) {}
        foreach (['auto_collect_enabled','auto_collect_count','auto_collect_category','auto_collect_access','auto_collect_sources','auto_collect_queries','auto_collect_cron_key','auto_collect_indian_enabled','auto_collect_chinese_enabled','auto_collect_japanese_enabled','auto_collect_min_length','auto_collect_max_images','auto_collect_translate_enabled','auto_collect_extract_full','auto_collect_save_images','auto_collect_filter_repair','auto_collect_language','auto_collect_content_type','auto_collect_image_quality','auto_collect_auto_publish','auto_collect_exclude_keywords','auto_collect_save_path','auto_collect_max_retries','auto_collect_timeout','auto_collect_time_limit','auto_collect_rotate','auto_collect_last_offset','auto_collect_lock','auto_collect_stop'] as $col) {
            try { @$pdo->exec("ALTER TABLE settings DROP COLUMN $col"); } catch (Throwable $e) {}
        }
        $done[] = 'ستون‌های auto_collect_*';
        sec_log((int)$a['id'], 'bot_cleanup', 'حذف جدول‌ها/ستون‌های ربات');
        $notice = 'پاک‌سازی دیتابیس انجام شد: ' . implode(' + ', $done) . ' ✓';
    }
}

/* آمار وضعیت فعلی */
$botUser = null; $botTips = 0;
try {
    $q = $pdo->query("SELECT id,name,phone FROM users WHERE phone='09100000000' OR name IN ('سامانه جمع‌آوری هوشمند','گردآوری خودکار') LIMIT 1");
    $botUser = $q->fetch() ?: null;
    if ($botUser) {
        $c = $pdo->prepare('SELECT COUNT(*) FROM tips WHERE author_id=?'); $c->execute([(int)$botUser['id']]);
        $botTips = (int)$c->fetchColumn();
    }
} catch (Throwable $e) {}
$botTables = [];
try {
    foreach ($pdo->query("SHOW TABLES LIKE 'bot_%'")->fetchAll() as $r) $botTables[] = array_values($r)[0];
} catch (Throwable $e) {}

header_html('پاک‌سازی بقایای ربات');
?><main class="wrap page">
<div class="flex between items-center" style="flex-wrap:wrap;gap:10px;margin-bottom:16px">
  <h1 style="font-size:22px;font-weight:900">🧹 پاک‌سازی بقایای ربات</h1>
  <a class="btn btn-secondary btn-sm" href="<?=url('admin')?>">بازگشت به داشبورد</a>
</div>
<?php if ($notice): ?><div class="notice"><?=h($notice)?></div><?php endif; ?>

<div class="admin-cards">
  <div class="card"><div class="k">حساب ربات</div><div class="v" style="font-size:15px;margin-top:4px"><?=$botUser ? '<span style="color:#b8860b">موجود — ' . h($botUser['name']) . '</span>' : '<span style="color:#0a7a4a">یافت نشد ✓</span>'?></div></div>
  <div class="card"><div class="k">قلق‌های ربات</div><div class="v"><?=fa($botTips)?></div></div>
  <div class="card"><div class="k">جدول‌های قدیمی ربات</div><div class="v"><?=$botTables ? fa(count($botTables)) : '۰ ✓'?></div></div>
</div>

<?php if (!$botUser && !$botTables): ?>
<div class="card mt" style="padding:18px"><p style="font-size:13px">✓ هیچ ردی از ربات در دیتابیس نیست — همه‌چیز تمیز است.</p></div>
<?php else: ?>
<div class="card mt" style="padding:18px">
  <h3 style="margin-bottom:8px">اقدامات</h3>
  <p class="muted" style="font-size:11.5px;line-height:2.2">اگر نام «گردآوری خودکار / سامانه جمع‌آوری هوشمند» روی صفحات سایت دیده می‌شود، یکی از گزینه‌های زیر مشکل را برای همیشه حل می‌کند.</p>
  <div class="flex gap" style="flex-wrap:wrap">
    <form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="xaction" value="rename">
      <button class="btn btn-primary btn-sm">✏️ تغییر نام به «تیم بردخان» (قلق‌ها می‌مانند)</button></form>
    <form method="post" onsubmit="return confirm('همهٔ قلق‌های ربات به نام شما منتقل و حساب ربات حذف شود؟')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="xaction" value="transfer">
      <button class="btn btn-secondary btn-sm">👤 انتقال قلق‌ها به نام من + حذف حساب ربات</button></form>
    <form method="post" onsubmit="return confirm('همهٔ قلق‌های ربات و حسابش برای همیشه حذف شود؟ بازگشتی نیست!')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="xaction" value="purge">
      <button class="btn btn-danger btn-sm">🗑 حذف کامل قلق‌ها و حساب ربات</button></form>
    <form method="post" onsubmit="return confirm('جدول‌ها و ستون‌های قدیمی ربات از دیتابیس حذف شود؟')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="xaction" value="droptables">
      <button class="btn btn-secondary btn-sm">🧽 حذف جدول‌ها/ستون‌های ربات از دیتابیس</button></form>
  </div>
</div>
<?php endif; ?>
</main>
<?php footer_html();
