<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace App\Common;


use Key\Database\MongoManager;
use Key\Records\Pagination;

class Search
{
    protected $uid;
    /**
     * @var int
     */
    protected $aid;

    /**
     * DB collection name.
     *
     * @var string
     */
    protected $collection;

    /**
     * Init conditions.
     *
     * @var array
     */
    protected $initCondition;

    /**
     * @var \App\Records\SearchFilter[]|null
     */
    protected $filters = array();

    /**
     * @var \App\Records\SearchFilter[]|null
     */
    protected $excludes = array();

    /**
     * @var string[]
     */
    protected $sorts = array();

    /**
     * @var array()
     */
    protected $fields = array();

    /**
     * @var \Key\Records\Pagination
     */
    protected $pagination = null;

    /**
     * @var bool
     */
    protected $only_count = false;

    /**
     * @var int
     */
    protected $total = 0;

    /**
     * Condition generated.
     *
     * @var array
     */
    protected $internal = array();

    protected $unwind = null;

    protected $lookups = null;

    protected $unwinds = null;

    /**
     * Search constructor.
     *
     * @param int $aid
     * @param string $collection collection name
     * @param array $condition
     */
    public function __construct($aid = 0, $collection, $condition = array())
    {
        $this->aid = $aid;
        $this->collection = $collection;
        $this->initCondition = $condition;
    }

    /**
     * @param mixed $uid
     */
    public function setUid($uid)
    {
        $this->uid = $uid;
    }

    /**
     * @return array|null
     */
    public function getLookups()
    {
        return $this->lookups;
    }

    public function getUnwinds()
    {
        return $this->unwinds;
    }

    /**
     * @return \App\Records\SearchFilter[]|null
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * @param $filters
     * @return $this
     */
    public function setFilters($filters)
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * @return \App\Records\SearchFilter[]|null
     */
    public function getExcludes()
    {
        return $this->excludes;
    }

    /**
     * @param \App\Records\SearchFilter[]|null $excludes
     * @return $this
     */
    public function setExcludes($excludes)
    {
        $this->excludes = $excludes;
        return $this;
    }

    /**
     * @return \string[]
     */
    public function getSorts()
    {
        return $this->sorts ? $this->sorts : array();
    }

    /**
     * @param $sorts
     * @return $this
     */
    public function setSorts($sorts)
    {
        $this->sorts = $sorts;

        return $this;
    }

    /**
     * @return array
     */
    public function getFields()
    {
        if (!is_array($this->fields)) {
            $this->fields = array();
        }
        if (!isset($this->fields['_id'])) {
            $this->fields['_id'] = 0;
        }
        return $this->fields ? $this->fields : array();
    }

    /**
     * @param $fields
     * @return $this
     */
    public function setFields($fields)
    {
        if (!is_array($fields)) {
            //Tools::log('[Search] Invalid fields set: ' . var_export($fields, true));
            $fields = array();
        }
        $this->fields = $fields;

        return $this;
    }

    /**
     * @return \Key\Records\Pagination
     */
    public function getPagination()
    {
        if (!$this->pagination) {
            $this->pagination = new Pagination('pagination', array(
                'page' => 1,
                'itemsPerPage' => Constants::DEFAULT_ITEMS_PER_PAGE
            ));
        }

        return $this->pagination;
    }

    /**
     * @param $pagination
     * @return $this
     */
    public function setPagination($pagination)
    {
        if (!($pagination instanceof Pagination)) {
            throw new \InvalidArgumentException('Invalid pagination record');
        }
        $this->pagination = $pagination;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isOnlyCount()
    {
        return $this->only_count;
    }

    /**
     * @param $only_count
     * @return $this
     */
    public function setOnlyCount($only_count)
    {
        $this->only_count = $only_count;

        return $this;
    }

    /**
     * Generate the all conditions.
     *
     * @return array
     */
    protected function generate()
    {
        $condition = array();

        $searchFilters = new SearchFilters($this->filters, $this->aid);
        $filter_condition = $searchFilters->getParsedConditions();
        $condition = array_merge($condition, $filter_condition);

        if ($this->excludes) {
            foreach ($this->excludes as $exclude) {
                $condition[$exclude['name']] = array(
                    '$nin' => $exclude['value']
                );
            }
        }

        // TODO: ACL check

        return $condition;
    }

    /**
     * @return array
     */
    protected function getInitCondition()
    {
        return $this->initCondition;
    }

    /**
     * @param array $initCondition
     */
    public function setInitCondition($initCondition)
    {
        $this->initCondition = $initCondition;
    }

    /**
     * @param string|array $unwind
     * @return $this
     */
    public function setUnwind($unwind)
    {
        $this->unwind = $unwind;
        return $this;
    }

    /**
     * Get generated condition.
     *
     * @return array
     */
    public function getCondition()
    {
        if ($this->internal) {
            return $this->internal;
        } else {
            $condition = $this->generate();
            if (is_array($condition)) {
                $this->internal = array_merge($this->getInitCondition(), $condition);

                return $this->internal;
            }

            return array();
        }

    }

    /**
     * Get the count via the condition.
     *
     * @return int
     */
    public function total()
    {
        $condition = $this->getCondition();
        $db = MongoManager::getInstance($this->uid, $this->aid);

        return $db->count($this->collection, $condition);
    }

    /**
     * Query.
     *
     * @return array
     */
    public function query()
    {
        $condition = $this->getCondition();
        $db = MongoManager::getInstance($this->uid, $this->aid);

        if ($this->unwind) {
            $pipeline = array(
                array('$match' => $condition),
                array('$unwind' => $this->unwind)
            );

            if ($this->getPagination()->getItemsPerPage()) {
                $pipeline[] = array('$limit' => $this->getPagination()->getItemsPerPage());
                $pipeline[] = array('$skip' => $this->getPagination()->getOffset());
            }

            $pipeline[] = array('$project' => $this->getFields());
            if ($this->getSorts()) {
                $pipeline[] = array('$sort' => $this->getSorts());
            }

            return $db->aggregate($this->collection, $pipeline);
        } else {
            return $db->fetchAll($this->collection, $condition, $this->getPagination()->getOffset(),
                $this->getPagination()->getItemsPerPage(), $this->getSorts(), $this->getFields());
        }
    }

}