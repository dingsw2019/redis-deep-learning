<?php
require_once ("../RedisClient.php");
require_once ("../predis-1.1/autoload.php");
use Predis\Command\ScriptCommand;

$conn = RedisClient::getConn();

//发送消息,业务处理完成后,写入消息队列
function push($message){
    global $conn;
    //do something
    $conn->rpush('queue:',[$message]);
}

//消费消息,取出消息,做业务处理
function pop(){
    global $conn;
    $message = $conn->lpop('queue:');
    if ($message) {
        //do something
    }
}

//阻塞消费消息,队列空时会阻塞等待,
//有消息进入会立刻弹出处理
function bpop(){
    global $conn;
    $message = $conn->blpop('queue:',0);
    if ($message) {
        //do something
        echo sprintf("消息[%s] 从[%s]队列中弹出",$message[1],$message[0]) . PHP_EOL;
    }
}

//while(1){
//    bpop();
//}

//延时队列
function delay(string $message,int $timeout=5){
    global $conn;
    $time = microtime(true) + $timeout;
    return $conn->zadd('delay:',[$message=>$time]);
}

//顺序消费延迟队列中的消息
function loop(){
    global $conn;
    while(1){
        //从延迟队列获取一条最近时间的消息
        $message_data = $conn->zrangebyscore('delay:',0,microtime(true),['withscores'=>true,'limit'=>[0,1]]);
        //延迟队列中无消息
        if (!$message_data) {
            sleep(1);
            continue;
        }
        //提取消息数据
        $message = key($message_data);
        //从延迟队列中删除刚获取的消息
        $success = $conn->zrem('delay:',$message);
        //多线程或多进程争抢消息时,
        //根据zrem返回值判断,当前实例有没有抢到任务
        //抢到任务,做业务处理后返回
        if($success){
            //do something
            echo sprintf("消费的消息,[%s]",$message) . PHP_EOL;
        }
    }
}

//delay('mmm1');
//delay('mmm2');
//delay('mmm3');

//loop();


/**
 * 从消息队列中搜索符合条件的最近n条消息
 * 返回消息内容并从消息队列中删除
 * @param string queue_key 消息队列的key
 * @param int $min      搜索时间戳开始时间
 * @param int $max      搜索时间戳结束时间
 * @param int $offset   要跳过的消息数量
 * @param int $limit    获取消息数量
 * @return array 删除成功的消息的消息内容
 * @
 */
class getAndDeleteRecentMessageScript extends ScriptCommand {

    public function getKeysCount()
    {
        return 1;
    }

    public function getScript()
    {
        return <<<LUA
-- 消息队列的redisKey
local queue = KEYS[1]
-- 搜索范围的最大/最小值,偏移量和取值数量
local min, max, offset, count = ARGV[1], ARGV[2], ARGV[3], ARGV[4] 
local message = false
local messages = {}
local queue_value = {}
local insert = table.insert
-- 获取最近n条消息并删除消息
queue_value = redis.call("ZRANGEBYSCORE",queue,min,max,"LIMIT",offset,count)
for idx, message in pairs(queue_value) do
    if redis.call("ZREM",queue,message) then
        insert(messages, idx, message)
    end
end
-- 返回删除成功的消息
return messages
LUA;

    }
}
$conn->getProfile()->defineCommand('get_and_delete_recent_message','getAndDeleteRecentMessageScript');

/********* 测试从延迟队列中弹出消息(lua版本) start **********/
////向延迟队列中写入10条数据
//foreach(range(1,10) as $msg_id){
//    $success = delay("msg{$msg_id}");
//    if($success){
//        echo "写入消息[msg{$msg_id}], 成功" . PHP_EOL;
//    }
//}
//
////删除最近写入的 2条
//$ret = $conn->get_and_delete_recent_message('delay:',0,microtime(true),3,2);
//
//var_export($ret);
/********* 测试从延迟队列中弹出消息(lua版本) end **********/

$arr = [
    1001 => [
        'weight' => '0.31',
    ],
    1002 => [
        'weight' => '0.21',
    ],
    1003 => [
        'weight' => '0.51',
    ],
    1004 => [
        'weight' => '0.11',
    ]
];

usort($arr,function($a,$b){
    var_dump($b['weight'] - $a['weight']);
    return ($b['weight'] - $a['weight']) > 0 ? 1 : -1;
});

var_export($arr);


//$a = array(3, 2, 5, 6, 1);
//
//usort($a, function($a, $b){
//    if ($a == $b) {
//        return 0;
//    }
////    return ($a < $b) ? -1 : 1;
//    echo "{$b} - {$a} = " . ($b-$a) . PHP_EOL;
//    return $b - $a;
//});
//
//var_dump($a);
