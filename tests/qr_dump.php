<?php
$src = file_get_contents(__DIR__ . '/../app/lib/QrEncoder.php');
$src = str_replace('class QrEncoder', 'class QrEncoderDump', $src);
$inject = <<<'CODE'
        if (getenv('QR_DUMP')) {
            $fh = fopen(getenv('QR_DUMP'), 'w');
            foreach ($codewords as $cw) fwrite($fh, $cw . "\n");
            for ($y = 0; $y < $size; $y++) fwrite($fh, implode('', array_map(fn($c) => $c ? '1' : '0', $fn[$y])) . "\n");
            exit(0);
        }
CODE;
$src = str_replace('        // Выбор лучшей маски', $inject . "        // Выбор лучшей маски", $src);
file_put_contents('/tmp/QrEncoderDump.php', $src);
echo "ok\n";
