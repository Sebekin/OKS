import qrcode
from qrcode.constants import ERROR_CORRECT_M
payloads = [
    'otpauth://totp/UchetOKS:admin?secret=JBSWY3DPEHPK3PXP&issuer=UchetOKS',
    'https://sebekin.ru/oks/object/12',
]
for p in payloads:
    qr = qrcode.QRCode(error_correction=ERROR_CORRECT_M, box_size=1, border=0)
    qr.add_data(p); qr.make(fit=True)
    m = qr.get_matrix()
    for row in m: print(''.join('#' if c else '.' for c in row))
    print('END')
