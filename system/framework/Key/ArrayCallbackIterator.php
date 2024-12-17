<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2019 keylogic.com
 * @version 1.0.0
 * @link http://www.keylogic.com
 */

namespace Key;


use ArrayIterator;

class ArrayCallbackIterator extends ArrayIterator {

    private $data = [];

    private $callback;
    public function __construct($value, $callback)
    {
        parent::__construct($value);
        $this->data = $value;
        $this->callback = $callback;
    }

    public function current()
    {
        $value = parent::current();
        return call_user_func($this->callback, $value);
    }

    public function offsetGet($offset)
    {
        error_log('>>>>>>>>>>>' . var_export($offset, true));
        return null;
    }
}
