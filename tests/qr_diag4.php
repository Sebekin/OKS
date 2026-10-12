<?php
require __DIR__ . '/../app/lib/QrEncoder.php';
$ref = [65,1,16,240,236,88,15,17,37,240,196,140,249,169,20,127,24,137,235,127,153,247,33,48,52,136];

// 1) Поток кодирования из encode(): перехватим buildMatrix через subclass? Проще — продублируем логику:
$text='A'; $len=strlen($text); $totalData=16;
$bits='0100'.str_pad(decbin($len),8,'0',STR_PAD_LEFT);
for($i=0;$i<$len;$i++)$bits.=str_pad(decbin(ord($text[$i])),8,'0',STR_PAD_LEFT);
$bits.='0000'; while(strlen($bits)%8)$bits.='0';
$bytes=[]; foreach(str_split($bits,8) as $b)$bytes[]=(int)bindec($b);
$pad=[0xEC,0x11];$pi=0;while(count($bytes)<$totalData){$bytes[]=$pad[$pi++%2];}
echo "data bytes: ",implode(',',$bytes),"\n";
$rs=new ReflectionMethod('QrEncoder','reedSolomon');$rs->setAccessible(true);
$ec=$rs->invoke(null,$bytes,10);
echo "my ecc: ",implode(',',$ec),"\n";
echo "ref ecc:",implode(',',array_slice($ref,16)),"\n";

// 2) Размещение: соберу matrix до маски с той же fn-картой, сравню с эталоном после де-маскировки маской 2
$final=array_merge($bytes,$ec);
file_put_contents('/tmp/final_cw.json',json_encode($final));
