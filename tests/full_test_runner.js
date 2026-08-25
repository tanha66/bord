/**
 * Full Project Test Runner - Node.js version (no PHP needed)
 * تست کامل پروژه بردخان بدون نیاز به PHP
 */

const fs = require('fs');
const path = require('path');
const ROOT = path.join(__dirname, '..');

let pass = 0, fail = 0;
const results = [];

function test(name, fn) {
  try {
    const r = fn();
    if (r === true) {
      results.push({name, ok: true});
      pass++;
    } else {
      results.push({name, ok: false, err: r});
      fail++;
    }
  } catch (e) {
    results.push({name, ok: false, err: e.message});
    fail++;
  }
}

function read(file) {
  return fs.readFileSync(path.join(ROOT, file), 'utf-8');
}

// Required files
const required = [
  'index.php','config.php','serve.php','install.php',
  'assets/style.css','manifest.webmanifest','sw.js','.htaccess',
  'pages/home.php','pages/admin.php','pages/boards.php','pages/about.php','pages/contact.php','pages/terms.php','pages/privacy.php',
  'php-extended/bk_extended.php','php-extended/bk_actionbar.php','php-extended/bk_admin_extra.php','php-extended/tickets.php','php-extended/admin_finance.php','php-extended/schema_build.php',
  'pages/admin_dashboard_v5.php','pages/admin_users_v5.php',
  'sql/schema.sql'
];
required.forEach(f => {
  test(`فایل ${f}`, () => fs.existsSync(path.join(ROOT, f)) ? true : 'ناموجود');
});

const index = read('index.php');

// Routes
const routes = ['home','tips','tip','boards','board','my-boards','seller-apply','upload','wallet','my-tips','repairs','repair','profile','leaderboard','premium','referral','about','contact','terms','privacy','admin','bookmarks','favorites','notifications','settings','reels','reels_demo','tour','login','register','verify','forgot','logout','serve','verify-email','payment-status','cron-cleanup','admin-cleanup','admin-security','ajax-comments','ajax-notifications','ajax-categories','diag-version'];
routes.forEach(r => {
  test(`Route ${r}`, () => index.includes(`'${r}'`) || index.includes(`"${r}"`) ? true : 'یافت نشد');
});

// Actions
const actions = ['login','register','verify','logout','forgot_request','forgot_reset','my_tip_delete','unlock','comment','rate','follow','favorite','bookmark','search_live','admin_tip','admin_user','admin_withdraw','subscribe','email_code_send','email_code_verify','email_code_resend','logout_all','session_kill','profile_update','suggest_category','admin_category','admin_settings','admin_report','contact_status','seller_apply','board_create','board_buy','board_ship','board_confirm','board_cancel','admin_board','admin_seller','upload_tip','withdraw','repair_create','repair_answer','repair_best','report','comment_vote'];
actions.forEach(a => {
  test(`Action ${a}`, () => index.includes(`'${a}'`) ? true : `ناموجود`);
});

// Bugs
test('رفع باگ BKC {{}}', () => !index.includes('var BKC={{csrf:') ? true : 'هنوز وجود دارد');
test('رفع باگ script', () => {
  const open = (index.match(/<script>/g) || []).length;
  const close = (index.match(/<\/script>/g) || []).length;
  return open <= close+5 ? true : `open ${open} close ${close}`;
});
test('رفع باگ \\t در board_ship', () => !index.includes('?\\tAND') && !index.includes('?\\t') ? true : 'هنوز وجود دارد');
test('ترمیم upload_tip', () => index.includes("if($action==='upload_tip')") ? true : 'ناموجود');
test('ترمیم withdraw', () => index.includes("if($action==='withdraw')") ? true : 'ناموجود');
test('ترمیم repair_create', () => index.includes("if($action==='repair_create')") ? true : 'ناموجود');

// Security
test('redirect_to open redirect check', () => index.includes('open redirect') ? true : 'چک نیست');
test('check_csrf', () => index.includes('check_csrf') ? true : 'نیست');
test('file_mime', () => index.includes('file_mime') ? true : 'نیست');
const serve = read('serve.php');
test('serve.php realpath', () => serve.includes('realpath') ? true : 'نیست');
test('serve.php fa fallback', () => serve.includes('function fa') ? true : 'نیست');
const htaccess = read('.htaccess');
test('htaccess block php in uploads', () => htaccess.includes('uploads') && htaccess.includes('php') ? true : 'نیست');

