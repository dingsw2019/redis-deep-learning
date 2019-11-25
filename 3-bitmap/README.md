
### 位图

#### bitfield
- 在redis中，每一个命令都都被事先标记为是写命令还是读命令。
  而BITFIELD命令却被标记为写，导致在有主从redis时，从redis被设置为readonly时，无法执行BITFIELD命令，无论子命令是GET还是SET。
- bitfield注意事项，已二进制存储,但是设置和显示是十进制
- [官方文档-英文版](https://redis.io/commands/bitfield)
- [官方文档-中文版（腾讯云出品）](https://cloud.tencent.com/developer/section/1374165)

```
//给-n键从0开始的4个位设置一个有符号的-5值
//-n的存储的二进制为,1011
127.0.0.1:6379> bitfield -n set i4 0 -5
1) (integer) 0

//获取-n键从0开始的4个位的有符号值,已十进制值返回
127.0.0.1:6379> bitfield -n get i4 0
1) (integer) -5

//获取-n键从0开始的4个位的无符号值,已十进制值返回
127.0.0.1:6379> bitfield -n get u4 0
1) (integer) 11

//把n键从0开始的4个位的二进制值转换成十进制值,1011(二进制)转换成-5(十进制)
//再把十进制值(-5)加2,得到的结果(-3)转换成二进制再存回n键,-3转换成1101(二进制)
127.0.0.1:6379> bitfield -n incrby i4 0 2
1) (integer) -3

//把n键最后一个位的值(1)再加1,得到2,转换成二进制为10
//但是i1规定了只修改1个位,所以从右截取了0,存储到最后一个位
//n的进制值为,1100
127.0.0.1:6379> bitfield -n incrby i1 3 1
1) (integer) 0

/***溢出控制(overflow)***/
//设置m键从0开始8位的有符号值为120,二进制为,1111000
127.0.0.1:6379> BITFIELD m SET i8 0 120
1) (integer) 120
//饱和控制,有符号最大值127,最小值-128
//当m的值要再加10时,会超过最大值限制,所以饱和控制
//将结果控制为最大值127。相反,如果小于最小值也会控制为-128
127.0.0.1:6379> BITFIELD m OVERFLOW SAT INCRBY i8 0 10
1) (integer) 127

//失败控制,当前m值为127,再加1就溢出了,不执行操作,已报错返回
127.0.0.1:6379> BITFIELD m OVERFLOW FAIL INCRBY i8 0 1
1) (nil)

//回绕控制,默认的控制方式
//像一个环形,最大值与最小值连接,127+1 = -128
127.0.0.1:6379> BITFIELD m OVERFLOW WRAP INCRBY i8 0 1
1) (integer) -128
```
 
### 思考题

1、将完整的 hello 单词中 5 个字符都使用位操作设置一下。
```
127.0.0.1:6379> BITFIELD hello:bit SET u8 0 72
1) (integer) 0
127.0.0.1:6379> BITFIELD hello:bit SET u8 8 69
1) (integer) 0
127.0.0.1:6379> BITFIELD hello:bit SET u8 16 76
1) (integer) 0
127.0.0.1:6379> BITFIELD hello:bit SET u8 24 76
1) (integer) 0
127.0.0.1:6379> BITFIELD hello:bit SET u8 32 79
1) (integer) 0
127.0.0.1:6379> GET hello:bit
"HELLO"
```

2、bitfield 可以同时混合执行多个 set/get/incrby 子指令，请读者尝试完成。
```
127.0.0.1:6379> BITFIELD num SET u8 0 10 INCRBY u8 0 2 GET u8 0
1) (integer) 0
2) (integer) 12
3) (integer) 12
```