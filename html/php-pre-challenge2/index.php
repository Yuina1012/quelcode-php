<?php
$array = explode(',', $_GET['array']);

// 修正はここから

 
for ($i = 0; $i < count($array); $i++) {
for ($n=1; $n<count($array);$n++){
  if($array[$n-1] > $array[$n]){
    //待避
    $tmp=$array[$n];
    //入れる
    $array[$n]=$array[$n-1];
    //待避してたのを入れる
    $array[$n-1]=$tmp;
  }
}
}
// 修正はここまで

echo "<pre>";
print_r($array);
echo "</pre>";
