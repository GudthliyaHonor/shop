<?php
/**
 * Created by PhpStorm.
 * User: Kun a88wangtian@163.com
 * Date: 2016/7/4
 * Time: 14:19
 */
namespace Key\Database;


use MongoClient;
use MongoCursorException;
use InvalidArgumentException;
use MongoConnectionException;
use MongoId;
use Exception;
use Key\Log\LoggerManager;

/**
 * Class Mongo this is the mongo driver
 * @package Key\Database
 */
class Mongo
{
    /**
     *  database connection url.
     *
     * @var string
     */
    private $url;

    /**
     * The db of the database server
     * @var $string
     */
    private $dbName;
    /**
     * mongodb instance
     * @var Mongodb
     */
    protected $connection = null;

    protected $rConnection = null;
    public $errors = '';

    /**
     * mongodb constructor.
     * @param string $url
     * @param string $dbName
     * @param $dbName
     */
    public function __construct($url,$dbName)
    {
        if (!is_string($url) || (trim($url)) == '') {
            throw new InvalidArgumentException('Invalid database connection url');
        }
        if (!is_string($dbName) || (trim($dbName)) == '') {
            throw new InvalidArgumentException('Invalid database name');
        }
        $this->host = $url;
        $this->dbName = $dbName;
    }

    /**
     * the update connection
     * @param $options
     * @return \MongoDB|boolean
     */
    protected function connect($options = array())
    {
        try {
            $client = new MongoClient($this->url,$options);
            $this->connection = $client->$this->dbName;
            return $this->connection;
        } catch (MongoConnectionException $e) {
            $this->errors = $e->getMessage();
            return false;
        }
    }
    /**
     * the read connection
     * @param array $options
     * @param int $retry
     * @return \MongoDB|null
     * @throws Exception
     */
    private function rConnect($options = array(),$retry = 3)
    {
        try {
            $client = new MongoClient($this->url,$options);
            $this->rConnection = $client->$this->dbName;
            return $this->rConnection;
        } catch (MongoConnectionException $e) {
            $this->errors = $e->getMessage();
        }
        if($retry>0){
            return $this->rConnect($options, --$retry);
        }
        throw new Exception("I've tried several times getting MongoClient.. Is mongod really running?");
    }

    /**
     * @return bool|Mongodb|\MongoDB
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
     * @return bool|Mongodb|\MongoDB
     */
    protected function getRConnection()
    {
        if ($this->connection) {
            return $this->connection;
        }
        return $this->connect();
    }

    /**
     * execute the Javascript
     * @param string $command
     * @param array $options
     * @return bool 如果成功返回true 报错返回false
     */
    public function execute($command, $options = array())
    {
        if (!is_string($command) || (trim($command)) == '') {
            throw new InvalidArgumentException('Invalid command' . $command);
        }
        $result = $this->getConnection()->execute($command, $options);
        return $result;
    }

    /**
     * 判断collection是否存在
     * @param string $collectionName 集合名称
     * @return bool 如果找到 $collection 则返回 TRUE，否则返回 FALSE
     */
    private function collectionExist($collectionName)
    {
        $collections = $this->getRConnection()->getCollectionNames();
        return in_array($collectionName, $collections);
    }

    /**
     * create a new collection
     * @param string $collection
     * @return bool 创建一个新collection 如果成功返回true 失败返回false
     */
    public function createCollection($collection)
    {
        if ($this->collectionExist($collection)) {
            $this->errors = 'the collection is exists';
            return false;
        }
        try {
            $this->getConnection()->createCollection($collection);
            return true;
        } catch (MongoCursorException $e) {
            $this->errors = $e->getMessage();
        }
        return false;
    }

