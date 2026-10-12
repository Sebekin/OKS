import qrcode
from qrcode.constants import ERROR_CORRECT_M

payloads = [
  'https://sebekin.ru/oks/object/12',
  'otpauth://totp/OKS:admin@sebekin.ru?secret=JBSWY3DPEHPK3PXP&issuer=OKS',
  'A',
]
for i, p in enumerate(payloads):
    qr = qrcode.QRCode(error_correction=ERROR_CORRECT_M)
    qr.add_data(p.encode()); qr.make(fit=True)
    ref = qr.get_matrix()
    lines = open(f"/tmp/ours_{i}.txt").read().splitlines()
    ours = [[c=='1' for c in row] for row in lines]
    nr, no = len(ref), len(ours)
    print(f"payload {i}: ref version={qr.version} size={nr}, ours size={no}")
    if nr != no:
        continue
    diffs = [(y,x) for y in range(nr) for x in range(nr) if ref[y][x]!=ours[y][x]]
    print("  diff count:", len(diffs), diffs[:10])
