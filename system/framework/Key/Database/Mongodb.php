<?php

/**
 * Created by PhpStorm.
 * User: Kun a88wangtian@163.com
 * Date: 2016/7/4
 * Time: 14:19
 */

namespace Key\Database;

use Key\Constants;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use MongoDB\Driver\BulkWrite;
use MongoDB\BSON\ObjectID;
use MongoDB\Driver\WriteConcern;
use MongoDB\Driver\Command;
use MongoDB\Driver\Exception\RuntimeException;
use MongoDB\Driver\Exception\ConnectionException;
use MongoDB\Driver\Exception\InvalidArgumentException;
use MongoDB\Driver\Exception\AuthenticationException;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\Exception\UnexpectedValueException;
use MongoDB\Driver\ReadPreference;
use stdClass;
use Exception;
use Key\Log\LoggerManager;
use Key\Exception\DatabaseException;


/**
 * Class Mongodb
 * @package Key\Database
 */
class Mongodb
{
    const WRITE_CONCERN_TIMEOUT = 5000;
    const WRITE_CONCERN_W = 1; //update delete

    private $dbName;

    private $url;
    /** @var \MongoDB\Driver\Manager */
    protected $connection = null;
    protected $rConnection = null;

    /** @deprecated */
    protected $uid;
    /** @deprecated */
    protected $aid;

    const READ_PREFERENCE_PRIMARY = ReadPreference::RP_PRIMARY;
    public $errors = '';

    protected $readPreference = null;

    protected $options = [];