    /**
     * 删除集合对应的文档
     * @param string $collection 集合名称
     * @param array $condition 删除条件
     * @param array $options 不提供删除整个表行为，如果删除整个表，默认删除一条
     * @return bool
     */
    public function delete($collection, $condition, $options = array())
    {
        if (!$condition) {
            $options['justOne'] = 1;
        }
        try {
            $this->getConnection()->$collection->remove($condition, $options);
            return true;
        } catch (MongoCursorException $e) {
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
        return  $id ? new MongoId($id) : new MongoId();
    }

    /**
     * 获取一个string类型的_id
     * @return string
     */
    public function getMongoStringId()
    {
        $id = (string)new MongoId();
        return $id;
    }

    /**
     * 往指定collection插入数据，如果collection不存在，返回错误
     * @param $type 1为需要返回_id,0为不需要
     * @param $collection
     * @param array $bind
     * @param array $options
     * @return bool 返回成功或者报错
     */
    public function insert($collection, $bind, $type = 0, $options = array('w' => 1))
    {
        if (!is_string($collection) || (trim($collection)) == '') {
            throw new InvalidArgumentException('Invalid collection ' . $collection . ' the collection is must input');
        }
        if (!$this->collectionExist($collection)) {
            throw new InvalidArgumentException(' the collection ' . $collection . '  is not exists');
        }
        if ($type) {
            $bind['_id'] = new MongoId();
        }
        try {
            $result = $this->getConnection()->$collection->insert($bind, $options);
            if ($type && $result['ok'] == 1) {
                return $bind['id'];
            }
            return true;
        } catch (MongoCursorException $e) {
            $this->errors = $e->getMessage();
            return false;
        }
    }

    /**
     * 往指定的collection批量插入数据
     * @param $collection
     * @param array $bind
     * @param array $options
     * @return bool
     */
    public function batchInsert($collection, $bind = array(), $options = array('w' => 1))
    {
        if (!is_string($collection) || (trim($collection)) == '') {
            throw new InvalidArgumentException('Invalid collection ' . $collection . ' the collection is must input');
        }
        if (!$this->collectionExist($collection)) {
            throw new InvalidArgumentException(' the collection ' . $collection . '  is should be exists');
        }
        try {
            $result = $this->getConnection()->$collection->batchInsert($bind, $options);
            return $result;
        } catch (MongoCursorException $e) {
            $this->errors = $e->getMessage();
            return false;
        }
    }

    /**
     * 返回指定集合中的文档数量
     * @param array $collection
     * @param array $condition
     * @return int 如果发生错误返回false
     */
    public function count($collection, $condition)
    {
        try {
            return $this->getRConnection()->$collection->count($condition);
        } catch (MongoCursorException $e) {
            $this->errors = $e->getMessage();
            return false;
        }

    }

    /**
     * 更新集合文档
     * @param string $collection 集合名称
     * @param array $condition 更新集合的条件
     * @param array $newData 新数据
     * @param array $options 更新的操作 默认更新多条
     * @return bool/int 返回更新的条数如果更新为0条则返回true,如果错误返回false
     */
    public function update($collection, $condition, $newData, $options = array('multiple' => true))
    {
        if (!is_string($collection) || (trim($collection)) == '') {
            throw new InvalidArgumentException('Invalid collection name.');
        }
        if (!is_array($condition)) {
            throw new InvalidArgumentException('Invalid condition data. The condition data should be a array ');
        }
        try {
            $result = $this->getConnection()->$collection->update($condition, $newData, $options);
            if ($result['nModified'] == 0) {
                return true;
            }
            return $result['nModified'];
        } catch (MongoCursorException $e) {
            $this->errors = $e->getMessage();
            error_log($this->errors);
            return false;
        }
    }

    /**
     * 如果索引不存在，创建索引
     * @param string $collection
     * @param array $keys
     * @param array $options
     * @return bool
     */
    public function createIndex($collection, $keys, $options = array('unique' => false))
    {
        return $this->getConnection()->$collection->createIndex($keys, $options);
    }

    /**
     * @param $collection
     * @param $keys
     * @return array|bool
     */
    public function deleteIndex($collection, $keys)
    {
        try {
            return $this->getConnection()->$collection->deleteIndex($keys);
        } catch (MongoCursorException $e) {
            $this->errors = $e->getMessage();
        }
        return false;
    }

    /**
     * delete all the index of the collection
     * @param string $collection
     * @return array|bool
     */
    public function deleteIndexes($collection)
    {
        try {
            return $this->getConnection()->$collection->deleteIndexes();
        } catch (MongoCursorException $e) {
            $this->errors = $e->getMessage();
        }
        return false;
    }

    /**
     * 查取一行数据
     * @param string $collection 查询的集合
     * @param array $condition 查询条件
     * @param array $fields 返回想要的字段
     * @param array $options 操作
     * @return array 返回获取的一行数据
     */
    public function fetchRow($collection, $condition = array(), $fields = array(),$options = array())
    {
        if (!isset($fields['_id'])) {
            $fields['_id'] = 0;
        }
        $result = $this->getRConnection()->$collection->findOne($condition, $fields, $options);
        return $result;
    }

    /**
     * 根据条件查询集合里所有文档
     * @param $collection
     * @param int $skip 跳过行数 如果不需要过滤传0
     * @param int $limit 返回限制条数 如果不需要过滤传0
     * @param array $query 查询条件数组 可不传，但是如果有排序，必须传，可以传空
     * @param array $sortFields 排序字段数组 可不传，但是如果有限定返回字段，必须传，可以传空
     * @param array $fields 返回限定字段数组
     * @return array
     */
    public function fetchAll($collection, $query = array(), $skip = 0, $limit = 0, $sortFields = array(), $fields = array())
    {
        $skip = intval($skip);
        $limit = intval($limit);
        if (!isset($fields['_id'])) {
            $fields['_id'] = 0;
        }
        $result = $this->getRConnection()->$collection->find($query, $fields)->skip($skip)->limit($limit)->sort($sortFields);
        return iterator_to_array($result);
    }

    /**
     * 保存一个数据对象 如果对象来自数据库，则更新现有的数据库对象，否则插入对象
     * 如果$bind 带_id 有2种情况，一种插入，一种是数据库已经有了(更新）插入返回_id，更新返回true
     * 如果$bind不带_id则插入，返回true
     * @param string $collection
     * @param array $bind
     * @param array $options 选项
     * @return mixed
     */
    public function save($collection, $bind, $options = array('w' => 1))
    {
        if (!is_array($bind) || count($bind) == 0) {
            throw new InvalidArgumentException('Invalid bind data. The bind data should be a array and it should not empty.');
        }
        try {
            $result = $this->getConnection()->$collection->save($bind, $options);
        } catch (MongoCursorException $e) {
            $this->errors = $e->getMessage();
            return false;
        }
        if (isset($result['upserted']) && $result['upserted']) {
            return $result['upserted'];
        }
        if (isset($result['updatedExisting']) && $result['updatedExisting'] || $result['ok'] == 1) {
            return true;
        }
        return false;
    }

    /**
     * @param $collection
     * @param array $pipeline
     * @param array $options
     * @return array
     */
    public function aggregate($collection, $pipeline = array(), $options = array())
    {
        $result = $this->getConnection()->$collection->aggregate($pipeline, $options);
        return $result;
    }

    /**
     * @param $update1 1insert2update3delete
     * @param $update2
     * @param array $update3
     * @throws Exception
     */
    public function transactions($update1,$update2,$update3 = array())
    {
        //prepare
        $init1 = ($update1['type']!=1) ? $this->fetchAll($update1['collection'],$update1['condition']):null;
        $init2 = ($update2['type']!=1) ? $this->fetchAll($update2['collection'],$update2['condition']):null;
        $init3 = (count($update3)>0 && $update3['type']!=1) ? $this->fetchAll($update3['collection'],$update3['condition']):null;

        //transaction init
        if($init1 && $init2 && ( (count($update3)===0) || $init3)){
            if(count($update3)===0){
                $bind = array('_id'=>$this->getMongoObjectId(),'source1'=>$update1['collection'],'source2'=>$update2['collection'],
                    'init1'=>$init1,'init2'=>$init2,'state'=>'initial');
                $result = $this->save('transactions',$bind);
            }else{
                $bind = array('_id'=>$this->getMongoObjectId(),'source1'=>$update1['collection'],'source2'=>$update2['collection'],
                    'source3'=>$update3['collection'],'init1'=>$init1,'init2'=>$init2,'init3'=>$init3,'state'=>'initial');
                $result = $this->save('transactions',$bind);
            }
        }else{
            throw new Exception('the condition of collection is error');
        }
        //transaction pending
        if($result){
            $pending = $this->update('transactions',array('_id'=>$bind['_id']),array('$set'=>array('state'=>'pending')));
            if($pending){
                if($update1['type']==1){
                    $update1['new_data']['pendingTransactions'] = array($bind['_id']);
                    $pending1 = $this->insert($update1['collection'],$update1['new_data'],1);
                }elseif($update1['type']==2){
                    $update1['condition']['pendingTransactions'] = array('$ne'=>$bind['_id']);
                    $update1['new_data']['$push'] = array('pendingTransactions'=>$bind['_id']);
                    $pending1 = $this->update($update1['collection'],$update1['condition'],$update1['new_data']);
                }else{
                    $pending1 = $this->delete($update1['collection'],$update1['condition']);
                }
                if($update2['type']==1){
                    $update2['new_data']['pendingTransactions'] = array($bind['_id']);
                    $pending2 = $this->insert($update2['collection'],$update2['new_data']);
                }elseif($update2['type']==2){
                    $update2['condition']['pendingTransactions'] = array('$ne'=>$bind['_id']);
                    $update2['new_data']['$push'] = array('pendingTransactions'=>$bind['_id']);
                    $pending2 = $this->update($update2['collection'],$update2['condition'],$update2['new_data']);
                }else{
                    $pending2 = $this->delete($update2['collection'],$update2['condition']);
                }
                if(!(count($update3)===0)){
                    if($update3['type']==1){
                        $update3['new_data']['pendingTransactions'] = array($bind['_id']);
                        $pending3 = $this->insert($update3['collection'],$update3['new_data']);
                    }elseif($update3['type']==2){
                        $update3['condition']['pendingTransactions'] = array('$ne'=>$bind['_id']);
                        $update3['new_data']['$push'] = array('pendingTransactions'=>$bind['_id']);
                        $pending3 = $this->update($update3['collection'],$update3['condition'],$update3['new_data']);
                    }else{
                        $pending3 = $this->delete($update3['collection'],$update3['condition']);
                    }

                }else{
                    $pending3 = null;
                }
                if($pending1 && $pending2 && (isset($pending3) || count($update3)===0)){
                    $commit = $this->update('transactions',array('_id'=>$bind['_id']),array('$set'=>array('state'=>'commit')));
                    if($commit){
                        //remove pending
                        if($update1['type']!=3){
                            $remove1 = $this->update($update1['collection'],array(),array('$pull'=>array('pendingTransactions'=>$bind['_id'])));
                        }
                        if($update2['type']!=3){
                            $remove2 = $this->update($update2['collection'],array(),array('$pull'=>array('pendingTransactions'=>$bind['_id'])));
                        }
                        if(count($update3)===0){
                            if($update3['type']!=3){
                                $remove3 = $this->update($update3['collection'],array(),array('$pull'=>array('pendingTransactions'=>$bind['_id'])));
                            }
                        }
                        if((isset($remove1) ||($update1['type']==3))  && (isset($remove2) ||($update2['type']==3)) && (isset($remove3) || count($update3)===0)){
                            try{
                                $this->update('transactions',array('_id'=>$bind['_id']),array('$set'=>array('state'=>'down')));
                                return true;
                            }catch (MongoCursorException $e){
                                LoggerManager::getMongoInstance()->warn('the transaction is end, but there is error message '.$e->getMessage());
                                return true;
                            }
                        }else{
                            //rollback remove pending status;处理策略，重新进行一次remove pending操作,如果不行，就写入数据库日志错误，但是返回true
                            if($update1['type']!=3){
                                $remove1 = $this->update($update1['collection'],array(),array('$pull'=>array('pendingTransactions'=>$bind['_id'])));
                            }
                            if($update2['type']!=3){
                                $remove2 = $this->update($update2['collection'],array(),array('$pull'=>array('pendingTransactions'=>$bind['_id'])));
                            }
                            if(count($update3)===0){
                                if($update3['type']!=3){
                                    $remove3 = $this->update($update3['collection'],array(),array('$pull'=>array('pendingTransactions'=>$bind['_id'])));
                                }
                            }
                            if((isset($remove1) ||($update1['type']==3))  && (isset($remove2) ||($update2['type']==3)) && (isset($remove3) || count($update3)===0)) {
                                try {
                                    $this->update('transactions', array('_id' => $bind['_id']), array('$set' => array('state' => 'down')));
                                    return true;
                                } catch (MongoCursorException $e) {
                                    LoggerManager::getMongoInstance()->warn('second remove pending:the transaction is end, but there is error message ' . $e->getMessage());
                                    return true;
                                }
                            }else{
                                LoggerManager::getMongoInstance()->warn('second remove pending:the transaction is commit, but remove pending  is error');
                                return true;
                            }
                        }

                    }else{
                        //rollback from  commit status
                    }

                }else{
                    //rollback from pending status;处理策略 将数据回滚过去 一个一个回滚
                    if($pending1){
                        if($update1['type']==1){
                            //插入回滚
                            $rollback_pending = $this->delete($update1['collection'],array('id'=>$pending1));
                        }elseif($update1['type']==2){
                            //更新回滚
                            $rollback_pending = $this->save($update1['collection'],$init1);
                        }else{
                            //删除回滚
                            $rollback_pending = $this->insert($update1['collection'],$init1);
                        }
                    }
                    if($pending2){
                        if($update2['type']==1){
                            $rollback_pending = $this->delete($update1['collection'],array('id'=>$pending2));
                            //插入回滚
                        }elseif($update2['type']==2){
                            //更新回滚
                            $rollback_pending = $this->save($update1['collection'],$init2);
                        }else{
                            //删除回滚
                            $rollback_pending = $this->insert($update1['collection'],$init2);
                        }
                    }

                    if($pending3){
                        if($update3['type']==1){
                            //插入回滚
                        }elseif($update3['type']==2){
                            //更新回滚
                        }else{
                            //删除回滚
                        }
                    }
                }

            }else{
                //rollback before pending
                throw new Exception('find the data error in pending');
            }
        }else{
            throw new Exception('add the data to  transactions collection is error');
        }
    }
}