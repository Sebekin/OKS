<?php
// Диагностика 5: матрица ДО маски (эмуляция placement) и сравнение с независимым размещением на Python.
require __DIR__ . '/../app/lib/QrEncoder.php';

$ref = [65,1,16,240,236,88,15,17,37,240,196,140,249,169,20,127,24,137,235,127,153,247,33,48,52,136];

// Дублируем placement из buildMatrix, но возвращаем матрицу до маски + fn-карту
$size = 21; $version = 1;
$m  = array_fill(0,$size,array_fill(0,$size,false));
$fn = array_fill(0,$size,array_fill(0,$size,false));
foreach ([[0,0],[0,$size-7],[$size-7,0]] as [$oy,$ox]) {
    for ($y=0;$y<7;$y++) for ($x=0;$x<7;$x++) {
        $edge=$y===0||$y===6||$x===0||$x===6; $core=$y>=2&&$y<=4&&$x>=2&&$x<=4;
        $m[$oy+$y][$ox+$x]=$edge||$core; $fn[$oy+$y][$ox+$x]=true;
    }
    for ($i=-1;$i<=7;$i++) foreach ([[$oy-1,$ox+$i],[$oy+7,$ox+$i],[$oy+$i,$ox-1],[$oy+$i,$ox+7]] as [$yy,$xx]) {
        if ($yy>=0&&$yy<$size&&$xx>=0&&$xx<$size){$m[$yy][$xx]=false;$fn[$yy][$xx]=true;}
    }
}
for ($i=8;$i<$size-8;$i++){$m[6][$i]=$m[$i][6]=($i%2===0);$fn[6][$i]=$fn[$i][6]=true;}
for ($i=0;$i<=8;$i++){if($i!==6){$fn[8][$i]=true;$fn[$i][8]=true;}}
for ($i=0;$i<8;$i++){$fn[8][$size-1-$i]=true;$fn[$size-1-$i][8]=true;}
$m[$size-8][8]=true;$fn[$size-8][8]=true;

$bitStream='';
foreach ($ref as $cw) $bitStream.=str_pad(decbin($cw),8,'0',STR_PAD_LEFT);
$bi=0;$up=true;
for ($col=$size-1;$col>0;$col-=2){
    if($col===6)$col--;
    for($cnt=0;$cnt<$size;$cnt++){
        $row=$up?$size-1-$cnt:$cnt;
        for($c=0;$c<2;$c++){
            $cc=$col-$c;
            if(!$fn[$row][$cc]) $m[$row][$cc]=($bitStream[$bi++]==='1');
        }
        $up=!$up;
    }
}
file_put_contents('/tmp/premask.json', json_encode(['m'=>array_map(fn($r)=>array_map(fn($v)=>$v?1:0,$r),$m),'fn'=>array_map(fn($r)=>array_map(fn($v)=>$v?1:0,$r),$fn)]));
echo "dumped\n";
