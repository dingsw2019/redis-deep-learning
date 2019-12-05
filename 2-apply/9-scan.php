<?php

require_once ("../RedisClient.php");

$conn = RedisClient::getConn();


/**
 * 随机添加参数
 * @param string $prefix 键前缀
 * @param int    $size   添加键数量
 * @return array
 * @throws Exception
 * @author dingsw
 * @date 2019/12/5 8:04
 */
function add(string $prefix,int $size){

    global $conn;

    $pipe = $conn->pipeline();

    for($i=0;$i<$size;$i++){
        $random = uniqid();
        $key = $prefix . $random;
        $pipe->set($key,$random);
    }

    return $pipe->execute();
}


add('key',200);

/******
count默认为10，搜索10个槽位
127.0.0.1:6379> scan 0 match key*
1) "48"
2)  1) "key5de84a0083554"
2) "key5de84a0083578"
3) "key5de84a0083571"
4) "key5de84a008369e"
5) "key5de84a00835a7"
6) "key5de84a00836da"
7) "key5de84a00834e9"
8) "key5de84a00836c9"
9) "key5de84a00835be"
10) "key5de84a0083626"
相同条件返回数据一致
127.0.0.1:6379> scan 0 match key*
1) "48"
2)  1) "key5de84a0083554"
2) "key5de84a0083578"
3) "key5de84a0083571"
4) "key5de84a008369e"
5) "key5de84a00835a7"
6) "key5de84a00836da"
7) "key5de84a00834e9"
8) "key5de84a00836c9"
9) "key5de84a00835be"
10) "key5de84a0083626"

移动游标,再次匹配10个槽位
127.0.0.1:6379> scan 48 match key*
1) "56"
2)  1) "key5de84a008356c"
2) "key5de84a008364d"
3) "key5de84a0083540"
4) "key5de84a008367e"
5) "key5de84a00835c3"
6) "key5de84a008351c"
7) "key5de84a0083694"
8) "key5de84a00835d3"
9) "key5de84a0083608"
10) "key5de84a0083617"

搜索100个槽位,得到了100条数据
127.0.0.1:6379> scan 56 match key* count 100
1) "133"
2)   1) "key5de84a00834de"
2) "key5de84a008364b"
3) "key5de84a0083551"
4) "key5de84a0083659"
5) "key5de84a0083620"
6) "key5de84a00835eb"
7) "key5de84a00836e6"
8) "key5de84a00835c5"
9) "key5de84a0083685"
10) "key5de84a00835b1"
11) "key5de84a0083687"
12) "key5de84a008371c"
...

 */