<?php

class db
{
    public $manager;
    public $databaseName;
    protected $writeConcern;
    protected $readConcern;
    protected $readPreference;

    protected $query;

    static $bulkWrite;

    /**
     * 插入新文档后,文档的_id
     *
     * @var
     */
    protected $insertedId;

    /**
     * 新文档插入成功的条数
     *
     * @var
     */
    protected $insertedCount;

    /**
     * 查询条件组
     *
     * @var array
     */
    protected $wheres = array();

    /**
     * 需要查询的字段
     *
     * @var array
     */
    protected $columns = array();

    /**
     * 按字段对结果排序
     *
     * @var
     */
    protected $orderBy = array();

    /**
     * 分组字段
     *
     * @var
     */
    protected $groupBy = array();

    /**
     * 查询的数量
     *
     * @var
     */
    protected $limit;

    /**
     * 从第几条记录开始查询
     *
     * @var
     */
    protected $offset;

    /**
     * Operator conversion.
     *
     * @var array
     */
    protected $conversion = [
        '=' => '=',
        '!=' => '$ne',
        '<>' => '$ne',
        '<' => '$lt',
        '<=' => '$lte',
        '>' => '$gt',
        '>=' => '$gte',
    ];

    public function __construct($uri = "mongodb://localhost:27017")
    {
        $this->manager = new MongoDB\Driver\Manager($uri);
        $this->readConcern = $this->manager->getReadConcern();
        $this->writeConcern = $this->manager->getWriteConcern();
        $this->readPreference = $this->manager->getReadPreference();
    }

    /**
     * 插入文档
     *
     * @param string $collectionName 文档名称
     * @param array $data 插入数据
     * @return int|null
     */
    public function insert($collectionName, array $data)
    {
        $bulk = static::getBulkWriteInstance();
        //构建插入语句,并保存ID
        $insertedId = $bulk->insert($data);
        //执行写入
        $writeResult = $this->manager->executeBulkWrite($this->databaseName . '.' . $collectionName, $bulk);
        //获取添加成功的文档数
        $this->insertedCount = $writeResult->getInsertedCount();
        //文档插入成功记录文档的_id
        $this->insertedId = $this->insertedCount ? $insertedId : '';

        return $this->insertedCount;
    }

    /**
     * 更新操作
     *
     * @param string $collectionName 文档名
     * @param array $data 需要更新的数据
     * @return int|null
     */
    public function update($collectionName, array $data)
    {
        $wheres = $this->compileWheres();
        $options = ['multi' => true, 'upsert' => false];
        $modifiedCount = $this->performUpdate($collectionName, $wheres, ['$set' => $data], $options);

        return $modifiedCount;
    }

    /**
     * 执行更新
     *
     * @param string $collectionName 文档名
     * @param array $filter 过滤条件
     * @param array $update 更新内容
     * @param array $options 更新选项
     * @return int|null
     */
    protected function performUpdate($collectionName, array $filter, array $update, array $options = [])
    {
        $bulk = static::getBulkWriteInstance();
        $bulk->update($filter, $update, $options);
        $writeResult = $this->manager->executeBulkWrite($this->databaseName . '.' . $collectionName, $bulk, $this->writeConcern);

        /* If the WriteConcern could not be fulfilled */
        if ($writeConcernError = $writeResult->getWriteConcernError()) {
            printf("%s (%d): %s\n", $writeConcernError->getMessage(), $writeConcernError->getCode(), var_export($writeConcernError->getInfo(), true));
        }

        return $writeResult->getModifiedCount();
    }

    /**
     * 对字段执行自增操作
     *
     * @param string $collectionName 文档名称
     * @param string $column 自增的列 取值默认为1,如果传递了该值 则以传递的值为准
     * @param int $value 自增的值
     * @return int|null
     */
    public function increment($collectionName, $column, $value = 1)
    {
        return $this->incrementOrDecrement($collectionName, $column, $value, 'up');
    }

    /**
     * 对字段执行自减操作
     *
     * @param string $collectionName 文档名称
     * @param string $column 自减的列
     * @param int $value 自减的值, 取值默认为1, 如果传递了该值 则以传递的值为准
     * @return int|null
     */
    public function decrement($collectionName, $column, $value = 1)
    {
        return $this->incrementOrDecrement($collectionName, $column, $value, 'down');
    }

