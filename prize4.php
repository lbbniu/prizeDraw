<?php
/**
 * 微信平台抽奖算法总结-再也不用怕奖品被提前抢光
 * 来源：http://www.xuanfengge.com/luck-draw.html
 */


/**
 * 思路一
 * 直观的做法是建立三张表
 create table award_batch (
	id int(10) unsigned not null auto_increment comment '主键',
	name varchar(100) not null comment '奖品名字',
	amount int  not null default 0 comment '奖品总数量',
	primary key (id)
 );
 create table award_pool (
 	id int(10)  unsigned not null auto_increment comment '主键',
 	award_id  int(10) unsigned not null comment '奖品类型id',
 	relaease_time timestamp not null  default CURRENT_TIMESTAMP comment '发放时间',
 	balance int unsigned not null default 0 comment '数量',
 	primary key(id)
 );
 create table record (
	id int(10) unsigned not null auto_increment comment '主键',
	owner_id char(32) not null comment '用户id',
	pool_id   int(10)  unsigned not null comment '奖品id',
	award_id  int(10) unsigned not null comment '奖品类型id',
	hit_time  timestamp NOT NULL default CURRENT_TIMESTAMP comment '中奖时间',
	primary key(id)
 );

 活动开始前，根据award_batch中的奖品配置信息，初始化award_pool中的数据，
 把每种奖品的释放时间初始化好，用户来抽奖时，根据当前时间在award_pool表中的
 查询到一条已经释放而且未被抽掉的奖品
 
 select * from award_pool where release_time <= now() and balance > 0 limit 1 ;
 同步到mc 或者 redis

 查询到后对其进行更新，如果被他人抢走，则未中奖
 update award_pool set balance = balance – 1 where id = #{id} balance > 0 ;

 同时留下抽奖情况到t_record中。

思路二
在思路一中，为了方便抽奖时判断当前是否有可中奖品，进行了初始化每件奖品的释放时间，
当奖品数量比较小的时候，情况还好，对于奖品数非常多的时候，抽奖的查询耗时会增加，
初始化奖池也是耗时的动作，是否可以不依赖这个表之间通过实时计算判断当前是否有奖品释放。
在t_award_batch表中添加两个字段，奖品总剩余量balance和上一次中奖时间last_update_time。


ID	名称(name)	奖品总量(amount)	奖品余量(balance)	更新时间(last_update_time)

 */


























