<?php
/**
 * php几个常用的概率算法（抽奖、广告首选）
 * 来源：http://www.cnblogs.com/qhorse/p/4838296.html
 * 
 * 做网站类的有时会弄个活动什么的，来让用户参加，既吸引用户注册，又提高网站的用户活跃度。
 * 同时参加的用户会获得一定的奖品，有100%中奖的，也有按一定概率中奖的，大的比如中个ipad、
 * iphone5，小的中个Q币什么的。那么我们在程序里必然会设计到算法，即按照一定的概率让用户获
 * 得奖品。先来看两个概率算法函数。
 */


/**
 * 全概率计算
 *
 * @param array $p array('a'=>0.5,'b'=>0.2,'c'=>0.4)
 * @return string 返回上面数组的key
 */
function random($ps){
    static $arr = array();
    $key = md5(serialize($ps));
 
    if (!isset($arr[$key])) {
        $max = array_sum($ps);
        foreach ($ps as $k=>$v) {
            $v = $v / $max * 10000;
            for ($i=0; $i<$v; $i++) $arr[$key][] = $k;
        }
    }
    return $arr[$key][mt_rand(0,count($arr[$key])-1)];
} 