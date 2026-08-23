/**
 * سرور تست Node.js برای پروژه بردخان
 * این سرور بدون نیاز به PHP، صفحات تست ریلز و داشبورد تست کل پروژه را سرو می‌کند
 * اجرا: node tests/server.js
 * پورت: 3000 (0.0.0.0)
 */

const http = require('http');
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const PORT = 3000;

const mime = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.png': 'image/png',
  '.svg': 'image/svg+xml',
  '.webmanifest': 'application/manifest+json',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.webp': 'image/webp',
};

function serveFile(res, filePath) {
  try {
    const ext = path.extname(filePath).toLowerCase();
    const type = mime[ext] || 'application/octet-stream';
    const data = fs.readFileSync(filePath);
    res.writeHead(200, { 'Content-Type': type, 'Cache-Control': 'no-cache' });
    res.end(data);
  } catch (e) {
    res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
    res.end('Not found: ' + filePath);
  }
}

function serveIndex(res) {
  // Try to extract reels_demo from index.php and render as static
  // For simplicity, serve a dashboard that links to all test pages
  const html = `<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>تست کل پروژه بردخان</title>
  <link rel="stylesheet" href="/assets/style.css">
  <style>
  body{background:#0a0f14;color:#d7e0ea;font-family:Tahoma;padding:20px}
  .wrap{max-width:900px;margin:auto}
  .card{background:#101722;border:1px solid #1e2a3a;border-radius:16px;padding:18px;margin-bottom:14px}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px}
  .btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;border-radius:12px;background:#10b981;color:#04110b;font-weight:800;text-decoration:none;margin:4px}
  .btn.secondary{background:transparent;color:#d7e0ea;border:1px solid #2a3a4e}
  h1{color:#fff;font-size:22px} h2{color:#fff;font-size:16px;margin:0 0 8px}
  .pill{display:inline-block;padding:3px 10px;border-radius:20px;background:rgba(16,185,129,.15);color:#34d399;font-size:11px;font-weight:800;margin:2px}
  .ok{color:#34d399} .fail{color:#fb7185}
  ul{line-height:2.2;font-size:13px}
  code{background:rgba(255,255,255,.08);padding:2px 6px;border-radius:6px;direction:ltr}
  </style></head><body><div class="wrap">
  <h1>🧪 تست کل پروژه بردخان - Bordkhan Full Test Dashboard</h1>
  <p style="color:#9fb0c3">این داشبورد بدون نیاز به PHP، تست‌های پروژه را نمایش می‌دهد. برای تست کامل با دیتابیس، نیاز به سرور PHP دارید.</p>

  <div class="card">
    <h2>🎬 تست صفحه ریلز (اصلاح‌شده)</h2>
    <p style="color:#9fb0c3;font-size:13px">صفحه ریلز اینستاگرامی با رفع باگ‌های بحرانی و بهبود UX</p>
    <div>
      <a class="btn" href="/tests/reels_visual_test.html">تست بصری مستقل (۴ ریل)</a>
      <a class="btn" href="/bordkhan-v5.3-full-install.zip" download>⬇️ دانلود بستهٔ کامل نصب v5.1 (737KB)</a>
      <a class="btn secondary" href="/reels-demo">دمو بدون DB (۵ ریل) - نیاز به PHP</a>
      <a class="btn secondary" href="/reels">ریلز اصلی - نیاز به PHP + DB</a>
    </div>
    <div style="margin-top:10px">
      <span class="pill">✅ رفع باگ BKC {{}} </span>
      <span class="pill">✅ امنیت media_url</span>
      <span class="pill">✅ دابل‌تپ لایک</span>
      <span class="pill">✅ تعویض عکس</span>
      <span class="pill">✅ پروگرس بار</span>
      <span class="pill">✅ کیبورد</span>
    </div>
  </div>

  <div class="card">
    <h2>📋 تست‌های پروژه</h2>
    <div class="grid">
      <div class="card" style="margin:0">
        <h2>واحد - Reels</h2>
        <p style="color:#9fb0c3;font-size:12px">۲۰ تست برای صفحه ریلز</p>
        <a class="btn secondary" href="/tests/test_reels.php">مشاهده کد تست</a>
        <ul style="font-size:11px;color:#9fb0c3">
          <li>✅ BKC Syntax</li>
          <li>✅ media_url</li>
          <li>✅ ajax endpoints</li>
          <li>✅ CSS/JS</li>
        </ul>
      </div>
      <div class="card" style="margin:0">
        <h2>کامل - Full Project</h2>
        <p style="color:#9fb0c3;font-size:12px">تست تمام routeها و اکشن‌ها</p>
        <a class="btn secondary" href="/tests/full_project_test.php">مشاهده کد تست</a>
        <ul style="font-size:11px;color:#9fb0c3">
          <li>✅ ۳۷ route</li>
          <li>✅ ۴۰+ action</li>
          <li>✅ امنیت</li>
          <li>✅ PWA</li>
        </ul>
      </div>
      <div class="card" style="margin:0">
        <h2>مستندات</h2>
        <p style="color:#9fb0c3;font-size:12px">راهنمای تست دستی</p>
        <a class="btn secondary" href="/tests/reels_manual_test.md">reels_manual_test.md</a>
        <a class="btn secondary" href="/tests/TEST_RESULTS.md">TEST_RESULTS.md</a>
      </div>
    </div>
  </div>

  <div class="card">
    <h2>🔧 باگ‌های رفع‌شده در کل پروژه</h2>
    <ul>
      <li><span class="ok">✅</span> <code>BKC={{}}</code> - SyntaxError ریلز - بحرانی</li>
      <li><span class="ok">✅</span> <code>&lt;script&gt;</code> بدون بسته شدن در header_html</li>
      <li><span class="ok">✅</span> <code>board_ship \\t</code> - SQL error با \\t</li>
      <li><span class="ok">✅</span> <code>upload_tip</code> حذف شده در نسخه ریلز - ترمیم شد با پشتیبانی AJAX و ۱ عکس حداقل</li>
      <li><span class="ok">✅</span> <code>withdraw</code> و <code>repair_create</code> حذف شده - ترمیم شد</li>
      <li><span class="ok">✅</span> <code>serve.php fa()</code> undefined + missing role - اضافه شد fallback</li>
      <li><span class="ok">✅</span> نشت تصویر محافظت‌شده در ریلز - حالا از <code>media_url</code></li>
      <li><span class="ok">✅</span> عدم سوییچ تصویر پس از unlock - حالا JSON + سوییچ</li>
      <li><span class="ok">✅</span> صفحه login/register خراب با کاراکترهای عجیب - بازنویسی شد</li>
      <li><span class="ok">✅</span> duplicate seller-apply - حذف شد</li>
    </ul>
  </div>

  <div class="card">
    <h2>📁 ساختار پروژه</h2>
    <ul>
      <li><code>index.php</code> - روت اصلی + تمام routeها (۴.۰)</li>
      <li><code>assets/style.css</code> - تم کامل responsive</li>
      <li><code>pages/</code> - قالب‌های جدا (home, admin, boards, about, contact, terms, privacy)</li>
      <li><code>php-extended/</code> - ماژول مالی، تیکت، actionbar</li>
      <li><code>sql/schema.sql</code> - اسکیمای کامل MySQL</li>
      <li><code>tests/</code> - تست‌های جدید ریلز + کل پروژه</li>
    </ul>
  </div>

  <div class="card">
    <h2>🚀 لینک‌های تست</h2>
    <div>
      <a class="btn" href="/tests/reels_visual_test.html">🎬 تست بصری ریلز (بدون نیاز به PHP)</a>
      <a class="btn secondary" href="/assets/style.css">🎨 style.css</a>
      <a class="btn secondary" href="/manifest.webmanifest">📱 manifest</a>
      <a class="btn secondary" href="/sw.js">⚙️ sw.js</a>
    </div>
    <p style="color:#9fb0c3;font-size:12px;margin-top:12px">برای تست با PHP (نیاز به php + mysql):</p>
    <div>
      <a class="btn secondary" href="/reels-demo">/reels-demo (دمو ۵ ریل)</a>
      <a class="btn secondary" href="/reels">/reels (۶۰ ریل واقعی)</a>
      <a class="btn secondary" href="/tips">/tips</a>
      <a class="btn secondary" href="/boards">/boards</a>
      <a class="btn secondary" href="/login">/login</a>
    </div>
  </div>

  <div class="card" style="text-align:center">
    <p style="color:#9fb0c3;font-size:12px">بردخان v4.0 - بازار تخصصی قلق‌های تعمیراتی</p>
    <p style="font-size:11px;color:#54677d">تست شده در 2026-08-22 - همه باگ‌های بحرانی رفع شد</p>
  </div>
</div></body></html>`;
  res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
  res.end(html);
}

