import sys, re
from PIL import Image

def load_matrix(path):
    blocks = []
    cur = []
    for line in open(path):
        line = line.strip()
        if line == 'END':
            blocks.append(cur); cur = []
        elif line:
            cur.append(line)
    return blocks

def to_png(mat, out, scale=8, border=4):
    n = len(mat)
    dim = (n + 2*border)*scale
    img = Image.new('L', (dim, dim), 255)
    px = img.load()
    for y in range(n):
        row = mat[y]
        for x in range(len(row)):
            if row[x] == '#':
                for dy in range(scale):
                    for dx in range(scale):
                        px[(x+border)*scale+dx, (y+border)*scale+dy] = 0
    img.save(out)

blocks = load_matrix(sys.argv[1])
tag = sys.argv[2] if len(sys.argv) > 2 else 'q'
for i, m in enumerate(blocks):
    png = f'/tmp/{tag}{i}.png'
    to_png(m, png)
    from pyzbar.pyzbar import decode
    res = decode(Image.open(png))
    print(f"block{i} size={len(m)}:", [r.data.decode() for r in res] or "NOT DECODED")
