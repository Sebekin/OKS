<?php
// Точечная сверка: наш RS-кодировщик на эталонных данных v3-M.
require __DIR__ . '/../app/lib/QrEncoder.php';

$refData = array_map('intval', explode(',', trim(file_get_contents('/tmp/ref_data44.txt'))));
$refEc   = array_map('intval', explode(',', trim(file_get_contents('/tmp/ref_ec26.txt'))));

$rs = new ReflectionMethod('QrEncoder', 'reedSolomon'); $rs->setAccessible(true);
$mine = $rs->invoke(null, $refData, 26);

echo "mine: " . implode(',', $mine) . "\n";
echo "ref : " . implode(',', $refEc) . "\n";
echo ($mine === $refEc ? "RS MATCH OK\n" : "RS MISMATCH\n");
