<?php
// Дамп матриц QrEncoder в JSON для сквозного теста декодированием pyzbar
require __DIR__ . '/../app/lib/QrEncoder.php';
$texts = [
    'https://sebekin.ru/oks/object/12',
    'otpauth://totp/OKS:admin?secret=JBSWY3DPEHPK3PXP&issuer=OKS',
    'A',
    str_repeat('x', 200),
];
$out = [];
foreach ($texts as $t) {
    $m = QrEncoder::encode($t);
    $out[] = ['text' => $t, 'matrix' => array_map(fn($row) => array_map(fn($c) => $c ? 1 : 0, $row), $m)];
}
file_put_contents('/tmp/mine_qr.json', json_encode($out));
echo "dumped\n";
