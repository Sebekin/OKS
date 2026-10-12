# Сквозной тест: матрицы QrEncoder (PHP) -> PNG -> независимое декодирование pyzbar
import json, sys
from PIL import Image
try:
    from pyzbar.pyzbar import decode
except ImportError:
    print("pyzbar not installed"); sys.exit(2)

items = json.load(open('/tmp/mine_qr.json'))
ok = True
for it in items:
    m = it['matrix']; n = len(m); q = 4; scale = 3
    size = (n + 2*q) * scale
    img = Image.new('L', (size, size), 255)
    px = img.load()
    for y in range(n):
        for x in range(n):
            if m[y][x]:
                for dy in range(scale):
                    for dx in range(scale):
                        px[(x+q)*scale+dx, (y+q)*scale+dy] = 0
    d = decode(img)
    got = d[0].data.decode() if d else None
    good = (got == it['text'])
    ok = ok and good
    print(('OK  ' if good else 'FAIL') + f" v{n-8} len={len(it['text'])} got={str(got)[:70]!r}")
print('ALL OK' if ok else 'HAS FAILURES')
sys.exit(0 if ok else 1)
