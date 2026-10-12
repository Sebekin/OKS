import qrcode
from qrcode.constants import ERROR_CORRECT_M

payload = 'https://sebekin.ru/oks/object/12'
qr = qrcode.QRCode(version=3, error_correction=ERROR_CORRECT_M, box_size=1, border=0)
qr.add_data(payload); qr.make(fit=True)
c = qr.data_list[0]
buf = []
class B:
    def put(self, val, length):
        for i in range(length-1, -1, -1):
            buf.append((val >> i) & 1)
c.write(B())
s = ''.join(map(str, buf))
print("bitlen:", len(s)); print(s[:48])
by = [int(s[i:i+8],2) for i in range(0,len(s)//8*8,8)]
print(",".join(map(str,by)))
