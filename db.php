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
        } else {
            exit("无效的参数:$operator");
        }

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
     * @param array $option 更新选项
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
        $wheres =  $this->compileWheres();
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
     * @return int|null
     */
    public function push($collectionName, $column, $value)
    {
        $wheres =  $this->compileWheres();
        $options = ['multi' => true, 'multiple' => 1];
        $modifiedCount = $this->performUpdate($collectionName, $wheres, ['$addToSet' => [$column => $value]], $options);

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
    public function pull($collectionName, $column, $value)
    {
        $wheres =  $this->compileWheres();
        $options = ['multi' => true, 'multiple' => 1];
        $modifiedCount = $this->performUpdate($collectionName, $wheres, ['$pull' => [$column => $value]], $options);

        return $modifiedCount;
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