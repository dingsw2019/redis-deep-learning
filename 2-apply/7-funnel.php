<?php
/**
 * 漏斗限流
 */

class Funnel {

    /**
     * @var int 漏斗容量
     */
    private $capacity;

    /**
     * @var int 漏嘴流水速率
     */
    private $leaking_rate;

    /**
     * @var int 漏斗剩余空间
     */
    private $left_quota;

    /**
     * @var string 上一次漏水时间
     */
    private $leaking_ts;

    public function __construct($capacity,$leaking_rate)
    {
        $this->capacity = $capacity; # 漏斗容量
        $this->leaking_rate = $leaking_rate; # 漏嘴流水速率
        $this->left_quota = $capacity; # 漏斗剩余空间
        $this->leaking_ts = microtime(true); # 上一次漏水时间
    }

    /**
     * 释放空间
     * @date 2019/12/2 7:50
     * @author dingsw
     */
    private function make_space(){
        $now_ts = microtime(true);
        $delta_ts = $now_ts - $this->leaking_ts; # 距离上一次漏水过去了多久
        $delta_quota = $delta_ts * $this->leaking_rate; # 可释放空间
        if ($delta_quota < 1) { # 空间少,等下次释放
            return ;
        }
        $this->left_quota += $delta_quota; # 增加剩余空间
        $this->leaking_ts = $now_ts; # 记录漏水时间
        if ($this->left_quota > $this->capacity) { # 剩余空间不得高于容量
            $this->left_quota = $this->capacity;
        }
    }

    /**
     * 限流入口
     * @param int $quota 容量
     * @date 2019/12/2 8:02
     * @author dingsw
     * @return bool
     */
    public function watering($quota){
        $this->make_space();
        if ($this->left_quota >= $quota) {
            $this->left_quota -= $quota;
            return true;
        }
        return false;
    }
}

$funnels = [];
function is_action_allowed($user_id,$action_key,$capacity,$leaking_rate){
    global $funnels;
    $key = sprintf("%s:%s",$user_id,$action_key);
    $funnel = $funnels[$key];
    if (!$funnel) {
        $funnel = new Funnel($capacity,$leaking_rate);
        $funnels[$key] = $funnel;
    }
    return $funnel->watering(1);
}

foreach(range(1,20) as $i){
    $allow = is_action_allowed("dingsw","reply",15,0.5);
    echo "{$i} , ". ($allow ? 'allow' : 'not allow') . PHP_EOL;
}