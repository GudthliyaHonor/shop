<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace App\Transfers;


use Key\Collection;

class TransferFactory
{
    /**
     * @var array
     */
    private static $instances = array();

    /**
     * @param $value
     * @param $properties
     * @return \App\Transfers\Transfer|boolean;
     */
    public static function getInstance($value, $properties = array())
    {
        if (!$properties || !is_array($properties)) {
            $properties = array();
        }

        $newProps = new Collection($properties);

        // data_type for employee template
        $data_type = $newProps->get('data_type', 0);
        if (!is_int($data_type)) {
            $data_type = intval($data_type);
        }
        if ($data_type === 0) {
            // type for collection template
            $class = $newProps->get('type', 'string');
        } else {
            $class = isset(Transfer::$data_types[$data_type]) ? Transfer::$data_types[$data_type] : 'string';
        }

        //$data_type = isset($properties['data_type']) && $properties['data_type'] ? $properties['data_type'] : 0;
        //$class = isset(Transfer::$data_types[$data_type]) ? Transfer::$data_types[$data_type] : (isset($properties['type']) ? $properties['type'] : 'string');
        $className = '\\App\\Transfers\\' . ucfirst($class) . 'Transfer';
        //if (!isset(self::$instances[$className])) {
        //self::$instances[$className] = new $className($value, $properties);
        //}
        //self::$instances[$className]->setValue($value);
        //self::$instances[$className]->setProperties($properties);
        //return self::$instances[$className];
       //Tools::log('[TransferFactory] >>>>>>>>>>>>>>>>>'.$className);
        return new $className($value, $properties);
    }
}