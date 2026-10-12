<?php
// Диагностика: кодовые слова НАШЕГО кодировщика (до наложения маски) + эталонные.
require __DIR__ . '/../app/lib/QrEncoder.php';

$payload = 'https://sebekin.ru/oks/object/12';
$len = strlen($payload);
$version = 3; $totalData = 44; $ecPerBlock = 26;

$bits = '0100' . str_pad(decbin($len), 8, '0', STR_PAD_LEFT);
for ($i = 0; $i < $len; $i++) $bits .= str_pad(decbin(ord($payload[$i])), 8, '0', STR_PAD_LEFT);
$bits .= '0000';
while (strlen($bits) % 8 !== 0) $bits .= '0';
$dataBytes = [];
foreach (str_split($bits, 8) as $b) $dataBytes[] = (int)bindec($b);
$pad = [0xEC, 0x11]; $pi = 0;
while (count($dataBytes) < $totalData) { $dataBytes[] = $pad[$pi++ % 2]; }

$rs = new ReflectionMethod('QrEncoder', 'reedSolomon'); $rs->setAccessible(true);
$ec = $rs->invoke(null, $dataBytes, $ecPerBlock);

$refData = array_map('intval', explode(',', trim(file_get_contents('/tmp/ref_data44.txt'))));
$refEc   = array_map('intval', explode(',', trim(file_get_contents('/tmp/ref_ec26.txt'))));

echo "ours data: " . implode(',', $dataBytes) . "\n";
echo "ref  data: " . implode(',', $refData) . "\n";
echo "DATA MATCH: " . ($dataBytes === $refData ? "YES" : "NO") . "\n";
echo "ours ec:   " . implode(',', $ec) . "\n";
echo "ref  ec:   " . implode(',', $refEc) . "\n";
echo "EC MATCH:   " . ($ec === $refEc ? "YES" : "NO") . "\n";
// позиции различий данных
if ($dataBytes !== $refData) {
    $diffs = [];
    for ($i = 0; $i < 44; $i++) if ($dataBytes[$i] !== $refData[$i]) $diffs[] = "$i:" . $dataBytes[$i] . "/" . $refData[$i];
    echo "data diffs: " . implode(' ', array_slice($diffs, 0, 15)) . "\n";
}