// PWA
test('manifest valid', () => {
  try { const m = JSON.parse(read('manifest.webmanifest')); return m.name ? true : 'name ندارد'; } catch(e){ return 'JSON نامعتبر'; }
});
test('sw.js CACHE', () => read('sw.js').includes('CACHE') ? true : 'نیست');
test('sw.js فقط assets کش می‌شود (HTML نه)', () => (read('sw.js').includes("pathname.startsWith('/assets/')") && (read('sw.js').split('c.put').length - 1) === 1) ? true : 'HTML هنوز کش می‌شود');

// JS regression — header inline script must parse AND register the lightbox even for guests
// (the zoom/lightbox + video tools used to live inside the notifications IIFE which bailed
//  out with `if(!bell)return;` when #notifBell was absent, i.e. for logged-out visitors)
test('سینتکس JS هدر (زوم عکس/ویدیو) معتبر', () => {
  const m = index.indexOf('/* فیلتر زنده');
  const e = index.indexOf('</script></head>');
  if (m < 0 || e < 0 || e <= m) return 'بلوک اسکریپت پیدا نشد';
  const code = index.slice(m, e);
  try { new Function(code); return true; } catch (err) { return 'خطای سینتکس JS: ' + err.message; }
});
test('نبود توالی خراب `})();()}` در index.php', () => !index.includes('})();()}') ? true : 'باگ سینتکس JS حاضر است');
test('لایت‌باکس برای مهمان (بدون notifBell) هم فعال است', () => {
  const m = index.indexOf('/* فیلتر زنده');
  const e = index.indexOf('</script></head>');
  if (m < 0 || e < 0 || e <= m) return 'بلوک اسکریپت پیدا نشد';
  const code = index.slice(m, e);
  function el(){ return { style:{}, children:[], classList:{add(){},remove(){},contains(){return false}}, setAttribute(){}, getAttribute(){return null}, addEventListener(){}, removeEventListener(){}, querySelector(){return null}, querySelectorAll(){return []}, appendChild(){}, closest(){return null}, remove(){}, innerHTML:'', textContent:'', value:'', disabled:false, requestFullscreen(){}, setPointerCapture(){}, dataset:{} }; }
  const document = { createElement:()=>el(), querySelector:()=>null, querySelectorAll:()=>[], getElementById:()=>null, addEventListener:()=>{}, removeEventListener:()=>{}, body:{appendChild(){},classList:{add(){},remove(){}},children:[]}, fullscreenElement:null, exitFullscreen(){} };
  const window = { addEventListener(){} };
  try {
    new Function('window','document','navigator','localStorage', code)(window, document, { userAgent:'node' }, { getItem:()=>null, setItem(){}, removeItem(){} });
    return typeof window.bkLightboxOpen === 'function' ? true : 'bkLightboxOpen تعریف نشد (برای مهمان)';
  } catch (err) { return 'خطای اجرای JS: ' + err.message; }
});

// Registration SQL regression (HY093): placeholders must match params
test('ثبت‌نام: تعداد جای متغیرهای INSERT users درست است', () => index.includes('VALUES(?,?,?,?,?,?,1,1)') ? true : 'عدم تطابق placeholder/param (HY093)');
// Bell must bind after DOM is ready (script runs in <head>)
test('زنگوله بعد از آماده‌شدن DOM بایند می‌شود', () => index.includes('function initBell(){') && index.includes("document.readyState==='loading'") ? true : 'زنگوله در head بایند نمی‌شود');

// CSS
const css = read('assets/style.css');
test('CSS vars', () => css.includes('--bg') && css.includes('--accent') ? true : 'نیست');
test('CSS media queries', () => css.includes('@media') ? true : 'نیست');

// Schema
const schema = read('sql/schema.sql');
test('schema users', () => schema.includes('users') ? true : 'نیست');
test('schema tips', () => schema.includes('tips') ? true : 'نیست');
test('schema boards', () => schema.includes('boards') ? true : 'نیست');

console.log('\n=== تست کامل پروژه بردخان (Node.js) ===\n');
results.forEach(r => {
  console.log(`${r.ok ? '✅' : '❌'} ${r.name}: ${r.ok ? 'PASS' : 'FAIL'}${r.err ? ' - '+r.err : ''}`);
});
console.log(`\n--- خلاصه ---\nکل: ${results.length}\nموفق: ${pass}\nناموفق: ${fail}\n`);
if (fail===0) console.log('\n🎉 کل پروژه بدون باگ بحرانی!\n');
else console.log(`\n⚠️ ${fail} مورد نیاز به بررسی\n`);
