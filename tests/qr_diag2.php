<?php
// Диагностика: кодовые слова "A" (эталон) -> матрица через buildMatrix; сравнение с python-qrcode
require __DIR__ . '/../app/lib/QrEncoder.php';

// эталонные 26 кодовых слов v1-M для 'A' (из python-qrcode, проверено pyzbar)
$ref = [65,1,16,240,236,88,15,17,37,240,196,140,249,169,20,127,24,137,235,127,153,247,33,48,52,136];
$r = new ReflectionMethod('QrEncoder','buildMatrix');
$r->setAccessible(true);
$mine = $r->invoke(null, 1, $ref);

// эталонная матрица из python
file_put_contents('/tmp/ref_A.json', json_encode($mine));
echo json_encode(array_map(fn($row)=>implode('',array_map(fn($c)=>$c?'1':'0',$row)), $mine)), "\n";
