<?php
/*------------------------------------------------------------------------------------------------------------------------
 | MongoDB 概念及特点
 |------------------------------------------------------------------------------------------------------------------------
 | 概念:
 | MongoDB是一个基于文档的NoSQL数据库，文档的结构为BSON形式
 |-------------------------------------------------------------------------------------------------------------------------
 | 特点:
 | ●mongodb是模式自由的（schema-free），同一集合中的文档结构可以不同，不需要事先定义集合的结构
 | ●复制集可以提高数据的安全性，分片集群可以实现负载均衡
 | ●不支持join连接
 | ●不支持事务，隔离性差，会出现脏读、幻读和不可重复读
 | ●读写操作是非序列化的
 | ●可能会读到尚未持久化的数据，对于复制集，采用local形式读取主节点上的数据，有回滚的风险
 | ●多文档的写操作不是原子性的，单文档的写操作是原子的
 | ●会缓存索引信息、最近使用的数据，不会缓存查询结果
 | ●写操作是延迟的，先将写操作写入日志，然后缓存在内存，最后写入磁盘
 | ●单个文档大小不可以超过16M
 |------------------------------------------------------------------------------------------------------------------------------------
 */

require_once __DIR__ . "/vendor/autoload.php";

$collection = (new MongoDB\Client)->example->customers;

//添加数据
$insertOneResult = $collection->insertMany([
    [
        'username' => 'Lili',
        'email' => 'Lili@example.com',
        'name' => 'Lili User',
    ],
    [
        'username' => 'yangqm',
        'email' => 'yang@example.com',
        'name' => 'Yang User',
        'family' => [
            'father',
            'mother',
            'older sister',
        ]
    ],
]);

/*---------------------------------------------------------------------------------------------------------------------------------------------------------------
 | 更新数据(UPDATE 语句)
 |---------------------------------------------------------------------------------------------------------------------------------------------------------------
 | $or 条件相当于SQL中的or $collection->updateMany(['$or' => [['username' => 'Lili'], ['username' => 'yangqm']]], ['$set' => ['email' => '1443261811@qq.com']])
 |---------------------------------------------------------------------------------------------------------------------------------------------------------------
 |  AND 和 OR 联合使用
 |----------------------------------------------------------------------------------------------------------------------------------------------------------------
 */


/*---------------------------------------------------------------------------------------------------------------------------------------------------------------
 | 查询数据(SELECT 语句)
 |---------------------------------------------------------------------------------------------------------------------------------------------------------------
 | 模糊搜索的使用(查询name字段中 以adm开头的行) $collection->find(['name' => new MongoDB\BSON\Regex('^adm', 'i')], ['limit' => 4])->toArray()
 |--------------------------------------------------------------------------------------------------------------------------------------------------------------
 | $or 条件相当于SQL中的or $collection->find(['$or' => [['email' => '1443261811@qq.com'], ['name' => new MongoDB\BSON\Regex('^ad', 'i')]]])->toArray()
 |----------------------------------------------------------------------------------------------------------------------------------------------------------------
 */

/*-------------------------------------------------------------------------------------------------------------------------------------------------------------
 | MongoDB 索引的使用
 |--------------------------------------------------------------------------------------------------------------------------------------------------------------
 | 索引的种类
 | 1: _id索引 (集合默认的索引，对于每个文档，数据库都会自动生成一个唯一的_id字段，无法删除，通过_id字段查询性能极高)
 | 2: 单键索引 (最普通的索引)
 | 3: 多键索引 (多键指的是字段的值为数组，而不是多个字段。数据库会对数组中每一个字段值创建一个索引条目，但每个条目都引用同一个文档。注意:不能同时对一个集合中的多个字段创建多键索引)
 | 4: 复合索引 (复合索引指的是多个字段同为一个索引,字段按正向排序还是负向排序很重要！，这决定了查询是否支持sort操作,另外查询必须遵循最左前缀原则)
 | 5: 过期索引 (在一段时间后，索引过期，相应数据也被删除，适合用户登录信息和日志。注意：过期索引字段值必须是ISODate类型或ISODate数组类型，不能使用时间戳)
 | 6: 全文索引 (全文索引的值为字符串,每个集合中能建立一个全文索引,不支持中文)
 | 7: 唯一索引 (索引字段不会存储重复的值，唯一索引保证了了字段的值具有唯一性。可以实现如果数据存在则不插入，如果不存在则插入的功能。)
 | 8: 哈希索引 (主要用于分片集群中，通过对分片key使用哈希索引，以遍对数据进行分片和路由。哈希索引只支持一个字段，不能和其他字段组合,哈希索引不能和unque同时使用)
 | 9: 地理位置索引 ()
 |
 |
 |
 |---------------------------------------------------------------------------------------------------------------------------------------------------------------
 | collection->createIndex(['username' => 1, 'email' => 1], ['unique' => true, 'name' => 'nameWithEmailUnique'])
 | 解释:创建复合索引 nameWithEmailUnique 索引类型是 unique,查询必须遵循最左前缀原则，查询语句中必须包含左边的字段，不能跳过左边的字段
 |---------------------------------------------------------------------------------------------------------------------------------------------------------------
 |
 |----------------------------------------------------------------------------------------------------------------------------------------------------------------
 */

