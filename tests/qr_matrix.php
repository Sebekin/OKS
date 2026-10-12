<?php
require __DIR__ . '/../app/lib/QrEncoder.php';

$payloads = [
    'otpauth://totp/UchetOKS:admin?secret=JBSWY3DPEHPK3PXP&issuer=UchetOKS',
    'https://sebekin.ru/oks/object/12',
];
foreach ($payloads as $p) {
    $m = QrEncoder::encode($p);
    foreach ($m as $row) echo implode('', array_map(fn($c) => $c ? '#' : '.', $row)), "\n";
    echo "END\n";
}