    /**
     * 根据操作符执行自增或自减
     *
     * @param string $collectionName 文档名
     * @param string $column 增减的列
     * @param int $value 增减的值
     * @param string $operator 操作符 取值:up(自增) down(自减)
     * @return int|null
     */
    protected function incrementOrDecrement($collectionName, $column, $value, $operator)
    {
        $value = $operator == 'up' ? $value : '-' . $value;
        $wheres = $this->compileWheres();
        $update = ['$inc' => [$column => (int)$value]];
        $options = ['multi' => true, 'upsert' => false];
        $modifiedCount = $this->performUpdate($collectionName, $wheres, $update, $options);

        return $modifiedCount;
    }

    /**
     * 向数组字段中追加一项
     *
     * @param string $collectionName 文档名
     * @param string $column 目标数组字段
     * @param string|int|array $value 追加的内容
     * @param boolean $unique 追加时是否保持数组内的值不重复
     * @return int|null
     */
    public function push($collectionName, $column, $value = null, $unique = false)
    {
        $wheres = $this->compileWheres();
        // Use the addToSet operator in case we only want unique items.
        $operator = $unique ? '$addToSet' : '$push';

        // Check if we are pushing multiple values.
        $batch = (is_array($value) and array_keys($value) === range(0, count($value) - 1));

        if (is_array($column)) {
            $query = [$operator => $column];
        } elseif ($batch) {
            $query = [$operator => [$column => ['$each' => $value]]];
        } else {
            $query = [$operator => [$column => $value]];
        }

        $options = ['multi' => true, 'multiple' => 1];
        $modifiedCount = $this->performUpdate($collectionName, $wheres, $query, $options);

        return $modifiedCount;
    }

    /**
     * 从数组字段中删除一项
     *
     * @param string $collectionName 文档名
     * @param string $column 目标数组字段
     * @param string|int|array $value 要删除的值
     * @return int|null
     */
    public function pull($collectionName, $column, $value = null)
    {
        // Check if we passed an associative array.
        $batch = (is_array($value) and array_keys($value) === range(0, count($value) - 1));
        // If we are pulling multiple values, we need to use $pullAll.
        $operator = $batch ? '$pullAll' : '$pull';
        $query = is_array($column) ? [$operator => $column] : [$operator => [$column => $value]];
        $wheres = $this->compileWheres();
        $options = ['multi' => true, 'multiple' => 1];
        $modifiedCount = $this->performUpdate($collectionName, $wheres, $query, $options);

        return $modifiedCount;
    }

    /**
     * 通过文档ID查询文件记录
     *
     * @param string $collectionName 文档名
     * @param string|array $id 支持多个id查询
     * @param array $columns 需要查询的字段
     * @return array|\MongoDB\Driver\Cursor
     */
    public function find($collectionName, $id, array $columns = [])
    {
        //如果id是多个就按多个id查询,类似于SQL中 where in条件语句
        if (is_array($id)) {
            $filter['_id']['$in'] = array_map(function ($value) {
                return new MongoDB\BSON\ObjectID($value);
            }, $id);
        } else {
            $filter['_id'] = new MongoDB\BSON\ObjectID($id);
        }

        //查询的字段,如果没有指定就查询所有字段
        $projection = $this->compileColumns($columns);
        $options = $projection ? compact('projection') : [];

        return $this->performQuery($collectionName, $filter, $options);
    }

    /**
     * 查询文档
     *
     * @param string $collectionName 文档名
     * @param array $columns
     * @return array|\MongoDB\Driver\Cursor
     */
    public function get($collectionName, array $columns = [])
    {
        $options = array();
        $filter = $this->compileWheres();

        $projection = $this->compileColumns($columns);
        $options['projection'] = $projection;
        $options['sort'] = $this->orderBy;
        $options['skip'] = $this->offset;
        $options['limit'] = $this->limit;
        $options = array_filter($options);

        return $this->performQuery($collectionName, $filter, $options);
    }

