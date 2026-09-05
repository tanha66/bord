#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Bordkhan — هارنس تست سید قلق‌ها (بدون PHP/MySQL در محیط سندباکس)
منطق seed_tips.php را ۱:۱ روی SQLite شبیه‌سازی می‌کند:
  • اعتبارسنجی کامل ۲۰۹ رکورد دیتاست
  • شبیه‌سازی INSERT با ترتیب دقیق ستون‌های seed_tips.php
  • تست idempotency (اجرای دوباره = صفر insert جدید)
  • تست resume (پاک کردن بخشی از bk_seed_state و ادامه)
"""
import json, glob, sqlite3, sys, random, datetime

random.seed(42)
DB = '/tmp/bk_test.sqlite'
con = sqlite3.connect(DB)
con.execute('PRAGMA journal_mode=WAL')
cur = con.cursor()

# ---------- schema مطابق MySQL ----------
cur.executescript('''
CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, email TEXT, name TEXT, role TEXT);
CREATE TABLE IF NOT EXISTS categories (id INTEGER PRIMARY KEY, name TEXT, status TEXT);
CREATE TABLE IF NOT EXISTS tips (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  author_id INTEGER NOT NULL,
  category_id INTEGER NOT NULL,
  title TEXT NOT NULL,
  short_description TEXT NOT NULL,
  description TEXT NOT NULL,
  device_name TEXT NOT NULL,
  brand TEXT NOT NULL,
  model TEXT,
  board_number TEXT,
  fault_type TEXT NOT NULL,
  difficulty TEXT NOT NULL DEFAULT 'medium',
  solution_json TEXT NOT NULL,
  tools TEXT,
  images_json TEXT,
  video_url TEXT,
  attachments_json TEXT,
  access_type TEXT NOT NULL DEFAULT 'free',
  price INTEGER NOT NULL DEFAULT 0,
  visibility TEXT NOT NULL DEFAULT 'public',
  status TEXT NOT NULL DEFAULT 'draft',
  tags TEXT,
  version INTEGER NOT NULL DEFAULT 1,
  versions_json TEXT,
  featured INTEGER NOT NULL DEFAULT 0,
  views INTEGER NOT NULL DEFAULT 0,
  likes_count INTEGER NOT NULL DEFAULT 0,
  purchases_count INTEGER NOT NULL DEFAULT 0,
  rating_sum INTEGER NOT NULL DEFAULT 0,
  rating_count INTEGER NOT NULL DEFAULT 0,
  duplicate_of INTEGER,
  rejection_reason TEXT,
  source_url TEXT,
  source_name TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  published_at TEXT
);
CREATE TABLE IF NOT EXISTS bk_seed_state (
  seed_key TEXT PRIMARY KEY,
  item_no INTEGER NOT NULL,
  tip_id INTEGER NOT NULL,
  source TEXT
);
''')
cur.execute("INSERT OR IGNORE INTO users (id,email,name,role) VALUES (1,'admin@bordkhan.ir','مدیر بردخان','superadmin')")
cur.execute("INSERT OR IGNORE INTO categories (id,name,status) VALUES (1,'موبایل و تبلت','active')")
con.commit()

# ---------- همان منطق seed_tips.php ----------
TOTAL_COL_PLACEHOLDERS = 23  # تعداد placeholderهای INSERT واقعی

def seed_run(dry=False):
    files = sorted(glob.glob('php-extended/seed-data/part*.json'),
                   key=lambda x: int(''.join(filter(str.isdigit, x.split('/')[-1]))))
    tips = []
    for f in files:
        data = json.load(open(f, encoding='utf-8'))
        for i, t in enumerate(data):
            t['_src'] = f.split('/')[-1] + '#' + str(i+1)
            tips.append(t)
    total = len(tips)
    done = {r[0] for r in cur.execute('SELECT item_no FROM bk_seed_state')}
    ok = skip = fail = 0
    for idx, t in enumerate(tips):
        no = idx + 1
        if no in done:
            skip += 1
            continue
        # اعتبارسنجی مثل PHP
        if len(t['title']) < 5 or len(t['short']) < 5 or len(t['desc']) < 10 or not t['steps']:
            fail += 1
            continue
        steps = [{'title': s[0], 'body': s[1]} for s in t['steps']]
        solution = json.dumps(steps, ensure_ascii=False)
        images = t.get('imgs') or []
        images_json = json.dumps(images, ensure_ascii=False)
        tools = t.get('tools') or ''
        video = ('https://www.aparat.com/v/' + t['vid']) if t.get('vid') else ''
        diff = t['diff'] if t.get('diff') in ('easy','medium','hard') else 'medium'
        access, price = 'free', 0
        if no % 4 == 0: access = 'like'
        elif no % 9 == 0: access, price = 'paid', 25000 + (no % 5) * 10000
        pub = (datetime.datetime.now() - datetime.timedelta(hours=(total-no)*3 + random.randint(0,2))).strftime('%Y-%m-%d %H:%M:%S')
        featured = 1 if no % 3 == 0 else 0
        # INSERT با همان تعداد placeholder (23)
        vals = [1, 1, t['title'], t['short'], t['desc'], t['device'], t['brand'],
                t.get('model') or '', '', t['fault'], diff, solution, tools,
                images_json, video, '[]', access, price, 'public', 'published',
                t['tags'], featured, pub]
        assert len(vals) == TOTAL_COL_PLACEHOLDERS, f"placeholder mismatch: {len(vals)}"
        if not dry:
            cur.execute('''INSERT INTO tips(author_id,category_id,title,short_description,description,
              device_name,brand,model,board_number,fault_type,difficulty,solution_json,tools,images_json,
              video_url,attachments_json,access_type,price,visibility,status,tags,version,versions_json,
              featured,views,likes_count,purchases_count,rating_sum,rating_count,duplicate_of,rejection_reason,
              source_url,source_name,published_at)
              VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NULL,?,0,0,0,0,0,NULL,NULL,NULL,NULL,?)''', vals)
            tip_id = cur.lastrowid
            cur.execute('INSERT OR IGNORE INTO bk_seed_state (seed_key,item_no,tip_id,source) VALUES (?,?,?,?)',
                        (f'seed-mobile-{no}', no, tip_id, t['_src']))
            ok += 1
    con.commit()
    return ok, skip, fail, total

print('=== اجرای اول (نصب کامل) ===')
ok, skip, fail, total = seed_run()
print(f'ثبت: {ok} | ردشده: {skip} | خطا: {fail} | مجموع دیتاست: {total}')
assert fail == 0, 'خطای اعتبارسنجی!'
assert ok == total, 'همه رکوردها ثبت نشدند!'

print('\n=== اجرای دوم (idempotency) ===')
ok2, skip2, fail2, _ = seed_run()
print(f'ثبت جدید: {ok2} | ردشده: {skip2}')
assert ok2 == 0 and skip2 == total, 'idempotency خراب است!'

print('\n=== تست resume: حذف ۳۰ رکورد آخر و ادامه ===')
last30 = cur.execute('SELECT tip_id FROM bk_seed_state WHERE item_no > ?', (total-30,)).fetchall()
ids = ','.join(str(r[0]) for r in last30)
cur.execute(f'DELETE FROM tips WHERE id IN ({ids})')
cur.execute(f'DELETE FROM bk_seed_state WHERE item_no > ?', (total-30,))
con.commit()
ok3, skip3, fail3, _ = seed_run()
print(f'ادامه: ثبت {ok3} جدید، {skip3} موجود')
assert ok3 == 30 and skip3 == total - 30, 'resume خراب است!'

print('\n=== آمار نهایی دیتابیس ===')
cnt = cur.execute("SELECT COUNT(*) FROM tips WHERE status='published'").fetchone()[0]
by_access = dict(cur.execute("SELECT access_type, COUNT(*) FROM tips GROUP BY access_type").fetchall())
by_diff = dict(cur.execute("SELECT difficulty, COUNT(*) FROM tips GROUP BY difficulty").fetchall())
with_video = cur.execute("SELECT COUNT(*) FROM tips WHERE video_url != ''").fetchone()[0]
with_images = cur.execute("SELECT COUNT(*) FROM tips WHERE images_json != '[]'").fetchone()[0]
featured = cur.execute("SELECT COUNT(*) FROM tips WHERE featured=1").fetchone()[0]
print(f'کل قلق‌های منتشرشده: {cnt}')
print(f'بر اساس دسترسی: {by_access}')
print(f'بر اساس سختی: {by_diff}')
print(f'دارای ویدیو: {with_video} | دارای تصویر: {with_images} | منتخب: {featured}')
assert cnt == total

# نمونه خروجی
print('\n=== نمونه رکورد ===')
row = cur.execute("SELECT title, difficulty, access_type, images_json, video_url FROM tips WHERE video_url != '' LIMIT 1").fetchone()
print('عنوان:', row[0])
print('سختی:', row[1], '| دسترسی:', row[2])
print('تصاویر:', row[3][:80], '...')
print('ویدیو:', row[4])
print('\n✅✅✅ همهٔ تست‌ها PASS — دیتاست و منطق سید معتبر است')
