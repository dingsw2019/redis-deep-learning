<?php
/**
 * HyperLogLog学习笔记
 */
require_once ("../RedisClient.php");

/*

//命令实验,0误差
127.0.0.1:6379> pfadd home:page:uv user1
(integer) 1
127.0.0.1:6379> pfcount home:page:uv
(integer) 1
127.0.0.1:6379> pfadd home:page:uv user2 user3 user4
(integer) 1
127.0.0.1:6379> pfcount home:page:uv
(integer) 4

//pfcount,多个key时,先计算key的交集,再返回交集的数量
127.0.0.1:6379> pfadd uv1 user1 user2 user3 user4
(integer) 1
127.0.0.1:6379> pfadd uv2 user1 user5 user3 user0
(integer) 1
127.0.0.1:6379> pfcount uv1 uv2
(integer) 6

//pfmerge,多个key做交集,结果存入新key
127.0.0.1:6379> pfmerge uv:1:2 uv1 uv2
OK
127.0.0.1:6379> pfcount uv:1:2
(integer) 6

*/


$conn = RedisClient::getConn();

/**
 * 首次误差
 * @param int $loop 循环次数
 * @date 2019/11/26 7:47
 */
function test_hyper_log1(int $loop){
    global $conn;
    $key = "home:page:uv";
    for ($i=0;$i<$loop;$i++) {
        $uid = sprintf("user%d",$i);
        $conn->pfadd($key,[$uid]);
        $total = $conn->pfcount($key);
        if ($total != $i+1) {
            echo "total={$total},i=" . ($i+1) . PHP_EOL;
            break;
        }
    }
}

//当写入user100时,第一次出现错误,输出内容为 total=99,i=100
//[注释break]共写入1000个用户ID,查看redis实际写入用户数为1010
//test_hyper_log1(1000);

/**
 * 写入总量误差
 * @param int $loop 循环次数
 * @date 2019/11/26 8:05
 */
function test_hyper_log2(int $loop){
    global $conn;
    $key = "home:search:uv";
    for ($i=0;$i<$loop;$i++) {
        $uid = sprintf("user%d",$i);
        $conn->pfadd($key,[$uid]);
    }
    $total = $conn->pfcount($key);
    echo "loop={$loop},total={$total}" . PHP_EOL;
}

//loop=100000,total=99715,误差=0.285%
//重跑一次,结果无变化,说明有去重功能
//test_hyper_log2(100000);


/************* HyperLogLog 实现原理 *************/

//算低位0的个数
function low_zeros($value){
    foreach(range(1,32) as $i){
        if ($value>>$i<<$i != $value){
            break;
        }
    }

    return $i-1;
}

//通过随机数记录最大的低位零的个数
class BitKeeper {
    public $maxbits = 0;

    function random(){
        $value = mt_rand(0,2**32-1);
        $bits = low_zeros($value);
        if ($bits > $this->maxbits){
            $this->maxbits = $bits;
        }
    }
}

class Experiment {
    public $n;
    public $keeper;

    public function __construct($n)
    {
        $this->n = $n;
        $this->keeper = new BitKeeper();
    }

    //获取最大的低位零的个数
    function do(){
        foreach(range(0,$this->n) as $i){
            $this->keeper->random();
        }
    }

    //输出n值,log2(n),k值
    function debug(){
        echo $this->n ." ". sprintf("%.2f",log($this->n,2))
            ." ". $this->keeper->maxbits . PHP_EOL;
    }
}

//输出随机结果
//foreach(range(1000,100000,100) as $i){
//    $exp = new Experiment($i);
//    $exp->do();
//    $exp->debug();
//    if($i>37600){
//        break;
//    }
//}

//echo low_zeros(212606320);

class ExperimentWithBucket {
    public $n;
    public $k;
    public $keepers;

    public function __construct($n,$k=1024)
    {
        $this->n = $n;
        $this->k = $k;
        foreach(range(0,$k) as $i){
            $this->keepers[$i] = new BitKeeper();
        }
    }

    //获取最大的低位零的个数
    function do(){
        foreach(range(0,$this->n) as $i){
            $m = mt_rand(0,1<<32-1);
            $keeper = $this->keepers[(($m & 0xfff0000)>>16) % count($this->keepers)];
            $keeper->random($m);
        }
    }

    function estimate(){
        //平均数的倒数
        $sumbits_inverse = 0;
        foreach($this->keepers as $keeper){
            if($keeper->maxbits){
                $sumbits_inverse += 1.0 / $keeper->maxbits;
            }
        }
        //调和平均数
        $avgbits = $this->k / $sumbits_inverse;
        return 2**$avgbits*$this->k;
    }
}

//输出随机结果
foreach(range(100000,1000000,100000) as $i){
    $exp = new ExperimentWithBucket($i);
    $exp->do();
    $est = $exp->estimate();
    echo $i ." ".sprintf("%.2f",$est)." "
        . sprintf("%.2f",abs($est-$i)/$i)
        . PHP_EOL;
}