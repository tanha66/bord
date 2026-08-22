<?php
/**
 * Bordkhan PHP parity handlers.
 * Include from patched index.php after config/index helpers are loaded.
 * Requires migrate.php once before using the new actions.
 */

function bk_json(array $data, int $status = 200): never {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function bk_user(): ?array { return function_exists('current_user') ? current_user() : null; }
function bk_login(): array { return function_exists('require_login') ? require_login() : (bk_user() ?: bk_json(['ok'=>false,'error'=>'ابتدا وارد شوید'],401)); }
function bk_admin(): array { return function_exists('require_admin') ? require_admin() : bk_json(['ok'=>false,'error'=>'دسترسی مدیر لازم است'],403); }
function bk_clean(string $v): string { return function_exists('clean_text') ? clean_text($v) : trim(strip_tags($v)); }
function bk_notify(int $uid, string $title, string $body, string $link = ''): void {
    if (function_exists('notify_user')) notify_user($uid, 'system', $title, $body, $link);
    else db()->prepare('INSERT INTO notifications(user_id,type,title,body,link) VALUES(?,?,?,?,?)')->execute([$uid,'system',$title,$body,$link]);
}
function bk_setting(string $key, mixed $default = null): mixed {
    static $s = null;
    if ($s === null) $s = db()->query('SELECT * FROM settings WHERE id=1 LIMIT 1')->fetch() ?: [];
    return array_key_exists($key, $s) && $s[$key] !== null && $s[$key] !== '' ? $s[$key] : $default;
}
function bk_balance(int $uid): int {
    $q = db()->prepare('SELECT balance FROM users WHERE id=?'); $q->execute([$uid]); return (int)$q->fetchColumn();
}
function bk_ajax(): bool {
    return str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json') || !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
}
function bk_back(string $path): never {
    if (function_exists('redirect_to')) redirect_to($path);
    header('Location: ' . (str_starts_with($path, '/') ? $path : '/' . ltrim($path, '/'))); exit;
}
function bk_tx(int $uid, int $amount, string $type, string $note, string $status = 'confirmed', array $extra = []): void {
    $bal = bk_balance($uid);
    $columns = ['user_id','type','amount','balance_after','note','status'];
    $values = [$uid,$type,$amount,$bal,$note,$status];
    foreach (['method','gateway','receipt_url','bank_name','card_number','reference'] as $k) {
        if (array_key_exists($k, $extra)) { $columns[] = $k; $values[] = $extra[$k]; }
    }
    $marks = implode(',', array_fill(0, count($columns), '?'));
    db()->prepare('INSERT INTO wallet_transactions(' . implode(',', $columns) . ') VALUES(' . $marks . ')')->execute($values);
}
function bk_upload_receipt(): ?string {
    $f = $_FILES['receipt'] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if ((int)$f['size'] > 5 * 1024 * 1024) return null;
    $mime = file_mime($f['tmp_name']);
    $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime] ?? null;
    if (!$ext) return null;
    if (!is_dir(UPLOAD_DIR . '/receipts')) mkdir(UPLOAD_DIR . '/receipts', 0755, true);
    $name = 'receipt-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/receipts/' . $name)) return null;
    return UPLOAD_URL . '/receipts/' . $name;
}
function bk_http_json(string $url, array $body, array $headers = []): array {
    if (!function_exists('curl_init')) return ['error' => 'PHP cURL extension is required'];
    $ch = curl_init($url);
    $json = json_encode($body, JSON_UNESCAPED_UNICODE);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $json,
        CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json','Accept: application/json'], $headers),
    ]);
    $raw = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($err) return ['error' => $err];
    $out = json_decode((string)$raw, true);
    return is_array($out) ? $out : ['error' => 'پاسخ درگاه نامعتبر است', 'raw' => $raw];
}
function bk_gateway_request(string $gateway, int $amountToman, string $callback, string $description, string $mobile): array {
    $amountRial = $amountToman * 10;
    $merchant = (string)bk_setting('gateway_merchant_id', '');
    $key = (string)bk_setting('gateway_api_key', $merchant);
    $sandbox = (int)bk_setting('gateway_sandbox', 1) === 1;
    if ($merchant === '' && $gateway !== 'idipay') return ['error' => 'کد پذیرنده در تنظیمات وارد نشده است'];

    if ($gateway === 'zarinpal') {
        $base = $sandbox ? 'https://sandbox.zarinpal.com/pg/v4/payment' : 'https://payment.zarinpal.com/pg/v4/payment';
        $r = bk_http_json($base . '/request.json', [
            'merchant_id' => $merchant, 'amount' => $amountRial, 'callback_url' => $callback,
            'description' => $description, 'metadata' => ['mobile' => $mobile],
        ]);
        if (($r['data']['code'] ?? 0) == 100 && !empty($r['data']['authority'])) return ['authority'=>$r['data']['authority'], 'url'=>($sandbox?'https://sandbox.zarinpal.com/pg/StartPay/':'https://www.zarinpal.com/pg/StartPay/') . $r['data']['authority']];
        return ['error' => $r['errors']['message'] ?? 'خطا در درخواست زرین‌پال', 'response'=>$r];
    }
    if ($gateway === 'idipay') {
        if ($key === '') return ['error'=>'API Key آیدیپی در تنظیمات وارد نشده است'];
        $orderId = 'BK-' . date('YmdHis') . '-' . random_int(100,999);
        $r = bk_http_json('https://api.idpay.ir/v1.1/payment', [
            'order_id' => $orderId, 'amount' => $amountRial,
            'callback' => $callback, 'desc' => $description, 'phone' => $mobile,
        ], ['X-API-KEY: ' . $key, 'X-SANDBOX: ' . ($sandbox ? '1' : '0')]);
        if (!empty($r['id']) && !empty($r['link'])) return ['authority'=>$r['id'], 'url'=>$r['link'], 'order_id'=>$orderId];
        return ['error' => $r['error_message'] ?? $r['message'] ?? 'خطا در درخواست آیدیپی', 'response'=>$r];
    }
    if ($gateway === 'zibal') {
        $r = bk_http_json('https://gateway.zibal.ir/v1/request', [
            'merchant' => $merchant, 'amount' => $amountRial, 'callbackUrl' => $callback,
            'description' => $description, 'mobile' => $mobile,
        ]);
        if (($r['result'] ?? -1) === 100 && isset($r['trackId'])) return ['authority'=>(string)$r['trackId'], 'url'=>'https://gateway.zibal.ir/start/' . $r['trackId']];
        return ['error' => $r['message'] ?? 'خطا در درخواست زیبال', 'response'=>$r];
    }
    return ['error' => 'درگاه انتخاب‌شده پشتیبانی نمی‌شود'];
}
function bk_gateway_verify(string $gateway, string $authority, int $amountToman, string $orderId = ''): array {
    $merchant = (string)bk_setting('gateway_merchant_id', ''); $key = (string)bk_setting('gateway_api_key', $merchant); $rial = $amountToman * 10;
    if ($gateway === 'zarinpal') {
        $base = ((int)bk_setting('gateway_sandbox',1) === 1) ? 'https://sandbox.zarinpal.com/pg/v4/payment' : 'https://payment.zarinpal.com/pg/v4/payment';
        $r = bk_http_json($base . '/verify.json', ['merchant_id'=>$merchant,'amount'=>$rial,'authority'=>$authority]);
        return (($r['data']['code'] ?? 0) == 100 || ($r['data']['code'] ?? 0) == 101) ? ['ok'=>true,'reference'=>(string)($r['data']['ref_id'] ?? $authority)] : ['ok'=>false,'error'=>$r['errors']['message'] ?? 'تأیید زرین‌پال ناموفق بود'];
    }
    if ($gateway === 'zibal') {
        $r = bk_http_json('https://gateway.zibal.ir/v1/verify', ['merchant'=>$merchant,'trackId'=>$authority]);
        return (($r['result'] ?? -1) === 100) ? ['ok'=>true,'reference'=>(string)($r['refNumber'] ?? $authority)] : ['ok'=>false,'error'=>$r['message'] ?? 'تأیید زیبال ناموفق بود'];
    }
    if ($gateway === 'idipay') {
        $r = bk_http_json('https://api.idpay.ir/v1.1/payment/verify', ['id'=>$authority,'order_id'=>$orderId], ['X-API-KEY: '.$key,'X-SANDBOX: '.((int)bk_setting('gateway_sandbox',1)===1?'1':'0')]);
        return (($r['status'] ?? 0) === 100) ? ['ok'=>true,'reference'=>(string)($r['settlement']['id'] ?? $authority)] : ['ok'=>false,'error'=>$r['error_message'] ?? 'تأیید آیدیپی ناموفق بود'];
    }
    return ['ok'=>false,'error'=>'درگاه نامعتبر'];
}

