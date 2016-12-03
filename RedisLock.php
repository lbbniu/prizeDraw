<?php
//来源：http://www.milan100.com/article/show/1680

/**
*在redis上实现分布式锁
*/
class RedisLock {
    private $redisString;
    private $lockedNames = [];
    public function __construct($param = NULL) {
        $this->redisString = RedisFactory::get($param)->string;
    }
     
    /**
    * 加锁
    * @param  [type]  $name           锁的标识名
    * @param  integer $timeout        循环获取锁的等待超时时间，在此时间内会一直尝试获取锁直到超时，为0表示失败后直接返回不等待
    * @param  integer $expire         当前锁的最大生存时间(秒)，必须大于0，如果超过生存时间锁仍未被释放，则系统会自动强制释放
    * @param  integer $waitIntervalUs 获取锁失败后挂起再试的时间间隔(微秒)
    * @return [type]                  [description]
    */
    public function lock($name, $timeout = 0, $expire = 15, $waitIntervalUs = 100000) {
        if ($name == null) return false;
         
        //取得当前时间
        $now = time();
        //获取锁失败时的等待超时时刻
        $timeoutAt = $now + $timeout;
        //锁的最大生存时刻
        $expireAt = $now + $expire;
         
        $redisKey = "Lock:{$name}";
        while (true) {
            //将rediskey的最大生存时刻存到redis里，过了这个时刻该锁会被自动释放
            $result = $this->redisString->setnx($redisKey, $expireAt);
             
            if ($result != false) {
                //设置key的失效时间
                $this->redisString->expire($redisKey, $expireAt);
                //将锁标志放到lockedNames数组里
                $this->lockedNames[$name] = $expireAt;
                return true;
            }
             
            //以秒为单位，返回给定key的剩余生存时间
            $ttl = $this->redisString->ttl($redisKey);
             
            //ttl小于0 表示key上没有设置生存时间（key是不会不存在的，因为前面setnx会自动创建）
            //如果出现这种状况，那就是进程的某个实例setnx成功后 crash 导致紧跟着的expire没有被调用
            //这时可以直接设置expire并把锁纳为己用
            if ($ttl < 0) {
                $this->redisString->set($redisKey, $expireAt);
                $this->lockedNames[$name] = $expireAt;
                return true;
            }
             
            /*****循环请求锁部分*****/
            //如果没设置锁失败的等待时间 或者 已超过最大等待时间了，那就退出
            if ($timeout <= 0 || $timeoutAt < microtime(true)) break;
             
            //隔 $waitIntervalUs 后继续 请求
            usleep($waitIntervalUs);
         
        }
         
        return false;
    }
    /**
    * 解锁
    * @param  [type] $name [description]
    * @return [type]       [description]
    */
    public function unlock($name) {
        //先判断是否存在此锁
        if ($this->isLocking($name)) {
            //删除锁
            if ($this->redisString->deleteKey("Lock:$name")) {
                //清掉lockedNames里的锁标志
                unset($this->lockedNames[$name]);
                return true;
            }
        }
        return false;
    }
    /**
    * 释放当前所有获得的锁
    * @return [type] [description]
    */
    public function unlockAll() {
        //此标志是用来标志是否释放所有锁成功
        $allSuccess = true;
        foreach ($this->lockedNames as $name => $expireAt) {
            if (false === $this->unlock($name)) {
                $allSuccess = false;
            }
        }
        return $allSuccess;
    }
    /**
    * 给当前所增加指定生存时间，必须大于0
    * @param  [type] $name [description]
    * @return [type]       [description]
    */
    public function expire($name, $expire) {
        //先判断是否存在该锁
        if ($this->isLocking($name)) {
            //所指定的生存时间必须大于0
            $expire = max($expire, 1);
            //增加锁生存时间
            if ($this->redisString->expire("Lock:$name", $expire)) {
                return true;
            }
        }
        return false;
    }
    /**
    * 判断当前是否拥有指定名字的所
    * @param  [type]  $name [description]
    * @return boolean       [description]
    */
    public function isLocking($name) {
        //先看lonkedName[$name]是否存在该锁标志名
        if (isset($this->lockedNames[$name])) {
            //从redis返回该锁的生存时间
            return (string)$this->lockedNames[$name] = (string)$this->redisString->get("Lock:$name");
        }
         
        return false;
    }
}






/**
 * 任务队列
 */
class RedisQueue {
 
    private $_redis;
 
    public function __construct($param = null) {
        $this->_redis = RedisFactory::get($param);
    }
 
