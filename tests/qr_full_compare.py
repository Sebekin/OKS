import qrcode, json, subprocess
from qrcode.constants import ERROR_CORRECT_M

payload = 'https://sebekin.ru/oks/object/12'

# Эталонная матрица (python-qrcode)
qr = qrcode.QRCode(version=3, error_correction=ERROR_CORRECT_M, box_size=1, border=0)
qr.add_data(payload); qr.make(fit=True)
ref_mask = qr.best_mask_pattern()
modules = qr.get_matrix()
n = len(modules)
ref = [[1 if modules[y][x] else 0 for x in range(n)] for y in range(n)]
print("ref mask:", ref_mask)

# Функциональная карта эталона: пересчитаем независимо
def fnmap(n):
    fn = [[False]*n for _ in range(n)]
    def mark(r,c,h,w):
        for y in range(max(0,r),min(n,r+h)):
            for x in range(max(0,c),min(n,c+w)):
                fn[y][x]=True
    mark(0,0,9,9); mark(0,n-8,9,8); mark(n-8,0,8,9)
    for i in range(8,n-8,2*4+0): pass
    # timing
    for i in range(n): fn[6][i]=True; fn[i][6]=True
    # alignment v3: none
    # format areas
    for i in range(9): fn[8][i]=True; fn[i][8]=True
    for i in range(8): fn[8][n-1-i]=True; fn[n-1-i][8]=True
    fn[n-8][8]=True
    return fn

fn = fnmap(n)

def maskbit(mask,y,x):
    if mask==0: return (y+x)%2==0
    if mask==1: return y%2==0
    if mask==2: return x%3==0
    if mask==3: return (y+x)%3==0
    if mask==4: return (y//2 + x//3)%2==0
    if mask==5: return ((y*x)%2)+((y*x)%3)==0
    if mask==6: return (((y*x)%2)+((y*x)%3))%2==0
    return (((y+x)%2)+((y*x)%3))%2==0

def read_codewords(mat, fn, mask, n, total_bits):
    bs=[]
    up=True; col=n-1
    while col>0:
        if col==6: col-=1
        for cnt in range(n):
            row = n-1-cnt if up else cnt
            for c in range(2):
                cc=col-c
                if not fn[row][cc]:
                    v = mat[row][cc] ^ (1 if maskbit(mask,row,cc) else 0)
                    bs.append(v)
            up = not up
        col-=2
    cw=[int(''.join(map(str,bs[i*8:i*8+8])),2) for i in range(total_bits//8)]
    return cw

total_cw = 70  # v3-M: 44 data + 26 ec
ref_cw = read_codewords(ref, fn, ref_mask, n, total_cw*8)
print("ref codewords:", ",".join(map(str,ref_cw)))
json.dump(ref_cw, open('/tmp/ref_cw_final.json','w'))
