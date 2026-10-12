<?php
// Диагностика 6: матрица placement ДО маски из РЕАЛЬНОГО кода buildMatrix (перехват через subclass-эмуляцию)
require __DIR__ . '/../app/lib/QrEncoder.php';
$ref = [65,1,16,240,236,88,15,17,37,240,196,140,249,169,20,127,24,137,235,127,153,247,33,48,52,136];

// Трюк: временно сделаю penalty() возвращающим так, чтобы выбралась маска 2? Нет.
// Проще: продублирую ТОЧНУЮ логику buildMatrix placement (копия из файла) и дам premask+fn.
// Но копия уже проверена в diag5. Вместо этого — вырежем финальную матрицу и де-маскируем маской 2,
// затем сравним с эталонной ref после де-маскировки той же маской 2: различия покажут характер ошибки.
$r = new ReflectionMethod('QrEncoder','buildMatrix'); $r->setAccessible(true);
$mine = $r->invoke(null, 1, $ref); // финальная (mask chosen by my penalty)
file_put_contents('/tmp/final_mine.json', json_encode(array_map(fn($row)=>array_map(fn($c)=>$c?1:0,$row),$mine)));
echo "ok\n";
