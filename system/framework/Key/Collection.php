<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */
namespace Key;


use Key\Abstracts\BaseRecord;
use Traversable;

/**
 * This class provides a common interface used by many other classes
 * in a Key framework application that manage "collections" of data
 * that must be inspected and/or manipulated.
 *
 * @package Key
 * @author Guanghui Li <liguanghui2006@163.com>
 */
class Collection implements \Countable, \IteratorAggregate, \ArrayAccess
{

    protected $data = array();


    /**
     * Create new collection
     *
     * @param array $items Pre-populate collection with this key-value array
     */
    public function __construct(array $items)
    {
        foreach ($items as $key => $item) {
            $this->set($key, $item);
        }
    }

    /**
     * Get collection item for key.
     *
     * @param string $key The data key
     * @param mixed $default The default value to return when key does not exists in the collection, default is null
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return $this->has($key) ? $this->data[$key] : $default;
    }

    /**
     * Get all items in collection
     *
     * @return array The collection's source data
     */
    public function all()
    {
        return $this->data;
    }

    /**
     * Get all keys in collection
     *
     * @return array
     */
    public function keys()
    {
        return array_keys($this->data);
    }

    /**
     * Add a value into the collection
     *
     * @param string $key The data key
     * @param string $value The data value
     */
    public function add($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * Set collection item.
     *
     * @param string $key The data key
     * @param mixed $value The data value
     */
    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * Replace the items.
     *
     * @param array $items Key-value array of data to append to this collection
     */
    public function replace(array $items)
    {
        foreach ($items as $key => $item) {
            $this->set($key, $item);
        }
    }

    /**
     * Remove a value from the collection
     *
     * @param string $key The data key
     */
    public function remove($key)
    {
        unset($this->data[$key]);
    }

    /**
     * Clear the collection
     */
    public function clear()
    {
        $this->data = array();
    }

    /**
     * Determine key exists in the collection.
     *
     * @param string $key the data key
     *
     * @return bool
     */
    public function has($key)
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Count elements of an object
     *
     * @link http://php.net/manual/en/countable.count.php
     *
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count()
    {
        return count($this->data);
    }


    /********************************************************************************
     * IteratorAggregate interface
     *******************************************************************************/

    /**
     * Retrieve an external iterator
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return Traversable An instance of an object implementing <b>Iterator</b> or
     * <b>Traversable</b>
     * @since 5.0.0
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->data);
    }

    /********************************************************************************
     * ArrayAccess interface
     *******************************************************************************/

    /**
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     * @since 5.0.0
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    /**
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    public function toArray($recursive = false)
    {
        $data = array();
        if (is_array($this->data)) {
            foreach($this->data as $key => $item) {
                if (is_array($item)) {
                    if (count($item)) {
                        foreach ($item as $idx => $val) {
                            if (is_object($val) && method_exists($val, 'toArray')) {
                                $data[$key][$idx] = $val->toArray($recursive);
                            } else {
                                $data[$key][$idx] = $val;
                            }
                        }
                    } else {
                        $data[$key] = $item;
                    }
                } else {
                    if ($recursive && is_object($item) && is_subclass_of($item, '\\Key\\Abstracts\\BaseRecord')) {
                        $data[$key] = $item->toArray($recursive);
                    } else {
                        $data[$key] = $item;
                    }
                }
            }
        }
        return $data;
    }

    public function getData($name, $defaultValue = null)
    {
        $value = $this->get($name, $defaultValue);
        if ($value instanceof BaseRecord) {
            return $value->toArray();
        }
        return $value;
    }

    public function setData($name, $value)
    {
        $this->set($name, $value);
    }
}