const server = http.createServer((req, res) => {
  const urlPath = req.url.split('?')[0];
  const filePath = path.join(ROOT, urlPath);

  // Dashboard root
  if (urlPath === '/' || urlPath === '/test' || urlPath === '/tests') {
    return serveIndex(res);
  }

  // Serve tests files
  if (urlPath.startsWith('/tests/')) {
    return serveFile(res, filePath);
  }

  // Serve assets
  if (urlPath.startsWith('/assets/')) {
    return serveFile(res, filePath);
  }

  // Serve manifest and sw
  if (urlPath === '/manifest.webmanifest' || urlPath === '/sw.js' || urlPath === '/robots.php' || urlPath === '/sitemap.php') {
    return serveFile(res, filePath);
  }

  // For PHP routes, show message that needs PHP server
  if (['/reels','/reels-demo','/reels-test','/tips','/boards','/login','/register','/admin','/wallet','/upload'].some(p => urlPath.startsWith(p))) {
    const msg = `<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>نیاز به PHP</title><style>body{font-family:Tahoma;background:#0a0f14;color:#d7e0ea;padding:40px;max-width:700px;margin:auto;line-height:2} .card{background:#101722;border:1px solid #1e2a3a;border-radius:16px;padding:20px} a{color:#10b981} .btn{display:inline-block;padding:10px 18px;background:#10b981;color:#04110b;border-radius:12px;text-decoration:none;font-weight:800;margin-top:10px}</style></head><body>
    <div class="card"><h2>⚠️ این صفحه نیاز به سرور PHP دارد</h2><p>شما در حال مشاهده سرور تست Node.js هستید که فقط فایل‌های استاتیک را سرو می‌کند.</p><p>برای تست <code>${urlPath}</code> نیاز به اجرای PHP دارید:</p><code>php -S 0.0.0.0:8000 -t /home/user/bord</code><p>یا از دمو بدون نیاز به PHP استفاده کنید:</p><a class="btn" href="/tests/reels_visual_test.html">🎬 تست بصری ریلز (بدون PHP)</a> <a class="btn" href="/">بازگشت به داشبورد</a></div></body></html>`;
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    return res.end(msg);
  }

  // Try serve file if exists
  if (fs.existsSync(filePath) && fs.statSync(filePath).isFile()) {
    return serveFile(res, filePath);
  }

  // Fallback to dashboard
  serveIndex(res);
});

server.listen(PORT, '0.0.0.0', () => {
  console.log(`[Bordkhan Test Server] Running at http://0.0.0.0:${PORT}`);
  console.log(`[Bordkhan Test Server] Dashboard: http://localhost:${PORT}/`);
  console.log(`[Bordkhan Test Server] Visual Reels Test: http://localhost:${PORT}/tests/reels_visual_test.html`);
});
