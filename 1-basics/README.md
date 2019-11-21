# 基础篇

### 1. string(字符串)

> Redis的字符串是动态字符串，采用预分配冗余空间的方式来减少内存的频繁分配。
> 内部为当前字符串实际分配的空间 capacity 一般要高于实际字符串长度 len。
> 当字符串长度小于 1M 时，扩容都是加倍现有的空间，
> 如果超过 1M，扩容时一次只会多扩 1M 的空间。
> 需要注意的是字符串最大长度为 512M。

### 2. list(列表)

> list 的插入和删除操作非常快，时间复杂度为 O(1)，
> 但是索引定位很慢，时间复杂度为 O(n)，
> 列表弹出最后一个元素之后，该数据结构自动被删除，内存被回收


### 容器的通用规则
1. **create if not exists**，
如果容器不存在，那就创建一个，再进行操作。比如 rpush 操作刚开始是没有列表的，Redis 就会自动创建一个，然后再 rpush 进去新元素。
2. **drop if no elements**，
如果容器里元素没有了，那么立即删除元素，释放内存。这意味着 lpop 操作到最后一个元素，列表就消失了。