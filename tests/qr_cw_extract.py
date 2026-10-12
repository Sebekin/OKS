# Extract codewords from reference matrices (qrcode lib, level M) using functional-module map
import qrcode
from qrcode.constants import ERROR_CORRECT_M

payloads = [
  'https://sebekin.ru/oks/object/12',
  'otpauth://totp/OKS:admin@sebekin.ru?secret=JBSWY3DPEHPK3PXP&issuer=OKS',
  'A',
]

def fnmap(n, version):
    fn = [[False]*n for _ in range(n)]
    def mark(r,c,h,w):
        for y in range(max(0,r), min(n,r+h)):
            for x in range(max(0,c), min(n,c+w)):
                fn[y][x]=True
    # finders + separators
    mark(-1,-1,9,9); mark(-1,n-8,9,9); mark(n-8,-1,9,9)
    # timing
    for i in range(n): fn[6][i]=True; fn[i][6]=True
    # alignment
    from qrcode.LUT import PATTERN_POSITION_TABLE
    pp = PATTERN_POSITION_TABLE[version-1]
    for py in pp:
        for px in pp:
            if (py,px) in [(6,6),(6,pp[-1]),(pp[-1],6)]: continue
            mark(py-2,px-2,5,5)
    # format info
    for i in range(9): fn[8][i]=True; fn[i][8]=True
    for i in range(8): fn[8][n-1-i]=True; fn[n-1-i][8]=True
    fn[n-8][8]=True
    # version info v>=7
    if version>=7:
        mark(n-11,0,3,6); mark(0,n-11,6,3)
    return fn

def maskbit(mask,y,x):
    if mask==0: return (y+x)%2==0
    if mask==1: return y%2==0
    if mask==2: return x%3==0
    if mask==3: return (y+x)%3==0
    if mask==4: return (y//2 + x//3)%2==0
    if mask==5: return ((y*x)%2)+((y*x)%3)==0
    if mask==6: return (((y*x)%2)+((y*x)%3))%2==0
    return (((y+x)%2)+((y*x)%3))%2==0

def read_bits(mat, fn, mask, n):
    bits=[]
    up=True
    col=n-1
    while col>0:
        if col==6: col-=1
        cnt=0
        while cnt<n:
            row = n-1-cnt if up else cnt
            for c in (0,1):
                cc=col-c
                if not fn[row][cc]:
                    v = mat[row][cc] ^ maskbit(mask,row,cc)
                    bits.append('1' if v else '0')
            cnt+=1
        up=not up
        col-=2
    return ''.join(bits)

for i,p in enumerate(payloads):
    qr=qrcode.QRCode(error_correction=ERROR_CORRECT_M)
    qr.add_data(p.encode()); qr.make(fit=True)
    m=qr.get_matrix(); n=len(m); v=qr.version
    fn=fnmap(n,v)
    bits=read_bits(m,fn,qr.best_mask_pattern() if hasattr(qr,'best_mask_pattern') else qr.mask_pattern,n)
    cw=[int(bits[j:j+8],2) for j in range(0,len(bits)-7,8)]
    print(i,"v",v,"mask",getattr(qr,'mask_pattern','?'))
    print(" first 20 cw:",cw[:20])
