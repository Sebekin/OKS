# Эталонный RS-кодировщик QR: GF(256) mod 0x11D, генератор prod_{i=0}^{n-1}(x - alpha^i).
import sys

def rs_encode(data, ec_count):
    exp = [0]*512; log = [0]*256
    x = 1
    for i in range(255):
        exp[i] = x; log[x] = i
        x <<= 1
        if x & 0x100: x ^= 0x11D
    for i in range(255, 512): exp[i] = exp[i-255]
    def gmul(a,b):
        return 0 if a==0 or b==0 else exp[(log[a]+log[b]) % 255]
    # gen в порядке убывания степеней: gen[0]=x^n коэффициент 1
    gen=[1]
    for i in range(ec_count):
        new=[0]*(len(gen)+1)
        for j,g in enumerate(gen):
            new[j]^=g
            new[j+1]^=gmul(g,exp[i])
        gen=new
    rem=[0]*ec_count
    for d in data:
        factor=d ^ rem[0]
        rem=rem[1:]+[0]
        if factor==0: continue
        for j in range(ec_count):
            rem[j]^=gmul(factor,gen[j+1])
    return rem

if __name__=='__main__':
    data=[int(x) for x in open('/tmp/ref_data44.txt').read().strip().split(',')]
    print(",".join(map(str,rs_encode(data,26))))
