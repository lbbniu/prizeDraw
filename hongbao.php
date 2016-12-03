<?php

//具体算法：（设置的总钱数，总人数，最大值，最小值要合理）

/** 
 * 求一个数的平方 
 * @param $n 
 */  
function sqr($n){  
    return $n*$n;  
}  
  
/** 
* 生产min和max之间的随机数，但是概率不是平均的，从min到max方向概率逐渐加大。 
* 先平方，然后产生一个平方值范围内的随机数，再开方，这样就产生了一种“膨胀”再“收缩”的效果。 
*/    
function xRandom($bonus_min,$bonus_max){  
    $sqr = intval(sqr($bonus_max-$bonus_min));  
    $rand_num = rand(0, ($sqr-1));  
    return intval(sqrt($rand_num));  
}  
  
  
 /** 
 *   
 * @param $bonus_total 红包总额 
 * @param $bonus_count 红包个数 
 * @param $bonus_max 每个小红包的最大额 
 * @param $bonus_min 每个小红包的最小额 
 * @return 存放生成的每个小红包的值的一维数组 
 */    
function getBonus($bonus_total, $bonus_count, $bonus_max, $bonus_min) {    
    $result = array();    
  
    $average = $bonus_total / $bonus_count;    
  
    $a = $average - $bonus_min;    
    $b = $bonus_max - $bonus_min;    
  
    //    
    //这样的随机数的概率实际改变了，产生大数的可能性要比产生小数的概率要小。    
    //这样就实现了大部分红包的值在平均数附近。大红包和小红包比较少。    
    $range1 = sqr($average - $bonus_min);    
    $range2 = sqr($bonus_max - $average);    
  
    for ($i = 0; $i < $bonus_count; $i++) {    
        //因为小红包的数量通常是要比大红包的数量要多的，因为这里的概率要调换过来。    
        //当随机数>平均值，则产生小红包    
        //当随机数<平均值，则产生大红包    
        if (rand($bonus_min, $bonus_max) > $average) {    
            // 在平均线上减钱    
            $temp = $bonus_min + xRandom($bonus_min, $average);    
            $result[$i] = $temp;    
            $bonus_total -= $temp;    
        } else {    
            // 在平均线上加钱    
            $temp = $bonus_max - xRandom($average, $bonus_max);    
            $result[$i] = $temp;    
            $bonus_total -= $temp;    
        }    
    }    
    // 如果还有余钱，则尝试加到小红包里，如果加不进去，则尝试下一个。    
    while ($bonus_total > 0) {    
        for ($i = 0; $i < $bonus_count; $i++) {    
            if ($bonus_total > 0 && $result[$i] < $bonus_max) {    
                $result[$i]++;    
                $bonus_total--;    
            }    
        }    
    }    
    // 如果钱是负数了，还得从已生成的小红包中抽取回来    
    while ($bonus_total < 0) {    
        for ($i = 0; $i < $bonus_count; $i++) {    
            if ($bonus_total < 0 && $result[$i] > $bonus_min) {    
                $result[$i]--;    
                $bonus_total++;    
            }    
        }    
    }    
    return $result;    
}  
$bonus_total = 200;  
$bonus_count = 100;  
$bonus_max = 10;//此算法要求设置的最大值要大于平均值  
$bonus_min = 1;  
$result_bonus = getBonus($bonus_total, $bonus_count, $bonus_max, $bonus_min);  
$total_money = 0;  
$arr = array();  
foreach ($result_bonus as $key => $value) {  
    $total_money += $value;  
    if(isset($arr[$value])){  
        $arr[$value] += 1;  
    }else{  
        $arr[$value] = 1;  
    }  
      
}  
//输出总钱数，查看是否与设置的总数相同  
echo $total_money;  
//输出所有随机红包值  
var_dump($result_bonus);  
//统计每个钱数的红包数量，检查是否接近正态分布  
ksort($arr);  
var_dump($arr);  