    /**
     * mongodb constructor.
     * @param $url
     * @param $dbName
     * @param $uid
     * @param $aid
     * @throws InvalidArgumentException
     */
    public function __construct($url, $dbName, $uid = 0, $aid = 0)
    {
        $this->dbName = $dbName;
        $this->url = $url;
        $this->uid = $uid;
        $this->aid = $aid;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getDbName()
    {
        return $this->dbName;
    }

    /**
     * connection to the db server(this is a update or a insert)
     * @param array $options
     * @return Manager|null
     * @throws Exception
     */
    protected function connect($options = array())
    {
        $server = $this->url;
        try {
            $manager = new Manager($server, $options);
            $this->connection = $manager;
            return $this->connection;
        } catch (RuntimeException $ex) {
            $this->errors = $ex->getMessage();
        } catch (InvalidArgumentException $ex) {
            $this->errors = $ex->getMessage();
        }
        throw new Exception("Mongodb Manager is error");
    }

    /**
     * Reconnect the server.
     *
     * @param array $options
     */
    public function reconnect($options = [])
    {
        $this->connection = null;
        $this->connect($options);
    }

    /**
     * get the database connection
     * @return Manager connection
     */
    protected function getConnection()
    {
        if ($this->connection) {
            return $this->connection;
        }
        return $this->connect();
    }

    /**
     * get the read database connection
     * @return mixed connection
     */
    protected function getRConnection()
    {
        if ($this->connection) {
            return $this->connection;
        }
        return $this->connect();
    }

    /**
     * Set read preference.
     * @param int $pref RP_PRIMARY/RP_PRIMARY_PREFERRED/RP_SECONDARY/RP_SECONDARY_PREFERRED/RP_NEAREST
     * @return $this
     */
    public function setReadPreference($pref)
    {
        $this->readPreference = new ReadPreference($pref);
        return $this;
    }

    protected function getReadPreference()
    {
        if (!$this->readPreference) {
            $this->readPreference = new ReadPreference(self::READ_PREFERENCE_PRIMARY);
        }
        return $this->readPreference;
    }

    /**
     * Get db server instance.
     *
     * @param integer $retry
     * @return \MongoDB\Driver\Server
     */
    public function getServer($retry = 3)
    {
        try {
            $this->setReadPreference(self::READ_PREFERENCE_PRIMARY);
            return $this->getConnection()->selectServer($this->getReadPreference());
        } catch (ConnectionException $ex) {
            $this->errors = $ex->getMessage();
            //LoggerManager::getFileInstance()->info('getServer Exception:' . $this->errors);
        } catch (AuthenticationException $ex) {
            $this->errors = $ex->getMessage();
        }
        if ($retry > 0) {
            $this->getServer(--$retry);
        } else {
            throw new Exception('DB connection timeout', Constants::SYS_DATABASE_ERROR);
        }
    }

    public function getRServer($retry = 3)
    {
        try {
            return $this->getRConnection()->selectServer($this->getReadPreference());
        } catch (ConnectionException $ex) {
            $this->errors = $ex->getMessage();
            //LoggerManager::getFileInstance()->info('getRServer Exception:' . $this->errors);
        } catch (AuthenticationException $ex) {
            $this->errors = $ex->getMessage();
        }
        if ($retry > 0) {
            $this->getRServer(--$retry);
        } else {
            throw new Exception('DB connection timeout', Constants::SYS_DATABASE_ERROR);
        }
    }

    /**
     * execute the command
     * @param $command
     * @param MongoDB\Driver\ReadPreference $readPreference
     * @return array
     * @throws InvalidArgumentException
     */
    public function execute($command, $readPreference = null)
    {
        $readPreference = isset($readPreference) ? $readPreference : null;
        $command = new Command($command);
        try {
            $result = $this->getServer()->executeCommand($this->dbName, $command, $readPreference);
            $result->setTypeMap(array('root' => 'array', 'document' => 'array', 'array' => 'array'));
            return $result->toArray();
        } catch (Exception $e) {
            $this->errors = $e->getMessage();
            //LoggerManager::getFileInstance()->info('Exception:' . $this->errors);
        }
        return false;
    }

    /**
     * 删除集合对应的文档
     * 此方法会调更新方法，防止数据被程序删除
     * @param string $collection 集合名称
     * @param array $condition 删除条件
     * @param array $options 不提供删除整个表行为，如果删除整个表，默认删除一条
     * @return bool|int 返回删除的删除数据条数
     */
    public function delete($collection, $condition, $options = array())
    {
        if (!$condition) {
            $options['limit'] = 1;
        }
        $newData = [
            'status' => 0,
            'deleted_by' => 0,
            'deleted_time' =>  Mongodb::getMongoDate(),
            'auto_deleted' => 1
        ];
        $newData = ['$set' => $newData];
        return $this->update($collection, $condition, $newData, $options);
        $bulk = new BulkWrite();
        $bulk->delete($condition, $options);
        try {
            $writeConcern = new WriteConcern(self::WRITE_CONCERN_W, self::WRITE_CONCERN_TIMEOUT);
            $result = $this->getServer()->executeBulkWrite($this->dbName . '.' . $collection, $bulk, $writeConcern);
            //LoggerManager::getMongoInstance()->info($this->logMessage());
            return $result->getDeletedCount();
        } catch (InvalidArgumentException $e) {
            $this->errors = $e->getMessage();
        }
        return false;
    }

    /**
     * 删除集合对应的文档，此方法会真正删除数据
     * @param string $collection 集合名称
     * @param array $condition 删除条件
     * @param array $options 不提供删除整个表行为，如果删除整个表，默认删除一条
     * @return bool|int 返回删除的删除数据条数
     */
    public function realDelete($collection, $condition, $options = array())
    {
        if (!$condition) {
            $options['limit'] = 1;
        }
        $bulk = new BulkWrite();
        $bulk->delete($condition, $options);
        try {
            $writeConcern = new WriteConcern(self::WRITE_CONCERN_W, self::WRITE_CONCERN_TIMEOUT);
            $result = $this->getServer()->executeBulkWrite($this->dbName . '.' . $collection, $bulk, $writeConcern);
            //LoggerManager::getMongoInstance()->info($this->logMessage());
            return $result->getDeletedCount();
        } catch (InvalidArgumentException $e) {
            $this->errors = $e->getMessage();
        }
        return false;
    }

    /**
     * 将一个id转换成MongoId
     * @param $id
     * @return MongoId
     */
    public function getMongoObjectId($id = 0)
    {
        return $id ? new ObjectID($id) : new ObjectID();
    }

    /**
     * 获取一个string类型的_id
     * @param $id
     * @return string
     */
    public function getMongoStringId($id = 0)
    {
        return $id ? (string)new ObjectID($id) : (string)new ObjectID();
    }

    /**
     * 往指定collection插入数据，如果collection不存在，返回错误
     * @param $type 1为需要返回_id,0为不需要
     * @param $collection
     * @param array $bind
     * @param array $options
     * @return bool 返回成功插入的条数或者报错
     * @throws InvalidArgumentException
     */
    public function insert($collection, $bind, $type = 1, $options = array('w' => 1))
    {
        if (!is_string($collection) || (trim($collection)) == '') {
            throw new InvalidArgumentException('Invalid collection ' . $collection . ' the collection is must input');
        }
        if (!(count($bind) > 0)) {
            throw new InvalidArgumentException('Invalid collection  the data is must input');
        }
        $bulk = new BulkWrite();
        $bind['_id'] = $bulk->insert($bind);
        try {
            $writeConcern = new WriteConcern(self::WRITE_CONCERN_W, self::WRITE_CONCERN_TIMEOUT);
            $result = $this->getServer()->executeBulkWrite($this->dbName . '.' . $collection, $bulk, $writeConcern);
            //LoggerManager::getMongoInstance()->info($this->logMessage($bind['_id']));
            if (env('DB_DEBUG') || env('APP_ENV') == 'development') error_log('[' . $this->getUrl() . ' // ' . $this->getDbName() . ']' . $collection . ' inserted: ' . $result->getInsertedCount());
            return $result->getInsertedCount();
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            $this->errors = $e->getMessage();
            return false;
        }
    }

    /**
     * 往指定的collection批量插入数据
     * @param $collection
     * @param array $bind
     * @param array $options
     * @return bool|int 返回插入数量或者错误
     * @throws InvalidArgumentException
     */
    public function batchInsert($collection, $bind = array(), $options = array('w' => 1))
    {
        if (!is_string($collection) || (trim($collection)) == '') {
            throw new InvalidArgumentException('Invalid collection ' . $collection . ' the collection is must input');
        }
        if (!(count($bind) > 0)) {
            throw new InvalidArgumentException('Invalid collection ' . $collection . ' the data is must input');
        }
        $bulk = new BulkWrite();
        $insertedIds = [];
        foreach ($bind as $key => $value) {
            $insertedId = $bulk->insert($value);
            if ($insertedId !== null) {
                $insertedIds[$key] = $insertedId;
            }
        }
        try {
            $writeConcern = new WriteConcern(self::WRITE_CONCERN_W, self::WRITE_CONCERN_TIMEOUT);
            $result = $this->getServer()->executeBulkWrite($this->dbName . '.' . $collection, $bulk, $writeConcern);
            //LoggerManager::getMongoInstance()->info($this->logMessage($insertedIds));
            // if (env('DB_DEBUG') || env('APP_ENV') == 'development') error_log('[' . $this->getUrl() . ' // ' . $this->getDbName() . ']' . $collection . ' inserted: ' . $result->getInsertedCount());
            return $result->getInsertedCount();
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            $this->errors = $e->getMessage();
            return false;
        }
    }

    /**
     * 返回指定集合中的文档数量
     * @param string $collection
     * @param array $condition
     * @return int 如果发生错误返回false
     * @throws UnexpectedValueException
     */
    public function count($collection, $condition)
    {
        if (env('DB_DEBUG') || env('APP_ENV') == 'development') error_log(/*'[' . $this->getUrl() . ' // ' . $this->getDbName() . ']' . */'count: ' . $collection . ' ' . json_encode($condition)); // . ' ' . json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0]));
        $cmd = ['count' => $collection];
        if (!empty($condition)) {
            $cmd['query'] = $condition;
        }
        $readPreference = null;
        $command = new Command($cmd);
        $cursor = $this->getServer()->executeCommand($this->dbName, $command, $readPreference);
        $result = current($cursor->toArray());
        if (!isset($result->n) || !(is_integer($result->n) || is_float($result->n))) {
            throw new UnexpectedValueException('count command did not return a numeric "n" value');
        }
        return (int)$result->n;
    }

    /**
     * 更新集合文档
     * @param string $collection 集合名称
     * @param array $condition 更新集合的条件
     * @param array $newData 新数据
     * @param array $options 更新的操作 默认更新多条
     * @return bool|int 返回更新的条数如果更新为0条则返回true,如果错误返回false
     * @throws InvalidArgumentException
     */
    public function update($collection, $condition, $newData, $options = array('multi' => true))
    {
        if (!is_string($collection) || (trim($collection)) == '') {
            throw new InvalidArgumentException('Invalid collection name.');
        }
        if (!is_array($condition)) {
            throw new InvalidArgumentException('Invalid condition data. The condition data should be a array ');
        }
        try {
            $bulk = new BulkWrite();
            $bulk->update($condition, $newData, $options);
            $writeConcern = new WriteConcern(self::WRITE_CONCERN_W, self::WRITE_CONCERN_TIMEOUT);
            $result = $this->getServer()->executeBulkWrite($this->dbName . '.' . $collection, $bulk, $writeConcern);
            //LoggerManager::getMongoInstance()->info($this->logMessage());
            return $result->getModifiedCount();
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            $this->errors = $e->getMessage();
            return false;
        }
    }

    /**
     * 批量更新数据
     * notice:传进来的数据是一个3维数组
     * for example:\app\mc\testMongo.php batchUpdateAction
     * 二维数组里面的数组名称必须是condition new_data option否则不能识别
     * @param $collection
     * @param array $newData
     * @return int 更改数据库的条数,如果有插入,则是插入的条数加上条件匹配的条数,如果没有插入，则是更新条件匹配的数量
     */
    public function batchUpdate($collection, $newData)
    {
        error_log('[!][WARN] Mongodb::batchUpdate called');
        if (!is_string($collection) || (trim($collection)) == '') {
            throw new InvalidArgumentException('Invalid collection name.');
        }
        if (!is_array($newData)) {
            throw new InvalidArgumentException('Invalid condition data. The condition data should be a array ');
        }
        try {
            $bulk = new BulkWrite();
            foreach ($newData as $key1 => $value1) {
                $bulk->update($value1['condition'], $value1['new_data'], isset($value1['option']) ? $value1['option'] : array());
            }
            $writeConcern = new WriteConcern(self::WRITE_CONCERN_W, self::WRITE_CONCERN_TIMEOUT);
            $result = $this->getServer()->executeBulkWrite($this->dbName . '.' . $collection, $bulk, $writeConcern);
            return $result->getUpsertedCount() + $result->getMatchedCount();
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            //LoggerManager::getFileInstance()->info('the error is ' . $e->getWriteResult()->getWriteErrors());
            return false;
        }
    }

    /**
     * 查取一行数据
     * @param string $collection 查询的集合
     * @param array $condition 查询条件
     * @param array $fields 返回想要的字段
     * @param array $options 操作
     * @return array 返回获取的一行数据
     */
    public function fetchRow($collection, $condition = array(), $fields = array(), $options = array())
    {
        if (!is_array($fields)) {
            $fields = [];
        }
        if (!isset($fields['_id'])) {
            $fields['_id'] = 0;
        }
        $options = array(
            'projection' => $fields,
            'limit' => 1,
            'comment' => json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)),
        );
        if (env('DB_DEBUG') || env('APP_ENV') == 'development') error_log(/*'[' . $this->getUrl() . ' // ' . $this->getDbName() . ']' . */'fetch row: ' . $collection . ' ' . json_encode($condition)); // . ' ' . json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0]));
        $query = new Query($condition, $options);
        $result = $this->getRServer()->executeQuery($this->dbName . '.' . $collection, $query);
        $result->setTypeMap(array('root' => 'array', 'document' => 'array', 'array' => 'array'));
        $arr = $result->toArray();
        if ($arr && count($arr) > 0) {
            return $arr[0];
        } else {
            return null;
        }
    }

    /**
     * 根据条件查询集合里所有文档
     * @param $collection
     * @param int $skip 跳过行数 如果不需要过滤传0
     * @param int $limit 返回限制条数 如果不需要过滤传0
     * @param array $condition 查询条件数组 可不传，但是如果有排序，必须传，可以传空
     * @param array $sortFields 排序字段数组 可不传，但是如果有限定返回字段，必须传，可以传空
     * @param array $fields 返回限定字段数组
     * @param array $queryOptions 查询的参数，慎用,allowDiskUse:true
     * @return array
     */
    public function fetchAll($collection, $condition = array(), $skip = 0, $limit = 0, $sortFields = array(), $fields = array(), $queryOptions = [])
    {
        try {
            $skip = intval($skip);
            $limit = intval($limit);
            if (!is_array($fields)) {
                $fields = [];
            }
            if (!is_array($sortFields)) {
                $sortFields = [];
            }
            if (!isset($fields['_id'])) {
                $fields['_id'] = 0;
            }
            // lgh: 2020/07/19, default sort for mongodb 4.2
            if (!$sortFields) {
                if (env('DB_DEBUG') || env('APP_ENV') == 'development') {
                    error_log('[WARN] Sort not set: ' . json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)));
                }
                if (!$fields || !isset($fields['_id']) || $fields['_id'] != 0) $sortFields = ['_id' => 1];
            }
            $options = array(
                'projection' => $fields,
                'limit' => $limit,
                'skip' => $skip,
                'sort' => $sortFields,
                'comment' => json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)),
            );
            // add new query options:allowDiskUse
            if (isset($queryOptions['allowDiskUse']) && $queryOptions['allowDiskUse']) {
                if (env('DB_DEBUG') || env('APP_ENV') == 'development') {
                    error_log('[WARN] allowDiskUse: ' . json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)));
                }
                $options['allowDiskUse'] = true;
            }
            if (env('DB_DEBUG') || env('APP_ENV') == 'development') error_log(/*'[' . $this->getUrl() . ' // ' . $this->getDbName() . ']' . */'fetchRows: ' . $collection . ' ' . json_encode($condition) . ' ' . json_encode($sortFields));
            $query = new Query($condition, $options);
            $result = $this->getRServer()->executeQuery($this->dbName . '.' . $collection, $query);
            $result->setTypeMap(array('root' => 'array', 'document' => 'array', 'array' => 'array'));
            return $result->toArray();
        } catch (\Exception $ex) {
            throw new DatabaseException($ex->getMessage() . (env('DB_DEBUG') || env('APP_ENV') == 'development' ? ' ' . $this->url . ' -> ' . json_encode($condition) : '') . ' -> ' . var_export($options, true), $ex->getCode());
        }
    }

    public function fetchRowTmp($collection, $condition = array(), $fields = array(), $options = array())
    {
        $options = array(
            'projection' => $fields,
            'limit' => 1
        );
        $query = new Query($condition, $options);
        $result = $this->getRServer()->executeQuery($this->dbName . '.' . $collection, $query);
        $result->setTypeMap(array('root' => 'array', 'document' => 'array', 'array' => 'array'));
        $arr = $result->toArray();
        if ($arr && count($arr) > 0) {
            return $arr[0];
        } else {
            return null;
        }
    }

    /**
     * 根据条件查询集合里所有文档
     * @param $collection
     * @param int $skip 跳过行数 如果不需要过滤传0
     * @param int $limit 返回限制条数 如果不需要过滤传0
     * @param array $condition 查询条件数组 可不传，但是如果有排序，必须传，可以传空
     * @param array $sortFields 排序字段数组 可不传，但是如果有限定返回字段，必须传，可以传空
     * @param array $fields 返回限定字段数组
     * @param array $queryOptions 查询的参数，慎用,allowDiskUse:true
     * @return array
     */
    public function fetchALLTmp($collection, $condition = array(), $skip = 0, $limit = 0, $sortFields = array(), $fields = array(), $queryOptions = [])
    {
        $skip = intval($skip);
        $limit = intval($limit);
        // lgh: 2020/07/19, default sort for mongodb 4.2
        if (!$sortFields) {
            $sortFields = ['_id' => 1];
        }
        $options = array(
            'projection' => $fields,
            'limit' => $limit,
            'skip' => $skip,
            'sort' => $sortFields
        );
        // add new query options:allowDiskUse
        if (isset($queryOptions['allowDiskUse']) && $queryOptions['allowDiskUse']) {
            $options['allowDiskUse'] = true;
        }
        $query = new Query($condition, $options);
        $result = $this->getRServer()->executeQuery($this->dbName . '.' . $collection, $query);
        $result->setTypeMap(array('root' => 'array', 'document' => 'array', 'array' => 'array'));
        return $result->toArray();
    }

    /**
     * @param $timestamp
     * @return mixed
     */
    public static function getMongoDate($timestamp = 0)
    {
        if (is_numeric($timestamp)) {
            return (!$timestamp) ? new UTCDateTime(time() * 1000) : new UTCDateTime($timestamp * 1000);
        } elseif ($timestamp instanceof UTCDateTime) {
            return $timestamp;
        }
        return new UTCDateTime();
    }

    /**
     * @param MongoDB\BSON\Timestamp $Date
     * @param $type
     * @param $format
     * @return mixed
     */
    public static function getDate($Date, $type = 1, $format = null)
    {
        if (!$format) {
            $format = $type === 1 ? 'Y-m-d' : 'Y-m-d H:i:s';
        }
        return date($format, ((string)$Date) / 1000);
    }

    /**
     * @param $collection
     * @param array $pipeline
     * @param array $options
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function aggregate($collection, array $pipeline, array $options = [])
    {
        if (empty($pipeline)) {
            throw new InvalidArgumentException('$pipeline is empty');
        }
        if (env('DB_DEBUG') || env('APP_ENV') == 'development') error_log(/*'[' . $this->getUrl() . ' // ' . $this->getDbName() . ']' . */'aggregate: ' . $collection . ' ' . json_encode($pipeline));
        foreach ($pipeline as $idx => $item) {
            if (isset($item['$match']) && !isset($item['$match']['$comment'])) {
                $pipeline[$idx]['$match']['$comment'] = json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2));
                break;
            }
        }
        $cmd = [
            'aggregate' => $collection,
            'pipeline' => $pipeline,
        ];
        if (isset($options['useCursor']) && $options['useCursor']) {
            $cmd['cursor'] = isset($options["batchSize"])
                ? ['batchSize' => $options["batchSize"]]
                : new stdClass;
        } else {
            $cmd['cursor'] = new stdClass;
        }
        if (isset($options['allowDiskUse'])) {
            $cmd['allowDiskUse'] = $options['allowDiskUse'] ? true : false;
        }
        $readPreference = isset($this->options['readPreference']) ? $this->options['readPreference'] : null;
        $command = new Command($cmd);
        try {
            $result = $this->getServer()->executeCommand($this->dbName, $command, $readPreference);
            //LoggerManager::getMongoInstance()->info($this->logMessage());
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage());
        }
        $result->setTypeMap(array('root' => 'array', 'document' => 'array', 'array' => 'array'));
        $arr = $result->toArray();
        return $arr;
    }

    /**
     * distinct query
     * @param string $collection
     * @param string $fieldName distinct的字段只能用一个字段
     * @param array|object $condition 查询条件
     * @return mixed
     */
    public function distinct($collection, $fieldName, $condition)
    {
        $cmd = [
            'distinct' => $collection,
            'key' => $fieldName,
        ];
        if (!empty($condition)) {
            $cmd['query'] = $condition;
        }
        $command = new Command($cmd);
        $readPreference = isset($this->options['readPreference']) ? $this->options['readPreference'] : null;
        try {
            $result = $this->getRServer()->executeCommand($this->dbName, $command, $readPreference);
            $arr = current($result->toArray());
            if (!isset($arr->values) || !is_array($arr->values)) {
                throw new UnexpectedValueException('distinct command did not return a "values" array');
            }
            return $arr->values;
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage());
        }
    }

    /**
     * @param mixed $_id
     * @return array
     */
    public function logMessage($_id = 0)
    {
        $trace = debug_backtrace();
        if (is_array($trace) && count($trace) > 1) {
            $hop = $trace[1];
            $message = array();
            $message['lineNumber'] = isset($hop['line']) ? $hop['line'] : null;
            $message['fileName'] = isset($hop['file']) ? $hop['file'] : null;
            $message['method'] = isset($hop['function']) ? $hop['function'] : null;
            $message['className'] = isset($hop['class']) ? $hop['class'] : null;
            $message['uid'] = $this->uid;
            $message['aid'] = $this->aid;
            if (isset($hop['args']) && is_array($hop['args'])) {
                $message['collection'] = $hop['args'][0];
                switch ($message['method']) {
                    case 'update':
                        $message['condition'] = $hop['args'][1];
                        $message['data'] = $hop['args'][2];
                        break;
                    case 'insert':
                        $message['data'] = $hop['args'][1];
                        break;
                    case 'aggregate':
                        $message['condition'] = $hop['args'][1];
                        break;
                    case 'batchInsert':
                        $message['data'] = $hop['args'][1];
                        break;
                    case 'delete':
                        $message['condition'] = $hop['args'][1];
                        break;
                }
            }
            if (is_array($_id) && $message['method'] == 'batchInsert') {
                $newMessage = array();
                foreach ($_id as $key => $value) {
                    $message['connected_id'] = $value;
                    $newMessage[$key] = $message;
                }
                return $newMessage;
            } else {
                $message['connected_id'] = $_id;
                return $message;
            }
        }
        return array();
    }

    protected function save()
    {
    }

    /**
     * @param $update1 1insert2update3delete
     * @param $update2
     * @param array $update3
     * @throws Exception
     */
    public function transactions($update1, $update2, $update3 = array())
    {
        //prepare
        $init1 = ($update1['type'] != 1) ? $this->fetchAll($update1['collection'], $update1['condition']) : null;
        $init2 = ($update2['type'] != 1) ? $this->fetchAll($update2['collection'], $update2['condition']) : null;
        $init3 = (count($update3) > 0 && $update3['type'] != 1) ? $this->fetchAll($update3['collection'], $update3['condition']) : null;

        //transaction init
        if ($init1 && $init2 && ((count($update3) === 0) || $init3)) {
            if (count($update3) === 0) {
                $bind = array(
                    '_id' => $this->getMongoObjectId(), 'source1' => $update1['collection'], 'source2' => $update2['collection'],
                    'init1' => $init1, 'init2' => $init2, 'state' => 'initial'
                );
                $result = $this->save('transactions', $bind);
            } else {
                $bind = array(
                    '_id' => $this->getMongoObjectId(), 'source1' => $update1['collection'], 'source2' => $update2['collection'],
                    'source3' => $update3['collection'], 'init1' => $init1, 'init2' => $init2, 'init3' => $init3, 'state' => 'initial'
                );
                $result = $this->save('transactions', $bind);
            }
        } else {
            throw new Exception('the condition of collection is error');
        }
        //transaction pending
        if ($result) {
            $pending = $this->update('transactions', array('_id' => $bind['_id']), array('$set' => array('state' => 'pending')));
            if ($pending) {
                if ($update1['type'] == 1) {
                    $update1['new_data']['pendingTransactions'] = array($bind['_id']);
                    $pending1 = $this->insert($update1['collection'], $update1['new_data'], 1);
                } elseif ($update1['type'] == 2) {
                    $update1['condition']['pendingTransactions'] = array('$ne' => $bind['_id']);
                    $update1['new_data']['$push'] = array('pendingTransactions' => $bind['_id']);
                    $pending1 = $this->update($update1['collection'], $update1['condition'], $update1['new_data']);
                } else {
                    $pending1 = $this->delete($update1['collection'], $update1['condition']);
                }
                if ($update2['type'] == 1) {
                    $update2['new_data']['pendingTransactions'] = array($bind['_id']);
                    $pending2 = $this->insert($update2['collection'], $update2['new_data']);
                } elseif ($update2['type'] == 2) {
                    $update2['condition']['pendingTransactions'] = array('$ne' => $bind['_id']);
                    $update2['new_data']['$push'] = array('pendingTransactions' => $bind['_id']);
                    $pending2 = $this->update($update2['collection'], $update2['condition'], $update2['new_data']);
                } else {
                    $pending2 = $this->delete($update2['collection'], $update2['condition']);
                }
                if (!(count($update3) === 0)) {
                    if ($update3['type'] == 1) {
                        $update3['new_data']['pendingTransactions'] = array($bind['_id']);
                        $pending3 = $this->insert($update3['collection'], $update3['new_data']);
                    } elseif ($update3['type'] == 2) {
                        $update3['condition']['pendingTransactions'] = array('$ne' => $bind['_id']);
                        $update3['new_data']['$push'] = array('pendingTransactions' => $bind['_id']);
                        $pending3 = $this->update($update3['collection'], $update3['condition'], $update3['new_data']);
                    } else {
                        $pending3 = $this->delete($update3['collection'], $update3['condition']);
                    }
                } else {
                    $pending3 = null;
                }
                if ($pending1 && $pending2 && (isset($pending3) || count($update3) === 0)) {
                    $commit = $this->update('transactions', array('_id' => $bind['_id']), array('$set' => array('state' => 'commit')));
                    if ($commit) {
                        //remove pending
                        if ($update1['type'] != 3) {
                            $remove1 = $this->update($update1['collection'], array(), array('$pull' => array('pendingTransactions' => $bind['_id'])));
                        }
                        if ($update2['type'] != 3) {
                            $remove2 = $this->update($update2['collection'], array(), array('$pull' => array('pendingTransactions' => $bind['_id'])));
                        }
                        if (count($update3) === 0) {
                            if ($update3['type'] != 3) {
                                $remove3 = $this->update($update3['collection'], array(), array('$pull' => array('pendingTransactions' => $bind['_id'])));
                            }
                        }
                        if ((isset($remove1) || ($update1['type'] == 3)) && (isset($remove2) || ($update2['type'] == 3)) && (isset($remove3) || count($update3) === 0)) {
                            try {
                                $this->update('transactions', array('_id' => $bind['_id']), array('$set' => array('state' => 'down')));
                                return true;
                            } catch (\MongoDB\Driver\Exception\Exception $e) {
                                //LoggerManager::getMongoInstance()->warn('the transaction is end, but there is error message ' . $e->getMessage());
                                return true;
                            }
                        } else {
                            //rollback remove pending status;处理策略，重新进行一次remove pending操作,如果不行，就写入数据库日志错误，但是返回true
                            if ($update1['type'] != 3) {
                                $remove1 = $this->update($update1['collection'], array(), array('$pull' => array('pendingTransactions' => $bind['_id'])));
                            }
                            if ($update2['type'] != 3) {
                                $remove2 = $this->update($update2['collection'], array(), array('$pull' => array('pendingTransactions' => $bind['_id'])));
                            }
                            if (count($update3) === 0) {
                                if ($update3['type'] != 3) {
                                    $remove3 = $this->update($update3['collection'], array(), array('$pull' => array('pendingTransactions' => $bind['_id'])));
                                }
                            }
                            if ((isset($remove1) || ($update1['type'] == 3)) && (isset($remove2) || ($update2['type'] == 3)) && (isset($remove3) || count($update3) === 0)) {
                                try {
                                    $this->update('transactions', array('_id' => $bind['_id']), array('$set' => array('state' => 'down')));
                                    return true;
                                } catch (\MongoDB\Driver\Exception\Exception $e) {
                                    //LoggerManager::getMongoInstance()->warn('second remove pending:the transaction is end, but there is error message ' . $e->getMessage());
                                    return true;
                                }
                            } else {
                                //LoggerManager::getMongoInstance()->warn('second remove pending:the transaction is commit, but remove pending  is error');
                                return true;
                            }
                        }
                    } else {
                        //rollback from  commit status
                    }
                } else {
                    //rollback from pending status;处理策略 将数据回滚过去 一个一个回滚
                    if ($pending1) {
                        if ($update1['type'] == 1) {
                            //插入回滚
                            $rollback_pending = $this->delete($update1['collection'], array('id' => $pending1));
                        } elseif ($update1['type'] == 2) {
                            //更新回滚
                            $rollback_pending = $this->save($update1['collection'], $init1);
                        } else {
                            //删除回滚
                            $rollback_pending = $this->insert($update1['collection'], $init1);
                        }
                    }
                    if ($pending2) {
                        if ($update2['type'] == 1) {
                            $rollback_pending = $this->delete($update1['collection'], array('id' => $pending2));
                            //插入回滚
                        } elseif ($update2['type'] == 2) {
                            //更新回滚
                            $rollback_pending = $this->save($update1['collection'], $init2);
                        } else {
                            //删除回滚
                            $rollback_pending = $this->insert($update1['collection'], $init2);
                        }
                    }

                    if ($pending3) {
                        if ($update3['type'] == 1) {
                            //插入回滚
                        } elseif ($update3['type'] == 2) {
                            //更新回滚
                        } else {
                            //删除回滚
                        }
                    }
                }
            } else {
                //rollback before pending
                throw new Exception('find the data error in pending');
            }
        } else {
            throw new Exception('add the data to  transactions collection is error');
        }
    }

    /**
     * Get file object store in the GridFS collection.
     *
     * @param string|ObjectId $object_id
     * @param string $collection_prefix GridFS collection prefix name, such as 'fs'
     * @return array
     */
    public function getGridFSFile($object_id, $collection_prefix = 'fs')
    {
        if (!($object_id instanceof ObjectID)) {
            // try to convert id to ObjectID
            $object_id = new ObjectID($object_id);
        }
        $file_object = $this->fetchRow($collection_prefix . '.files', array(
            '_id' => $object_id
        ));

        return $file_object;
    }

    /**
     * Get file chunks stored in GridFS.
     *
     * @param string|ObjectId $object_id
     * @param string $collection_prefix GridFS chunks collection prefix name, such as 'fs'
     * @return array
     */
    public function getGridFSFileChunks($object_id, $collection_prefix)
    {
        if (!($object_id instanceof ObjectID)) {
            // try to convert id to ObjectID
            $object_id = new ObjectID($object_id);
        }

        return $this->fetchAll($collection_prefix . '.chunks', array(
            'files_id' => $object_id
        ));
    }

    /**
     * Download the file from Mongo DB.
     *
     * @param string|ObjectId $object_id
     * @param string $collection_prefix GridFS chunks collection prefix name, such as 'fs'
     * @param string $file_name Output file name
     * @param string $content_type Content type for the file
     */
    public function downloadFSFile($object_id, $collection_prefix = 'fs', $file_name = 'report.pdf', $content_type = 'application/pdf')
    {
        $file = $this->getGridFSFile($object_id, $collection_prefix);
        if ($file) {
            header('Content-Type: ' . $content_type);
            header('Content-Disposition: attachment; filename="' . $file_name . '"');

            $chunks = $this->getGridFSFileChunks($object_id, $collection_prefix);
            if ($chunks) {
                header('Content-length: ' . $file['length']);
                foreach ($chunks as $chunk) {
                    if ($chunk['data'] instanceof \MongoDB\BSON\Binary) {
                        echo $chunk['data']->getData();
                    }
                }
            }
        }

        exit();
    }
}
