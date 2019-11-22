<?php
require_once ("../RedisClient.php");

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

