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

    public function where($column = '', $operator = '', $value = '')
    {
        switch (true) {
            case is_array($column) :
                $this->wheres = array_merge($column, $this->wheres);
                break;
            case (func_num_args() == 2) :
                $this->wheres[$column] = $operator;
                break;
            case (func_num_args() == 3 && array_key_exists($operator, $this->conversion)) :
                $this->wheres[$column] = [$this->conversion[$operator] => $value];
                break;
            default :
                exit("无效的参数:$operator");
        }

        return $this;
    }

    public function compileWheres()
    {
        if (!count($this->wheres) > 0) {
            return $this->wheres;
        }

        $wheres = [];
        foreach ($this->wheres as $k => $where) {
            if (count($where) == 3) {
                $wheres[$where['column']] = [$where['operator'] => $where['value']];
            } else {
                $wheres[key($where)] = current($where);
            }
        }

        return $wheres;
    }

    public function update($collectionName, array $data)
    {
        $bulk = static::getBulkWriteInstance();
        $wheres = $this->wheres;
        $this->wheres = [];
//print_r($wheres);die;
        $bulk->update($wheres, ['$set' => $data], ['multi' => true, 'upsert' => false]);

        $writeResult = $this->manager->executeBulkWrite($this->databaseName . '.' . $collectionName, $bulk, $this->writeConcern);

        /* If the WriteConcern could not be fulfilled */
        if ($writeConcernError = $writeResult->getWriteConcernError()) {
            printf("%s (%d): %s\n", $writeConcernError->getMessage(), $writeConcernError->getCode(), var_export($writeConcernError->getInfo(), true));
        }

        return $writeResult->getModifiedCount();
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