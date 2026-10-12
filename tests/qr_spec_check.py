import qrcode
from qrcode.constants import ERROR_CORRECT_M
for v in range(1, 11):
    qr = qrcode.QRCode(version=v, error_correction=ERROR_CORRECT_M)
    total = qr.get_total_codewords()
    ec_per_block, num_blocks = qr.get_error_correcting_code_length() if hasattr(qr,'get_error_correcting_code_length') else (None,None)
    print(v, "total_cw", total)
