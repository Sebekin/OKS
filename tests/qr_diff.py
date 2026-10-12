import sys

def load(path):
    blocks, cur = [], []
    for line in open(path):
        s = line.strip()
        if s == 'END': blocks.append(cur); cur = []
        elif s: cur.append(s)
    return blocks

mine, ref = load('/tmp/mine.txt'), load('/tmp/ref.txt')
for bi, (m, r) in enumerate(zip(mine, ref)):
    n = len(m)
    # функция маски от эталонной матрицы: в зонах данных fn=false -> maskbit = m XOR ref
    diffs = [(y,x) for y in range(n) for x in range(n) if m[y][x] != r[y][x]]
    print(f"block{bi}: size={n} diff_cells={len(diffs)}")
    # проверка: все ли различия объясняются разной маской (константа по формуле?)
    # соберём гипотезу: маска 0..7 из нашей таблицы
    def maskbit(mask,y,x):
        if mask==0: return (y+x)%2==0
        if mask==1: return y%2==0
        if mask==2: return x%3==0
        if mask==3: return (y+x)%3==0
        if mask==4: return (y//2+x//3)%2==0
        if mask==5: return ((y*x)%2)+((y*x)%3)==0
        if mask==6: return (((y*x)%2)+((y*x)%3))%2==0
        return (((y+x)%2)+((y*x)%3))%2==0
    # для каждой пары масок (our,ref) проверить согласованность на всех diff-ячейках... но мы не знаем какие ячейки data.
    # проще: diff-паттерн должен совпадать с XOR двух масок на всей области данных.
    found=None
    for a in range(8):
        for b in range(8):
            ok=all(((m[y][x]!=r[y][x]) == (maskbit(a,y,x)^maskbit(b,y,x))) for (y,x) in diffs)
            # и на остальных клетках нет расхождений => там XOR масок = false или это function patterns
            if ok and all((m[y][x]==r[y][x]) or maskbit(a,y,x)^maskbit(b,y,x) for y in range(n) for x in range(n)):
                found=(a,b); break
        if found: break
    print("  mask hypothesis (mine,ref):", found)
