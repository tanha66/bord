#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Bordkhan — تولید ۲۰۹ عکس یکتا (نسخهٔ ۲ — تنوع بصری کامل)
هر کارت از ۵ لایهٔ متغیر ساخته می‌شود تا هیچ دو کارتی شبیه هم نباشند:
  ۱) عکس پایهٔ موضوعی + برش/زوم/آینه‌سازی متفاوت (seed = شمارهٔ قلق)
  ۲) رنگ‌بندی دوگانه (duotone) اختصاصی برند
  ۳) الگوی گرافیکی (نوار مورب/نقطه/مدار) با seed متفاوت
  ۴) آیکون بزرگ نوع خرابی (باتری/نمایشگر/دوربین/شارژ/آب/صدا/برد…)
  ۵) عنوان فارسی همان قلق + چیپ برند + شماره
اجرا:  python3 tools/make_unique_images.py
"""
import json, glob, os, sys, random, math
from PIL import Image, ImageDraw, ImageFilter, ImageFont, ImageEnhance
import arabic_reshaper
from bidi.algorithm import get_display

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA = os.path.join(ROOT, 'php-extended', 'seed-data')
MEDIA = os.path.join(ROOT, 'uploads-seed', 'tips')
FONTS = os.path.join(ROOT, 'tools', 'fonts')

W, H = 1280, 720

F_BOLD = os.path.join(FONTS, 'Vazirmatn-Bold.ttf')
F_MED = os.path.join(FONTS, 'Vazirmatn-Medium.ttf')

def shape(s): return get_display(arabic_reshaper.reshape(s))
FA = str.maketrans('0123456789', '۰۱۲۳۴۵۶۷۸۹')
def fa(n): return str(n).translate(FA)

CAT_COLORS = {
    'آیفون': ((14, 165, 233), (12, 74, 110)),
    'سامسونگ': ((139, 92, 246), (76, 29, 149)),
    'شیائومی': ((249, 115, 22), (124, 45, 18)),
    'هواوی': ((239, 68, 68), (127, 29, 29)),
    'آنر': ((20, 184, 166), (17, 94, 89)),
    'سایر': ((245, 158, 11), (120, 53, 15)),
}

TOPIC_BASES = {
    'battery': ['tip-swollen-battery.jpg', 'tip-disconnect-battery-flex.jpg', 'tip-open-iphone-repair.jpg'],
    'display': ['tip-cracked-display.jpg', 'tip-screws-organization-mat.jpg', 'tip-open-iphone-repair.jpg'],
    'camera': ['tip-cracked-camera-lens.jpg', 'tip-board-macro-afem.jpg', 'tip-cracked-display.jpg'],
    'charge': ['tip-wet-usb-port.jpg', 'tip-charger-and-cable.jpg', 'tip-phone-circuit-repair.jpg'],
    'water': ['tip-phone-in-rice.jpg', 'tip-wet-usb-port.jpg'],
    'audio': ['tip-speaker-mesh-macro.jpg', 'tip-open-iphone-repair.jpg'],
    'board': ['tip-board-under-microscope.jpg', 'tip-board-macro-afem.jpg', 'tip-phone-circuit-repair.jpg'],
    'network': ['tip-board-under-microscope.jpg', 'tip-phone-circuit-repair.jpg', 'tip-esd-workspace.jpg'],
    'software': ['tip-esd-workspace.jpg', 'tip-screws-organization-mat.jpg', 'tip-open-iphone-repair.jpg'],
    'general': ['tip-screws-organization-mat.jpg', 'tip-esd-workspace.jpg', 'tip-heat-gun-backcover.jpg', 'tip-open-iphone-repair.jpg'],
}
KEYWORDS = [
    ('water', ['آب', 'رطوبت', 'برنج', 'ساحل', 'خیس', 'شور']),
    ('battery', ['باتری', 'شارژدهی', 'مصرف باتری', 'متورم', 'خاموشی', 'پرش درصد']),
    ('display', ['نمایشگر', 'تاچ', 'صفحه', 'لمس', 'گوست', 'خط سبز', 'لکه', 'چشمک', 'ال‌سی‌دی', 'LCD', 'AMOLED', 'سوختگی صفحه']),
    ('camera', ['دوربین', 'لنز', 'فلش', 'فوکوس', 'Face ID', 'چهره']),
    ('audio', ['اسپیکر', 'میکروفون', 'صدا', 'ویبره', 'بلندگو', 'هدفون', 'جک', 'Audio']),
    ('network', ['وای‌فای', 'بلوتوث', 'سیم‌کارت', 'NFC', 'آنتن', 'GPS', 'شبکه']),
    ('software', ['نرم‌افزار', 'فلش', 'رام', 'ریست', 'Odin', 'MiFlash', 'DFU', 'رمز', 'FRP', 'کش', 'Safe Mode', 'کندی', 'هنگ', 'بکاپ', 'حافظه', 'microSD']),
    ('charge', ['شارژ', 'پورت', 'Type-C', 'سوکت', 'کابل', 'شارژر', 'پاوربانک', 'SuperCharge', 'MagSafe', 'PD']),
    ('board', ['برد', 'IC', '4013', '4014', 'NAND', 'میکروسکوپ', 'ریستارت', 'لوگو', 'اکسیدیشن', 'اولتراسونیک', 'اتصال کوتاه', 'سوختگی']),
]
TOPIC_ICON = {
    'battery': '🔋', 'display': '📱', 'camera': '📷', 'charge': '🔌',
    'water': '💧', 'audio': '🔊', 'board': '🔬', 'network': '📶',
    'software': '⚙️', 'general': '🛠',
}

def topic_for(t, no):
    text = ' '.join([t.get('title', ''), t.get('fault', ''), t.get('tags', ''), t.get('short', '')])
    for topic, kws in KEYWORDS:
        if any(k in text for k in kws):
            bases = TOPIC_BASES[topic]
            return bases[no % len(bases)], topic
    bases = TOPIC_BASES['general']
    return bases[no % len(bases)], 'general'

def load_font(p, s): return ImageFont.truetype(p, s)

def wrap_rtl(draw, text, font, max_w):
    words = text.split(); lines, cur = [], ''
    for w in words:
        trial = (cur + ' ' + w).strip()
        if draw.textlength(shape(trial), font=font) <= max_w or not cur: cur = trial
        else: lines.append(cur); cur = w
    if cur: lines.append(cur)
    return lines

def varied_base(base_path, seed, w, h):
    """برش/زوم/آینهٔ متفاوت برای هر seed تا عکس پایه تکراری به نظر نرسد"""
    img = Image.open(base_path).convert('RGB')
    rng = random.Random(seed * 7919 + 13)
    flip = rng.random() < 0.55
    if flip: img = img.transpose(Image.FLIP_LEFT_RIGHT)
    iw, ih = img.size
    zoom = rng.uniform(1.05, 1.45)          # زوم متفاوت
    scale = max(w / iw, h / ih) * zoom
    img = img.resize((int(iw * scale) + 1, int(ih * scale) + 1), Image.LANCZOS)
    maxL = img.width - w; maxT = img.height - h
    left = int(rng.triangular(0, max(maxL, 1), maxL * rng.random())) if maxL > 0 else 0
    top = int(rng.triangular(0, max(maxT, 1), maxT * rng.random())) if maxT > 0 else 0
    img = img.crop((left, top, left + w, top + h))
    # چرخش خیلی جزئی برای تنوع بیشتر
    ang = rng.uniform(-2.5, 2.5)
    if abs(ang) > 0.4:
        img = img.rotate(ang, resample=Image.BICUBIC, expand=False, fillcolor=(10, 14, 22))
    # روشنایی/کنتراست متفاوت
    img = ImageEnhance.Brightness(img).enhance(rng.uniform(0.88, 1.08))
    img = ImageEnhance.Contrast(img).enhance(rng.uniform(0.92, 1.12))
    return img, rng

def duotone_tint(img, light, dark, strength):
    """لایهٔ رنگ برند با blend جهت‌دار"""
    grad = Image.new('RGB', (img.width, img.height))
    gd = ImageDraw.Draw(grad)
    for y in range(img.height):
        k = y / img.height
        r = int(light[0] * (1 - k) + dark[0] * k)
        g = int(light[1] * (1 - k) + dark[1] * k)
        b = int(light[2] * (1 - k) + dark[2] * k)
        gd.line([(0, y), (img.width, y)], fill=(r, g, b))
    mask = Image.new('L', (img.width, img.height), int(255 * strength))
    # ماسک گرادیانی: بالا کمتر، پایین بیشتر
    md = ImageDraw.Draw(mask)
    for y in range(img.height):
        a = int(255 * strength * (0.35 + 0.65 * (y / img.height)))
        md.line([(0, y), (img.width, y)], fill=a)
    return Image.composite(grad, img, mask)

def add_pattern(img, rng, color, seed):
    """الگوی گرافیکی یکتا: مورب/نقطه/حلقه/مدار"""
    overlay = Image.new('RGBA', (img.width, img.height), (0, 0, 0, 0))
    d = ImageDraw.Draw(overlay)
    kind = seed % 4
    alpha = 26
    if kind == 0:      # نوارهای مورب
        step = rng.choice([46, 58, 72])
        for x in range(-img.height, img.width + step, step):
            d.line([(x, img.height), (x + img.height, 0)], fill=color + (alpha,), width=rng.choice([6, 9, 12]))
    elif kind == 1:    # نقاط
        step = rng.choice([40, 52, 64])
        for yy in range(step // 2, img.height, step):
            for xx in range(step // 2, img.width, step):
                r = rng.choice([3, 4, 5])
                d.ellipse([xx - r, yy - r, xx + r, yy + r], fill=color + (alpha + 8,))
    elif kind == 2:    # حلقه‌های هم‌مرکز از گوشه
        cx, cy = (img.width, 0) if seed % 2 else (0, img.height)
        for r in range(80, int(img.width * 1.3), rng.choice([70, 90, 110])):
            d.ellipse([cx - r, cy - r, cx + r, cy + r], outline=color + (alpha,), width=3)
    else:              # مسیر مدار (خطوط زاویه‌دار + پین)
        for _ in range(rng.randint(7, 11)):
            x1, y1 = rng.randint(0, img.width), rng.randint(0, img.height)
            x2 = x1 + rng.choice([-1, 1]) * rng.randint(60, 220)
            y2 = y1 + rng.choice([-1, 1]) * rng.randint(40, 140)
            d.line([(x1, y1), (x2, y1), (x2, y2)], fill=color + (alpha + 10,), width=3)
            d.ellipse([x2 - 5, y2 - 5, x2 + 5, y2 + 5], fill=color + (alpha + 40,))
    img = Image.alpha_composite(img.convert('RGBA'), overlay)
    return img.convert('RGB')

def make_card(no, t):
    base_name, topic = topic_for(t, no)
    base_path = os.path.join(MEDIA, base_name)
    if not os.path.exists(base_path):
        base_path = os.path.join(MEDIA, 'tip-esd-workspace.jpg')
    img, rng = varied_base(base_path, no, W, H)

    cat = t.get('cat', 'سایر')
    light, dark = CAT_COLORS.get(cat, CAT_COLORS['سایر'])
    img = duotone_tint(img, light, dark, 0.34)          # رنگ برند
    img = add_pattern(img, rng, light, no)               # الگوی یکتا

    # گرادیان تیره برای خوانایی متن
    darkL = Image.new('L', (W, H), 0)
    dgr = ImageDraw.Draw(darkL)
    for y in range(H):
        a = int(70 + (190 - 70) * (y / H) ** 1.35)
        dgr.line([(0, y), (W, y)], fill=a)
    black = Image.new('RGB', (W, H), (8, 12, 20))
    img = Image.composite(black, img, darkL)

    draw = ImageDraw.Draw(img, 'RGBA')

    # نوار رنگی حاشیه
    draw.rectangle([W - 14, 0, W, H], fill=light + (255,))

    # آیکون بزرگ موضوع در دایرهٔ شیشه‌ای (بالا-چپ)
    icon = TOPIC_ICON.get(topic, '🛠')
    cx, cy, cr = 108, 108, 66
    draw.ellipse([cx - cr, cy - cr, cx + cr, cy + cr], fill=(255, 255, 255, 36), outline=light + (200,), width=3)
    f_icon = load_font(F_MED, 58)
    bb = draw.textbbox((0, 0), icon, font=f_icon)
    draw.text((cx - (bb[2] - bb[0]) / 2 - bb[0], cy - (bb[3] - bb[1]) / 2 - bb[1]), icon, font=f_icon, fill=(255, 255, 255, 255))

    # چیپ برند (بالا-راست)
    chip_text = shape(f"{t.get('device','')} • {t.get('brand','')}")
    f_chip = load_font(F_MED, 30)
    cw = int(draw.textlength(chip_text, font=f_chip)) + 44
    draw.rounded_rectangle([W - 34 - cw, 26, W - 34, 26 + 52], radius=26, fill=light + (235,))
    draw.text((W - 34 - cw / 2, 26 + 26), chip_text, font=f_chip, fill=(255, 255, 255, 255), anchor='mm')

    # عنوان قلق (حداکثر ۳ خط)
    f_title = load_font(F_BOLD, 54)
    all_lines = wrap_rtl(draw, t['title'], f_title, W - 150)
    lines = all_lines[:3]
    if len(all_lines) > 3:
        lines[2] = lines[2].rstrip(' ،.…') + '…'
    y = H - 96 - (len(lines) - 1) * 68
    for ln in lines:
        s = shape(ln)
        tw = draw.textlength(s, font=f_title)
        draw.text((W - 60 - tw + 3, y + 3), s, font=f_title, fill=(0, 0, 0, 190))
        draw.text((W - 60 - tw, y), s, font=f_title, fill=(255, 255, 255, 255))
        y += 68

    # فوتر
    f_foot = load_font(F_MED, 26)
    foot = shape(f"بردخان • قلق {fa(no)} از {fa(209)}")
    draw.text((W - 60, 26 + 26), foot, font=f_foot, fill=(255, 255, 255, 205), anchor='rm')  # فوتر بالا-چپ (زیر چیپ برند) تا با عنوان تداخل نکند

    out = os.path.join(MEDIA, f'tip-{no:03d}.jpg')
    img.save(out, 'JPEG', quality=82, optimize=True)
    return out

def main():
    files = sorted(glob.glob(os.path.join(DATA, 'part*.json')),
                   key=lambda x: int(''.join(filter(str.isdigit, os.path.basename(x)))))
    tips = []
    for f in files: tips.extend(json.load(open(f, encoding='utf-8')))
    print(f'dataset: {len(tips)} tips')
    for no, t in enumerate(tips, 1):
        make_card(no, t)
        if no % 40 == 0: print(f'  ... {no}/{len(tips)}')
    print(f'done: {len(tips)} unique images')

if __name__ == '__main__':
    main()
