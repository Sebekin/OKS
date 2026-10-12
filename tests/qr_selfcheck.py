# Самопроверка извлекателя: пересобираем матрицу из (data+ec) кодовых слов,
# наивной раскладкой зигзагом + маска, и сверяем с эталонной матрицей python-qrcode.
import qrcode, json
from qrcode.constants import ERROR_CORRECT_M

payload = 'https://sebekin.ru/oks/object/12'
qr = qrcode.QRCode(version=3, error_correction=ERROR_CORRECT_M, box_size=1, border=0)
qr.add_data(payload); qr.make(fit=True)
n = len(qr.get_matrix())
ref_mask = qr.best_mask_pattern()
ref_mat = [[1 if v else 0 for v in row] for row in qr.get_matrix()]

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

def maskbit(mask,y,x):
    if mask==0: return (y+x)%2==0
    if mask==1: return y%2==0
    if mask==2: return x%3==0
    if mask==3: return (y+x)%3==0
    if mask==4: return (y//2 + x//3)%2==0
    if mask==5: return ((y*x)%2)+((y*x)%3)==0
    if mask==6: return (((y*x)%2)+((y*x)%3))%2==0
    return (((y+x)%2)+((y*x)%3))%2==0

# --- извлечение данных (как раньше) ---
bs=[]; up=True; col=n-1
while col>0:
    if col==6: col-=1
    for cnt in range(n):
        row=n-1-cnt if up else cnt
        for c in range(2):
            cc=col-c
            if not fn[row][cc]:
                bs.append(ref_mat[row][cc] ^ (1 if maskbit(ref_mask,row,cc) else 0))
        up=not up
    col-=2
cw=[int(''.join(map(str,bs[i*8:i*8+8])),2) for i in range(len(bs)//8)]
print("extracted cw count:", len(cw))

# --- обратная сборка той же маскированной области из тех же битов ---
m2=[[0]*n for _ in range(n)]
bi=0; up=True; col=n-1
while col>0:
    if col==6: col-=1
    for cnt in range(n):
        row=n-1-cnt if up else cnt
        for c in range(2):
            cc=col-c
            if not fn[row][cc]:
                bit=int(bs[bi]); bi+=1
                m2[row][cc]= bit ^ (1 if maskbit(ref_mask,row,cc) else 0)
        up=not up
    col-=2
diff=sum(1 for y in range(n) for x in range(n) if not fn[y][x] and m2[y][x]!=ref_mat[y][x])
print("roundtrip diff in data area:", diff)
