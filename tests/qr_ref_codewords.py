# Реверс-зигзаг из эталонной матрицы с НЕЗАВИСИМО построенной fn-картой
import qrcode, json
from qrcode.constants import ERROR_CORRECT_M

payload = 'https://sebekin.ru/oks/object/12'
qr = qrcode.QRCode(version=3, error_correction=ERROR_CORRECT_M, box_size=1, border=0)
qr.add_data(payload); qr.make(fit=True)
m = [list(map(int,r)) for r in [['#'==c for c in row] and [1 if c else 0 for c in row] for row in (lambda mm: [''.join('#' if x else '.' for x in r) for r in mm])(qr.get_matrix())]]
n = len(m)

def maskbit(mask,y,x):
    if mask==0: return (y+x)%2==0
    if mask==1: return y%2==0
    if mask==2: return x%3==0
    if mask==3: return (y+x)%3==0
    if mask==4: return (y//2+x//3)%2==0
    if mask==5: return ((y*x)%2)+((y*x)%3)==0
    if mask==6: return (((y*x)%2)+((y*x)%3))%2==0
    return (((y+x)%2)+((y*x)%3))%2==0

fmt_bits=[m[8][0],m[8][1],m[8][2],m[8][3],m[8][4],m[8][5],m[8][7],m[8][8],m[7][8],m[5][8],m[4][8],m[3][8],m[2][8],m[1][8],m[0][8]]
fmt=0
for i,b in enumerate(fmt_bits):
    if b: fmt|=1<<i
fmt^=0x5412
mask=(fmt>>10)&7
print("ref mask:",mask)

fn=[[0]*n for _ in range(n)]
for oy,ox in [(0,0),(0,n-7),(n-7,0)]:
    for y in range(7):
        for x in range(7): fn[oy+y][ox+x]=1
    for i in range(-1,8):
        for yy,xx in [(oy-1,ox+i),(oy+7,ox+i),(oy+i,ox-1),(oy+i,ox+7)]:
            if 0<=yy<n and 0<=xx<n: fn[yy][xx]=1
for i in range(8,n-8): fn[6][i]=fn[i][6]=1
# alignment v3: позиции [6,22], пропуск пересечений с finder
for py in [6,22]:
    for px in [6,22]:
        if (py,px) in [(6,6),(6,22),(22,6)]: continue
        for dy in range(-2,3):
            for dx in range(-2,3): fn[py+dy][px+dx]=1
for i in range(9):
    if i!=6: fn[8][i]=fn[i][8]=1
for i in range(8): fn[8][n-1-i]=fn[n-1-i][8]=1
fn[n-8][8]=1

bs=''
up=True
col=n-1
while col>0:
    if col==6: col-=1
    for cnt in range(n):
        row=n-1-cnt if up else cnt
        for c in range(2):
            cc=col-c
            if not fn[row][cc]:
                v=m[row][cc]^maskbit(mask,row,cc)
                bs+='1' if v else '0'
        up=not up
    col-=2
vals=[int(bs[i:i+8],2) for i in range(0,len(bs)//8*8,8)]
print("ref codewords:",",".join(map(str,vals)))
json.dump(vals,open('/tmp/ref_cw.json','w'))
