<?php
require __DIR__ . '/../app/lib/QrEncoder.php';
$ref = [65,1,16,240,236,88,15,17,37,240,196,140,249,169,20,127,24,137,235,127,153,247,33,48,52,136];

// шаг 1: матрица ДО маскирования (rebuild internals) — используем reflection для placement без mask? 
// Проще: вызову buildMatrix и сравню с эталоном по маске 2.
$r = new ReflectionMethod('QrEncoder','buildMatrix'); $r->setAccessible(true);
$mine = $r->invoke(null, 1, $ref);

// мой drawFormat: fmt int для mask 2
$rf = new ReflectionMethod('QrEncoder','drawFormat'); $rf->setAccessible(true);
$m2 = array_fill(0,21,array_fill(0,21,false));
$rf->invokeArgs(null,[&$m2,21,2]);
$pos1=[[8,0],[8,1],[8,2],[8,3],[8,4],[8,5],[8,7],[8,8],[7,8],[5,8],[4,8],[3,8],[2,8],[1,8],[0,8]];
$v=0; foreach($pos1 as $i=>[$y,$x]) if($m2[$y][$x]) $v|=1<<$i;
echo "my fmt raw bits int: ", decbin($v), "\n";
echo "expected for M/mask2: ", decbin((0b000001010110100)^0b101010000010010), "\n"; // 0x5412 xor data(0<<3|2)=2 -> fmt=(2<<10|rem)^0x5412
