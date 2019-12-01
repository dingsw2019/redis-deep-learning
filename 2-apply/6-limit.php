<?php
/**
 * 简单限流
 */

require_once ("../RedisClient.php");


$conn = RedisClient::getConn();

/**
 * 限制用户操作次数
 * @param string $user_id    用户
 * @param string $action_key 行为
 * @param int    $period     持续时长
 * @param int    $max_count  限制操作次数
 * @date 2019/12/1 20:01
 * @author dingsw
 * @return bool
 * @throws Exception
 */
function is_action_allowed($user_id,$action_key,$period,$max_count){

    global $conn;
    //存储用户行为的键
    $key = sprintf("hist:%s:%s",$user_id,$action_key);
    //触发行为的时间
    $now_ts = intval(microtime(true) * 1000);

    //记录行为,清理过期行为
    $pipe = $conn->pipeline(['atomic'=>true]);
    $pipe->zadd($key,[$now_ts=>$now_ts]);
    $pipe->zremrangebyscore($key,0,$now_ts-$period*1000);
    $pipe->zcard($key);
    $pipe->expire($key,$period+1);

    list($current_count) = array_slice($pipe->execute(),2,1);
    //判断行为数量是否超过限制
    return $current_count <= $max_count;
}


foreach(range(1,20) as $i){

    $is_allowed = is_action_allowed("dingsw","reply",60,5);
    echo "{$i} , ". ($is_allowed ? 'allow' : 'not allow') . PHP_EOL;
}
