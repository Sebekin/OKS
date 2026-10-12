<?php
require __DIR__.'/../app/lib/QrEncoder.php';

$payloads = [
  'https://sebekin.ru/oks/object/12',
  'otpauth://totp/OKS:admin@sebekin.ru?secret=JBSWY3DPEHPK3PXP&issuer=OKS',
  'A',
];
foreach ($payloads as $i => $p) {
    $m = QrEncoder::encode($p);
    $rows = array_map(fn($r) => implode('', array_map(fn($c) => $c ? '1':'0', $r)), $m);
    file_put_contents("/tmp/ours_$i.txt", implode("\n", $rows));
}
echo "ok\n";