/*---------------------------------------------------------------------------------------------------------------------------------------------------------------
 | MongoDB 查询分析 explain 的使用
 |---------------------------------------------------------------------------------------------------------------------------------------------------------------
 | 主要字段的解释
 | 1: indexFilterSet (是否使用了索引)
 | 2: stage 查询方式 取值(COLLSCAN:全表扫描, IXSCAN:索引扫描, FETCH:根据索引去检索文档, SHARD_MERGE:合并分片结果, IDHACK:针对_id进行查询)
 | 3: executionTimeMillis 执行耗时
 | 4: totalKeysExamined 索引扫描次数
 | 5: totalDocsExamined 文档扫描次数
 | 6:
 |---------------------------------------------------------------------------------------------------------------------------------------------------------------
 | 使用:
 | //构建查询语句
 | $cursor = new MongoDB\Operation\FindOne(
 |     $collection->getDatabaseName(),
 |     $collection->getCollectionName(),
 |     ['username' => 'admin']
 | );
 | //执行查询分析
 | $cursor = $collection->explain($cursor);
 | //输出分析结果
 | p($cursor);
 |----------------------------------------------------------------------------------------------------------------------------------------------------------------
 */

/*---------------------------------------------------------------------------------------------------------------------------------------------------------------
 | MongoDB 聚合 aggregate()方法的使用
 |---------------------------------------------------------------------------------------------------------------------------------------------------------------
 | 分组和统计数量的使用:
 | //此句相当于SQL的 select username, count(*) as num_tutorial from table_name group by username
 | $collection->aggregate([['$group' => ['_id' => '$username', 'num_tutorial' => ['$sum' => 1]]]])
 |
 |---------------------------------------------------------------------------------------------------------------------------------------------------------------
 |  AND 和 OR 联合使用
 |----------------------------------------------------------------------------------------------------------------------------------------------------------------
 */


//$cursor = $collection->createIndex(['username' => 1, 'email' => 1], ['unique' => true, 'name' => 'nameWithEmailUnique']);
//$cursor = $collection->dropIndex('username_1');
//$cursor = $collection->listIndexes();
//$cursor = $collection->insertOne(['username' => 'Lili', 'email' => '1443261811', 'name' => 'YANG']);
//$cursor = $collection->find(['username' => 'Lili']);
/*$cursor = new MongoDB\Operation\FindOne(
    $collection->getDatabaseName(),
    $collection->getCollectionName(),
    ['username' => 'admin']
);*/
//$cursor = $collection->explain($cursor);
//p($cursor);
//$res = $collection->aggregate(['$group' => ['id' => '$name', 'count' => ['$sum' => 1]]]);
//$res = $collection->aggregate([['$project' => ['email' => 1]], ['$group' => ['_id' => '$username', 'num_tutorial' => ['$sum' => 1]]]]);
//foreach ($res as $item) {
//    p($item);
//}
//$res = $collection->find(['$or' => [['age' => 30], ['salary' => ['$gt' => 7500]]]])->toArray();
//$res = $collection->find(['salary' => new MongoDB\BSON\Regex('^7', 'i')])->toArray();
//foreach ($res as $item) {
//    p($item);
//}
function p($data)
{
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}

