<?php
/**
 * 手动修改数据库的时候备份
 * User: Kun a88wangtian@163.com
 * Date: 2016/7/4
 */
namespace Key\Database;

/**
 * Class Mongodb
 * @package Key\Database
 */
class Backup
{
    /**
     * @var Mongodb
     */
    private $db;

    /**
     * @var string
     */
    private $prefix_coll = 'backup';

    /**
     * @var string
     */
    private $operator = '';

    /**
     * @var int
     */
    private $count = 0;
    /**
     * @var array
     */
    private $init_data = array();

    /**
     * @var string
     */
    private $context = '';
    /**
     * @var
     */
    private $back_collection;

    /**
     * Backup constructor.
     * @param $operator
     */
    public function __construct($operator = '')
    {
        $this->db = MongoManager::getInstance();
        $this->operator = $operator;
    }

    /**
     * @param string $collection 需要备份的表
     * @param array $condition 备份的条件
     * @param string $context 备份的原因备注
     * @return bool|int
     * @throws \Exception
     */
    public function insertBack($collection, $condition, $context = '')
    {
        $this->back_collection = $collection;
        $this->context = $context;
        $data = $this->db->fetchAll($collection, $condition);
        if (!$data || count($data) == 0) {
            throw new \Exception('THe Backup Data is Error');
        }
        $this->count = count($data);
        foreach ($data as $key => $item) {
            $data[$key] = array_merge($item, $this->getInitData());
        }
        return $this->db->batchInsert($this->getCollection(), $data);
    }

    /**
     * @return string
     */
    private function getCollection()
    {
        $coll = date('Y', time());
        return $this->prefix_coll . $coll;
    }

    /**
     * @return array
     */
    private function getInitData()
    {
        if ($this->init_data && count($this->init_data) > 0) {
            return $this->init_data;
        }
        $this->init_data = array(
            'back_id' => $this->db->getMongoStringId(),
            'back_collection' => $this->back_collection,
            'operator' => $this->operator,
            'count' => $this->count,
            'back_time' => $this->db->getMongoDate(),
            'context' => $this->context
        );
        return $this->init_data;
    }

    /**
     * 获取备份的列表,一次一条数据
     */
    public function getList()
    {
        //todo
    }

    /**
     * 获取每次的备份的详情情况
     * @param $id
     */
    public function view($id)
    {
        //todo
    }
}