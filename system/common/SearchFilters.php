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


use App\Records\SearchFilter;
use App\Transfers\TransferFactory;
use MongoDB\BSON\Regex;

/**
 * Class SearchFilters
 * @package App\Common
 */
class SearchFilters
{
    const OP_BETWEEN = 1; // int, datetime
    const OP_EQUAL = 2; // option, datetime
    const OP_NO_EQUAL = 3; // option, level, string, datetime, int
    const OP_BEFORE = 4; // datetime
    const OP_AFTER = 5; // datetime
    const OP_CONTAIN = 6; // option, level, string, int
    const OP_NO_CONTAIN = 7; // level, string
    const OP_ABOVE = 8; // level, int
    const OP_BELOW = 9; // level, int

    public static $banStrings = array('{', '}', '$ne', '$gte', '$gt', '$lt', '$lte', '$in', '$nin', '$exists', '$where', 'tojson', '==', 'db.');

    /**
     * @var int
     */
    protected $aid = 0;

    /**
     * @var \App\Records\SearchFilter[]
     */
    protected $filters = array();

    protected $fuzzy_fields = array('real_name', 'eno', 'phone');

    protected $fields = array();

    protected $lookups = array();

    protected $unwinds = array();

    /**
     * SearchFilters constructor.
     * @param \App\Records\SearchFilter[] $filters
     * @param int $aid
     */
    public function __construct($filters, $aid)
    {
        if (!is_array($filters)) {
            throw new \InvalidArgumentException('Invalid filters');
        }

        foreach ($filters as $filter) {
            if (!($filter instanceof SearchFilter)) {
                throw new \InvalidArgumentException('Invalid filter');
            }
            $this->filters[] = $filter;
        }
        $this->aid = $aid;
    }

    public function getLookups()
    {
        return $this->lookups;
    }

    public function getUnwinds()
    {
        return $this->unwinds;
    }
    /**
     * Convert data.
     *
     * @param string $name field name
     * @param mixed $val field value
     * @return mixed
     */
    protected function transfer($name, $val)
    {

        $name = explode('.', $name);
        $name = $name[count($name) - 1];
        $found = null;
        if (!isset($this->fields[$name])) {
            foreach ($this->fields as $item) {
                if (isset($item['name']) && $item['name'] == $name) {
                    $found = $item;
                }
            }
        } else {
            $found = $this->fields[$name];
        }
        if (!$found) {
            //Tools::log('Invalid field name:' . $name, 'SearchFilter');
            throw new \InvalidArgumentException('Invalid field name:' . $name);
        }
        $transfer = TransferFactory::getInstance($val, $found);
        return $transfer->convert();
    }

    /**
     * Retrieves the search filter condition.
     *
     * @return array
     */
    public function getParsedConditions()
    {
        $condition = array();

        foreach ($this->filters as $filter) {
            $value = $filter->getData('value');

            if (is_string($value) || is_int($value)) {
                $value = array($value);
            }

            if (!is_array($value) || count(array_filter($value, function ($v) {
                    return $v !== false && trim($v) !== '';
                })) === 0
            ) {
                continue;
            }

            $this->getParsedCondition($filter, $condition);
        }

        return $condition;
    }

    /**
     * Check if the name is banned.
     *
     * @param string $name filter name
     * @return bool
     */
    protected function checkIsSafeStr($name)
    {
        return !in_array(strtolower($name), static::$banStrings);
    }

    /**
     * Handle single filter.
     *
     * @param \App\Records\SearchFilter $filter
     * @param $condition
     */
    protected function getParsedCondition($filter, &$condition)
    {
        $operator = (int)$filter->getData('operator');
        $name = (string) $filter->getData('name');

        if (!$this->checkIsSafeStr($name)) {
            //Tools::log('[SearchFilters] WARNING: unsafe filter name ' . $name);
            return;
        }

        $name = $this->getSearchFieldName($name);

        $value = $filter->getData('value');
        switch ($operator) {
            case self::OP_BETWEEN:
                if (count($value) > 0) {
                    if (!empty($value[0])) {
                        $condition[$name]['$gte'] = $this->transfer($name, $value[0]);
                    }
                }
                if (count($value) > 1) {
                    if (!empty($value[1])) {
                        $condition[$name]['$lte'] = $this->transfer($name, $value[1]);
                    }
                }
                break;
            case self::OP_EQUAL:
                $condition[$name] = $this->transfer($name, $value[0]);
                break;
            case self::OP_NO_EQUAL:
                $condition[$name] = array(
                    '$ne' => $this->transfer($name, $value[0])
                );
                break;
            case self::OP_BEFORE:
                $condition[$name] = array(
                    '$lt' => $this->transfer($name, $value[0])
                );
                break;
            case self::OP_AFTER:
                $condition[$name] = array(
                    '$gt' => $this->transfer($name, $value[0])
                );
                break;
            case self::OP_ABOVE:
            case self::OP_BELOW:
                $data = RestModel::getRestData('/dictionary/view', 'dictionary', array('id' => $name));
                $dictionary = $data && isset($data['dictionary']) ? $data['dictionary'] : null;
                if ($dictionary) {
                    $value = $value[0];
                    $valid = array();

                    $options = $dictionary['options'];
                    if ($operator == self::OP_BELOW) {
                        $options = array_reverse($options);
                    }
                    foreach ($options as $option) {
                        if ($option['name'] == $value) {
                            $valid[] = $value;
                            break;
                        } else {
                            $valid[] = $option['name'];
                        }
                    }
                    $condition[$name] = array(
                        '$in' => $valid
                    );
                }
                break;
            case self::OP_NO_CONTAIN:
                if (in_array($name, $this->fuzzy_fields)) {
                    $in = array();
                    foreach ($value as $item) {
                        $in[] = new Regex($item, 'im');
                    }
                    $condition[$name] = array(
                        '$not' => array(
                            '$in' => $in
                        )
                    );
                } else {
                    $condition[$name] = array(
                        '$not' => array(
                            '$in' => $value
                        )
                    );
                }
                break;
            case self::OP_CONTAIN:
                $value = is_array($value) ? array_filter($value) : null;
                if ($value) {
                    //if (in_array($name, $this->fuzzy_fields)) {
                    $ors = array();
                    foreach ($value as $item) {
                        $ors[] = array(
                            $name => new Regex($item, 'im')
                        );
                    }
                    if (isset($condition['$or'])) {
                        $condition['$and'] = array(
                            array('$or' => $condition['$or']),
                            array('$or' => $ors)
                        );
                        unset($condition['$or']);
                    } elseif (isset($condition['$and'])) {
                        $condition['$and'][] = array('$or' => $ors);
                    } else {
                        $condition['$or'] = $ors;
                    }
                    //} else {
                    //    $condition[$name] = array(
                    //        //'$exists' => true,
                    //        '$in' => $value
                    //    );
                    //}
                }
                break;
            default:
                $value = is_array($value) ? $value : null;
                if ($value) {
                    $ors = array();
                    foreach ($value as $item) {
                        $ors[] = array(
                            $name => $item
                        );
                    }
                    if (isset($condition['$or'])) {
                        $condition['$and'] = array(
                            array('$or' => $condition['$or']),
                            array('$or' => $ors)
                        );
                        unset($condition['$or']);
                    } elseif (isset($condition['$and'])) {
                        $condition['$and'][] = array('$or' => $ors);
                    } else {
                        $condition['$or'] = $ors;
                    }
                }
                //Tools::log('the condition is');
                //Tools::log($condition);
        }
    }

    protected function getSearchFieldName($name)
    {
        return $name;
    }
}