    /**
     * 入队一个 Task
     * @param [type] $name 队列名称
     * @param [type] $id 任务id（或者其数组）
     * @param integer $timeout 入队超时时间(秒)
     * @param integer $afterInterval [description]
     * @return [type] [description]
     */
    public function enqueue($name, $id, $timeout = 10, $afterInterval = 0) {
//合法性检测
        if (empty($name) || empty($id) || $timeout <= 0)
            return false;
//加锁
        if (!$this->_redis->lock->lock("Queue:{$name}", $timeout)) {
            Logger::get('queue')->error("enqueue faild becouse of lock failure: name = $name, id = $id");
            return false;
        }
//入队时以当前时间戳作为 score
        $score = microtime(true) + $afterInterval;
//入队
        foreach ((array) $id as $item) {
//先判断下是否已经存在该id了
            if (false === $this->_redis->zset->getScore("Queue:$name", $item)) {
                $this->_redis->zset->add("Queue:$name", $score, $item);
            }
        }
//解锁
        $this->_redis->lock->unlock("Queue:$name");
        return true;
    }
 
    /**
     * 出队一个Task，需要指定$id 和 $score
     * 如果$score 与队列中的匹配则出队，否则认为该Task已被重新入队过，当前操作按失败处理
     *
     * @param [type] $name 队列名称
     * @param [type] $id 任务标识
     * @param [type] $score 任务对应score，从队列中获取任务时会返回一个score，只有$score和队列中的值匹配时Task才会被出队
     * @param integer $timeout 超时时间(秒)
     * @return [type] Task是否成功，返回false可能是redis操作失败，也有可能是$score与队列中的值不匹配（这表示该Task自从获取到本地之后被其他线程入队过）
     */
    public function dequeue($name, $id, $score, $timeout = 10) {
//合法性检测
        if (empty($name) || empty($id) || empty($score))
            return false;
//加锁
        if (!$this->_redis->lock->lock("Queue:$name", $timeout)) {
            Logger:get('queue')->error("dequeue faild becouse of lock lailure:name=$name, id = $id");
            return false;
        }
//出队
//先取出redis的score
        $serverScore = $this->_redis->zset->getScore("Queue:$name", $id);
        $result = false;
//先判断传进来的score和redis的score是否是一样
        if ($serverScore == $score) {
//删掉该$id
            $result = (float) $this->_redis->zset->delete("Queue:$name", $id);
            if ($result == false) {
                Logger::get('queue')->error("dequeue faild because of redis delete failure: name =$name, id = $id");
            }
        }
//解锁
        $this->_redis->lock->unlock("Queue:$name");
        return $result;
    }
 
    /**
     * 获取队列顶部若干个Task 并将其出队
     * @param [type] $name 队列名称
     * @param integer $count 数量
     * @param integer $timeout 超时时间
     * @return [type] 返回数组[0=>['id'=> , 'score'=> ], 1=>['id'=> , 'score'=> ], 2=>['id'=> , 'score'=> ]]
     */
    public function pop($name, $count = 1, $timeout = 10) {
//合法性检测
        if (empty($name) || $count <= 0)
            return [];
//加锁
        if (!$this->_redis->lock->lock("Queue:$name")) {
            Logger::get('queue')->error("pop faild because of pop failure: name = $name, count = $count");
            return false;
        }
//取出若干的Task
        $result = [];
        $array = $this->_redis->zset->getByScore("Queue:$name", false, microtime(true), true, false, [0, $count]);
//将其放在$result数组里 并 删除掉redis对应的id
        foreach ($array as $id => $score) {
            $result[] = ['id' => $id, 'score' => $score];
            $this->_redis->zset->delete("Queue:$name", $id);
        }
//解锁
        $this->_redis->lock->unlock("Queue:$name");
        return $count == 1 ? (empty($result) ? false : $result[0]) : $result;
    }
 
    /**
     * 获取队列顶部的若干个Task
     * @param [type] $name 队列名称
     * @param integer $count 数量
     * @return [type] 返回数组[0=>['id'=> , 'score'=> ], 1=>['id'=> , 'score'=> ], 2=>['id'=> , 'score'=> ]]
     */
    public function top($name, $count = 1) {
//合法性检测
        if (empty($name) || $count < 1)
            return [];
//取错若干个Task
        $result = [];
        $array = $this->_redis->zset->getByScore("Queue:$name", false, microtime(true), true, false, [0, $count]);
//将Task存放在数组里
        foreach ($array as $id => $score) {
            $result[] = ['id' => $id, 'score' => $score];
        }
//返回数组
        return $count == 1 ? (empty($result) ? false : $result[0]) : $result;
    }
 
}
