<?php
require __DIR__.'/../app/lib/QrEncoder.php';
$payloads = [
  'https://sebekin.ru/oks/object/12',
  'otpauth://totp/OKS:admin@sebekin.ru?secret=JBSWY3DPEHPK3PXP&issuer=OKS',
  'A',
];
// replicate encode() internals up to interleaved codewords
foreach ($payloads as $i => $p) {
    $ref = QrEncoder::encode($p); // matrix only; we need codewords -> add debug method? Instead reimplement here minimal using reflection is complex.
}
