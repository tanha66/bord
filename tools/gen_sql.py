#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Bordkhan — مولد seed_mobile_tips.sql از دیتاست part*.json
قواعد سید (یکسان با seed_tips.php و seed-all.php):
  • access: no%4==0 → like، no%9==0 → paid (اولویت paid)
  • price:  paid → 25000+(no%5)*10000، بقیه 0
  • featured: no%3==0
  • published_at: اکنون − ۳ساعت×(total−no) + رندم ۰..۲ ساعت (نزولی از جدید به قدیم)
  • images_json: ["/uploads/tip-NNN.jpg"] — عکس یکتا
  • video_url: https://www.aparat.com/v/<code> در صورت وجود vid
اجرای دوباره امن است (WHERE NOT EXISTS بر اساس title).
"""
import json, glob, os, random, datetime, sys

BASE = os.path.join(os.path.dirname(__file__), '..', 'php-extended', 'seed-data')
OUT = os.path.join(BASE, 'seed_mobile_tips.sql')

def load_tips():
    tips = []
    for f in sorted(glob.glob(os.path.join(BASE, 'part*.json')),
                    key=lambda p: int(''.join(ch for ch in os.path.basename(p) if ch.isdigit()))):
        tips.extend(json.load(open(f, encoding='utf-8')))
    return tips

def sq(s):
    return "'" + str(s).replace("'", "''") + "'"

def main():
    tips = load_tips()
    total = len(tips)
    random.seed(309)
    now = datetime.datetime.now()
    lines = [
        "-- ============================================================",
        f"-- Bordkhan — سید {total} قلق تعمیر گوشی (فارسی) — نسخهٔ SQL",
        f"-- ⚠️ هر قلق عکس یکتای خودش را دارد: tip-001.jpg … tip-{total:03d}.jpg (سئو)",
        "-- تصاویر تخت در ریشهٔ uploads/ (serve.php فقط ریشه را سرو می‌کند)",
        "-- اجرای دوباره امن است (عناوین تکراری درج نمی‌شوند)",
        "-- ============================================================",
        "",
        "SET NAMES utf8mb4;",
        "SET @bk_author := (SELECT id FROM users WHERE role IN ('admin','superadmin') ORDER BY id ASC LIMIT 1);",
        "SET @bk_cat    := (SELECT id FROM categories WHERE name LIKE '%موبایل و تبلت%' AND status='active' ORDER BY parent_id IS NOT NULL ASC, id ASC LIMIT 1);",
        "",
    ]
    cols = ("INSERT INTO tips (author_id,category_id,title,short_description,description,device_name,"
            "brand,model,board_number,fault_type,difficulty,solution_json,tools,images_json,video_url,"
            "attachments_json,access_type,price,visibility,status,tags,version,versions_json,featured,views,"
            "likes_count,purchases_count,rating_sum,rating_count,duplicate_of,rejection_reason,source_url,"
            "source_name,published_at)")
    for no, t in enumerate(tips, 1):
        if no % 4 == 0:
            access, price = 'like', 0
        elif no % 9 == 0:
            access, price = 'paid', 25000 + (no % 5) * 10000
        else:
            access, price = 'free', 0
        featured = 1 if no % 3 == 0 else 0
        pub = now - datetime.timedelta(hours=3 * (total - no)) + datetime.timedelta(hours=random.randint(0, 2))
        solution = json.dumps([{"title": s[0], "body": s[1]} for s in t['steps']], ensure_ascii=False)
        images = json.dumps([f"/uploads/tip-{no:03d}.jpg"], ensure_ascii=False)
        video = f"https://www.aparat.com/v/{t['vid']}" if t.get('vid') else ''
        row = ("SELECT @bk_author,@bk_cat," + ",".join([
            sq(t['title']), sq(t['short']), sq(t['desc']), sq(t['device']), sq(t['brand']),
            sq(t.get('model', '')), sq(''), sq(t['fault']), sq(t['diff']), sq(solution),
            sq(t.get('tools', '')), sq(images), sq(video), sq('[]'), sq(access), str(price),
            sq('public'), sq('published'), sq(t['tags']), "1,NULL", str(featured),
            "0,0,0,0,0,0,NULL,NULL,NULL,NULL", sq(pub.strftime('%Y-%m-%d %H:%M:%S')),
        ]) + " FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tips WHERE title = " + sq(t['title']) + ");")
        lines.append(cols)
        lines.append(row)
        lines.append("")
    open(OUT, 'w', encoding='utf-8').write("\n".join(lines))
    nv = sum(1 for t in tips if t.get('vid'))
    print(f"OK: {total} tips → {OUT} (videos: {nv})")

if __name__ == '__main__':
    sys.exit(main())