    /**
     * 聚合查询
     *
     * @param string $collectionName 文档名称
     * @param array $columns
     * @return array|\MongoDB\Driver\Cursor
     */
    public function aggregate($collectionName, $columns = [])
    {
        $group = [];
        $pipeline = [];
        if ($this->wheres) {
            $pipeline[] = ['$match' => $this->compileWheres()];
        }

        $columns = $columns ?: $this->columns;
        if ($columns) {
            foreach ($columns as $column) {
                $group[$column] = ['$last' => '$' . $column];
            }
        }

        if ($this->groupBy) {
            $group += ['_id' => $this->groupBy, 'avg' => ['$sum' => '$salary']];
        }

        $pipeline[] = ['$group' => $group];
//        print_r($pipeline);die;
        $command = [
            'aggregate' => $collectionName,
            'pipeline' => $pipeline,
            'cursor' => new stdClass,
        ];
        $command = new MongoDB\Driver\Command($command);
        $cursor = $this->manager->executeCommand($this->databaseName, $command);
        //将查询迭代器转化为数组
        return iterator_to_array($cursor);
    }

    /**
     * 获取记录的数量
     *
     * @param string $collectionName 文档名
     * @return mixed
     */
    public function count($collectionName)
    {
        //查询记录总的数量
        $commands = [
            'count' => $collectionName,
            'query' => $this->compileWheres()
        ];
        $command = new \MongoDB\Driver\Command($commands);
        $cursor = $this->manager->executeCommand($this->databaseName, $command);
        $info = $cursor->toArray();
        $count = $info[0]->n;

        return $count;
    }

    /**
     * 获取某个字段的和
     *
     * @param string $collectionName 文档名
     * @param string $column 字段名
     * @return mixed
     */
    public function sum($collectionName, $column)
    {
        $pipeline[] = ['$group' => ['_id' => 'null', $column => ['$sum' => '$' . $column]]];

        $data = $this->executeAggregate($collectionName, $pipeline);

        return $data[0]->$column;
    }

    /**
     * 获取记录中某个字段的最小值
     *
     * @param string $collectionName 文档名
     * @param string $column 字段名
     * @return mixed
     */
    public function min($collectionName, $column)
    {
        $pipeline[] = ['$group' => ['_id' => 'null', $column => ['$min' => '$' . $column]]];

        $data = $this->executeAggregate($collectionName, $pipeline);

        return $data[0]->$column;
    }

    /**
     * 获取记录中某个字段的最大值
     *
     * @param string $collectionName 文档名
     * @param string $column 字段名
     * @return mixed
     */
    public function max($collectionName, $column)
    {
        $pipeline[] = ['$group' => ['_id' => 'null', $column => ['$max' => '$' . $column]]];

        $data = $this->executeAggregate($collectionName, $pipeline);

        return $data[0]->$column;
    }

    /**
     * 获取记录中某个字段的平均值
     *
     * @param string $collectionName 文档名
     * @param string $column 字段名称
     * @return mixed
     */
    public function avg($collectionName, $column)
    {
        $pipeline[] = ['$group' => ['_id' => 'null', $column => ['$avg' => '$' . $column]]];

        $data = $this->executeAggregate($collectionName, $pipeline);

        return $data[0]->$column;
    }

    /**
     * 执行聚合查询
     *
     * @param string $collectionName 文档名称
     * @param array $pipeline 管道
     * @return array|\MongoDB\Driver\Cursor
     */
    public function executeAggregate($collectionName, array $pipeline)
    {
        $wheres = $this->compileWheres();
        $wheres and $pipeline[] = ['$match' => $wheres];

        $command = [
            'aggregate' => $collectionName,
            'pipeline' => $pipeline,
            'cursor' => new stdClass,
        ];
        $command = new MongoDB\Driver\Command($command);
        $cursor = $this->manager->executeCommand($this->databaseName, $command);
        //将查询迭代器转化为数组
        return iterator_to_array($cursor);
    }

    /**
     * 执行查询语句
     *
     * @param string $collectionName 文档名
     * @param array $filter 过滤条件
     * @param array $options 查询选项
     * @return array|\MongoDB\Driver\Cursor
     */
    public function performQuery($collectionName, array $filter, array $options)
    {
        //构建查询语句
        $query = new MongoDB\Driver\Query($filter, $options);
        //执行查询
        $cursor = $this->manager->executeQuery($this->databaseName . '.' . $collectionName, $query, $this->readPreference);
        //将查询迭代器转化为数组
        return iterator_to_array($cursor);
    }