function bk_extended_action(string $action): never {
    if ($action === 'profile_save') {
        $u = bk_login(); $postal = bk_clean($_POST['postal_code'] ?? '');
        if ($postal !== '' && !preg_match('/^\d{10}$/', $postal)) bk_json(['ok'=>false,'error'=>'کد پستی باید ۱۰ رقم باشد'],422);
        db()->prepare('UPDATE users SET name=?,address=?,postal_code=?,landline=?,mobile=?,city=? WHERE id=?')->execute([
            bk_clean($_POST['name'] ?? $u['name']), trim($_POST['address'] ?? ''), $postal,
            bk_clean($_POST['landline'] ?? ''), bk_clean($_POST['mobile'] ?? ''), bk_clean($_POST['city'] ?? ''), $u['id'],
        ]);
        bk_json(['ok'=>true,'message'=>'اطلاعات آدرس ذخیره شد']);
    }
    if ($action === 'wallet_card_to_card') {
        $u = bk_login(); $amount = (int)($_POST['amount'] ?? 0); $bank = bk_clean($_POST['bank_name'] ?? ''); $card = bk_clean($_POST['card_number'] ?? ''); $receipt = bk_upload_receipt();
        $min = (int)bk_setting('gateway_min_charge',100000);
        if ($amount < $min || !$bank || strlen(preg_replace('/\D/','',$card)) < 10 || !$receipt) bk_json(['ok'=>false,'error'=>'مبلغ، بانک، کارت و تصویر فیش الزامی است'],422);
        bk_tx($u['id'], $amount, 'charge', 'درخواست شارژ کارت‌به‌کارت', 'pending', ['method'=>'z2c','receipt_url'=>$receipt,'bank_name'=>$bank,'card_number'=>$card]);
        $admins = db()->query("SELECT id FROM users WHERE role IN ('admin','superadmin')")->fetchAll(); foreach($admins as $a) bk_notify((int)$a['id'],'فیش شارژ جدید','یک فیش کارت‌به‌کارت برای بررسی ثبت شد.',url('admin-finance'));
        if (!bk_ajax()) bk_back('wallet-plus');
        bk_json(['ok'=>true,'message'=>'فیش ثبت شد؛ پس از تأیید مدیر موجودی اضافه می‌شود']);
    }
    if ($action === 'wallet_gateway_start') {
        $u = bk_login(); $amount = (int)($_POST['amount'] ?? 0); $gateway = (string)bk_setting('gateway_type','zarinpal');
        $min = (int)bk_setting('gateway_min_charge',100000); $max = (int)bk_setting('gateway_max_charge',50000000);
        if (!(int)bk_setting('gateway_enabled',0)) bk_json(['ok'=>false,'error'=>'درگاه توسط مدیر غیرفعال است'],503);
        if ($amount < $min || $amount > $max) bk_json(['ok'=>false,'error'=>'مبلغ خارج از محدوده مجاز'],422);
        $callback = SITE_URL . '/wallet-plus?verify=1';
        $r = bk_gateway_request($gateway,$amount,$callback,'شارژ کیف پول بردخان',(string)($u['mobile'] ?? $u['phone']));
        if (!empty($r['error'])) { if (!bk_ajax()) { $GLOBALS['bk_error']=$r['error']; bk_back('wallet-plus'); } bk_json(['ok'=>false,'error'=>$r['error']],502); }
        db()->prepare('INSERT INTO bk_gateway_payments(user_id,amount,gateway,authority,order_id) VALUES(?,?,?,?,?)')->execute([$u['id'],$amount,$gateway,$r['authority'],$r['order_id'] ?? null]);
        if (!bk_ajax()) { header('Location: '.$r['url']); exit; }
        bk_json(['ok'=>true,'url'=>$r['url'],'authority'=>$r['authority']]);
    }
    if ($action === 'board_buy_extended' || $action === 'board_buy') {
        $u = bk_login(); $bid=(int)($_POST['board_id']??0); $full=bk_clean($_POST['full_name']??$u['name']); $phone=bk_clean($_POST['phone']??($u['mobile']??$u['phone'])); $address=trim($_POST['address']??($u['address']??'')); $city=bk_clean($_POST['city']??''); $postal=bk_clean($_POST['postal_code']??($u['postal_code']??''));
        if (!$full || !preg_match('/^09\d{9}$/',preg_replace('/\D/','',$phone)) || strlen(preg_replace('/\D/','',$postal))!==10 || mb_strlen($address)<8 || !$city) bk_json(['ok'=>false,'error'=>'نام، موبایل، شهر، آدرس و کد پستی معتبر الزامی است'],422);
        $q=db()->prepare("SELECT * FROM boards WHERE id=? AND status='approved' AND stock>0");$q->execute([$bid]);$b=$q->fetch();if(!$b)bk_json(['ok'=>false,'error'=>'برد موجود نیست'],404);if((int)$b['seller_id']===$u['id'])bk_json(['ok'=>false,'error'=>'برد خودتان را نمی‌توانید بخرید'],422);
        $amount=(int)$b['price']; if (bk_balance($u['id'])<$amount) { if(!bk_ajax()) bk_back('wallet'); bk_json(['ok'=>false,'error'=>'موجودی کیف پول کافی نیست'],422); }
        $pdo=db();$escrow=function_exists('escrow_admin_id')?(int)escrow_admin_id():0;
        if (!$escrow || $escrow === (int)$u['id']) { if(!bk_ajax()) bk_back('boards'); bk_json(['ok'=>false,'error'=>'حساب امانت سیستم پیکربندی نشده است'],503); }
        $pdo->beginTransaction();try {
            $lock=$pdo->prepare('SELECT balance FROM users WHERE id=? FOR UPDATE');$lock->execute([$u['id']]);$bal=(int)$lock->fetchColumn();
            if($bal<$amount) throw new Exception('balance');
            $pdo->prepare('UPDATE users SET balance=balance-? WHERE id=?')->execute([$amount,$u['id']]);
            $pdo->prepare('UPDATE users SET balance=balance+? WHERE id=?')->execute([$amount,$escrow]);
            $net=$amount-(int)floor($amount*(int)bk_setting('board_commission_percent',10)/100);
            $pdo->prepare('INSERT INTO board_orders(board_id,buyer_id,seller_id,admin_id,amount,commission_percent,commission_amount,net_amount,status,full_name,phone,address,city,postal_code) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$bid,$u['id'],$b['seller_id'],$escrow,$amount,(int)bk_setting('board_commission_percent',10),$amount-$net,$net,'paid',$full,$phone,$address,$city,$postal]);
            $pdo->prepare('UPDATE boards SET stock=stock-1,sold_count=sold_count+1 WHERE id=?')->execute([$bid]);$pdo->commit();
            bk_notify((int)$b['seller_id'],'سفارش جدید برد','آدرس ارسال در سفارش ثبت شده است؛ ثبت شرکت حمل و کد رهگیری اجباری است.',url('my-boards'));
            if(!bk_ajax()) bk_back('board/'.$bid);
            bk_json(['ok'=>true,'message'=>'خرید ثبت شد؛ وجه در امانت است']);
        } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if(!bk_ajax())bk_back('board/'.$bid);bk_json(['ok'=>false,'error'=>'ثبت خرید انجام نشد'],500);}
    }
    if ($action === 'board_ship_extended' || $action === 'board_ship') {
        $u=bk_login();$oid=(int)($_POST['order_id']??0);$carrier=bk_clean($_POST['carrier']??'');$tracking=bk_clean($_POST['tracking_code']??'');
        if (!in_array($carrier,['پست','تیپاکس','باربری','پیک'],true) || mb_strlen($tracking)<6) bk_json(['ok'=>false,'error'=>'شرکت حمل و کد رهگیری اجباری است'],422);
        $q=db()->prepare("SELECT * FROM board_orders WHERE id=? AND seller_id=? AND status='paid'");$q->execute([$oid,$u['id']]);if(!$q->fetch())bk_json(['ok'=>false,'error'=>'سفارش قابل ارسال نیست'],404);
        db()->prepare("UPDATE board_orders SET status='shipped',carrier=?,tracking_code=?,shipped_at=NOW() WHERE id=?")->execute([$carrier,$tracking,$oid]);
        $q=db()->prepare('SELECT buyer_id FROM board_orders WHERE id=?');$q->execute([$oid]);if($buyer=$q->fetchColumn())bk_notify((int)$buyer,'برد ارسال شد','شرکت حمل: '.$carrier.' — کد رهگیری: '.$tracking,url('boards'));
        if(!bk_ajax()) bk_back('my-boards');
        bk_json(['ok'=>true,'message'=>'ارسال با کد رهگیری ثبت شد']);
    }
    bk_json(['ok'=>false,'error'=>'اکشن PHP ناشناخته'],400);
}

