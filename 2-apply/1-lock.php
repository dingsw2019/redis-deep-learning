<?php
/**
 * 分布式锁,
 * 主要考察使用者对原子性的理解
 * 原子性可以保证程序从异常中恢复后,
 * redis中的数据是正确的，程序依然正常运行
 * 3个请求锁的演变版本,1个释放锁的lua功能
 */
require_once ("../RedisClient.php");

require_once ("../predis-1.1/autoload.php");
use Predis\Command\ScriptCommand;

$conn = RedisClient::getConn();


/**
 * 基础版,完成锁的请求与释放,但是有严重的问题.
 * 如果请求锁完成后,宕机了,就成死锁了.再也不能请求到锁了
 * @return null
 */
function base () {
    global $conn;
    $unique = uniqid();
    //请求锁
    $lock = $conn->setnx('lock',$unique);
    if(!$lock){
        return null;
    }

    //do something...

    //释放锁
    $conn->del('lock');
}

/**
 * 过期时间版,通过给锁添加过期时间,
 * 防止异常情况出现一段时间后,也可正常工作
 * 问题：如果在请求锁完成之后,添加锁过期时间之前宕机怎么办?
 * @return null
 */
function expire () {
    global $conn;
    $unique = uniqid();
    //请求锁
    $lock = $conn->setnx('lock',$unique);
    if(!$lock){
        return null;
    }
    //给已请求到的锁添加过期时间
    $conn->expire('lock',5);

    //do something

    //释放锁
    $conn->del('lock');
}

/**
 * 设置锁和添加过期时间放在一个命令中,要成功一起成功
 * @return null
 */
function perfect () {
    global $conn;
    $unique = uniqid();
    //请求锁并添加过期时间
    $lock = $conn->set('lock',$unique,'ex',60,'nx');
    if(!$lock){
        return null;
    }

    //do something

    //释放锁
    $conn->del('lock');
}


/**
 * lua版释放锁, 把比对锁值和删除锁组合成原子操作
 * @param string $lock_key 锁的redisKey
 * @param string $lock_value 锁的value
 * @link https://redis.io/commands/set
 * Class releaseLockScript
 */
class releaseLockScript extends ScriptCommand {

    public function getScript()
    {
        return <<<LUA
if redis.call("get",ARGV[1]) == ARGV[2] then
    return redis.call("del",ARGV[1])
else
    return 0
end
LUA;
    }
}
//定义方法名,通过releaseLock调用
$conn->getProfile()->defineCommand('releaseLock','releaseLockScript');
//例子 , 调用释放锁
//var_export($conn->releaseLock('lock','123123'));
