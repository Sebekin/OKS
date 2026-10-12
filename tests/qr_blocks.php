<?php
require '/tmp/QrEncoderDump.php'; // класс QrEncoderDump с инъекцией дампа
// но инъекция делает exit; вместо этого воспроизведём логику здесь:
$payload = 'https://sebekin.ru/oks/object/12';
$len = strlen($payload);
$totalData = 44; $ecPerBlock = 26; $n1 = 1; $l1 = 44; $n2 = 0; $l2 = 0; // v3 M
$bits = '0100' . str_pad(decbin($len), 8, '0', STR_PAD_LEFT);
for ($i = 0; $i < $len; $i++) $bits .= str_pad(decbin(ord($payload[$i])), 8, '0', STR_PAD_LEFT);
$bits .= '0000';
while (strlen($bits) % 8 !== 0) $bits .= '0';
$bytes = [];
foreach (str_split($bits, 8) as $b) $bytes[] = (int)bindec($b);
$pad = [0xEC, 0x11]; $pi = 0;
while (count($bytes) < $totalData) { $bytes[] = $pad[$pi++ % 2]; }
echo "data bytes (" . count($bytes) . "):\n" . implode(',', $bytes) . "\n";
// реверс-зигзаг из эталона с нашей fn-картой
$lines = file('/tmp/mine_v29.txt', FILE_IGNORE_NEW_LINES);
$cwMine = []; $fnmap = [];
foreach ($lines as $ln) {
    if (preg_match('/^[01]{29}$/', $ln)) $fnmap[] = str_split($ln);
    elseif ($ln !== '' && ctype_digit($ln)) $cwMine[] = (int)$ln;
}
$n = 29;
$m = json_decode(file_get_contents('/tmp/ref_matrix.json'));
// зигзаг в обратную сторону: читаем кодовые слова эталона
$mask = 6;
function maskbit($mask,$y,$x){
    if($mask==0)return ($y+$x)%2==0; if($mask==1)return $y%2==0; if($mask==2)return $x%3==0;
    if($mask==3)return ($y+$x)%3==0; if($mask==4)return (intdiv($y,2)+intdiv($x,3))%2==0;
    if($mask==5)return (($y*$x)%2)+(($y*$x)%3)==0; if($mask==6)return ((($y*$x)%2)+(($y*$x)%3))%2==0;
    return ((($y+$x)%2)+(($y*$x)%3))%2==0;
}
$bs=''; $up=true;
for ($col=$n-1;$col>0;$col-=2){ if($col===6)$col--; for($cnt=0;$cnt<$n;$cnt++){ $row=$up?$n-1-$cnt:$cnt;
  for($c=0;$c<2;$c++){ $cc=$col-$c; if($fnmap[$row][$cc]=='0'){ $v=((int)$m[$row][$cc]==1)^maskbit($mask,$row,$cc);$bs.=$v?'1':'0'; } }
  $up=!$up; } }
$ref=[]; foreach(str_split(substr($bs,0,560),8) as $b) $ref[]=bindec($b);
echo "ref codewords (".count($ref)."):\n".implode(',',$ref)."\n";
echo "mine first 44: ".implode(',',array_slice($cwMine,0,44))."\n";