function bk_render_wallet_plus(): never {
    $u=bk_login();
    if (isset($_GET['verify'], $_GET['Authority'])) {
        $pdo=db();
        $pdo->beginTransaction();
        try {
            $q=$pdo->prepare("SELECT * FROM bk_gateway_payments WHERE user_id=? AND authority=? AND status='pending' FOR UPDATE");
            $q->execute([$u['id'],$_GET['Authority']]);$p=$q->fetch();
            if($p){$v=bk_gateway_verify($p['gateway'],(string)$p['authority'],(int)$p['amount'],(string)($p['order_id'] ?? ''));if($v['ok']){$pdo->prepare("UPDATE users SET balance=balance+? WHERE id=?")->execute([$p['amount'],$u['id']]);bk_tx($u['id'],(int)$p['amount'],'charge','شارژ درگاه '.$p['gateway'],'confirmed',['method'=>'gateway','gateway'=>$p['gateway'],'reference'=>$v['reference']]);$pdo->prepare("UPDATE bk_gateway_payments SET status='confirmed',reference=?,verified_at=NOW() WHERE id=?")->execute([$v['reference'],$p['id']]);$message='پرداخت تأیید و موجودی شارژ شد.';}else{$pdo->prepare("UPDATE bk_gateway_payments SET status='failed' WHERE id=?")->execute([$p['id']]);$message='تأیید پرداخت ناموفق بود: '.$v['error'];}}
            $pdo->commit();
        } catch (Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); $message='خطا در تأیید پرداخت: '.$e->getMessage(); }
    }
    $s = ['type'=>bk_setting('gateway_type','zarinpal'),'enabled'=>(int)bk_setting('gateway_enabled',0),'min'=>(int)bk_setting('gateway_min_charge',100000),'max'=>(int)bk_setting('gateway_max_charge',50000000),'bank'=>bk_setting('z2c_bank_name',''),'owner'=>bk_setting('z2c_account_name',''),'card'=>bk_setting('z2c_card_number','')];
    $gatewayOk = $s['enabled']===1 && in_array($s['type'], ['zarinpal','idipay','zibal'], true);
    $z2cOk = trim((string)$s['card']) !== '';
    header_html('شارژ کیف پول');
    ?><main class="wrap page"><div class="page-title"><h1>💳 شارژ کیف پول</h1><p><?=h($message??'افزایش موجودی کیف پول')?></p></div><?php if(!$gatewayOk && !$z2cOk):?><div class="card empty" style="padding:34px">شارژ کیف پول در حال حاضر <b>غیرفعال</b> است و روش پرداختی برای شما نمایش داده نمی‌شود.<br>برای فعال‌سازی از طریق <a class="check" href="<?=url('tickets')?>">تیکت پشتیبانی</a> با مدیریت در تماس باشید.</div><?php else:?><div class="grid grid-2"><?php if($gatewayOk):?><div class="card auth-card"><h3>درگاه پرداخت</h3><p class="muted">درگاه: <?=h($s['type'])?> · حداقل <?=money($s['min'])?> · حداکثر <?=money($s['max'])?></p><form method="post" action="<?=url('wallet-plus')?>"><input type="hidden" name="action" value="wallet_gateway_start"><input type="hidden" name="csrf" value="<?=function_exists('csrf')?csrf():''?>"><label class="field-label">مبلغ تومان</label><input class="field" type="number" name="amount" min="<?=$s['min']?>" max="<?=$s['max']?>" required><button class="btn btn-primary btn-full mt">پرداخت از <?=h($s['type'])?></button></form></div><?php endif;?><?php if($z2cOk):?><div class="card auth-card"><h3>کارت‌به‌کارت</h3><p class="muted">بانک: <?=h($s['bank'])?><br>به نام: <?=h($s['owner'])?><br><b dir="ltr"><?=h($s['card'])?></b></p><form method="post" enctype="multipart/form-data" id="z2cForm"><input type="hidden" name="action" value="wallet_card_to_card"><input type="hidden" name="csrf" value="<?=function_exists('csrf')?csrf():''?>"><label class="field-label">مبلغ تومان</label><input class="field" type="number" name="amount" required><label class="field-label">بانک واریزکننده</label><input class="field" name="bank_name" required><label class="field-label">شماره کارت واریزکننده</label><input class="field" name="card_number" dir="ltr" required><label class="field-label">تصویر فیش</label><input class="field" type="file" name="receipt" accept="image/*" required><div id="z2cMsg"></div><button class="btn btn-secondary btn-full mt">ثبت فیش</button></form></div><script>
(function(){var f=document.getElementById('z2cForm');if(!f)return;var msg=document.getElementById('z2cMsg');f.addEventListener('submit',function(e){e.preventDefault();var b=f.querySelector('button');var orig=b?b.textContent:'';if(b){b.disabled=true;b.textContent='⏳ در حال ارسال…';}msg.innerHTML='';fetch(window.location.href,{method:'POST',body:new FormData(f),headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}}).then(function(r){return r.json().catch(function(){return null;});}).then(function(j){if(j&&j.ok){msg.innerHTML='<div class="notice" style="margin-top:10px">✅ '+(j.message||'انجام شد')+'</div>';f.reset();}else{msg.innerHTML='<div class="notice error" style="margin-top:10px">⚠️ '+((j&&j.error)||'پاسخی از سرور دریافت نشد؛ دوباره تلاش کنید.')+'</div>';}if(b){b.disabled=false;b.textContent=orig;}}).catch(function(){msg.innerHTML='<div class="notice error" style="margin-top:10px">⚠️ خطای ارتباط با سرور؛ دوباره تلاش کنید.</div>';if(b){b.disabled=false;b.textContent=orig;}});});})();
</script><?php endif;?></div><?php endif;?></main><?php footer_html();exit;
}
