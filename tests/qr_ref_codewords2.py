# Извлечение кодовых слов ЭТАЛОНА (python-qrcode) корректным способом:
# используем внутренности библиотеки — qr.modules и её же mask pattern,
# но раскладку делаем по независимой карте функциональных модулей.
import qrcode, json
from qrcode.constants import ERROR_CORRECT_M

payload = 'https://sebekin.ru/oks/object/12'
qr = qrcode.QRCode(version=3, error_correction=ERROR_CORRECT_M, box_size=1, border=0)
qr.add_data(payload); qr.make(fit=True)
n = len(qr.get_matrix())
mask = qr.best_mask_pattern()
mat = [[1 if v else 0 for v in row] for row in qr.get_matrix()]

def fnmap(n):
    fn=[[False]*n for _ in range(n)]
    def mark(r,c,h,w):
        for y in range(max(0,r),min(n,r+h)):
            for x in range(max(0,c),min(n,c+w)): fn[y][x]=True
    mark(-1,-1,9,9); mark(-1,n-8,9,8); mark(n-8,-1,8,9)
    for i in range(n): fn[6][i]=True; fn[i][6]=True
    for i in range(9): fn[8][i]=True; fn[i][8]=True
    for i in range(8): fn[8][n-1-i]=True
    for i in range(7): fn[n-1-i][8]=True
    return fn
fn = fnmap(n)

def maskbit(m,y,x):
    if m==0: return (y+x)%2==0
    if m==1: return y%2==0
    if m==2: return x%3==0
    if m==3: return (y+x)%3==0
    if m==4: return (y//2 + x//3)%2==0
    if m==5: return ((y*x)%2)+((y*x)%3)==0
    if m==6: return (((y*x)%2)+((y*x)%3))%2==0
    return (((y+x)%2)+((y*x)%3))%2==0

bs=[]; up=True; col=n-1
while col>0:
    if col==6: col-=1
    for cnt in range(n):
        row=n-1-cnt if up else cnt
        for c in range(2):
            cc=col-c
            if not fn[row][cc]:
                bs.append(mat[row][cc] ^ (1 if maskbit(mask,row,cc) else 0))
        up=not up
    col-=2
bits=''.join(map(str,bs))
print("total bits:", len(bits))
# первые биты потока данных: ожидаем 0100 + длина
print("header:", bits[:12])
cw=[int(bits[i*8:i*8+8],2) for i in range(len(bits)//8)]
data=cw[:44]; ec=cw[44:70]
open('/tmp/ref_data44.txt','w').write(",".join(map(str,data)))
open('/tmp/ref_ec26.txt','w').write(",".join(map(str,ec)))
print("data:", ",".join(map(str,data)))
print("ec:", ",".join(map(str,ec)))
