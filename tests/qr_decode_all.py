from PIL import Image
from pyzbar.pyzbar import decode

for i in range(3):
    rows = open(f"/tmp/ours_{i}.txt").read().splitlines()
    n = len(rows)
    q = 4
    dim = n + q*2
    img = Image.new("L", (dim, dim), 255)
    px = img.load()
    for y in range(n):
        for x in range(n):
            if rows[y][x] == '1':
                px[x+q, y+q] = 0
    img = img.resize((dim*8, dim*8), Image.NEAREST)
    res = decode(img)
    ok = res[0].data.decode('utf-8','replace') if res else None
    print(i, "PASS" if ok else "FAIL", repr(ok)[:90])
