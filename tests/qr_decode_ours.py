# Полностью независимый декодер QR (ISO 18004): читает НАШУ матрицу, восстанавливает
# формат-инфу, кодовые слова, проверяет RS-синдромы и извлекает payload.
import json, sys

mat = json.load(open('/tmp/ours_matrix.json'))
n = len(mat)

def gf_tables():
    exp=[0]*512; log=[0]*256; x=1
    for i in range(255):
        exp[i]=x; log[x]=i
        x<<=1
        if x & 0x100: x^=0x11D
    for i in range(255,512): exp[i]=exp[i-255]
    return log,exp
LOG,EXP=gf_tables()
def gmul(a,b): return 0 if a==0 or b==0 else EXP[(LOG[a]+LOG[b])%255]

def maskbit(m,y,x):
    if m==0: return (y+x)%2==0
    if m==1: return y%2==0
    if m==2: return x%3==0
    if m==3: return (y+x)%3==0
    if m==4: return (y//2 + x//3)%2==0
    if m==5: return ((y*x)%2)+((y*x)%3)==0
    if m==6: return (((y*x)%2)+((y*x)%3))%2==0
    return (((y+x)%2)+((y*x)%3))%2==0

# --- формат информации (LSB-first, TL-зона) ---
bits=[]
for i in range(6): bits.append(mat[8][i])
bits += [mat[8][7], mat[8][8], mat[7][8]]
for r in range(5,-1,-1): bits.append(mat[r][8])
raw=0
for i,b in enumerate(bits): raw |= b<<i
LEGAL={}
gen=0b10100110111
for data in range(32):
    rem=data<<10
    for i in range(14,9,-1):
        if (rem>>i)&1: rem^=gen<<(i-10)
    LEGAL[((data<<10)|rem)^0x5412]=data
found=[d for w,d in LEGAL.items() if w==raw]
if not found:
    print("FORMAT PARSE FAIL"); sys.exit(1)
d=found[0]; ec_bits=(d>>3)&3; mask=d&7
print("format OK: ec_bits=",bin(ec_bits),"mask=",mask)

# --- функциональная карта v3 ---
fn=[[False]*n for _ in range(n)]
def mark(r,c,h,w):
    for y in range(max(0,r),min(n,r+h)):
        for x in range(max(0,c),min(n,c+w)): fn[y][x]=True
mark(-1,-1,9,9); mark(-1,n-8,9,8); mark(n-8,-1,8,9)
for i in range(n): fn[6][i]=True; fn[i][6]=True
for i in range(9): fn[8][i]=True; fn[i][8]=True
for i in range(8): fn[8][n-1-i]=True
for i in range(7): fn[n-1-i][8]=True

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
cw=[int(''.join(map(str,bs[i*8:i*8+8])),2) for i in range(560//8)]
data=cw[:44]; ec=cw[44:]

# --- RS-синдромы всего блока (v3-M один блок 44+26) ---
allcw=data+ec
bad=[]
for j in range(26):
    s=0
    for idx,v in enumerate(allcw):
        s^=gmul(v,EXP[(j*(len(allcw)-1-idx))%255])
    if s!=0: bad.append(j)
print("RS syndromes zero:", not bad, bad[:5])

# --- поток данных ---
stream=''.join(format(b,'08b') for b in data)
pos=0; mode=int(stream[pos:pos+4],2); pos+=4
ln=int(stream[pos:pos+8],2); pos+=8
out=bytes(int(stream[pos+8*k:pos+8*k+8],2) for k in range(ln))
print("mode:",mode,"len:",ln,"payload:",out.decode(errors='replace'))
ok = mode==4 and out.decode(errors='replace')=='https://sebekin.ru/oks/object/12' and not bad
print("VERDICT:", "PASS" if ok else "FAIL")
