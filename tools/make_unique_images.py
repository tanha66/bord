#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Bordkhan — تولید ۲۰۹ عکس یکتا برای قلق‌ها (سئو)
هر قلق یک تصویر اختصاصی می‌گیرد: عکس موضوعی مرتبط + عنوان فارسی قلق + برچسب برند
خروجی: uploads-seed/tips/tip-001.jpg ... tip-209.jpg (ترتیب دقیقاً مطابق seed)
اجرا:  python3 tools/make_unique_images.py
"""
import json, glob, os, sys
from PIL import Image, ImageDraw, ImageFilter, ImageFont
import arabic_reshaper
from bidi.algorithm import get_display

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA = os.path.join(ROOT, 'php-extended', 'seed-data')
MEDIA = os.path.join(ROOT, 'uploads-seed', 'tips')
FONTS = os.path.join(ROOT, 'tools', 'fonts')

W, H = 1280, 720

# ---------- فونت‌ها ----------
F_BOLD = os.path.join(FONTS, 'Vazirmatn-Bold.ttf')
F_MED  = os.path.join(FONTS, 'Vazirmatn-Medium.ttf')

def shape(s: str) -> str:
    return get_display(arabic_reshaper.reshape(s))

FA_DIGITS = str.maketrans('0123456789', '۰۱۲۳۴۵۶۷۸۹')
def fa(n) -> str:
    return str(n).translate(FA_DIGITS)

# ---------- رنگ دسته‌ها ----------
CAT_COLORS = {
    'آیفون':  (14, 165, 233),   # sky
    'سامسونگ': (139, 92, 246),  # violet
    'شیائومی': (249, 115, 22),  # orange
    'هواوی':  (239, 68, 68),    # red
    'آنر':    (20, 184, 166),   # teal
    'سایر':   (245, 158, 11),   # amber
}

# ---------- نگاشت موضوعی عکس پایه ----------
TOPIC_BASES = {
    'battery':  ['tip-swollen-battery.jpg', 'tip-disconnect-battery-flex.jpg', 'tip-open-iphone-repair.jpg'],
    'display':  ['tip-cracked-display.jpg', 'tip-screws-organization-mat.jpg', 'tip-open-iphone-repair.jpg'],
    'camera':   ['tip-cracked-camera-lens.jpg', 'tip-board-macro-afem.jpg', 'tip-cracked-display.jpg'],
    'charge':   ['tip-wet-usb-port.jpg', 'tip-charger-and-cable.jpg', 'tip-phone-circuit-repair.jpg'],
    'water':    ['tip-phone-in-rice.jpg', 'tip-wet-usb-port.jpg'],
    'audio':    ['tip-speaker-mesh-macro.jpg', 'tip-open-iphone-repair.jpg'],
    'board':    ['tip-board-under-microscope.jpg', 'tip-board-macro-afem.jpg', 'tip-phone-circuit-repair.jpg'],
    'general':  ['tip-screws-organization-mat.jpg', 'tip-esd-workspace.jpg', 'tip-heat-gun-backcover.jpg', 'tip-open-iphone-repair.jpg'],
}
KEYWORDS = [
    ('battery', ['باتری', 'شارژدهی', 'مصرف باتری', 'متورم', 'خاموشی', 'پرش درصد']),
    ('water',   ['آب', 'رطوبت', 'برنج', 'ساحل', 'خیس', 'شور']),
    ('display', ['نمایشگر', 'تاچ', 'صفحه', 'لمس', 'گوست', 'خط سبز', 'لکه', 'چشمک', 'ال‌سی‌دی', 'LCD', 'AMOLED', 'سوختگی صفحه']),
    ('camera',  ['دوربین', 'لنز', 'فلش', 'فوکوس', 'Face ID', 'چهره']),
    ('audio',   ['اسپیکر', 'میکروفون', 'صدا', 'ویبره', 'بلندگو', 'هدفون', 'جک', 'Audio']),
    ('board',   ['برد', 'IC', '4013', '4014', 'NAND', 'میکروسکوپ', 'ریستارت', 'لوگو', 'DFU', 'ریستارت مکرر', 'گیر', 'هنگ', 'نرم‌افزار', 'فلش', 'رام', 'ریست', 'وای‌فای', 'بلوتوث', 'سیم‌کارت', 'NFC', 'اثر انگشت']),
    ('charge',  ['شارژ', 'پورت', 'Type-C', 'سوکت', 'کابل', 'شارژر', 'پاوربانک', 'SuperCharge', 'MagSafe', 'PD']),
]

def topic_for(t, no):
    text = ' '.join([t.get('title',''), t.get('fault',''), t.get('tags',''), t.get('short','')])
    for topic, kws in KEYWORDS:
        if any(k in text for k in kws):
            bases = TOPIC_BASES[topic]
            return bases[no % len(bases)], topic
    bases = TOPIC_BASES['general']
    return bases[no % len(bases)], 'general'

# ---------- متن ----------
def load_font(path, size):
    return ImageFont.truetype(path, size)

def wrap_rtl(draw, text, font, max_w):
    words = text.split()
    lines, cur = [], ''
    for w in words:
        trial = (cur + ' ' + w).strip()
        if draw.textlength(shape(trial), font=font) <= max_w or not cur:
            cur = trial
        else:
            lines.append(cur); cur = w
    if cur: lines.append(cur)
    return lines

def cover_crop(img, w, h):
    iw, ih = img.size
    scale = max(w / iw, h / ih)
    img = img.resize((int(iw * scale) + 1, int(ih * scale) + 1), Image.LANCZOS)
    left = (img.width - w) // 2
    top = (img.height - h) // 2
    return img.crop((left, top, left + w, top + h))

def make_card(no, t):
    base_name, topic = topic_for(t, no)
    base_path = os.path.join(MEDIA, base_name)
    if not os.path.exists(base_path):
        base_path = os.path.join(MEDIA, 'tip-esd-workspace.jpg')
    img = Image.open(base_path).convert('RGB')
    img = cover_crop(img, W, H)

    # کمی تیره‌سازی برای خوانایی متن
    dark = Image.new('L', (W, H), 0)
    dgr = ImageDraw.Draw(dark)
    for y in range(H):
        # گرادیان: بالا ۹۰، پایین ۱۸۵
        alpha = int(90 + (185 - 90) * (y / H) ** 1.4)
        dgr.line([(0, y), (W, y)], fill=alpha)
    black = Image.new('RGB', (W, H), (8, 12, 20))
    img = Image.composite(black, img, dark)

    draw = ImageDraw.Draw(img, 'RGBA')
    cat = t.get('cat', 'سایر')
    cr, cg, cb = CAT_COLORS.get(cat, CAT_COLORS['سایر'])

    # نوار رنگی سمت راست (هویت دسته)
    draw.rectangle([W - 14, 0, W, H], fill=(cr, cg, cb, 255))

    # چیپ بالا: دسته • برند
    chip_text = shape(f"{t.get('device','')} • {t.get('brand','')}")
    f_chip = load_font(F_MED, 30)
    cw = int(draw.textlength(chip_text, font=f_chip)) + 44
    draw.rounded_rectangle([W - 34 - cw, 26, W - 34, 26 + 52], radius=26, fill=(cr, cg, cb, 235))
    draw.text((W - 34 - cw / 2, 26 + 26), chip_text, font=f_chip, fill=(255, 255, 255, 255), anchor='mm')

    # عنوان قلق (حداکثر ۳ خط)
    f_title = load_font(F_BOLD, 54)
    lines = wrap_rtl(draw, t['title'], f_title, W - 150)[:3]
    if len(lines) == 3 and len(wrap_rtl(draw, t['title'], f_title, W - 150)) > 3:
        lines[2] = lines[2].rstrip(' ،.…') + '…'
    y = H - 96 - (len(lines) - 1) * 68
    for ln in lines:
        s = shape(ln)
        tw = draw.textlength(s, font=f_title)
        # سایهٔ نرم
        draw.text((W - 60 - tw + 3, y + 3), s, font=f_title, fill=(0, 0, 0, 190))
        draw.text((W - 60 - tw, y), s, font=f_title, fill=(255, 255, 255, 255))
        y += 68

    # فوتر: بردخان + شمارهٔ قلق + برچسب موضوع
    f_foot = load_font(F_MED, 26)
    foot = shape(f"بردخان • قلق شمارهٔ {fa(no)}")
    draw.text((W - 60, H - 34), foot, font=f_foot, fill=(255, 255, 255, 225), anchor='rm')
    tag = {'battery':'🔋','display':'📱','camera':'📷','charge':'🔌','water':'💧','audio':'🔊','board':'🔬','general':'🛠'}.get(topic, '🛠')
    draw.text((60, H - 34), tag, font=load_font(F_MED, 30), fill=(255, 255, 255, 225), anchor='lm')

    out = os.path.join(MEDIA, f'tip-{no:03d}.jpg')
    img.save(out, 'JPEG', quality=82, optimize=True)
    return out

def main():
    files = sorted(glob.glob(os.path.join(DATA, 'part*.json')),
                   key=lambda x: int(''.join(filter(str.isdigit, os.path.basename(x)))))
    tips = []
    for f in files:
        tips.extend(json.load(open(f, encoding='utf-8')))
    total = len(tips)
    print(f'dataset: {total} tips')
    if total == 0:
        sys.exit('no dataset!')
    made = 0
    for no, t in enumerate(tips, 1):
        make_card(no, t)
        made += 1
        if made % 30 == 0:
            print(f'  ... {made}/{total}')
    print(f'done: {made} unique images in {MEDIA}')

if __name__ == '__main__':
    main()
