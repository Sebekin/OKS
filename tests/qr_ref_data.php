<?php
// Эталонные кодовые слова (70) для 'https://sebekin.ru/oks/object/12', v3-M, маска 4,
// извлечены независимо из матрицы python-qrcode (tests/qr_full_compare.py).
$ref = json_decode(file_get_contents('/tmp/ref_cw_final.json'), true);
file_put_contents('/tmp/ref_data44.txt', implode(',', array_slice($ref, 0, 44)));
file_put_contents('/tmp/ref_ec26.txt', implode(',', array_slice($ref, 44)));
echo "data: " . implode(',', array_slice($ref, 0, 44)) . "\n";
echo "ec:   " . implode(',', array_slice($ref, 44)) . "\n";