    /**
     * 设置查询条件
     *
     * @param string|array $column
     * @param string|null $operator
     * @param string|null $value
     * @return $this
     */
    public function where($column = '', $operator = '', $value = '')
    {
        if (is_array($column)) {
            $this->wheres += $column;
        } else if (func_num_args() == 2) {
            $this->wheres[$column] = $operator;
        } else if (func_num_args() == 3 && array_key_exists($operator, $this->conversion)) {
            $this->wheres[$column] = [$this->conversion[$operator] => $value];
        }

        return $this;
    }

    /**
     * 查询符合条件的多个值
     *
     * @param string $column
     * @param array $values
     * @return $this
     */
    public function whereIn($column, array $values)
    {
        if ($column == 'id') {
            $values[] = array_map(function ($value) {
                return new MongoDB\BSON\ObjectID($value);
            }, $values);
        }

        $this->wheres[$column]['$in'] = $values;

        return $this;
    }

    /**
     * 设置or查询条件
     *
     * @param string|array $column
     * @param null|string $operator
     * @param null|string $value
     * @return $this
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        if (is_array($column)) {
            //如果字段是id则对值进行转换
            array_key_exists('id', $column) and $column['id'] = new MongoDB\BSON\ObjectID($column['id']);
            $this->wheres['$or'][] = $column;
        } else if (func_num_args() == 2) {
            //如果字段是id则对值进行转换
            $column == 'id' and $value = new MongoDB\BSON\ObjectID($value);
            $this->wheres['$or'][] = [$column => $operator];
        } else if (func_num_args() == 3 && array_key_exists($operator, $this->conversion)) {
            $this->wheres['$or'][] = [$column => [$this->conversion[$operator] => $value]];
        }

        return $this;
    }

    /**
     * 设置模糊查询
     *
     * @param string $column 查询的字段
     * @param string $regex 正则表达式
     * @return $this
     */
    public function likeWhere($column, $regex)
    {
        $this->wheres[$column] = new MongoDB\BSON\Regex($regex, 'i');

        return $this;
    }

    /**
     * 指定需要查询的字段
     *
     * @param array|string $columns
     * @return $this
     */
    public function select($columns)
    {
        if ($columns) {
            $this->columns = is_array($columns) ? $columns : explode(',', $columns);
        }

        return $this;
    }

    /**
     * 对结果进行排序
     *
     * @param string $column 排序的字段
     * @param string $direction 排序的方式 正序或倒叙
     * @return $this
     */
    public function orderBy($column, $direction = 'asc')
    {
        $directions = ['asc' => 1, 'desc' => -1];
        if (array_key_exists($direction, $directions)) {
            $this->orderBy[$column] = $directions[$direction];
        }

        return $this;
    }

    /**
     * 设置分组
     *
     * @param string $column 分组的字段
     * @return $this
     */
    public function groupBy($column)
    {
        if ($column) {
            $this->groupBy[$column] = '$' . $column;
        }

        return $this;
    }

    /**
     * 查询行数限制
     *
     * @param int $value 获取记录的条数
     * @return $this
     */
    public function limit($value)
    {
        is_numeric($value) and $this->limit = $value;

        return $this;
    }

    /**
     * 查询行数偏移量
     *
     * @param int $value
     * @return $this
     */
    public function offset($value)
    {
        is_numeric($value) and $this->offset = $value;

        return $this;
    }

    /**
     * 处理查询条件
     *
     * @return array
     */
    protected function compileWheres()
    {
        if (!count($this->wheres)) {
            return $this->wheres;
        }

        $wheres = $this->wheres;
        $this->wheres = [];
        if (array_key_exists('_id', $wheres)) {
            $wheres['_id'] = new MongoDB\BSON\ObjectID($wheres['_id']);
        }

        return $wheres;
    }

    /**
     * 将查询字段组成一个关联数组,以字段名称为键 以1为值:[filed => 1]
     *
     * @param array $columns
     * @return array|null
     */
    public function compileColumns(array $columns)
    {
        $columns = $columns ?: $this->columns;
        $this->columns = [];

        return count($columns)
            ? array_combine($columns, array_fill(0, count($columns), 1))
            : null;
    }

    /**
     * 获取写驱动
     *
     * @return mixed
     */
    public function getBulkWriteInstance()
    {
        if (!static::$bulkWrite) {
            static::$bulkWrite = new MongoDB\Driver\BulkWrite;
        }

        return static::$bulkWrite;
    }

    public function __get($name)
    {
        $this->databaseName = $name;

        return $this;
    }
}