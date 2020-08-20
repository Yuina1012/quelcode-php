<?php
$array = explode(',', $_GET['array']);

// 修正はここから
$lenght = count($array);
for ($i = 0; $i < $lenght - 1; $i++) {
    for ($j = 1; $j < $lenght - $i; $j++) {
        if ($array [$j - 1]   >  $array [ $j ]){
            //退避
            $tmp = $array[ $j ];
            //入れる
            $array[ $j ] = $array[ $j -1 ];
            //退避してたのを入れる
            $array[$j - 1] = $tmp;
        }
    }  
}
// 修正はここまで

echo "<pre>";
print_r($array);
echo "</pre>";
