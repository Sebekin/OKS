<?php
require __DIR__.'/../app/lib/QrEncoder.php';
$payloads = [
  'https://sebekin.ru/oks/object/12',
  'otpauth://totp/OKS:admin@sebekin.ru?secret=JBSWY3DPEHPK3PXP&issuer=OKS',
  'A',
];
foreach ($payloads as $i => $p) {
    echo "payload $i len=" . strlen($p) . " size=" . count(QrEncoder::encode($p)) . "\n";
}
